<?php

namespace App\Services\Authorization;

use App\Models\User;

class PermissionChecker
{
    /**
     * Determine whether a user holds the unrestricted system-level
     * Super Admin role, verified by role code and scope — never by
     * name, email, or client identity.
     */
    public function isSuperAdmin(User $user): bool
    {
        $role = $user->role;

        return $role !== null
            && $role->code === 'SUPER_ADMIN'
            && $role->scope === 'system';
    }

    /**
     * Determine whether a user has the requested system permission.
     */
    public function allows(User $user, string $permissionCode): bool
    {
        if (trim($permissionCode) === '') {
            return false;
        }

        $role = $user->role;

        // Users without a valid system role cannot access system permissions.
        if (! $role || $role->scope !== 'system') {
            return false;
        }

        // Super Admin has unrestricted system-level authorization.
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        // Other system roles inherit permissions exclusively through
        // their assigned role-permission relationships.
        return $role->permissions()
            ->where('code', $permissionCode)
            ->exists();
    }
}
