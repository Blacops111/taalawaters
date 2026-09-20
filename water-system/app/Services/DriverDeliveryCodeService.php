<?php

namespace App\Services;

use App\Jobs\SendDeliveryConfirmationCode;
use App\Models\DeliveryNote;
use App\Models\Driver;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DriverDeliveryCodeService
{
    public function __construct(
        private readonly DeliveryConfirmationCodeService $confirmationCodes,
    ) {
    }

    public function send(
        DeliveryNote $deliveryNote,
        Driver $driver,
        User $user,
    ): DeliveryNote {
        return DB::transaction(function () use (
            $deliveryNote,
            $driver,
            $user,
        ) {
            $lockedNote = DeliveryNote::query()
                ->lockForUpdate()
                ->findOrFail($deliveryNote->id);

            if ($lockedNote->status !== DeliveryNote::STATUS_DISPATCHED) {
                throw ValidationException::withMessages([
                    'confirmation_code' => 'Only dispatched deliveries can receive a confirmation code.',
                ]);
            }

            if ($lockedNote->dispatched_at === null) {
                throw ValidationException::withMessages([
                    'confirmation_code' => 'This delivery does not have a valid dispatch record.',
                ]);
            }

            if (! $lockedNote->items()->exists()) {
                throw ValidationException::withMessages([
                    'confirmation_code' => 'This delivery has no item history and cannot receive a confirmation code safely.',
                ]);
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
                abort(404);
            }

            $lockedSale = SalesOrder::query()
                ->lockForUpdate()
                ->findOrFail($lockedNote->sales_order_id);

            if ($lockedSale->status !== SalesOrder::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'confirmation_code' => 'The linked sale is no longer eligible for delivery confirmation.',
                ]);
            }

            if (
                blank($lockedNote->recipient_name)
                || (
                    blank($lockedNote->recipient_phone)
                    && blank($lockedNote->recipient_email)
                )
            ) {
                throw ValidationException::withMessages([
                    'confirmation_code' => 'The recipient must have a name and at least one confirmation contact method.',
                ]);
            }

            $cooldownSeconds = max(
                1,
                (int) config(
                    'delivery.confirmation_code_resend_cooldown_seconds',
                    60
                )
            );

            if (
                $lockedNote->confirmation_code_generated_at !== null
                && $lockedNote->confirmation_code_generated_at
                    ->copy()
                    ->addSeconds($cooldownSeconds)
                    ->isFuture()
            ) {
                throw ValidationException::withMessages([
                    'confirmation_code' => 'A confirmation code was requested recently. Wait a moment before sending a new code.',
                ]);
            }

            $code = $this->confirmationCodes->generateFor($lockedNote);

            SendDeliveryConfirmationCode::dispatch(
                $lockedNote->id,
                $code,
            )->afterCommit();

            return $lockedNote->fresh([
                'salesOrder.customer',
                'items.inventoryItem',
                'vehicleAssignment.driver',
                'vehicleAssignment.vehicle',
            ]);
        }, 3);
    }
}
