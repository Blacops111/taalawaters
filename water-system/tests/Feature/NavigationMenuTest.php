<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_layout_uses_offcanvas_hamburger_navigation(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('delivery-notes.index'))
            ->assertOk()
            ->assertSee('data-bs-toggle="offcanvas"', false)
            ->assertSee('data-bs-target="#taalaMainMenu"', false)
            ->assertSee('Operations')
            ->assertSee('Supply')
            ->assertSee('Logistics')
            ->assertSee('Deliveries')
            ->assertSee('Drivers')
            ->assertSee('Vehicles')
            ->assertSee('Assignments')
            ->assertSee('Profile')
            ->assertSee('Logout');
    }
}
