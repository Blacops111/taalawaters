<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VehicleAssignmentService
{
    public function assign(
        Driver $driver,
        Vehicle $vehicle,
        User $user,
        ?string $notes = null,
    ): VehicleAssignment {
        return DB::transaction(function () use ($driver, $vehicle, $user, $notes) {
            $lockedDriver = Driver::query()
                ->lockForUpdate()
                ->findOrFail($driver->id);

            $lockedVehicle = Vehicle::query()
                ->lockForUpdate()
                ->findOrFail($vehicle->id);

            if (! $lockedDriver->is_active) {
                throw ValidationException::withMessages([
                    'driver_id' => 'Only active drivers can be assigned to a vehicle.',
                ]);
            }

            if (! $lockedVehicle->is_active) {
                throw ValidationException::withMessages([
                    'vehicle_id' => 'Only active vehicles can be assigned.',
                ]);
            }

            if ($lockedVehicle->status !== Vehicle::STATUS_AVAILABLE) {
                throw ValidationException::withMessages([
                    'vehicle_id' => 'The selected vehicle is not currently available.',
                ]);
            }

            $driverAlreadyAssigned = VehicleAssignment::query()
                ->where('driver_id', $lockedDriver->id)
                ->whereNull('unassigned_at')
                ->exists();

            if ($driverAlreadyAssigned) {
                throw ValidationException::withMessages([
                    'driver_id' => 'The selected driver already has an active vehicle assignment.',
                ]);
            }

            $vehicleAlreadyAssigned = VehicleAssignment::query()
                ->where('vehicle_id', $lockedVehicle->id)
                ->whereNull('unassigned_at')
                ->exists();

            if ($vehicleAlreadyAssigned) {
                throw ValidationException::withMessages([
                    'vehicle_id' => 'The selected vehicle already has an active driver assignment.',
                ]);
            }

            $assignment = VehicleAssignment::create([
                'driver_id' => $lockedDriver->id,
                'vehicle_id' => $lockedVehicle->id,
                'assigned_by' => $user->id,
                'assigned_at' => now(),
                'notes' => $this->nullableTrim($notes),
            ]);

            $lockedVehicle->update([
                'status' => Vehicle::STATUS_ASSIGNED,
            ]);

            return $assignment->load(['driver', 'vehicle', 'assigner']);
        }, 3);
    }

    public function unassign(VehicleAssignment $assignment): VehicleAssignment
    {
        return DB::transaction(function () use ($assignment) {
            $lockedAssignment = VehicleAssignment::query()
                ->lockForUpdate()
                ->findOrFail($assignment->id);

            if ($lockedAssignment->unassigned_at !== null) {
                throw ValidationException::withMessages([
                    'assignment' => 'This vehicle assignment has already ended.',
                ]);
            }

            $lockedVehicle = Vehicle::query()
                ->lockForUpdate()
                ->findOrFail($lockedAssignment->vehicle_id);

            $lockedAssignment->update([
                'unassigned_at' => now(),
            ]);

            $lockedVehicle->update([
                'status' => $lockedVehicle->is_active
                    ? Vehicle::STATUS_AVAILABLE
                    : Vehicle::STATUS_INACTIVE,
            ]);

            return $lockedAssignment->fresh(['driver', 'vehicle', 'assigner']);
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
