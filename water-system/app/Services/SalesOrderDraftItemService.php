<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesOrderDraftItemService
{
    public function addItem(
        SalesOrder $salesOrder,
        InventoryItem $inventoryItem,
        int $quantity,
    ): SalesOrderItem {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be at least 1 whole finished unit.',
            ]);
        }

        return DB::transaction(function () use ($salesOrder, $inventoryItem, $quantity) {
            $lockedOrder = SalesOrder::query()
                ->lockForUpdate()
                ->findOrFail($salesOrder->id);

            if ($lockedOrder->status !== SalesOrder::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'sales_order' => 'Items can only be added while the sale is still a draft.',
                ]);
            }

            $lockedItem = InventoryItem::query()
                ->lockForUpdate()
                ->findOrFail($inventoryItem->id);

            if (
                ! $lockedItem->is_active
                || ! $lockedItem->is_sellable
                || $lockedItem->category !== 'finished_product'
            ) {
                throw ValidationException::withMessages([
                    'inventory_item_id' => 'Only active sellable finished products can be added to a sale.',
                ]);
            }

            $priceColumn = $lockedOrder->sale_type === SalesOrder::TYPE_BUSINESS
                ? 'wholesale_price'
                : 'retail_price';

            $priceLabel = $lockedOrder->sale_type === SalesOrder::TYPE_BUSINESS
                ? 'wholesale'
                : 'retail';

            $configuredPrice = $lockedItem->{$priceColumn};

            if ($configuredPrice === null) {
                throw ValidationException::withMessages([
                    'inventory_item_id' => 'The '.$priceLabel.' price for '.$lockedItem->name.' has not been configured yet.',
                ]);
            }

            $unitPriceCents = (int) round(((float) $configuredPrice) * 100);

            if ($unitPriceCents < 0) {
                throw ValidationException::withMessages([
                    'inventory_item_id' => 'The configured sales price is invalid.',
                ]);
            }

            $lineTotalCents = $quantity * $unitPriceCents;
            $maxMoneyCents = 9_999_999_999_999_999;

            if ($lineTotalCents > $maxMoneyCents) {
                throw ValidationException::withMessages([
                    'quantity' => 'This sale line total is too large.',
                ]);
            }

            $item = $lockedOrder->items()->create([
                'inventory_item_id' => $lockedItem->id,
                'quantity' => $quantity,
                'unit_price' => number_format($unitPriceCents / 100, 2, '.', ''),
                'line_total' => number_format($lineTotalCents / 100, 2, '.', ''),
            ]);

            $lockedOrder->update([
                'total_amount' => $lockedOrder->items()->sum('line_total'),
            ]);

            return $item->fresh(['inventoryItem', 'salesOrder']);
        }, 3);
    }

    public function removeItem(
        SalesOrder $salesOrder,
        SalesOrderItem $salesOrderItem,
    ): SalesOrder {
        return DB::transaction(function () use ($salesOrder, $salesOrderItem) {
            $lockedOrder = SalesOrder::query()
                ->lockForUpdate()
                ->findOrFail($salesOrder->id);

            if ($lockedOrder->status !== SalesOrder::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'sales_order' => 'Items can only be removed while the sale is still a draft.',
                ]);
            }

            $lockedItem = SalesOrderItem::query()
                ->whereKey($salesOrderItem->id)
                ->where('sales_order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedItem) {
                throw ValidationException::withMessages([
                    'sales_order_item' => 'The selected sale item does not belong to this draft.',
                ]);
            }

            $lockedItem->delete();

            $lockedOrder->update([
                'total_amount' => $lockedOrder->items()->sum('line_total'),
            ]);

            return $lockedOrder->fresh([
                'customer',
                'creator',
                'items.inventoryItem',
            ]);
        }, 3);
    }
}
