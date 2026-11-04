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
