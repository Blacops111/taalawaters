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

class DeliveryNoteFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_note_can_link_sale_assignment_destination_and_creator(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $sale = SalesOrder::create([
            'sale_type' => SalesOrder::TYPE_BUSINESS,
            'reference' => 'SALE-FOUNDATION-001',
            'status' => SalesOrder::STATUS_COMPLETED,
            'sale_at' => now(),
            'total_amount' => 2500,
            'created_by' => $admin->id,
        ]);

        $driver = Driver::create([
            'name' => 'Delivery Driver',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'registration_number' => 'KME 111D',
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

        $deliveryNote = DeliveryNote::create([
            'reference' => 'DN-00000001',
            'sales_order_id' => $sale->id,
            'vehicle_assignment_id' => $assignment->id,
            'status' => DeliveryNote::STATUS_DRAFT,
            'delivery_address' => 'Ugunja Town',
            'scheduled_at' => '2026-09-21 09:00:00',
            'created_by' => $admin->id,
            'notes' => 'Morning route.',
        ]);

        $this->assertDatabaseHas('delivery_notes', [
            'id' => $deliveryNote->id,
            'reference' => 'DN-00000001',
            'sales_order_id' => $sale->id,
            'vehicle_assignment_id' => $assignment->id,
            'status' => 'draft',
            'delivery_address' => 'Ugunja Town',
            'created_by' => $admin->id,
        ]);

        $this->assertSame($sale->id, $deliveryNote->salesOrder->id);
        $this->assertSame($assignment->id, $deliveryNote->vehicleAssignment->id);
        $this->assertSame($admin->id, $deliveryNote->creator->id);
    }

    public function test_delivery_note_items_keep_exact_sales_order_line_and_inventory_item_links(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $item = InventoryItem::create([
            'sku' => 'DN-TEST-ITEM',
            'name' => 'Delivery Test Water',
            'category' => 'finished_product',
            'unit' => 'bottle',
            'is_active' => true,
        ]);

        $sale = SalesOrder::create([
            'sale_type' => SalesOrder::TYPE_BUSINESS,
            'reference' => 'SALE-FOUNDATION-002',
            'status' => SalesOrder::STATUS_COMPLETED,
            'sale_at' => now(),
            'total_amount' => 1000,
            'created_by' => $admin->id,
        ]);

        $saleItem = SalesOrderItem::create([
            'sales_order_id' => $sale->id,
            'inventory_item_id' => $item->id,
            'quantity' => 20,
            'unit_price' => 50,
            'line_total' => 1000,
        ]);

        $deliveryNote = DeliveryNote::create([
            'reference' => 'DN-00000002',
            'sales_order_id' => $sale->id,
            'status' => DeliveryNote::STATUS_DRAFT,
            'created_by' => $admin->id,
        ]);

        $deliveryItem = DeliveryNoteItem::create([
            'delivery_note_id' => $deliveryNote->id,
            'sales_order_item_id' => $saleItem->id,
            'inventory_item_id' => $item->id,
            'quantity' => 12,
        ]);

        $this->assertDatabaseHas('delivery_note_items', [
            'id' => $deliveryItem->id,
            'delivery_note_id' => $deliveryNote->id,
            'sales_order_item_id' => $saleItem->id,
            'inventory_item_id' => $item->id,
            'quantity' => 12,
        ]);

        $this->assertSame($saleItem->id, $deliveryItem->salesOrderItem->id);
        $this->assertSame($item->id, $deliveryItem->inventoryItem->id);
    }

    public function test_sale_and_assignment_expose_delivery_note_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $sale = SalesOrder::create([
            'sale_type' => SalesOrder::TYPE_BUSINESS,
            'reference' => 'SALE-FOUNDATION-003',
            'status' => SalesOrder::STATUS_COMPLETED,
            'sale_at' => now(),
            'total_amount' => 500,
            'created_by' => $admin->id,
        ]);

        $driver = Driver::create([
            'name' => 'History Driver',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'registration_number' => 'KTA 222T',
            'vehicle_type' => Vehicle::TYPE_TANKER_TRUCK,
            'status' => Vehicle::STATUS_ASSIGNED,
            'is_active' => true,
        ]);

        $assignment = VehicleAssignment::create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);

        DeliveryNote::create([
            'reference' => 'DN-00000003',
            'sales_order_id' => $sale->id,
            'vehicle_assignment_id' => $assignment->id,
            'status' => DeliveryNote::STATUS_DRAFT,
            'created_by' => $admin->id,
        ]);

        $this->assertCount(1, $sale->deliveryNotes);
        $this->assertCount(1, $assignment->deliveryNotes);
        $this->assertSame(
            'DN-00000003',
            $sale->deliveryNotes->first()->reference
        );
    }
}
