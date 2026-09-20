<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleAssignmentFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_can_be_linked_to_vehicle_with_assignment_audit_details(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $driver = Driver::create([
            'name' => 'Assignment Driver',
            'license_number' => 'DL-ASSIGN-001',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'registration_number' => 'KME 700A',
            'vehicle_type' => Vehicle::TYPE_MOTORBIKE,
            'status' => Vehicle::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $assignment = VehicleAssignment::create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'assigned_by' => $admin->id,
            'assigned_at' => '2026-09-20 08:00:00',
            'notes' => 'Morning delivery assignment.',
        ]);

        $this->assertDatabaseHas('vehicle_assignments', [
            'id' => $assignment->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'assigned_by' => $admin->id,
            'unassigned_at' => null,
            'notes' => 'Morning delivery assignment.',
        ]);

        $this->assertSame($driver->id, $assignment->driver->id);
        $this->assertSame($vehicle->id, $assignment->vehicle->id);
        $this->assertSame($admin->id, $assignment->assigner->id);
        $this->assertSame(
            '2026-09-20 08:00:00',
            $assignment->assigned_at->format('Y-m-d H:i:s')
        );
    }

    public function test_completed_assignment_is_preserved_when_vehicle_is_assigned_again_later(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $firstDriver = Driver::create([
            'name' => 'First Driver',
            'license_number' => 'DL-HISTORY-001',
            'is_active' => true,
        ]);

        $secondDriver = Driver::create([
            'name' => 'Second Driver',
            'license_number' => 'DL-HISTORY-002',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'registration_number' => 'KTA 800T',
            'vehicle_type' => Vehicle::TYPE_TANKER_TRUCK,
            'status' => Vehicle::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        VehicleAssignment::create([
            'driver_id' => $firstDriver->id,
            'vehicle_id' => $vehicle->id,
            'assigned_by' => $admin->id,
            'assigned_at' => '2026-09-20 08:00:00',
            'unassigned_at' => '2026-09-20 12:00:00',
        ]);

        VehicleAssignment::create([
            'driver_id' => $secondDriver->id,
            'vehicle_id' => $vehicle->id,
            'assigned_by' => $admin->id,
            'assigned_at' => '2026-09-20 13:00:00',
        ]);

        $this->assertDatabaseCount('vehicle_assignments', 2);

        $this->assertDatabaseHas('vehicle_assignments', [
            'driver_id' => $firstDriver->id,
            'vehicle_id' => $vehicle->id,
            'unassigned_at' => '2026-09-20 12:00:00',
        ]);

        $this->assertDatabaseHas('vehicle_assignments', [
            'driver_id' => $secondDriver->id,
            'vehicle_id' => $vehicle->id,
            'unassigned_at' => null,
        ]);
    }

    public function test_driver_and_vehicle_expose_assignment_history_relationships(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $driver = Driver::create([
            'name' => 'Relationship Driver',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'registration_number' => 'KME 900R',
            'vehicle_type' => Vehicle::TYPE_MOTORBIKE,
            'status' => Vehicle::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        VehicleAssignment::create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);

        $this->assertCount(1, $driver->assignments);
        $this->assertCount(1, $vehicle->assignments);
        $this->assertSame($vehicle->id, $driver->assignments->first()->vehicle_id);
        $this->assertSame($driver->id, $vehicle->assignments->first()->driver_id);
    }
}
