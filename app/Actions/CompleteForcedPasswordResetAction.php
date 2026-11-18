<?php

namespace App\Actions;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\PasswordPolicy;
use App\Services\Auth\PasswordResetException;
use App\Services\Auth\PasswordResetService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Complete a forced password reset (§11.3): the account holder sets a new
 * password through the single-use emailed link.
 *
 * The password policy is checked first — a failed attempt records against
 * the token (five failures disable it) without consuming it — then the
 * consumption and the account update commit atomically: the token is
 * consumed, the password is replaced, and the `password_reset_required`
 * block is cleared in the same transaction. Sessions were already
 * invalidated at issuance.
 */
class CompleteForcedPasswordResetAction
{
    public function __construct(
        private readonly PasswordResetService $passwordResetService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @throws ValidationException When the new password fails the policy
     * @throws PasswordResetException For every unusable or exhausted token
     */
    public function execute(string $rawToken, string $newPassword): User
    {
        try {
            (new PasswordPolicy)->validate('new password', $newPassword, function (string $message): void {
                throw ValidationException::withMessages(['password' => $message]);
            });

            /** @var User $user */
            $user = DB::transaction(function () use ($rawToken, $newPassword) {
                $request = $this->passwordResetService->consume($rawToken);

                $user = User::query()
                    ->whereKey($request->user_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // The password cast on the User model hashes the plain value.
                $user->forceFill([
                    'password' => $newPassword,
                    'password_reset_required' => false,
                ])->save();

                $this->auditLogger->log(
                    $user,
                    $user,
                    'user.password.reset_completed',
                    null,
                    [],
                    'Forced password reset completed.',
                );

                return $user;
            });

            return $user->refresh();
        } catch (PasswordResetException $exception) {
            // The transaction rolled back, which would also have undone the
            // expiry reclassification consume() performed; persist it again
            // outside the transaction so the state change survives.
            if ($exception->getMessage() === PasswordResetException::expired()->getMessage()) {
                $this->passwordResetService->markExpiredByToken($rawToken);
            }

            throw $exception;
        }
    }
}
