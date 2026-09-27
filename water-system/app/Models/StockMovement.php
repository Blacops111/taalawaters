<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'movement_type',
        'quantity_delta',
        'source_type',
        'source_id',
        'created_by',
        'occurred_at',
        'reference',
        'notes',
        'value_delta_cents',
        'reverses_movement_id',
    ];

    protected function casts(): array
    {
        return [
            'value_delta_cents' => 'integer',
            'reverses_movement_id' => 'integer',
            'quantity_delta' => 'decimal:3',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::observe(\App\Observers\StockCostObserver::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
