<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\ProductionRecipe;
use App\Models\ProductionRecipeComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionRecipeFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipe_can_link_a_finished_product_to_inventory_components(): void
    {
        $finishedProduct = InventoryItem::create([
            'sku' => 'FW-TEST',
            'name' => 'Finished Test Water',
            'category' => 'finished_product',
            'unit' => 'unit',
            'reorder_level' => 0,
            'is_sellable' => true,
            'is_active' => true,
        ]);

        $rawWater = InventoryItem::create([
            'sku' => 'RAW-TEST',
            'name' => 'Raw Test Water',
            'category' => 'raw_water',
            'unit' => 'litre',
            'reorder_level' => 0,
            'is_sellable' => false,
            'is_active' => true,
        ]);

        $emptyBottle = InventoryItem::create([
            'sku' => 'BOTTLE-TEST',
            'name' => 'Empty Test Bottle',
            'category' => 'empty_bottle',
            'unit' => 'unit',
            'reorder_level' => 0,
            'is_sellable' => false,
            'is_active' => true,
        ]);

        $recipe = ProductionRecipe::create([
            'finished_product_id' => $finishedProduct->id,
            'name' => 'Test Water Recipe',
            'output_quantity' => 1,
            'is_active' => true,
        ]);

        ProductionRecipeComponent::create([
            'production_recipe_id' => $recipe->id,
            'inventory_item_id' => $rawWater->id,
            'quantity_required' => 0.5,
        ]);

        ProductionRecipeComponent::create([
            'production_recipe_id' => $recipe->id,
            'inventory_item_id' => $emptyBottle->id,
            'quantity_required' => 1,
        ]);

        $recipe->load('finishedProduct', 'components.inventoryItem');

        $this->assertSame('FW-TEST', $recipe->finishedProduct->sku);
        $this->assertCount(2, $recipe->components);
        $this->assertSame('Test Water Recipe', $finishedProduct->fresh()->productionRecipe->name);
        $this->assertEquals(
            0.5,
            (float) $rawWater->fresh()->recipeComponents->first()->quantity_required
        );
    }
}
