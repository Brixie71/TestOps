<?php

namespace App\Http\Controllers;

use App\Models\Defect;
use App\Models\Project;
use App\Models\TestResult;
use App\Models\TestRun;
use App\Models\User;
use App\Services\Authorization\ProjectVisibility;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Read-only landing dashboard: scoped KPI cards and short lists.
 *
 * Every query is constrained through ProjectVisibility — the same source as
 * the projects directory — so no widget can expose a project the user cannot
 * see. Ability-based widgets are omitted, not zeroed, when the user's
 * permissions cover no project.
 */
class DashboardController extends Controller
{
    /**
     * Number of days the recorded-results metric covers.
     */
    private const RESULTS_WINDOW_DAYS = 7;

    /**
     * Rows shown in each dashboard list.
     */
    private const LIST_LIMIT = 5;

    private const PROJECT_LIMIT = 8;

    public function __construct(
        private readonly ProjectVisibility $visibility,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $totals = $this->totals($user);

        $metrics = [
            [
                'label' => 'Projects',
                'value' => $totals['projects'],
                'description' => 'Projects you can access',
            ],
        ];

        if ($totals['run_projects'] > 0) {
            $metrics[] = [
                'label' => 'Open runs',
                'value' => $totals['open_runs'],
                'description' => 'Runs in progress',
            ];
            $metrics[] = [
                'label' => 'Results (7 days)',
                'value' => $totals['results'],
                'description' => 'Outcomes recorded this week',
            ];
        }

        if ($totals['defect_projects'] > 0) {
            $metrics[] = [
                'label' => 'Open defects',
                'value' => $totals['open_defects'],
                'description' => 'Open or in progress',
            ];
        }

        $projects = $this->projects($user);
        $projectIds = $projects->pluck('id')->all();

        return view('dashboard.index', [
            'metrics' => $metrics,
            'projects' => $projects,
            'openRuns' => $this->openRuns($user),
            'openDefects' => $this->openDefects($user),
            'showRuns' => $totals['run_projects'] > 0,
            'showDefects' => $totals['defect_projects'] > 0,
            // Per-row testing columns are omitted for projects outside the
            // matching ability, so a visible project never shows counts the
            // user is not allowed to see.
            'runScopedProjectIds' => $projectIds === []
                ? []
                : $this->visibility->projectIdsFor($user, 'test_runs.view')
                    ->whereIn('id', $projectIds)
                    ->pluck('id')
                    ->all(),
            'defectScopedProjectIds' => $projectIds === []
                ? []
                : $this->visibility->projectIdsFor($user, 'defects.view')
                    ->whereIn('id', $projectIds)
                    ->pluck('id')
                    ->all(),
        ]);
    }

    /**
     * Every dashboard total in one round trip, so the page's query count stays
     * fixed regardless of how many projects the user can see.
     *
     * @return array{projects: int, run_projects: int, open_runs: int, results: int, defect_projects: int, open_defects: int}
     */
    private function totals(User $user): array
    {
        $runScope = fn (): Builder => $this->visibility->projectIdsFor($user, 'test_runs.view');
        $defectScope = fn (): Builder => $this->visibility->projectIdsFor($user, 'defects.view');

        $row = Project::query()
            ->selectSub(
                $this->visibility->viewableProjects($user)->selectRaw('count(*)')->toBase(),
                'projects_total',
            )
            ->selectSub($this->countProjects($runScope()), 'run_projects_total')
            ->selectSub(
                TestRun::query()
                    ->selectRaw('count(*)')
                    ->whereIn('project_id', $runScope())
                    ->where('status', TestRun::STATUS_OPEN)
                    ->toBase(),
                'open_runs_total',
            )
            ->selectSub(
                TestResult::query()
                    ->selectRaw('count(*)')
                    ->where('recorded_at', '>=', now()->subDays(self::RESULTS_WINDOW_DAYS))
                    ->whereHas('runCase.run', fn (Builder $run) => $run
                        ->whereIn('project_id', $runScope()))
                    ->toBase(),
                'results_total',
            )
            ->selectSub($this->countProjects($defectScope()), 'defect_projects_total')
            ->selectSub(
                Defect::query()
                    ->selectRaw('count(*)')
                    ->whereIn('project_id', $defectScope())
                    ->whereIn('status', [Defect::STATUS_OPEN, Defect::STATUS_IN_PROGRESS])
                    ->toBase(),
                'open_defects_total',
            )
            ->limit(1)
            ->first();

        // No projects at all means the driving row is absent; every total is 0.
        if ($row === null) {
            return [
                'projects' => 0,
                'run_projects' => 0,
                'open_runs' => 0,
                'results' => 0,
                'defect_projects' => 0,
                'open_defects' => 0,
            ];
        }

        return [
            'projects' => (int) $row->projects_total,
            'run_projects' => (int) $row->run_projects_total,
            'open_runs' => (int) $row->open_runs_total,
            'results' => (int) $row->results_total,
            'defect_projects' => (int) $row->defect_projects_total,
            'open_defects' => (int) $row->open_defects_total,
        ];
    }

    /**
     * Count the projects matched by a scoped project-id builder as a scalar
     * subselect. The project-id builder already selects a column, so it cannot
     * be reused with selectRaw directly.
     *
     * @param  Builder<Project>  $projectIds
     */
    private function countProjects(Builder $projectIds): \Illuminate\Database\Query\Builder
    {
        return Project::query()
            ->selectRaw('count(*)')
            ->whereIn('projects.id', $projectIds)
            ->toBase();
    }

    /**
     * @return Collection<int, Project>
     */
    private function projects(User $user): Collection
    {
        return $this->visibility->viewableProjects($user)
            ->select('projects.*')
            ->withCount([
                'testRuns as open_runs_count' => fn (Builder $query) => $query
                    ->where('status', TestRun::STATUS_OPEN),
                'defects as unresolved_defects_count' => fn (Builder $query) => $query
                    ->whereIn('status', [Defect::STATUS_OPEN, Defect::STATUS_IN_PROGRESS]),
            ])
            ->orderByDesc('unresolved_defects_count')
            ->orderBy('name')
            ->limit(self::PROJECT_LIMIT)
            ->get();
    }

    /**
     * @return Collection<int, TestRun>
     */
    private function openRuns(User $user): Collection
    {
        return TestRun::query()
            ->select([
                'test_runs.id',
                'test_runs.project_id',
                'test_runs.name',
                'test_runs.sequence',
                'test_runs.started_at',
                'test_runs.created_by',
                'projects.name as project_name',
                'users.name as author_name',
            ])
            ->join('projects', 'projects.id', '=', 'test_runs.project_id')
            ->leftJoin('users', 'users.id', '=', 'test_runs.created_by')
            ->whereIn('test_runs.project_id', $this->visibility->projectIdsFor($user, 'test_runs.view'))
            ->where('test_runs.status', TestRun::STATUS_OPEN)
            ->orderByDesc('test_runs.started_at')
            ->orderByDesc('test_runs.id')
            ->withCount([
                'cases as cases_count',
                'cases as recorded_count' => fn (Builder $query) => $query->whereHas('result'),
            ])
            ->limit(self::LIST_LIMIT)
            ->get();
    }

    /**
     * @return Collection<int, Defect>
     */
    private function openDefects(User $user): Collection
    {
        return Defect::query()
            ->select(['defects.*', 'projects.name as project_name'])
            ->join('projects', 'projects.id', '=', 'defects.project_id')
            ->whereIn('defects.project_id', $this->visibility->projectIdsFor($user, 'defects.view'))
            ->whereIn('defects.status', [Defect::STATUS_OPEN, Defect::STATUS_IN_PROGRESS])
            ->orderByRaw(
                "case defects.severity
                    when 'blocker' then 0
                    when 'critical' then 1
                    when 'major' then 2
                    when 'minor' then 3
                    else 4
                end"
            )
            ->orderByDesc('defects.created_at')
            ->limit(self::LIST_LIMIT)
            ->get();
    }
}
