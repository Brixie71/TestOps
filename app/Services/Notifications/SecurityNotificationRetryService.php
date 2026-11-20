<?php

namespace App\Services\Notifications;

use App\Mail\AccountDeactivatedMailable;
use App\Mail\AccountReactivatedMailable;
use App\Mail\EmailChangeCancelledMailable;
use App\Mail\PasswordChangedMailable;
use App\Mail\SystemRoleChangedMailable;
use App\Models\AuditLog;
use App\Models\NotificationDelivery;
use Closure;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * Rebuilds the send path for failed security notifications so an authorized
 * administrator can retry them under the §15.4–§15.5 retry policy.
 *
 * Only deliveries whose message can be reconstructed are retryable:
 * deactivation, restoration, role changes (role names come from the
 * immutable `user.role_changed` audit snapshot), self-service password
 * changes, and email-change cancellation. Verification and password-reset
 * emails carry single-use tokens that are never stored, so they have no
 * retry path — a fresh email must be requested through its normal
 * workflow.
 *
 * The returned closure sends the mail and lets transport exceptions escape,
 * so `NotificationDeliveryRetryService` records the attempt outcome.
 */
class SecurityNotificationRetryService
{
    /**
     * Security notification types with a reconstructible resend path.
     */
    public const RETRYABLE_TYPES = [
        AccountSecurityNotificationService::NOTIFICATION_TYPE_ACCOUNT_DEACTIVATED,
        AccountSecurityNotificationService::NOTIFICATION_TYPE_ACCOUNT_REACTIVATED,
        AccountSecurityNotificationService::NOTIFICATION_TYPE_SYSTEM_ROLE_CHANGED,
        AccountSecurityNotificationService::NOTIFICATION_TYPE_PASSWORD_CHANGED,
        InvitationDeliveryService::NOTIFICATION_TYPE_EMAIL_CHANGE_CANCELLED,
    ];

    /**
     * Build the retry closure, or null when the message cannot be
     * reconstructed from the delivery record and its audit history.
     *
     * @return Closure(): void|null
     */
    public function retryClosure(NotificationDelivery $delivery): ?Closure
    {
        $mailable = $this->mailableFor($delivery);

        if ($mailable === null) {
            return null;
        }

        return function () use ($delivery, $mailable): void {
            Mail::to((string) $delivery->recipient_email)->send($mailable);
        };
    }

    private function mailableFor(NotificationDelivery $delivery): ?Mailable
    {
        return match ($delivery->notification_type) {
            AccountSecurityNotificationService::NOTIFICATION_TYPE_ACCOUNT_DEACTIVATED => new AccountDeactivatedMailable(
                accountEmail: (string) $delivery->recipient_email,
            ),
            AccountSecurityNotificationService::NOTIFICATION_TYPE_ACCOUNT_REACTIVATED => new AccountReactivatedMailable(
                accountEmail: (string) $delivery->recipient_email,
            ),
            AccountSecurityNotificationService::NOTIFICATION_TYPE_SYSTEM_ROLE_CHANGED => $this->roleChangedMailable($delivery),
            AccountSecurityNotificationService::NOTIFICATION_TYPE_PASSWORD_CHANGED => new PasswordChangedMailable(
                accountEmail: (string) $delivery->recipient_email,
            ),
            InvitationDeliveryService::NOTIFICATION_TYPE_EMAIL_CHANGE_CANCELLED => new EmailChangeCancelledMailable(
                currentEmail: (string) $delivery->recipient_email,
            ),
            default => null,
        };
    }

    /**
     * Reconstruct the role-change message from the audit snapshot recorded
     * with the original change (§8, §12.3).
     */
    private function roleChangedMailable(NotificationDelivery $delivery): ?Mailable
    {
        $audit = AuditLog::query()
            ->where('target_user_id', $delivery->user_id)
            ->where('action', 'user.role_changed')
            ->where('created_at', '<=', $delivery->created_at)
            ->orderByDesc('id')
            ->first();

        if ($audit === null) {
            return null;
        }

        return new SystemRoleChangedMailable(
            previousRoleName: $audit->before_values['role_name'] ?? null,
            newRoleName: $audit->after_values['role_name'] ?? null,
        );
    }
}
