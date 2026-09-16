<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderDraftItemControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_draft_and_only_eligible_finished_products_are_offered(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->createDraft($admin);

        $finishedProduct = $this->createInventoryItem([
            'sku' => 'FW-ITEM-TEST',
            'name' => 'Finished Water Item Test',
            'category' => 'finished_product',
            'is_sellable' => true,
            'is_active' => true,
        ]);

        $rawMaterial = $this->createInventoryItem([
            'sku' => 'RAW-ITEM-TEST',
            'name' => 'Raw Material Item Test',
            'category' => 'raw_water',
            'is_sellable' => false,
            'is_active' => true,
            'retail_price' => null,
            'wholesale_price' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('sales-orders.edit', $order))
            ->assertOk()
            ->assertSee('Sales Draft #'.$order->id)
            ->assertSee('Retail Price')
            ->assertSee($finishedProduct->name)
            ->assertDontSee($rawMaterial->name);
    }

    public function test_admin_can_add_finished_product_to_draft_without_deducting_stock(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->createDraft($admin);
        $finishedProduct = $this->createInventoryItem();

        $this->actingAs($admin)
            ->post(route('sales-orders.items.store', $order), [
                'inventory_item_id' => $finishedProduct->id,
                'quantity' => 12,
            ])
            ->assertRedirect(route('sales-orders.edit', $order))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('sales_order_items', [
            'sales_order_id' => $order->id,
            'inventory_item_id' => $finishedProduct->id,
            'quantity' => 12,
            'unit_price' => 50,
            'line_total' => 600,
        ]);

        $this->assertSame('600.00', $order->fresh()->total_amount);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_non_finished_product_cannot_be_added_to_draft(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->createDraft($admin);
        $rawMaterial = $this->createInventoryItem([
            'sku' => 'RAW-SALE-BLOCKED',
            'name' => 'Raw Sale Blocked',
            'category' => 'raw_water',
            'is_sellable' => false,
            'retail_price' => null,
            'wholesale_price' => null,
        ]);

        $this->actingAs($admin)
            ->from(route('sales-orders.edit', $order))
            ->post(route('sales-orders.items.store', $order), [
                'inventory_item_id' => $rawMaterial->id,
                'quantity' => 1,
            ])
            ->assertRedirect(route('sales-orders.edit', $order))
            ->assertSessionHasErrors('inventory_item_id');

        $this->assertDatabaseCount('sales_order_items', 0);
        $this->assertSame('0.00', $order->fresh()->total_amount);
    }

    public function test_draft_item_quantity_must_be_a_whole_unit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->createDraft($admin);
        $finishedProduct = $this->createInventoryItem();

        $this->actingAs($admin)
            ->from(route('sales-orders.edit', $order))
            ->post(route('sales-orders.items.store', $order), [
                'inventory_item_id' => $finishedProduct->id,
                'quantity' => 0.5,
            ])
            ->assertRedirect(route('sales-orders.edit', $order))
            ->assertSessionHasErrors('quantity');

        $this->assertDatabaseCount('sales_order_items', 0);
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

    private function createInventoryItem(array $overrides = []): InventoryItem
    {
        return InventoryItem::create(array_merge([
            'sku' => 'FW-DRAFT-ITEM',
            'name' => 'Finished Water Draft Item',
            'category' => 'finished_product',
            'unit' => 'unit',
            'reorder_level' => 0,
            'retail_price' => 50,
            'wholesale_price' => 40,
            'is_sellable' => true,
            'is_active' => true,
        ], $overrides));
    }
}
