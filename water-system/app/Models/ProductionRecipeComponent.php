<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionRecipeComponent extends Model
{
    protected $fillable = [
        'production_recipe_id',
        'inventory_item_id',
        'quantity_required',
    ];

    protected function casts(): array
    {
        return [
            'quantity_required' => 'decimal:3',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(ProductionRecipe::class, 'production_recipe_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
