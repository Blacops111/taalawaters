<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\ProductionRecipe;
use App\Models\ProductionRun;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ProductionRunService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductionRunServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_run_consumes_components_and_adds_finished_stock_atomically(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$recipe, $rawWater, $bottle, $finishedProduct] = $this->makeRecipe();

        $this->addStock($rawWater, 100);
        $this->addStock($bottle, 100);

        $run = app(ProductionRunService::class)->complete(
            $recipe,
            10,
            $admin,
            CarbonImmutable::parse('2026-09-15 09:00:00', 'UTC'),
            'Morning production test.'
        );

        $this->assertDatabaseHas('production_runs', [
            'id' => $run->id,
            'production_recipe_id' => $recipe->id,
            'finished_product_id' => $finishedProduct->id,
            'quantity_produced' => 10,
            'recipe_output_quantity' => 1,
            'status' => 'completed',
            'created_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $rawWater->id,
            'movement_type' => 'production_consumption',
            'quantity_delta' => -5,
            'source_type' => ProductionRun::class,
            'source_id' => $run->id,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $bottle->id,
            'movement_type' => 'production_consumption',
            'quantity_delta' => -10,
            'source_type' => ProductionRun::class,
            'source_id' => $run->id,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $finishedProduct->id,
            'movement_type' => 'production_output',
            'quantity_delta' => 10,
            'source_type' => ProductionRun::class,
            'source_id' => $run->id,
        ]);

        $this->assertEquals(95.0, (float) $rawWater->stockMovements()->sum('quantity_delta'));
        $this->assertEquals(90.0, (float) $bottle->stockMovements()->sum('quantity_delta'));
        $this->assertEquals(10.0, (float) $finishedProduct->stockMovements()->sum('quantity_delta'));
        $this->assertSame('PROD-00000001', $run->reference);
    }

    public function test_insufficient_component_stock_rolls_back_the_entire_run(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$recipe, $rawWater, $bottle, $finishedProduct] = $this->makeRecipe();

        $this->addStock($rawWater, 100);
        $this->addStock($bottle, 5);

        try {
            app(ProductionRunService::class)->complete($recipe, 10, $admin);
            $this->fail('Expected production to fail because bottle stock is insufficient.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity_produced', $exception->errors());
        }

        $this->assertSame(0, ProductionRun::count());
        $this->assertSame(2, StockMovement::count());
        $this->assertEquals(100.0, (float) $rawWater->stockMovements()->sum('quantity_delta'));
        $this->assertEquals(5.0, (float) $bottle->stockMovements()->sum('quantity_delta'));
        $this->assertEquals(0.0, (float) $finishedProduct->stockMovements()->sum('quantity_delta'));
    }

    public function test_recipe_output_quantity_scales_component_consumption(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$recipe, $rawWater, $bottle, $finishedProduct] = $this->makeRecipe(2);

        $recipe->components()->delete();
        $recipe->components()->createMany([
            [
                'inventory_item_id' => $rawWater->id,
                'quantity_required' => 1,
            ],
            [
                'inventory_item_id' => $bottle->id,
                'quantity_required' => 2,
            ],
        ]);

        $this->addStock($rawWater, 20);
        $this->addStock($bottle, 20);

        app(ProductionRunService::class)->complete($recipe, 10, $admin);

        $this->assertEquals(15.0, (float) $rawWater->stockMovements()->sum('quantity_delta'));
        $this->assertEquals(10.0, (float) $bottle->stockMovements()->sum('quantity_delta'));
        $this->assertEquals(10.0, (float) $finishedProduct->stockMovements()->sum('quantity_delta'));
    }

    private function makeRecipe(float $outputQuantity = 1): array
    {
        $rawWater = InventoryItem::create([
            'sku' => 'RAW-TEST',
            'name' => 'Raw Water Test',
            'category' => 'raw_water',
            'unit' => 'litre',
            'reorder_level' => 0,
            'is_sellable' => false,
            'is_active' => true,
        ]);

        $bottle = InventoryItem::create([
            'sku' => 'BOTTLE-TEST',
            'name' => 'Bottle Test',
            'category' => 'packaging_material',
            'unit' => 'unit',
            'reorder_level' => 0,
            'is_sellable' => false,
            'is_active' => true,
        ]);

        $finishedProduct = InventoryItem::create([
            'sku' => 'FW-TEST',
            'name' => 'Finished Water Test',
            'category' => 'finished_product',
            'unit' => 'unit',
            'reorder_level' => 0,
            'is_sellable' => true,
            'is_active' => true,
        ]);

        $recipe = ProductionRecipe::create([
            'finished_product_id' => $finishedProduct->id,
            'name' => 'Test Production Recipe',
            'output_quantity' => $outputQuantity,
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
