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

class DeliveryNoteDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_dispatch_page_for_draft_delivery_note(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$sale, $deliveryNote] = $this->draftDeliveryNote($admin);
        $assignment = $this->activeAssignment($admin);

        $this->actingAs($admin)
            ->get(route('delivery-notes.dispatch', $deliveryNote))
            ->assertOk()
            ->assertSee('Dispatch Delivery Note')
            ->assertSee($deliveryNote->reference)
            ->assertSee($sale->reference)
            ->assertSee($assignment->driver->name)
            ->assertSee($assignment->vehicle->registration_number);
    }

    public function test_admin_can_dispatch_draft_and_assign_driver_vehicle_at_dispatch(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [, $deliveryNote] = $this->draftDeliveryNote($admin);
        $assignment = $this->activeAssignment($admin);

        $this->actingAs($admin)
            ->post(route('delivery-notes.dispatch.store', $deliveryNote), [
                'vehicle_assignment_id' => $assignment->id,
            ])
            ->assertRedirect(route('sales-orders.show', $deliveryNote->sales_order_id))
            ->assertSessionHas('success');

        $deliveryNote->refresh();

        $this->assertSame(DeliveryNote::STATUS_DISPATCHED, $deliveryNote->status);
        $this->assertSame($assignment->id, $deliveryNote->vehicle_assignment_id);
        $this->assertNotNull($deliveryNote->dispatched_at);
    }

    public function test_dispatch_requires_active_driver_vehicle_assignment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [, $deliveryNote] = $this->draftDeliveryNote($admin);

        $this->actingAs($admin)
            ->post(route('delivery-notes.dispatch.store', $deliveryNote), [])
            ->assertSessionHasErrors('vehicle_assignment_id');

        $this->assertSame(
            DeliveryNote::STATUS_DRAFT,
            $deliveryNote->fresh()->status
        );
    }

    public function test_ended_assignment_cannot_dispatch_delivery_note(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [, $deliveryNote] = $this->draftDeliveryNote($admin);
        $assignment = $this->activeAssignment($admin);

        $assignment->update(['unassigned_at' => now()]);

        $this->actingAs($admin)
            ->post(route('delivery-notes.dispatch.store', $deliveryNote), [
                'vehicle_assignment_id' => $assignment->id,
            ])
            ->assertSessionHasErrors('vehicle_assignment_id');

        $this->assertSame(
            DeliveryNote::STATUS_DRAFT,
            $deliveryNote->fresh()->status
        );
    }

    public function test_inactive_driver_or_unavailable_vehicle_cannot_dispatch(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [, $firstNote] = $this->draftDeliveryNote($admin, 'DN-DISPATCH-A');
        $firstAssignment = $this->activeAssignment($admin, 'KME 411A');

        $firstAssignment->driver->update(['is_active' => false]);

        $this->actingAs($admin)
            ->post(route('delivery-notes.dispatch.store', $firstNote), [
                'vehicle_assignment_id' => $firstAssignment->id,
            ])
            ->assertSessionHasErrors('vehicle_assignment_id');

        [, $secondNote] = $this->draftDeliveryNote($admin, 'DN-DISPATCH-B');
        $secondAssignment = $this->activeAssignment($admin, 'KME 422B');

        $secondAssignment->vehicle->update([
            'status' => Vehicle::STATUS_MAINTENANCE,
        ]);

        $this->actingAs($admin)
            ->post(route('delivery-notes.dispatch.store', $secondNote), [
                'vehicle_assignment_id' => $secondAssignment->id,
            ])
            ->assertSessionHasErrors('vehicle_assignment_id');

        $this->assertSame(DeliveryNote::STATUS_DRAFT, $firstNote->fresh()->status);
        $this->assertSame(DeliveryNote::STATUS_DRAFT, $secondNote->fresh()->status);
    }

    public function test_dispatched_delivery_note_cannot_be_dispatched_twice(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [, $deliveryNote] = $this->draftDeliveryNote($admin);
        $assignment = $this->activeAssignment($admin);

        $deliveryNote->update([
            'vehicle_assignment_id' => $assignment->id,
            'status' => DeliveryNote::STATUS_DISPATCHED,
            'dispatched_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('delivery-notes.dispatch.store', $deliveryNote), [
                'vehicle_assignment_id' => $assignment->id,
            ])
            ->assertSessionHasErrors('delivery_note');
    }

    public function test_reversed_sale_delivery_note_cannot_be_dispatched(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$sale, $deliveryNote] = $this->draftDeliveryNote($admin);
        $assignment = $this->activeAssignment($admin);

        $sale->update(['status' => SalesOrder::STATUS_REVERSED]);

        $this->actingAs($admin)
            ->post(route('delivery-notes.dispatch.store', $deliveryNote), [
                'vehicle_assignment_id' => $assignment->id,
            ])
            ->assertSessionHasErrors('delivery_note');

        $this->assertSame(
            DeliveryNote::STATUS_DRAFT,
            $deliveryNote->fresh()->status
        );
    }

    public function test_non_admin_cannot_dispatch_delivery_note(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        [, $deliveryNote] = $this->draftDeliveryNote($admin);

        $this->actingAs($staff)
            ->get(route('delivery-notes.dispatch', $deliveryNote))
            ->assertRedirect('/dashboard');
    }

    private function draftDeliveryNote(
        User $admin,
        string $reference = 'DN-DISPATCH-001',
    ): array {
        $item = InventoryItem::create([
            'sku' => 'DN-DISPATCH-'.uniqid(),
            'name' => 'Dispatch Test Water',
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
            'reference' => 'SALE-DISPATCH-'.uniqid(),
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

        $deliveryNote = DeliveryNote::create([
            'reference' => $reference,
            'sales_order_id' => $sale->id,
            'status' => DeliveryNote::STATUS_DRAFT,
            'created_by' => $admin->id,
        ]);

        DeliveryNoteItem::create([
            'delivery_note_id' => $deliveryNote->id,
            'sales_order_item_id' => $saleItem->id,
            'inventory_item_id' => $item->id,
            'quantity' => 5,
        ]);

        return [$sale, $deliveryNote];
    }

    private function activeAssignment(
        User $admin,
        string $registration = 'KME 400D',
    ): VehicleAssignment {
        $driver = Driver::create([
            'name' => 'Dispatch Driver '.uniqid(),
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'registration_number' => $registration,
            'vehicle_type' => Vehicle::TYPE_MOTORBIKE,
            'status' => Vehicle::STATUS_ASSIGNED,
            'is_active' => true,
        ]);

        $assignment = VehicleAssignment::create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);

        $assignment->setRelation('driver', $driver);
        $assignment->setRelation('vehicle', $vehicle);

        return $assignment;
    }
}
