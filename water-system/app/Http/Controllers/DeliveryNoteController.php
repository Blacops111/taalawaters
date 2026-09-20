<?php

namespace App\Http\Controllers;

use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\SalesOrder;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Services\DeliveryNoteCompletionService;
use App\Services\DeliveryNoteDispatchService;
use App\Services\DeliveryNoteDraftService;
use Illuminate\Http\Request;

class DeliveryNoteController extends Controller
{
    public function create(SalesOrder $salesOrder)
    {
        if ($salesOrder->status !== SalesOrder::STATUS_COMPLETED) {
            abort(404);
        }

        $salesOrder->load([
            'customer',
            'items.inventoryItem',
        ]);

        $alreadyAllocated = DeliveryNoteItem::query()
            ->join('delivery_notes', 'delivery_note_items.delivery_note_id', '=', 'delivery_notes.id')
            ->where('delivery_notes.sales_order_id', $salesOrder->id)
            ->where('delivery_notes.status', '!=', DeliveryNote::STATUS_CANCELLED)
            ->selectRaw(
                'delivery_note_items.sales_order_item_id, SUM(delivery_note_items.quantity) as allocated_quantity'
            )
            ->groupBy('delivery_note_items.sales_order_item_id')
            ->pluck('allocated_quantity', 'delivery_note_items.sales_order_item_id');

        $remainingQuantities = $salesOrder->items->mapWithKeys(
            fn ($item) => [
                $item->id => max(
                    0,
                    (float) $item->quantity - (float) ($alreadyAllocated[$item->id] ?? 0)
                ),
            ]
        );

        $activeAssignments = $this->activeAssignments();

        return view('delivery-notes.create', compact(
            'salesOrder',
            'remainingQuantities',
            'activeAssignments',
        ));
    }

    public function store(
        Request $request,
        SalesOrder $salesOrder,
        DeliveryNoteDraftService $service,
    ) {
        $validated = $request->validate([
            'vehicle_assignment_id' => [
                'nullable',
                'integer',
                'exists:vehicle_assignments,id',
            ],
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_phone' => [
                'required',
                'string',
                'max:50',
                'regex:/^[0-9+() .-]{7,50}$/',
            ],
            'delivery_address' => ['nullable', 'string', 'max:2000'],
            'scheduled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'quantities' => ['required', 'array'],
            'quantities.*' => [
                'nullable',
                'integer',
                'min:0',
                'max:999999999',
            ],
        ]);

        $assignment = isset($validated['vehicle_assignment_id'])
            ? VehicleAssignment::findOrFail($validated['vehicle_assignment_id'])
            : null;

        $deliveryNote = $service->create(
            $salesOrder,
            $assignment,
            $request->user(),
            $validated['quantities'],
            $validated['recipient_name'],
            $validated['recipient_phone'],
            $validated['delivery_address'] ?? null,
            $validated['scheduled_at'] ?? null,
            $validated['notes'] ?? null,
        );

        return redirect()
            ->route('sales-orders.show', $salesOrder)
            ->with(
                'success',
                'Draft delivery note '.$deliveryNote->reference.' was created successfully.'
            );
    }

    public function dispatchForm(DeliveryNote $deliveryNote)
    {
        if ($deliveryNote->status !== DeliveryNote::STATUS_DRAFT) {
            abort(404);
        }

        $deliveryNote->load([
            'salesOrder.customer',
            'items.inventoryItem',
            'vehicleAssignment.driver',
            'vehicleAssignment.vehicle',
        ]);

        if ($deliveryNote->salesOrder->status !== SalesOrder::STATUS_COMPLETED) {
            abort(404);
        }

        $activeAssignments = $this->activeAssignments();

        return view('delivery-notes.dispatch', compact(
            'deliveryNote',
            'activeAssignments',
        ));
    }

    public function dispatch(
        Request $request,
        DeliveryNote $deliveryNote,
        DeliveryNoteDispatchService $service,
    ) {
        $validated = $request->validate([
            'vehicle_assignment_id' => [
                'required',
                'integer',
                'exists:vehicle_assignments,id',
            ],
        ]);

        $assignment = VehicleAssignment::findOrFail(
            $validated['vehicle_assignment_id']
        );

        $dispatched = $service->dispatch($deliveryNote, $assignment);

        return redirect()
            ->route('sales-orders.show', $dispatched->sales_order_id)
            ->with(
                'success',
                'Delivery note '.$dispatched->reference.' was dispatched successfully.'
            );
    }

    public function deliver(
        DeliveryNote $deliveryNote,
        DeliveryNoteCompletionService $service,
    ) {
        $delivered = $service->markDelivered($deliveryNote);

        return redirect()
            ->route('sales-orders.show', $delivered->sales_order_id)
            ->with(
                'success',
                'Delivery note '.$delivered->reference.' was marked as delivered successfully.'
            );
    }

    private function activeAssignments()
    {
        return VehicleAssignment::query()
            ->whereNull('unassigned_at')
            ->whereHas('driver', fn ($query) => $query->where('is_active', true))
            ->whereHas('vehicle', fn ($query) => $query
                ->where('is_active', true)
                ->where('status', Vehicle::STATUS_ASSIGNED))
            ->with(['driver', 'vehicle'])
            ->latest('assigned_at')
            ->get();
    }
}
