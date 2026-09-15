<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\ProductionRecipe;
use App\Models\ProductionRun;
use App\Models\ProductionRunReversal;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ProductionRunReversalService;
use App\Services\ProductionRunService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductionRunReversalServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reversal_restores_consumed_materials_and_removes_finished_output(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$recipe, $rawWater, $bottle, $finishedProduct] = $this->makeRecipe();

        $this->addStock($rawWater, 100);
        $this->addStock($bottle, 100);

        $run = app(ProductionRunService::class)->complete(
            $recipe,
            10,
            $admin,
            CarbonImmutable::parse('2026-09-15 09:00:00', 'UTC')
        );

        $reversal = app(ProductionRunReversalService::class)->reverse(
            $run,
            $admin,
            'Incorrect production quantity was recorded.',
            CarbonImmutable::parse('2026-09-15 10:00:00', 'UTC')
        );

        $this->assertDatabaseHas('production_run_reversals', [
            'id' => $reversal->id,
            'production_run_id' => $run->id,
            'reversed_by' => $admin->id,
            'reference' => 'REV-'.$run->reference,
            'reason' => 'Incorrect production quantity was recorded.',
        ]);

        $this->assertDatabaseHas('production_runs', [
            'id' => $run->id,
            'status' => 'reversed',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $rawWater->id,
            'movement_type' => 'production_reversal_restore',
            'quantity_delta' => 5,
            'source_type' => ProductionRunReversal::class,
            'source_id' => $reversal->id,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $bottle->id,
            'movement_type' => 'production_reversal_restore',
            'quantity_delta' => 10,
            'source_type' => ProductionRunReversal::class,
            'source_id' => $reversal->id,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $finishedProduct->id,
            'movement_type' => 'production_reversal_output',
            'quantity_delta' => -10,
            'source_type' => ProductionRunReversal::class,
            'source_id' => $reversal->id,
        ]);

        $this->assertEquals(100.0, (float) $rawWater->stockMovements()->sum('quantity_delta'));
        $this->assertEquals(100.0, (float) $bottle->stockMovements()->sum('quantity_delta'));
        $this->assertEquals(0.0, (float) $finishedProduct->stockMovements()->sum('quantity_delta'));
    }

    public function test_same_production_run_cannot_be_reversed_twice(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$recipe, $rawWater, $bottle, $finishedProduct] = $this->makeRecipe();

        $this->addStock($rawWater, 100);
        $this->addStock($bottle, 100);

        $run = app(ProductionRunService::class)->complete($recipe, 10, $admin);

        app(ProductionRunReversalService::class)->reverse(
            $run,
            $admin,
            'First and only reversal.'
        );

        try {
            app(ProductionRunReversalService::class)->reverse(
                $run->fresh(),
                $admin,
                'Second reversal should fail.'
            );
            $this->fail('Expected the second reversal to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('production_run_id', $exception->errors());
        }

        $this->assertSame(1, ProductionRunReversal::count());
        $this->assertEquals(100.0, (float) $rawWater->stockMovements()->sum('quantity_delta'));
        $this->assertEquals(100.0, (float) $bottle->stockMovements()->sum('quantity_delta'));
        $this->assertEquals(0.0, (float) $finishedProduct->stockMovements()->sum('quantity_delta'));
    }

    public function test_reversal_is_blocked_when_finished_output_is_no_longer_available(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$recipe, $rawWater, $bottle, $finishedProduct] = $this->makeRecipe();

        $this->addStock($rawWater, 100);
        $this->addStock($bottle, 100);

        $run = app(ProductionRunService::class)->complete($recipe, 10, $admin);

        StockMovement::create([
            'inventory_item_id' => $finishedProduct->id,
            'movement_type' => 'test_sale',
            'quantity_delta' => -6,
            'occurred_at' => '2026-09-15 09:30:00',
            'created_by' => $admin->id,
            'reference' => 'TEST-SALE-001',
        ]);

        try {
            app(ProductionRunReversalService::class)->reverse(
                $run,
                $admin,
                'Attempt reversal after finished stock left inventory.'
            );
            $this->fail('Expected reversal to fail because finished stock is insufficient.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('production_run_id', $exception->errors());
        }

        $this->assertSame(0, ProductionRunReversal::count());
        $this->assertSame(
            0,
            StockMovement::query()
                ->whereIn('movement_type', [
                    'production_reversal_restore',
                    'production_reversal_output',
                ])
                ->count()
        );
        $this->assertSame('completed', $run->fresh()->status);
        $this->assertEquals(95.0, (float) $rawWater->stockMovements()->sum('quantity_delta'));
        $this->assertEquals(90.0, (float) $bottle->stockMovements()->sum('quantity_delta'));
        $this->assertEquals(4.0, (float) $finishedProduct->stockMovements()->sum('quantity_delta'));
    }

    public function test_reversal_uses_original_movements_even_if_recipe_changes_later(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$recipe, $rawWater, $bottle, $finishedProduct] = $this->makeRecipe();

        $this->addStock($rawWater, 100);
        $this->addStock($bottle, 100);

        $run = app(ProductionRunService::class)->complete($recipe, 10, $admin);

        $recipe->components()->delete();
        $recipe->components()->createMany([
            [
                'inventory_item_id' => $rawWater->id,
                'quantity_required' => 0.8,
            ],
            [
                'inventory_item_id' => $bottle->id,
                'quantity_required' => 2,
            ],
        ]);

        app(ProductionRunReversalService::class)->reverse(
            $run,
            $admin,
            'Recipe changed after original production.'
        );

        $this->assertEquals(100.0, (float) $rawWater->stockMovements()->sum('quantity_delta'));
        $this->assertEquals(100.0, (float) $bottle->stockMovements()->sum('quantity_delta'));
        $this->assertEquals(0.0, (float) $finishedProduct->stockMovements()->sum('quantity_delta'));
    }

    public function test_reversal_requires_a_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$recipe, $rawWater, $bottle] = $this->makeRecipe();

        $this->addStock($rawWater, 100);
        $this->addStock($bottle, 100);

        $run = app(ProductionRunService::class)->complete($recipe, 10, $admin);

        try {
            app(ProductionRunReversalService::class)->reverse($run, $admin, '   ');
            $this->fail('Expected an empty reversal reason to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }

        $this->assertSame(0, ProductionRunReversal::count());
        $this->assertSame('completed', $run->fresh()->status);
    }

    private function makeRecipe(): array
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
