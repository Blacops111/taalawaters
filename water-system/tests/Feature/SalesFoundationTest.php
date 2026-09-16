<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_order_can_link_customer_creator_and_finished_inventory_item(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $customer = Customer::create([
            'name' => 'Test Customer',
            'phone' => '0700000000',
            'email' => 'customer@example.com',
            'address' => 'Nairobi',
            'is_active' => true,
        ]);

        $finishedProduct = InventoryItem::create([
            'sku' => 'FW-SALES-TEST',
            'name' => 'Finished Water Sales Test',
            'category' => 'finished_product',
            'unit' => 'unit',
            'reorder_level' => 0,
            'is_sellable' => true,
            'is_active' => true,
        ]);

        $order = SalesOrder::create([
            'customer_id' => $customer->id,
            'reference' => 'SALE-TEST-0001',
            'status' => 'draft',
            'sale_at' => '2026-09-16 09:00:00',
            'total_amount' => 500,
            'created_by' => $admin->id,
            'notes' => 'Foundation test order.',
        ]);

        $item = $order->items()->create([
            'inventory_item_id' => $finishedProduct->id,
            'quantity' => 10,
            'unit_price' => 50,
            'line_total' => 500,
        ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Test Customer',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'customer_id' => $customer->id,
            'reference' => 'SALE-TEST-0001',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('sales_order_items', [
            'id' => $item->id,
            'sales_order_id' => $order->id,
            'inventory_item_id' => $finishedProduct->id,
            'quantity' => 10,
            'unit_price' => 50,
            'line_total' => 500,
        ]);

        $this->assertTrue($order->fresh()->customer->is($customer));
        $this->assertTrue($order->fresh()->creator->is($admin));
        $this->assertTrue($order->fresh()->items->first()->inventoryItem->is($finishedProduct));
        $this->assertSame('500.00', $order->fresh()->total_amount);
    }

    public function test_sales_order_can_exist_without_registered_customer_for_walk_in_sale(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $order = SalesOrder::create([
            'customer_id' => null,
            'reference' => 'SALE-TEST-0002',
            'status' => 'draft',
            'sale_at' => '2026-09-16 10:00:00',
            'total_amount' => 0,
            'created_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'customer_id' => null,
            'reference' => 'SALE-TEST-0002',
            'status' => 'draft',
        ]);

        $this->assertNull($order->fresh()->customer);
    }
}
