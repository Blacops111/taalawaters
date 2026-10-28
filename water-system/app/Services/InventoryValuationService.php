<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\InventoryCostingSetup;
use App\Models\JournalEntry;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InventoryValuationService
{
    public function report(string $asOf): array
    {
        Validator::make(['as_of' => $asOf], [
            'as_of' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now()->utc()->toDateString()],
        ])->validate();

        // One read transaction keeps opening values, movements and journals in the same
        // database snapshot under the application's default MySQL isolation level.
        return DB::transaction(function () use ($asOf) {
            $setup = InventoryCostingSetup::find(1);
            $activatedAt = $setup?->activated_at?->copy()->utc();
            $available = $setup?->is_active && $activatedAt && $asOf >= $activatedAt->toDateString();
            $report = [
                'asOf' => $asOf, 'activatedAt' => $activatedAt, 'available' => (bool) $available,
                'unavailableReason' => ! $setup?->is_active || ! $activatedAt
                    ? 'Inventory costing has not been activated.'
                    : 'Historical valuation is unavailable before costing activation. No historical costs have been estimated.',
                'rows' => collect(), 'totalValueCents' => null, 'ledgerCents' => null,
                'differenceCents' => null, 'isReconciled' => false, 'issues' => [],
            ];
            if (! $available) {
                return $report;
            }

            // Exclusive next midnight includes the entire selected UTC day, including
            // fractional seconds. Never use today's mutable quantity/value balances.
            $end = CarbonImmutable::parse($asOf, 'UTC')->addDay()->startOfDay()->toDateTimeString();
            $movements = DB::table('stock_movements')
                ->where('occurred_at', '<', $end)
                ->groupBy('inventory_item_id')
                ->select('inventory_item_id')
                ->selectRaw('COUNT(*) as movement_count')
                ->selectRaw('SUM(CAST(ROUND(quantity_delta * 1000, 0) AS SIGNED)) as stock_quantity_milli')
                ->selectRaw('SUM(CASE WHEN value_delta_cents IS NOT NULL THEN CAST(ROUND(quantity_delta * 1000, 0) AS SIGNED) ELSE 0 END) as costed_quantity_milli')
                ->selectRaw('COALESCE(SUM(value_delta_cents), 0) as movement_value_cents');

            $rows = DB::table('inventory_items as items')
                ->leftJoin('inventory_cost_balances as opening', 'opening.inventory_item_id', '=', 'items.id')
                ->leftJoinSub($movements, 'movements', 'movements.inventory_item_id', '=', 'items.id')
                ->where(function ($query) use ($end) {
                    $query->where('items.created_at', '<', $end)
                        ->orWhereNotNull('movements.inventory_item_id')
                        ->orWhere('opening.opening_quantity_milli', '!=', 0)
                        ->orWhere('opening.opening_value_cents', '!=', 0);
                })
                ->orderBy('items.sku')
                ->get([
                    'items.id', 'items.sku', 'items.name', 'items.unit', 'items.is_active',
                    'opening.inventory_item_id as snapshot_item_id',
                    'opening.opening_quantity_milli', 'opening.opening_value_cents',
                    'movements.movement_count', 'movements.stock_quantity_milli',
                    'movements.costed_quantity_milli', 'movements.movement_value_cents',
                ])
                ->map(function ($row) {
                    $row->quantity_milli = (int) $row->opening_quantity_milli + (int) $row->costed_quantity_milli;
                    $row->value_cents = (int) $row->opening_value_cents + (int) $row->movement_value_cents;
                    $row->has_issue = ($row->snapshot_item_id === null && (int) $row->movement_count > 0)
                        || $row->quantity_milli !== (int) $row->stock_quantity_milli
                        || $row->quantity_milli < 0 || $row->value_cents < 0
                        || ($row->quantity_milli === 0 && $row->value_cents !== 0);
                    $row->average_cost = $row->quantity_milli > 0
                        ? (string) BigDecimal::of($row->value_cents)->multipliedBy(10)
                            ->dividedBy($row->quantity_milli, 4, RoundingMode::HALF_UP)
                        : null;

                    return $row;
                });

            $inventoryAccount = AccountingAccount::where('code', '1300')->first();
            $ledgerCents = (int) DB::table('journal_lines as lines')
                ->join('journal_entries as entries', 'entries.id', '=', 'lines.journal_entry_id')
                ->join('accounting_accounts as accounts', 'accounts.id', '=', 'lines.accounting_account_id')
                ->where('accounts.code', '1300')
                ->where('entries.status', JournalEntry::STATUS_POSTED)
                ->whereDate('entries.entry_date', '<=', $asOf)
                ->selectRaw('COALESCE(SUM(CAST(ROUND(lines.debit * 100, 0) AS SIGNED) - CAST(ROUND(lines.credit * 100, 0) AS SIGNED)), 0) as balance_cents')
                ->first()->balance_cents;
            $totalValueCents = (int) $rows->sum('value_cents');
            $differenceCents = $totalValueCents - $ledgerCents;
            $issues = [];
            if (! $inventoryAccount || $inventoryAccount->type !== 'asset') {
                $issues[] = 'Account 1300 Inventory is missing or has an invalid account type.';
            }
            if ($rows->contains('has_issue', true)) {
                $issues[] = 'Some items have missing opening snapshots, inconsistent quantities or invalid values. Review the flagged rows before relying on this report.';
            }

            return array_replace($report, compact('rows', 'totalValueCents', 'ledgerCents', 'differenceCents', 'issues'), [
                'isReconciled' => $differenceCents === 0 && $issues === [],
            ]);
        });
    }
}
