<?php

namespace App\Services\Notifications;

use App\Mail\SystemAnnouncementMailable;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The first optional-category sender (§14.3-14.4): system announcements
 * composed by a Super Admin and delivered to every active account.
 *
 * The stored preference is resolved immediately before each delivery. A
 * recipient who disabled the category is recorded as a suppressed delivery
 * (never a failure) and receives nothing. Delivery is synchronous and a mail
 * failure is recorded per recipient without interrupting the remaining
 * audience (§14.1).
 */
class SystemAnnouncementNotificationService
{
    public const NOTIFICATION_TYPE = 'system_announcement';

    public function __construct(
        private readonly NotificationPreferenceChecker $preferenceChecker,
        private readonly NotificationDeliveryRecorder $recorder,
    ) {}

    /**
     * Send one announcement to every active account.
     *
     * Invited, deactivated (soft-deleted), and otherwise non-active accounts
     * are not part of the audience.
     *
     * @return array{recipients: int, delivered: int, suppressed: int, failed: int}
     */
    public function sendToActiveAccounts(string $subject, string $body): array
    {
        $counts = ['recipients' => 0, 'delivered' => 0, 'suppressed' => 0, 'failed' => 0];

        User::query()
            ->where('account_status', 'active')
            ->chunkById(200, function ($users) use ($subject, $body, &$counts): void {
                foreach ($users as $user) {
                    $outcome = $this->send($user, $subject, $body);

                    $counts['recipients']++;
                    $counts[$outcome]++;
                }
            });

        return $counts;
    }

    /**
     * Send one announcement to one recipient and record its immutable
     * outcome.
     *
     * @return 'delivered'|'suppressed'|'failed'
     */
    public function send(User $user, string $subject, string $body): string
    {
        $recipient = (string) $user->email;

        if (! $this->preferenceChecker->allows($user, UserNotificationPreference::CATEGORY_SYSTEM_ANNOUNCEMENTS)) {
            $this->recorder->recordSuppressed($user, $recipient, self::NOTIFICATION_TYPE);

            return 'suppressed';
        }

        try {
            Mail::to($recipient)->send(new SystemAnnouncementMailable($subject, $body));

            $this->recorder->recordSuccess($user, $recipient, self::NOTIFICATION_TYPE);

            return 'delivered';
        } catch (Throwable $exception) {
            $this->recorder->recordFailure($user, $recipient, self::NOTIFICATION_TYPE, $exception);

            return 'failed';
        }
    }
}
