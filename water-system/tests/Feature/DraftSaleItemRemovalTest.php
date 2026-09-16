<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DraftSaleItemRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_remove_item_from_draft_and_total_is_recalculated_without_touching_inventory(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->finishedProduct('FW-REMOVE-1', 'Finished Water Remove 1');
        $secondProduct = $this->finishedProduct('FW-REMOVE-2', 'Finished Water Remove 2');
        $order = $this->draftOrder($admin, 150);

        $removedItem = $order->items()->create([
            'inventory_item_id' => $product->id,
            'quantity' => 4,
            'unit_price' => 30,
            'line_total' => 120,
        ]);

        $remainingItem = $order->items()->create([
            'inventory_item_id' => $secondProduct->id,
            'quantity' => 1,
            'unit_price' => 30,
            'line_total' => 30,
        ]);

        $this->actingAs($admin)
            ->delete(route('sales-orders.items.destroy', [$order, $removedItem]))
            ->assertRedirect(route('sales-orders.edit', $order))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('sales_order_items', ['id' => $removedItem->id]);
        $this->assertDatabaseHas('sales_order_items', ['id' => $remainingItem->id]);
        $this->assertDatabaseCount('stock_movements', 0);

        $order->refresh();
        $this->assertSame(30.0, (float) $order->total_amount);

        $this->actingAs($admin)
            ->get(route('sales-orders.edit', $order))
            ->assertOk()
            ->assertSee('Remove')
            ->assertSee('KES 30.00');
    }

    public function test_item_from_another_draft_cannot_be_removed_through_the_wrong_sale(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->finishedProduct('FW-REMOVE-WRONG', 'Finished Water Wrong Draft');
        $firstOrder = $this->draftOrder($admin, 0);
        $secondOrder = $this->draftOrder($admin, 30);

        $otherItem = $secondOrder->items()->create([
            'inventory_item_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 30,
            'line_total' => 30,
        ]);

        $this->actingAs($admin)
            ->from(route('sales-orders.edit', $firstOrder))
            ->delete(route('sales-orders.items.destroy', [$firstOrder, $otherItem]))
            ->assertRedirect(route('sales-orders.edit', $firstOrder))
            ->assertSessionHasErrors('sales_order_item');

        $this->assertDatabaseHas('sales_order_items', ['id' => $otherItem->id]);
        $this->assertSame(30.0, (float) $secondOrder->fresh()->total_amount);
    }

    public function test_item_cannot_be_removed_after_sale_is_completed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->finishedProduct('FW-REMOVE-DONE', 'Finished Water Completed Removal');

        $order = SalesOrder::create([
            'customer_id' => null,
            'sale_type' => SalesOrder::TYPE_WALK_IN,
            'reference' => 'SALE-REMOVE-DONE',
            'status' => SalesOrder::STATUS_COMPLETED,
            'sale_at' => '2026-09-16 14:00:00',
            'total_amount' => 30,
            'created_by' => $admin->id,
        ]);

        $item = $order->items()->create([
            'inventory_item_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 30,
            'line_total' => 30,
        ]);

        $this->actingAs($admin)
            ->from(route('sales-orders.history'))
            ->delete(route('sales-orders.items.destroy', [$order, $item]))
            ->assertRedirect(route('sales-orders.history'))
            ->assertSessionHasErrors('sales_order');

        $this->assertDatabaseHas('sales_order_items', ['id' => $item->id]);
        $this->assertSame(30.0, (float) $order->fresh()->total_amount);
    }

    private function finishedProduct(string $sku, string $name): InventoryItem
    {
        return InventoryItem::create([
            'sku' => $sku,
            'name' => $name,
            'category' => 'finished_product',
            'unit' => 'unit',
            'reorder_level' => 0,
            'retail_price' => 30,
            'wholesale_price' => 25,
            'is_sellable' => true,
            'is_active' => true,
        ]);
    }

    private function draftOrder(User $admin, int $totalAmount): SalesOrder
    {
        return SalesOrder::create([
            'customer_id' => null,
            'sale_type' => SalesOrder::TYPE_WALK_IN,
            'status' => SalesOrder::STATUS_DRAFT,
            'sale_at' => '2026-09-16 13:00:00',
            'total_amount' => $totalAmount,
            'created_by' => $admin->id,
        ]);
    }
}
