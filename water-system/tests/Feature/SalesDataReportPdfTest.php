<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesDataReportPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_v2_sales_report_as_pdf(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $product = InventoryItem::create([
            'sku' => 'FW-PDF-500',
            'name' => 'Finished Water PDF 500 ml',
            'category' => 'finished_product',
            'unit' => 'unit',
            'reorder_level' => 0,
            'retail_price' => 30,
            'wholesale_price' => 25,
            'is_sellable' => true,
            'is_active' => true,
        ]);

        $sale = SalesOrder::create([
            'customer_id' => null,
            'sale_type' => SalesOrder::TYPE_WALK_IN,
            'reference' => 'SALE-PDF-0001',
            'status' => SalesOrder::STATUS_COMPLETED,
            'sale_at' => '2026-09-16 10:00:00',
            'total_amount' => 90,
            'created_by' => $admin->id,
        ]);

        $sale->items()->create([
            'inventory_item_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 30,
            'line_total' => 90,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('sales-orders.report.pdf', [
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-30',
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $contentDisposition = (string) $response->headers->get('content-disposition');

        $this->assertStringContainsString('attachment', $contentDisposition);
        $this->assertStringContainsString('taala_v2_sales_report.pdf', $contentDisposition);
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_report_page_shows_pdf_download_for_selected_date_range(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('sales-orders.report', [
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-30',
            ]))
            ->assertOk()
            ->assertSee('Download PDF')
            ->assertSee(route('sales-orders.report.pdf'), false)
            ->assertSee('date_from=2026-09-01', false)
            ->assertSee('date_to=2026-09-30', false);
    }
}
