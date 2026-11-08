<?php

namespace Tests\Feature\Settings;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserManagementPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserLifecycleHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_deactivate_an_eligible_account(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create();

        $response = $this->actingAs($actor)
            ->from(route('settings.users'))
            ->post(route('settings.users.deactivate', $target), [
                'reason' => 'Confirmed policy violation.',
            ]);

        $response->assertRedirect(route('settings.users'));
        $this->assertSoftDeleted('users', ['id' => $target->getKey()]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.deactivated',
            'target_user_id' => $target->getKey(),
            'reason' => 'Confirmed policy violation.',
        ]);
    }

    public function test_deactivation_requires_a_reason(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->from(route('settings.users'))
            ->post(route('settings.users.deactivate', $target), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertNotSoftDeleted('users', ['id' => $target->getKey()]);
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_system_admin_with_the_permission_can_deactivate(): void
    {
        $actor = $this->createSystemAdmin(withPermission: true);
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->post(route('settings.users.deactivate', $target), [
                'reason' => 'Confirmed policy violation.',
            ])
            ->assertRedirect(route('settings.users'));

        $this->assertSoftDeleted('users', ['id' => $target->getKey()]);
    }

    public function test_system_admin_without_the_permission_is_forbidden(): void
    {
        $actor = $this->createSystemAdmin(withPermission: false);
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->post(route('settings.users.deactivate', $target), [
                'reason' => 'Confirmed policy violation.',
            ])
            ->assertForbidden();

        $this->assertNotSoftDeleted('users', ['id' => $target->getKey()]);
    }

    public function test_project_scoped_roles_cannot_gain_lifecycle_access(): void
    {
        $role = Role::query()->create([
            'name' => 'Project System Admin',
            'code' => 'PROJECT_SYSTEM_ADMIN',
            'scope' => 'project',
        ]);

        $permission = Permission::query()->firstOrCreate(
            ['code' => 'users.deactivate'],
            ['name' => 'Deactivate User Accounts'],
        );
        $role->permissions()->attach($permission);

        $actor = User::factory()->create(['role_id' => $role->id]);
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->post(route('settings.users.deactivate', $target), [
                'reason' => 'Confirmed policy violation.',
            ])
            ->assertForbidden();

        $this->assertNotSoftDeleted('users', ['id' => $target->getKey()]);
    }

    public function test_system_admin_cannot_deactivate_a_super_admin(): void
    {
        $actor = $this->createSystemAdmin(withPermission: true);
        $target = $this->createSuperAdmin();

        $this->actingAs($actor)
            ->post(route('settings.users.deactivate', $target), [
                'reason' => 'Confirmed policy violation.',
            ])
            ->assertForbidden();

        $this->assertNotSoftDeleted('users', ['id' => $target->getKey()]);
    }

    public function test_system_admin_cannot_deactivate_another_system_admin(): void
    {
        $actor = $this->createSystemAdmin(withPermission: true);
        $target = $this->createSystemAdmin(withPermission: false);

        $this->actingAs($actor)
            ->post(route('settings.users.deactivate', $target), [
                'reason' => 'Confirmed policy violation.',
            ])
            ->assertForbidden();

        $this->assertNotSoftDeleted('users', ['id' => $target->getKey()]);
    }

    public function test_super_admin_cannot_deactivate_themselves(): void
    {
        $actor = $this->createSuperAdmin();

        $this->actingAs($actor)
            ->post(route('settings.users.deactivate', $actor), [
                'reason' => 'Confirmed policy violation.',
            ])
            ->assertForbidden();

        $this->assertNotSoftDeleted('users', ['id' => $actor->getKey()]);
    }

    public function test_super_admin_can_reactivate_a_deactivated_account(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create();
        $target->delete();

        $this->actingAs($actor)
            ->post(route('settings.users.reactivate', $target->getKey()))
            ->assertRedirect(route('settings.users'));

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'account_status' => 'active',
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.reactivated',
            'target_user_id' => $target->getKey(),
        ]);
    }

    public function test_reactivation_requires_the_lifecycle_permission(): void
    {
        $actor = $this->createSystemAdmin(withPermission: false);
        $target = User::factory()->create();
        $target->delete();

        $this->actingAs($actor)
            ->post(route('settings.users.reactivate', $target->getKey()))
            ->assertForbidden();

        $this->assertSoftDeleted('users', ['id' => $target->getKey()]);
    }

    public function test_the_directory_shows_deactivated_accounts_and_the_reactivate_action(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create(['email' => 'deactivated@example.com']);

        $this->actingAs($actor)
            ->post(route('settings.users.deactivate', $target), ['reason' => 'Confirmed policy violation.']);

        $this->actingAs($actor)
            ->get(route('settings.users', ['status' => 'deactivated']))
            ->assertOk()
            ->assertSee('deactivated@example.com')
            ->assertSee('Deactivated')
            ->assertSee('settings/users/'.$target->getKey().'/reactivate');
    }

    public function test_the_directory_shows_the_deactivate_action_for_other_users_only(): void
    {
        $actor = $this->createSuperAdmin();
        $other = User::factory()->create();

        $this->actingAs($actor)
            ->get(route('settings.users'))
            ->assertOk()
            ->assertSee('settings/users/'.$other->getKey().'/deactivate')
            ->assertDontSee('settings/users/'.$actor->getKey().'/deactivate');
    }

    public function test_system_admin_with_the_permission_can_access_the_directory(): void
    {
        $actor = $this->createSystemAdmin(withPermission: true);
        $target = User::factory()->create(['email' => 'member@example.com']);
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($actor)
            ->get(route('settings.users'));

        $response->assertOk()
            ->assertSee('member@example.com');

        // Eligible ordinary accounts expose lifecycle controls...
        $this->assertSeeLifecycleControls($response, $target->getKey(), true);

        // ...but protected system roles never do for a System Admin.
        $this->assertSeeLifecycleControls($response, $superAdmin->getKey(), false);
    }

    public function test_system_admin_without_the_permission_cannot_access_the_directory(): void
    {
        $actor = $this->createSystemAdmin(withPermission: false);
        User::factory()->create();

        $this->actingAs($actor)
            ->get(route('settings.users'))
            ->assertForbidden();
    }

    public function test_super_admin_directory_access_remains_intact(): void
    {
        $actor = $this->createSuperAdmin();
        $other = User::factory()->create(['email' => 'plain-member@example.com']);
        $otherSuperAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($actor)->get(route('settings.users'));

        $response->assertOk()->assertSee('plain-member@example.com');

        // Super Admin sees lifecycle controls for every other row,
        // including protected system roles.
        $this->assertSeeLifecycleControls($response, $other->getKey(), true);
        $this->assertSeeLifecycleControls($response, $otherSuperAdmin->getKey(), true);
    }

    public function test_system_admin_can_perform_eligible_lifecycle_actions_via_the_interface(): void
    {
        $actor = $this->createSystemAdmin(withPermission: true);
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->post(route('settings.users.deactivate', $target), [
                'reason' => 'Confirmed policy violation.',
            ])
            ->assertRedirect(route('settings.users'));

        // The deactivated account is visible through the interface, including
        // the Reactivate action for the authorized System Admin.
        $this->actingAs($actor)
            ->get(route('settings.users', ['status' => 'deactivated']))
            ->assertOk()
            ->assertSee($target->email)
            ->assertSee('settings/users/'.$target->getKey().'/reactivate', false);

        $this->actingAs($actor)
            ->post(route('settings.users.reactivate', $target->getKey()))
            ->assertRedirect(route('settings.users'));

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'deleted_at' => null,
        ]);
    }

    public function test_the_seeder_grants_users_deactivate_to_the_system_admin_role(): void
    {
        (new RoleSeeder)->run();
        (new UserManagementPermissionSeeder)->run();

        $this->assertDatabaseHas('permissions', ['code' => 'users.deactivate']);

        $systemAdmin = Role::query()->where('code', 'SYSTEM_ADMIN')->firstOrFail();

        $this->assertTrue(
            $systemAdmin->permissions()->where('code', 'users.deactivate')->exists(),
        );
    }

    public function test_the_seeder_is_idempotent(): void
    {
        (new RoleSeeder)->run();
        (new UserManagementPermissionSeeder)->run();

        (new UserManagementPermissionSeeder)->run();

        $this->assertDatabaseCount('permissions', 1);
    }

    private function assertSeeLifecycleControls($response, int $userId, bool $visible): void
    {
        $deactivateUrl = 'settings/users/'.$userId.'/deactivate';

        if ($visible) {
            $response->assertSee($deactivateUrl, false);
        } else {
            $response->assertDontSee($deactivateUrl, false);
        }
    }

    private function createSuperAdmin(): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'SUPER_ADMIN'],
            ['name' => 'Super Admin', 'scope' => 'system'],
        );

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function createSystemAdmin(bool $withPermission): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'SYSTEM_ADMIN'],
            ['name' => 'System Admin', 'scope' => 'system'],
        );

        if ($withPermission) {
            $permission = Permission::query()->firstOrCreate(
                ['code' => 'users.deactivate'],
                ['name' => 'Deactivate User Accounts'],
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        // Deactivation transactions fail closed without the canonical
        // SUPER_ADMIN system role row, so every System Admin setup seeds it.
        Role::query()->firstOrCreate(
            ['code' => 'SUPER_ADMIN'],
            ['name' => 'Super Admin', 'scope' => 'system'],
        );

        return User::factory()->create(['role_id' => $role->id]);
    }
}
