<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use Database\Seeders\InventoryItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryItemSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_master_skus_are_seeded_idempotently(): void
    {
        $this->seed(InventoryItemSeeder::class);

        $this->assertSame(16, InventoryItem::count());

        $this->assertDatabaseHas('inventory_items', [
            'sku' => 'RAW-WATER',
            'category' => 'raw_water',
            'unit' => 'litre',
            'is_sellable' => false,
        ]);

        $this->assertDatabaseHas('inventory_items', [
            'sku' => 'FW-500ML',
            'category' => 'finished_product',
            'is_sellable' => true,
        ]);

        $this->assertDatabaseHas('inventory_items', [
            'sku' => 'FW-20L',
            'category' => 'finished_product',
            'is_sellable' => true,
        ]);

        $this->seed(InventoryItemSeeder::class);

        $this->assertSame(16, InventoryItem::count());
    }
}
