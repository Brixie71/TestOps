<?php

namespace App\Services\Notifications;

use App\Mail\AccountDeactivatedMailable;
use App\Mail\AccountReactivatedMailable;
use App\Mail\PasswordChangedMailable;
use App\Mail\SystemRoleChangedMailable;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends the mandatory account-security notifications of §14.2: account
 * deactivation, account restoration, system-role changes, and self-service
 * password changes.
 *
 * These notifications cannot be disabled by users, are sent synchronously,
 * and are recorded through the immutable notification delivery recorder. A
 * send never throws: every outcome — successful or classified failure — is
 * persisted as one delivery row, so a mail failure can never roll back the
 * account operation that triggered it (§14.1). The messages carry no
 * internal administrative details.
 */
class AccountSecurityNotificationService
{
    public const NOTIFICATION_TYPE_ACCOUNT_DEACTIVATED = 'account_deactivated';

    public const NOTIFICATION_TYPE_ACCOUNT_REACTIVATED = 'account_reactivated';

    public const NOTIFICATION_TYPE_SYSTEM_ROLE_CHANGED = 'system_role_changed';

    public const NOTIFICATION_TYPE_PASSWORD_CHANGED = 'password_changed';

    public function __construct(
        private readonly NotificationDeliveryRecorder $recorder,
    ) {}

    /**
     * Notify the account holder that their account was deactivated.
     */
    public function sendAccountDeactivated(User $user): ?NotificationDelivery
    {
        return $this->send(
            $user,
            new AccountDeactivatedMailable(accountEmail: (string) $user->email),
            self::NOTIFICATION_TYPE_ACCOUNT_DEACTIVATED,
        );
    }

    /**
     * Notify the account holder that their account was restored.
     */
    public function sendAccountReactivated(User $user): ?NotificationDelivery
    {
        return $this->send(
            $user,
            new AccountReactivatedMailable(accountEmail: (string) $user->email),
            self::NOTIFICATION_TYPE_ACCOUNT_REACTIVATED,
        );
    }

    /**
     * Notify the account holder that an administrator changed their system
     * role. Null names represent "no system role".
     */
    public function sendSystemRoleChanged(
        User $user,
        ?string $previousRoleName,
        ?string $newRoleName,
    ): ?NotificationDelivery {
        return $this->send(
            $user,
            new SystemRoleChangedMailable(
                previousRoleName: $previousRoleName,
                newRoleName: $newRoleName,
            ),
            self::NOTIFICATION_TYPE_SYSTEM_ROLE_CHANGED,
        );
    }

    /**
     * Notify the account holder that their password was changed.
     */
    public function sendPasswordChanged(User $user): ?NotificationDelivery
    {
        return $this->send(
            $user,
            new PasswordChangedMailable(accountEmail: (string) $user->email),
            self::NOTIFICATION_TYPE_PASSWORD_CHANGED,
        );
    }

    /**
     * Send one security notification and record its immutable outcome.
     */
    private function send(User $user, Mailable $mailable, string $notificationType): ?NotificationDelivery
    {
        $recipient = (string) $user->email;

        try {
            Mail::to($recipient)->send($mailable);

            return $this->recorder->recordSuccess($user, $recipient, $notificationType);
        } catch (Throwable $exception) {
            return $this->recorder->recordFailure($user, $recipient, $notificationType, $exception);
        }
    }
}
