<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseRequestSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_submit_draft_with_items_without_supplier(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $purchaseRequest = PurchaseRequest::create([
            'reference' => 'PREQ-00000010',
            'status' => PurchaseRequest::STATUS_DRAFT,
            'supplier_id' => null,
            'requested_by' => $admin->id,
            'requested_at' => now(),
        ]);

        $item = InventoryItem::create([
            'sku' => 'CAP-SUBMIT',
            'name' => 'Submission Test Cap',
            'category' => 'cap',
            'unit' => 'unit',
            'reorder_level' => 0,
            'is_sellable' => false,
            'is_active' => true,
        ]);

        $purchaseRequest->items()->create([
            'inventory_item_id' => $item->id,
            'quantity' => 500,
        ]);

        $this->actingAs($admin)
            ->post(route('purchase-requests.submit', $purchaseRequest))
            ->assertRedirect(route('purchase-requests.edit', $purchaseRequest))
            ->assertSessionHas('success');

        $purchaseRequest->refresh();

        $this->assertSame(PurchaseRequest::STATUS_SUBMITTED, $purchaseRequest->status);
        $this->assertSame($admin->id, $purchaseRequest->submitted_by);
        $this->assertNotNull($purchaseRequest->submitted_at);
        $this->assertNull($purchaseRequest->supplier_id);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_empty_draft_cannot_be_submitted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $purchaseRequest = PurchaseRequest::create([
            'reference' => 'PREQ-00000011',
            'status' => PurchaseRequest::STATUS_DRAFT,
            'requested_by' => $admin->id,
            'requested_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('purchase-requests.edit', $purchaseRequest))
            ->post(route('purchase-requests.submit', $purchaseRequest))
            ->assertRedirect(route('purchase-requests.edit', $purchaseRequest))
            ->assertSessionHasErrors('purchase_request');

        $this->assertSame(
            PurchaseRequest::STATUS_DRAFT,
            $purchaseRequest->fresh()->status
        );
    }

    public function test_submitted_request_cannot_be_submitted_again(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $purchaseRequest = PurchaseRequest::create([
            'reference' => 'PREQ-00000012',
            'status' => PurchaseRequest::STATUS_SUBMITTED,
            'requested_by' => $admin->id,
            'requested_at' => now(),
            'submitted_by' => $admin->id,
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('purchase-requests.edit', $purchaseRequest))
            ->post(route('purchase-requests.submit', $purchaseRequest))
            ->assertRedirect(route('purchase-requests.edit', $purchaseRequest))
            ->assertSessionHasErrors('purchase_request');
    }

    public function test_submitted_request_page_is_read_only_and_shows_submission_details(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $purchaseRequest = PurchaseRequest::create([
            'reference' => 'PREQ-00000013',
            'status' => PurchaseRequest::STATUS_SUBMITTED,
            'requested_by' => $admin->id,
            'requested_at' => now(),
            'submitted_by' => $admin->id,
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('purchase-requests.edit', $purchaseRequest))
            ->assertOk()
            ->assertSee('Submitted By')
            ->assertSee($admin->name)
            ->assertSee('Items are read-only')
            ->assertDontSee('Submit for Approval');
    }
}
