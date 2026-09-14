<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class WaterMeterReading extends Model
{
    protected $fillable = [
        'water_meter_id',
        'idempotency_key',
        'reading_value',
        'normalized_litres',
        'delta_litres',
        'status',
        'review_reason',
        'reading_at',
        'received_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'reading_value' => 'decimal:3',
            'normalized_litres' => 'decimal:3',
            'delta_litres' => 'decimal:3',
            'reading_at' => 'datetime',
            'received_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function waterMeter(): BelongsTo
    {
        return $this->belongsTo(WaterMeter::class);
    }

    public function stockMovement(): MorphOne
    {
        return $this->morphOne(StockMovement::class, 'source');
    }
}
