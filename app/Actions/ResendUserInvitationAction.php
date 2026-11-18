<?php

namespace App\Actions;

use App\Models\InvitationLinkRequest;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CredentialHasher;
use App\Services\Auth\InvitationCredentialGenerator;
use App\Services\Auth\InvitationLinkException;
use App\Services\Auth\InvitationLinkRequestIssuer;
use App\Services\Auth\InvitationRateLimiter;
use App\Services\Auth\InvitationRateLimitException;
use App\Services\Notifications\InvitationDeliveryService;
use App\Services\Settings\AccountLinkExpirationSettings;
use Closure;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Rotate an invitation's credentials and issue a fresh emailed link.
 *
 * Used for resend requests and as the retry closure for failed
 * notification deliveries: every retry creates fresh invitation
 * credentials. The rolling rate limit and the single-transaction rotation
 * guarantee that concurrent resends cannot bypass the 60-second cooldown
 * or the hourly limits.
 */
class ResendUserInvitationAction
{
    public function __construct(
        private readonly InvitationCredentialGenerator $generator,
        private readonly CredentialHasher $hasher,
        private readonly InvitationLinkRequestIssuer $issuer,
        private readonly InvitationRateLimiter $rateLimiter,
        private readonly AccountLinkExpirationSettings $settings,
        private readonly InvitationDeliveryService $deliveryService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Build the manual-retry closure for the notification delivery retry
     * service: every retry rotates the invitation credentials (fresh link)
     * and re-sends through the delivery service.
     *
     * @return Closure(NotificationDelivery): void
     */
    public function retryClosure(User $actor): Closure
    {
        return function (
            NotificationDelivery $delivery
        ) use ($actor): void {
            $failedLinkRequest = InvitationLinkRequest::query()
                ->where('delivery_id', $delivery->getKey())
                ->first();

            if ($failedLinkRequest === null) {
                throw new RuntimeException(
                    'No invitation link request is linked to delivery '
                    ."[$delivery->getKey()].",
                );
            }

            $resent = $this->execute(
                $failedLinkRequest->invitation,
                $actor,
                $failedLinkRequest->ip_address,
            );

            if ($failedLinkRequest->method === UserInvitation::METHOD_DIRECT_ACCOUNT_SETUP) {
                $this->deliveryService->sendDirectAccountSetupLink(
                    $resent['linkRequest'],
                    $resent['token'],
                );

                return;
            }

            $this->deliveryService->sendInvitationLink(
                $resent['linkRequest'],
                $resent['token'],
                '',
            );
        };
    }

    /**
     * @param  bool  $consumeRecoveryCode  When true (invitation recovery),
     *                                     the recovery code is consumed
     *                                     in the same rotation
     *                                     transaction so it cannot be
     *                                     reused after a successful
     *                                     recovery. Administrative
     *                                     resends keep the code because
     *                                     a fresh one is never issued.
     * @return array{invitation: UserInvitation, linkRequest: InvitationLinkRequest, token: string}
     *
     * @throws InvitationLinkException When the invitation is cancelled or
     *                                 already accepted.
     * @throws InvitationRateLimitException When the
     *                                      cooldown or hourly limits are exhausted.
     */
    public function execute(
        UserInvitation $invitation,
        ?User $requestedBy = null,
        ?string $ipAddress = null,
        bool $consumeRecoveryCode = false,
    ): array {
        $now = now();

        $this->rateLimiter->assertRequestAllowed($invitation, $ipAddress, $now);

        $this->assertLive($invitation, 'This invitation can no longer receive a fresh link.');

        $newToken = $this->generator->generateToken();

        /** @var array{invitation: UserInvitation, token: string} $result */
        $result = DB::transaction(function () use (
            $invitation,
            $requestedBy,
            $ipAddress,
            $newToken,
            $now,
            $consumeRecoveryCode
        ): array {
            $locked = UserInvitation::query()
                ->whereKey($invitation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertLive($locked, 'This invitation can no longer receive a fresh link.');

            $previousGeneration = (int) $locked->credential_generation;

            $rotation = [
                'token_hash' => $this->hasher->hash($newToken),
                'expires_at' => $now->copy()->addMinutes(
                    $this->settings->getMinutes(AccountLinkExpirationSettings::KEY_INVITATION_LINK),
                ),
                'credential_generation' => $previousGeneration + 1,
                'recovery_attempts' => 0,
            ];

            if ($consumeRecoveryCode) {
                // Single-use: a consumed recovery code can never
                // authenticate another recovery request.
                $rotation['recovery_code_hash'] = null;
                $rotation['recovery_code_expires_at'] = null;
            }

            $locked->forceFill($rotation)->save();

            $issuedLink = $this->issuer->issue($locked, $requestedBy, $ipAddress, $now);

            $this->auditLogger->log(
                $requestedBy,
                $locked->user,
                'invitation.resent',
                ['credential_generation' => $previousGeneration],
                ['credential_generation' => (int) $locked->credential_generation],
            );

            return [
                'invitation' => $locked,
                'token' => $issuedLink['token'],
                'linkRequest' => $issuedLink['linkRequest'],
            ];
        });

        return $result;
    }

    private function assertLive(UserInvitation $invitation, string $message): void
    {
        if ($invitation->isCancelled()) {
            throw InvitationLinkException::cancelled();
        }

        if ($invitation->isAccepted()) {
            throw InvitationLinkException::alreadyAccepted();
        }
    }
}
