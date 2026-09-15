<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ProductionRunReversal extends Model
{
    protected $fillable = [
        'production_run_id',
        'reversed_by',
        'reversed_at',
        'reference',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'reversed_at' => 'datetime',
        ];
    }

    public function productionRun(): BelongsTo
    {
        return $this->belongsTo(ProductionRun::class);
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'source');
    }
}
