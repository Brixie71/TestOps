<?php

namespace App\Http\Controllers\Settings;

use App\Actions\ResendUserInvitationAction;
use App\Http\Controllers\Controller;
use App\Models\InvitationLinkRequest;
use App\Models\NotificationDelivery;
use App\Models\NotificationDeliveryRetry;
use App\Services\Notifications\InvitationDeliveryService;
use App\Services\Notifications\NotificationDeliveryRetryService;
use App\Services\Notifications\NotificationRetryException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationDeliveryHistoryController extends Controller
{
    private const PAGE_SIZE = 50;

    /**
     * Notification types with a resend path: the manual-retry closure
     * rotates the invitation credentials behind the failed link request.
     * Email verification has no administrative resend path (users request
     * a fresh verification email through their normal workflow), so those
     * deliveries are never retryable here.
     */
    private const RETRYABLE_TYPES = [
        InvitationDeliveryService::NOTIFICATION_TYPE_INVITATION_LINK,
        InvitationDeliveryService::NOTIFICATION_TYPE_DIRECT_ACCOUNT_SETUP,
    ];

    public function __construct(
        private readonly NotificationDeliveryRetryService $retryService,
        private readonly ResendUserInvitationAction $resendUserInvitationAction,
    ) {}

    /**
     * Email delivery history page (§15.1–15.2): recipient, notification
     * type, delivery and retry status, failure reason, and retry attempt
     * history. Original delivery records are immutable; the effective
     * status derives from the latest retry.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:delivered,failed'],
            'type' => ['nullable', 'string', 'max:100'],
        ]);

        $deliveries = $this->filteredQuery($filters)
            ->with(['retries', 'user'])
            ->orderByDesc('id')
            ->paginate(self::PAGE_SIZE)
            ->withQueryString();

        $types = NotificationDelivery::query()
            ->distinct()
            ->orderBy('notification_type')
            ->pluck('notification_type');

        // Delivery ids that carry a linked invitation link request, so the
        // view can render retry controls only where a resend path exists.
        $retryableIds = InvitationLinkRequest::query()
            ->whereIn('delivery_id', $deliveries->getCollection()->pluck('id'))
            ->pluck('delivery_id');

        return view('settings.notifications', [
            'deliveries' => $deliveries,
            'types' => $types,
            'filters' => $filters,
            'hasFilters' => collect($filters)->some(fn ($value): bool => $value !== null && $value !== ''),
            'retryableTypes' => self::RETRYABLE_TYPES,
            'maxRetries' => NotificationDeliveryRetryService::MAX_MANUAL_RETRIES,
            'retryableIds' => $retryableIds,
        ]);
    }

    /**
     * Manual retry for a failed delivery (§15.4–15.5). The service owns the
     * eligibility checks, the attempt slot reservation, the immutable
     * original record, and the audit; the controller only maps outcomes to
     * HTTP responses.
     */
    public function retry(Request $request, NotificationDelivery $delivery): RedirectResponse
    {
        $linkRequest = InvitationLinkRequest::query()
            ->where('delivery_id', $delivery->getKey())
            ->first();

        if (! in_array($delivery->notification_type, self::RETRYABLE_TYPES, true) || $linkRequest === null) {
            return back()->withErrors([
                'retry' => 'This delivery has no automatic resend path. Issue a fresh invitation instead.',
            ]);
        }

        try {
            $attempt = $this->retryService->retry(
                $request->user(),
                $delivery,
                $this->resendUserInvitationAction->retryClosure($request->user()),
            );
        } catch (NotificationRetryException $exception) {
            // Unauthorized and missing records are request-level errors;
            // eligibility rejections (cooldown, retry limit, already
            // delivered, concurrent attempt) surface as inline feedback.
            if ($exception->getMessage() === NotificationRetryException::unauthorized()->getMessage()) {
                abort(403, $exception->getMessage());
            }

            if ($exception->getMessage() === NotificationRetryException::deliveryNotFound()->getMessage()) {
                abort(404, $exception->getMessage());
            }

            return back()->withErrors(['retry' => $exception->getMessage()]);
        }

        $outcome = $attempt->status === NotificationDeliveryRetry::STATUS_DELIVERED
            ? 'delivered'
            : 'failed';

        return back()->with('status', sprintf(
            'Retry attempt %d completed: %s.',
            $attempt->attempt_number,
            $outcome,
        ));
    }

    /**
     * @param  array{search: string|null, status: string|null, type: string|null}  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        $query = NotificationDelivery::query();

        $query->when($filters['search'] ?? null, fn (Builder $q, string $search) => $q->where(
            'recipient_email',
            'like',
            '%'.$search.'%',
        ));

        $query->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status));

        $query->when($filters['type'] ?? null, fn (Builder $q, string $type) => $q->where('notification_type', $type));

        return $query;
    }
}
