<?php

namespace App\Actions;

use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\AccountSecurityNotificationService;
use App\Services\Users\UserLifecycleAuthorizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Assign (or clear) a user account's system role as one transactionally
 * atomic account-management change.
 *
 * The same serialization rules as the lifecycle actions apply: inside one
 * transaction the canonical SUPER_ADMIN system role row is locked with
 * `lockForUpdate()` — a role change can reduce the number of active Super
 * Admin accounts — and the participants are reloaded under that lock so
 * every authorization and eligibility check observes committed concurrent
 * lifecycle changes. Authorization re-checks last-active-Super-Admin
 * protection while the lock is held when the target currently holds
 * SUPER_ADMIN.
 *
 * Only existing system-scoped roles are eligible (§6.1); project-scoped
 * roles are never assignable as system roles. Deactivated accounts must be
 * reactivated before their role can change. The change is audited as
 * `user.role_changed` with the before/after role snapshots. After the
 * transaction commits, the mandatory role-change notification (§14.2) is
 * sent; a delivery failure is recorded and never rolls back the change
 * (§14.1).
 */
class AssignUserRoleAction
{
    public function __construct(
        private readonly UserLifecycleAuthorizer $authorizer,
        private readonly AuditLogger $auditLogger,
        private readonly AccountSecurityNotificationService $securityNotifications,
    ) {}

    /**
     * @param  int|null  $roleId  Existing system-scoped role id, or null to
     *                            clear the system role assignment.
     *
     * @throws ValidationException when the target, role, or state is invalid
     * @throws AuthorizationException when the actor is not authorized
     * @throws RuntimeException when the canonical SUPER_ADMIN role row is missing
     */
    public function execute(User $actor, User $target, ?int $roleId): void
    {
        $newRole = null;

        if ($roleId !== null) {
            $newRole = Role::query()->find($roleId);
        }

        if ($roleId !== null && $newRole === null) {
            throw ValidationException::withMessages([
                'role_id' => 'The selected system role does not exist.',
            ]);
        }

        if ($newRole !== null && $newRole->scope !== 'system') {
            throw ValidationException::withMessages([
                'role_id' => 'Only system-scoped roles can be assigned to accounts.',
            ]);
        }

        $beforeRole = $target->role;

        DB::transaction(function () use ($actor, $target, $newRole, $beforeRole): void {
            // Serialization root for the last-active-SUPER_ADMIN safeguard
            // (same row lock as deactivation: role changes can reduce the
            // number of active Super Admin accounts).
            $canonicalRole = Role::query()
                ->where('code', 'SUPER_ADMIN')
                ->where('scope', 'system')
                ->lockForUpdate()
                ->first();

            if ($canonicalRole === null) {
                throw new RuntimeException(
                    'The canonical SUPER_ADMIN system role row is missing, so the last active Super Admin protection cannot be enforced. Run RoleSeeder before performing user management changes.'
                );
            }

            // Reload the participants under the lock: the target including
            // soft-deleted rows, the actor only while its account exists.
            $target = User::query()->withTrashed()->find($target->getKey());
            $actor = User::query()->find($actor->getKey());

            if ($actor === null) {
                throw new AuthorizationException(
                    'You are not authorized to assign system roles.'
                );
            }

            if ($target === null) {
                throw ValidationException::withMessages([
                    'user' => 'This account no longer exists.',
                ]);
            }

            $this->authorizer->assertCanChangeRole($actor, $target, $newRole);

            if ($target->trashed()) {
                throw ValidationException::withMessages([
                    'user' => 'Reactivate this account before changing its system role.',
                ]);
            }

            if ((int) $target->role_id === ($newRole?->getKey())) {
                throw ValidationException::withMessages([
                    'role_id' => 'This account already has that system role.',
                ]);
            }

            if ($beforeRole !== null
                && $beforeRole->code === 'SUPER_ADMIN'
                && $beforeRole->scope === 'system'
            ) {
                // Recheck while holding the role-row lock: demoting the
                // last active Super Admin must fail closed.
                $this->authorizer->assertLastActiveSuperAdminProtected($target);
            }

            $afterRole = $newRole !== null
                ? Role::query()->find($newRole->getKey())
                : null;

            $target->forceFill(['role_id' => $newRole?->getKey()])->save();

            $this->auditLogger->log(
                $actor,
                $target,
                'user.role_changed',
                [
                    'role_id' => $beforeRole?->getKey(),
                    'role_code' => $beforeRole?->code,
                    'role_name' => $beforeRole?->name,
                ],
                [
                    'role_id' => $afterRole?->getKey(),
                    'role_code' => $afterRole?->code,
                    'role_name' => $afterRole?->name,
                ],
            );
        });

        $this->securityNotifications->sendSystemRoleChanged(
            $target,
            $beforeRole?->name,
            $newRole?->name,
        );
    }
}
