<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_record_an_opening_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $item = $this->makeItem('EB-500ML', 'Empty Bottle 500 ml', 'empty_bottle');

        $this->actingAs($admin)
            ->post(route('inventory.receipts.store'), [
                'inventory_item_id' => $item->id,
                'movement_type' => 'opening_balance',
                'quantity' => 1000,
                'occurred_at' => '2026-09-14 12:00:00',
                'reference' => 'OPENING-COUNT-001',
            ])
            ->assertRedirect(route('inventory.index'));

        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $item->id,
            'movement_type' => 'opening_balance',
            'quantity_delta' => 1000,
            'created_by' => $admin->id,
            'reference' => 'OPENING-COUNT-001',
        ]);
    }

    public function test_an_item_cannot_receive_two_opening_balances(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $item = $this->makeItem('CAP', 'Bottle Cap', 'cap');

        StockMovement::create([
            'inventory_item_id' => $item->id,
            'movement_type' => 'opening_balance',
            'quantity_delta' => 500,
            'created_by' => $admin->id,
            'occurred_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->from(route('inventory.receipts.create'))
            ->post(route('inventory.receipts.store'), [
                'inventory_item_id' => $item->id,
                'movement_type' => 'opening_balance',
                'quantity' => 100,
                'occurred_at' => '2026-09-14 12:00:00',
            ])
            ->assertRedirect(route('inventory.receipts.create'))
            ->assertSessionHasErrors('movement_type');

        $this->assertSame(1, StockMovement::where('inventory_item_id', $item->id)->count());
    }

    public function test_raw_borehole_water_cannot_be_added_manually(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $item = $this->makeItem('RAW-WATER', 'Raw Borehole Water', 'raw_water', 'litre');

        $this->actingAs($admin)
            ->from(route('inventory.receipts.create'))
            ->post(route('inventory.receipts.store'), [
                'inventory_item_id' => $item->id,
                'movement_type' => 'opening_balance',
                'quantity' => 1000,
                'occurred_at' => '2026-09-14 12:00:00',
            ])
            ->assertRedirect(route('inventory.receipts.create'))
            ->assertSessionHasErrors('inventory_item_id');

        $this->assertSame(0, StockMovement::count());
    }

    public function test_finished_products_cannot_be_manually_received_as_normal_stock(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $item = $this->makeItem('FW-500ML', 'Finished Water 500 ml', 'finished_product');

        $this->actingAs($admin)
            ->from(route('inventory.receipts.create'))
            ->post(route('inventory.receipts.store'), [
                'inventory_item_id' => $item->id,
                'movement_type' => 'stock_received',
                'quantity' => 100,
                'occurred_at' => '2026-09-14 12:00:00',
                'reference' => 'DN-001',
            ])
            ->assertRedirect(route('inventory.receipts.create'))
            ->assertSessionHasErrors('movement_type');

        $this->assertSame(0, StockMovement::count());
    }

    private function makeItem(
        string $sku,
        string $name,
        string $category,
        string $unit = 'unit'
    ): InventoryItem {
        return InventoryItem::create([
            'sku' => $sku,
            'name' => $name,
            'category' => $category,
            'unit' => $unit,
            'reorder_level' => 0,
            'is_sellable' => $category === 'finished_product',
            'is_active' => true,
        ]);
    }
}
