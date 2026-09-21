<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccountingAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseReceiptPostingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(AccountingAccountSeeder::class)->run();
    }

    public function test_admin_can_partially_receive_approved_goods_and_inventory_increases(): void
    {
        [$admin, $purchaseRequest, $requestItem, $item] = $this->approvedRequest(500);

        $this->actingAs($admin)
            ->post(route('purchase-requests.receipts.store', $purchaseRequest), [
                'received_at' => '2026-09-19 18:00:00',
                'supplier_delivery_reference' => 'DN-001',
                'notes' => 'First delivery.',
                'quantities' => [
                    $requestItem->id => 300,
                ],
            ])
            ->assertRedirect(route('purchase-requests.receipts.create', $purchaseRequest))
            ->assertSessionHas('success');

        $receipt = PurchaseReceipt::firstOrFail();

        $this->assertSame('GRN-00000001', $receipt->reference);
        $this->assertSame('DN-001', $receipt->supplier_delivery_reference);

        $this->assertDatabaseHas('purchase_receipt_items', [
            'purchase_receipt_id' => $receipt->id,
            'purchase_request_item_id' => $requestItem->id,
            'inventory_item_id' => $item->id,
            'quantity' => 300,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $item->id,
            'movement_type' => 'purchase_received',
            'quantity_delta' => 300,
            'source_type' => PurchaseReceipt::class,
            'source_id' => $receipt->id,
            'reference' => 'GRN-00000001',
        ]);

        $this->assertSame(300.0, $item->stockBalance());
        $this->assertSame(
            PurchaseRequest::STATUS_PARTIALLY_RECEIVED,
            $purchaseRequest->fresh()->status
        );
    }

    public function test_remaining_goods_can_be_received_and_request_becomes_received(): void
    {
        [$admin, $purchaseRequest, $requestItem, $item] = $this->approvedRequest(500);

        $this->actingAs($admin)->post(
            route('purchase-requests.receipts.store', $purchaseRequest),
            [
                'received_at' => '2026-09-19 18:00:00',
                'quantities' => [$requestItem->id => 300],
            ]
        );

        $this->actingAs($admin)
            ->post(route('purchase-requests.receipts.store', $purchaseRequest), [
                'received_at' => '2026-09-19 19:00:00',
                'quantities' => [$requestItem->id => 200],
            ])
            ->assertSessionHas('success');

        $this->assertSame(2, PurchaseReceipt::count());
        $this->assertSame(500.0, $item->stockBalance());
        $this->assertSame(
            PurchaseRequest::STATUS_RECEIVED,
            $purchaseRequest->fresh()->status
        );
    }

    public function test_received_quantity_cannot_exceed_remaining_approved_quantity(): void
    {
        [$admin, $purchaseRequest, $requestItem, $item] = $this->approvedRequest(500);

        $this->actingAs($admin)
            ->from(route('purchase-requests.receipts.create', $purchaseRequest))
            ->post(route('purchase-requests.receipts.store', $purchaseRequest), [
                'received_at' => '2026-09-19 18:00:00',
                'quantities' => [$requestItem->id => 501],
            ])
            ->assertRedirect(route('purchase-requests.receipts.create', $purchaseRequest))
            ->assertSessionHasErrors('quantities.'.$requestItem->id);

        $this->assertDatabaseCount('purchase_receipts', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(0.0, $item->stockBalance());
    }

    public function test_at_least_one_received_quantity_is_required(): void
    {
        [$admin, $purchaseRequest] = $this->approvedRequest(500);

        $this->actingAs($admin)
            ->from(route('purchase-requests.receipts.create', $purchaseRequest))
            ->post(route('purchase-requests.receipts.store', $purchaseRequest), [
                'received_at' => '2026-09-19 18:00:00',
                'quantities' => [],
            ])
            ->assertRedirect(route('purchase-requests.receipts.create', $purchaseRequest))
            ->assertSessionHasErrors('quantities');

        $this->assertDatabaseCount('purchase_receipts', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_unapproved_request_cannot_receive_goods(): void
    {
        [$admin, $purchaseRequest, $requestItem] = $this->approvedRequest(500);

        $purchaseRequest->update([
            'status' => PurchaseRequest::STATUS_SUBMITTED,
        ]);

        $this->actingAs($admin)
            ->from(route('purchase-requests.edit', $purchaseRequest))
            ->post(route('purchase-requests.receipts.store', $purchaseRequest), [
                'received_at' => '2026-09-19 18:00:00',
                'quantities' => [$requestItem->id => 100],
            ])
            ->assertRedirect(route('purchase-requests.edit', $purchaseRequest))
            ->assertSessionHasErrors('purchase_request');

        $this->assertDatabaseCount('purchase_receipts', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_goods_receiving_page_shows_requested_received_and_remaining_quantities(): void
    {
        [$admin, $purchaseRequest, $requestItem] = $this->approvedRequest(500);

        $this->actingAs($admin)
            ->post(route('purchase-requests.receipts.store', $purchaseRequest), [
                'received_at' => '2026-09-19 18:00:00',
                'quantities' => [$requestItem->id => 300],
            ]);

        $this->actingAs($admin)
            ->get(route('purchase-requests.receipts.create', $purchaseRequest))
            ->assertOk()
            ->assertSee('Goods Receiving')
            ->assertSee('500.000')
            ->assertSee('300.000')
            ->assertSee('200.000')
            ->assertSee('GRN-00000001');
    }

    private function approvedRequest(float $quantity): array
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $supplier = Supplier::create([
            'name' => 'Goods Receipt Supplier',
            'is_active' => true,
        ]);

        $item = InventoryItem::create([
            'sku' => 'CAP-GRN-POST',
            'name' => 'Goods Receipt Posting Cap',
            'category' => 'cap',
            'unit' => 'unit',
            'reorder_level' => 0,
            'is_sellable' => false,
            'is_active' => true,
        ]);

        $purchaseRequest = PurchaseRequest::create([
            'reference' => 'PREQ-00000200',
            'status' => PurchaseRequest::STATUS_APPROVED,
            'supplier_id' => $supplier->id,
            'requested_by' => $admin->id,
            'requested_at' => now()->subDay(),
            'submitted_by' => $admin->id,
            'submitted_at' => now()->subHours(4),
            'approved_by' => $admin->id,
            'approved_at' => now()->subHours(2),
        ]);

        $requestItem = $purchaseRequest->items()->create([
            'inventory_item_id' => $item->id,
            'quantity' => $quantity,
            'approved_unit_cost' => 1.25,
        ]);

        return [$admin, $purchaseRequest, $requestItem, $item];
    }
}
