<?php

namespace App\Services\Authorization;

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single source of truth for project scope: which projects a user may see,
 * and which projects hold a given project-scoped ability.
 *
 * The projects directory and every dashboard widget resolve their project
 * scope through this service, so the role rules exist in exactly one place.
 * ProjectPolicy remains the per-project authority; this class mirrors it as
 * query constraints. ProjectVisibilityTest keeps the two in parity.
 */
class ProjectVisibility
{
    public function __construct(
        private readonly PermissionChecker $permissionChecker,
    ) {}

    /**
     * Projects the user may see, mirroring ProjectPolicy::view().
     *
     * SUPER_ADMIN sees every project. A SYSTEM_ADMIN holding either
     * cross-project management permission (projects.manage-members or
     * projects.manage-ownership) sees every project. A SYSTEM_ADMIN without
     * those grants needs active membership plus the system-scope
     * projects.view permission; ownership alone never qualifies. Everyone
     * else sees owned projects or projects whose project-scoped role grants
     * projects.view.
     *
     * @return Builder<Project>
     */
    public function viewableProjects(User $user): Builder
    {
        $query = Project::query();

        if ($this->permissionChecker->isSuperAdmin($user)) {
            return $query;
        }

        if ($this->isSystemAdmin($user)) {
            if ($this->viewsAllProjects($user)) {
                return $query;
            }

            if (! $this->permissionChecker->allows($user, 'projects.view')) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereHas('members', fn (Builder $members) => $members
                ->whereKey($user->getKey()));
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query
                ->where('owner_id', $user->getKey())
                ->orWhereHas('members', fn (Builder $members) => $members
                    ->whereKey($user->getKey())
                    ->whereIn('project_user.role_id', $this->projectRoleIdsGranting('projects.view')));
        });
    }

    /**
     * IDs of projects where the user holds the given project-scoped ability,
     * mirroring the per-ability ProjectPolicy methods (viewTestCases,
     * viewTestRuns, and viewDefects share this shape).
     *
     * @return Builder<Project>
     */
    public function projectIdsFor(User $user, string $permissionCode): Builder
    {
        $query = Project::query()->select('projects.id');

        if ($this->permissionChecker->isSuperAdmin($user)) {
            return $query;
        }

        if ($this->isSystemAdmin($user)) {
            // A system-scope grant covers every project; the project role
            // path still requires membership without a system grant.
            if ($this->permissionChecker->allows($user, $permissionCode)) {
                return $query;
            }

            return $query->whereHas('members', fn (Builder $members) => $members
                ->whereKey($user->getKey())
                ->whereIn('project_user.role_id', $this->projectRoleIdsGranting($permissionCode)));
        }

        return $query->where(function (Builder $query) use ($user, $permissionCode): void {
            $query
                ->where('owner_id', $user->getKey())
                ->orWhereHas('members', fn (Builder $members) => $members
                    ->whereKey($user->getKey())
                    ->whereIn('project_user.role_id', $this->projectRoleIdsGranting($permissionCode)));
        });
    }

    /**
     * Cross-project directory visibility, mirroring ProjectPolicy::view():
     * SUPER_ADMIN always qualifies, and a SYSTEM_ADMIN qualifies through
     * either existing cross-project management permission.
     */
    private function viewsAllProjects(User $user): bool
    {
        return $this->permissionChecker->isSuperAdmin($user)
            || ($this->isSystemAdmin($user)
                && ($this->permissionChecker->allows($user, 'projects.manage-members')
                    || $this->permissionChecker->allows($user, 'projects.manage-ownership')));
    }

    /**
     * True only for a system-scope role with the SYSTEM_ADMIN code, so a
     * project-scoped role can never reach a system-level branch.
     */
    private function isSystemAdmin(User $user): bool
    {
        $role = $user->role;

        return $role !== null
            && $role->scope === 'system'
            && $role->code === 'SYSTEM_ADMIN';
    }

    /**
     * Project-scoped roles that grant the requested permission code.
     *
     * @return Builder<Role>
     */
    private function projectRoleIdsGranting(string $permissionCode): Builder
    {
        return Role::query()
            ->where('scope', 'project')
            ->whereHas('permissions', fn (Builder $permissions) => $permissions
                ->where('code', $permissionCode))
            ->select('id');
    }
}
