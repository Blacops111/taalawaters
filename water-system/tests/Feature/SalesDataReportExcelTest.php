<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class SalesDataReportExcelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_filtered_v2_sales_report_as_excel(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $includedProduct = InventoryItem::create([
            'sku' => 'FW-XLSX-500',
            'name' => 'Finished Water Excel 500 ml',
            'category' => 'finished_product',
            'unit' => 'unit',
            'reorder_level' => 0,
            'retail_price' => 30,
            'wholesale_price' => 25,
            'is_sellable' => true,
            'is_active' => true,
        ]);

        $excludedProduct = InventoryItem::create([
            'sku' => 'FW-XLSX-OLD',
            'name' => 'Old Excel Water',
            'category' => 'finished_product',
            'unit' => 'unit',
            'reorder_level' => 0,
            'retail_price' => 40,
            'wholesale_price' => 35,
            'is_sellable' => true,
            'is_active' => true,
        ]);

        $includedSale = SalesOrder::create([
            'customer_id' => null,
            'sale_type' => SalesOrder::TYPE_WALK_IN,
            'reference' => 'SALE-XLSX-INCLUDED',
            'status' => SalesOrder::STATUS_COMPLETED,
            'sale_at' => '2026-09-16 10:00:00',
            'total_amount' => 90,
            'created_by' => $admin->id,
        ]);

        $includedSale->items()->create([
            'inventory_item_id' => $includedProduct->id,
            'quantity' => 3,
            'unit_price' => 30,
            'line_total' => 90,
        ]);

        $excludedSale = SalesOrder::create([
            'customer_id' => null,
            'sale_type' => SalesOrder::TYPE_WALK_IN,
            'reference' => 'SALE-XLSX-EXCLUDED',
            'status' => SalesOrder::STATUS_COMPLETED,
            'sale_at' => '2026-08-15 10:00:00',
            'total_amount' => 80,
            'created_by' => $admin->id,
        ]);

        $excludedSale->items()->create([
            'inventory_item_id' => $excludedProduct->id,
            'quantity' => 2,
            'unit_price' => 40,
            'line_total' => 80,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('sales-orders.report.excel', [
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-30',
            ]));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $contentDisposition = (string) $response->headers->get('content-disposition');

        $this->assertStringContainsString('attachment', $contentDisposition);
        $this->assertStringContainsString('taala_v2_sales_report.xlsx', $contentDisposition);

        $content = $response->streamedContent();
        $this->assertStringStartsWith('PK', $content);

        $temporaryFile = tempnam(sys_get_temp_dir(), 'taala-sales-report-');
        $this->assertNotFalse($temporaryFile);

        file_put_contents($temporaryFile, $content);

        try {
            $spreadsheet = IOFactory::load($temporaryFile);

            $this->assertSame(['Summary', 'Product Breakdown'], $spreadsheet->getSheetNames());

            $summarySheet = $spreadsheet->getSheetByName('Summary');
            $this->assertNotNull($summarySheet);
            $this->assertSame('2026-09-01', $summarySheet->getCell('B3')->getValue());
            $this->assertSame('2026-09-30', $summarySheet->getCell('B4')->getValue());
            $this->assertSame(1, (int) $summarySheet->getCell('B6')->getValue());
            $this->assertSame(90.0, (float) $summarySheet->getCell('B7')->getValue());
            $this->assertSame(3.0, (float) $summarySheet->getCell('B8')->getValue());
            $this->assertSame(90.0, (float) $summarySheet->getCell('B9')->getValue());
            $this->assertSame(0.0, (float) $summarySheet->getCell('B10')->getValue());

            $productSheet = $spreadsheet->getSheetByName('Product Breakdown');
            $this->assertNotNull($productSheet);
            $this->assertSame('FW-XLSX-500', $productSheet->getCell('A2')->getValue());
            $this->assertSame('Finished Water Excel 500 ml', $productSheet->getCell('B2')->getValue());
            $this->assertSame(3.0, (float) $productSheet->getCell('C2')->getValue());
            $this->assertSame(90.0, (float) $productSheet->getCell('D2')->getValue());
            $this->assertSame(2, $productSheet->getHighestRow());

            $spreadsheet->disconnectWorksheets();
        } finally {
            @unlink($temporaryFile);
        }
    }

    public function test_report_page_shows_excel_download_for_selected_date_range(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('sales-orders.report', [
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-30',
            ]))
            ->assertOk()
            ->assertSee('Download Excel')
            ->assertSee(route('sales-orders.report.excel'), false)
            ->assertSee('date_from=2026-09-01', false)
            ->assertSee('date_to=2026-09-30', false);
    }
}
