<?php

namespace Tests\Feature\Authorization;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ProjectPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_owner_can_view_update_and_delete_own_project(): void
    {
        $project = Project::factory()->create();
        $owner = $project->owner;

        $this->assertTrue(Gate::forUser($owner)->allows('view', $project));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $project));
        $this->assertTrue(Gate::forUser($owner)->allows('delete', $project));
    }

    public function test_project_member_can_view_project_with_assigned_permission(): void
    {
        [$project, $member] = $this->createProjectMember('projects.view');

        $this->assertTrue(Gate::forUser($member)->allows('view', $project));
    }

    public function test_project_member_can_update_project_with_assigned_permission(): void
    {
        [$project, $member] = $this->createProjectMember('projects.update');

        $this->assertTrue(Gate::forUser($member)->allows('update', $project));
    }

    public function test_project_member_can_delete_project_with_assigned_permission(): void
    {
        [$project, $member] = $this->createProjectMember('projects.delete');

        $this->assertTrue(Gate::forUser($member)->allows('delete', $project));
    }

    public function test_project_member_without_required_permission_is_denied(): void
    {
        [$project, $member] = $this->createProjectMember('projects.view');

        $this->assertFalse(Gate::forUser($member)->allows('update', $project));
        $this->assertFalse(Gate::forUser($member)->allows('delete', $project));
    }

    public function test_non_member_cannot_access_project(): void
    {
        $project = Project::factory()->create();
        $nonMember = User::factory()->create();

        $this->assertFalse(Gate::forUser($nonMember)->allows('view', $project));
        $this->assertFalse(Gate::forUser($nonMember)->allows('update', $project));
        $this->assertFalse(Gate::forUser($nonMember)->allows('delete', $project));
    }

    public function test_system_role_cannot_grant_project_permissions(): void
    {
        $project = Project::factory()->create();
        $member = User::factory()->create();

        $systemRole = $this->createRole(
            'QA Supervisor',
            'QA_SUPERVISOR',
            'system'
        );

        $permission = $this->createPermission('projects.view');

        $systemRole->permissions()->attach($permission);

        $project->members()->attach($member->id, [
            'role_id' => $systemRole->id,
        ]);

        $this->assertFalse(Gate::forUser($member)->allows('view', $project));
    }

    public function test_super_admin_has_unrestricted_project_access(): void
    {
        $project = Project::factory()->create();

        $superAdminRole = $this->createRole(
            'Super Admin',
            'SUPER_ADMIN',
            'system'
        );

        $superAdmin = User::factory()->create();

        $this->assignRole($superAdmin, $superAdminRole);

        $this->assertTrue(Gate::forUser($superAdmin)->allows('view', $project));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('update', $project));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('delete', $project));
    }

    public function test_system_admin_requires_system_permission_and_project_membership(): void
    {
        $project = Project::factory()->create();

        $systemAdminRole = $this->createRole(
            'System Admin',
            'SYSTEM_ADMIN',
            'system'
        );

        $viewPermission = $this->createPermission('projects.view');

        $systemAdminRole->permissions()->attach($viewPermission);

        $systemAdmin = User::factory()->create();

        $this->assignRole($systemAdmin, $systemAdminRole);

        // System permission alone is insufficient without project membership.
        $this->assertFalse(
            Gate::forUser($systemAdmin)->allows('view', $project)
        );

        $projectRole = $this->createRole(
            'Project Viewer',
            'PROJECT_VIEWER',
            'project'
        );

        $project->members()->attach($systemAdmin->id, [
            'role_id' => $projectRole->id,
        ]);

        // Membership plus system permission grants access.
        $this->assertTrue(
            Gate::forUser($systemAdmin)->allows('view', $project)
        );

        // The System Admin does not have the update system permission.
        $this->assertFalse(
            Gate::forUser($systemAdmin)->allows('update', $project)
        );
    }

    public function test_system_admin_owner_still_requires_membership_and_system_permission(): void
    {
        $systemAdminRole = $this->createRole(
            'System Admin',
            'SYSTEM_ADMIN',
            'system'
        );

        $systemAdmin = User::factory()->create();

        $this->assignRole($systemAdmin, $systemAdminRole);

        $project = Project::factory()->create([
            'owner_id' => $systemAdmin->id,
        ]);

        // Ownership alone does not grant SYSTEM ADMIN access.
        $this->assertFalse(
            Gate::forUser($systemAdmin)->allows('view', $project)
        );

        $projectRole = $this->createRole(
            'Project Viewer',
            'PROJECT_VIEWER',
            'project'
        );

        $project->members()->attach($systemAdmin->id, [
            'role_id' => $projectRole->id,
        ]);

        // Membership alone is insufficient without the system permission.
        $this->assertFalse(
            Gate::forUser($systemAdmin)->allows('view', $project)
        );

        $viewPermission = $this->createPermission('projects.view');

        $systemAdminRole->permissions()->attach($viewPermission);

        // Both requirements are now satisfied.
        $this->assertTrue(
            Gate::forUser($systemAdmin)->allows('view', $project)
        );
    }

    private function createProjectMember(string $permissionCode): array
    {
        $project = Project::factory()->create();
        $member = User::factory()->create();

        $projectRole = $this->createRole(
            'Project Member',
            'PROJECT_MEMBER',
            'project'
        );

        $permission = $this->createPermission($permissionCode);

        $projectRole->permissions()->attach($permission);

        $project->members()->attach($member->id, [
            'role_id' => $projectRole->id,
        ]);

        return [$project, $member];
    }

    private function assignRole(User $user, Role $role): void
    {
        $user->role()->associate($role);
        $user->save();
    }

    private function createRole(
        string $name,
        string $code,
        string $scope
    ): Role {
        return Role::query()->create([
            'name' => $name,
            'code' => $code,
            'scope' => $scope,
        ]);
    }

    private function createPermission(string $code): Permission
    {
        return Permission::query()->create([
            'name' => ucwords(str_replace(['.', '_'], ' ', $code)),
            'code' => $code,
        ]);
    }
}
