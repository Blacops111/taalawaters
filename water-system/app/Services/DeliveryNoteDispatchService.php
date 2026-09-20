<?php

namespace App\Services;

use App\Models\DeliveryNote;
use App\Models\Driver;
use App\Models\SalesOrder;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryNoteDispatchService
{
    public function __construct(
        private readonly DeliveryConfirmationCodeService $confirmationCodes,
    ) {
    }

    public function dispatch(
        DeliveryNote $deliveryNote,
        VehicleAssignment $vehicleAssignment,
    ): DeliveryNote {
        return DB::transaction(function () use ($deliveryNote, $vehicleAssignment) {
            $lockedNote = DeliveryNote::query()
                ->lockForUpdate()
                ->findOrFail($deliveryNote->id);

            if ($lockedNote->status !== DeliveryNote::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'delivery_note' => 'Only draft delivery notes can be dispatched.',
                ]);
            }

            $lockedSale = SalesOrder::query()
                ->lockForUpdate()
                ->findOrFail($lockedNote->sales_order_id);

            if ($lockedSale->status !== SalesOrder::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'delivery_note' => 'The linked sale must still be completed before dispatch.',
                ]);
            }

            if (! $lockedNote->items()->exists()) {
                throw ValidationException::withMessages([
                    'delivery_note' => 'A delivery note must contain at least one item before dispatch.',
                ]);
            }

            if (
                blank($lockedNote->recipient_name)
                || blank($lockedNote->recipient_phone)
            ) {
                throw ValidationException::withMessages([
                    'recipient_phone' => 'A delivery recipient name and phone number are required before dispatch.',
                ]);
            }

            $lockedAssignment = VehicleAssignment::query()
                ->lockForUpdate()
                ->findOrFail($vehicleAssignment->id);

            if ($lockedAssignment->unassigned_at !== null) {
                throw ValidationException::withMessages([
                    'vehicle_assignment_id' => 'The selected driver/vehicle assignment has already ended.',
                ]);
            }

            $driver = Driver::query()
                ->lockForUpdate()
                ->findOrFail($lockedAssignment->driver_id);

            if (! $driver->is_active) {
                throw ValidationException::withMessages([
                    'vehicle_assignment_id' => 'The selected assignment has an inactive driver.',
                ]);
            }

            $vehicle = Vehicle::query()
                ->lockForUpdate()
                ->findOrFail($lockedAssignment->vehicle_id);

            if (! $vehicle->is_active || $vehicle->status !== Vehicle::STATUS_ASSIGNED) {
                throw ValidationException::withMessages([
                    'vehicle_assignment_id' => 'The selected assignment does not have an active assigned vehicle.',
                ]);
            }

            $lockedNote->update([
                'vehicle_assignment_id' => $lockedAssignment->id,
                'status' => DeliveryNote::STATUS_DISPATCHED,
                'dispatched_at' => now(),
            ]);

            $this->confirmationCodes->generateFor($lockedNote);

            return $lockedNote->fresh([
                'items.inventoryItem',
                'salesOrder.customer',
                'vehicleAssignment.driver',
                'vehicleAssignment.vehicle',
                'creator',
            ]);
        }, 3);
    }
}
