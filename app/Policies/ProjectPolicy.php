<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionChecker;

class ProjectPolicy
{
    public function __construct(
        private readonly PermissionChecker $permissionChecker
    ) {}

    /**
     * Super Admin has unrestricted project access.
     */
    public function before(User $user): ?bool
    {
        $role = $user->role;

        if (
            $role &&
            $role->scope === 'system' &&
            $role->code === 'SUPER_ADMIN'
        ) {
            return true;
        }

        return null;
    }

    public function view(User $user, Project $project): bool
    {
        return $this->canPerform($user, $project, 'projects.view');
    }

    public function update(User $user, Project $project): bool
    {
        return $this->canPerform($user, $project, 'projects.update');
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->canPerform($user, $project, 'projects.delete');
    }

    /**
     * Cross-project membership-management ability.
     *
     * SYSTEM_ADMIN holds authority through the system-scope permission,
     * without requiring project membership. Other users manage membership
     * only in projects where their project-scoped role grants the same
     * permission code. The two sources are intentionally evaluated
     * separately from canPerform(): cross-project system authority must
     * never be able to bypass the ordinary project-action membership gate.
     */
    public function manageMembers(User $user, Project $project): bool
    {
        return $this->hasSystemGrant($user, 'projects.manage-members')
            || $this->projectRoleGrants($user, $project, 'projects.manage-members');
    }

    /**
     * Project role-assignment ability.
     *
     * SYSTEM_ADMIN assigns existing project-scoped roles across projects
     * through the system-scope `projects.manage-members` permission,
     * without requiring project membership. Other users assign roles only
     * in projects where their project-scoped role holds
     * `projects.assign-roles`. Owner and last-PROJECT_ADMIN invariants are
     * enforced later by the transactional membership actions, never
     * bypassed here.
     */
    public function assignRoles(User $user, Project $project): bool
    {
        return $this->hasSystemGrant($user, 'projects.manage-members')
            || $this->projectRoleGrants($user, $project, 'projects.assign-roles');
    }

    /**
     * Cross-project ownership-management ability (assignment, transfer,
     * unassignment, and assignment-type changes).
     *
     * Exclusive to the system-scope permission: PROJECT_ADMIN and other
     * project-scoped roles can never gain ownership-management authority.
     */
    public function manageOwnership(User $user, ?Project $project = null): bool
    {
        return $this->hasSystemGrant($user, 'projects.manage-ownership');
    }

    /**
     * System administrators hold the required permission through their
     * system-scope role, independent of any project membership.
     */
    private function hasSystemGrant(User $user, string $permissionCode): bool
    {
        $systemRole = $user->role;

        if (
            ! $systemRole ||
            $systemRole->scope !== 'system' ||
            $systemRole->code !== 'SYSTEM_ADMIN'
        ) {
            return false;
        }

        return $this->permissionChecker->allows($user, $permissionCode);
    }

    /**
     * Project-scoped grant: acting user must be a project member whose
     * project-scoped role holds the permission.
     */
    private function projectRoleGrants(User $user, Project $project, string $permissionCode): bool
    {
        $membership = $project->members()
            ->whereKey($user->id)
            ->first();

        if (! $membership) {
            return false;
        }

        $projectRole = Role::query()
            ->whereKey($membership->pivot->role_id)
            ->where('scope', 'project')
            ->first();

        if (! $projectRole) {
            return false;
        }

        return $projectRole->permissions()
            ->where('code', $permissionCode)
            ->exists();
    }

    /**
     * Evaluate ownership, system administrator restrictions,
     * and project membership permissions.
     */
    private function canPerform(
        User $user,
        Project $project,
        string $permissionCode
    ): bool {
        $systemRole = $user->role;

        // SYSTEM ADMIN must have both system permission and membership.
        // Ownership does not bypass these requirements.
        if (
            $systemRole &&
            $systemRole->scope === 'system' &&
            $systemRole->code === 'SYSTEM_ADMIN'
        ) {
            return $this->hasProjectAccess(
                $user,
                $project,
                $permissionCode
            );
        }

        // Ordinary project owners can manage their own projects.
        if ($this->isOwner($user, $project)) {
            return true;
        }

        return $this->hasProjectAccess(
            $user,
            $project,
            $permissionCode
        );
    }

    private function isOwner(User $user, Project $project): bool
    {
        return (int) $project->owner_id === (int) $user->id;
    }

    /**
     * Check project membership and the appropriate permission source.
     */
    private function hasProjectAccess(
        User $user,
        Project $project,
        string $permissionCode
    ): bool {
        $membership = $project->members()
            ->whereKey($user->id)
            ->first();

        if (! $membership) {
            return false;
        }

        $systemRole = $user->role;

        // SYSTEM ADMIN uses system-level permissions.
        if (
            $systemRole &&
            $systemRole->scope === 'system' &&
            $systemRole->code === 'SYSTEM_ADMIN'
        ) {
            return $this->permissionChecker->allows(
                $user,
                $permissionCode
            );
        }

        // Other users must have a valid project-scoped role.
        $projectRole = Role::query()
            ->whereKey($membership->pivot->role_id)
            ->where('scope', 'project')
            ->first();

        if (! $projectRole) {
            return false;
        }

        return $projectRole->permissions()
            ->where('code', $permissionCode)
            ->exists();
    }
}
