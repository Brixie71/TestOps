<?php

namespace App\Services\Users;

use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionChecker;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Server-side authorization for user account lifecycle operations.
 *
 * Reuses the existing PermissionChecker — no parallel authorization
 * mechanism — and enforces the approved User Management restrictions:
 * the actor needs the `users.deactivate` system permission, project-scoped
 * roles can never grant lifecycle authority, users cannot deactivate or
 * reactivate themselves, and non-Super-Admin actors may never manage
 * Super Admin or System Admin accounts.
 */
class UserLifecycleAuthorizer
{
    /**
     * Built-in system role codes that non-Super-Admin actors may not manage.
     *
     * @var array<int, string>
     */
    private const PROTECTED_TARGET_ROLE_CODES = ['SUPER_ADMIN', 'SYSTEM_ADMIN'];

    public function __construct(
        private readonly PermissionChecker $permissionChecker
    ) {}

    /**
     * Assert that the actor may deactivate the target user.
     *
     * @throws AuthorizationException when the actor is not authorized
     */
    public function assertCanDeactivate(User $actor, User $target): void
    {
        $this->assertAuthorized($actor, $target);
    }

    /**
     * Assert that the actor may reactivate the target user.
     *
     * @throws AuthorizationException when the actor is not authorized
     */
    public function assertCanReactivate(User $actor, User $target): void
    {
        $this->assertAuthorized($actor, $target);
    }

    /**
     * Assert that an active Super Admin safeguard protects the last active
     * Super Admin from being deactivated.
     *
     * The system must always keep at least one active Super Admin. This is a
     * defence-in-depth check: authorization already blocks self-deactivation,
     * so it is primarily meaningful for future lifecycle operations and for
     * bulk remaining paths that bypass those restrictions.
     *
     * @throws AuthorizationException when the target is the last active Super Admin
     */
    public function assertLastActiveSuperAdminProtected(User $target): void
    {
        $role = $target->role;

        if ($role === null
            || $role->scope !== 'system'
            || $role->code !== 'SUPER_ADMIN') {
            return;
        }

        $otherActiveSuperAdmins = User::query()
            ->whereHas('role', function ($query): void {
                $query->where('code', 'SUPER_ADMIN')->where('scope', 'system');
            })
            ->whereKeyNot($target->getKey())
            ->count();

        if ($otherActiveSuperAdmins === 0) {
            throw new AuthorizationException(
                'The last active Super Admin account cannot be deactivated.'
            );
        }
    }

    /**
     * Assert that the actor may manage the target user's password (§11.2):
     * the `users.password.manage` permission, the self-management
     * restriction (§6.5 — self-password changes follow their separate
     * security workflow), and the protected-target restrictions.
     *
     * @throws AuthorizationException when the actor is not authorized
     */
    public function assertCanManagePassword(User $actor, User $target): void
    {
        if (! $this->permissionChecker->allows($actor, 'users.password.manage')) {
            throw new AuthorizationException(
                'You are not authorized to manage user passwords.'
            );
        }

        if ($actor->is($target)) {
            throw new AuthorizationException(
                'You cannot manage your own password through administrative password management.'
            );
        }

        if ($this->isProtectedTarget($actor, $target)) {
            throw new AuthorizationException(
                'System Admins cannot manage Super Admin or other System Admin accounts.'
            );
        }
    }

    /**
     * Assert that the actor may change the target user's system role to
     * the given role (null clears the assignment).
     *
     * Same permission and target protections as lifecycle operations, plus
     * the approved System Admin role restrictions: only Super Admin may
     * grant the built-in SUPER_ADMIN role, and System Admins may never
     * change another System Admin's or a Super Admin's role.
     *
     * @throws AuthorizationException when the actor is not authorized
     */
    public function assertCanChangeRole(User $actor, User $target, ?Role $newRole): void
    {
        if (! $this->permissionChecker->allows($actor, 'users.roles.assign')) {
            throw new AuthorizationException(
                'You are not authorized to assign system roles.'
            );
        }

        if ($actor->is($target)) {
            throw new AuthorizationException(
                'You cannot change your own system role.'
            );
        }

        if ($this->isProtectedTarget($actor, $target)) {
            throw new AuthorizationException(
                'System Admins cannot manage Super Admin or other System Admin accounts.'
            );
        }

        if ($newRole !== null
            && $newRole->scope === 'system'
            && $newRole->code === 'SUPER_ADMIN'
            && ! $this->permissionChecker->isSuperAdmin($actor)
        ) {
            throw new AuthorizationException(
                'Only Super Admin may assign the Super Admin role.'
            );
        }
    }

    /**
     * @throws AuthorizationException when the actor is not authorized
     */
    private function assertAuthorized(User $actor, User $target): void
    {
        if (! $this->permissionChecker->allows($actor, 'users.deactivate')) {
            throw new AuthorizationException(
                'You are not authorized to manage user account lifecycle operations.'
            );
        }

        if ($actor->is($target)) {
            throw new AuthorizationException(
                'You cannot deactivate or reactivate your own account.'
            );
        }

        if ($this->isProtectedTarget($actor, $target)) {
            throw new AuthorizationException(
                'System Admins cannot manage Super Admin or other System Admin accounts.'
            );
        }
    }

    private function isProtectedTarget(User $actor, User $target): bool
    {
        if ($this->permissionChecker->isSuperAdmin($actor)) {
            return false;
        }

        $role = $target->role;

        return $role !== null
            && $role->scope === 'system'
            && in_array($role->code, self::PROTECTED_TARGET_ROLE_CODES, true);
    }
}
