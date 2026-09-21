<?php

namespace App\Services;

use App\Models\DeliveryNote;
use App\Models\InventoryItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SalesOrderReversal;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesOrderReversalService
{
    public function reverse(
        SalesOrder $salesOrder,
        User $user,
        string $reason,
        ?CarbonInterface $reversedAt = null,
    ): SalesOrderReversal {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required to reverse a completed sale.',
            ]);
        }

        $reversedAtUtc = $reversedAt
            ? CarbonImmutable::instance($reversedAt)->utc()
            : now()->utc();

        return DB::transaction(function () use (
            $salesOrder,
            $user,
            $reason,
            $reversedAtUtc,
        ) {
            $lockedOrder = SalesOrder::query()
                ->whereKey($salesOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status !== SalesOrder::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'sales_order' => 'Only completed sales can be reversed.',
                ]);
            }

            if (SalesOrderReversal::query()
                ->where('sales_order_id', $lockedOrder->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'sales_order' => 'This sale has already been reversed.',
                ]);
            }

            $activeDeliveryNote = DeliveryNote::query()
                ->where('sales_order_id', $lockedOrder->id)
                ->where('status', '!=', DeliveryNote::STATUS_CANCELLED)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($activeDeliveryNote) {
                throw ValidationException::withMessages([
                    'sales_order' => 'A sale with a non-cancelled delivery note cannot be reversed.',
                ]);
            }

            $items = SalesOrderItem::query()
                ->where('sales_order_id', $lockedOrder->id)
                ->orderBy('inventory_item_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'sales_order' => 'The completed sale has no item history and cannot be reversed safely.',
                ]);
            }

            $originalMovements = StockMovement::query()
                ->where('source_type', SalesOrder::class)
                ->where('source_id', $lockedOrder->id)
                ->where('movement_type', 'sale')
                ->orderBy('inventory_item_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($originalMovements->isEmpty()) {
                throw ValidationException::withMessages([
                    'sales_order' => 'The original sale stock movements are missing and the sale cannot be reversed safely.',
                ]);
            }

            $expectedQuantities = $items
                ->groupBy('inventory_item_id')
                ->map(fn ($group) => (float) $group->sum('quantity'));

            $movementQuantities = [];

            foreach ($originalMovements as $movement) {
                $quantityDelta = (float) $movement->quantity_delta;

                if ($quantityDelta >= 0) {
                    throw ValidationException::withMessages([
                        'sales_order' => 'One or more original sale stock movements are invalid.',
                    ]);
                }

                $movementQuantities[$movement->inventory_item_id] =
                    ($movementQuantities[$movement->inventory_item_id] ?? 0.0)
                    + abs($quantityDelta);
            }

            if (count($movementQuantities) !== $expectedQuantities->count()) {
                throw ValidationException::withMessages([
                    'sales_order' => 'The original sale stock movements do not match the recorded sale items.',
                ]);
            }

            foreach ($expectedQuantities as $inventoryItemId => $expectedQuantity) {
                $deductedQuantity = $movementQuantities[$inventoryItemId] ?? null;

                if (
                    $deductedQuantity === null
                    || abs($deductedQuantity - $expectedQuantity) > 0.0005
                ) {
                    throw ValidationException::withMessages([
                        'sales_order' => 'The original sale stock movements do not match the recorded sale items.',
                    ]);
                }
            }

            $itemIds = $originalMovements
                ->pluck('inventory_item_id')
                ->unique()
                ->sort()
                ->values();

            $lockedInventory = InventoryItem::query()
                ->whereIn('id', $itemIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lockedInventory->count() !== $itemIds->count()) {
                throw ValidationException::withMessages([
                    'sales_order' => 'One or more inventory items from the original sale are unavailable.',
                ]);
            }

            $baseReference = $lockedOrder->reference
                ?: 'SALE-'.str_pad((string) $lockedOrder->id, 8, '0', STR_PAD_LEFT);

            $reversal = SalesOrderReversal::create([
                'sales_order_id' => $lockedOrder->id,
                'reversed_by' => $user->id,
                'reversed_at' => $reversedAtUtc,
                'reference' => 'REV-'.$baseReference,
                'reason' => $reason,
            ]);

            foreach ($originalMovements as $movement) {
                StockMovement::create([
                    'inventory_item_id' => $movement->inventory_item_id,
                    'movement_type' => 'sale_reversal_restore',
                    'quantity_delta' => abs((float) $movement->quantity_delta),
                    'source_type' => SalesOrderReversal::class,
                    'source_id' => $reversal->id,
                    'created_by' => $user->id,
                    'occurred_at' => $reversedAtUtc,
                    'reference' => $reversal->reference,
                    'notes' => 'Reversal of '.$baseReference.' stock movement #'.$movement->id.'. Reason: '.$reason,
                ]);
            }

            app(SalesAccountingService::class)->postSaleReversal(
                $reversal,
                $user,
            );

            $lockedOrder->status = SalesOrder::STATUS_REVERSED;
            $lockedOrder->save();

            return $reversal->fresh([
                'salesOrder.items.inventoryItem',
                'reversedBy',
                'stockMovements.inventoryItem',
            ]);
        }, 3);
    }
}
