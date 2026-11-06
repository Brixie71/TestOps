<?php

namespace App\Services\Notifications;

use App\Models\NotificationDelivery;
use App\Models\NotificationDeliveryRetry;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\PermissionChecker;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Manual retry workflow for failed notification deliveries.
 *
 * Retry policy: at most three manual retries per original delivery with a
 * five-minute cooldown between attempts; further retries require manual
 * intervention. Only failed deliveries are eligible, the original delivery
 * record is never modified, and every attempt is audited.
 *
 * Concurrency design: the attempt slot is reserved as a pending retry row
 * under a row lock before the transport runs, so simultaneous requests
 * cannot bypass the retry limit or cooldown. The delivery attempt itself
 * runs outside any transaction, then the pending row is finalized once with
 * its outcome — a thrown transport exception can therefore never leave an
 * attempt recorded as misleadingly successful.
 */
class NotificationDeliveryRetryService
{
    /**
     * Maximum manual retries per original delivery.
     */
    public const MAX_MANUAL_RETRIES = 3;

    /**
     * Cooldown between manual retries, in minutes.
     */
    public const COOLDOWN_MINUTES = 5;

    public function __construct(
        private readonly PermissionChecker $permissionChecker,
        private readonly NotificationDeliveryRecorder $recorder,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Perform one authorized manual retry of a failed delivery.
     *
     * $attemptDelivery performs the actual resend for the given delivery and
     * throws when the transport fails. The returned retry records the final
     * outcome; transport exceptions are captured as a failed attempt rather
     * than rethrown.
     *
     * @param  Closure(NotificationDelivery): void  $attemptDelivery
     */
    public function retry(
        User $actor,
        NotificationDelivery $delivery,
        Closure $attemptDelivery,
    ): NotificationDeliveryRetry {
        $this->authorizeRetry($actor);

        $retry = $this->reserveAttempt($delivery);

        $status = NotificationDeliveryRetry::STATUS_DELIVERED;
        $diagnostics = null;

        try {
            $attemptDelivery($delivery);
        } catch (Throwable $exception) {
            $status = NotificationDeliveryRetry::STATUS_FAILED;
            $diagnostics = $this->recorder->diagnose($exception);
        }

        return $this->finalizeAttempt($actor, $delivery, $retry, $status, $diagnostics);
    }

    /**
     * Allow only Super Admin and SYSTEM ADMIN actors, following the existing
     * PermissionChecker and system-role patterns.
     */
    private function authorizeRetry(User $actor): void
    {
        if ($this->permissionChecker->isSuperAdmin($actor)) {
            return;
        }

        $role = $actor->role;

        if ($role !== null && $role->scope === 'system' && $role->code === 'SYSTEM_ADMIN') {
            return;
        }

        throw NotificationRetryException::unauthorized();
    }

    /**
     * Validate eligibility and reserve the next attempt slot atomically.
     *
     * The reservation runs in a short transaction with the delivery row
     * locked, and the transport is never executed while it is held.
     */
    private function reserveAttempt(NotificationDelivery $delivery): NotificationDeliveryRetry
    {
        return DB::transaction(function () use ($delivery): NotificationDeliveryRetry {
            $locked = NotificationDelivery::query()
                ->whereKey($delivery->getKey())
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw NotificationRetryException::deliveryNotFound();
            }

            if ($locked->status !== NotificationDelivery::STATUS_FAILED) {
                throw NotificationRetryException::deliveryIsNotFailed();
            }

            $retries = $locked->retries()
                ->orderByDesc('attempt_number')
                ->get();

            $latest = $retries->first();

            if ($latest !== null && $latest->status === NotificationDeliveryRetry::STATUS_DELIVERED) {
                throw NotificationRetryException::latestRetryAlreadyDelivered();
            }

            if ($retries->count() >= self::MAX_MANUAL_RETRIES) {
                throw NotificationRetryException::retryLimitReached(self::MAX_MANUAL_RETRIES);
            }

            if ($latest !== null) {
                $cooldownSeconds = self::COOLDOWN_MINUTES * 60;
                $elapsedSeconds = $latest->attempted_at->diffInSeconds(now(), true);

                if ($elapsedSeconds < $cooldownSeconds) {
                    throw NotificationRetryException::cooldownActive(
                        (int) ($cooldownSeconds - $elapsedSeconds),
                    );
                }
            }

            $attemptNumber = (int) $retries->max('attempt_number') + 1;

            try {
                return NotificationDeliveryRetry::query()->create([
                    'notification_delivery_id' => $locked->getKey(),
                    'attempt_number' => $attemptNumber,
                    'status' => NotificationDeliveryRetry::STATUS_PENDING,
                    'attempted_at' => now(),
                    'completed_at' => null,
                ]);
            } catch (QueryException $exception) {
                if ($this->isUniqueViolation($exception)) {
                    throw NotificationRetryException::attemptConflict();
                }

                throw $exception;
            }
        });
    }

    /**
     * Finalize the reserved attempt with its outcome and audit the retry.
     *
     * @param  array{failure_code: string, failure_message: string, user_message: string}|null  $diagnostics
     */
    private function finalizeAttempt(
        User $actor,
        NotificationDelivery $delivery,
        NotificationDeliveryRetry $retry,
        string $status,
        ?array $diagnostics,
    ): NotificationDeliveryRetry {
        return DB::transaction(function () use (
            $actor,
            $delivery,
            $retry,
            $status,
            $diagnostics,
        ): NotificationDeliveryRetry {
            $retry->status = $status;
            $retry->completed_at = now();

            if ($diagnostics !== null) {
                $retry->failure_code = $diagnostics['failure_code'];
                $retry->failure_message = $diagnostics['failure_message'];
                $retry->user_message = $diagnostics['user_message'];
            }

            $retry->save();

            $this->auditLogger->log(
                $actor,
                $delivery->user,
                'notification.retry',
                null,
                [
                    'notification_delivery_id' => $delivery->getKey(),
                    'notification_type' => $delivery->notification_type,
                    'attempt_number' => $retry->attempt_number,
                    'outcome' => $status,
                ],
            );

            return $retry;
        });
    }

    /**
     * Detect a unique-constraint violation portably across PostgreSQL and
     * SQLite through the SQLSTATE class of the exception.
     */
    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? '';

        return is_string($sqlState) && str_starts_with($sqlState, '23');
    }
}
