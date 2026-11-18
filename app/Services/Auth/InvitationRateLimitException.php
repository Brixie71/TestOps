<?php

namespace App\Services\Auth;

use RuntimeException;

/**
 * An invitation delivery that is blocked by the invitation rate limit or
 * the recovery lockout. Messages stay credential-free and are safe to
 * render to administrators.
 */
class InvitationRateLimitException extends RuntimeException
{
    /**
     * Seconds until the next request is allowed (zero for hourly limits).
     */
    public function __construct(
        string $message,
        public readonly int $retryAfterSeconds = 0,
    ) {
        parent::__construct($message);
    }
}
