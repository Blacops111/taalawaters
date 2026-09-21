<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\SalesOrderCompletionService;
use Database\Seeders\AccountingAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderReversalControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(AccountingAccountSeeder::class)->run();
    }

    public function test_completed_sale_shows_reversal_confirmation_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$order] = $this->createCompletedSale($admin, 10, 2);

        $this->actingAs($admin)
            ->get(route('sales-orders.show', $order))
            ->assertOk()
            ->assertSee('Reverse Sale')
            ->assertSee(route('sales-orders.reversal', $order), false);

        $this->actingAs($admin)
            ->get(route('sales-orders.reversal', $order))
            ->assertOk()
            ->assertSee('Reverse Sale '.$order->reference)
            ->assertSee('Products to Restore')
            ->assertSee('Confirm Sale Reversal')
            ->assertSee('Finished Water Reversal UI Test');
    }

    public function test_admin_can_reverse_completed_sale_from_ui_and_audit_remains_visible(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$order, $product] = $this->createCompletedSale($admin, 10, 3);

        $this->assertSame(7.0, $this->stockBalance($product));

        $response = $this->actingAs($admin)
            ->post(route('sales-orders.reverse', $order), [
                'reason' => 'Customer order was entered incorrectly.',
            ]);

        $response->assertRedirect(route('sales-orders.show', $order));

        $order->refresh();

        $this->assertSame(SalesOrder::STATUS_REVERSED, $order->status);
        $this->assertSame(10.0, $this->stockBalance($product));

        $this->assertDatabaseHas('sales_order_reversals', [
            'sales_order_id' => $order->id,
            'reversed_by' => $admin->id,
            'reference' => 'REV-'.$order->reference,
            'reason' => 'Customer order was entered incorrectly.',
        ]);

        $this->actingAs($admin)
            ->get(route('sales-orders.show', $order))
            ->assertOk()
            ->assertSee('Reversed')
            ->assertSee('Sale Reversal Audit')
            ->assertSee('REV-'.$order->reference)
            ->assertSee('Customer order was entered incorrectly.')
            ->assertSee('+3.000')
            ->assertDontSee('Reverse Sale');

        $this->actingAs($admin)
            ->get(route('sales-orders.history'))
            ->assertOk()
            ->assertSee($order->reference)
            ->assertSee('Reversed');

        $this->actingAs($admin)
            ->get(route('sales-orders.report'))
            ->assertOk()
            ->assertDontSee('Finished Water Reversal UI Test')
            ->assertSee('KES 0.00');
    }

    public function test_reversal_ui_requires_a_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$order, $product] = $this->createCompletedSale($admin, 10, 2);

        $this->actingAs($admin)
            ->from(route('sales-orders.reversal', $order))
            ->post(route('sales-orders.reverse', $order), [
                'reason' => '',
            ])
            ->assertRedirect(route('sales-orders.reversal', $order))
            ->assertSessionHasErrors('reason');

        $this->assertSame(SalesOrder::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertSame(8.0, $this->stockBalance($product));
    }

    public function test_reversed_sale_cannot_open_reversal_form_again(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$order] = $this->createCompletedSale($admin, 10, 1);

        $this->actingAs($admin)
            ->post(route('sales-orders.reverse', $order), [
                'reason' => 'Duplicate sale entry.',
            ])
            ->assertRedirect(route('sales-orders.show', $order));

        $this->actingAs($admin)
            ->get(route('sales-orders.reversal', $order->fresh()))
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('sales-orders.show', $order->fresh()))
            ->assertOk()
            ->assertSee('Duplicate sale entry.')
            ->assertDontSee('Confirm Sale Reversal');
    }

    private function createCompletedSale(User $admin, int $openingStock, int $quantity): array
    {
        $product = InventoryItem::create([
            'sku' => 'FW-REVERSAL-UI',
            'name' => 'Finished Water Reversal UI Test',
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
            'quantity_delta' => $openingStock,
            'created_by' => $admin->id,
            'occurred_at' => '2026-09-16 08:00:00',
            'reference' => 'OPEN-REVERSAL-UI',
        ]);

        $order = SalesOrder::create([
            'customer_id' => null,
            'sale_type' => SalesOrder::TYPE_WALK_IN,
            'reference' => null,
            'status' => SalesOrder::STATUS_DRAFT,
            'sale_at' => '2026-09-16 12:00:00',
            'total_amount' => $quantity * 30,
            'created_by' => $admin->id,
        ]);

        $order->items()->create([
            'inventory_item_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => 30,
            'line_total' => $quantity * 30,
        ]);

        $completed = app(SalesOrderCompletionService::class)->complete($order, $admin);

        return [$completed, $product];
    }

    private function stockBalance(InventoryItem $product): float
    {
        return (float) StockMovement::query()
            ->where('inventory_item_id', $product->id)
            ->sum('quantity_delta');
    }
}
