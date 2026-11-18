<?php

namespace App\Services\Auth;

use App\Models\InvitationLinkRequest;
use App\Models\User;
use App\Models\UserInvitation;
use Carbon\CarbonInterface;

/**
 * Creates one invitation link request row for an invitation and keeps the
 * invitation's sent bookkeeping consistent.
 *
 * The caller owns the rate-limit decision and the transaction; concatenating
 * rows under a caller transaction keeps sent-count and link-request history
 * atomic with credential rotation.
 */
class InvitationLinkRequestIssuer
{
    public function __construct(
        private readonly CredentialHasher $hasher,
        private readonly InvitationCredentialGenerator $generator,
    ) {}

    /**
     * Record one issued link for the invitation's current generation.
     *
     * @return array{linkRequest: InvitationLinkRequest, token: string}
     */
    public function issue(
        UserInvitation $invitation,
        ?User $requestedBy,
        ?string $ipAddress,
        ?CarbonInterface $now = null,
    ): array {
        $now ??= now();

        $token = $this->generator->generateToken();

        $linkRequest = InvitationLinkRequest::query()->create([
            'user_invitation_id' => $invitation->getKey(),
            'recipient_email' => (string) $invitation->invited_email,
            'credential_generation' => $invitation->credential_generation,
            'method' => (string) $invitation->method,
            'token_hash' => $this->hasher->hash($token),
            'expires_at' => $invitation->expires_at,
            'requested_by' => $requestedBy?->getKey(),
            'ip_address' => $ipAddress,
        ]);

        $invitation->forceFill([
            'sent_count' => $invitation->sent_count + 1,
            'last_sent_at' => $now,
        ])->save();

        return ['linkRequest' => $linkRequest, 'token' => $token];
    }
}
