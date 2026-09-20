<?php

namespace Tests\Feature;

use App\Models\DeliveryNote;
use App\Models\InventoryItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderReversal;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\SalesOrderCompletionService;
use App\Services\SalesOrderReversalService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesOrderReversalTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_sale_can_be_reversed_and_finished_stock_is_restored(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->createFinishedProduct();
        $this->addStock($product, 10, $admin);
        $order = $this->createDraftWithItem($admin, $product, 3, 30);

        $completed = app(SalesOrderCompletionService::class)->complete($order, $admin);

        $this->assertSame(7.0, $this->balance($product));

        $reversal = app(SalesOrderReversalService::class)->reverse(
            $completed,
            $admin,
            'Wrong quantity entered by cashier.',
            CarbonImmutable::parse('2026-09-16 15:00:00', 'UTC'),
        );

        $this->assertSame('REV-SALE-00000001', $reversal->reference);
        $this->assertSame('Wrong quantity entered by cashier.', $reversal->reason);
        $this->assertSame($admin->id, $reversal->reversed_by);
        $this->assertSame(SalesOrder::STATUS_REVERSED, $completed->fresh()->status);
        $this->assertSame(10.0, $this->balance($product));

        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $product->id,
            'movement_type' => 'sale_reversal_restore',
            'quantity_delta' => 3,
            'source_type' => SalesOrderReversal::class,
            'source_id' => $reversal->id,
            'created_by' => $admin->id,
            'reference' => 'REV-SALE-00000001',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $product->id,
            'movement_type' => 'sale',
            'quantity_delta' => -3,
            'source_type' => SalesOrder::class,
            'source_id' => $completed->id,
            'reference' => 'SALE-00000001',
        ]);
    }

    public function test_completed_sale_with_draft_delivery_note_cannot_be_reversed(): void
    {
        $this->assertDeliveryNoteBlocksReversal(DeliveryNote::STATUS_DRAFT);
    }

    public function test_completed_sale_with_dispatched_delivery_note_cannot_be_reversed(): void
    {
        $this->assertDeliveryNoteBlocksReversal(DeliveryNote::STATUS_DISPATCHED);
    }

    public function test_completed_sale_with_delivered_delivery_note_cannot_be_reversed(): void
    {
        $this->assertDeliveryNoteBlocksReversal(DeliveryNote::STATUS_DELIVERED);
    }

    public function test_completed_sale_with_only_cancelled_delivery_note_can_be_reversed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->createFinishedProduct();
        $this->addStock($product, 10, $admin);
        $order = $this->createDraftWithItem($admin, $product, 3, 30);
        $completed = app(SalesOrderCompletionService::class)->complete($order, $admin);

        $this->createDeliveryNote(
            $completed,
            $admin,
            DeliveryNote::STATUS_CANCELLED,
        );

        $reversal = app(SalesOrderReversalService::class)->reverse(
            $completed,
            $admin,
            'Cancelled delivery means the sale can be reversed.',
        );

        $this->assertSame(
            SalesOrder::STATUS_REVERSED,
            $completed->fresh()->status
        );
        $this->assertSame(10.0, $this->balance($product));
        $this->assertDatabaseHas('sales_order_reversals', [
            'id' => $reversal->id,
            'sales_order_id' => $completed->id,
        ]);
    }

    public function test_reversal_requires_a_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->createFinishedProduct();
        $this->addStock($product, 5, $admin);
        $order = $this->createDraftWithItem($admin, $product, 2, 30);
        $completed = app(SalesOrderCompletionService::class)->complete($order, $admin);

        try {
            app(SalesOrderReversalService::class)->reverse($completed, $admin, '   ');
            $this->fail('Expected a reversal-reason validation error was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }

        $this->assertSame(SalesOrder::STATUS_COMPLETED, $completed->fresh()->status);
        $this->assertDatabaseCount('sales_order_reversals', 0);
        $this->assertSame(3.0, $this->balance($product));
    }

    public function test_draft_sale_cannot_be_reversed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->createFinishedProduct();
        $order = $this->createDraftWithItem($admin, $product, 1, 30);

        try {
            app(SalesOrderReversalService::class)->reverse(
                $order,
                $admin,
                'Attempted draft reversal.',
            );
            $this->fail('Expected draft-sale reversal validation error was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('sales_order', $exception->errors());
        }

        $this->assertSame(SalesOrder::STATUS_DRAFT, $order->fresh()->status);
        $this->assertDatabaseCount('sales_order_reversals', 0);
    }

    public function test_reversed_sale_cannot_restore_stock_twice(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->createFinishedProduct();
        $this->addStock($product, 8, $admin);
        $order = $this->createDraftWithItem($admin, $product, 2, 30);
        $completed = app(SalesOrderCompletionService::class)->complete($order, $admin);
        $service = app(SalesOrderReversalService::class);

        $service->reverse($completed, $admin, 'First and only reversal.');

        try {
            $service->reverse($completed->fresh(), $admin, 'Second reversal attempt.');
            $this->fail('Expected duplicate reversal validation error was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('sales_order', $exception->errors());
        }

        $this->assertDatabaseCount('sales_order_reversals', 1);
        $this->assertSame(1, StockMovement::query()
            ->where('movement_type', 'sale_reversal_restore')
            ->count());
        $this->assertSame(8.0, $this->balance($product));
    }

    public function test_reversal_rolls_back_when_original_sale_stock_audit_is_incomplete(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->createFinishedProduct();
        $this->addStock($product, 10, $admin);
        $order = $this->createDraftWithItem($admin, $product, 4, 30);
        $completed = app(SalesOrderCompletionService::class)->complete($order, $admin);

        StockMovement::query()
            ->where('movement_type', 'sale')
            ->where('source_type', SalesOrder::class)
            ->where('source_id', $completed->id)
            ->delete();

        try {
            app(SalesOrderReversalService::class)->reverse(
                $completed,
                $admin,
                'Should fail because stock audit is incomplete.',
            );
            $this->fail('Expected incomplete-audit validation error was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('sales_order', $exception->errors());
        }

        $this->assertSame(SalesOrder::STATUS_COMPLETED, $completed->fresh()->status);
        $this->assertDatabaseCount('sales_order_reversals', 0);
        $this->assertDatabaseMissing('stock_movements', [
            'movement_type' => 'sale_reversal_restore',
        ]);
    }

    private function assertDeliveryNoteBlocksReversal(string $status): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->createFinishedProduct();
        $this->addStock($product, 10, $admin);
        $order = $this->createDraftWithItem($admin, $product, 3, 30);
        $completed = app(SalesOrderCompletionService::class)->complete($order, $admin);

        $this->createDeliveryNote($completed, $admin, $status);

        try {
            app(SalesOrderReversalService::class)->reverse(
                $completed,
                $admin,
                'This reversal must be blocked by delivery history.',
            );
            $this->fail('Expected delivery-note reversal validation error was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('sales_order', $exception->errors());
            $this->assertSame(
                'A sale with a non-cancelled delivery note cannot be reversed.',
                $exception->errors()['sales_order'][0]
            );
        }

        $this->assertSame(
            SalesOrder::STATUS_COMPLETED,
            $completed->fresh()->status
        );
        $this->assertDatabaseCount('sales_order_reversals', 0);
        $this->assertSame(7.0, $this->balance($product));
        $this->assertDatabaseMissing('stock_movements', [
            'movement_type' => 'sale_reversal_restore',
        ]);
    }

    private function createDeliveryNote(
        SalesOrder $salesOrder,
        User $user,
        string $status,
    ): DeliveryNote {
        return DeliveryNote::create([
            'reference' => 'DN-REV-'.uniqid(),
            'sales_order_id' => $salesOrder->id,
            'status' => $status,
            'recipient_name' => 'Reversal Test Receiver',
            'recipient_phone' => '+254700000001',
            'dispatched_at' => in_array(
                $status,
                [
                    DeliveryNote::STATUS_DISPATCHED,
                    DeliveryNote::STATUS_DELIVERED,
                ],
                true
            ) ? now()->subHour() : null,
            'delivered_at' => $status === DeliveryNote::STATUS_DELIVERED
                ? now()->subMinutes(30)
                : null,
            'created_by' => $user->id,
        ]);
    }

    private function createFinishedProduct(): InventoryItem
    {
        return InventoryItem::create([
            'sku' => 'FW-REVERSAL-500',
            'name' => 'Finished Water Reversal 500 ml',
            'category' => 'finished_product',
            'unit' => 'unit',
            'reorder_level' => 0,
            'retail_price' => 30,
            'wholesale_price' => 25,
            'is_sellable' => true,
            'is_active' => true,
        ]);
    }

    private function addStock(InventoryItem $product, int $quantity, User $user): void
    {
        StockMovement::create([
            'inventory_item_id' => $product->id,
            'movement_type' => 'opening_balance',
            'quantity_delta' => $quantity,
            'created_by' => $user->id,
            'occurred_at' => '2026-09-16 08:00:00',
            'reference' => 'OPEN-SALE-REVERSAL-TEST',
        ]);
    }

    private function createDraftWithItem(
        User $user,
        InventoryItem $product,
        int $quantity,
        int $unitPrice,
    ): SalesOrder {
        $order = SalesOrder::create([
            'customer_id' => null,
            'sale_type' => SalesOrder::TYPE_WALK_IN,
            'reference' => null,
            'status' => SalesOrder::STATUS_DRAFT,
            'sale_at' => '2026-09-16 12:00:00',
            'total_amount' => $quantity * $unitPrice,
            'created_by' => $user->id,
        ]);

        $order->items()->create([
            'inventory_item_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $quantity * $unitPrice,
        ]);

        return $order;
    }

    private function balance(InventoryItem $product): float
    {
        return (float) StockMovement::query()
            ->where('inventory_item_id', $product->id)
            ->sum('quantity_delta');
    }
}
