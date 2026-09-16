<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaalaCrystalBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_taala_crystal_branding(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Taala Crystal')
            ->assertSee('Uholo Fresh Springs Co. Ltd')
            ->assertSee('P.O. Box 277-40606, Ugunja')
            ->assertSee('+254 724 293 226')
            ->assertSee('uholofreshsprings@gmail.com')
            ->assertSee('Management Dashboard');
    }
}
