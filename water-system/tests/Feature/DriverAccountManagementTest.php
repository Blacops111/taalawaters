<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_link_existing_staff_account_to_driver(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driverUser = User::factory()->create([
            'email' => 'driver@example.com',
            'role' => 'staff',
        ]);

        $driver = Driver::create([
            'name' => 'Portal Driver',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('drivers.login-account.link', $driver), [
                'login_email' => 'driver@example.com',
            ])
            ->assertRedirect(route('drivers.edit', $driver))
            ->assertSessionHas('success');

        $this->assertSame(
            $driverUser->id,
            $driver->fresh()->user_id
        );

        $this->assertSame(
            'driver',
            $driverUser->fresh()->role
        );
    }

    public function test_driver_edit_page_shows_linked_login_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driverUser = User::factory()->create([
            'email' => 'linked@example.com',
            'role' => 'driver',
        ]);

        $driver = Driver::create([
            'user_id' => $driverUser->id,
            'name' => 'Linked Driver',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('drivers.edit', $driver))
            ->assertOk()
            ->assertSee('Driver Login Account')
            ->assertSee('linked@example.com')
            ->assertSee('Unlink Login Account');
    }

    public function test_admin_account_cannot_be_converted_into_driver_account(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@example.com',
        ]);

        $driver = Driver::create([
            'name' => 'Portal Driver',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('drivers.edit', $driver))
            ->post(route('drivers.login-account.link', $driver), [
                'login_email' => 'admin@example.com',
            ])
            ->assertRedirect(route('drivers.edit', $driver))
            ->assertSessionHasErrors('login_email');

        $this->assertNull($driver->fresh()->user_id);
        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_same_user_account_cannot_be_linked_to_two_drivers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driverUser = User::factory()->create([
            'email' => 'shared@example.com',
            'role' => 'driver',
        ]);

        Driver::create([
            'user_id' => $driverUser->id,
            'name' => 'First Driver',
            'is_active' => true,
        ]);

        $secondDriver = Driver::create([
            'name' => 'Second Driver',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('drivers.edit', $secondDriver))
            ->post(route('drivers.login-account.link', $secondDriver), [
                'login_email' => 'shared@example.com',
            ])
            ->assertRedirect(route('drivers.edit', $secondDriver))
            ->assertSessionHasErrors('login_email');

        $this->assertNull($secondDriver->fresh()->user_id);
    }

    public function test_admin_can_unlink_driver_account_without_deleting_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driverUser = User::factory()->create([
            'email' => 'unlink@example.com',
            'role' => 'driver',
        ]);

        $driver = Driver::create([
            'user_id' => $driverUser->id,
            'name' => 'Unlink Driver',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('drivers.login-account.unlink', $driver))
            ->assertRedirect(route('drivers.edit', $driver))
            ->assertSessionHas('success');

        $this->assertNull($driver->fresh()->user_id);
        $this->assertSame('staff', $driverUser->fresh()->role);
        $this->assertDatabaseHas('users', [
            'id' => $driverUser->id,
            'email' => 'unlink@example.com',
        ]);
    }

    public function test_non_admin_cannot_manage_driver_login_accounts(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $driverUser = User::factory()->create([
            'email' => 'driver@example.com',
            'role' => 'staff',
        ]);

        $driver = Driver::create([
            'name' => 'Portal Driver',
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->post(route('drivers.login-account.link', $driver), [
                'login_email' => 'driver@example.com',
            ])
            ->assertRedirect('/dashboard');

        $this->assertNull($driver->fresh()->user_id);
        $this->assertSame('staff', $driverUser->fresh()->role);
    }
}
