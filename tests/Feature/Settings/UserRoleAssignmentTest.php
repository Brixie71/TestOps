<?php

namespace Tests\Feature\Settings;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_assign_a_system_role(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create(); // No system role.
        $newRole = $this->createSystemRole('TESTER', 'Tester');

        $this->actingAs($actor)
            ->from(route('settings.users.show', $target))
            ->post(route('settings.users.role.update', $target), [
                'role_id' => $newRole->id,
            ])
            ->assertRedirect(route('settings.users.show', $target))
            ->assertSessionHas('status');

        $this->assertSame($newRole->id, $target->refresh()->role_id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.role_changed',
            'target_user_id' => $target->getKey(),
        ]);
    }

    public function test_super_admin_can_clear_a_system_role(): void
    {
        $actor = $this->createSuperAdmin();
        $oldRole = $this->createSystemRole('TESTER', 'Tester');
        $target = User::factory()->create(['role_id' => $oldRole->id]);

        $this->actingAs($actor)
            ->post(route('settings.users.role.update', $target), [
                'role_id' => null,
            ])
            ->assertRedirect(route('settings.users.show', $target));

        $this->assertNull($target->refresh()->role_id);
    }

    public function test_system_admin_with_the_permission_can_assign_an_ordinary_role(): void
    {
        $actor = $this->createSystemAdmin(withPermission: true);
        $target = User::factory()->create();
        $newRole = $this->createSystemRole('TESTER', 'Tester');

        $this->actingAs($actor)
            ->post(route('settings.users.role.update', $target), [
                'role_id' => $newRole->id,
            ])
            ->assertRedirect(route('settings.users.show', $target));

        $this->assertSame($newRole->id, $target->refresh()->role_id);
    }

    public function test_system_admin_without_the_permission_is_forbidden(): void
    {
        $actor = $this->createSystemAdmin(withPermission: false);
        $target = User::factory()->create();
        $newRole = $this->createSystemRole('TESTER', 'Tester');

        $this->actingAs($actor)
            ->post(route('settings.users.role.update', $target), [
                'role_id' => $newRole->id,
            ])
            ->assertForbidden();

        $this->assertNull($target->refresh()->role_id);
    }

    public function test_system_admin_cannot_assign_the_super_admin_role(): void
    {
        $actor = $this->createSystemAdmin(withPermission: true);
        $target = User::factory()->create();
        $superAdminRole = Role::query()->firstOrCreate(
            ['code' => 'SUPER_ADMIN'],
            ['name' => 'Super Admin', 'scope' => 'system'],
        );

        $this->actingAs($actor)
            ->post(route('settings.users.role.update', $target), [
                'role_id' => $superAdminRole->id,
            ])
            ->assertForbidden();

        $this->assertNull($target->refresh()->role_id);
        $this->assertSame(0, AuditLog::query()->where('action', 'user.role_changed')->count());
    }

    public function test_system_admin_cannot_change_another_system_admins_role(): void
    {
        $actor = $this->createSystemAdmin(withPermission: true);
        $systemAdminRole = Role::query()->where('code', 'SYSTEM_ADMIN')->first();
        $target = User::factory()->create(['role_id' => $systemAdminRole->id]);
        $newRole = $this->createSystemRole('TESTER', 'Tester');

        $this->actingAs($actor)
            ->post(route('settings.users.role.update', $target), [
                'role_id' => $newRole->id,
            ])
            ->assertForbidden();

        $this->assertSame($systemAdminRole->id, $target->refresh()->role_id);
    }

    public function test_system_admin_cannot_change_a_super_admins_role(): void
    {
        $actor = $this->createSystemAdmin(withPermission: true);
        $target = $this->createSuperAdmin();
        $newRole = $this->createSystemRole('TESTER', 'Tester');

        $this->actingAs($actor)
            ->post(route('settings.users.role.update', $target), [
                'role_id' => $newRole->id,
            ])
            ->assertForbidden();

        $this->assertSame($this->superAdminRoleId(), $target->refresh()->role_id);
    }

    public function test_users_cannot_change_their_own_role(): void
    {
        $actor = $this->createSuperAdmin();
        $newRole = $this->createSystemRole('TESTER', 'Tester');

        $this->actingAs($actor)
            ->post(route('settings.users.role.update', $actor), [
                'role_id' => $newRole->id,
            ])
            ->assertForbidden();

        $this->assertSame($this->superAdminRoleId(), $actor->refresh()->role_id);
    }

    public function test_a_super_admin_can_demote_another_super_admin_when_others_remain(): void
    {
        $actor = $this->createSuperAdmin();
        $target = $this->createSuperAdmin();
        $newRole = $this->createSystemRole('TESTER', 'Tester');

        // A second active Super Admin besides the target keeps the
        // last-active safeguard satisfied.
        $this->actingAs($actor)
            ->post(route('settings.users.role.update', $target), [
                'role_id' => $newRole->id,
            ])
            ->assertRedirect(route('settings.users.show', $target));

        $this->assertSame($newRole->id, $target->refresh()->role_id);
    }

    public function test_deactivated_accounts_reject_role_changes(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create();
        $target->delete();
        $newRole = $this->createSystemRole('TESTER', 'Tester');

        $this->actingAs($actor)
            ->post(route('settings.users.role.update', $target), [
                'role_id' => $newRole->id,
            ])
            ->assertNotFound();

        $this->assertNull($target->refresh()->role_id);
    }

    public function test_project_scoped_roles_are_never_assignable(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create();
        $projectRole = Role::query()->create([
            'name' => 'Project Admin',
            'code' => 'PROJECT_ADMIN',
            'scope' => 'project',
        ]);

        $this->actingAs($actor)
            ->from(route('settings.users.show', $target))
            ->post(route('settings.users.role.update', $target), [
                'role_id' => $projectRole->id,
            ])
            ->assertRedirect(route('settings.users.show', $target))
            ->assertSessionHasErrors('role_id');

        $this->assertNull($target->refresh()->role_id);
    }

    public function test_assigning_the_current_role_again_is_rejected(): void
    {
        $actor = $this->createSuperAdmin();
        $oldRole = $this->createSystemRole('TESTER', 'Tester');
        $target = User::factory()->create(['role_id' => $oldRole->id]);

        $this->actingAs($actor)
            ->from(route('settings.users.show', $target))
            ->post(route('settings.users.role.update', $target), [
                'role_id' => $oldRole->id,
            ])
            ->assertRedirect(route('settings.users.show', $target))
            ->assertSessionHasErrors('role_id');

        $this->assertSame($oldRole->id, $target->refresh()->role_id);
    }

    public function test_the_role_change_audits_before_and_after_values(): void
    {
        $actor = $this->createSuperAdmin();
        $oldRole = $this->createSystemRole('TESTER', 'Tester');
        $newRole = $this->createSystemRole('MANAGER', 'Manager');
        $target = User::factory()->create(['role_id' => $oldRole->id]);

        $this->actingAs($actor)
            ->post(route('settings.users.role.update', $target), [
                'role_id' => $newRole->id,
            ]);

        $entry = AuditLog::query()
            ->where('action', 'user.role_changed')
            ->where('target_user_id', $target->getKey())
            ->sole();

        $this->assertSame($oldRole->id, $entry->before_values['role_id']);
        $this->assertSame('TESTER', $entry->before_values['role_code']);
        $this->assertSame($newRole->id, $entry->after_values['role_id']);
        $this->assertSame('MANAGER', $entry->after_values['role_code']);
    }

    private function superAdminRoleId(): int
    {
        return (int) Role::query()
            ->where('code', 'SUPER_ADMIN')
            ->where('scope', 'system')
            ->sole()
            ->getKey();
    }

    private function createSystemRole(string $code, string $name): Role
    {
        return Role::query()->firstOrCreate(
            ['code' => $code],
            ['name' => $name, 'scope' => 'system'],
        );
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
                ['code' => 'users.roles.assign'],
                ['name' => 'Assign User System Roles'],
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        // Role-assignment transactions fail closed without the canonical
        // SUPER_ADMIN system role row, so every System Admin setup seeds it.
        Role::query()->firstOrCreate(
            ['code' => 'SUPER_ADMIN'],
            ['name' => 'Super Admin', 'scope' => 'system'],
        );

        return User::factory()->create(['role_id' => $role->id]);
    }
}
