<?php

namespace Tests\Unit\Authorization;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionCheckerTest extends TestCase
{
    use RefreshDatabase;

    private PermissionChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = new PermissionChecker;
    }

    public function test_user_with_assigned_permission_is_authorized(): void
    {
        $role = $this->createSystemRole();

        $permission = Permission::query()->create([
            'name' => 'View Projects',
            'code' => 'projects.view',
        ]);

        $role->permissions()->attach($permission);

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $this->assertTrue(
            $this->checker->allows($user, 'projects.view')
        );
    }

    public function test_user_without_assigned_permission_is_denied(): void
    {
        $role = $this->createSystemRole();

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $this->assertFalse(
            $this->checker->allows($user, 'projects.view')
        );
    }

    public function test_user_without_system_role_is_denied(): void
    {
        $user = User::factory()->create([
            'role_id' => null,
        ]);

        $this->assertFalse(
            $this->checker->allows($user, 'projects.view')
        );
    }

    public function test_project_role_cannot_grant_system_permissions(): void
    {
        $role = Role::query()->create([
            'name' => 'Project Administrator',
            'code' => 'PROJECT_ADMIN',
            'scope' => 'project',
        ]);

        $permission = Permission::query()->create([
            'name' => 'View Projects',
            'code' => 'projects.view',
        ]);

        $role->permissions()->attach($permission);

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $this->assertFalse(
            $this->checker->allows($user, 'projects.view')
        );
    }

    public function test_super_admin_has_unrestricted_system_permissions(): void
    {
        $role = Role::query()->create([
            'name' => 'Super Admin',
            'code' => 'SUPER_ADMIN',
            'scope' => 'system',
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $this->assertTrue(
            $this->checker->allows($user, 'projects.view')
        );

        $this->assertTrue(
            $this->checker->allows($user, 'users.deactivate')
        );
    }

    public function test_empty_permission_identifier_is_denied(): void
    {
        $role = $this->createSystemRole();

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $this->assertFalse(
            $this->checker->allows($user, '')
        );

        $this->assertFalse(
            $this->checker->allows($user, '   ')
        );
    }

    private function createSystemRole(): Role
    {
        return Role::query()->create([
            'name' => 'QA Supervisor',
            'code' => 'QA_SUPERVISOR',
            'scope' => 'system',
        ]);
    }
}
