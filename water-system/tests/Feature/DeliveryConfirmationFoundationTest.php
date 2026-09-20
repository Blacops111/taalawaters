<?php

namespace Tests\Feature;

use App\Models\DeliveryNote;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeliveryConfirmationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_note_can_snapshot_recipient_and_confirmation_security_state(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sale = $this->completedSale($admin);

        $deliveryNote = DeliveryNote::create([
            'reference' => 'DN-CONFIRM-0001',
            'sales_order_id' => $sale->id,
            'status' => DeliveryNote::STATUS_DISPATCHED,
            'recipient_name' => 'Jane Receiver',
            'recipient_phone' => '+254712345678',
            'dispatched_at' => now(),
            'created_by' => $admin->id,
        ]);

        $plainCode = '482731';

        $deliveryNote->forceFill([
            'confirmation_code_hash' => Hash::make($plainCode),
            'confirmation_code_generated_at' => '2026-09-20 18:00:00',
            'confirmation_code_expires_at' => '2026-09-20 18:20:00',
            'confirmation_code_last_sent_at' => '2026-09-20 18:00:00',
            'confirmation_code_failed_attempts' => 2,
            'confirmation_code_locked_at' => null,
        ])->save();

        $deliveryNote->refresh();

        $this->assertSame('Jane Receiver', $deliveryNote->recipient_name);
        $this->assertSame('+254712345678', $deliveryNote->recipient_phone);
        $this->assertSame(2, $deliveryNote->confirmation_code_failed_attempts);
        $this->assertSame(
            '2026-09-20 18:20:00',
            $deliveryNote->confirmation_code_expires_at?->format('Y-m-d H:i:s')
        );
        $this->assertTrue(
            Hash::check($plainCode, $deliveryNote->confirmation_code_hash)
        );
    }

    public function test_confirmation_code_hash_is_not_plaintext_or_serialized(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sale = $this->completedSale($admin);

        $deliveryNote = DeliveryNote::create([
            'reference' => 'DN-CONFIRM-0002',
            'sales_order_id' => $sale->id,
            'status' => DeliveryNote::STATUS_DISPATCHED,
            'dispatched_at' => now(),
            'created_by' => $admin->id,
        ]);

        $plainCode = '593104';

        $deliveryNote->forceFill([
            'confirmation_code_hash' => Hash::make($plainCode),
        ])->save();

        $fresh = $deliveryNote->fresh();

        $this->assertNotSame($plainCode, $fresh->confirmation_code_hash);
        $this->assertTrue(
            Hash::check($plainCode, $fresh->confirmation_code_hash)
        );
        $this->assertArrayNotHasKey(
            'confirmation_code_hash',
            $fresh->toArray()
        );
    }

    public function test_confirmation_verification_audit_can_reference_verifying_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $verifier = User::factory()->create(['role' => 'staff']);
        $sale = $this->completedSale($admin);

        $deliveryNote = DeliveryNote::create([
            'reference' => 'DN-CONFIRM-0003',
            'sales_order_id' => $sale->id,
            'status' => DeliveryNote::STATUS_DELIVERED,
            'dispatched_at' => now()->subMinutes(30),
            'delivered_at' => now(),
            'created_by' => $admin->id,
        ]);

        $deliveryNote->forceFill([
            'confirmation_code_verified_at' => now(),
            'confirmation_code_verified_by' => $verifier->id,
        ])->save();

        $deliveryNote->refresh();

        $this->assertSame(
            $verifier->id,
            $deliveryNote->confirmationVerifier->id
        );
        $this->assertNotNull($deliveryNote->confirmation_code_verified_at);
    }

    public function test_existing_delivery_note_defaults_to_unverified_confirmation_state(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sale = $this->completedSale($admin);

        $deliveryNote = DeliveryNote::create([
            'reference' => 'DN-CONFIRM-0004',
            'sales_order_id' => $sale->id,
            'status' => DeliveryNote::STATUS_DRAFT,
            'created_by' => $admin->id,
        ])->fresh();

        $this->assertSame(0, $deliveryNote->confirmation_code_failed_attempts);
        $this->assertNull($deliveryNote->confirmation_code_hash);
        $this->assertNull($deliveryNote->confirmation_code_expires_at);
        $this->assertNull($deliveryNote->confirmation_code_locked_at);
        $this->assertNull($deliveryNote->confirmation_code_verified_at);
        $this->assertNull($deliveryNote->confirmation_code_verified_by);
    }

    private function completedSale(User $admin): SalesOrder
    {
        return SalesOrder::create([
            'sale_type' => SalesOrder::TYPE_BUSINESS,
            'reference' => 'SALE-CONFIRM-'.uniqid(),
            'status' => SalesOrder::STATUS_COMPLETED,
            'sale_at' => now(),
            'total_amount' => 1000,
            'created_by' => $admin->id,
        ]);
    }
}
