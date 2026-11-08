<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProjectAuthorizationPermissionSeeder extends Seeder
{
    /**
     * System-scope permissions created and maintained by this seeder and
     * granted to SYSTEM_ADMIN for cross-project ownership management.
     *
     * @var array<string, array{name: string, description: string}>
     */
    private const SYSTEM_PERMISSIONS = [
        'projects.manage-ownership' => [
            'name' => 'Manage Project Ownership',
            'description' => 'Allows assigning, transferring, unassigning, and changing the assignment type of project ownership across projects, subject to the approved System Admin restrictions.',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $role = Role::query()
                ->where('code', 'SYSTEM_ADMIN')
                ->where('scope', 'system')
                ->first();

            if ($role === null) {
                throw new RuntimeException(
                    'The SYSTEM_ADMIN system role was not found. Run RoleSeeder first.'
                );
            }

            // The membership permission is the existing unique record owned
            // by ProjectRoleSeeder (project scope for PROJECT_ADMIN). It is
            // reused here for SYSTEM_ADMIN; the role's scope determines the
            // authority, never the permission itself.
            $manageMembership = Permission::query()
                ->where('code', 'projects.manage-members')
                ->first();

            if ($manageMembership === null) {
                throw new RuntimeException(
                    'The projects.manage-members permission was not found. Run ProjectRoleSeeder first.'
                );
            }

            // Fail safely on incompatible scope conflicts: attaching the
            // ownership permission to a project-scoped role would grant
            // project administrators ownership authority.
            $ownershipConflict = Role::query()
                ->where('scope', 'project')
                ->whereHas('permissions', fn ($query) => $query->where('code', 'projects.manage-ownership'))
                ->exists();

            if ($ownershipConflict) {
                throw new RuntimeException(
                    'The projects.manage-ownership permission is attached to a project-scoped role, which would grant project administrators ownership authority. Remove that attachment before seeding.'
                );
            }

            $permissionIds = [];

            foreach (self::SYSTEM_PERMISSIONS as $code => $attributes) {
                $permission = Permission::updateOrCreate(
                    ['code' => $code],
                    $attributes,
                );

                $permissionIds[] = $permission->id;
            }

            $permissionIds[] = $manageMembership->id;

            $role->permissions()->syncWithoutDetaching($permissionIds);
        });
    }
}
