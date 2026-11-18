<?php

namespace Tests\Feature\Settings;

use App\Models\PasswordResetRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserPasswordManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_permitted_admin_can_manually_set_a_users_password(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create(['password' => 'old-password-1!']);

        $this->actingAs($actor)
            ->from(route('settings.users.show', $target))
            ->post(route('settings.users.password.update', $target), [
                'current_password' => 'SuperAdmin#Pass1',
                'password' => 'N3w-Password#2026',
                'password_confirmation' => 'N3w-Password#2026',
            ])
            ->assertRedirect(route('settings.users.show', $target))
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('N3w-Password#2026', $target->refresh()->password));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.password.set',
            'target_user_id' => $target->getKey(),
            'actor_user_id' => $actor->getKey(),
        ]);
    }

    public function test_the_admin_must_confirm_their_own_current_password(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create(['password' => 'old-password-1!']);

        $this->actingAs($actor)
            ->from(route('settings.users.show', $target))
            ->post(route('settings.users.password.update', $target), [
                'current_password' => 'wrong-password-1!',
                'password' => 'N3w-Password#2026',
                'password_confirmation' => 'N3w-Password#2026',
            ])
            ->assertRedirect(route('settings.users.show', $target))
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('old-password-1!', $target->refresh()->password));
    }

    public function test_the_new_password_must_satisfy_the_approved_policy(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create(['password' => 'old-password-1!']);

        $this->actingAs($actor)
            ->from(route('settings.users.show', $target))
            ->post(route('settings.users.password.update', $target), [
                'current_password' => 'SuperAdmin#Pass1',
                'password' => 'short1!',
                'password_confirmation' => 'short1!',
            ])
            ->assertRedirect(route('settings.users.show', $target))
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('old-password-1!', $target->refresh()->password));
    }

    public function test_a_system_admin_without_the_permission_is_forbidden(): void
    {
        $actor = $this->createSystemAdmin(withPasswordPermission: false);
        $target = User::factory()->create(['password' => 'old-password-1!']);

        $this->actingAs($actor)
            ->post(route('settings.users.password.update', $target), [
                'current_password' => 'x',
                'password' => 'N3w-Password#2026',
                'password_confirmation' => 'N3w-Password#2026',
            ])
            ->assertForbidden();

        $this->actingAs($actor)
            ->post(route('settings.users.password.force-reset', $target), [])
            ->assertForbidden();
    }

    public function test_a_system_admin_cannot_manage_a_super_admins_password(): void
    {
        $actor = $this->createSystemAdmin(withPasswordPermission: true);
        $superAdminRole = Role::query()->firstOrCreate(
            ['code' => 'SUPER_ADMIN'],
            ['name' => 'Super Admin', 'scope' => 'system'],
        );

        $target = User::factory()->create([
            'role_id' => $superAdminRole->id,
            'password' => 'old-password-1!',
        ]);

        $this->actingAs($actor)
            ->post(route('settings.users.password.update', $target), [
                'current_password' => 'SystemAdmin#Pass1',
                'password' => 'N3w-Password#2026',
                'password_confirmation' => 'N3w-Password#2026',
            ])
            ->assertForbidden();

        $this->assertTrue(Hash::check('old-password-1!', $target->refresh()->password));
    }

    public function test_an_admin_cannot_manage_their_own_password(): void
    {
        $actor = $this->createSuperAdmin();

        $this->actingAs($actor)
            ->post(route('settings.users.password.update', $actor), [
                'current_password' => 'SuperAdmin#Pass1',
                'password' => 'N3w-Password#2026',
                'password_confirmation' => 'N3w-Password#2026',
            ])
            ->assertForbidden();
    }

    public function test_a_permitted_admin_can_force_an_email_link_reset(): void
    {
        Mail::fake();

        $actor = $this->createSuperAdmin();
        $target = User::factory()->create(['password' => 'old-password-1!']);

        $this->actingAs($actor)
            ->from(route('settings.users.show', $target))
            ->post(route('settings.users.password.force-reset', $target), [
                'confirmed' => '1',
            ])
            ->assertRedirect(route('settings.users.show', $target))
            ->assertSessionHas('status');

        $this->assertTrue((bool) $target->refresh()->password_reset_required);

        $this->assertDatabaseHas('password_reset_requests', [
            'user_id' => $target->getKey(),
            'state' => 'issued',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.password.reset_forced',
            'target_user_id' => $target->getKey(),
            'actor_user_id' => $actor->getKey(),
        ]);
    }

    public function test_forced_resets_honour_the_issuance_cooldown(): void
    {
        Mail::fake();

        $actor = $this->createSuperAdmin();
        $target = User::factory()->create(['password' => 'old-password-1!']);

        $this->actingAs($actor)
            ->post(route('settings.users.password.force-reset', $target), [
                'confirmed' => '1',
            ])
            ->assertRedirect(route('settings.users.show', $target));

        $this->actingAs($actor)
            ->post(route('settings.users.password.force-reset', $target), [
                'confirmed' => '1',
            ])
            ->assertRedirect(route('settings.users.show', $target))
            ->assertSessionHasErrors('force_reset');

        $this->assertSame(1, PasswordResetRequest::query()->count());
    }

    private function createSuperAdmin(): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'SUPER_ADMIN'],
            ['name' => 'Super Admin', 'scope' => 'system'],
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'password' => 'SuperAdmin#Pass1',
        ]);
    }

    private function createSystemAdmin(bool $withPasswordPermission): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'SYSTEM_ADMIN'],
            ['name' => 'System Admin', 'scope' => 'system'],
        );

        if ($withPasswordPermission) {
            $permission = Permission::query()->firstOrCreate(
                ['code' => 'users.password.manage'],
                ['name' => 'Manage User Passwords'],
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        return User::factory()->create([
            'role_id' => $role->id,
            'password' => 'SystemAdmin#Pass1',
        ]);
    }
}
