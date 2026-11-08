<?php

namespace App\Actions;

use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Users\LifecycleConnectionGuard;
use App\Services\Users\UserLifecycleAuthorizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Reactivate a soft-deleted user account as one transactionally atomic
 * lifecycle change.
 *
 * The lifecycle, session, and Passport connections must resolve to the same
 * database connection (checked before the transaction), then, inside one
 * transaction owned by this action:
 *
 * 1. The canonical SUPER_ADMIN system role row is locked with
 *    `lockForUpdate()` using the same serialization-root protocol as
 *    `DeactivateUserAction`: reactivation changes the number of active
 *    Super Admin accounts (it increases that count), so every operation
 *    changing that state must serialize on this same row lock.
 * 2. The target (including soft-deleted rows) and the actor are reloaded
 *    from the database under the lock, so state loaded before the
 *    transaction can never drive the checks.
 * 3. Authorization and target eligibility are checked against the reloaded
 *    models; a target that is no longer soft-deleted is rejected, which
 *    prevents a duplicate `user.reactivated` audit event.
 * 4. The account is restored, keeping its existing `account_status` (an
 *    invited account is never activated automatically), and
 *    `user.reactivated` is audited.
 *
 * No last-active-Super-Admin restriction applies to reactivation — it can
 * only increase the active count — and previously revoked OAuth credentials
 * are never restored, old database sessions are never restored, and no
 * notification or email behavior is triggered.
 */
class ReactivateUserAction
{
    public function __construct(
        private readonly LifecycleConnectionGuard $connectionGuard,
        private readonly UserLifecycleAuthorizer $authorizer,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @throws ValidationException when the target is not deactivated
     * @throws AuthorizationException when the actor is not authorized
     * @throws RuntimeException when the connections are inconsistent, when the
     *                          canonical SUPER_ADMIN system role row is missing
     */
    public function execute(User $actor, User $target): void
    {
        $this->connectionGuard->assertConsistentConnections();

        DB::transaction(function () use ($actor, $target): void {
            // Serialization root shared with DeactivateUserAction. A
            // concurrent lifecycle transaction changing the number of active
            // Super Admin accounts blocks here until the in-flight one
            // commits or rolls back.
            $canonicalRole = Role::query()
                ->where('code', 'SUPER_ADMIN')
                ->where('scope', 'system')
                ->lockForUpdate()
                ->first();

            if ($canonicalRole === null) {
                throw new RuntimeException(
                    'The canonical SUPER_ADMIN system role row is missing, so the last active Super Admin protection cannot be enforced. Run RoleSeeder before performing user lifecycle changes.'
                );
            }

            // The participants may have been loaded before the transaction.
            // Reload them while holding the role-row lock — the target
            // including soft-deleted rows, the actor only while its account
            // still exists — so every subsequent check uses current database
            // state instead of stale in-memory attributes.
            $target = User::query()->withTrashed()->find($target->getKey());
            $actor = User::query()->find($actor->getKey());

            if ($actor === null) {
                throw new AuthorizationException(
                    'You are not authorized to manage user account lifecycle operations.'
                );
            }

            $this->authorizer->assertCanReactivate($actor, $target);

            if ($target === null || ! $target->trashed()) {
                throw ValidationException::withMessages([
                    'user' => 'Only deactivated accounts can be reactivated.',
                ]);
            }

            $beforeValues = ['deleted_at' => $target->deleted_at?->toDateTimeString()];

            $target->restore();

            $this->auditLogger->log(
                $actor,
                $target,
                'user.reactivated',
                $beforeValues,
                ['account_status' => $target->account_status],
            );
        });
    }
}
