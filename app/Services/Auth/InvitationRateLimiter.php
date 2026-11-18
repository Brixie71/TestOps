<?php

namespace App\Services\Auth;

use App\Models\InvitationLinkRequest;
use App\Models\UserInvitation;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Rolling-window rate limit over invitation link requests.
 *
 * Phase 1 limits are fixed class constants: at most one request per
 * invitation every 60 seconds, five requests per invitation per hour, and
 * ten requests per source IP per hour. Making these configurable through
 * the settings interface is deferred to the settings-UI phase.
 *
 * The rolling window is measured from the persisted invitation_link_requests
 * rows, so restarts and multiple application nodes stay consistent.
 */
class InvitationRateLimiter
{
    /**
     * Minimum spacing between two requests for the same invitation.
     */
    public const COOLDOWN_SECONDS = 60;

    /**
     * Maximum link requests within the rolling hour, per invitation.
     */
    public const MAX_REQUESTS_PER_INVITATION = 5;

    /**
     * Maximum link requests within the rolling hour, per source IP.
     */
    public const MAX_REQUESTS_PER_IP = 10;

    /**
     * Length of the rolling window.
     */
    public const WINDOW_MINUTES = 60;

    /**
     * Assert that a fresh link request is allowed right now.
     *
     * @param  CarbonInterface|null  $now  Injectable for tests.
     *
     * @throws InvitationRateLimitException When the cooldown or either
     *                                      rolling limit is exhausted.
     */
    public function assertRequestAllowed(
        UserInvitation $invitation,
        ?string $ipAddress,
        ?CarbonInterface $now = null,
    ): void {
        $now ??= Carbon::now();

        $windowStart = $now->copy()->subMinutes(self::WINDOW_MINUTES);

        $lastRequest = $invitation->linkRequests()
            ->latest('id')
            ->first(['created_at']);

        if ($lastRequest !== null
            && $lastRequest->created_at->greaterThan(
                $now->copy()->subSeconds(self::COOLDOWN_SECONDS)
            )
        ) {
            throw new InvitationRateLimitException(
                'A fresh invitation link was just sent. Please wait a minute before sending another one.',
                self::COOLDOWN_SECONDS,
            );
        }

        $invitationCount = $invitation->linkRequests()
            ->where('created_at', '>=', $windowStart)
            ->count();

        if ($invitationCount >= self::MAX_REQUESTS_PER_INVITATION) {
            throw new InvitationRateLimitException(
                'Too many invitation links were requested for this account within the last hour.',
            );
        }

        if ($ipAddress !== null) {
            $this->assertIpWithinWindow($ipAddress, $windowStart);
        }
    }

    /**
     * Assert that a brand-new invitation may be issued from this source
     * IP; only the per-IP rolling window applies to brand-new invitations.
     *
     * @throws InvitationRateLimitException When the per-IP limit is
     *                                      exhausted.
     */
    public function assertNewInvitationAllowed(
        ?string $ipAddress,
        ?CarbonInterface $now = null
    ): void {
        if ($ipAddress === null) {
            return;
        }

        $now ??= Carbon::now();

        $this->assertIpWithinWindow(
            $ipAddress,
            $now->copy()->subMinutes(self::WINDOW_MINUTES),
        );
    }

    private function assertIpWithinWindow(string $ipAddress, CarbonInterface $windowStart): void
    {
        $ipCount = InvitationLinkRequest::query()
            ->where('ip_address', $ipAddress)
            ->where('created_at', '>=', $windowStart)
            ->count();

        if ($ipCount >= self::MAX_REQUESTS_PER_IP) {
            throw new InvitationRateLimitException(
                'Too many invitation links were requested from this IP address within the last hour.',
            );
        }
    }
}
