<?php

namespace App\Services\Notifications;

use App\Mail\DirectAccountSetupMailable;
use App\Mail\EmailVerificationMailable;
use App\Mail\InvitationLinkMailable;
use App\Mail\PasswordResetMailable;
use App\Models\EmailVerificationRequest;
use App\Models\InvitationLinkRequest;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\Auth\PasswordResetService;
use App\Services\Settings\AccountLinkExpirationSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Sends invitation, direct-setup, and email-verification emails through
 * the immutable notification delivery recorder.
 *
 * Raw tokens travel once through the mailable arguments; they are never
 * persisted, logged, or audited by this service. A send never throws: the
 * outcome — successful or classified failure — is recorded as an immutable
 * delivery row, and the returned delivery id is linked onto the generating
 * invitation link request without touching the existing
 * notification_deliveries semantics.
 */
class InvitationDeliveryService
{
    public const NOTIFICATION_TYPE_INVITATION_LINK = 'invitation_link';

    public const NOTIFICATION_TYPE_DIRECT_ACCOUNT_SETUP = 'direct_account_setup';

    public const NOTIFICATION_TYPE_EMAIL_VERIFICATION = 'email_verification';

    public const NOTIFICATION_TYPE_FORCED_PASSWORD_RESET = 'forced_password_reset';

    public function __construct(
        private readonly NotificationDeliveryRecorder $recorder,
        private readonly AccountLinkExpirationSettings $settings,
    ) {}

    /**
     * Send the initial invitation link with the recovery code.
     */
    public function sendInvitationLink(
        InvitationLinkRequest $linkRequest,
        string $token,
        string $recoveryCode,
    ): ?NotificationDelivery {
        $expiresInMinutes = $this->settings
            ->getMinutes(AccountLinkExpirationSettings::KEY_INVITATION_LINK);

        return $this->send(
            $linkRequest,
            new InvitationLinkMailable(
                acceptUrl: route('invitations.accept.show', ['token' => $token]),
                recoveryCode: $recoveryCode,
                expiresInMinutes: $expiresInMinutes,
            ),
            self::NOTIFICATION_TYPE_INVITATION_LINK,
        );
    }

    /**
     * Send a direct account password-setup link.
     */
    public function sendDirectAccountSetupLink(
        InvitationLinkRequest $linkRequest,
        string $token,
    ): ?NotificationDelivery {
        $expiresInMinutes = $this->settings
            ->getMinutes(AccountLinkExpirationSettings::KEY_INVITATION_LINK);

        return $this->send(
            $linkRequest,
            new DirectAccountSetupMailable(
                setupUrl: route('invitations.accept.show', ['token' => $token]),
                expiresInMinutes: $expiresInMinutes,
            ),
            self::NOTIFICATION_TYPE_DIRECT_ACCOUNT_SETUP,
        );
    }

    /**
     * Send the account email-verification link.
     */
    public function sendEmailVerification(
        EmailVerificationRequest $verificationRequest,
        string $token,
    ): ?NotificationDelivery {
        $expiresInMinutes = $this->settings
            ->getMinutes(AccountLinkExpirationSettings::KEY_EMAIL_VERIFICATION);

        $mailable = new EmailVerificationMailable(
            verificationUrl: route('email-verification.show', ['token' => $token]),
            expiresInMinutes: $expiresInMinutes,
        );

        try {
            Mail::to($verificationRequest->email)->send($mailable);

            return $this->recorder->recordSuccess(
                $verificationRequest->user,
                $verificationRequest->email,
                self::NOTIFICATION_TYPE_EMAIL_VERIFICATION,
            );
        } catch (Throwable $exception) {
            return $this->recorder->recordFailure(
                $verificationRequest->user,
                $verificationRequest->email,
                self::NOTIFICATION_TYPE_EMAIL_VERIFICATION,
                $exception,
            );
        }
    }

    /**
     * Send the forced password-reset email (§11.3), recording the immutable
     * delivery outcome. The raw token travels only through the mailable
     * argument; it is never persisted, logged, or audited.
     */
    public function sendForcedPasswordReset(
        User $user,
        string $token,
    ): ?NotificationDelivery {
        $mailable = new PasswordResetMailable(
            resetUrl: route('password-reset.show', ['token' => $token]),
            expiresInMinutes: PasswordResetService::EXPIRY_MINUTES,
        );

        try {
            Mail::to((string) $user->email)->send($mailable);

            return $this->recorder->recordSuccess(
                $user,
                (string) $user->email,
                self::NOTIFICATION_TYPE_FORCED_PASSWORD_RESET,
            );
        } catch (Throwable $exception) {
            return $this->recorder->recordFailure(
                $user,
                (string) $user->email,
                self::NOTIFICATION_TYPE_FORCED_PASSWORD_RESET,
                $exception,
            );
        }
    }

    /**
     * Send one invitation mailable, record the outcome, and best-effort
     * link the delivery row onto the link request.
     */
    private function send(
        InvitationLinkRequest $linkRequest,
        Mailable $mailable,
        string $notificationType,
    ): ?NotificationDelivery {
        $recipient = $linkRequest->recipient_email;
        $user = $linkRequest->invitation->user;

        try {
            Mail::to($recipient)->send($mailable);

            $delivery = $this->recorder->recordSuccess(
                $user,
                $recipient,
                $notificationType,
            );
        } catch (Throwable $exception) {
            $delivery = $this->recorder->recordFailure(
                $user,
                $recipient,
                $notificationType,
                $exception,
            );
        }

        if ($delivery !== null) {
            try {
                $linkRequest->forceFill(['delivery_id' => $delivery->getKey()])->save();
            } catch (Throwable) {
                report(new RuntimeException(
                    'Failed to link the delivery record to invitation link request '
                    ."[$linkRequest->getKey()].",
                ));
            }
        }

        return $delivery;
    }
}
