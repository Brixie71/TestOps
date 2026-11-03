<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $role = Role::query()->create([
            'name' => 'Super Admin',
            'code' => 'SUPER_ADMIN',
            'scope' => 'system',
        ]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function regularUser(): User
    {
        $role = Role::query()->create([
            'name' => 'Standard User',
            'code' => 'USER',
            'scope' => 'system',
        ]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_guests_are_redirected_to_login_from_settings(): void
    {
        $this->get(route('settings.index'))->assertRedirect(route('login'));
        $this->get(route('settings.users'))->assertRedirect(route('login'));
    }

    public function test_non_super_admin_receives_403(): void
    {
        $this->actingAs($this->regularUser())
            ->get(route('settings.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_access_settings(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('settings.index'))
            ->assertOk();
    }

    public function test_guests_are_redirected_from_profile(): void
    {
        $this->get(route('profile'))->assertRedirect(route('login'));
    }

    public function test_profile_is_available_to_any_authenticated_user(): void
    {
        $this->actingAs($this->regularUser())
            ->get(route('profile'))
            ->assertOk();
    }
}
