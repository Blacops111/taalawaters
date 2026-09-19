<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaalaCrystalPublicWebsiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_homepage_is_a_taala_crystal_website_with_login_and_register_actions(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Welcome to')
            ->assertSee('Taala Crystal')
            ->assertSee('Uholo Fresh Springs Co. Ltd')
            ->assertSee('Login')
            ->assertSee('Create Account')
            ->assertSee(route('login'), false)
            ->assertSee(route('register'), false);
    }

    public function test_authenticated_homepage_replaces_login_actions_with_dashboard_and_logout(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/')
            ->assertOk()
            ->assertSee('Open Management Dashboard')
            ->assertSee('Logout')
            ->assertSee(route('dashboard'), false)
            ->assertSee(route('logout'), false);
    }

    public function test_authenticated_application_navigation_has_logout_button(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($admin->name)
            ->assertSee('Logout')
            ->assertSee(route('logout'), false);
    }
}
