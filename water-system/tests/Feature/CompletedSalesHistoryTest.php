<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompletedSalesHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_completed_sales_history_with_items_and_totals(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $product = InventoryItem::create([
            'sku' => 'FW-HISTORY-500',
            'name' => 'Finished Water History 500 ml',
            'category' => 'finished_product',
            'unit' => 'unit',
            'reorder_level' => 0,
            'retail_price' => 30,
            'wholesale_price' => 25,
            'is_sellable' => true,
            'is_active' => true,
        ]);

        $completed = SalesOrder::create([
            'customer_id' => null,
            'sale_type' => SalesOrder::TYPE_WALK_IN,
            'reference' => 'SALE-00000123',
            'status' => SalesOrder::STATUS_COMPLETED,
            'sale_at' => '2026-09-16 12:00:00',
            'total_amount' => 90,
            'created_by' => $admin->id,
        ]);

        $completed->items()->create([
            'inventory_item_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 30,
            'line_total' => 90,
        ]);

        SalesOrder::create([
            'customer_id' => null,
            'sale_type' => SalesOrder::TYPE_WALK_IN,
            'reference' => 'DRAFT-SHOULD-NOT-APPEAR',
            'status' => SalesOrder::STATUS_DRAFT,
            'sale_at' => '2026-09-16 13:00:00',
            'total_amount' => 0,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('sales-orders.history'))
            ->assertOk()
            ->assertSee('Completed Sales History')
            ->assertSee('SALE-00000123')
            ->assertSee('Finished Water History 500 ml')
            ->assertSee('3 × Finished Water History 500 ml')
            ->assertSee('KES 90.00')
            ->assertSee('Completed')
            ->assertDontSee('DRAFT-SHOULD-NOT-APPEAR')
            ->assertDontSee('Complete Sale & Deduct Stock');
    }

    public function test_v2_sales_page_links_to_completed_sales_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('sales-orders.create'))
            ->assertOk()
            ->assertSee('Completed Sales History')
            ->assertSee(route('sales-orders.history'), false);
    }
}
