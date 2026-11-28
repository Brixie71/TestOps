<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Defect;
use App\Models\Project;
use App\Models\TestResult;
use App\Models\TestRun;
use App\Models\TestRunCase;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only evidence sheet for one test run: the frozen snapshot, what each
 * case produced, who recorded it, and which defects it raised. Reads gate on
 * viewTestRuns; defect data additionally requires viewDefects and is omitted
 * (never zeroed) without it.
 */
class TestRunReportController extends Controller
{
    /**
     * Human labels for the five result statuses.
     *
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        TestResult::STATUS_PASSED => 'Passed',
        TestResult::STATUS_FAILED => 'Failed',
        TestResult::STATUS_BLOCKED => 'Blocked',
        TestResult::STATUS_SKIPPED => 'Skipped',
        TestResult::STATUS_NOT_RUN => 'Not run',
    ];

    public function show(Project $project, TestRun $testRun): View
    {
        Gate::authorize('viewTestRuns', $project);

        $showDefects = Gate::allows('viewDefects', $project);

        $run = $testRun->load(['author', 'cases.result.recorder', 'cases.result.defects']);

        $rows = $run->cases->map(fn (TestRunCase $case): array => $this->caseRow($case, $showDefects));

        return view('projects.test-runs.report', [
            'project' => $project,
            'run' => $run,
            'rows' => $rows,
            'summary' => $this->summary($rows),
        ]);
    }

    /**
     * CSV export of the same content: one row per frozen case, streamed.
     */
    public function export(Project $project, TestRun $testRun): StreamedResponse
    {
        Gate::authorize('viewTestRuns', $project);

        $showDefects = Gate::allows('viewDefects', $project);

        $filename = 'run-'.$testRun->displayKey().'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($testRun, $showDefects): void {
            $output = fopen('php://output', 'w');

            $header = ['Run', 'Run name', 'Run status', 'Position', 'Case title', 'Severity', 'Steps', 'Result', 'Notes', 'Recorded by', 'Recorded at'];

            if ($showDefects) {
                $header[] = 'Defects';
            }

            fputcsv($output, $header);

            $testRun->cases()
                ->with(['result.recorder', 'result.defects'])
                ->lazy(200)
                ->each(function (TestRunCase $case) use ($output, $testRun, $showDefects): void {
                    $row = $this->caseRow($case, $showDefects);

                    $line = [
                        $testRun->displayKey(),
                        $testRun->name,
                        ucfirst($testRun->status),
                        $row['position'],
                        $row['title'],
                        ucfirst($row['severity']),
                        $this->stepsText($row['steps']),
                        $row['status_label'],
                        $row['notes'] ?? '',
                        $row['recorder'] ?? '',
                        $row['recorded_at']?->copy()->timezone(config('app.display_timezone'))->format('Y-m-d H:i:s') ?? '',
                    ];

                    if ($showDefects) {
                        $line[] = $this->defectsText($row['defects']);
                    }

                    fputcsv($output, $line);
                });

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * One row per frozen case, shaped for both the view and the CSV.
     *
     * @return array{position: int, title: string, severity: string, status: string, status_label: string, notes: string|null, recorder: string|null, recorded_at: Carbon|null, steps: array<int, array{action: string, expected_result: string|null}>, defects: Collection<int, Defect>}
     */
    private function caseRow(TestRunCase $case, bool $showDefects): array
    {
        $result = $case->result;
        $status = $result?->status ?? TestResult::STATUS_NOT_RUN;

        return [
            'position' => (int) $case->position,
            'title' => (string) $case->title,
            'severity' => (string) $case->severity,
            'status' => $status,
            'status_label' => self::STATUS_LABELS[$status],
            'notes' => $result?->notes,
            'recorder' => $result?->recorder?->name,
            'recorded_at' => $result?->recorded_at,
            'steps' => $case->steps ?? [],
            'defects' => $showDefects && $result
                ? $result->defects->sortBy('sequence')->values()
                : collect(),
        ];
    }

    /**
     * Steps as one numbered cell: "1. action -> expected; 2. action".
     *
     * @param  array<int, array{action: string, expected_result: string|null}>  $steps
     */
    private function stepsText(array $steps): string
    {
        return collect($steps)
            ->map(function (array $step, int $index): string {
                $text = ($index + 1).'. '.(string) ($step['action'] ?? '');

                return empty($step['expected_result']) ? $text : $text.' -> '.$step['expected_result'];
            })
            ->implode('; ');
    }

    /**
     * Defects as "DEF-7 title; DEF-9 title".
     *
     * @param  Collection<int, Defect>  $defects
     */
    private function defectsText(Collection $defects): string
    {
        return $defects
            ->map(fn (Defect $defect): string => $defect->displayKey().' '.$defect->title)
            ->implode('; ');
    }

    /**
     * Case total plus a count per result status, in the canonical order.
     *
     * @param  Collection<int, array{status: string}>  $rows
     * @return array{total: int, statuses: list<array{label: string, count: int}>}
     */
    private function summary(Collection $rows): array
    {
        $counts = array_fill_keys(array_keys(self::STATUS_LABELS), 0);

        foreach ($rows as $row) {
            $counts[$row['status']] = ($counts[$row['status']] ?? 0) + 1;
        }

        return [
            'total' => $rows->count(),
            'statuses' => collect(self::STATUS_LABELS)
                ->map(fn (string $label, string $status): array => [
                    'label' => $label,
                    'count' => $counts[$status],
                ])
                ->values()
                ->all(),
        ];
    }
}
