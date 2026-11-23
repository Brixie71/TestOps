<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\SystemAnnouncementNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Super Admin system announcements (§14.3): compose a maintenance notice or
 * release announcement and deliver it to every active account.
 *
 * Delivery respects each recipient's system-announcement preference at send
 * time; suppressed recipients are recorded and counted, never treated as
 * failures. The audit entry stores the subject and aggregate outcome only,
 * never the message body.
 */
class AnnouncementController extends Controller
{
    public function __construct(
        private readonly SystemAnnouncementNotificationService $announcements,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(): View
    {
        return view('settings.announcements', [
            'activeRecipientCount' => User::query()->where('account_status', 'active')->count(),
            'recentDeliveries' => NotificationDelivery::query()
                ->where('notification_type', SystemAnnouncementNotificationService::NOTIFICATION_TYPE)
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $outcome = $this->announcements->sendToActiveAccounts(
            $validated['subject'],
            $validated['body'],
        );

        $this->auditLogger->log(
            $request->user(),
            null,
            'announcement.sent',
            null,
            [
                'subject' => $validated['subject'],
                'recipients' => $outcome['recipients'],
                'delivered' => $outcome['delivered'],
                'suppressed' => $outcome['suppressed'],
                'failed' => $outcome['failed'],
            ],
        );

        return redirect()
            ->route('settings.announcements')
            ->with('status', sprintf(
                'Announcement processed for %d %s: %d delivered, %d suppressed by preferences, %d failed.',
                $outcome['recipients'],
                $outcome['recipients'] === 1 ? 'account' : 'accounts',
                $outcome['delivered'],
                $outcome['suppressed'],
                $outcome['failed'],
            ));
    }
}
