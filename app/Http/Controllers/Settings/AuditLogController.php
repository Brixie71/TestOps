<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Audit\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    private const PAGE_SIZE = 50;

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Dedicated audit log page (§12.4): filtering by actor, target user,
     * action type, and date range (§12.5). Every viewing action itself
     * generates an audit event.
     */
    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);

        $logs = $this->filteredQuery($filters)
            ->orderByDesc('id')
            ->paginate(self::PAGE_SIZE)
            ->withQueryString();

        $this->auditLogger->log(
            $request->user(),
            null,
            'audit.viewed',
            null,
            $this->auditContext($filters),
        );

        $actions = AuditLog::query()
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        $currentPage = $logs->currentPage();
        $lastPage = $logs->lastPage();

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
            'logs' => $logs,
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
