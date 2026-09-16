<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesDataReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_summarizes_completed_v2_sales_and_product_breakdown(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $walkInProduct = $this->createFinishedProduct(
            'FW-REPORT-500',
            'Finished Water Report 500 ml',
            30,
            25,
        );

        $businessProduct = $this->createFinishedProduct(
            'FW-REPORT-1L',
            'Finished Water Report 1 L',
            35,
            25,
        );

        $draftOnlyProduct = $this->createFinishedProduct(
            'FW-REPORT-DRAFT',
            'Draft Only Water',
            999,
            900,
        );

        $customer = Customer::create([
            'name' => 'Report Supermarket',
            'customer_type' => 'supermarket',
            'is_active' => true,
        ]);

        $walkInSale = SalesOrder::create([
            'customer_id' => null,
            'sale_type' => SalesOrder::TYPE_WALK_IN,
            'reference' => 'SALE-REPORT-WALKIN',
            'status' => SalesOrder::STATUS_COMPLETED,
            'sale_at' => '2026-09-10 10:00:00',
            'total_amount' => 60,
            'created_by' => $admin->id,
        ]);

        $walkInSale->items()->create([
            'inventory_item_id' => $walkInProduct->id,
            'quantity' => 2,
            'unit_price' => 30,
            'line_total' => 60,
        ]);

        $businessSale = SalesOrder::create([
            'customer_id' => $customer->id,
            'sale_type' => SalesOrder::TYPE_BUSINESS,
            'reference' => 'SALE-REPORT-BUSINESS',
            'status' => SalesOrder::STATUS_COMPLETED,
            'sale_at' => '2026-09-11 11:00:00',
            'total_amount' => 100,
            'created_by' => $admin->id,
        ]);

        $businessSale->items()->create([
            'inventory_item_id' => $businessProduct->id,
            'quantity' => 4,
            'unit_price' => 25,
            'line_total' => 100,
        ]);

        $draft = SalesOrder::create([
            'customer_id' => null,
            'sale_type' => SalesOrder::TYPE_WALK_IN,
            'status' => SalesOrder::STATUS_DRAFT,
            'sale_at' => '2026-09-12 12:00:00',
            'total_amount' => 999,
            'created_by' => $admin->id,
        ]);

        $draft->items()->create([
            'inventory_item_id' => $draftOnlyProduct->id,
            'quantity' => 1,
            'unit_price' => 999,
            'line_total' => 999,
        ]);

        $this->actingAs($admin)
            ->get(route('sales-orders.report'))
            ->assertOk()
            ->assertSee('V2 Sales Data Report')
            ->assertSee('Completed Sales')
            ->assertSee('Total Sales Value')
            ->assertSee('KES 160.00')
            ->assertSee('Total Units Sold')
            ->assertSee('Finished Water Report 500 ml')
            ->assertSee('Finished Water Report 1 L')
            ->assertSee('KES 60.00')
            ->assertSee('KES 100.00')
            ->assertDontSee('Draft Only Water')
            ->assertDontSee('KES 999.00');
    }

    public function test_report_can_be_filtered_by_date_range(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $olderProduct = $this->createFinishedProduct(
            'FW-REPORT-OLD',
            'Older Report Water',
            20,
            18,
        );

        $newerProduct = $this->createFinishedProduct(
            'FW-REPORT-NEW',
            'Newer Report Water',
            50,
            40,
        );

        $olderSale = SalesOrder::create([
            'customer_id' => null,
            'sale_type' => SalesOrder::TYPE_WALK_IN,
            'reference' => 'SALE-REPORT-OLD',
            'status' => SalesOrder::STATUS_COMPLETED,
            'sale_at' => '2026-08-31 10:00:00',
            'total_amount' => 40,
            'created_by' => $admin->id,
        ]);

        $olderSale->items()->create([
            'inventory_item_id' => $olderProduct->id,
            'quantity' => 2,
            'unit_price' => 20,
            'line_total' => 40,
        ]);

        $newerSale = SalesOrder::create([
            'customer_id' => null,
            'sale_type' => SalesOrder::TYPE_WALK_IN,
            'reference' => 'SALE-REPORT-NEW',
            'status' => SalesOrder::STATUS_COMPLETED,
            'sale_at' => '2026-09-16 10:00:00',
            'total_amount' => 150,
            'created_by' => $admin->id,
        ]);

        $newerSale->items()->create([
            'inventory_item_id' => $newerProduct->id,
            'quantity' => 3,
            'unit_price' => 50,
            'line_total' => 150,
        ]);

        $this->actingAs($admin)
            ->get(route('sales-orders.report', [
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-30',
            ]))
            ->assertOk()
            ->assertSee('2026-09-01')
            ->assertSee('2026-09-30')
            ->assertSee('KES 150.00')
            ->assertSee('Newer Report Water')
            ->assertDontSee('Older Report Water')
            ->assertDontSee('KES 40.00');
    }

    public function test_v2_sales_page_links_to_sales_data_report(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('sales-orders.create'))
            ->assertOk()
            ->assertSee('Sales Data Report')
            ->assertSee(route('sales-orders.report'), false);
    }

    private function createFinishedProduct(
        string $sku,
        string $name,
        float $retailPrice,
        float $wholesalePrice,
    ): InventoryItem {
        return InventoryItem::create([
            'sku' => $sku,
            'name' => $name,
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
