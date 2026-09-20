<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Services\VehicleAssignmentService;
use Illuminate\Http\Request;

class VehicleAssignmentController extends Controller
{
    public function index()
    {
        $drivers = Driver::query()
            ->where('is_active', true)
            ->whereDoesntHave('assignments', fn ($query) => $query->whereNull('unassigned_at'))
            ->orderBy('name')
            ->get();

        $vehicles = Vehicle::query()
            ->where('is_active', true)
            ->where('status', Vehicle::STATUS_AVAILABLE)
            ->whereDoesntHave('assignments', fn ($query) => $query->whereNull('unassigned_at'))
            ->orderBy('registration_number')
            ->get();

        $activeAssignments = VehicleAssignment::query()
            ->whereNull('unassigned_at')
            ->with(['driver', 'vehicle', 'assigner'])
            ->latest('assigned_at')
            ->get();

        $assignmentHistory = VehicleAssignment::query()
            ->whereNotNull('unassigned_at')
            ->with(['driver', 'vehicle', 'assigner'])
            ->latest('unassigned_at')
            ->paginate(25);

        return view('vehicle-assignments.index', compact(
            'drivers',
            'vehicles',
            'activeAssignments',
            'assignmentHistory',
        ));
    }

    public function store(Request $request, VehicleAssignmentService $service)
    {
        $validated = $request->validate([
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $assignment = $service->assign(
            Driver::findOrFail($validated['driver_id']),
            Vehicle::findOrFail($validated['vehicle_id']),
            $request->user(),
            $validated['notes'] ?? null,
        );

        return redirect()
            ->route('vehicle-assignments.index')
            ->with(
                'success',
                $assignment->driver->name.' was assigned to '.$assignment->vehicle->registration_number.'.'
            );
    }

    public function destroy(
        VehicleAssignment $vehicleAssignment,
        VehicleAssignmentService $service,
    ) {
        $assignment = $service->unassign($vehicleAssignment);

        return redirect()
            ->route('vehicle-assignments.index')
            ->with(
                'success',
                $assignment->driver->name.' was unassigned from '.$assignment->vehicle->registration_number.'.'
            );
    }
}
