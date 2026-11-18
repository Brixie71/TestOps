<?php

namespace App\Services\Auth;

use RuntimeException;

/**
 * A consumption or state-transition error for an email verification
 * request. Messages deliberately stay credential-free and are safe to
 * render to guests.
 */
class EmailVerificationException extends RuntimeException
{
    /**
     * No request matches the supplied token.
     */
    public const REASON_NOT_FOUND = 'not_found';

    /**
     * The request was already consumed.
     */
    public const REASON_ALREADY_CONSUMED = 'already_consumed';

    /**
     * The request passed its expiration time without consumption.
     */
    public const REASON_EXPIRED = 'expired';

    /**
     * The request was revoked or superseded.
     */
    public const REASON_INVALIDATED = 'invalidated';

    /**
     * The request exists but was issued for a different purpose.
     */
    public const REASON_PURPOSE_MISMATCH = 'purpose_mismatch';

    public function __construct(
        public readonly string $reason,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function notFound(): static
    {
        return new static(
            self::REASON_NOT_FOUND,
            'This email verification link is no longer valid.',
        );
    }

    public static function alreadyConsumed(): static
    {
        return new static(
            self::REASON_ALREADY_CONSUMED,
            'This email verification link has already been used.',
        );
    }

    public static function expired(): static
    {
        return new static(
            self::REASON_EXPIRED,
            'This email verification link has expired. Please request a new one.',
        );
    }

    public static function invalidated(): static
    {
        return new static(
            self::REASON_INVALIDATED,
            'This email verification link is no longer valid.',
        );
    }

    public static function purposeMismatch(): static
    {
        return new static(
            self::REASON_PURPOSE_MISMATCH,
            'This email verification link is not valid for this action.',
        );
    }
}
