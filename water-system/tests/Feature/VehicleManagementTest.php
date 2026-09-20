<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_vehicle_list_and_create_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Vehicle::create([
            'registration_number' => 'KME 100A',
            'name' => 'Bottle Bike',
            'vehicle_type' => Vehicle::TYPE_MOTORBIKE,
            'capacity_quantity' => 6,
            'capacity_unit' => 'crates',
            'status' => Vehicle::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('vehicles.index'))
            ->assertOk()
            ->assertSee('Vehicles')
            ->assertSee('KME 100A')
            ->assertSee('Motorbike');

        $this->actingAs($admin)
            ->get(route('vehicles.create'))
            ->assertOk()
            ->assertSee('Add Vehicle')
            ->assertSee('Tanker Truck');
    }

    public function test_admin_can_create_motorbike(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('vehicles.store'), [
                'registration_number' => ' kme 200b ',
                'name' => ' Delivery Bike 2 ',
                'vehicle_type' => Vehicle::TYPE_MOTORBIKE,
                'capacity_quantity' => 8,
                'capacity_unit' => ' crates ',
                'status' => Vehicle::STATUS_AVAILABLE,
                'is_active' => '1',
            ])
            ->assertRedirect(route('vehicles.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vehicles', [
            'registration_number' => 'KME 200B',
            'name' => 'Delivery Bike 2',
            'vehicle_type' => 'motorbike',
            'capacity_unit' => 'crates',
            'status' => 'available',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_create_tanker_truck(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('vehicles.store'), [
                'registration_number' => 'KTA 500T',
                'name' => 'Bulk Tanker',
                'vehicle_type' => Vehicle::TYPE_TANKER_TRUCK,
                'capacity_quantity' => 10000,
                'capacity_unit' => 'litre',
                'status' => Vehicle::STATUS_AVAILABLE,
                'is_active' => '1',
            ])
            ->assertRedirect(route('vehicles.index'));

        $this->assertDatabaseHas('vehicles', [
            'registration_number' => 'KTA 500T',
            'vehicle_type' => 'tanker_truck',
            'capacity_quantity' => 10000,
            'capacity_unit' => 'litre',
        ]);
    }

    public function test_admin_can_mark_vehicle_for_maintenance_and_deactivate_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $vehicle = Vehicle::create([
            'registration_number' => 'KME 300C',
            'vehicle_type' => Vehicle::TYPE_MOTORBIKE,
            'status' => Vehicle::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('vehicles.update', $vehicle), [
                'registration_number' => 'KME 300C',
                'vehicle_type' => Vehicle::TYPE_MOTORBIKE,
                'status' => Vehicle::STATUS_MAINTENANCE,
                'is_active' => '1',
            ])
            ->assertRedirect(route('vehicles.index'));

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'status' => 'maintenance',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('vehicles.update', $vehicle), [
                'registration_number' => 'KME 300C',
                'vehicle_type' => Vehicle::TYPE_MOTORBIKE,
                'status' => Vehicle::STATUS_AVAILABLE,
                'is_active' => '0',
            ])
            ->assertRedirect(route('vehicles.index'));

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'status' => 'inactive',
            'is_active' => false,
        ]);
    }

    public function test_duplicate_registration_number_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Vehicle::create([
            'registration_number' => 'KTA 900Z',
            'vehicle_type' => Vehicle::TYPE_TANKER_TRUCK,
            'status' => Vehicle::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('vehicles.create'))
            ->post(route('vehicles.store'), [
                'registration_number' => 'KTA 900Z',
                'vehicle_type' => Vehicle::TYPE_TANKER_TRUCK,
                'status' => Vehicle::STATUS_AVAILABLE,
                'is_active' => '1',
            ])
            ->assertRedirect(route('vehicles.create'))
            ->assertSessionHasErrors('registration_number');

        $this->assertDatabaseCount('vehicles', 1);
    }

    public function test_only_motorbike_and_tanker_truck_are_allowed_in_v2_vehicle_ui(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('vehicles.create'))
            ->post(route('vehicles.store'), [
                'registration_number' => 'KDV 111D',
                'vehicle_type' => Vehicle::TYPE_DELIVERY_VAN,
                'status' => Vehicle::STATUS_AVAILABLE,
                'is_active' => '1',
            ])
            ->assertRedirect(route('vehicles.create'))
            ->assertSessionHasErrors('vehicle_type');

        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_non_admin_is_redirected_away_from_vehicle_management(): void
    {
        $user = User::factory()->create(['role' => 'staff']);

        $this->actingAs($user)
            ->get(route('vehicles.index'))
            ->assertRedirect('/dashboard');
    }

    public function test_application_navigation_links_to_vehicles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('vehicles.index'), false)
            ->assertSee('Vehicles');
    }
}
