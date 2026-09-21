<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseRequestApprovedCostTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitted_purchase_request_requires_and_stores_approved_unit_costs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $supplier = Supplier::create([
            'name' => 'Costed Purchase Supplier',
            'is_active' => true,
        ]);

        $purchaseRequest = PurchaseRequest::create([
            'reference' => 'PREQ-COST-0001',
            'status' => PurchaseRequest::STATUS_SUBMITTED,
            'requested_by' => $admin->id,
            'requested_at' => now(),
            'submitted_by' => $admin->id,
            'submitted_at' => now(),
        ]);

        $cap = $this->inventoryItem('CAP-COST', 'Costed Bottle Cap', 'cap');
        $label = $this->inventoryItem('LABEL-COST', 'Costed Bottle Label', 'label');

        $capLine = $purchaseRequest->items()->create([
            'inventory_item_id' => $cap->id,
            'quantity' => 1000,
        ]);

        $labelLine = $purchaseRequest->items()->create([
            'inventory_item_id' => $label->id,
            'quantity' => 500,
        ]);

        $this->actingAs($admin)
            ->get(route('purchase-requests.edit', $purchaseRequest))
            ->assertOk()
            ->assertSee('Approved Unit Costs')
            ->assertSee('Costed Bottle Cap')
            ->assertSee('Costed Bottle Label');

        $this->actingAs($admin)
            ->post(route('purchase-requests.approve', $purchaseRequest), [
                'supplier_id' => $supplier->id,
                'approved_unit_costs' => [
                    $capLine->id => 1.25,
                    $labelLine->id => 2.40,
                ],
            ])
            ->assertRedirect(route('purchase-requests.edit', $purchaseRequest))
            ->assertSessionHas('success');

        $this->assertSame(
            PurchaseRequest::STATUS_APPROVED,
            $purchaseRequest->fresh()->status
        );

        $this->assertSame('1.25', $capLine->fresh()->approved_unit_cost);
        $this->assertSame('2.40', $labelLine->fresh()->approved_unit_cost);

        $this->actingAs($admin)
            ->get(route('purchase-requests.edit', $purchaseRequest->fresh()))
            ->assertOk()
            ->assertSee('KES 1.25')
            ->assertSee('KES 2.40')
            ->assertSee('KES 1,250.00')
            ->assertSee('KES 1,200.00');

        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_approval_fails_if_any_requested_material_has_no_unit_cost(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $supplier = Supplier::create([
            'name' => 'Incomplete Cost Supplier',
            'is_active' => true,
        ]);

        $purchaseRequest = PurchaseRequest::create([
            'reference' => 'PREQ-COST-0002',
            'status' => PurchaseRequest::STATUS_SUBMITTED,
            'requested_by' => $admin->id,
            'requested_at' => now(),
            'submitted_by' => $admin->id,
            'submitted_at' => now(),
        ]);

        $cap = $this->inventoryItem('CAP-COST-MISS', 'Cap Missing Cost Test', 'cap');
        $seal = $this->inventoryItem('SEAL-COST-MISS', 'Seal Missing Cost Test', 'seal');

        $capLine = $purchaseRequest->items()->create([
            'inventory_item_id' => $cap->id,
            'quantity' => 100,
        ]);

        $sealLine = $purchaseRequest->items()->create([
            'inventory_item_id' => $seal->id,
            'quantity' => 100,
        ]);

        $this->actingAs($admin)
            ->from(route('purchase-requests.edit', $purchaseRequest))
            ->post(route('purchase-requests.approve', $purchaseRequest), [
                'supplier_id' => $supplier->id,
                'approved_unit_costs' => [
                    $capLine->id => 1.50,
                ],
            ])
            ->assertRedirect(route('purchase-requests.edit', $purchaseRequest))
            ->assertSessionHasErrors('approved_unit_costs.'.$sealLine->id);

        $this->assertSame(
            PurchaseRequest::STATUS_SUBMITTED,
            $purchaseRequest->fresh()->status
        );

        $this->assertNull($capLine->fresh()->approved_unit_cost);
        $this->assertNull($sealLine->fresh()->approved_unit_cost);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    private function inventoryItem(
        string $sku,
        string $name,
        string $category,
    ): InventoryItem {
        return InventoryItem::create([
            'sku' => $sku,
            'name' => $name,
            'category' => $category,
            'unit' => 'unit',
            'reorder_level' => 0,
            'is_sellable' => false,
            'is_active' => true,
        ]);
    }
}
