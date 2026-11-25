<?php

namespace App\Services\Notifications;

use App\Models\NotificationDelivery;
use App\Models\Project;
use Closure;
use Illuminate\Support\Facades\Mail;

/**
 * Rebuilds failed project activity notifications for the administrative
 * retry workflow (A15.4-A15.5).
 *
 * Unlike system announcements, project activity messages are reconstructible:
 * each delivery stores the event and referenced ids, so the message is
 * re-rendered from current project and user records. Deliveries without that
 * context (or whose project has since been removed) have no retry path.
 */
class ProjectActivityRetryService
{
    /**
     * Notification types with a reconstructible resend path.
     */
    public const RETRYABLE_TYPES = [
        ProjectActivityNotificationService::NOTIFICATION_TYPE,
    ];

    public function __construct(
        private readonly ProjectActivityMessageFactory $messages,
    ) {}

    /**
     * Build the retry closure, or null when the message cannot be rebuilt.
     *
     * @return Closure(): void|null
     */
    public function retryClosure(NotificationDelivery $delivery): ?Closure
    {
        $context = $delivery->context;
        $event = is_array($context) ? ($context['event'] ?? null) : null;
        $projectId = is_array($context) ? ($context['project_id'] ?? null) : null;

        if (! is_string($event) || (! is_int($projectId) && ! is_string($projectId))) {
            return null;
        }

        $project = Project::query()->find($projectId);

        if ($project === null) {
            return null;
        }

        $mailable = $this->messages->build($event, $project, $context);

        if ($mailable === null) {
            return null;
        }

        return function () use ($delivery, $mailable): void {
            Mail::to((string) $delivery->recipient_email)->send($mailable);
        };
    }
}
