<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\ProductionRecipe;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ProductionRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionRunReversalControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_reversal_confirmation_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$recipe, $rawWater, $bottle, $finishedProduct] = $this->makeRecipe();

        $this->addStock($rawWater, 10);
        $this->addStock($bottle, 10);

        $run = app(ProductionRunService::class)->complete($recipe, 2, $admin);

        $this->actingAs($admin)
            ->get(route('production.runs.reversal', $run))
            ->assertOk()
            ->assertSee('Reverse Production Run')
            ->assertSee($run->reference)
            ->assertSee($finishedProduct->name)
            ->assertSee('Original Inventory Movements')
            ->assertSee('Confirm Reversal');
    }

    public function test_admin_can_reverse_production_run_from_confirmation_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$recipe, $rawWater, $bottle, $finishedProduct] = $this->makeRecipe();

        $this->addStock($rawWater, 10);
        $this->addStock($bottle, 10);

        $run = app(ProductionRunService::class)->complete($recipe, 2, $admin);

        $this->assertEquals(9.0, (float) $rawWater->stockMovements()->sum('quantity_delta'));
        $this->assertEquals(8.0, (float) $bottle->stockMovements()->sum('quantity_delta'));
        $this->assertEquals(2.0, (float) $finishedProduct->stockMovements()->sum('quantity_delta'));

        $this->actingAs($admin)
            ->post(route('production.runs.reverse', $run), [
                'reason' => 'Incorrect production quantity entered during testing.',
            ])
            ->assertRedirect(route('production.runs.create'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('production_runs', [
            'id' => $run->id,
            'status' => 'reversed',
        ]);

        $this->assertDatabaseHas('production_run_reversals', [
            'production_run_id' => $run->id,
            'reversed_by' => $admin->id,
            'reference' => 'REV-'.$run->reference,
            'reason' => 'Incorrect production quantity entered during testing.',
        ]);

        $this->assertEquals(10.0, (float) $rawWater->stockMovements()->sum('quantity_delta'));
        $this->assertEquals(10.0, (float) $bottle->stockMovements()->sum('quantity_delta'));
        $this->assertEquals(0.0, (float) $finishedProduct->stockMovements()->sum('quantity_delta'));
    }

    public function test_reversal_form_requires_a_reason_before_inventory_changes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$recipe, $rawWater, $bottle, $finishedProduct] = $this->makeRecipe();

        $this->addStock($rawWater, 10);
        $this->addStock($bottle, 10);

        $run = app(ProductionRunService::class)->complete($recipe, 2, $admin);

        $this->actingAs($admin)
            ->from(route('production.runs.reversal', $run))
            ->post(route('production.runs.reverse', $run), [
                'reason' => '',
            ])
            ->assertRedirect(route('production.runs.reversal', $run))
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseMissing('production_run_reversals', [
            'production_run_id' => $run->id,
        ]);

        $this->assertDatabaseHas('production_runs', [
            'id' => $run->id,
            'status' => 'completed',
        ]);

        $this->assertEquals(9.0, (float) $rawWater->stockMovements()->sum('quantity_delta'));
        $this->assertEquals(8.0, (float) $bottle->stockMovements()->sum('quantity_delta'));
        $this->assertEquals(2.0, (float) $finishedProduct->stockMovements()->sum('quantity_delta'));
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
