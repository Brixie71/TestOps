<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Audit\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    private const DATES_PER_PAGE = 7;

    private const AUDIT_VIEWED_SESSION_KEY = 'audit.viewed_logged';

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Dedicated audit log page (§12.4): filtering by actor, target user,
     * action type, and date range (§12.5).
     *
     * Viewing the page is itself audited once per browser session, so
     * navigating back and forth does not flood the history with duplicate
     * `audit.viewed` rows. Results are grouped per calendar date; repeated
     * occurrences of the same action keep one row with an occurrence count
     * and the list of times it happened on that date.
     */
    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);

        $dates = $this->filteredQuery($filters)
            ->selectRaw($this->dateExpression().' as day')
            ->distinct()
            ->orderByDesc('day')
            ->pluck('day')
            ->map(fn ($day): string => (string) $day)
            ->values();

        $currentPage = Paginator::resolveCurrentPage();
        $datePaginator = (new LengthAwarePaginator(
            $dates->slice(($currentPage - 1) * self::DATES_PER_PAGE, self::DATES_PER_PAGE)->values(),
            $dates->count(),
            self::DATES_PER_PAGE,
            $currentPage,
            ['path' => Paginator::resolveCurrentPath(), 'query' => $request->query()],
        ))->withQueryString();

        $dayGroups = $this->groupByDate($filters, $datePaginator->items());
        $totalEvents = $this->filteredQuery($filters)->count();

        if (! $request->session()->has(self::AUDIT_VIEWED_SESSION_KEY)) {
            $this->auditLogger->log(
                $request->user(),
                null,
                'audit.viewed',
                null,
                $this->auditContext($filters),
            );

            $request->session()->put(self::AUDIT_VIEWED_SESSION_KEY, true);
        }

        $actions = AuditLog::query()
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        $lastPage = $datePaginator->lastPage();

        // Same windowed page list as the user directory.
        $pageWindow = $lastPage <= 7
            ? collect(range(1, $lastPage))
            : collect([1, $lastPage])
                ->merge(range(max(2, $currentPage - 1), min($lastPage - 1, $currentPage + 1)))
                ->sort()
                ->unique()
                ->values();

        $hasFilters = collect($filters)->filter(fn ($value): bool => $value !== null && $value !== '')->isNotEmpty();

        return view('settings.audit-logs', [
            'dayGroups' => $dayGroups,
            'datePaginator' => $datePaginator,
            'totalEvents' => $totalEvents,
            'actions' => $actions,
            'filters' => $filters,
            'hasFilters' => $hasFilters,
            'currentPage' => $currentPage,
            'lastPage' => $lastPage,
            'pageWindow' => $pageWindow,
        ]);
    }

    /**
     * CSV export using the same active filters as the page (§12.5).
     * Streamed in chunks so arbitrarily large histories do not build a
     * single response in memory.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);

        $this->auditLogger->log(
            $request->user(),
            null,
            'audit.exported',
            null,
            $this->auditContext($filters) + ['format' => 'csv'],
        );

        $filename = 'audit-logs-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($filters): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'Timestamp (UTC)',
                'Action',
                'Actor name',
                'Actor email',
                'Target name',
                'Target email',
                'Reason',
                'Before values',
                'After values',
            ]);

            $this->filteredQuery($filters)
                ->orderBy('id')
                ->chunk(500, function ($logs) use ($output): void {
                    foreach ($logs as $log) {
                        fputcsv($output, [
                            $log->created_at?->format('Y-m-d H:i:s'),
                            $log->action,
                            $log->actor_name,
                            $log->actor_email,
                            $log->target_name,
                            $log->target_email,
                            $log->reason,
                            $log->before_values !== null ? json_encode($log->before_values) : '',
                            $log->after_values !== null ? json_encode($log->after_values) : '',
                        ]);
                    }
                });

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * PDF export using the same active filters as the page (§12.4-§12.5).
     *
     * The filtered history is rendered into one landscape document. Like the
     * CSV export, the export itself is audited with the active filters and
     * the produced format.
     */
    public function exportPdf(Request $request): Response
    {
        $filters = $this->validatedFilters($request);

        $this->auditLogger->log(
            $request->user(),
            null,
            'audit.exported',
            null,
            $this->auditContext($filters) + ['format' => 'pdf'],
        );

        $generatedAt = now();

        return Pdf::loadView('settings.audit-logs-pdf', [
            'logs' => $this->filteredQuery($filters)->orderBy('id')->get(),
            'filters' => $filters,
            'generatedAt' => $generatedAt,
        ])
            ->setPaper('a4', 'landscape')
            ->download('audit-logs-'.$generatedAt->format('Ymd-His').'.pdf');
    }

    /**
     * Portable calendar-date expression for the configured connection.
     */
    private function dateExpression(): string
    {
        return DB::connection()->getDriverName() === 'pgsql'
            ? 'created_at::date'
            : 'date(created_at)';
    }

    /**
     * Load the events for the shown dates and collapse repeated identical
     * actions into one entry per day with an occurrence count and the times
     * they happened.
     *
     * @param  array{actor: string|null, target: string|null, action: string|null, date_from: string|null, date_to: string|null}  $filters
     * @param  array<int, string>  $days
     * @return Collection<int, array{date: Carbon, count: int, groups: Collection<int, array{action: string, actor_name: string|null, actor_email: string|null, target_name: string|null, target_email: string|null, reason: string|null, count: int, times: Collection<int, Carbon>}>}>
     */
    private function groupByDate(array $filters, array $days): Collection
    {
        if ($days === []) {
            return collect();
        }

        $from = Carbon::parse($days[0])->startOfDay();
        $to = Carbon::parse($days[count($days) - 1])->endOfDay();

        return $this->filteredQuery($filters)
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (AuditLog $log): string => $log->created_at?->toDateString() ?? '')
            ->sortKeysDesc()
            ->map(function (Collection $events, string $day): array {
                $groups = $events
                    ->groupBy(fn (AuditLog $log): string => implode('|', [
                        $log->action,
                        $log->actor_name ?? '',
                        $log->actor_email ?? '',
                        $log->target_name ?? '',
                        $log->target_email ?? '',
                        $log->reason ?? '',
                    ]))
                    ->map(fn (Collection $occurrences): array => [
                        'action' => (string) $occurrences->first()->action,
                        'actor_name' => $occurrences->first()->actor_name,
                        'actor_email' => $occurrences->first()->actor_email,
                        'target_name' => $occurrences->first()->target_name,
                        'target_email' => $occurrences->first()->target_email,
                        'reason' => $occurrences->first()->reason,
                        'count' => $occurrences->count(),
                        'times' => $occurrences->pluck('created_at')->filter()->sortDesc()->values(),
                    ])
                    ->sortByDesc(fn (array $group): int => $group['times']->first()?->getTimestamp() ?? 0)
                    ->values();

                return [
                    'date' => Carbon::parse($day),
                    'count' => $events->count(),
                    'groups' => $groups,
                ];
            })
            ->values();
    }

    /**
     * @return array{actor: string|null, target: string|null, action: string|null, date_from: string|null, date_to: string|null}
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'actor' => ['nullable', 'string', 'max:100'],
            'target' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);
    }

    /**
     * @param  array{actor: string|null, target: string|null, action: string|null, date_from: string|null, date_to: string|null}  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        $query = AuditLog::query();

        $query->when($filters['actor'] ?? null, fn (Builder $q, string $actor) => $q->where(
            fn (Builder $inner) => $inner
                ->where('actor_name', 'like', '%'.$actor.'%')
                ->orWhere('actor_email', 'like', '%'.$actor.'%'),
        ));

        $query->when($filters['target'] ?? null, fn (Builder $q, string $target) => $q->where(
            fn (Builder $inner) => $inner
                ->where('target_name', 'like', '%'.$target.'%')
                ->orWhere('target_email', 'like', '%'.$target.'%'),
        ));

        $query->when($filters['action'] ?? null, fn (Builder $q, string $action) => $q->where('action', $action));

        $query->when($filters['date_from'] ?? null, fn (Builder $q, string $dateFrom) => $q->where(
            'created_at',
            '>=',
            Carbon::parse($dateFrom)->startOfDay(),
        ));

        $query->when($filters['date_to'] ?? null, fn (Builder $q, string $dateTo) => $q->where(
            'created_at',
            '<=',
            Carbon::parse($dateTo)->endOfDay(),
        ));

        return $query;
    }

    /**
     * Metadata recorded on the self-audit event for a view or export.
     *
     * @param  array{actor: string|null, target: string|null, action: string|null, date_from: string|null, date_to: string|null}  $filters
     * @return array<string, string>
     */
    private function auditContext(array $filters): array
    {
        return collect($filters)
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->map(fn ($value): string => (string) $value)
            ->all();
    }
}
