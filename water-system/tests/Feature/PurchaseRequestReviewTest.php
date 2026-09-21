<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseRequestReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_submitted_request_and_choose_active_supplier(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = $this->supplier('Approval Supplier', true);
        $purchaseRequest = $this->submittedRequest($admin);
        $requestItem = $purchaseRequest->items()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('purchase-requests.approve', $purchaseRequest), [
                'supplier_id' => $supplier->id,
                'approved_unit_costs' => [
                    $requestItem->id => 2.50,
                ],
            ])
            ->assertRedirect(route('purchase-requests.edit', $purchaseRequest))
            ->assertSessionHas('success');

        $purchaseRequest->refresh();

        $this->assertSame(PurchaseRequest::STATUS_APPROVED, $purchaseRequest->status);
        $this->assertSame($supplier->id, $purchaseRequest->supplier_id);
        $this->assertSame($admin->id, $purchaseRequest->approved_by);
        $this->assertNotNull($purchaseRequest->approved_at);
        $this->assertNull($purchaseRequest->rejection_reason);
        $this->assertSame('2.50', $requestItem->fresh()->approved_unit_cost);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_approval_requires_active_supplier(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $inactiveSupplier = $this->supplier('Inactive Approval Supplier', false);
        $purchaseRequest = $this->submittedRequest($admin);

        $this->actingAs($admin)
            ->from(route('purchase-requests.edit', $purchaseRequest))
            ->post(route('purchase-requests.approve', $purchaseRequest), [
                'supplier_id' => $inactiveSupplier->id,
            ])
            ->assertRedirect(route('purchase-requests.edit', $purchaseRequest))
            ->assertSessionHasErrors('supplier_id');

        $this->assertSame(
            PurchaseRequest::STATUS_SUBMITTED,
            $purchaseRequest->fresh()->status
        );
    }

    public function test_admin_can_reject_submitted_request_with_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $purchaseRequest = $this->submittedRequest($admin);

        $this->actingAs($admin)
            ->post(route('purchase-requests.reject', $purchaseRequest), [
                'rejection_reason' => 'Supplier quote exceeds the approved budget.',
            ])
            ->assertRedirect(route('purchase-requests.edit', $purchaseRequest))
            ->assertSessionHas('success');

        $purchaseRequest->refresh();

        $this->assertSame(PurchaseRequest::STATUS_REJECTED, $purchaseRequest->status);
        $this->assertSame($admin->id, $purchaseRequest->rejected_by);
        $this->assertNotNull($purchaseRequest->rejected_at);
        $this->assertSame(
            'Supplier quote exceeds the approved budget.',
            $purchaseRequest->rejection_reason
        );
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_rejection_requires_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $purchaseRequest = $this->submittedRequest($admin);

        $this->actingAs($admin)
            ->from(route('purchase-requests.edit', $purchaseRequest))
            ->post(route('purchase-requests.reject', $purchaseRequest), [
                'rejection_reason' => '',
            ])
            ->assertRedirect(route('purchase-requests.edit', $purchaseRequest))
            ->assertSessionHasErrors('rejection_reason');

        $this->assertSame(
            PurchaseRequest::STATUS_SUBMITTED,
            $purchaseRequest->fresh()->status
        );
    }

    public function test_only_submitted_requests_can_be_approved_or_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = $this->supplier('State Supplier', true);

        $draft = PurchaseRequest::create([
            'reference' => 'PREQ-00000021',
            'status' => PurchaseRequest::STATUS_DRAFT,
            'requested_by' => $admin->id,
            'requested_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('purchase-requests.edit', $draft))
            ->post(route('purchase-requests.approve', $draft), [
                'supplier_id' => $supplier->id,
            ])
            ->assertRedirect(route('purchase-requests.edit', $draft))
            ->assertSessionHasErrors('purchase_request');

        $this->actingAs($admin)
            ->from(route('purchase-requests.edit', $draft))
            ->post(route('purchase-requests.reject', $draft), [
                'rejection_reason' => 'Not ready for review.',
            ])
            ->assertRedirect(route('purchase-requests.edit', $draft))
            ->assertSessionHasErrors('purchase_request');

        $this->assertSame(PurchaseRequest::STATUS_DRAFT, $draft->fresh()->status);
    }

    public function test_submitted_page_shows_review_controls_and_final_states_are_read_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = $this->supplier('UI Review Supplier', true);
        $purchaseRequest = $this->submittedRequest($admin);

        $this->actingAs($admin)
            ->get(route('purchase-requests.edit', $purchaseRequest))
            ->assertOk()
            ->assertSee('Approve Purchase Request')
            ->assertSee('Reject Purchase Request')
            ->assertSee($supplier->name);

        $purchaseRequest->update([
            'status' => PurchaseRequest::STATUS_APPROVED,
            'supplier_id' => $supplier->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('purchase-requests.edit', $purchaseRequest))
            ->assertOk()
            ->assertSee('Approved By')
            ->assertDontSee('Approve Purchase Request')
            ->assertDontSee('Reject Purchase Request');
    }

    private function submittedRequest(User $admin): PurchaseRequest
    {
        $purchaseRequest = PurchaseRequest::create([
            'reference' => 'PREQ-'.str_pad((string) random_int(30, 99999999), 8, '0', STR_PAD_LEFT),
            'status' => PurchaseRequest::STATUS_SUBMITTED,
            'requested_by' => $admin->id,
            'requested_at' => now(),
            'submitted_by' => $admin->id,
            'submitted_at' => now(),
        ]);

        $item = InventoryItem::create([
            'sku' => 'CAP-'.strtoupper(substr(md5((string) microtime(true)), 0, 8)),
            'name' => 'Review Test Cap',
            'category' => 'cap',
            'unit' => 'unit',
            'reorder_level' => 0,
            'is_sellable' => false,
            'is_active' => true,
        ]);

        $purchaseRequest->items()->create([
            'inventory_item_id' => $item->id,
            'quantity' => 100,
        ]);

        return $purchaseRequest;
    }

    private function supplier(string $name, bool $active): Supplier
    {
        return Supplier::create([
            'name' => $name,
            'is_active' => $active,
        ]);
    }
}
