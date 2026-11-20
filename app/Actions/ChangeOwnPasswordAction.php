<?php

namespace App\Actions;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\PasswordPolicy;
use App\Services\Notifications\AccountSecurityNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Change a signed-in user's own password (§6.5, §11.4).
 *
 * The account holder must confirm their current password and the new
 * password must satisfy the approved password policy (§11.1); it is hashed
 * by the User model's `hashed` cast and is never logged, emailed, or audited
 * in plaintext. The change is audited as `user.password.changed` with no
 * password material of any kind.
 *
 * The current session stays signed in while every other database session
 * belonging to the account is ended. Existing OAuth tokens are intentionally
 * left untouched. After the transaction commits, the mandatory security
 * notice (§14.2) is sent; a delivery failure never rolls back the change
 * (§14.1).
 */
class ChangeOwnPasswordAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AccountSecurityNotificationService $securityNotifications,
    ) {}

    /**
     * @throws ValidationException When the current password, policy, or
     *                             account state is invalid
     */
    public function execute(
        User $user,
        string $currentPassword,
        string $newPassword,
        string $currentSessionId,
    ): void {
        if (! Hash::check($currentPassword, (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Your current password is incorrect.',
            ]);
        }

        (new PasswordPolicy)->validate('new password', $newPassword, function (string $message): void {
            throw ValidationException::withMessages(['password' => $message]);
        });

        DB::transaction(function () use ($user, $newPassword, $currentSessionId): void {
            // The account may have been loaded before the transaction;
            // reload it so the write targets current database state.
            $user = User::query()->find($user->getKey());

            if ($user === null) {
                throw ValidationException::withMessages([
                    'account' => 'This account no longer exists.',
                ]);
            }

            $user->forceFill(['password' => $newPassword])->save();

            $this->auditLogger->log(
                $user,
                $user,
                'user.password.changed',
                null,
                [],
                'Self-service password change.',
            );

            DB::connection(config('session.connection'))
                ->table(config('session.table'))
                ->where('user_id', $user->getKey())
                ->where('id', '!=', $currentSessionId)
                ->delete();
        });

        $this->securityNotifications->sendPasswordChanged($user);
    }
}
