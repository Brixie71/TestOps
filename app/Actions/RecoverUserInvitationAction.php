<?php

namespace App\Actions;

use App\Models\InvitationLinkRequest;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CredentialHasher;
use App\Services\Auth\InvitationRateLimitException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Recover a live invitation with its recovery code.
 *
 * The recovery code is delivered in the original invitation email; when
 * the link expired or was lost, its holder can exchange the code for a
 * fresh link. Failed attempts accumulate on the invitation row; five
 * failures lock recovery for 30 minutes. Every failure answer is the same
 * credential-free neutral message so visitors learn nothing about which
 * addresses hold invitations.
 */
class RecoverUserInvitationAction
{
    /**
     * Failed recovery attempts tolerated before a 30-minute lockout.
     */
    public const MAX_FAILED_ATTEMPTS = 5;

    /**
     * Lockout window after repeated failures.
     */
    public const LOCKOUT_MINUTES = 30;

    private const NEUTRAL_FAILURE =
        'Recovery could not be completed with the provided details. Please check the email address and recovery code.';

    public function __construct(
        private readonly CredentialHasher $hasher,
        private readonly ResendUserInvitationAction $resendAction,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return array{invitation: UserInvitation, linkRequest: InvitationLinkRequest, token: string}
     *
     * @throws InvitationRateLimitException When recovery is locked out.
     * @throws ValidationException With the neutral failure
     *                             message, or with the resend
     *                             rate-limit message.
     */
    public function execute(
        string $email,
        string $recoveryCode,
        ?string $ipAddress = null
    ): array {
        $now = Carbon::now();

        $email = strtolower(trim($email));
        $recoveryCode = strtoupper(trim($recoveryCode));

        $user = User::query()
            ->where('email', $email)
            ->where('account_status', 'invited')
            ->first();

        $invitation = $user?->invitations()->latest('id')->first();

        if ($invitation === null
            || $invitation->isCancelled()
            || $invitation->isAccepted()
            || $invitation->recovery_code_hash === null
        ) {
            throw ValidationException::withMessages([
                'recovery' => self::NEUTRAL_FAILURE,
            ]);
        }

        if ($invitation->recovery_locked_until !== null
            && $invitation->recovery_locked_until->greaterThan($now)
        ) {
            throw new InvitationRateLimitException(
                'Recovery is temporarily locked for this invitation. Please try again later.',
            );
        }

        $codeIsValid = $this->hasher->matches($recoveryCode, (string) $invitation->recovery_code_hash)
            && $invitation->recovery_code_expires_at->greaterThan($now);

        if (! $codeIsValid) {
            $this->recordFailedAttempt($invitation, $now);

            throw ValidationException::withMessages([
                'recovery' => self::NEUTRAL_FAILURE,
            ]);
        }

        // The recovery code is consumed together with the credential
        // rotation, so one code can complete at most one recovery.
        $result = $this->resendAction->execute(
            $invitation,
            $user,
            $ipAddress,
            true,
        );

        // Audit only after the rotation committed: on PostgreSQL a
        // concurrent recovery can lose the rotation race (a second holder
        // rotates first or the cooldown intervenes), and an audit written
        // before the attempt would record a recovery that never happened.
        $this->auditLogger->log(
            $user,
            $user,
            'invitation.recovered',
            null,
            ['invited_email' => $invitation->invited_email],
        );

        return $result;
    }

    /**
     * Count one failed attempt, locking recovery after the fifth.
     *
     * The counter is incremented under the invitation row lock inside its
     * own short transaction so parallel wrong-code requests on PostgreSQL
     * serialize their increments and none is lost.
     */
    private function recordFailedAttempt(UserInvitation $invitation, Carbon $now): void
    {
        DB::transaction(function () use ($invitation, $now): void {
            $locked = UserInvitation::query()
                ->whereKey($invitation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $attempts = $locked->recovery_attempts + 1;

            $isLockout = $attempts >= self::MAX_FAILED_ATTEMPTS;

            $locked->forceFill([
                'recovery_attempts' => $attempts,
                'recovery_locked_until' => $isLockout
                    ? $now->copy()->addMinutes(self::LOCKOUT_MINUTES)
                    : null,
            ])->save();

            if ($isLockout) {
                $this->auditLogger->log(
                    null,
                    $locked->user,
                    'invitation.recovery_locked',
                    null,
                    ['failed_attempts' => $attempts],
                );
            }
        });
    }
}
