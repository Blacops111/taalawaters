<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseRequestItemManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_draft_and_see_only_purchasable_active_inventory_items(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $purchaseRequest = PurchaseRequest::create([
            'reference' => 'PREQ-00000001',
            'status' => PurchaseRequest::STATUS_DRAFT,
            'requested_by' => $admin->id,
            'requested_at' => now(),
        ]);

        $bottle = $this->inventoryItem('EB-PR-500ML', 'Purchasable Empty Bottle', 'empty_bottle', true);
        $rawWater = $this->inventoryItem('RAW-PR-WATER', 'Raw Purchase Test Water', 'raw_water', true);
        $finished = $this->inventoryItem('FW-PR-500ML', 'Finished Purchase Test Water', 'finished_product', true);
        $inactive = $this->inventoryItem('CAP-PR-INACTIVE', 'Inactive Purchase Cap', 'cap', false);

        $this->actingAs($admin)
            ->get(route('purchase-requests.edit', $purchaseRequest))
            ->assertOk()
            ->assertSee($purchaseRequest->reference)
            ->assertSee($bottle->name)
            ->assertDontSee($rawWater->name)
            ->assertDontSee($finished->name)
            ->assertDontSee($inactive->name)
            ->assertSee('No materials have been added yet.');
    }

    public function test_admin_can_add_material_and_quantity_without_changing_inventory(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $purchaseRequest = PurchaseRequest::create([
            'reference' => 'PREQ-00000002',
            'status' => PurchaseRequest::STATUS_DRAFT,
            'requested_by' => $admin->id,
            'requested_at' => now(),
        ]);

        $item = $this->inventoryItem('CAP-PR-ACTIVE', 'Purchase Cap', 'cap', true);

        $this->actingAs($admin)
            ->post(route('purchase-requests.items.store', $purchaseRequest), [
                'inventory_item_id' => $item->id,
                'quantity' => 750,
            ])
            ->assertRedirect(route('purchase-requests.edit', $purchaseRequest))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_request_items', [
            'purchase_request_id' => $purchaseRequest->id,
            'inventory_item_id' => $item->id,
            'quantity' => 750,
        ]);

        $this->assertSame(0.0, $item->stockBalance());
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_same_material_cannot_be_added_twice_to_one_purchase_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $purchaseRequest = PurchaseRequest::create([
            'reference' => 'PREQ-00000003',
            'status' => PurchaseRequest::STATUS_DRAFT,
            'requested_by' => $admin->id,
            'requested_at' => now(),
        ]);

        $item = $this->inventoryItem('LABEL-PR', 'Purchase Label', 'label', true);

        $purchaseRequest->items()->create([
            'inventory_item_id' => $item->id,
            'quantity' => 100,
        ]);

        $this->actingAs($admin)
            ->from(route('purchase-requests.edit', $purchaseRequest))
            ->post(route('purchase-requests.items.store', $purchaseRequest), [
                'inventory_item_id' => $item->id,
                'quantity' => 200,
            ])
            ->assertRedirect(route('purchase-requests.edit', $purchaseRequest))
            ->assertSessionHasErrors('inventory_item_id');

        $this->assertDatabaseCount('purchase_request_items', 1);
    }

    public function test_raw_water_and_finished_products_cannot_be_added_as_purchase_request_items(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $purchaseRequest = PurchaseRequest::create([
            'reference' => 'PREQ-00000004',
            'status' => PurchaseRequest::STATUS_DRAFT,
            'requested_by' => $admin->id,
            'requested_at' => now(),
        ]);

        foreach ([
            $this->inventoryItem('RAW-PR-BLOCK', 'Blocked Raw Water', 'raw_water', true),
            $this->inventoryItem('FW-PR-BLOCK', 'Blocked Finished Product', 'finished_product', true),
        ] as $item) {
            $this->actingAs($admin)
                ->from(route('purchase-requests.edit', $purchaseRequest))
                ->post(route('purchase-requests.items.store', $purchaseRequest), [
                    'inventory_item_id' => $item->id,
                    'quantity' => 10,
                ])
                ->assertRedirect(route('purchase-requests.edit', $purchaseRequest))
                ->assertSessionHasErrors('inventory_item_id');
        }

        $this->assertDatabaseCount('purchase_request_items', 0);
    }

    public function test_admin_can_remove_item_from_draft_without_changing_inventory(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $purchaseRequest = PurchaseRequest::create([
            'reference' => 'PREQ-00000005',
            'status' => PurchaseRequest::STATUS_DRAFT,
            'requested_by' => $admin->id,
            'requested_at' => now(),
        ]);

        $item = $this->inventoryItem('SEAL-PR', 'Purchase Seal', 'seal', true);

        $requestItem = $purchaseRequest->items()->create([
            'inventory_item_id' => $item->id,
            'quantity' => 300,
        ]);

        $this->actingAs($admin)
            ->delete(route('purchase-requests.items.destroy', [$purchaseRequest, $requestItem]))
            ->assertRedirect(route('purchase-requests.edit', $purchaseRequest))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('purchase_request_items', [
            'id' => $requestItem->id,
        ]);

        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_items_cannot_be_changed_after_request_leaves_draft_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $purchaseRequest = PurchaseRequest::create([
            'reference' => 'PREQ-00000006',
            'status' => PurchaseRequest::STATUS_SUBMITTED,
            'requested_by' => $admin->id,
            'requested_at' => now(),
        ]);

        $item = $this->inventoryItem('PACK-PR', 'Purchase Packaging', 'packaging_material', true);

        $this->actingAs($admin)
            ->from(route('purchase-requests.edit', $purchaseRequest))
            ->post(route('purchase-requests.items.store', $purchaseRequest), [
                'inventory_item_id' => $item->id,
                'quantity' => 50,
            ])
            ->assertRedirect(route('purchase-requests.edit', $purchaseRequest))
            ->assertSessionHasErrors('purchase_request');

        $this->assertDatabaseCount('purchase_request_items', 0);
    }

    private function inventoryItem(
        string $sku,
        string $name,
        string $category,
        bool $active
    ): InventoryItem {
        return InventoryItem::create([
            'sku' => $sku,
            'name' => $name,
            'category' => $category,
            'unit' => 'unit',
            'reorder_level' => 0,
            'is_sellable' => $category === 'finished_product',
            'is_active' => $active,
        ]);
    }
}
