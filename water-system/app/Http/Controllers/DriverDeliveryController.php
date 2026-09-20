<?php

namespace App\Http\Controllers;

use App\Models\DeliveryNote;
use App\Services\DeliveryConfirmationVerificationService;
use App\Services\DriverDeliveryCodeService;
use Illuminate\Http\Request;

class DriverDeliveryController extends Controller
{
    public function index(Request $request)
    {
        $driver = $request->user()->driverProfile;

        $deliveryNotes = DeliveryNote::query()
            ->whereHas(
                'vehicleAssignment',
                fn ($query) => $query->where('driver_id', $driver->id)
            )
            ->whereIn('status', [
                DeliveryNote::STATUS_DISPATCHED,
                DeliveryNote::STATUS_DELIVERED,
            ])
            ->with([
                'salesOrder.customer',
                'items.inventoryItem',
                'vehicleAssignment.vehicle',
            ])
            ->latest('dispatched_at')
            ->paginate(20);

        return view('driver-deliveries.index', compact(
            'driver',
            'deliveryNotes',
        ));
    }

    public function sendCode(
        Request $request,
        DeliveryNote $deliveryNote,
        DriverDeliveryCodeService $service,
    ) {
        $deliveryNote = $this->ownedDispatchedDelivery(
            $request,
            $deliveryNote,
        );

        $sent = $service->send(
            $deliveryNote,
            $request->user()->driverProfile,
            $request->user(),
        );

        return redirect()
            ->route('driver.deliveries.confirm', $sent)
            ->with(
                'success',
                'A fresh confirmation code is being sent to the recipient.'
            );
    }

    public function confirmForm(
        Request $request,
        DeliveryNote $deliveryNote,
    ) {
        $deliveryNote = $this->ownedDispatchedDelivery(
            $request,
            $deliveryNote,
        );

        $deliveryNote->load([
            'salesOrder.customer',
            'items.inventoryItem',
            'vehicleAssignment.vehicle',
        ]);

        return view('driver-deliveries.confirm', compact('deliveryNote'));
    }

    public function confirm(
        Request $request,
        DeliveryNote $deliveryNote,
        DeliveryConfirmationVerificationService $service,
    ) {
        $validated = $request->validate([
            'confirmation_code' => [
                'required',
                'digits:6',
            ],
        ]);

        $driver = $request->user()->driverProfile;

        $confirmed = $service->verifyAndDeliver(
            $deliveryNote,
            $driver,
            $request->user(),
            $validated['confirmation_code'],
        );

        return redirect()
            ->route('driver.deliveries.index')
            ->with(
                'success',
                'Delivery '.$confirmed->reference.' was confirmed successfully.'
            );
    }

    private function ownedDispatchedDelivery(
        Request $request,
        DeliveryNote $deliveryNote,
    ): DeliveryNote {
        $driverId = $request->user()->driverProfile->id;

        return DeliveryNote::query()
            ->whereKey($deliveryNote->id)
            ->where('status', DeliveryNote::STATUS_DISPATCHED)
            ->whereHas(
                'vehicleAssignment',
                fn ($query) => $query->where('driver_id', $driverId)
            )
            ->firstOrFail();
    }
}
