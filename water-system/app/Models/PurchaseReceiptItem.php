<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReceiptItem extends Model
{
    protected $fillable = [
        'purchase_receipt_id',
        'purchase_request_item_id',
        'inventory_item_id',
        'quantity',
        'unit_cost',
        'receipt_value_cents',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'receipt_value_cents' => 'integer',
            'quantity' => 'decimal:3',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (PurchaseReceiptItem $item) {
            if ($item->getRawOriginal('unit_cost') !== null) {
                throw new \LogicException('Recorded receipt cost snapshots cannot be edited.');
            }
        });
        static::deleting(function (PurchaseReceiptItem $item) {
            if ($item->unit_cost !== null) {
                throw new \LogicException('Recorded receipt cost snapshots cannot be deleted.');
            }
        });
    }

    public function purchaseReceipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class);
    }

    public function purchaseRequestItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestItem::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
