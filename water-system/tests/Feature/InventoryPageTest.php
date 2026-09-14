<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_live_inventory_balances_from_stock_movements(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $item = InventoryItem::create([
            'sku' => 'TEST-500ML',
            'name' => 'Test Water 500 ml',
            'category' => 'finished_product',
            'unit' => 'unit',
            'reorder_level' => 100,
            'is_sellable' => true,
            'is_active' => true,
        ]);

        StockMovement::create([
            'inventory_item_id' => $item->id,
            'movement_type' => 'opening_balance',
            'quantity_delta' => 1000,
            'occurred_at' => now()->subMinute(),
        ]);

        StockMovement::create([
            'inventory_item_id' => $item->id,
            'movement_type' => 'test_issue',
            'quantity_delta' => -250,
            'occurred_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('TEST-500ML')
            ->assertSee('Test Water 500 ml')
            ->assertSee('750.000')
            ->assertSee('In Stock');
    }
}
