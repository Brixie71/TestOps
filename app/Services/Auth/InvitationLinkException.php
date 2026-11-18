<?php

namespace App\Services\Auth;

use RuntimeException;

/**
 * An acceptance or state error for an invitation link. Messages stay
 * credential-free and are safe to render to guests and administrators.
 */
class InvitationLinkException extends RuntimeException
{
    public const REASON_NOT_FOUND = 'not_found';

    public const REASON_CONSUMED = 'consumed';

    public const REASON_EXPIRED = 'expired';

    public const REASON_CANCELLED = 'cancelled';

    public const REASON_ALREADY_ACCEPTED = 'already_accepted';

    /**
     * The link belongs to a superseded credential generation; a fresh link
     * was issued through a resend or recovery and this one is dead.
     */
    public const REASON_INVALIDATED = 'invalidated';

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
            'This invitation link is no longer valid.',
        );
    }

    public static function consumed(): static
    {
        return new static(
            self::REASON_CONSUMED,
            'This invitation link has already been used.',
        );
    }

    public static function expired(): static
    {
        return new static(
            self::REASON_EXPIRED,
            'This invitation link has expired. Please request a new one.',
        );
    }

    public static function cancelled(): static
    {
        return new static(
            self::REASON_CANCELLED,
            'This invitation was cancelled. Please contact an administrator.',
        );
    }

    public static function alreadyAccepted(): static
    {
        return new static(
            self::REASON_ALREADY_ACCEPTED,
            'This invitation has already been accepted.',
        );
    }

    public static function invalidated(): static
    {
        return new static(
            self::REASON_INVALIDATED,
            'This invitation link is no longer valid. Please use the most recent link that was emailed to you.',
        );
    }
}
