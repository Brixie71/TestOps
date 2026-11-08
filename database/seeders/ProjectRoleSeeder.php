<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProjectRoleSeeder extends Seeder
{
    /**
     * Canonical project roles managed by this seeder.
     *
     * System-scoped roles are intentionally excluded (see RoleSeeder).
     *
     * @var array<string, array{name: string, description: string, scope: string}>
     */
    private const PROJECT_ROLES = [
        'PROJECT_ADMIN' => [
            'name' => 'Project Admin',
            'description' => 'Project administrator who manages the project, its members, and project-scoped role assignments.',
            'scope' => 'project',
        ],
    ];

    /**
     * Canonical permissions granted to the PROJECT_ADMIN project role.
     *
     * Project-scoped permissions never grant system-wide authority.
     *
     * @var array<string, array{name: string, description: string}>
     */
    private const ROLE_PERMISSIONS = [
        'projects.view' => [
            'name' => 'View Projects',
            'description' => 'Allows viewing projects within the authorized project scope.',
        ],
        'projects.create' => [
            'name' => 'Create Projects',
            'description' => 'Allows creating projects within the authorized project scope.',
        ],
        'projects.update' => [
            'name' => 'Update Projects',
            'description' => 'Allows updating projects within the authorized project scope.',
        ],
        'projects.delete' => [
            'name' => 'Delete Projects',
            'description' => 'Allows deleting projects within the authorized project scope.',
        ],
        'projects.manage-members' => [
            'name' => 'Manage Project Members',
            'description' => 'Allows adding and removing project members within the authorized project scope.',
        ],
        'projects.assign-roles' => [
            'name' => 'Assign Project Roles',
            'description' => 'Allows assigning and changing project-scoped roles, including PROJECT_ADMIN, within the authorized project scope.',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $roles = [];

            foreach (self::PROJECT_ROLES as $code => $attributes) {
                $role = Role::query()
                    ->where('code', $code)
                    ->lockForUpdate()
                    ->first();

                if ($role !== null && $role->scope !== 'project') {
                    throw new RuntimeException(
                        "Cannot seed the {$code} project role because that code is already assigned to a non-project role."
                    );
                }

                if ($role === null) {
                    $role = Role::query()->create([
                        'code' => $code,
                        ...$attributes,
                    ]);
                } else {
                    $role->update($attributes);
                }

                $roles[] = $role;
            }

            $permissionIds = [];

            foreach (self::ROLE_PERMISSIONS as $code => $attributes) {
                $permission = Permission::firstOrCreate(
                    ['code' => $code],
                    $attributes,
                );

                $permissionIds[] = $permission->id;
            }

            foreach ($roles as $role) {
                // sync() enforces the exact canonical permission set:
                // permissions attached outside the approved list are removed.
                $role->permissions()->sync($permissionIds);
            }
        });
    }
}
