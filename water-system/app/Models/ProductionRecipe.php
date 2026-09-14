<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionRecipe extends Model
{
    protected $fillable = [
        'finished_product_id',
        'name',
        'output_quantity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'output_quantity' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function finishedProduct(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'finished_product_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(ProductionRecipeComponent::class);
    }
}
