<?php

namespace Tests\Feature\Authorization;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ProjectPolicyAbilitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_with_ownership_permission_manages_ownership_across_projects_without_membership(): void
    {
        $systemAdmin = $this->createSystemAdminWithPermission('projects.manage-ownership');

        $project = Project::factory()->create();

        $this->assertTrue(Gate::forUser($systemAdmin)->allows('manageOwnership', $project));

        // Ownership management is cross-project: membership is not required.
        $otherProject = Project::factory()->withoutOwner()->create();

        $this->assertTrue(Gate::forUser($systemAdmin)->allows('manageOwnership', $otherProject));
    }

    public function test_system_admin_without_ownership_permission_cannot_manage_ownership(): void
    {
        $systemAdmin = User::factory()->create();
        $this->assignSystemRole($systemAdmin, $this->createRole('System Admin', 'SYSTEM_ADMIN', 'system'));

        $project = Project::factory()->create();

        $this->assertFalse(Gate::forUser($systemAdmin)->allows('manageOwnership', $project));
    }

    public function test_system_admin_with_membership_permission_manages_membership_across_projects_without_membership(): void
    {
        $systemAdmin = $this->createSystemAdminWithPermission('projects.manage-members');

        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();

        $this->assertTrue(Gate::forUser($systemAdmin)->allows('manageMembers', $project));
        $this->assertTrue(Gate::forUser($systemAdmin)->allows('manageMembers', $otherProject));
    }

    public function test_system_admin_without_membership_permission_cannot_manage_membership(): void
    {
        $systemAdmin = User::factory()->create();
        $this->assignSystemRole($systemAdmin, $this->createRole('System Admin', 'SYSTEM_ADMIN', 'system'));

        $project = Project::factory()->create();

        $this->assertFalse(Gate::forUser($systemAdmin)->allows('manageMembers', $project));
    }

    public function test_membership_and_ownership_permissions_do_not_grant_each_other(): void
    {
        $systemAdmin = User::factory()->create();
        $this->assignSystemRole($systemAdmin, $this->createRole('System Admin', 'SYSTEM_ADMIN', 'system'));

        $project = Project::factory()->create();

        $role = $systemAdmin->role;

        // Holding the ownership permission grants ownership management only.
        $role->permissions()->syncWithoutDetaching([$this->createPermission('projects.manage-ownership')->id]);
        $systemAdmin->refresh();

        $this->assertTrue(Gate::forUser($systemAdmin)->allows('manageOwnership', $project));
        $this->assertFalse(Gate::forUser($systemAdmin)->allows('manageMembers', $project));

        // Holding the membership permission grants membership management only.
        $role->permissions()->detach(Permission::query()->where('code', 'projects.manage-ownership')->value('id'));
        $role->permissions()->syncWithoutDetaching([$this->createPermission('projects.manage-members')->id]);
        $systemAdmin->refresh();

        $this->assertTrue(Gate::forUser($systemAdmin)->allows('manageMembers', $project));
        $this->assertFalse(Gate::forUser($systemAdmin)->allows('manageOwnership', $project));
    }

    public function test_project_admin_manages_membership_only_where_member_with_project_permission(): void
    {
        $adminRole = $this->createRole('Project Admin', 'PROJECT_ADMIN', 'project');
        $adminRole->permissions()->attach($this->createPermission('projects.manage-members')->id);

        $member = User::factory()->create();

        $project = Project::factory()->create();
        $project->members()->attach($member->id, ['role_id' => $adminRole->id]);

        $this->assertTrue(Gate::forUser($member)->allows('manageMembers', $project));

        // Same permission, but no membership in this project.
        $outsiderProject = Project::factory()->create();

        $this->assertFalse(Gate::forUser($member)->allows('manageMembers', $outsiderProject));
    }

    public function test_system_admin_assigns_project_roles_through_membership_permission(): void
    {
        $systemAdmin = User::factory()->create();
        $this->assignSystemRole($systemAdmin, $this->createRole('System Admin', 'SYSTEM_ADMIN', 'system'));

        $role = $systemAdmin->role;

        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();

        // Without the membership permission there is no assignment authority.
        $this->assertFalse(Gate::forUser($systemAdmin)->allows('assignRoles', $project));

        // The ownership permission alone does not grant role assignment.
        $role->permissions()->syncWithoutDetaching([$this->createPermission('projects.manage-ownership')->id]);
        $systemAdmin->refresh();

        $this->assertTrue(Gate::forUser($systemAdmin)->allows('manageOwnership', $project));
        $this->assertFalse(Gate::forUser($systemAdmin)->allows('assignRoles', $project));

        // Adding the membership permission grants cross-project role
        // assignment without any project membership.
        $role->permissions()->syncWithoutDetaching([$this->createPermission('projects.manage-members')->id]);
        $systemAdmin->refresh();

        $this->assertTrue(Gate::forUser($systemAdmin)->allows('assignRoles', $project));
        $this->assertTrue(Gate::forUser($systemAdmin)->allows('assignRoles', $otherProject));
    }

    public function test_project_admin_assigns_roles_only_within_their_membership_boundary(): void
    {
        $adminRole = $this->createRole('Project Admin', 'PROJECT_ADMIN', 'project');
        $adminRole->permissions()->attach($this->createPermission('projects.assign-roles')->id);

        $member = User::factory()->create();

        $project = Project::factory()->create();
        $project->members()->attach($member->id, ['role_id' => $adminRole->id]);

        $this->assertTrue(Gate::forUser($member)->allows('assignRoles', $project));

        $otherProject = Project::factory()->create();

        $this->assertFalse(Gate::forUser($member)->allows('assignRoles', $otherProject));
    }

    public function test_project_admin_cannot_manage_designated_ownership(): void
    {
        $adminRole = $this->createRole('Project Admin', 'PROJECT_ADMIN', 'project');

        $adminRole->permissions()->attach($this->createPermission('projects.assign-roles')->id);
        $adminRole->permissions()->attach($this->createPermission('projects.manage-members')->id);
        $adminRole->permissions()->attach($this->createPermission('projects.manage-ownership')->id);

        $member = User::factory()->create();

        $project = Project::factory()->withoutOwner()->create();
        $project->members()->attach($member->id, ['role_id' => $adminRole->id]);

        // Even with every project permission including manage-membership,
        // ownership management stays exclusive to the system grant.
        $this->assertFalse(Gate::forUser($member)->allows('manageOwnership', $project));

        // Membership management itself still works within the boundary.
        $this->assertTrue(Gate::forUser($member)->allows('manageMembers', $project));
    }

    public function test_project_permissions_cannot_grant_system_level_authority(): void
    {
        $adminRole = $this->createRole('Project Admin', 'PROJECT_ADMIN', 'project');
        $adminRole->permissions()->attach($this->createPermission('projects.manage-ownership')->id);
        $adminRole->permissions()->attach($this->createPermission('users.deactivate')->id);

        $user = User::factory()->create(['role_id' => $adminRole->id]);

        $project = Project::factory()->create();

        $this->assertFalse(Gate::forUser($user)->allows('manageOwnership', $project));
        $this->assertFalse(Gate::forUser($user)->allows('manageMembers', $project));
        $this->assertFalse(Gate::forUser($user)->allows('assignRoles', $project));
    }

    public function test_super_admin_retains_unrestricted_access_to_new_abilities(): void
    {
        $superAdminRole = $this->createRole('Super Admin', 'SUPER_ADMIN', 'system');

        $superAdmin = User::factory()->create();
        $this->assignSystemRole($superAdmin, $superAdminRole);

        $project = Project::factory()->withoutOwner()->create();

        $this->assertTrue(Gate::forUser($superAdmin)->allows('manageOwnership', $project));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('manageMembers', $project));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('assignRoles', $project));

        $this->assertDatabaseMissing('permission_role', [
            'role_id' => $superAdminRole->id,
        ]);
    }

    public function test_existing_view_update_delete_authorization_unchanged(): void
    {
        $project = Project::factory()->create();
        $owner = $project->owner;

        $this->assertTrue(Gate::forUser($owner)->allows('view', $project));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $project));
        $this->assertTrue(Gate::forUser($owner)->allows('delete', $project));
    }

    private function createSystemAdminWithPermission(string $permissionCode): User
    {
        $systemAdminRole = Role::query()->firstOrCreate(
            ['code' => 'SYSTEM_ADMIN', 'scope' => 'system'],
            ['name' => 'System Admin', 'description' => 'System administrator with administrative access.'],
        );

        $systemAdminRole->permissions()->syncWithoutDetaching([$this->createPermission($permissionCode)->id]);

        $systemAdmin = User::factory()->create();
        $this->assignSystemRole($systemAdmin, $systemAdminRole);

        return $systemAdmin;
    }

    private function createRole(string $name, string $code, string $scope): Role
    {
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

    private function assignSystemRole(User $user, Role $role): void
    {
        $user->role()->associate($role);
        $user->save();
    }
}
