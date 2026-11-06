<?php

namespace App\Services\Notifications;

use RuntimeException;

/**
 * Rejected manual notification retry attempts.
 *
 * Future HTTP callers map these to the appropriate status codes; this
 * milestone exposes no route.
 */
class NotificationRetryException extends RuntimeException
{
    public static function unauthorized(): self
    {
        return new self('You are not authorized to retry notification deliveries.');
    }

    public static function deliveryNotFound(): self
    {
        return new self('The notification delivery record was not found.');
    }

    public static function deliveryIsNotFailed(): self
    {
        return new self('Only failed notification deliveries can be retried.');
    }

    public static function latestRetryAlreadyDelivered(): self
    {
        return new self('The notification was already delivered by a retry attempt.');
    }

    public static function retryLimitReached(int $maxRetries): self
    {
        return new self(
            "The maximum of {$maxRetries} manual retries has been reached. Manual intervention is required.",
        );
    }

    public static function cooldownActive(int $secondsRemaining): self
    {
        return new self(
            "A manual retry is not allowed for another {$secondsRemaining} seconds.",
        );
    }

    public static function attemptConflict(): self
    {
        return new self('The retry attempt conflicts with a concurrent attempt. Please try again.');
    }
}
