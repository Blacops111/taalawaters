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

class DeliveryNoteDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_delivery_note_form_for_completed_sale(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$sale, $saleItem] = $this->completedSale($admin, 10);
        $assignment = $this->activeAssignment($admin);

        $this->actingAs($admin)
            ->get(route('delivery-notes.create', $sale))
            ->assertOk()
            ->assertSee('Create Delivery Note')
            ->assertSee($sale->reference)
            ->assertSee($saleItem->inventoryItem->name)
            ->assertSee($assignment->driver->name)
            ->assertSee($assignment->vehicle->registration_number);
    }

    public function test_admin_can_create_partial_draft_delivery_note(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$sale, $saleItem] = $this->completedSale($admin, 10);
        $assignment = $this->activeAssignment($admin);

        $this->actingAs($admin)
            ->post(route('delivery-notes.store', $sale), [
                'vehicle_assignment_id' => $assignment->id,
                'delivery_address' => ' Ugunja Town ',
                'scheduled_at' => '2026-09-21 09:00:00',
                'notes' => ' First trip ',
                'quantities' => [
                    $saleItem->id => 4,
                ],
            ])
            ->assertRedirect(route('sales-orders.show', $sale))
            ->assertSessionHas('success');

        $deliveryNote = DeliveryNote::query()->firstOrFail();

        $this->assertSame('DN-00000001', $deliveryNote->reference);
        $this->assertSame(DeliveryNote::STATUS_DRAFT, $deliveryNote->status);

        $this->assertDatabaseHas('delivery_notes', [
            'id' => $deliveryNote->id,
            'sales_order_id' => $sale->id,
            'vehicle_assignment_id' => $assignment->id,
            'delivery_address' => 'Ugunja Town',
            'created_by' => $admin->id,
            'notes' => 'First trip',
        ]);

        $this->assertDatabaseHas('delivery_note_items', [
            'delivery_note_id' => $deliveryNote->id,
            'sales_order_item_id' => $saleItem->id,
            'inventory_item_id' => $saleItem->inventory_item_id,
            'quantity' => 4,
        ]);
    }

    public function test_multiple_drafts_cannot_allocate_more_than_sale_quantity(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$sale, $saleItem] = $this->completedSale($admin, 10);

        $first = DeliveryNote::create([
            'reference' => 'DN-EXISTING-001',
            'sales_order_id' => $sale->id,
            'status' => DeliveryNote::STATUS_DRAFT,
            'created_by' => $admin->id,
        ]);

        DeliveryNoteItem::create([
            'delivery_note_id' => $first->id,
            'sales_order_item_id' => $saleItem->id,
            'inventory_item_id' => $saleItem->inventory_item_id,
            'quantity' => 7,
        ]);

        $this->actingAs($admin)
            ->from(route('delivery-notes.create', $sale))
            ->post(route('delivery-notes.store', $sale), [
                'quantities' => [
                    $saleItem->id => 4,
                ],
            ])
            ->assertRedirect(route('delivery-notes.create', $sale))
            ->assertSessionHasErrors('quantities.'.$saleItem->id);

        $this->assertDatabaseCount('delivery_notes', 1);
    }

    public function test_delivery_note_requires_at_least_one_positive_quantity(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$sale, $saleItem] = $this->completedSale($admin, 10);

        $this->actingAs($admin)
            ->post(route('delivery-notes.store', $sale), [
                'quantities' => [
                    $saleItem->id => 0,
                ],
            ])
            ->assertSessionHasErrors('quantities');

        $this->assertDatabaseCount('delivery_notes', 0);
    }

    public function test_ended_assignment_cannot_be_used_on_new_delivery_note(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$sale, $saleItem] = $this->completedSale($admin, 10);
        $assignment = $this->activeAssignment($admin);

        $assignment->update([
            'unassigned_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('delivery-notes.store', $sale), [
                'vehicle_assignment_id' => $assignment->id,
                'quantities' => [
                    $saleItem->id => 2,
                ],
            ])
            ->assertSessionHasErrors('vehicle_assignment_id');

        $this->assertDatabaseCount('delivery_notes', 0);
    }

    public function test_draft_or_reversed_sale_cannot_create_delivery_note(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$sale, $saleItem] = $this->completedSale($admin, 10);

        $sale->update(['status' => SalesOrder::STATUS_REVERSED]);

        $this->actingAs($admin)
            ->get(route('delivery-notes.create', $sale))
            ->assertNotFound();

        $this->actingAs($admin)
            ->post(route('delivery-notes.store', $sale), [
                'quantities' => [
                    $saleItem->id => 1,
                ],
            ])
            ->assertSessionHasErrors('sales_order');

        $this->assertDatabaseCount('delivery_notes', 0);
    }

    public function test_non_admin_cannot_manage_delivery_note_drafts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        [$sale] = $this->completedSale($admin, 5);

        $this->actingAs($staff)
            ->get(route('delivery-notes.create', $sale))
            ->assertRedirect('/dashboard');
    }

    private function completedSale(User $admin, int $quantity): array
    {
        $item = InventoryItem::create([
            'sku' => 'DN-DRAFT-'.uniqid(),
            'name' => 'Delivery Draft Test Water',
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
            'reference' => 'SALE-DN-'.uniqid(),
            'status' => SalesOrder::STATUS_COMPLETED,
            'sale_at' => now(),
            'total_amount' => $quantity * 45,
            'created_by' => $admin->id,
        ]);

        $saleItem = SalesOrderItem::create([
            'sales_order_id' => $sale->id,
            'inventory_item_id' => $item->id,
            'quantity' => $quantity,
            'unit_price' => 45,
            'line_total' => $quantity * 45,
        ]);

        $saleItem->setRelation('inventoryItem', $item);

        return [$sale, $saleItem];
    }

    private function activeAssignment(User $admin): VehicleAssignment
    {
        $driver = Driver::create([
            'name' => 'Delivery Draft Driver',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'registration_number' => 'KME '.random_int(100, 999).'D',
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
