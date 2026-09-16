<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderCompletionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_complete_sale_from_draft_screen_and_inventory_is_deducted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $product = InventoryItem::create([
            'sku' => 'FW-COMPLETE-UI',
            'name' => 'Finished Water Completion UI',
            'category' => 'finished_product',
            'unit' => 'unit',
            'reorder_level' => 0,
            'retail_price' => 30,
            'wholesale_price' => 25,
            'is_sellable' => true,
            'is_active' => true,
        ]);

        StockMovement::create([
            'inventory_item_id' => $product->id,
            'movement_type' => 'opening_balance',
            'quantity_delta' => 10,
            'created_by' => $admin->id,
            'occurred_at' => '2026-09-16 08:00:00',
            'reference' => 'OPEN-COMPLETE-UI',
        ]);

        $order = SalesOrder::create([
            'customer_id' => null,
            'sale_type' => SalesOrder::TYPE_WALK_IN,
            'status' => SalesOrder::STATUS_DRAFT,
            'sale_at' => '2026-09-16 11:00:00',
            'total_amount' => 60,
            'created_by' => $admin->id,
        ]);

        $order->items()->create([
            'inventory_item_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 30,
            'line_total' => 60,
        ]);

        $this->actingAs($admin)
            ->get(route('sales-orders.edit', $order))
            ->assertOk()
            ->assertSee('Complete Sale & Deduct Stock', false);

        $this->actingAs($admin)
            ->post(route('sales-orders.complete', $order))
            ->assertRedirect(route('sales-orders.create'))
            ->assertSessionHas('success');

        $order->refresh();

        $this->assertSame(SalesOrder::STATUS_COMPLETED, $order->status);
        $this->assertSame('SALE-'.str_pad((string) $order->id, 8, '0', STR_PAD_LEFT), $order->reference);

        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $product->id,
            'movement_type' => 'sale',
            'quantity_delta' => -2,
            'source_type' => SalesOrder::class,
            'source_id' => $order->id,
            'reference' => $order->reference,
        ]);

        $this->assertSame(
            8.0,
            (float) StockMovement::query()
                ->where('inventory_item_id', $product->id)
                ->sum('quantity_delta')
        );
    }

    public function test_empty_draft_does_not_show_completion_button(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $order = SalesOrder::create([
            'customer_id' => null,
            'sale_type' => SalesOrder::TYPE_WALK_IN,
            'status' => SalesOrder::STATUS_DRAFT,
            'sale_at' => '2026-09-16 11:00:00',
            'total_amount' => 0,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('sales-orders.edit', $order))
            ->assertOk()
            ->assertDontSee('Complete Sale & Deduct Stock')
            ->assertSee('Add at least one finished product before the sale can be completed.');
    }
}
