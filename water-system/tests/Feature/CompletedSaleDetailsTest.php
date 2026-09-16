<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompletedSaleDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_read_only_completed_sale_details_and_inventory_audit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $product = InventoryItem::create([
            'sku' => 'FW-DETAIL-500',
            'name' => 'Finished Water Detail 500 ml',
            'category' => 'finished_product',
            'unit' => 'unit',
            'reorder_level' => 0,
            'retail_price' => 30,
            'wholesale_price' => 25,
            'is_sellable' => true,
            'is_active' => true,
        ]);

        $order = SalesOrder::create([
            'customer_id' => null,
            'sale_type' => SalesOrder::TYPE_WALK_IN,
            'reference' => 'SALE-00000456',
            'status' => SalesOrder::STATUS_COMPLETED,
            'sale_at' => '2026-09-16 14:00:00',
            'total_amount' => 120,
            'created_by' => $admin->id,
            'notes' => 'Counter sale test note.',
        ]);

        $order->items()->create([
            'inventory_item_id' => $product->id,
            'quantity' => 4,
            'unit_price' => 30,
            'line_total' => 120,
        ]);

        StockMovement::create([
            'inventory_item_id' => $product->id,
            'movement_type' => 'sale',
            'quantity_delta' => -4,
            'source_type' => SalesOrder::class,
            'source_id' => $order->id,
            'created_by' => $admin->id,
            'occurred_at' => '2026-09-16 14:00:00',
            'reference' => $order->reference,
            'notes' => 'Finished stock sold.',
        ]);

        $this->actingAs($admin)
            ->get(route('sales-orders.show', $order))
            ->assertOk()
            ->assertSee('Completed Sale SALE-00000456')
            ->assertSee('Finished Water Detail 500 ml')
            ->assertSee('KES 30.00')
            ->assertSee('KES 120.00')
            ->assertSee('Counter sale test note.')
            ->assertSee('Inventory Deduction Audit')
            ->assertSee('-4.000')
            ->assertDontSee('Complete Sale & Deduct Stock');

        $this->actingAs($admin)
            ->get(route('sales-orders.history'))
            ->assertOk()
            ->assertSee(route('sales-orders.show', $order), false)
            ->assertSee('View');
    }

    public function test_draft_sale_cannot_be_opened_on_completed_sale_details_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $draft = SalesOrder::create([
            'customer_id' => null,
            'sale_type' => SalesOrder::TYPE_WALK_IN,
            'status' => SalesOrder::STATUS_DRAFT,
            'sale_at' => '2026-09-16 15:00:00',
            'total_amount' => 0,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('sales-orders.show', $draft))
            ->assertNotFound();
    }
}
