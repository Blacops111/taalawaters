<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseRequestFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_request_can_be_created_without_supplier(): void
    {
        $requester = User::factory()->create(['role' => 'admin']);

        $purchaseRequest = PurchaseRequest::create([
            'status' => PurchaseRequest::STATUS_DRAFT,
            'supplier_id' => null,
            'requested_by' => $requester->id,
            'requested_at' => '2026-09-19 12:00:00',
            'notes' => 'Packaging materials required.',
        ]);

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'draft',
            'supplier_id' => null,
            'requested_by' => $requester->id,
            'notes' => 'Packaging materials required.',
        ]);

        $this->assertNull($purchaseRequest->supplier);
        $this->assertTrue($purchaseRequest->requester->is($requester));
    }

    public function test_purchase_request_can_hold_inventory_items_without_changing_stock(): void
    {
        $requester = User::factory()->create(['role' => 'admin']);

        $item = InventoryItem::create([
            'sku' => 'CAP-PR-TEST',
            'name' => 'Purchase Request Test Cap',
            'category' => 'cap',
            'unit' => 'piece',
            'reorder_level' => 100,
            'is_sellable' => false,
            'is_active' => true,
        ]);

        $purchaseRequest = PurchaseRequest::create([
            'requested_by' => $requester->id,
            'requested_at' => now(),
        ]);

        $purchaseRequest->items()->create([
            'inventory_item_id' => $item->id,
            'quantity' => 500,
        ]);

        $this->assertDatabaseHas('purchase_request_items', [
            'purchase_request_id' => $purchaseRequest->id,
            'inventory_item_id' => $item->id,
            'quantity' => 500,
        ]);

        $this->assertCount(1, $purchaseRequest->fresh()->items);
        $this->assertSame(0.0, $item->stockBalance());
        $this->assertDatabaseCount('stock_movements', 0);
    }
}
