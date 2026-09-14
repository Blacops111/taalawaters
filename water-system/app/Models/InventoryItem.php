<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InventoryItem extends Model
{
    protected $fillable = [
        'sku',
        'name',
        'category',
        'unit',
        'reorder_level',
        'is_sellable',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'reorder_level' => 'decimal:3',
            'is_sellable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function waterMeters(): HasMany
    {
        return $this->hasMany(WaterMeter::class);
    }

    public function productionRecipe(): HasOne
    {
        return $this->hasOne(ProductionRecipe::class, 'finished_product_id');
    }

    public function recipeComponents(): HasMany
    {
        return $this->hasMany(ProductionRecipeComponent::class);
    }

    public function stockBalance(): float
    {
        return (float) $this->stockMovements()->sum('quantity_delta');
    }
}
