<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\ProjectAuthorizationPermissionSeeder;
use Database\Seeders\ProjectRoleSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserManagementPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ProjectAuthorizationPermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_grants_both_system_permissions_to_system_admin(): void
    {
        $this->seedCanonicalRolesForAdministration();

        $originalMembershipId = $this->existingMembershipPermissionId();

        (new ProjectAuthorizationPermissionSeeder)->run();

        $this->assertDatabaseCount('permissions', 7);

        $this->assertDatabaseHas('permissions', [
            'code' => 'projects.manage-ownership',
            'name' => 'Manage Project Ownership',
        ]);

        $this->assertDatabaseHas('permissions', [
            'id' => $originalMembershipId,
            'code' => 'projects.manage-members',
        ]);

        $systemAdmin = $this->systemAdminRole();

        $attachedCodes = $systemAdmin->permissions()->orderBy('code')->pluck('code')->all();

        $this->assertSame([], array_values(array_diff(
            ['projects.manage-members', 'projects.manage-ownership'],
            $attachedCodes,
        )));
    }

    public function test_super_admin_remains_unrestricted_without_attached_permissions(): void
    {
        $this->seedCanonicalRolesForAdministration();

        (new ProjectAuthorizationPermissionSeeder)->run();

        $this->assertDatabaseMissing('permission_role', [
            'role_id' => $this->superAdminRole()->id,
        ]);
    }

    public function test_it_is_idempotent_when_executed_repeatedly(): void
    {
        $this->seedCanonicalRolesForAdministration();

        $seeder = new ProjectAuthorizationPermissionSeeder;

        $seeder->run();

        $originalPermissionIds = $this->systemAdminRole()
            ->permissions()
            ->orderBy('code')
            ->pluck('id', 'code')
            ->all();

        $seeder->run();

        $this->assertDatabaseCount('permissions', 7);
        $this->assertSame($originalPermissionIds, $this->systemAdminRole()
            ->permissions()
            ->orderBy('code')
            ->pluck('id', 'code')
            ->all());
    }

    public function test_it_corrects_metadata_of_the_ownership_permission(): void
    {
        $this->seedCanonicalRolesForAdministration();

        $drifted = Permission::create([
            'name' => 'Incorrect Ownership Permission',
            'code' => 'projects.manage-ownership',
            'description' => 'Incorrect description.',
        ]);

        (new ProjectAuthorizationPermissionSeeder)->run();

        $this->assertDatabaseHas('permissions', [
            'id' => $drifted->id,
            'code' => 'projects.manage-ownership',
            'name' => 'Manage Project Ownership',
        ]);
    }

    public function test_it_does_not_remove_unrelated_system_admin_permissions(): void
    {
        $this->seedCanonicalRolesForAdministration();

        (new UserManagementPermissionSeeder)->run();

        (new ProjectAuthorizationPermissionSeeder)->run();

        $systemAdmin = $this->systemAdminRole();

        $this->assertTrue($systemAdmin->permissions()->where('code', 'users.deactivate')->exists());
        $this->assertTrue($systemAdmin->permissions()->where('code', 'projects.manage-members')->exists());
        $this->assertTrue($systemAdmin->permissions()->where('code', 'projects.manage-ownership')->exists());
    }

    public function test_it_rejects_ownership_permission_attached_to_project_roles(): void
    {
        $this->seedCanonicalRolesForAdministration();

        $strayOwnershipPermission = Permission::create([
            'name' => 'Manage Project Ownership',
            'code' => 'projects.manage-ownership',
            'description' => 'Wrongly attached to a project role.',
        ]);

        $projectRole = Role::query()->create([
            'name' => 'Project Admin',
            'code' => 'PROJECT_SUPER',
            'scope' => 'project',
        ]);

        $projectRole->permissions()->attach($strayOwnershipPermission->id);

        $exception = null;

        try {
            (new ProjectAuthorizationPermissionSeeder)->run();
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertStringContainsString('projects.manage-ownership', $exception->getMessage());

        $this->assertDatabaseMissing('permission_role', [
            'role_id' => $this->systemAdminRole()->id,
        ]);
    }

    public function test_it_requires_the_membership_permission_to_already_exist(): void
    {
        (new RoleSeeder)->run();

        $exception = null;

        try {
            (new ProjectAuthorizationPermissionSeeder)->run();
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertStringContainsString('ProjectRoleSeeder', $exception->getMessage());
    }

    public function test_it_requires_the_system_admin_role_to_exist(): void
    {
        (new RoleSeeder)->run();
        Role::query()->delete();

        $exception = null;

        try {
            (new ProjectAuthorizationPermissionSeeder)->run();
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertStringContainsString('RoleSeeder', $exception->getMessage());
    }

    private function seedCanonicalRolesForAdministration(): void
    {
        (new RoleSeeder)->run();
        (new ProjectRoleSeeder)->run();
    }

    private function existingMembershipPermissionId(): int
    {
        return Permission::query()->where('code', 'projects.manage-members')->value('id');
    }

    private function systemAdminRole(): Role
    {
        return Role::query()->where('code', 'SYSTEM_ADMIN')->where('scope', 'system')->firstOrFail();
    }

    private function superAdminRole(): Role
    {
        return Role::query()->where('code', 'SUPER_ADMIN')->where('scope', 'system')->firstOrFail();
    }
}
