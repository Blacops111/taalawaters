<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseReceiptFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_purchase_request_can_have_goods_receipt_without_changing_stock(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $supplier = Supplier::create([
            'name' => 'Receipt Foundation Supplier',
            'is_active' => true,
        ]);

        $item = InventoryItem::create([
            'sku' => 'CAP-GRN-FOUNDATION',
            'name' => 'Goods Receipt Test Cap',
            'category' => 'cap',
            'unit' => 'unit',
            'reorder_level' => 0,
            'is_sellable' => false,
            'is_active' => true,
        ]);

        $purchaseRequest = PurchaseRequest::create([
            'reference' => 'PREQ-00000100',
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
            'quantity' => 500,
        ]);

        $receipt = PurchaseReceipt::create([
            'reference' => 'GRN-00000001',
            'purchase_request_id' => $purchaseRequest->id,
            'supplier_delivery_reference' => 'DN-TEST-001',
            'received_by' => $admin->id,
            'received_at' => now(),
            'notes' => 'Foundation only; no stock posting yet.',
        ]);

        $receipt->items()->create([
            'purchase_request_item_id' => $requestItem->id,
            'inventory_item_id' => $item->id,
            'quantity' => 300,
        ]);

        $this->assertTrue($receipt->purchaseRequest->is($purchaseRequest));
        $this->assertTrue($receipt->receiver->is($admin));
        $this->assertCount(1, $receipt->fresh()->items);
        $this->assertSame(0.0, $item->stockBalance());
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_goods_receipt_line_keeps_purchase_request_line_and_inventory_item_links(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $supplier = Supplier::create([
            'name' => 'Receipt Link Supplier',
            'is_active' => true,
        ]);

        $item = InventoryItem::create([
            'sku' => 'LABEL-GRN-FOUNDATION',
            'name' => 'Goods Receipt Test Label',
            'category' => 'label',
            'unit' => 'unit',
            'reorder_level' => 0,
            'is_sellable' => false,
            'is_active' => true,
        ]);

        $purchaseRequest = PurchaseRequest::create([
            'reference' => 'PREQ-00000101',
            'status' => PurchaseRequest::STATUS_APPROVED,
            'supplier_id' => $supplier->id,
            'requested_by' => $admin->id,
            'requested_at' => now(),
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $requestItem = $purchaseRequest->items()->create([
            'inventory_item_id' => $item->id,
            'quantity' => 1000,
        ]);

        $receipt = PurchaseReceipt::create([
            'reference' => 'GRN-00000002',
            'purchase_request_id' => $purchaseRequest->id,
            'received_by' => $admin->id,
            'received_at' => now(),
        ]);

        $receiptItem = $receipt->items()->create([
            'purchase_request_item_id' => $requestItem->id,
            'inventory_item_id' => $item->id,
            'quantity' => 250,
        ]);

        $this->assertTrue($receiptItem->purchaseReceipt->is($receipt));
        $this->assertTrue($receiptItem->purchaseRequestItem->is($requestItem));
        $this->assertTrue($receiptItem->inventoryItem->is($item));

        $this->assertDatabaseHas('purchase_receipt_items', [
            'purchase_receipt_id' => $receipt->id,
            'purchase_request_item_id' => $requestItem->id,
            'inventory_item_id' => $item->id,
            'quantity' => 250,
        ]);
    }
}
