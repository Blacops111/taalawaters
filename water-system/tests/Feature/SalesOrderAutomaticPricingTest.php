<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderAutomaticPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_walk_in_sale_uses_retail_price_and_ignores_submitted_price(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->createProduct(75, 60);
        $order = $this->createOrder($admin, SalesOrder::TYPE_WALK_IN);

        $this->actingAs($admin)
            ->post(route('sales-orders.items.store', $order), [
                'inventory_item_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 1,
            ])
            ->assertRedirect(route('sales-orders.edit', $order))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sales_order_items', [
            'sales_order_id' => $order->id,
            'inventory_item_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 75,
            'line_total' => 150,
        ]);

        $this->assertSame('150.00', $order->fresh()->total_amount);
    }

    public function test_business_customer_sale_uses_wholesale_price(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::create([
            'name' => 'Bulk Buyer Supermarket',
            'customer_type' => 'supermarket',
            'is_active' => true,
        ]);
        $product = $this->createProduct(75, 60);
        $order = $this->createOrder($admin, SalesOrder::TYPE_BUSINESS, $customer->id);

        $this->actingAs($admin)
            ->post(route('sales-orders.items.store', $order), [
                'inventory_item_id' => $product->id,
                'quantity' => 3,
            ])
            ->assertRedirect(route('sales-orders.edit', $order))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sales_order_items', [
            'sales_order_id' => $order->id,
            'inventory_item_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 60,
            'line_total' => 180,
        ]);

        $this->assertSame('180.00', $order->fresh()->total_amount);
    }

    public function test_sale_rejects_product_when_required_price_is_not_configured(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->createProduct(null, null);
        $order = $this->createOrder($admin, SalesOrder::TYPE_WALK_IN);

        $this->actingAs($admin)
            ->from(route('sales-orders.edit', $order))
            ->post(route('sales-orders.items.store', $order), [
                'inventory_item_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertRedirect(route('sales-orders.edit', $order))
            ->assertSessionHasErrors('inventory_item_id');

        $this->assertDatabaseCount('sales_order_items', 0);
        $this->assertSame('0.00', $order->fresh()->total_amount);
    }

    private function createOrder(User $user, string $saleType, ?int $customerId = null): SalesOrder
    {
        return SalesOrder::create([
            'customer_id' => $customerId,
            'sale_type' => $saleType,
            'reference' => null,
            'status' => SalesOrder::STATUS_DRAFT,
            'sale_at' => '2026-09-16 13:00:00',
            'total_amount' => 0,
            'created_by' => $user->id,
        ]);
    }

    private function createProduct(string|int|float|null $retailPrice, string|int|float|null $wholesalePrice): InventoryItem
    {
        return InventoryItem::create([
            'sku' => 'FW-AUTO-PRICE',
            'name' => 'Finished Water Auto Price',
            'category' => 'finished_product',
            'unit' => 'unit',
            'reorder_level' => 0,
            'retail_price' => $retailPrice,
            'wholesale_price' => $wholesalePrice,
            'is_sellable' => true,
            'is_active' => true,
        ]);
    }
}
