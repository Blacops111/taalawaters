<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_register_pages_show_google_authentication_action(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Continue with Google')
            ->assertSee(route('google.redirect'), false);

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Continue with Google')
            ->assertSee(route('google.redirect'), false);
    }

    public function test_google_callback_creates_verified_staff_user_and_logs_them_in(): void
    {
        $googleUser = new SocialiteUser();
        $googleUser->id = 'google-user-123';
        $googleUser->name = 'Google User';
        $googleUser->email = 'google.user@example.com';
        $googleUser->user = ['email_verified' => true];

        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn($googleUser);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'email' => 'google.user@example.com',
            'google_id' => 'google-user-123',
            'role' => 'staff',
        ]);

        $this->assertNotNull(
            User::where('email', 'google.user@example.com')->first()->email_verified_at
        );
    }

    public function test_google_callback_links_existing_account_by_verified_email_without_changing_role(): void
    {
        $existing = User::factory()->create([
            'email' => 'existing@example.com',
            'role' => 'admin',
            'google_id' => null,
            'email_verified_at' => null,
        ]);

        $googleUser = new SocialiteUser();
        $googleUser->id = 'google-existing-456';
        $googleUser->name = 'Existing User';
        $googleUser->email = 'existing@example.com';
        $googleUser->user = ['email_verified' => true];

        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn($googleUser);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $this->get(route('google.callback'))
            ->assertRedirect(route('dashboard', absolute: false));

        $existing->refresh();

        $this->assertAuthenticatedAs($existing);
        $this->assertSame('google-existing-456', $existing->google_id);
        $this->assertSame('admin', $existing->role);
        $this->assertNotNull($existing->email_verified_at);
    }
}
