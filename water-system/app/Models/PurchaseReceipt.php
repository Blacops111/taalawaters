<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PurchaseReceipt extends Model
{
    protected $fillable = [
        'reference',
        'due_date',
        'purchase_request_id',
        'supplier_delivery_reference',
        'received_by',
        'received_at',
        'notes',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $model) {
            if ($model->isDirty('due_date')) {
                throw \Illuminate\Validation\ValidationException::withMessages(['due_date' => 'Posted transaction due dates cannot be changed.']);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'received_at' => 'datetime',
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }

    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function supplierPayments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }
}
