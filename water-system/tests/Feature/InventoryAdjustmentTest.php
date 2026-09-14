<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_damage_adjustment_reduces_stock_and_records_audit_details(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $item = $this->makeItem('EB-500ML');
        $this->addOpeningBalance($item, 100);

        $this->actingAs($admin)
            ->post(route('inventory.adjustments.store'), [
                'inventory_item_id' => $item->id,
                'adjustment_type' => 'damage',
                'quantity' => 25,
                'occurred_at' => '2026-09-14 12:00:00',
                'reference' => 'DAMAGE-001',
                'notes' => 'Twenty-five bottles cracked during handling.',
            ])
            ->assertRedirect(route('inventory.index'));

        $movement = StockMovement::query()
            ->where('inventory_item_id', $item->id)
            ->where('movement_type', 'damage')
            ->firstOrFail();

        $this->assertEquals(-25.0, (float) $movement->quantity_delta);
        $this->assertSame($admin->id, $movement->created_by);
        $this->assertSame('DAMAGE-001', $movement->reference);
        $this->assertEquals(75.0, (float) $item->stockMovements()->sum('quantity_delta'));
    }

    public function test_decrease_adjustment_cannot_remove_more_than_live_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $item = $this->makeItem('CAP');
        $this->addOpeningBalance($item, 40);

        $this->actingAs($admin)
            ->from(route('inventory.adjustments.create'))
            ->post(route('inventory.adjustments.store'), [
                'inventory_item_id' => $item->id,
                'adjustment_type' => 'wastage',
                'quantity' => 50,
                'occurred_at' => '2026-09-14 12:00:00',
                'notes' => 'Test wastage entry.',
            ])
            ->assertRedirect(route('inventory.adjustments.create'))
            ->assertSessionHasErrors('quantity');

        $this->assertSame(1, StockMovement::count());
        $this->assertEquals(40.0, (float) $item->stockMovements()->sum('quantity_delta'));
    }

    public function test_verified_count_correction_can_increase_stock(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $item = $this->makeItem('LABEL-1L');
        $this->addOpeningBalance($item, 80);

        $this->actingAs($admin)
            ->post(route('inventory.adjustments.store'), [
                'inventory_item_id' => $item->id,
                'adjustment_type' => 'stock_correction_increase',
                'quantity' => 20,
                'occurred_at' => '2026-09-14 12:00:00',
                'reference' => 'COUNT-001',
                'notes' => 'Physical count found twenty additional labels.',
            ])
            ->assertRedirect(route('inventory.index'));

        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $item->id,
            'movement_type' => 'stock_correction_increase',
            'quantity_delta' => 20,
            'created_by' => $admin->id,
            'reference' => 'COUNT-001',
        ]);

        $this->assertEquals(100.0, (float) $item->stockMovements()->sum('quantity_delta'));
    }

    public function test_raw_borehole_water_cannot_be_manually_adjusted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

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
            ->from(route('inventory.adjustments.create'))
            ->post(route('inventory.adjustments.store'), [
                'inventory_item_id' => $rawWater->id,
                'adjustment_type' => 'stock_correction_increase',
                'quantity' => 100,
                'occurred_at' => '2026-09-14 12:00:00',
                'notes' => 'Should not be allowed.',
            ])
            ->assertRedirect(route('inventory.adjustments.create'))
            ->assertSessionHasErrors('inventory_item_id');

        $this->assertSame(0, StockMovement::count());
    }

    private function makeItem(string $sku): InventoryItem
    {
        return InventoryItem::create([
            'sku' => $sku,
            'name' => 'Test '.$sku,
            'category' => 'packaging_material',
            'unit' => 'unit',
            'reorder_level' => 10,
            'is_sellable' => false,
            'is_active' => true,
        ]);
    }

    private function addOpeningBalance(InventoryItem $item, float $quantity): void
    {
        StockMovement::create([
            'inventory_item_id' => $item->id,
            'movement_type' => 'opening_balance',
            'quantity_delta' => $quantity,
            'occurred_at' => '2026-09-14 11:00:00',
        ]);
    }
}
