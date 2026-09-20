<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_driver_list_and_create_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Driver::create([
            'name' => 'John Driver',
            'phone' => '+254700000001',
            'license_number' => 'DL-001',
            'license_expiry' => '2027-12-31',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('drivers.index'))
            ->assertOk()
            ->assertSee('Drivers')
            ->assertSee('John Driver')
            ->assertSee('DL-001');

        $this->actingAs($admin)
            ->get(route('drivers.create'))
            ->assertOk()
            ->assertSee('Add Driver')
            ->assertSee('Driving Licence Number');
    }

    public function test_admin_can_create_driver(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('drivers.store'), [
                'name' => '  Mary Driver  ',
                'phone' => ' +254711111111 ',
                'license_number' => ' DL-MARY-001 ',
                'license_expiry' => '2028-05-31',
                'is_active' => '1',
                'notes' => ' Delivery driver ',
            ])
            ->assertRedirect(route('drivers.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('drivers', [
            'name' => 'Mary Driver',
            'phone' => '+254711111111',
            'license_number' => 'DL-MARY-001',
            'license_expiry' => '2028-05-31',
            'is_active' => true,
            'notes' => 'Delivery driver',
        ]);
    }

    public function test_admin_can_update_and_deactivate_driver_without_deleting_record(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $driver = Driver::create([
            'name' => 'Original Driver',
            'license_number' => 'DL-EDIT-001',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('drivers.update', $driver), [
                'name' => 'Updated Driver',
                'license_number' => 'DL-EDIT-001',
                'is_active' => '0',
                'notes' => 'Temporarily unavailable.',
            ])
            ->assertRedirect(route('drivers.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'name' => 'Updated Driver',
            'license_number' => 'DL-EDIT-001',
            'is_active' => false,
            'notes' => 'Temporarily unavailable.',
        ]);

        $this->assertDatabaseCount('drivers', 1);
    }

    public function test_duplicate_driver_license_number_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Driver::create([
            'name' => 'Driver One',
            'license_number' => 'DL-DUP-001',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('drivers.create'))
            ->post(route('drivers.store'), [
                'name' => 'Driver Two',
                'license_number' => 'DL-DUP-001',
                'is_active' => '1',
            ])
            ->assertRedirect(route('drivers.create'))
            ->assertSessionHasErrors('license_number');

        $this->assertDatabaseCount('drivers', 1);
    }

    public function test_non_admin_is_redirected_away_from_driver_management(): void
    {
        $user = User::factory()->create(['role' => 'staff']);

        $this->actingAs($user)
            ->get(route('drivers.index'))
            ->assertRedirect('/dashboard');
    }

    public function test_application_navigation_links_to_drivers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('drivers.index'), false)
            ->assertSee('Drivers');
    }
}
