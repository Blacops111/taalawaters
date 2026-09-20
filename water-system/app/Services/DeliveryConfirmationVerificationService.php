<?php

namespace App\Services;

use App\Models\DeliveryNote;
use App\Models\Driver;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DeliveryConfirmationVerificationService
{
    public function verifyAndDeliver(
        DeliveryNote $deliveryNote,
        Driver $driver,
        User $user,
        string $code,
    ): DeliveryNote {
        $result = DB::transaction(function () use (
            $deliveryNote,
            $driver,
            $user,
            $code,
        ) {
            $lockedNote = DeliveryNote::query()
                ->lockForUpdate()
                ->findOrFail($deliveryNote->id);

            if ($lockedNote->status !== DeliveryNote::STATUS_DISPATCHED) {
                return ['error' => 'Only dispatched deliveries can be confirmed.'];
            }

            if ($lockedNote->dispatched_at === null) {
                return ['error' => 'This delivery does not have a valid dispatch record.'];
            }

            if (! $lockedNote->items()->exists()) {
                return ['error' => 'This delivery has no item history and cannot be confirmed safely.'];
            }

            $assignment = $lockedNote->vehicleAssignment()
                ->lockForUpdate()
                ->first();

            if (
                ! $driver->is_active
                || $driver->user_id !== $user->id
                || ! $assignment
                || $assignment->driver_id !== $driver->id
            ) {
                return ['forbidden' => true];
            }

            $lockedSale = SalesOrder::query()
                ->lockForUpdate()
                ->findOrFail($lockedNote->sales_order_id);

            if ($lockedSale->status !== SalesOrder::STATUS_COMPLETED) {
                return ['error' => 'The linked sale is no longer eligible for delivery confirmation.'];
            }

            if (
                blank($lockedNote->confirmation_code_hash)
                || $lockedNote->confirmation_code_expires_at === null
            ) {
                return ['error' => 'No active delivery confirmation code is available.'];
            }

            if ($lockedNote->confirmation_code_locked_at !== null) {
                return ['error' => 'This confirmation code is locked after too many failed attempts.'];
            }

            if ($lockedNote->confirmation_code_expires_at->isPast()) {
                return ['error' => 'This delivery confirmation code has expired.'];
            }

            if (
                $lockedNote->confirmation_code_sms_sent_at === null
                && $lockedNote->confirmation_code_email_sent_at === null
            ) {
                return ['error' => 'The confirmation code has not been sent successfully yet.'];
            }

            $maxAttempts = max(
                1,
                (int) config('delivery.confirmation_code_max_attempts', 5)
            );

            if ($lockedNote->confirmation_code_failed_attempts >= $maxAttempts) {
                $lockedNote->forceFill([
                    'confirmation_code_locked_at' => now(),
                ])->save();

                return ['error' => 'This confirmation code is locked after too many failed attempts.'];
            }

            if (! Hash::check($code, $lockedNote->confirmation_code_hash)) {
                $attempts = min(
                    255,
                    $lockedNote->confirmation_code_failed_attempts + 1
                );

                $updates = [
                    'confirmation_code_failed_attempts' => $attempts,
                ];

                if ($attempts >= $maxAttempts) {
                    $updates['confirmation_code_locked_at'] = now();
                }

                $lockedNote->forceFill($updates)->save();

                return [
                    'error' => $attempts >= $maxAttempts
                        ? 'Too many incorrect codes. This confirmation code is now locked.'
                        : 'The delivery confirmation code is incorrect.',
                ];
            }

            $verifiedAt = now();

            $lockedNote->forceFill([
                'status' => DeliveryNote::STATUS_DELIVERED,
                'delivered_at' => $verifiedAt,
                'confirmation_code_verified_at' => $verifiedAt,
                'confirmation_code_verified_by' => $user->id,
                'confirmation_code_hash' => null,
            ])->save();

            return [
                'delivery_note' => $lockedNote->fresh([
                    'items.inventoryItem',
                    'salesOrder.customer',
                    'vehicleAssignment.driver',
                    'vehicleAssignment.vehicle',
                    'creator',
                    'confirmationVerifier',
                ]),
            ];
        }, 3);

        if (! empty($result['forbidden'])) {
            abort(404);
        }

        if (isset($result['error'])) {
            throw ValidationException::withMessages([
                'confirmation_code' => $result['error'],
            ]);
        }

        return $result['delivery_note'];
    }
}
