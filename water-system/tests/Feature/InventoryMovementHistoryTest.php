<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryMovementHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_inventory_movement_audit_trail(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $item = InventoryItem::create([
            'sku' => 'EB-500ML',
            'name' => 'Empty Bottle 500 ml',
            'category' => 'empty_bottle',
            'unit' => 'unit',
            'reorder_level' => 100,
            'is_sellable' => false,
            'is_active' => true,
        ]);

        StockMovement::create([
            'inventory_item_id' => $item->id,
            'movement_type' => 'stock_received',
            'quantity_delta' => 1000,
            'created_by' => $admin->id,
            'occurred_at' => now(),
            'reference' => 'DELIVERY-001',
            'notes' => 'Supplier delivery received.',
        ]);

        $this->actingAs($admin)
            ->get(route('inventory.movements'))
            ->assertOk()
            ->assertSee('Stock Movement History')
            ->assertSee('EB-500ML')
            ->assertSee('Stock Received')
            ->assertSee('+1,000.000')
            ->assertSee('DELIVERY-001')
            ->assertSee($admin->name)
            ->assertSee('Supplier delivery received.');
    }

    public function test_inventory_movement_history_can_be_filtered_by_item(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $firstItem = InventoryItem::create([
            'sku' => 'CAP',
            'name' => 'Bottle Cap',
            'category' => 'cap',
            'unit' => 'unit',
            'reorder_level' => 100,
            'is_sellable' => false,
            'is_active' => true,
        ]);

        $secondItem = InventoryItem::create([
            'sku' => 'SEAL',
            'name' => 'Bottle Seal',
            'category' => 'seal',
            'unit' => 'unit',
            'reorder_level' => 100,
            'is_sellable' => false,
            'is_active' => true,
        ]);

        StockMovement::create([
            'inventory_item_id' => $firstItem->id,
            'movement_type' => 'opening_balance',
            'quantity_delta' => 500,
            'created_by' => $admin->id,
            'occurred_at' => now()->subMinute(),
            'reference' => 'OPEN-CAP',
        ]);

        StockMovement::create([
            'inventory_item_id' => $secondItem->id,
            'movement_type' => 'opening_balance',
            'quantity_delta' => 700,
            'created_by' => $admin->id,
            'occurred_at' => now(),
            'reference' => 'OPEN-SEAL',
        ]);

        $this->actingAs($admin)
            ->get(route('inventory.movements', [
                'inventory_item_id' => $firstItem->id,
            ]))
            ->assertOk()
            ->assertSee('OPEN-CAP')
            ->assertDontSee('OPEN-SEAL');
    }
}
