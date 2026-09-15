<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\ProductionRecipe;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionRunControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_production_run_form_with_configured_recipe(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$recipe, , , $finishedProduct] = $this->makeRecipe();

        $this->actingAs($admin)
            ->get(route('production.runs.create'))
            ->assertOk()
            ->assertSee('Record Production Run')
            ->assertSee($finishedProduct->sku)
            ->assertSee($finishedProduct->name);
    }

    public function test_admin_can_complete_production_run_from_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$recipe, $rawWater, $bottle, $finishedProduct] = $this->makeRecipe();

        $this->addStock($rawWater, 20);
        $this->addStock($bottle, 20);

        $this->actingAs($admin)
            ->post(route('production.runs.store'), [
                'production_recipe_id' => $recipe->id,
                'quantity_produced' => 10,
                'occurred_at' => '2026-09-15T09:00',
                'notes' => 'Morning production run.',
            ])
            ->assertRedirect(route('production.runs.create'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('production_runs', [
            'production_recipe_id' => $recipe->id,
            'finished_product_id' => $finishedProduct->id,
            'quantity_produced' => 10,
            'status' => 'completed',
            'created_by' => $admin->id,
            'notes' => 'Morning production run.',
        ]);

        $this->assertEquals(15.0, (float) $rawWater->stockMovements()->sum('quantity_delta'));
        $this->assertEquals(10.0, (float) $bottle->stockMovements()->sum('quantity_delta'));
        $this->assertEquals(10.0, (float) $finishedProduct->stockMovements()->sum('quantity_delta'));
    }

    public function test_fractional_finished_units_are_rejected_by_form_validation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$recipe, $rawWater, $bottle, $finishedProduct] = $this->makeRecipe();

        $this->addStock($rawWater, 20);
        $this->addStock($bottle, 20);

        $this->actingAs($admin)
            ->from(route('production.runs.create'))
            ->post(route('production.runs.store'), [
                'production_recipe_id' => $recipe->id,
                'quantity_produced' => 0.5,
                'occurred_at' => '2026-09-15T09:00',
            ])
            ->assertRedirect(route('production.runs.create'))
            ->assertSessionHasErrors('quantity_produced');

        $this->assertDatabaseCount('production_runs', 0);
        $this->assertEquals(20.0, (float) $rawWater->stockMovements()->sum('quantity_delta'));
        $this->assertEquals(20.0, (float) $bottle->stockMovements()->sum('quantity_delta'));
        $this->assertEquals(0.0, (float) $finishedProduct->stockMovements()->sum('quantity_delta'));
    }

    public function test_failed_form_run_does_not_change_any_inventory(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$recipe, $rawWater, $bottle, $finishedProduct] = $this->makeRecipe();

        $this->addStock($rawWater, 20);
        $this->addStock($bottle, 5);

        $this->actingAs($admin)
            ->from(route('production.runs.create'))
            ->post(route('production.runs.store'), [
                'production_recipe_id' => $recipe->id,
                'quantity_produced' => 10,
                'occurred_at' => '2026-09-15T09:00',
            ])
            ->assertRedirect(route('production.runs.create'))
            ->assertSessionHasErrors('quantity_produced');

        $this->assertDatabaseCount('production_runs', 0);
        $this->assertEquals(20.0, (float) $rawWater->stockMovements()->sum('quantity_delta'));
        $this->assertEquals(5.0, (float) $bottle->stockMovements()->sum('quantity_delta'));
        $this->assertEquals(0.0, (float) $finishedProduct->stockMovements()->sum('quantity_delta'));
    }

    private function makeRecipe(): array
    {
        $rawWater = InventoryItem::create([
            'sku' => 'RAW-WATER',
            'name' => 'Raw Borehole Water',
            'category' => 'raw_water',
            'unit' => 'litre',
            'reorder_level' => 0,
            'is_sellable' => false,
            'is_active' => true,
        ]);

        $bottle = InventoryItem::create([
            'sku' => 'EB-500ML',
            'name' => 'Empty Bottle 500 ml',
            'category' => 'packaging_material',
            'unit' => 'unit',
            'reorder_level' => 0,
            'is_sellable' => false,
            'is_active' => true,
        ]);

        $finishedProduct = InventoryItem::create([
            'sku' => 'FW-500ML',
            'name' => 'Finished Water 500 ml',
            'category' => 'finished_product',
            'unit' => 'unit',
            'reorder_level' => 0,
            'is_sellable' => true,
            'is_active' => true,
        ]);

        $recipe = ProductionRecipe::create([
            'finished_product_id' => $finishedProduct->id,
            'name' => 'Finished Water 500 ml Recipe',
            'output_quantity' => 1,
            'is_active' => true,
        ]);

        $recipe->components()->createMany([
            [
                'inventory_item_id' => $rawWater->id,
                'quantity_required' => 0.5,
            ],
            [
                'inventory_item_id' => $bottle->id,
                'quantity_required' => 1,
            ],
        ]);

        return [$recipe, $rawWater, $bottle, $finishedProduct];
    }

    private function addStock(InventoryItem $item, float $quantity): void
    {
        StockMovement::create([
            'inventory_item_id' => $item->id,
            'movement_type' => 'opening_balance',
            'quantity_delta' => $quantity,
            'occurred_at' => '2026-09-15 08:00:00',
        ]);
    }
}
