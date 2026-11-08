<?php

namespace App\Actions;

use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\RevokesUserCredentials;
use App\Services\Users\LifecycleConnectionGuard;
use App\Services\Users\UserLifecycleAuthorizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Deactivate a user account as one transactionally atomic lifecycle change.
 *
 * The lifecycle, session, and Passport connections must resolve to the same
 * database connection (checked before the transaction), then, inside one
 * transaction owned by this action:
 *
 * 1. The canonical SUPER_ADMIN system role row is locked with
 *    `lockForUpdate()`. This row is the serialization root for the
 *    last-active-Super-Admin safeguard: EVERY future operation that can
 *    reduce the number of active Super Admin accounts (deactivation,
 *    system-role changes, permanent deletion, bulk operations) must acquire
 *    this same row lock before changing that state, and never rely on a
 *    count performed before its own transaction.
 * 2. Authorization, target eligibility, and the last-active-SUPER_ADMIN
 *    condition are (re)checked while the role-row lock is held, against
 *    database state reloaded under the lock, so a committed concurrent
 *    lifecycle change is observed before acting.
 * 3. The target user is soft-deleted (deactivation is never a new
 *    `account_status` value).
 * 4. `user.deactivated` is audited with the reason and before/after values.
 * 5. Every OAuth access token, refresh token, and authorization code of the
 *    target user is revoked through `RevokesUserCredentials`; the aggregate
 *    counts (never token identifiers or secrets) are audited as
 *    `oauth.credentials.revoked`.
 * 6. Only the target user's database sessions are purged through the
 *    configured session connection and table.
 *
 * Pending email-change cancellation and deactivation notifications are
 * intentionally deferred: no email-change module or user-notification
 * workflow exists yet (see the deferred notes in the User Management
 * specification). Email-address history and reservation records are
 * preserved.
 */
class DeactivateUserAction
{
    /**
     * Maximum length of a deactivation reason, shared with HTTP validation.
     */
    private const MAX_REASON_LENGTH = 500;

    public function __construct(
        private readonly LifecycleConnectionGuard $connectionGuard,
        private readonly UserLifecycleAuthorizer $authorizer,
        private readonly RevokesUserCredentials $revokesUserCredentials,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @throws ValidationException when the reason or target is invalid
     * @throws AuthorizationException when the actor is not authorized
     * @throws RuntimeException when the connections are inconsistent, when the
     *                          canonical SUPER_ADMIN system role row is missing
     */
    public function execute(User $actor, User $target, string $reason): void
    {
        $this->connectionGuard->assertConsistentConnections();

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A deactivation reason is required.',
            ]);
        }

        if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            throw ValidationException::withMessages([
                'reason' => 'The deactivation reason may not exceed 500 characters.',
            ]);
        }

        $beforeValues = ['account_status' => $target->account_status];

        DB::transaction(function () use ($actor, $target, $reason, $beforeValues): void {
            // Serialization root for the last-active-SUPER-ADMIN safeguard.
            // A concurrent transaction intending to reduce the number of
            // active Super Admin accounts blocks here until the in-flight
            // one commits or rolls back.
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

            if ($target === null) {
                throw ValidationException::withMessages([
                    'user' => 'This account is already deactivated.',
                ]);
            }

            $this->authorizer->assertCanDeactivate($actor, $target);

            if ($target->trashed()) {
                throw ValidationException::withMessages([
                    'user' => 'This account is already deactivated.',
                ]);
            }

            // Recheck while holding the role-row lock: the count now observes
            // every committed concurrent lifecycle change.
            $this->authorizer->assertLastActiveSuperAdminProtected($target);

            $target->delete();

            $this->auditLogger->log(
                $actor,
                $target,
                'user.deactivated',
                $beforeValues,
                ['deleted_at' => $target->deleted_at?->toDateTimeString()],
                $reason,
            );

            $revocationCounts = $this->revokesUserCredentials->revokeForUser($target->getKey());

            $this->auditLogger->log(
                $actor,
                $target,
                'oauth.credentials.revoked',
                null,
                $revocationCounts,
                $reason,
            );

            DB::connection(config('session.connection'))
                ->table(config('session.table'))
                ->where('user_id', $target->getKey())
                ->delete();
        });
    }
}
