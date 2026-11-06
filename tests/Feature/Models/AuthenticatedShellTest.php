<?php

namespace Tests\Feature\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticatedShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_the_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_load_the_dashboard_placeholder(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Dashboard');
    }

    public function test_logout_destroys_the_session_and_redirects_to_login(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');

        // A logged-out user cannot revisit /dashboard.
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_active_users_can_log_in_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'active@example.com',
            'password' => 'password',
            'account_status' => 'active',
        ]);

        $this->post('/login', [
            'email' => 'active@example.com',
            'password' => 'password',
        ])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_invited_users_cannot_log_in_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'invited@example.com',
            'password' => 'password',
            'account_status' => 'invited',
        ]);

        $this->from('/login')
            ->post('/login', [
                'email' => 'invited@example.com',
                'password' => 'password',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
