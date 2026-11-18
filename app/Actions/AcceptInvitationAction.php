<?php

namespace App\Actions;

use App\Models\EmailVerificationRequest;
use App\Models\InvitationLinkRequest;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CredentialHasher;
use App\Services\Auth\EmailVerificationService;
use App\Services\Auth\InvitationLinkException;
use App\Services\Notifications\InvitationDeliveryService;
use Illuminate\Support\Facades\DB;

/**
 * Accept an invitation link: set the account password and enter the email
 * verification state.
 *
 * The link token is consumed, the invitation is marked accepted, and a
 * fresh account_activation email-verification request is issued in one
 * transaction; the account stays in the invited status until that request
 * is consumed. The verification email sends after the commit, so a
 * transport failure never rolls back the acceptance.
 */
class AcceptInvitationAction
{
    public function __construct(
        private readonly CredentialHasher $hasher,
        private readonly EmailVerificationService $verificationService,
        private readonly InvitationDeliveryService $deliveryService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return array{user: User, invitation: UserInvitation, verificationRequest: EmailVerificationRequest}
     *
     * @throws InvitationLinkException With a typed reason
     *                                 for every unusable
     *                                 link.
     */
    public function execute(string $rawToken, string $password): array
    {
        /** @var array{user: User, invitation: UserInvitation, verificationRequest: EmailVerificationRequest, verificationToken: string} $accepted */
        $accepted = DB::transaction(function () use ($rawToken, $password): array {
            $linkRequest = InvitationLinkRequest::query()
                ->where('token_hash', $this->hasher->hash($rawToken))
                ->lockForUpdate()
                ->first();

            if ($linkRequest === null) {
                throw InvitationLinkException::notFound();
            }

            $now = now();

            if ($linkRequest->consumed_at !== null) {
                throw InvitationLinkException::consumed();
            }

            if ($linkRequest->expires_at->lessThan($now)) {
                throw InvitationLinkException::expired();
            }

            $invitation = UserInvitation::query()
                ->whereKey($linkRequest->user_invitation_id)
                ->lockForUpdate()
                ->firstOrFail();

            // A superseded generation means a fresh link was issued through
            // a resend or recovery; this older link is dead even before its
            // own expiry.
            if ((int) $linkRequest->credential_generation !== (int) $invitation->credential_generation) {
                throw InvitationLinkException::invalidated();
            }

            if ($invitation->isCancelled()) {
                throw InvitationLinkException::cancelled();
            }

            if ($invitation->isAccepted()) {
                throw InvitationLinkException::alreadyAccepted();
            }

            $invitedUser = $invitation->user()
                ->lockForUpdate()
                ->firstOrFail();

            // The password cast on the User model hashes the plain value.
            $invitedUser->forceFill(['password' => $password])->save();

            $invitation->forceFill([
                'accepted_at' => $now,
            ])->save();

            $linkRequest->forceFill([
                'consumed_at' => $now,
            ])->save();

            $verification = $this->verificationService->issue(
                $invitedUser,
                EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
                (string) $invitation->invited_email,
                $now,
            );

            $this->auditLogger->log(
                $invitedUser,
                $invitedUser,
                'invitation.accepted',
                null,
                [
                    'method' => (string) $invitation->method,
                    'acceptance_flow' => (string) $invitation->acceptance_flow,
                ],
            );

            return [
                'user' => $invitedUser,
                'invitation' => $invitation,
                'verificationRequest' => $verification['request'],
                'verificationToken' => $verification['token'],
            ];
        });

        // The verification email sends after the acceptance commit; a
        // transport failure is recorded as a failed delivery instead of
        // rolling back the set password.
        $this->deliveryService->sendEmailVerification(
            $accepted['verificationRequest'],
            $accepted['verificationToken'],
        );

        return [
            'user' => $accepted['user'],
            'invitation' => $accepted['invitation'],
            'verificationRequest' => $accepted['verificationRequest'],
        ];
    }
}
