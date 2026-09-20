<?php

namespace App\Services;

use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryNoteDraftService
{
    public function create(
        SalesOrder $salesOrder,
        ?VehicleAssignment $vehicleAssignment,
        User $user,
        array $quantities,
        string $recipientName,
        string $recipientPhone,
        ?string $deliveryAddress = null,
        ?string $scheduledAt = null,
        ?string $notes = null,
    ): DeliveryNote {
        return DB::transaction(function () use (
            $salesOrder,
            $vehicleAssignment,
            $user,
            $quantities,
            $recipientName,
            $recipientPhone,
            $deliveryAddress,
            $scheduledAt,
            $notes,
        ) {
            $lockedSale = SalesOrder::query()
                ->lockForUpdate()
                ->findOrFail($salesOrder->id);

            if ($lockedSale->status !== SalesOrder::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'sales_order' => 'Delivery notes can only be created for completed sales.',
                ]);
            }

            $saleItems = SalesOrderItem::query()
                ->where('sales_order_id', $lockedSale->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($saleItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'quantities' => 'The completed sale has no items available for delivery.',
                ]);
            }

            $unknownItemIds = collect(array_keys($quantities))
                ->map(fn ($id) => (int) $id)
                ->diff($saleItems->keys());

            if ($unknownItemIds->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'quantities' => 'One or more delivery lines do not belong to this sale.',
                ]);
            }

            $lockedAssignment = null;

            if ($vehicleAssignment) {
                $lockedAssignment = VehicleAssignment::query()
                    ->with(['driver', 'vehicle'])
                    ->lockForUpdate()
                    ->findOrFail($vehicleAssignment->id);

                if ($lockedAssignment->unassigned_at !== null) {
                    throw ValidationException::withMessages([
                        'vehicle_assignment_id' => 'The selected driver/vehicle assignment has already ended.',
                    ]);
                }

                if (! $lockedAssignment->driver?->is_active) {
                    throw ValidationException::withMessages([
                        'vehicle_assignment_id' => 'The selected assignment has an inactive driver.',
                    ]);
                }

                if (
                    ! $lockedAssignment->vehicle?->is_active
                    || $lockedAssignment->vehicle?->status !== Vehicle::STATUS_ASSIGNED
                ) {
                    throw ValidationException::withMessages([
                        'vehicle_assignment_id' => 'The selected assignment does not have an active assigned vehicle.',
                    ]);
                }
            }

            $alreadyAllocated = DeliveryNoteItem::query()
                ->join('delivery_notes', 'delivery_note_items.delivery_note_id', '=', 'delivery_notes.id')
                ->where('delivery_notes.sales_order_id', $lockedSale->id)
                ->where('delivery_notes.status', '!=', DeliveryNote::STATUS_CANCELLED)
                ->selectRaw(
                    'delivery_note_items.sales_order_item_id, SUM(delivery_note_items.quantity) as allocated_quantity'
                )
                ->groupBy('delivery_note_items.sales_order_item_id')
                ->pluck('allocated_quantity', 'delivery_note_items.sales_order_item_id');

            $lines = [];

            foreach ($saleItems as $saleItem) {
                $requested = (int) ($quantities[$saleItem->id] ?? 0);

                if ($requested <= 0) {
                    continue;
                }

                $allocated = (float) ($alreadyAllocated[$saleItem->id] ?? 0);
                $remaining = (float) $saleItem->quantity - $allocated;

                if ($requested > $remaining) {
                    throw ValidationException::withMessages([
                        'quantities.'.$saleItem->id => 'Delivery quantity cannot exceed the remaining sale quantity.',
                    ]);
                }

                $lines[] = [
                    'sales_order_item_id' => $saleItem->id,
                    'inventory_item_id' => $saleItem->inventory_item_id,
                    'quantity' => $requested,
                ];
            }

            if ($lines === []) {
                throw ValidationException::withMessages([
                    'quantities' => 'Enter a delivery quantity for at least one sale item.',
                ]);
            }

            $deliveryNote = DeliveryNote::create([
                'reference' => null,
                'sales_order_id' => $lockedSale->id,
                'vehicle_assignment_id' => $lockedAssignment?->id,
                'status' => DeliveryNote::STATUS_DRAFT,
                'delivery_address' => $this->nullableTrim($deliveryAddress),
                'recipient_name' => trim($recipientName),
                'recipient_phone' => trim($recipientPhone),
                'scheduled_at' => $scheduledAt,
                'created_by' => $user->id,
                'notes' => $this->nullableTrim($notes),
            ]);

            $deliveryNote->update([
                'reference' => 'DN-'.str_pad((string) $deliveryNote->id, 8, '0', STR_PAD_LEFT),
            ]);

            $deliveryNote->items()->createMany($lines);

            return $deliveryNote->fresh([
                'items.inventoryItem',
                'salesOrder.customer',
                'vehicleAssignment.driver',
                'vehicleAssignment.vehicle',
                'creator',
            ]);
        }, 3);
    }

    private function nullableTrim(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
