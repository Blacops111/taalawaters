<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleAssignmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_assignment_page_with_available_drivers_and_vehicles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $driver = Driver::create([
            'name' => 'Available Driver',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'registration_number' => 'KME 101A',
            'vehicle_type' => Vehicle::TYPE_MOTORBIKE,
            'status' => Vehicle::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('vehicle-assignments.index'))
            ->assertOk()
            ->assertSee('Driver & Vehicle Assignments', false)
            ->assertSee($driver->name)
            ->assertSee($vehicle->registration_number);
    }

    public function test_admin_can_assign_active_driver_to_available_vehicle(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $driver = Driver::create([
            'name' => 'Assigned Driver',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'registration_number' => 'KME 202B',
            'vehicle_type' => Vehicle::TYPE_MOTORBIKE,
            'status' => Vehicle::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('vehicle-assignments.store'), [
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'notes' => ' Bottle delivery route ',
            ])
            ->assertRedirect(route('vehicle-assignments.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vehicle_assignments', [
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'assigned_by' => $admin->id,
            'unassigned_at' => null,
            'notes' => 'Bottle delivery route',
        ]);

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'status' => Vehicle::STATUS_ASSIGNED,
        ]);
    }

    public function test_inactive_driver_cannot_be_assigned(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $driver = Driver::create([
            'name' => 'Inactive Driver',
            'is_active' => false,
        ]);

        $vehicle = Vehicle::create([
            'registration_number' => 'KME 303C',
            'vehicle_type' => Vehicle::TYPE_MOTORBIKE,
            'status' => Vehicle::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('vehicle-assignments.store'), [
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
            ])
            ->assertSessionHasErrors('driver_id');

        $this->assertDatabaseCount('vehicle_assignments', 0);
        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'status' => Vehicle::STATUS_AVAILABLE,
        ]);
    }

    public function test_unavailable_vehicle_cannot_be_assigned(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $driver = Driver::create([
            'name' => 'Ready Driver',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'registration_number' => 'KTA 404T',
            'vehicle_type' => Vehicle::TYPE_TANKER_TRUCK,
            'status' => Vehicle::STATUS_MAINTENANCE,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('vehicle-assignments.store'), [
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
            ])
            ->assertSessionHasErrors('vehicle_id');

        $this->assertDatabaseCount('vehicle_assignments', 0);
    }

    public function test_driver_and_vehicle_cannot_have_more_than_one_active_assignment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $firstDriver = Driver::create([
            'name' => 'First Driver',
            'is_active' => true,
        ]);

        $secondDriver = Driver::create([
            'name' => 'Second Driver',
            'is_active' => true,
        ]);

        $firstVehicle = Vehicle::create([
            'registration_number' => 'KME 505E',
            'vehicle_type' => Vehicle::TYPE_MOTORBIKE,
            'status' => Vehicle::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $secondVehicle = Vehicle::create([
            'registration_number' => 'KME 606F',
            'vehicle_type' => Vehicle::TYPE_MOTORBIKE,
            'status' => Vehicle::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('vehicle-assignments.store'), [
                'driver_id' => $firstDriver->id,
                'vehicle_id' => $firstVehicle->id,
            ])
            ->assertRedirect(route('vehicle-assignments.index'));

        $this->actingAs($admin)
            ->post(route('vehicle-assignments.store'), [
                'driver_id' => $firstDriver->id,
                'vehicle_id' => $secondVehicle->id,
            ])
            ->assertSessionHasErrors('driver_id');

        $firstVehicle->update(['status' => Vehicle::STATUS_AVAILABLE]);

        $this->actingAs($admin)
            ->post(route('vehicle-assignments.store'), [
                'driver_id' => $secondDriver->id,
                'vehicle_id' => $firstVehicle->id,
            ])
            ->assertSessionHasErrors('vehicle_id');

        $this->assertDatabaseCount('vehicle_assignments', 1);
    }

    public function test_admin_can_end_assignment_and_vehicle_becomes_available_again(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $driver = Driver::create([
            'name' => 'Route Driver',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'registration_number' => 'KTA 707T',
            'vehicle_type' => Vehicle::TYPE_TANKER_TRUCK,
            'status' => Vehicle::STATUS_ASSIGNED,
            'is_active' => true,
        ]);

        $assignment = VehicleAssignment::create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('vehicle-assignments.destroy', $assignment))
            ->assertRedirect(route('vehicle-assignments.index'))
            ->assertSessionHas('success');

        $this->assertNotNull($assignment->fresh()->unassigned_at);

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'status' => Vehicle::STATUS_AVAILABLE,
        ]);
    }

    public function test_assignment_page_shows_active_and_completed_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $driver = Driver::create([
            'name' => 'History Driver',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'registration_number' => 'KME 808H',
            'vehicle_type' => Vehicle::TYPE_MOTORBIKE,
            'status' => Vehicle::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        VehicleAssignment::create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'assigned_by' => $admin->id,
            'assigned_at' => now()->subHours(2),
            'unassigned_at' => now()->subHour(),
        ]);

        $this->actingAs($admin)
            ->get(route('vehicle-assignments.index'))
            ->assertOk()
            ->assertSee('Assignment History')
            ->assertSee('History Driver')
            ->assertSee('KME 808H');
    }

    public function test_non_admin_is_redirected_away_from_assignment_management(): void
    {
        $user = User::factory()->create(['role' => 'staff']);

        $this->actingAs($user)
            ->get(route('vehicle-assignments.index'))
            ->assertRedirect('/dashboard');
    }
}
