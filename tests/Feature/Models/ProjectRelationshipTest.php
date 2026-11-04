<?php

namespace Tests\Feature\Models;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_belongs_to_its_owner(): void
    {

        $project = Project::factory()->create();

        $this->assertInstanceOf(User::class, $project->owner);
        $this->assertSame($project->owner_id, $project->owner->id);
    }

    public function test_project_has_members_with_assigned_project_roles(): void
    {
        $project = Project::factory()->create();
        $member = User::factory()->create();

        $role = Role::query()->create([
            'name' => 'Tester',
            'code' => 'TESTER',
            'scope' => 'project',
        ]);

        $project->members()->attach($member->id, [
            'role_id' => $role->id,
        ]);

        $this->assertTrue($project->members->contains($member));
        $this->assertSame($role->id, $project->members->first()->pivot->role_id);
    }

    public function test_user_belongs_to_projects_as_a_member(): void
    {
        $project = Project::factory()->create();
        $member = User::factory()->create();

        $role = Role::query()->create([
            'name' => 'Tester',
            'code' => 'TESTER',
            'scope' => 'project',
        ]);

        $member->projects()->attach($project->id, [
            'role_id' => $role->id,
        ]);

        $this->assertTrue($member->projects->contains($project));
        $this->assertSame(
            $role->id,
            $member->projects->first()->pivot->role_id
        );
    }

    public function test_user_belongs_to_its_assigned_system_role(): void
    {
        $role = Role::query()->create([
            'name' => 'System Auditor',
            'code' => 'SYSTEM_AUDITOR',
            'scope' => 'system',
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $this->assertInstanceOf(Role::class, $user->role);
        $this->assertSame($role->id, $user->role->id);
    }

    public function test_role_has_users_assigned_to_it(): void
    {
        $role = Role::query()->create([
            'name' => 'System Auditor',
            'code' => 'SYSTEM_AUDITOR',
            'scope' => 'system',
        ]);

        $firstUser = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $secondUser = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $this->assertCount(2, $role->users);
        $this->assertTrue($role->users->contains($firstUser));
        $this->assertTrue($role->users->contains($secondUser));
    }

    public function test_role_has_assigned_permissions(): void
    {
        $role = Role::query()->create([
            'name' => 'QA Supervisor',
            'code' => 'QA_SUPERVISOR',
            'scope' => 'system',
        ]);

        $viewProjects = Permission::query()->create([
            'name' => 'View Projects',
            'code' => 'PROJECTS_VIEW',
        ]);

        $createProjects = Permission::query()->create([
            'name' => 'Create Projects',
            'code' => 'PROJECTS_CREATE',
        ]);

        $role->permissions()->attach([
            $viewProjects->id,
            $createProjects->id,
        ]);

        $this->assertCount(2, $role->permissions);
        $this->assertTrue($role->permissions->contains($viewProjects));
        $this->assertTrue($role->permissions->contains($createProjects));
    }

    public function test_permission_has_roles_assigned_to_it(): void
    {
        $permission = Permission::query()->create([
            'name' => 'View Projects',
            'code' => 'PROJECTS_VIEW',
        ]);

        $firstRole = Role::query()->create([
            'name' => 'QA Supervisor',
            'code' => 'QA_SUPERVISOR',
            'scope' => 'system',
        ]);

        $secondRole = Role::query()->create([
            'name' => 'Project Admin',
            'code' => 'PROJECT_ADMIN',
            'scope' => 'project',
        ]);

        $permission->roles()->attach([
            $firstRole->id,
            $secondRole->id,
        ]);

        $this->assertCount(2, $permission->roles);
        $this->assertTrue($permission->roles->contains($firstRole));
        $this->assertTrue($permission->roles->contains($secondRole));
    }

    public function test_role_has_project_members_assigned_to_it(): void
    {
        $role = Role::query()->create([
            'name' => 'Tester',
            'code' => 'TESTER',
            'scope' => 'project',
        ]);

        $firstProject = Project::factory()->create();
        $secondProject = Project::factory()->create();

        $member = User::factory()->create();

        $role->projectMembers()->attach($member->id, [
            'project_id' => $firstProject->id,
        ]);

        $role->projectMembers()->attach($member->id, [
            'project_id' => $secondProject->id,
        ]);

        $projectMembers = $role->projectMembers;

        $this->assertCount(2, $projectMembers);

        $this->assertSame(
            [$firstProject->id, $secondProject->id],
            $projectMembers->pluck('pivot.project_id')->sort()->values()->all()
        );
    }

    public function test_soft_deleted_users_are_excluded_from_normal_queries(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(
            User::query()->whereKey($user->id)->exists()
        );

        $user->delete();

        $this->assertNotNull($user->fresh()->deleted_at);

        $this->assertFalse(
            User::query()->whereKey($user->id)->exists()
        );

        $this->assertTrue(
            User::withTrashed()->whereKey($user->id)->exists()
        );
    }
}
