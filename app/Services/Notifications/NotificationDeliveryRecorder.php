<?php

namespace App\Services\Notifications;

use App\Models\NotificationDelivery;
use App\Models\User;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

/**
 * Records notification delivery outcomes as one immutable original delivery
 * row per notification attempt.
 *
 * Recording never stores message bodies, passwords, tokens, reset links, or
 * other secrets: callers pass only the recipient snapshot and notification
 * type, and transport exceptions are converted into safe diagnostic
 * classifications instead of raw messages.
 *
 * Persistence failures are reported and returned as null so a recording
 * failure never rolls back an already-successful account operation. Each
 * record is a single insert, which participates in a caller's open
 * transaction when one exists; this service opens no transactions of its own.
 */
class NotificationDeliveryRecorder
{
    /**
     * Record a successful delivery attempt.
     */
    public function recordSuccess(
        ?User $user,
        string $recipientEmail,
        string $notificationType,
    ): ?NotificationDelivery {
        return $this->persist(fn (): NotificationDelivery => NotificationDelivery::query()->create([
            'user_id' => $user?->getKey(),
            'recipient_email' => $recipientEmail,
            'notification_type' => $notificationType,
            'status' => NotificationDelivery::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]));
    }

    /**
     * Record a failed delivery attempt with sanitized diagnostics.
     */
    public function recordFailure(
        ?User $user,
        string $recipientEmail,
        string $notificationType,
        Throwable $exception,
    ): ?NotificationDelivery {
        $diagnostics = $this->diagnose($exception);

        return $this->persist(fn (): NotificationDelivery => NotificationDelivery::query()->create([
            'user_id' => $user?->getKey(),
            'recipient_email' => $recipientEmail,
            'notification_type' => $notificationType,
            'status' => NotificationDelivery::STATUS_FAILED,
            'failure_code' => $diagnostics['failure_code'],
            'failure_message' => $diagnostics['failure_message'],
            'user_message' => $diagnostics['user_message'],
            'delivered_at' => null,
        ]));
    }

    /**
     * Convert a delivery exception into safe diagnostic classifications.
     *
     * The raw exception message is never read or stored, so transport error
     * text containing addresses, links, or tokens cannot leak into storage.
     *
     * @return array{failure_code: string, failure_message: string, user_message: string}
     */
    public function diagnose(Throwable $exception): array
    {
        $isTransportFailure = $exception instanceof TransportExceptionInterface;

        return [
            'failure_code' => $isTransportFailure ? 'mail_transport_failed' : 'delivery_failed',
            'failure_message' => $isTransportFailure
                ? sprintf('The mail transport failed while sending (%s).', $exception::class)
                : sprintf('Notification delivery failed (%s).', $exception::class),
            'user_message' => 'Your email could not be delivered. Please try again later.',
        ];
    }

    /**
     * Persist one delivery row, reporting but never throwing on failure.
     */
    private function persist(callable $create): ?NotificationDelivery
    {
        try {
            return $create();
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
