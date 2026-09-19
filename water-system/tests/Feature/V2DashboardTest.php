<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class V2DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_v2_inventory_and_completed_v2_sales_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $rawWater = InventoryItem::create([
            'sku' => 'RAW-WATER-DASH',
            'name' => 'Raw Water Dashboard',
            'category' => 'raw_water',
            'unit' => 'litre',
            'reorder_level' => 0,
            'is_sellable' => false,
            'is_active' => true,
        ]);

        $finished = InventoryItem::create([
            'sku' => 'FW-DASH-500',
            'name' => 'Finished Water Dashboard 500 ml',
            'category' => 'finished_product',
            'unit' => 'unit',
            'reorder_level' => 20,
            'retail_price' => 30,
            'wholesale_price' => 25,
            'is_sellable' => true,
            'is_active' => true,
        ]);

        StockMovement::create([
            'inventory_item_id' => $rawWater->id,
            'movement_type' => 'meter_reading',
            'quantity_delta' => 1250,
            'created_by' => $admin->id,
            'occurred_at' => '2026-09-19 08:00:00',
            'reference' => 'METER-DASH-1',
        ]);

        StockMovement::create([
            'inventory_item_id' => $finished->id,
            'movement_type' => 'opening_balance',
            'quantity_delta' => 15,
            'created_by' => $admin->id,
            'occurred_at' => '2026-09-19 08:00:00',
            'reference' => 'OPEN-DASH-1',
        ]);

        $completed = SalesOrder::create([
            'sale_type' => SalesOrder::TYPE_WALK_IN,
            'reference' => 'SALE-DASH-1',
            'status' => SalesOrder::STATUS_COMPLETED,
            'sale_at' => '2026-09-19 10:00:00',
            'total_amount' => 90,
            'created_by' => $admin->id,
        ]);

        $completed->items()->create([
            'inventory_item_id' => $finished->id,
            'quantity' => 3,
            'unit_price' => 30,
            'line_total' => 90,
        ]);

        $reversed = SalesOrder::create([
            'sale_type' => SalesOrder::TYPE_WALK_IN,
            'reference' => 'SALE-DASH-REVERSED',
            'status' => SalesOrder::STATUS_REVERSED,
            'sale_at' => '2026-09-19 11:00:00',
            'total_amount' => 999,
            'created_by' => $admin->id,
        ]);

        $reversed->items()->create([
            'inventory_item_id' => $finished->id,
            'quantity' => 33,
            'unit_price' => 30,
            'line_total' => 990,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('V2 Business Overview')
            ->assertSee('1,250.000 L')
            ->assertSee('15')
            ->assertSee('KES 90.00')
            ->assertSee('Low Stock Items')
            ->assertSee('Finished Water Dashboard 500 ml')
            ->assertSee('V2 Sales Revenue Trend')
            ->assertSee('V2 Units Sold by Product')
            ->assertSee('Finished Product Stock')
            ->assertDontSee('Total Profit')
            ->assertDontSee('Monthly Profit');
    }
}
