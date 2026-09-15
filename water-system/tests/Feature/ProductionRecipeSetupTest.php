<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\ProductionRecipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionRecipeSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_production_recipe_setup_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $finishedProduct = $this->makeItem(
            'FW-500ML',
            'Finished Water 500 ml',
            'finished_product',
            'unit',
            true
        );

        $this->actingAs($admin)
            ->get(route('production.recipes.index'))
            ->assertOk()
            ->assertSee('Production Recipes')
            ->assertSee($finishedProduct->sku)
            ->assertSee($finishedProduct->name);
    }

    public function test_admin_can_save_exact_recipe_component_quantities(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $finishedProduct = $this->makeItem(
            'FW-500ML',
            'Finished Water 500 ml',
            'finished_product',
            'unit',
            true
        );

        $rawWater = $this->makeItem(
            'RAW-WATER',
            'Raw Borehole Water',
            'raw_water',
            'litre'
        );

        $emptyBottle = $this->makeItem(
            'EB-500ML',
            'Empty Bottle 500 ml',
            'empty_bottle',
            'unit'
        );

        $this->actingAs($admin)
            ->put(route('production.recipes.update', $finishedProduct), [
                'output_quantity' => 1,
                'components' => [
                    $rawWater->id => 0.5,
                    $emptyBottle->id => 1,
                ],
            ])
            ->assertRedirect(route('production.recipes.index'));

        $recipe = ProductionRecipe::query()
            ->where('finished_product_id', $finishedProduct->id)
            ->firstOrFail();

        $this->assertSame('Finished Water 500 ml Recipe', $recipe->name);
        $this->assertEquals(1.0, (float) $recipe->output_quantity);

        $this->assertDatabaseHas('production_recipe_components', [
            'production_recipe_id' => $recipe->id,
            'inventory_item_id' => $rawWater->id,
            'quantity_required' => 0.5,
        ]);

        $this->assertDatabaseHas('production_recipe_components', [
            'production_recipe_id' => $recipe->id,
            'inventory_item_id' => $emptyBottle->id,
            'quantity_required' => 1,
        ]);
    }

    public function test_finished_product_cannot_be_used_as_a_recipe_component(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $finishedProduct = $this->makeItem(
            'FW-500ML',
            'Finished Water 500 ml',
            'finished_product',
            'unit',
            true
        );

        $otherFinishedProduct = $this->makeItem(
            'FW-1L',
            'Finished Water 1 litre',
            'finished_product',
            'unit',
            true
        );

        $this->actingAs($admin)
            ->from(route('production.recipes.edit', $finishedProduct))
            ->put(route('production.recipes.update', $finishedProduct), [
                'output_quantity' => 1,
                'components' => [
                    $otherFinishedProduct->id => 1,
                ],
            ])
            ->assertRedirect(route('production.recipes.edit', $finishedProduct))
            ->assertSessionHasErrors('components');

        $this->assertSame(0, ProductionRecipe::count());
    }

    private function makeItem(
        string $sku,
        string $name,
        string $category,
        string $unit,
        bool $sellable = false
    ): InventoryItem {
        return InventoryItem::create([
            'sku' => $sku,
            'name' => $name,
            'category' => $category,
            'unit' => $unit,
            'reorder_level' => 0,
            'is_sellable' => $sellable,
            'is_active' => true,
        ]);
    }
}
