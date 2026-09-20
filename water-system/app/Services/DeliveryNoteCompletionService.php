<?php

namespace App\Services;

use App\Models\DeliveryNote;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryNoteCompletionService
{
    public function markDelivered(DeliveryNote $deliveryNote): DeliveryNote
    {
        return DB::transaction(function () use ($deliveryNote) {
            $lockedNote = DeliveryNote::query()
                ->lockForUpdate()
                ->findOrFail($deliveryNote->id);

            if ($lockedNote->status !== DeliveryNote::STATUS_DISPATCHED) {
                throw ValidationException::withMessages([
                    'delivery_note' => 'Only dispatched delivery notes can be marked as delivered.',
                ]);
            }

            if ($lockedNote->dispatched_at === null) {
                throw ValidationException::withMessages([
                    'delivery_note' => 'The delivery note has no dispatch timestamp and cannot be completed safely.',
                ]);
            }

            if ($lockedNote->vehicle_assignment_id === null) {
                throw ValidationException::withMessages([
                    'delivery_note' => 'The dispatched delivery note has no driver/vehicle assignment.',
                ]);
            }

            if (! $lockedNote->items()->exists()) {
                throw ValidationException::withMessages([
                    'delivery_note' => 'The delivery note has no item history and cannot be completed safely.',
                ]);
            }

            $lockedSale = SalesOrder::query()
                ->lockForUpdate()
                ->findOrFail($lockedNote->sales_order_id);

            if ($lockedSale->status !== SalesOrder::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'delivery_note' => 'The linked sale must still be completed before delivery can be confirmed.',
                ]);
            }

            $lockedNote->update([
                'status' => DeliveryNote::STATUS_DELIVERED,
                'delivered_at' => now(),
            ]);

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
