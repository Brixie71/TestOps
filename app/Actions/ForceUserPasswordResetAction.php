<?php

namespace App\Actions;

use App\Models\PasswordResetRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\PasswordResetException;
use App\Services\Auth\PasswordResetService;
use App\Services\Notifications\InvitationDeliveryService;
use App\Services\Users\LifecycleConnectionGuard;
use App\Services\Users\UserLifecycleAuthorizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Force a user account to complete a password reset through an email link
 * (§11.2 forced option, §11.3).
 *
 * Inside one transaction: the target is marked `password_reset_required`
 * (blocking logins until completion), a single-use time-limited reset
 * request is issued (superseding any outstanding one), the target's
 * database sessions are invalidated, and the issuance is audited. The reset
 * email sends after the commit through the immutable notification delivery
 * recorder; a transport failure is recorded as a failed delivery and never
 * rolls back the forced-reset state. The raw token is never persisted,
 * logged, or audited, and the new password is chosen later by the user —
 * never by the administrator.
 */
class ForceUserPasswordResetAction
{
    public function __construct(
        private readonly LifecycleConnectionGuard $connectionGuard,
        private readonly UserLifecycleAuthorizer $authorizer,
        private readonly PasswordResetService $passwordResetService,
        private readonly InvitationDeliveryService $deliveryService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @throws ValidationException When the target state is invalid
     * @throws AuthorizationException When the actor is not authorized
     * @throws RuntimeException When the connections are inconsistent
     * @throws PasswordResetException When the issuance
     *                                cooldown or hourly
     *                                limit is exhausted
     */
    public function execute(User $actor, User $target): void
    {
        $this->connectionGuard->assertConsistentConnections();

        $token = DB::transaction(function () use ($actor, $target): string {
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

            /** @var array{request: PasswordResetRequest, token: string} $issued */
            $issued = $this->passwordResetService->issue($target, now());

            $target->forceFill(['password_reset_required' => true])->save();

            $this->auditLogger->log(
                $actor,
                $target,
                'user.password.reset_forced',
                null,
                ['password_reset_required' => true],
                'Forced password reset issued.',
            );

            DB::connection(config('session.connection'))
                ->table(config('session.table'))
                ->where('user_id', $target->getKey())
                ->delete();

            return $issued['token'];
        });

        $this->deliveryService->sendForcedPasswordReset($target, $token);
    }
}
