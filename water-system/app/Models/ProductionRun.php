<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ProductionRun extends Model
{
    protected $fillable = [
        'production_recipe_id',
        'finished_product_id',
        'quantity_produced',
        'recipe_output_quantity',
        'status',
        'occurred_at',
        'created_by',
        'reference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_produced' => 'decimal:3',
            'recipe_output_quantity' => 'decimal:3',
            'occurred_at' => 'datetime',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(ProductionRecipe::class, 'production_recipe_id');
    }

    public function finishedProduct(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'finished_product_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(ProductionRunReversal::class);
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'source');
    }
}
