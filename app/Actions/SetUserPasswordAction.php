<?php

namespace App\Actions;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\PasswordPolicy;
use App\Services\Users\LifecycleConnectionGuard;
use App\Services\Users\UserLifecycleAuthorizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Administratively set a user account's password (§11.2, manual option).
 *
 * The acting administrator must confirm their own current password. The new
 * password must satisfy the approved password policy (§11.1) and is hashed
 * by the User model's `hashed` cast — never logged, emailed, or audited in
 * plaintext. The target's database sessions are invalidated inside the same
 * transaction, and the change is audited as `user.password.set` with no
 * password material of any kind.
 */
class SetUserPasswordAction
{
    public function __construct(
        private readonly LifecycleConnectionGuard $connectionGuard,
        private readonly UserLifecycleAuthorizer $authorizer,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @throws ValidationException When the confirmation, policy, or target
     *                             state is invalid
     * @throws AuthorizationException When the actor is not authorized
     * @throws RuntimeException When the connections are inconsistent
     */
    public function execute(
        User $actor,
        User $target,
        string $actorCurrentPassword,
        string $newPassword,
    ): void {
        $this->connectionGuard->assertConsistentConnections();

        if (! Hash::check($actorCurrentPassword, (string) $actor->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Your current password is incorrect.',
            ]);
        }

        (new PasswordPolicy)->validate('new password', $newPassword, function (string $message): void {
            throw ValidationException::withMessages(['password' => $message]);
        });

        DB::transaction(function () use ($actor, $target, $newPassword): void {
            // The participants may have been loaded before the transaction;
            // reload them so the checks use current database state.
            $target = User::query()->withTrashed()->find($target->getKey());
            $actor = User::query()->find($actor->getKey());

            if ($actor === null) {
                throw new AuthorizationException(
                    'You are not authorized to manage user passwords.'
                );
            }

            if ($target === null) {
                throw ValidationException::withMessages([
                    'user' => 'This account no longer exists.',
                ]);
            }

            $this->authorizer->assertCanManagePassword($actor, $target);

            if ($target->trashed()) {
                throw ValidationException::withMessages([
                    'user' => 'Reactivate this account before managing its password.',
                ]);
            }

            $target->forceFill(['password' => $newPassword])->save();

            $this->auditLogger->log(
                $actor,
                $target,
                'user.password.set',
                null,
                [],
                'Administrative password reset.',
            );

            DB::connection(config('session.connection'))
                ->table(config('session.table'))
                ->where('user_id', $target->getKey())
                ->delete();
        });
    }
}
