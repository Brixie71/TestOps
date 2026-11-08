<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionChecker;
use Database\Seeders\ProjectRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ProjectRoleSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Approved canonical PROJECT_ADMIN permissions.
     *
     * @var list<string>
     */
    private const EXPECTED_PERMISSIONS = [
        'projects.view',
        'projects.create',
        'projects.update',
        'projects.delete',
        'projects.manage-members',
        'projects.assign-roles',
    ];

    public function test_it_creates_the_canonical_project_admin_role_with_approved_permissions(): void
    {
        (new ProjectRoleSeeder)->run();

        $this->assertDatabaseCount('roles', 1);

        $this->assertDatabaseHas('roles', [
            'code' => 'PROJECT_ADMIN',
            'name' => 'Project Admin',
            'description' => 'Project administrator who manages the project, its members, and project-scoped role assignments.',
            'scope' => 'project',
        ]);

        $role = $this->projectAdminRole();

        $this->assertSame([], array_values(array_diff(self::EXPECTED_PERMISSIONS, $role->permissions()->pluck('code')->all())));

        // Project-scoped permissions must never grant system-wide authority.
        $this->assertTrue($role->permissions->every(
            fn (Permission $permission) => ! str_starts_with($permission->code, 'users.')
        ));
    }

    public function test_it_is_idempotent_when_executed_repeatedly(): void
    {
        $seeder = new ProjectRoleSeeder;

        $seeder->run();

        $originalRoleId = $this->projectAdminRole()->id;
        $originalPermissionIds = $this->projectAdminRole()
            ->permissions()
            ->orderBy('code')
            ->pluck('id', 'code')
            ->all();

        $seeder->run();

        $this->assertDatabaseCount('roles', 1);
        $this->assertDatabaseCount('permissions', 6);
        $this->assertDatabaseCount('permission_role', 6);
        $this->assertSame($originalRoleId, $this->projectAdminRole()->id);
        $this->assertSame($originalPermissionIds, $this->projectAdminRole()
            ->permissions()
            ->orderBy('code')
            ->pluck('id', 'code')
            ->all());
    }

    public function test_it_corrects_metadata_of_existing_project_roles(): void
    {
        $mismatchedRole = Role::query()->create([
            'name' => 'Incorrect Project Admin',
            'code' => 'PROJECT_ADMIN',
            'description' => 'Incorrect description.',
            'scope' => 'project',
        ]);

        (new ProjectRoleSeeder)->run();

        $this->assertDatabaseCount('roles', 1);

        $this->assertDatabaseHas('roles', [
            'id' => $mismatchedRole->id,
            'code' => 'PROJECT_ADMIN',
            'name' => 'Project Admin',
            'description' => 'Project administrator who manages the project, its members, and project-scoped role assignments.',
            'scope' => 'project',
        ]);
    }

    public function test_it_rejects_scope_conflicts_and_rolls_back_the_transaction(): void
    {
        $conflictingRole = Role::query()->create([
            'name' => 'System Project Admin',
            'code' => 'PROJECT_ADMIN',
            'description' => 'System-scoped role using a reserved project code.',
            'scope' => 'system',
        ]);

        $exception = null;

        try {
            (new ProjectRoleSeeder)->run();
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertStringContainsString('PROJECT_ADMIN', $exception->getMessage());

        $this->assertDatabaseCount('roles', 1);

        $this->assertDatabaseHas('roles', [
            'id' => $conflictingRole->id,
            'name' => 'System Project Admin',
            'code' => 'PROJECT_ADMIN',
            'description' => 'System-scoped role using a reserved project code.',
            'scope' => 'system',
        ]);

        $this->assertDatabaseCount('permissions', 0);
    }

    public function test_reseeding_removes_permissions_outside_the_canonical_set(): void
    {
        (new ProjectRoleSeeder)->run();

        $role = $this->projectAdminRole();

        $strayPermission = Permission::firstOrCreate(
            ['code' => 'projects.export'],
            [
                'name' => 'Export Projects',
                'description' => 'Permission attached outside the canonical set.',
            ],
        );

        $role->permissions()->attach($strayPermission->id);

        (new ProjectRoleSeeder)->run();

        $attachedCodes = $this->projectAdminRole()->permissions()->orderBy('code')->pluck('code')->all();

        $expected = collect(self::EXPECTED_PERMISSIONS)->sort()->values()->all();

        $this->assertSame($expected, $attachedCodes);
        $this->assertDatabaseCount('permission_role', count(self::EXPECTED_PERMISSIONS));
    }

    public function test_it_does_not_modify_existing_system_roles(): void
    {
        (new RoleSeeder)->run();

        $systemRoleIds = Role::query()
            ->where('scope', 'system')
            ->orderBy('code')
            ->pluck('id', 'code')
            ->all();

        (new ProjectRoleSeeder)->run();

        $this->assertDatabaseCount('roles', 3);

        $this->assertDatabaseHas('roles', [
            'id' => $systemRoleIds['SUPER_ADMIN'],
            'code' => 'SUPER_ADMIN',
            'name' => 'Super Admin',
            'description' => 'System-wide administrator with full access.',
            'scope' => 'system',
        ]);

        $this->assertDatabaseHas('roles', [
            'id' => $systemRoleIds['SYSTEM_ADMIN'],
            'code' => 'SYSTEM_ADMIN',
            'name' => 'System Admin',
            'description' => 'System administrator with administrative access subject to defined restrictions.',
            'scope' => 'system',
        ]);
    }

    public function test_project_scoped_role_permissions_cannot_grant_system_authority(): void
    {
        (new ProjectRoleSeeder)->run();

        $role = $this->projectAdminRole();

        // A system permission code attached to the project role is still
        // unreachable through system authorization.
        $systemPermission = Permission::firstOrCreate(
            ['code' => 'users.deactivate'],
            [
                'name' => 'Deactivate User Accounts',
                'description' => 'Allows deactivating eligible user accounts.',
            ],
        );

        $role->permissions()->syncWithoutDetaching([$systemPermission->id]);

        $member = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $allows = $this->app->make(PermissionChecker::class);

        $this->assertFalse($allows->allows($member, 'users.deactivate'));
        $this->assertFalse($allows->allows($member, 'projects.view'));
    }

    private function projectAdminRole(): Role
    {
        return Role::query()
            ->where('code', 'PROJECT_ADMIN')
            ->where('scope', 'project')
            ->firstOrFail();
    }
}
