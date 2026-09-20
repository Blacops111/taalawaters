<?php

namespace Tests\Feature;

use App\Models\DeliveryNote;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryNoteHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_paginated_delivery_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sale = $this->completedSale($admin, 'SALE-HISTORY-001');

        $draft = $this->deliveryNote(
            $sale,
            $admin,
            'DN-HISTORY-DRAFT',
            DeliveryNote::STATUS_DRAFT,
            'Draft Receiver',
        );

        $delivered = $this->deliveryNote(
            $sale,
            $admin,
            'DN-HISTORY-DELIVERED',
            DeliveryNote::STATUS_DELIVERED,
            'Delivered Receiver',
        );

        $this->actingAs($admin)
            ->get(route('delivery-notes.index'))
            ->assertOk()
            ->assertSee('Delivery History')
            ->assertSee($draft->reference)
            ->assertSee($delivered->reference)
            ->assertSee($sale->reference)
            ->assertSee('Deliveries');
    }

    public function test_delivery_history_can_filter_by_status_and_search(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sale = $this->completedSale($admin, 'SALE-FILTER-001');

        $draft = $this->deliveryNote(
            $sale,
            $admin,
            'DN-FILTER-DRAFT',
            DeliveryNote::STATUS_DRAFT,
            'Alpha Receiver',
        );

        $dispatched = $this->deliveryNote(
            $sale,
            $admin,
            'DN-FILTER-DISPATCHED',
            DeliveryNote::STATUS_DISPATCHED,
            'Beta Receiver',
        );

        $this->actingAs($admin)
            ->get(route('delivery-notes.index', [
                'status' => DeliveryNote::STATUS_DISPATCHED,
            ]))
            ->assertOk()
            ->assertSee($dispatched->reference)
            ->assertDontSee($draft->reference);

        $this->actingAs($admin)
            ->get(route('delivery-notes.index', [
                'search' => 'Alpha Receiver',
            ]))
            ->assertOk()
            ->assertSee($draft->reference)
            ->assertDontSee($dispatched->reference);

        $this->actingAs($admin)
            ->get(route('delivery-notes.index', [
                'search' => $sale->reference,
            ]))
            ->assertOk()
            ->assertSee($draft->reference)
            ->assertSee($dispatched->reference);
    }

    public function test_non_admin_cannot_view_delivery_history(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('delivery-notes.index'))
            ->assertRedirect('/dashboard');
    }

    private function completedSale(
        User $admin,
        string $reference,
    ): SalesOrder {
        return SalesOrder::create([
            'sale_type' => SalesOrder::TYPE_BUSINESS,
            'reference' => $reference,
            'status' => SalesOrder::STATUS_COMPLETED,
            'sale_at' => now(),
            'total_amount' => 500,
            'created_by' => $admin->id,
        ]);
    }

    private function deliveryNote(
        SalesOrder $sale,
        User $admin,
        string $reference,
        string $status,
        string $recipient,
    ): DeliveryNote {
        return DeliveryNote::create([
            'reference' => $reference,
            'sales_order_id' => $sale->id,
            'status' => $status,
            'recipient_name' => $recipient,
            'recipient_email' => strtolower(str_replace(' ', '.', $recipient)).'@example.com',
            'dispatched_at' => in_array(
                $status,
                [
                    DeliveryNote::STATUS_DISPATCHED,
                    DeliveryNote::STATUS_DELIVERED,
                ],
                true
            ) ? now()->subHour() : null,
            'delivered_at' => $status === DeliveryNote::STATUS_DELIVERED
                ? now()->subMinutes(20)
                : null,
            'created_by' => $admin->id,
        ]);
    }
}
