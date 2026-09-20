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
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DriverDeliveryConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_sees_only_deliveries_assigned_to_them(): void
    {
        [$driverUser, $driver] = $this->driverAccount('Own Driver');
        [$otherUser, $otherDriver] = $this->driverAccount('Other Driver');

        $ownDelivery = $this->dispatchedDelivery($driverUser, $driver, '482731');
        $otherDelivery = $this->dispatchedDelivery($otherUser, $otherDriver, '593104');

        $this->actingAs($driverUser)
            ->get(route('driver.deliveries.index'))
            ->assertOk()
            ->assertSee($ownDelivery->reference)
            ->assertDontSee($otherDelivery->reference);
    }

    public function test_correct_code_marks_delivery_delivered_and_records_driver_audit(): void
    {
        [$driverUser, $driver] = $this->driverAccount();
        $deliveryNote = $this->dispatchedDelivery($driverUser, $driver, '482731');

        $this->actingAs($driverUser)
            ->post(route('driver.deliveries.confirm.store', $deliveryNote), [
                'confirmation_code' => '482731',
            ])
            ->assertRedirect(route('driver.deliveries.index'))
            ->assertSessionHas('success');

        $deliveryNote->refresh();

        $this->assertSame(DeliveryNote::STATUS_DELIVERED, $deliveryNote->status);
        $this->assertNotNull($deliveryNote->delivered_at);
        $this->assertNotNull($deliveryNote->confirmation_code_verified_at);
        $this->assertSame(
            $driverUser->id,
            $deliveryNote->confirmation_code_verified_by
        );
        $this->assertNull($deliveryNote->confirmation_code_hash);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_code_cannot_be_verified_before_notification_is_accepted(): void
    {
        [$driverUser, $driver] = $this->driverAccount();
        $deliveryNote = $this->dispatchedDelivery($driverUser, $driver, '482731');

        $deliveryNote->forceFill([
            'confirmation_code_sms_sent_at' => null,
            'confirmation_code_email_sent_at' => null,
        ])->save();

        $this->actingAs($driverUser)
            ->post(route('driver.deliveries.confirm.store', $deliveryNote), [
                'confirmation_code' => '482731',
            ])
            ->assertSessionHasErrors('confirmation_code');

        $deliveryNote->refresh();

        $this->assertSame(
            DeliveryNote::STATUS_DISPATCHED,
            $deliveryNote->status
        );
        $this->assertNull($deliveryNote->delivered_at);
        $this->assertSame(0, $deliveryNote->confirmation_code_failed_attempts);
    }

    public function test_driver_sees_sending_state_until_notification_is_accepted(): void
    {
        [$driverUser, $driver] = $this->driverAccount();
        $deliveryNote = $this->dispatchedDelivery($driverUser, $driver, '482731');

        $deliveryNote->forceFill([
            'confirmation_code_sms_sent_at' => null,
            'confirmation_code_email_sent_at' => null,
        ])->save();

        $this->actingAs($driverUser)
            ->get(route('driver.deliveries.confirm', $deliveryNote))
            ->assertOk()
            ->assertSee('Sending Confirmation Code')
            ->assertDontSee('Verify & Mark Delivered');

        $deliveryNote->forceFill([
            'confirmation_code_sms_sent_at' => now(),
        ])->save();

        $this->actingAs($driverUser)
            ->get(route('driver.deliveries.confirm', $deliveryNote))
            ->assertOk()
            ->assertSeeText('Verify & Mark Delivered');
    }

    public function test_incorrect_code_increments_failed_attempts_without_delivering(): void
    {
        [$driverUser, $driver] = $this->driverAccount();
        $deliveryNote = $this->dispatchedDelivery($driverUser, $driver, '482731');

        $this->actingAs($driverUser)
            ->post(route('driver.deliveries.confirm.store', $deliveryNote), [
                'confirmation_code' => '111111',
            ])
            ->assertSessionHasErrors('confirmation_code');

        $deliveryNote->refresh();

        $this->assertSame(1, $deliveryNote->confirmation_code_failed_attempts);
        $this->assertSame(DeliveryNote::STATUS_DISPATCHED, $deliveryNote->status);
        $this->assertNull($deliveryNote->delivered_at);
    }

    public function test_confirmation_code_locks_after_maximum_failed_attempts(): void
    {
        config(['delivery.confirmation_code_max_attempts' => 5]);

        [$driverUser, $driver] = $this->driverAccount();
        $deliveryNote = $this->dispatchedDelivery($driverUser, $driver, '482731');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->actingAs($driverUser)
                ->post(route('driver.deliveries.confirm.store', $deliveryNote), [
                    'confirmation_code' => '111111',
                ])
                ->assertSessionHasErrors('confirmation_code');
        }

        $deliveryNote->refresh();

        $this->assertSame(5, $deliveryNote->confirmation_code_failed_attempts);
        $this->assertNotNull($deliveryNote->confirmation_code_locked_at);
        $this->assertSame(DeliveryNote::STATUS_DISPATCHED, $deliveryNote->status);

        $this->actingAs($driverUser)
            ->post(route('driver.deliveries.confirm.store', $deliveryNote), [
                'confirmation_code' => '482731',
            ])
            ->assertSessionHasErrors('confirmation_code');

        $this->assertSame(
            DeliveryNote::STATUS_DISPATCHED,
            $deliveryNote->fresh()->status
        );
    }

    public function test_expired_code_cannot_mark_delivery_delivered(): void
    {
        [$driverUser, $driver] = $this->driverAccount();
        $deliveryNote = $this->dispatchedDelivery($driverUser, $driver, '482731');

        $deliveryNote->forceFill([
            'confirmation_code_expires_at' => now()->subMinute(),
        ])->save();

        $this->actingAs($driverUser)
            ->post(route('driver.deliveries.confirm.store', $deliveryNote), [
                'confirmation_code' => '482731',
            ])
            ->assertSessionHasErrors('confirmation_code');

        $this->assertSame(
            DeliveryNote::STATUS_DISPATCHED,
            $deliveryNote->fresh()->status
        );
    }

    public function test_driver_cannot_view_or_confirm_another_drivers_delivery(): void
    {
        [$driverUser] = $this->driverAccount('First Driver');
        [$otherUser, $otherDriver] = $this->driverAccount('Second Driver');

        $otherDelivery = $this->dispatchedDelivery(
            $otherUser,
            $otherDriver,
            '482731'
        );

        $this->actingAs($driverUser)
            ->get(route('driver.deliveries.confirm', $otherDelivery))
            ->assertNotFound();

        $this->actingAs($driverUser)
            ->post(route('driver.deliveries.confirm.store', $otherDelivery), [
                'confirmation_code' => '482731',
            ])
            ->assertNotFound();

        $this->assertSame(
            DeliveryNote::STATUS_DISPATCHED,
            $otherDelivery->fresh()->status
        );
    }

    public function test_reversed_sale_cannot_be_confirmed_as_delivered(): void
    {
        [$driverUser, $driver] = $this->driverAccount();
        $deliveryNote = $this->dispatchedDelivery($driverUser, $driver, '482731');

        $deliveryNote->salesOrder->update([
            'status' => SalesOrder::STATUS_REVERSED,
        ]);

        $this->actingAs($driverUser)
            ->post(route('driver.deliveries.confirm.store', $deliveryNote), [
                'confirmation_code' => '482731',
            ])
            ->assertSessionHasErrors('confirmation_code');

        $this->assertSame(
            DeliveryNote::STATUS_DISPATCHED,
            $deliveryNote->fresh()->status
        );
    }

    public function test_manual_admin_delivery_completion_endpoint_is_removed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$driverUser, $driver] = $this->driverAccount();
        $deliveryNote = $this->dispatchedDelivery($driverUser, $driver, '482731');

        $this->actingAs($admin)
            ->post('/delivery-notes/'.$deliveryNote->id.'/deliver')
            ->assertNotFound();

        $this->assertSame(
            DeliveryNote::STATUS_DISPATCHED,
            $deliveryNote->fresh()->status
        );
    }

    public function test_driver_dashboard_redirects_to_driver_delivery_portal(): void
    {
        [$driverUser] = $this->driverAccount();

        $this->actingAs($driverUser)
            ->get(route('dashboard'))
            ->assertRedirect(route('driver.deliveries.index'));
    }

    private function driverAccount(
        string $name = 'Delivery Driver',
    ): array {
        $user = User::factory()->create([
            'name' => $name,
            'role' => 'driver',
        ]);

        $driver = Driver::create([
            'user_id' => $user->id,
            'name' => $name,
            'phone' => '+254700000001',
            'is_active' => true,
        ]);

        return [$user, $driver];
    }

    private function dispatchedDelivery(
        User $driverUser,
        Driver $driver,
        string $code,
    ): DeliveryNote {
        $admin = User::factory()->create(['role' => 'admin']);

        $item = InventoryItem::create([
            'sku' => 'DRV-CONF-'.uniqid(),
            'name' => 'Driver Confirmation Water',
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
            'reference' => 'SALE-DRV-'.uniqid(),
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

        $vehicle = Vehicle::create([
            'registration_number' => 'K'.strtoupper(substr(md5(uniqid()), 0, 7)),
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
            'reference' => 'DN-DRV-'.uniqid(),
            'sales_order_id' => $sale->id,
            'vehicle_assignment_id' => $assignment->id,
            'status' => DeliveryNote::STATUS_DISPATCHED,
            'recipient_name' => 'Receiver',
            'recipient_phone' => '+254712345678',
            'delivery_address' => 'Ugunja Town',
            'dispatched_at' => now()->subMinutes(20),
            'created_by' => $admin->id,
        ]);

        $deliveryNote->forceFill([
            'confirmation_code_hash' => Hash::make($code),
            'confirmation_code_generated_at' => now()->subMinutes(20),
            'confirmation_code_expires_at' => now()->addHour(),
            'confirmation_code_sms_sent_at' => now()->subMinutes(19),
            'confirmation_code_failed_attempts' => 0,
        ])->save();

        DeliveryNoteItem::create([
            'delivery_note_id' => $deliveryNote->id,
            'sales_order_item_id' => $saleItem->id,
            'inventory_item_id' => $item->id,
            'quantity' => 5,
        ]);

        $deliveryNote->setRelation('salesOrder', $sale);

        return $deliveryNote;
    }
}
