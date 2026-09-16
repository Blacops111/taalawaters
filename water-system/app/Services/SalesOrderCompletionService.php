<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesOrderCompletionService
{
    public function complete(SalesOrder $salesOrder, User $user): SalesOrder
    {
        return DB::transaction(function () use ($salesOrder, $user) {
            $lockedOrder = SalesOrder::query()
                ->lockForUpdate()
                ->findOrFail($salesOrder->id);

            if ($lockedOrder->status !== SalesOrder::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'sales_order' => 'Only draft sales can be completed.',
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
                    'sales_order' => 'Add at least one finished product before completing the sale.',
                ]);
            }

            $requirements = $items
                ->groupBy('inventory_item_id')
                ->map(fn ($group) => (float) $group->sum('quantity'));

            $itemIds = $requirements->keys()->sort()->values();

            $lockedInventory = InventoryItem::query()
                ->whereIn('id', $itemIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($requirements as $inventoryItemId => $requiredQuantity) {
                $inventoryItem = $lockedInventory->get($inventoryItemId);

                if (
                    ! $inventoryItem
                    || ! $inventoryItem->is_active
                    || ! $inventoryItem->is_sellable
                    || $inventoryItem->category !== 'finished_product'
                ) {
                    throw ValidationException::withMessages([
                        'sales_order' => 'One or more sale items are no longer valid finished products.',
                    ]);
                }

                if ($requiredQuantity <= 0 || abs($requiredQuantity - round($requiredQuantity)) > 0.000001) {
                    throw ValidationException::withMessages([
                        'sales_order' => 'Sale quantities must be positive whole finished units.',
                    ]);
                }

                $availableBalance = (float) StockMovement::query()
                    ->where('inventory_item_id', $inventoryItem->id)
                    ->sum('quantity_delta');

                if ($availableBalance + 0.0005 < $requiredQuantity) {
                    throw ValidationException::withMessages([
                        'quantity' => sprintf(
                            'Insufficient %s stock. Required %.0f unit(s), available %.3f unit(s).',
                            $inventoryItem->name,
                            $requiredQuantity,
                            $availableBalance
                        ),
                    ]);
                }
            }

            $reference = 'SALE-'.str_pad((string) $lockedOrder->id, 8, '0', STR_PAD_LEFT);
            $totalAmount = $items->sum('line_total');

            $lockedOrder->update([
                'reference' => $reference,
                'status' => SalesOrder::STATUS_COMPLETED,
                'total_amount' => $totalAmount,
            ]);

            foreach ($requirements as $inventoryItemId => $requiredQuantity) {
                StockMovement::create([
                    'inventory_item_id' => $inventoryItemId,
                    'movement_type' => 'sale',
                    'quantity_delta' => -$requiredQuantity,
                    'source_type' => SalesOrder::class,
                    'source_id' => $lockedOrder->id,
                    'created_by' => $user->id,
                    'occurred_at' => $lockedOrder->sale_at,
                    'reference' => $reference,
                    'notes' => 'Finished stock sold on '.$reference.'.',
                ]);
            }

            return $lockedOrder->fresh([
                'customer',
                'creator',
                'items.inventoryItem',
                'stockMovements.inventoryItem',
            ]);
        }, 3);
    }
}
