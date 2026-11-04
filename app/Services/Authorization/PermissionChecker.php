<?php

namespace App\Services\Authorization;

use App\Models\User;

class PermissionChecker
{
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
        if ($role->code === 'SUPER_ADMIN') {
            return true;
        }

        // Other system roles inherit permissions exclusively through
        // their assigned role-permission relationships.
        return $role->permissions()
            ->where('code', $permissionCode)
            ->exists();
    }
}
