<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DriverAccessFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'driver'])
            ->get('/_test/driver-access', fn () => 'driver-ok');
    }

    public function test_driver_record_can_be_linked_to_exactly_one_user_account(): void
    {
        $user = User::factory()->create(['role' => 'driver']);

        $driver = Driver::create([
            'user_id' => $user->id,
            'name' => 'Portal Driver',
            'is_active' => true,
        ]);

        $this->assertSame($user->id, $driver->user->id);
        $this->assertSame($driver->id, $user->driverProfile->id);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Driver::create([
            'user_id' => $user->id,
            'name' => 'Duplicate Portal Driver',
            'is_active' => true,
        ]);
    }

    public function test_active_linked_driver_account_can_access_driver_routes(): void
    {
        $user = User::factory()->create(['role' => 'driver']);

        Driver::create([
            'user_id' => $user->id,
            'name' => 'Active Driver',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/_test/driver-access')
            ->assertOk()
            ->assertSee('driver-ok');
    }

    public function test_unlinked_driver_role_user_is_forbidden(): void
    {
        $user = User::factory()->create(['role' => 'driver']);

        $this->actingAs($user)
            ->get('/_test/driver-access')
            ->assertForbidden();
    }

    public function test_inactive_driver_profile_is_forbidden(): void
    {
        $user = User::factory()->create(['role' => 'driver']);

        Driver::create([
            'user_id' => $user->id,
            'name' => 'Inactive Driver',
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->get('/_test/driver-access')
            ->assertForbidden();
    }

    public function test_admin_or_staff_account_cannot_use_driver_only_route(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($admin)
            ->get('/_test/driver-access')
            ->assertForbidden();

        $this->actingAs($staff)
            ->get('/_test/driver-access')
            ->assertForbidden();
    }
}
