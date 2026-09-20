<?php

namespace Tests\Feature;

use App\Jobs\SendDeliveryConfirmationCode;
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
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DriverDeliveryCodeSendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_assigned_driver_can_send_fresh_confirmation_code_when_near_recipient(): void
    {
        [$driverUser, $driver] = $this->driverAccount();
        $deliveryNote = $this->dispatchedDelivery($driver);

        $this->actingAs($driverUser)
            ->get(route('driver.deliveries.index'))
            ->assertOk()
            ->assertSee('Send Code')
            ->assertDontSee('Enter Code');

        $this->actingAs($driverUser)
            ->post(route('driver.deliveries.send-code', $deliveryNote))
            ->assertRedirect(route('driver.deliveries.confirm', $deliveryNote))
            ->assertSessionHas('success');

        $deliveryNote->refresh();

        $this->assertNotNull($deliveryNote->confirmation_code_hash);
        $this->assertNotNull($deliveryNote->confirmation_code_generated_at);
        $this->assertNotNull($deliveryNote->confirmation_code_expires_at);
        $this->assertSame(0, $deliveryNote->confirmation_code_failed_attempts);
        $this->assertNull($deliveryNote->confirmation_code_locked_at);

        Queue::assertPushed(
            SendDeliveryConfirmationCode::class,
            function (SendDeliveryConfirmationCode $job) use ($deliveryNote) {
                return $job->deliveryNoteId === $deliveryNote->id
                    && Hash::check(
                        $job->code,
                        $deliveryNote->confirmation_code_hash
                    );
            }
        );
    }

    public function test_driver_cannot_generate_codes_repeatedly_inside_cooldown(): void
    {
        config([
            'delivery.confirmation_code_resend_cooldown_seconds' => 60,
        ]);

        [$driverUser, $driver] = $this->driverAccount();
        $deliveryNote = $this->dispatchedDelivery($driver);

        $this->actingAs($driverUser)
            ->post(route('driver.deliveries.send-code', $deliveryNote))
            ->assertSessionHas('success');

        $firstHash = $deliveryNote->fresh()->confirmation_code_hash;

        $this->actingAs($driverUser)
            ->post(route('driver.deliveries.send-code', $deliveryNote))
            ->assertSessionHasErrors('confirmation_code');

        $this->assertSame(
            $firstHash,
            $deliveryNote->fresh()->confirmation_code_hash
        );

        Queue::assertPushed(SendDeliveryConfirmationCode::class, 1);
    }

    public function test_driver_can_replace_expired_or_locked_code_after_cooldown(): void
    {
        config([
            'delivery.confirmation_code_resend_cooldown_seconds' => 60,
        ]);

        [$driverUser, $driver] = $this->driverAccount();
        $deliveryNote = $this->dispatchedDelivery($driver);

        $deliveryNote->forceFill([
            'confirmation_code_hash' => Hash::make('111222'),
            'confirmation_code_generated_at' => now()->subMinutes(5),
            'confirmation_code_expires_at' => now()->subMinute(),
            'confirmation_code_failed_attempts' => 5,
            'confirmation_code_locked_at' => now()->subMinute(),
        ])->save();

        $oldHash = $deliveryNote->confirmation_code_hash;

        $this->actingAs($driverUser)
            ->post(route('driver.deliveries.send-code', $deliveryNote))
            ->assertRedirect(route('driver.deliveries.confirm', $deliveryNote))
            ->assertSessionHas('success');

        $deliveryNote->refresh();

        $this->assertNotSame($oldHash, $deliveryNote->confirmation_code_hash);
        $this->assertTrue($deliveryNote->confirmation_code_expires_at->isFuture());
        $this->assertSame(0, $deliveryNote->confirmation_code_failed_attempts);
        $this->assertNull($deliveryNote->confirmation_code_locked_at);

        Queue::assertPushed(SendDeliveryConfirmationCode::class, 1);
    }

    public function test_driver_cannot_send_code_for_another_drivers_delivery(): void
    {
        [$driverUser] = $this->driverAccount('First Driver');
        [, $otherDriver] = $this->driverAccount('Second Driver');
        $deliveryNote = $this->dispatchedDelivery($otherDriver);

        $this->actingAs($driverUser)
            ->post(route('driver.deliveries.send-code', $deliveryNote))
            ->assertNotFound();

        $this->assertNull(
            $deliveryNote->fresh()->confirmation_code_hash
        );

        Queue::assertNothingPushed();
    }

    public function test_reversed_sale_delivery_cannot_receive_confirmation_code(): void
    {
        [$driverUser, $driver] = $this->driverAccount();
        $deliveryNote = $this->dispatchedDelivery($driver);

        $deliveryNote->salesOrder->update([
            'status' => SalesOrder::STATUS_REVERSED,
        ]);

        $this->actingAs($driverUser)
            ->post(route('driver.deliveries.send-code', $deliveryNote))
            ->assertSessionHasErrors('confirmation_code');

        $this->assertNull(
            $deliveryNote->fresh()->confirmation_code_hash
        );

        Queue::assertNothingPushed();
    }

    public function test_admin_cannot_use_driver_send_code_route(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [, $driver] = $this->driverAccount();
        $deliveryNote = $this->dispatchedDelivery($driver);

        $this->actingAs($admin)
            ->post(route('driver.deliveries.send-code', $deliveryNote))
            ->assertForbidden();

        $this->assertNull(
            $deliveryNote->fresh()->confirmation_code_hash
        );

        Queue::assertNothingPushed();
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
        Driver $driver,
    ): DeliveryNote {
        $admin = User::factory()->create(['role' => 'admin']);

        $item = InventoryItem::create([
            'sku' => 'DRV-SEND-'.uniqid(),
            'name' => 'Driver Send Code Water',
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
            'reference' => 'SALE-SEND-'.uniqid(),
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
            'reference' => 'DN-SEND-'.uniqid(),
            'sales_order_id' => $sale->id,
            'vehicle_assignment_id' => $assignment->id,
            'status' => DeliveryNote::STATUS_DISPATCHED,
            'recipient_name' => 'Delivery Receiver',
            'recipient_phone' => '+254712345678',
            'recipient_email' => 'receiver@example.com',
            'delivery_address' => 'Ugunja Town',
            'dispatched_at' => now()->subMinutes(20),
            'created_by' => $admin->id,
        ]);

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
