<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_only_active_sellable_finished_products_on_pricing_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $finished = InventoryItem::create([
            'sku' => 'FW-500ML-PRICE',
            'name' => 'Finished Water 500 ml Pricing',
            'category' => 'finished_product',
            'unit' => 'unit',
            'reorder_level' => 0,
            'is_sellable' => true,
            'is_active' => true,
        ]);

        InventoryItem::create([
            'sku' => 'CAP-PRICE',
            'name' => 'Bottle Cap Pricing',
            'category' => 'packaging',
            'unit' => 'unit',
            'reorder_level' => 0,
            'is_sellable' => false,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('inventory.pricing.index'))
            ->assertOk()
            ->assertSee('Finished Product Sales Pricing')
            ->assertSee($finished->sku)
            ->assertDontSee('CAP-PRICE');
    }

    public function test_admin_can_set_retail_and_wholesale_prices(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->finishedProduct();

        $this->actingAs($admin)
            ->patch(route('inventory.pricing.update', $product), [
                'retail_price' => 50,
                'wholesale_price' => 40,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $product->id,
            'retail_price' => '50.00',
            'wholesale_price' => '40.00',
        ]);
    }

    public function test_wholesale_price_cannot_be_higher_than_retail_price(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->finishedProduct();

        $this->actingAs($admin)
            ->from(route('inventory.pricing.index'))
            ->patch(route('inventory.pricing.update', $product), [
                'retail_price' => 40,
                'wholesale_price' => 50,
            ])
            ->assertRedirect(route('inventory.pricing.index'))
            ->assertSessionHasErrors('wholesale_price');

        $this->assertNull($product->fresh()->retail_price);
        $this->assertNull($product->fresh()->wholesale_price);
    }

    public function test_non_finished_item_cannot_receive_sales_pricing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $packaging = InventoryItem::create([
            'sku' => 'LABEL-PRICE',
            'name' => 'Label Pricing Test',
            'category' => 'packaging',
            'unit' => 'unit',
            'reorder_level' => 0,
            'is_sellable' => false,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('inventory.pricing.index'))
            ->patch(route('inventory.pricing.update', $packaging), [
                'retail_price' => 10,
                'wholesale_price' => 8,
            ])
            ->assertRedirect(route('inventory.pricing.index'))
            ->assertSessionHasErrors('inventory_item');

        $this->assertNull($packaging->fresh()->retail_price);
        $this->assertNull($packaging->fresh()->wholesale_price);
    }

    private function finishedProduct(): InventoryItem
    {
        return InventoryItem::create([
            'sku' => 'FW-1L-PRICE',
            'name' => 'Finished Water 1 L Pricing',
            'category' => 'finished_product',
            'unit' => 'unit',
            'reorder_level' => 0,
            'is_sellable' => true,
            'is_active' => true,
        ]);
    }
}
