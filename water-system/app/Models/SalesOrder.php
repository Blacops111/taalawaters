<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SalesOrder extends Model
{
    public const TYPE_WALK_IN = 'walk_in';
    public const TYPE_BUSINESS = 'business';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'customer_id',
        'sale_type',
        'reference',
        'status',
        'sale_at',
        'total_amount',
        'created_by',
        'notes',
    ];

    public function customerPayments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    protected function casts(): array
    {
        return [
            'sale_at' => 'datetime',
            'total_amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(SalesOrderReversal::class);
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'source');
    }

    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class);
    }

    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }
}
