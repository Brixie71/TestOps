<?php

namespace App\Actions;

use App\Models\User;
use App\Models\UserEmailAddress;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\EmailVerificationService;
use App\Services\Notifications\InvitationDeliveryService;
use App\Services\Users\UserLifecycleAuthorizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Cancel a pending self-service email change (§9.6).
 *
 * The account holder cancels their own pending request after confirming
 * their current password; authorized administrators cancel another account's
 * request with a recorded reason, subject to the same protected-target rules
 * as the other administrative actions. Cancellation releases the pending
 * address, invalidates every outstanding verification token, and preserves
 * the current verified email. Administrative cancellations are notified to
 * the current verified address after the commit (§14.5) and never roll back
 * on a delivery failure.
 */
class CancelEmailChangeAction
{
    public function __construct(
        private readonly EmailVerificationService $verificationService,
        private readonly InvitationDeliveryService $deliveryService,
        private readonly UserLifecycleAuthorizer $authorizer,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @throws ValidationException When the confirmation password is wrong or
     *                             no pending change exists
     * @throws AuthorizationException When an administrator is not authorized
     */
    public function execute(
        User $actor,
        User $target,
        ?string $currentPassword = null,
        ?string $reason = null,
    ): void {
        $selfCancellation = $actor->is($target) && $currentPassword !== null;

        if ($selfCancellation) {
            if (! Hash::check((string) $currentPassword, (string) $actor->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'Your current password is incorrect.',
                ]);
            }
        } else {
            $this->authorizer->assertCanManageEmailChange($actor, $target);
        }

        /** @var User $user */
        $user = DB::transaction(function () use ($actor, $target, $selfCancellation, $reason): User {
            $target = User::query()->withTrashed()->lockForUpdate()->find($target->getKey());

            if ($target === null) {
                throw ValidationException::withMessages([
                    'user' => 'This account no longer exists.',
                ]);
            }

            $pending = UserEmailAddress::query()
                ->where('user_id', $target->getKey())
                ->where('status', UserEmailAddress::STATUS_PENDING)
                ->lockForUpdate()
                ->get();

            if ($pending->isEmpty()) {
                throw ValidationException::withMessages([
                    'email' => 'This account has no pending email change to cancel.',
                ]);
            }

            foreach ($pending as $address) {
                $address->delete();
            }

            $this->verificationService->invalidateOutstanding(
                $target,
                EmailVerificationService::PURPOSE_EMAIL_CHANGE,
            );

            $this->auditLogger->log(
                $selfCancellation ? $target : $actor,
                $target,
                $selfCancellation
                    ? 'user.email.change_cancelled'
                    : 'user.email.change_cancelled_by_admin',
                null,
                ['cancelled_email' => $pending->first()->email],
                $selfCancellation ? null : $reason,
            );

            return $target;
        });

        if (! $selfCancellation) {
            // §14.5: notify the current verified address; a delivery failure
            // is recorded and never rolls back the cancellation.
            $this->deliveryService->sendEmailChangeCancellation($user);
        }
    }
}
