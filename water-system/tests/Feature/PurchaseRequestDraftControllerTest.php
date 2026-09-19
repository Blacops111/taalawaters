<?php

namespace Tests\Feature;

use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseRequestDraftControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_purchase_request_list_and_create_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Supplier::create([
            'name' => 'Active Packaging Supplier',
            'is_active' => true,
        ]);

        Supplier::create([
            'name' => 'Inactive Packaging Supplier',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('purchase-requests.index'))
            ->assertOk()
            ->assertSee('Purchase Requests')
            ->assertSee('New Purchase Request');

        $this->actingAs($admin)
            ->get(route('purchase-requests.create'))
            ->assertOk()
            ->assertSee('New Purchase Request')
            ->assertSee('Active Packaging Supplier')
            ->assertDontSee('Inactive Packaging Supplier')
            ->assertSee('Select later during approval');
    }

    public function test_admin_can_create_draft_purchase_request_without_supplier(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->post(route('purchase-requests.store'), [
                'supplier_id' => '',
                'requested_at' => '2026-09-19T14:30',
                'notes' => 'Need more packaging materials.',
            ]);

        $purchaseRequest = PurchaseRequest::firstOrFail();

        $response
            ->assertRedirect(route('purchase-requests.index'))
            ->assertSessionHas('success');

        $this->assertSame('PREQ-00000001', $purchaseRequest->reference);
        $this->assertSame(PurchaseRequest::STATUS_DRAFT, $purchaseRequest->status);
        $this->assertNull($purchaseRequest->supplier_id);
        $this->assertSame($admin->id, $purchaseRequest->requested_by);

        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_admin_can_optionally_link_active_supplier_when_creating_draft(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $supplier = Supplier::create([
            'name' => 'Preferred Cap Supplier',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('purchase-requests.store'), [
                'supplier_id' => $supplier->id,
                'requested_at' => '2026-09-19T15:00',
            ])
            ->assertRedirect(route('purchase-requests.index'));

        $this->assertDatabaseHas('purchase_requests', [
            'supplier_id' => $supplier->id,
            'status' => PurchaseRequest::STATUS_DRAFT,
            'requested_by' => $admin->id,
        ]);
    }

    public function test_inactive_supplier_cannot_be_linked_to_new_purchase_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $supplier = Supplier::create([
            'name' => 'Inactive Supplier',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->from(route('purchase-requests.create'))
            ->post(route('purchase-requests.store'), [
                'supplier_id' => $supplier->id,
                'requested_at' => '2026-09-19T15:30',
            ])
            ->assertRedirect(route('purchase-requests.create'))
            ->assertSessionHasErrors('supplier_id');

        $this->assertDatabaseCount('purchase_requests', 0);
    }
}
