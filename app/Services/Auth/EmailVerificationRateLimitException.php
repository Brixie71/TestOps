<?php

namespace App\Services\Auth;

use RuntimeException;

/**
 * A verification-email issuance refused by the approved security limits
 * (§10.3-§10.7). Messages are credential-free and safe to render.
 */
class EmailVerificationRateLimitException extends RuntimeException
{
    /**
     * A replacement was requested inside the 60-second resend cooldown.
     */
    public const REASON_RESEND_COOLDOWN = 'resend_cooldown';

    /**
     * The account hit the five-per-rolling-hour issuance limit.
     */
    public const REASON_ACCOUNT_LIMIT = 'account_limit';

    /**
     * The requesting network hit the configurable per-IP hourly limit.
     */
    public const REASON_IP_LIMIT = 'ip_limit';

    /**
     * The account is inside its post-failed-attempt lockout window.
     */
    public const REASON_LOCKED = 'locked';

    public function __construct(
        public readonly string $reason,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function resendCooldown(int $seconds): static
    {
        return new static(
            self::REASON_RESEND_COOLDOWN,
            "Please wait {$seconds} seconds before requesting another verification email.",
        );
    }

    public static function accountLimit(): static
    {
        return new static(
            self::REASON_ACCOUNT_LIMIT,
            'Too many verification emails were requested for this account. Try again later.',
        );
    }

    public static function ipLimit(): static
    {
        return new static(
            self::REASON_IP_LIMIT,
            'Too many verification emails were requested from this network. Try again later.',
        );
    }

    public static function locked(): static
    {
        return new static(
            self::REASON_LOCKED,
            'Verification emails are temporarily blocked for this account after repeated failed attempts. Try again later.',
        );
    }
}
