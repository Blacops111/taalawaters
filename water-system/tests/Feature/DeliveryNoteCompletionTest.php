<?php

namespace Tests\Feature;

use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\Driver;
use App\Models\InventoryItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryNoteCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_mark_dispatched_delivery_note_as_delivered(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [, $deliveryNote] = $this->dispatchedDeliveryNote($admin);

        $this->actingAs($admin)
            ->post(route('delivery-notes.deliver', $deliveryNote))
            ->assertRedirect(route('sales-orders.show', $deliveryNote->sales_order_id))
            ->assertSessionHas('success');

        $deliveryNote->refresh();

        $this->assertSame(DeliveryNote::STATUS_DELIVERED, $deliveryNote->status);
        $this->assertNotNull($deliveryNote->delivered_at);
    }

    public function test_draft_delivery_note_cannot_be_marked_delivered(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [, $deliveryNote] = $this->dispatchedDeliveryNote($admin);

        $deliveryNote->update([
            'status' => DeliveryNote::STATUS_DRAFT,
            'dispatched_at' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('delivery-notes.deliver', $deliveryNote))
            ->assertSessionHasErrors('delivery_note');

        $this->assertSame(
            DeliveryNote::STATUS_DRAFT,
            $deliveryNote->fresh()->status
        );
        $this->assertNull($deliveryNote->fresh()->delivered_at);
    }

    public function test_delivery_note_cannot_be_marked_delivered_twice(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [, $deliveryNote] = $this->dispatchedDeliveryNote($admin);

        $deliveryNote->update([
            'status' => DeliveryNote::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('delivery-notes.deliver', $deliveryNote))
            ->assertSessionHasErrors('delivery_note');
    }

    public function test_reversed_sale_delivery_note_cannot_be_marked_delivered(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$sale, $deliveryNote] = $this->dispatchedDeliveryNote($admin);

        $sale->update([
            'status' => SalesOrder::STATUS_REVERSED,
        ]);

        $this->actingAs($admin)
            ->post(route('delivery-notes.deliver', $deliveryNote))
            ->assertSessionHasErrors('delivery_note');

        $this->assertSame(
            DeliveryNote::STATUS_DISPATCHED,
            $deliveryNote->fresh()->status
        );
    }

    public function test_assignment_may_end_after_dispatch_without_erasing_delivery_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [, $deliveryNote, $assignment] = $this->dispatchedDeliveryNote($admin);

        $assignment->update([
            'unassigned_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('delivery-notes.deliver', $deliveryNote))
            ->assertRedirect(route('sales-orders.show', $deliveryNote->sales_order_id))
            ->assertSessionHas('success');

        $this->assertSame(
            DeliveryNote::STATUS_DELIVERED,
            $deliveryNote->fresh()->status
        );

        $this->assertSame(
            $assignment->id,
            $deliveryNote->fresh()->vehicle_assignment_id
        );
    }

    public function test_delivery_completion_does_not_create_inventory_movements(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [, $deliveryNote] = $this->dispatchedDeliveryNote($admin);

        $this->actingAs($admin)
            ->post(route('delivery-notes.deliver', $deliveryNote))
            ->assertRedirect(route('sales-orders.show', $deliveryNote->sales_order_id));

        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_non_admin_cannot_mark_delivery_note_delivered(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        [, $deliveryNote] = $this->dispatchedDeliveryNote($admin);

        $this->actingAs($staff)
            ->post(route('delivery-notes.deliver', $deliveryNote))
            ->assertRedirect('/dashboard');

        $this->assertSame(
            DeliveryNote::STATUS_DISPATCHED,
            $deliveryNote->fresh()->status
        );
    }

    private function dispatchedDeliveryNote(User $admin): array
    {
        $item = InventoryItem::create([
            'sku' => 'DN-DELIVER-'.uniqid(),
            'name' => 'Delivery Completion Test Water',
            'category' => 'finished_product',
            'unit' => 'bottle',
            'reorder_level' => 0,
            'retail_price' => 50,
            'wholesale_price' => 45,
            'is_sellable' => true,
            'is_active' => true,
        ]);

        $sale = SalesOrder::create([
            'sale_type' => SalesOrder::TYPE_BUSINESS,
            'reference' => 'SALE-DELIVER-'.uniqid(),
            'status' => SalesOrder::STATUS_COMPLETED,
            'sale_at' => now(),
            'total_amount' => 450,
            'created_by' => $admin->id,
        ]);

        $saleItem = SalesOrderItem::create([
            'sales_order_id' => $sale->id,
            'inventory_item_id' => $item->id,
            'quantity' => 10,
            'unit_price' => 45,
            'line_total' => 450,
        ]);

        $driver = Driver::create([
            'name' => 'Delivery Completion Driver '.uniqid(),
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'registration_number' => 'KME '.random_int(100, 999).'C',
            'vehicle_type' => Vehicle::TYPE_MOTORBIKE,
            'status' => Vehicle::STATUS_ASSIGNED,
            'is_active' => true,
        ]);

        $assignment = VehicleAssignment::create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'assigned_by' => $admin->id,
            'assigned_at' => now()->subHour(),
        ]);

        $deliveryNote = DeliveryNote::create([
            'reference' => 'DN-DELIVER-'.uniqid(),
            'sales_order_id' => $sale->id,
            'vehicle_assignment_id' => $assignment->id,
            'status' => DeliveryNote::STATUS_DISPATCHED,
            'dispatched_at' => now()->subMinutes(30),
            'created_by' => $admin->id,
        ]);

        DeliveryNoteItem::create([
            'delivery_note_id' => $deliveryNote->id,
            'sales_order_item_id' => $saleItem->id,
            'inventory_item_id' => $item->id,
            'quantity' => 5,
        ]);

        return [$sale, $deliveryNote, $assignment];
    }
}
