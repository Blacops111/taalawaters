<?php

namespace App\Observers;

use App\Models\InventoryCostingSetup;
use App\Models\InventoryItem;
use App\Models\ProductionRun;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryCostingService as Cost;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

class StockCostObserver
{
    public function creating(StockMovement $movement): void
    {
        $cost = app(Cost::class);
        // One lock also serializes activation with every existing stock writer.
        $setup = InventoryCostingSetup::query()->lockForUpdate()->findOrFail(1);
        if (! $setup->is_active) {
            $movement->value_delta_cents = null;
            return;
        }
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Costed stock must be written inside a transaction.');
        }
        $item = InventoryItem::query()->lockForUpdate()->findOrFail($movement->inventory_item_id);
        $balance = DB::table('inventory_cost_balances')->where('inventory_item_id', $item->id)->first();
        if (! $balance) {
            // New items can start empty; legacy quantities must never acquire an invented cost.
            if (Cost::quantityMilli($item->id) !== 0) {
                $cost->fail('Opening valuation is missing for '.$item->sku.'.');
            }
            DB::table('inventory_cost_balances')->insert([
                'inventory_item_id' => $item->id, 'opening_quantity_milli' => 0, 'opening_value_cents' => 0,
                'quantity_milli' => 0, 'value_cents' => 0, 'last_occurred_at' => $setup->activated_at,
            ]);
            $balance = DB::table('inventory_cost_balances')->where('inventory_item_id', $item->id)->first();
        }
        $at = CarbonImmutable::instance($movement->occurred_at)->utc();
        if ($at->lt(CarbonImmutable::parse($balance->last_occurred_at, 'UTC'))) {
            $cost->fail('Costed stock cannot be backdated before its opening valuation or latest movement.');
        }
        $quantity = Cost::scaled($movement->quantity_delta, 3);
        $available = (int) $balance->quantity_milli;
        $value = (int) $balance->value_cents;
        if ($quantity === 0 || $available + $quantity < 0
            || Cost::quantityMilli($item->id) !== $available) {
            $cost->fail('Stock quantities do not reconcile for '.$item->sku.'.');
        }
        $type = $movement->movement_type;
        if ($movement->reverses_movement_id !== null) {
            $original = StockMovement::findOrFail($movement->reverses_movement_id);
            $expected = ['sale_reversal_restore' => 'sale', 'production_reversal_restore' => 'production_consumption', 'production_reversal_output' => 'production_output'];
            if (($expected[$type] ?? null) !== $original->movement_type || $original->inventory_item_id !== $item->id
                || $original->value_delta_cents === null || $quantity !== -Cost::scaled($original->quantity_delta, 3)) {
                $cost->fail('The original movement has no matching recorded cost. Pre-costing transactions cannot be reversed after activation.');
            }
            if (str_starts_with($type, 'production_reversal') && StockMovement::where('inventory_item_id', $item->id)
                ->where('id', '>', $original->id)->exists()) {
                $cost->fail('This batch cannot be automatically reversed because subsequent stock activity exists. A reviewed correction is required.');
            }
            $delta = -(int) $original->value_delta_cents;
        } elseif (in_array($type, ['sale', 'production_consumption', 'damage', 'wastage', 'stock_correction_decrease'], true)) {
            if ($quantity >= 0) {
                $cost->fail('This stock movement must remove quantity.');
            }
            $delta = -Cost::issueValue(-$quantity, $available, $value);
        } elseif ($type === 'purchase_received') {
            if ($quantity <= 0 || $movement->value_delta_cents === null || $movement->value_delta_cents <= 0) {
                $cost->fail('A goods receipt requires its approved cost snapshot.');
            }
            $delta = (int) $movement->value_delta_cents;
        } elseif ($type === 'production_output') {
            $inputs = StockMovement::where('source_type', ProductionRun::class)->where('source_id', $movement->source_id)
                ->where('movement_type', 'production_consumption')->get();
            if ($quantity <= 0 || $inputs->isEmpty() || $inputs->contains(fn ($row) => $row->value_delta_cents === null)) {
                $cost->fail('Production requires recorded costs for every consumed material.');
            }
            $conversionCents = (int) \App\Models\ProductionCostAllocation::where('production_run_id', $movement->source_id)->sum('amount_cents');
            $delta = -(int) $inputs->sum('value_delta_cents') + $conversionCents;
        } elseif ($type === 'borehole_extraction' && $item->category === 'raw_water' && $quantity > 0) {
            // Explicit zero acquisition cost; eligible recorded conversion expenses can be allocated at production.
            $delta = 0;
        } elseif ($type === 'stock_correction_increase' && $quantity > 0 && $available > 0) {
            $delta = \Brick\Math\BigDecimal::of($value)->multipliedBy($quantity)->dividedBy($available, 0, \Brick\Math\RoundingMode::HALF_UP)->toInt();
        } else {
            $cost->fail('Use approved Goods Receiving or Production for new stock. Empty-stock increases and pre-costing reversals require a reviewed correction.');
        }
        $newValue = $value + $delta;
        if ($newValue < 0 || ($available + $quantity === 0 && $newValue !== 0)) {
            $cost->fail('This movement would leave an invalid inventory value. Reverse later activity first.');
        }
        $movement->value_delta_cents = $delta;
        DB::table('inventory_cost_balances')->where('inventory_item_id', $item->id)->update([
            'quantity_milli' => $available + $quantity, 'value_cents' => $newValue, 'last_occurred_at' => $at,
        ]);
    }

    public function created(StockMovement $movement): void
    {
        if ($movement->value_delta_cents === null || $movement->value_delta_cents === 0
            || ! in_array($movement->movement_type, ['damage', 'wastage', 'stock_correction_decrease', 'stock_correction_increase'], true)) {
            return;
        }
        $increase = $movement->value_delta_cents > 0;
        app(Cost::class)->postPair(User::findOrFail($movement->created_by), $movement->occurred_at,
            'Stock adjustment #'.$movement->id.': '.$movement->notes,
            $increase ? '1300' : '5500', $increase ? '5500' : '1300', abs($movement->value_delta_cents), $movement);
    }

    public function updating(StockMovement $movement): void
    {
        if ($movement->getRawOriginal('value_delta_cents') !== null) {
            throw new LogicException('Costed stock movements are immutable. Use an audited reversal.');
        }
    }

    public function deleting(StockMovement $movement): void
    {
        if ($movement->value_delta_cents !== null) {
            throw new LogicException('Costed stock movements cannot be deleted.');
        }
    }
}
