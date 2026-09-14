<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaterMeter extends Model
{
    protected $fillable = [
        'public_id',
        'inventory_item_id',
        'name',
        'serial_number',
        'protocol',
        'reading_unit',
        'location',
        'token_hash',
        'max_flow_litres_per_minute',
        'last_reading_litres',
        'last_reading_at',
        'last_seen_at',
        'is_active',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'max_flow_litres_per_minute' => 'decimal:3',
            'last_reading_litres' => 'decimal:3',
            'last_reading_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(WaterMeterReading::class);
    }
}
