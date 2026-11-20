<?php

namespace App\Actions;

use App\Models\Role;
use App\Models\User;
use App\Models\UserEmailAddress;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\RevokesUserCredentials;
use App\Services\Users\LifecycleConnectionGuard;
use App\Services\Users\UserDeletionDependencyInspector;
use App\Services\Users\UserLifecycleAuthorizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Permanently delete a previously deactivated account as one atomically
 * committed operation (§7.3).
 *
 * The lifecycle, session, and Passport connections must resolve to the same
 * connection (checked before the transaction), then, inside one transaction:
 *
 * 1. The canonical SUPER_ADMIN system role row is locked as the shared
 *    serialization root for every lifecycle operation (defence in depth; a
 *    deactivated account cannot be the last active Super Admin).
 * 2. The actor, the target (including soft-deleted rows), and the blocking
 *    dependencies are reloaded/checked under that lock, so a stale in-memory
 *    target can never be deleted.
 * 3. Remaining OAuth credentials are revoked and the target's database
 *    sessions are purged.
 * 4. Every email-address history row is removed, releasing the current and
 *    previously verified addresses for reuse (§9.3). Rows owned by other
 *    tables cascade at the database level; independent audit history keeps
 *    its identity snapshots because it stores no foreign key to `users`.
 * 5. The user row is force-deleted and `user.permanently_deleted` is audited.
 *
 * The approved specification requires project ownership to be resolved first
 * because `projects.owner_id` restricts deletion; the dependency inspector
 * enforces that and the foreign key remains the final integrity guard.
 */
class PermanentlyDeleteUserAction
{
    /**
     * Maximum length of a deletion reason, shared with HTTP validation.
     */
    private const MAX_REASON_LENGTH = 500;

    public function __construct(
        private readonly LifecycleConnectionGuard $connectionGuard,
        private readonly UserLifecycleAuthorizer $authorizer,
        private readonly UserDeletionDependencyInspector $dependencyInspector,
        private readonly RevokesUserCredentials $revokesUserCredentials,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @throws ValidationException when the password, reason, target state, or
     *                             blocking dependencies refuse deletion
     * @throws AuthorizationException when the actor is not authorized
     * @throws RuntimeException when the connections are inconsistent or the
     *                          canonical SUPER_ADMIN role row is missing
     */
    public function execute(
        User $actor,
        User $target,
        string $currentPassword,
        string $reason,
    ): void {
        $this->connectionGuard->assertConsistentConnections();

        if (! Hash::check($currentPassword, (string) $actor->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Your current password is incorrect.',
            ]);
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A permanent deletion reason is required.',
            ]);
        }

        if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            throw ValidationException::withMessages([
                'reason' => 'The permanent deletion reason may not exceed 500 characters.',
            ]);
        }

        DB::transaction(function () use ($actor, $target, $reason): void {
            $canonicalRole = Role::query()
                ->where('code', 'SUPER_ADMIN')
                ->where('scope', 'system')
                ->lockForUpdate()
                ->first();

            if ($canonicalRole === null) {
                throw new RuntimeException(
                    'The canonical SUPER_ADMIN system role row is missing, so the account lifecycle coordination lock cannot be acquired. Run RoleSeeder before performing user lifecycle changes.'
                );
            }

            $target = User::query()->withTrashed()->lockForUpdate()->find($target->getKey());
            $actor = User::query()->find($actor->getKey());

            if ($actor === null) {
                throw new AuthorizationException(
                    'You are not authorized to permanently delete accounts.'
                );
            }

            if ($target === null) {
                throw ValidationException::withMessages([
                    'delete' => 'This account no longer exists.',
                ]);
            }

            $this->authorizer->assertCanPermanentlyDelete($actor, $target);

            if (! $target->trashed()) {
                throw ValidationException::withMessages([
                    'delete' => 'Deactivate this account before permanently deleting it.',
                ]);
            }

            $this->assertNoBlockingDependencies($target);

            $deletedAddresses = UserEmailAddress::query()
                ->where('user_id', $target->getKey())
                ->count();

            // Snapshot the identity for the audit record before the row is
            // gone; AuditLogger already stores names and emails independently.
            $identity = [
                'user_id' => $target->getKey(),
                'name' => $target->name,
                'email' => $target->email,
                'email_addresses_released' => $deletedAddresses,
            ];

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

            UserEmailAddress::query()
                ->where('user_id', $target->getKey())
                ->delete();

            try {
                $target->forceDelete();
            } catch (QueryException $exception) {
                // The foreign key is the final guard against an ownership
                // race; surface it as an actionable validation error.
                throw ValidationException::withMessages([
                    'delete' => 'This account still has dependent records that block permanent deletion. Resolve them and try again.',
                ]);
            }

            $this->auditLogger->log(
                $actor,
                $target,
                'user.permanently_deleted',
                $identity,
                null,
                $reason,
            );
        });
    }

    /**
     * Refuse deletion while a restrictive dependency row exists (§7.3).
     */
    private function assertNoBlockingDependencies(User $target): void
    {
        $dependencies = $this->dependencyInspector->blockingDependencies($target);

        if ($dependencies === []) {
            return;
        }

        $summary = collect($dependencies)
            ->map(fn (array $dependency): string => sprintf(
                '%s (%d): %s',
                $dependency['label'],
                $dependency['count'],
                $dependency['resolution'],
            ))
            ->implode(' ');

        throw ValidationException::withMessages([
            'delete' => 'This account still has dependent records: '.$summary,
        ]);
    }
}
