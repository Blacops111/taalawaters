<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryLowStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_set_a_reorder_level_for_inventory_items(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $item = InventoryItem::create([
            'sku' => 'CAP-TEST',
            'name' => 'Test Bottle Cap',
            'category' => 'cap',
            'unit' => 'unit',
            'reorder_level' => 0,
            'is_sellable' => false,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('inventory.index'))
            ->patch(route('inventory.reorder-level.update', $item), [
                'reorder_level' => 250,
            ])
            ->assertRedirect(route('inventory.index'))
            ->assertSessionHasNoErrors();

        $this->assertEquals(250.0, (float) $item->fresh()->reorder_level);
    }

    public function test_raw_borehole_water_cannot_use_a_manual_reorder_level(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $rawWater = InventoryItem::create([
            'sku' => 'RAW-WATER',
            'name' => 'Raw Borehole Water',
            'category' => 'raw_water',
            'unit' => 'litre',
            'reorder_level' => 0,
            'is_sellable' => false,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('inventory.index'))
            ->patch(route('inventory.reorder-level.update', $rawWater), [
                'reorder_level' => 1000,
            ])
            ->assertRedirect(route('inventory.index'))
            ->assertSessionHasErrors('reorder_level');

        $this->assertEquals(0.0, (float) $rawWater->fresh()->reorder_level);
    }

    public function test_low_stock_page_is_calculated_automatically_from_live_balances(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $lowItem = $this->makeItem('LOW-ITEM', 'Low Test Item', 'cap', 100);
        $goodItem = $this->makeItem('GOOD-ITEM', 'Healthy Test Item', 'label', 100);
        $emptyItem = $this->makeItem('EMPTY-ITEM', 'Empty Test Item', 'seal', 50);
        $unconfiguredItem = $this->makeItem('NO-LEVEL', 'No Level Item', 'packaging_material', 0);
        $rawWater = $this->makeItem('RAW-WATER', 'Raw Borehole Water', 'raw_water', 1000, 'litre');

        $this->addStock($lowItem, 80);
        $this->addStock($goodItem, 150);
        $this->addStock($rawWater, 100);

        $this->actingAs($admin)
            ->get(route('inventory.low-stock'))
            ->assertOk()
            ->assertSee('LOW-ITEM')
            ->assertSee('Low Test Item')
            ->assertSee('EMPTY-ITEM')
            ->assertDontSee('GOOD-ITEM')
            ->assertDontSee('NO-LEVEL')
            ->assertDontSee('RAW-WATER');

        $this->actingAs($admin)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('Low Stock (2)');
    }

    private function makeItem(
        string $sku,
        string $name,
        string $category,
        float $reorderLevel,
        string $unit = 'unit'
    ): InventoryItem {
        return InventoryItem::create([
            'sku' => $sku,
            'name' => $name,
            'category' => $category,
            'unit' => $unit,
            'reorder_level' => $reorderLevel,
            'is_sellable' => false,
            'is_active' => true,
        ]);
    }

    private function addStock(InventoryItem $item, float $quantity): void
    {
        StockMovement::create([
            'inventory_item_id' => $item->id,
            'movement_type' => 'opening_balance',
            'quantity_delta' => $quantity,
            'occurred_at' => now(),
        ]);
    }
}
