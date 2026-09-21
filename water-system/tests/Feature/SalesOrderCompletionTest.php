<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\SalesOrderCompletionService;
use Database\Seeders\AccountingAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesOrderCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(AccountingAccountSeeder::class)->run();
    }

    public function test_sale_completion_deducts_finished_stock_and_generates_reference(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->createFinishedProduct();
        $this->addStock($product, 10, $admin);
        $order = $this->createDraftWithItem($admin, $product, 3, 30);

        $completed = app(SalesOrderCompletionService::class)->complete($order, $admin);

        $this->assertSame(SalesOrder::STATUS_COMPLETED, $completed->status);
        $this->assertSame('SALE-00000001', $completed->reference);
        $this->assertSame('90.00', $completed->total_amount);

        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $product->id,
            'movement_type' => 'sale',
            'quantity_delta' => -3,
            'source_type' => SalesOrder::class,
            'source_id' => $order->id,
            'created_by' => $admin->id,
            'reference' => 'SALE-00000001',
        ]);

        $this->assertSame(7.0, (float) StockMovement::query()
            ->where('inventory_item_id', $product->id)
            ->sum('quantity_delta'));
    }

    public function test_sale_completion_rejects_insufficient_stock_and_rolls_back(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->createFinishedProduct();
        $this->addStock($product, 2, $admin);
        $order = $this->createDraftWithItem($admin, $product, 3, 30);

        try {
            app(SalesOrderCompletionService::class)->complete($order, $admin);
            $this->fail('Expected insufficient-stock validation error was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }

        $this->assertSame(SalesOrder::STATUS_DRAFT, $order->fresh()->status);
        $this->assertNull($order->fresh()->reference);
        $this->assertDatabaseMissing('stock_movements', [
            'movement_type' => 'sale',
            'source_type' => SalesOrder::class,
            'source_id' => $order->id,
        ]);
        $this->assertSame(2.0, (float) StockMovement::query()
            ->where('inventory_item_id', $product->id)
            ->sum('quantity_delta'));
    }

    public function test_duplicate_product_lines_are_aggregated_before_stock_check(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->createFinishedProduct();
        $this->addStock($product, 5, $admin);
        $order = $this->createDraft($admin);

        $order->items()->create([
            'inventory_item_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 30,
            'line_total' => 90,
        ]);
        $order->items()->create([
            'inventory_item_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 30,
            'line_total' => 90,
        ]);
        $order->update(['total_amount' => 180]);

        try {
            app(SalesOrderCompletionService::class)->complete($order, $admin);
            $this->fail('Expected aggregate insufficient-stock validation error was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }

        $this->assertSame(SalesOrder::STATUS_DRAFT, $order->fresh()->status);
        $this->assertDatabaseMissing('stock_movements', [
            'movement_type' => 'sale',
            'source_type' => SalesOrder::class,
            'source_id' => $order->id,
        ]);
    }

    public function test_empty_draft_cannot_be_completed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->createDraft($admin);

        try {
            app(SalesOrderCompletionService::class)->complete($order, $admin);
            $this->fail('Expected empty-sale validation error was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('sales_order', $exception->errors());
        }

        $this->assertSame(SalesOrder::STATUS_DRAFT, $order->fresh()->status);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_completed_sale_cannot_deduct_stock_twice(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->createFinishedProduct();
        $this->addStock($product, 10, $admin);
        $order = $this->createDraftWithItem($admin, $product, 2, 30);
        $service = app(SalesOrderCompletionService::class);

        $service->complete($order, $admin);

        try {
            $service->complete($order->fresh(), $admin);
            $this->fail('Expected already-completed validation error was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('sales_order', $exception->errors());
        }

        $this->assertSame(1, StockMovement::query()
            ->where('movement_type', 'sale')
            ->where('source_type', SalesOrder::class)
            ->where('source_id', $order->id)
            ->count());
        $this->assertSame(8.0, (float) StockMovement::query()
            ->where('inventory_item_id', $product->id)
            ->sum('quantity_delta'));
    }

    private function createFinishedProduct(array $overrides = []): InventoryItem
    {
        return InventoryItem::create(array_merge([
            'sku' => 'FW-COMPLETE-TEST',
            'name' => 'Finished Water Completion Test',
            'category' => 'finished_product',
            'unit' => 'unit',
            'reorder_level' => 0,
            'retail_price' => 30,
            'wholesale_price' => 25,
            'is_sellable' => true,
            'is_active' => true,
        ], $overrides));
    }

    private function addStock(InventoryItem $product, int $quantity, User $user): void
    {
        StockMovement::create([
            'inventory_item_id' => $product->id,
            'movement_type' => 'opening_balance',
            'quantity_delta' => $quantity,
            'created_by' => $user->id,
            'occurred_at' => '2026-09-16 08:00:00',
            'reference' => 'OPEN-COMPLETE-TEST',
        ]);
    }

    private function createDraft(User $user): SalesOrder
    {
        return SalesOrder::create([
            'customer_id' => null,
            'sale_type' => SalesOrder::TYPE_WALK_IN,
            'reference' => null,
            'status' => SalesOrder::STATUS_DRAFT,
            'sale_at' => '2026-09-16 12:00:00',
            'total_amount' => 0,
            'created_by' => $user->id,
        ]);
    }

    private function createDraftWithItem(
        User $user,
        InventoryItem $product,
        int $quantity,
        int $unitPrice,
    ): SalesOrder {
        $order = $this->createDraft($user);
        $lineTotal = $quantity * $unitPrice;

        $order->items()->create([
            'inventory_item_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
        ]);
        $order->update(['total_amount' => $lineTotal]);

        return $order;
    }
}
