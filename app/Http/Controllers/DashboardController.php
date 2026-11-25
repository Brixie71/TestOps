<?php

namespace App\Http\Controllers;

use App\Models\Defect;
use App\Models\TestResult;
use App\Models\TestRun;
use App\Services\Authorization\ProjectVisibility;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Read-only landing dashboard: scoped KPI counts for the signed-in user.
 *
 * Every count is constrained through ProjectVisibility — the same source as
 * the projects directory — so no widget can expose a project the user cannot
 * see. Detail lists and navigation arrive in later slices.
 */
class DashboardController extends Controller
{
    /**
     * Number of days the recorded-results metric covers.
     */
    private const RESULTS_WINDOW_DAYS = 7;

    public function __construct(
        private readonly ProjectVisibility $visibility,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $metrics = [
            [
                'label' => 'Projects',
                'value' => $this->visibility->viewableProjects($user)->count(),
                'description' => 'Projects you can access',
            ],
        ];

        // A testing card appears only when the user's ability covers at least
        // one project; otherwise it is omitted rather than shown as zero.
        if ($this->visibility->projectIdsFor($user, 'test_runs.view')->exists()) {
            $metrics[] = [
                'label' => 'Open runs',
                'value' => TestRun::query()
                    ->whereIn('project_id', $this->visibility->projectIdsFor($user, 'test_runs.view'))
                    ->where('status', TestRun::STATUS_OPEN)
                    ->count(),
                'description' => 'Runs in progress',
            ];

            $metrics[] = [
                'label' => 'Results (7 days)',
                'value' => TestResult::query()
                    ->where('recorded_at', '>=', now()->subDays(self::RESULTS_WINDOW_DAYS))
                    ->whereHas('runCase.run', fn (Builder $run) => $run
                        ->whereIn('project_id', $this->visibility->projectIdsFor($user, 'test_runs.view')))
                    ->count(),
                'description' => 'Outcomes recorded this week',
            ];
        }

        if ($this->visibility->projectIdsFor($user, 'defects.view')->exists()) {
            $metrics[] = [
                'label' => 'Open defects',
                'value' => Defect::query()
                    ->whereIn('project_id', $this->visibility->projectIdsFor($user, 'defects.view'))
                    ->whereIn('status', [Defect::STATUS_OPEN, Defect::STATUS_IN_PROGRESS])
                    ->count(),
                'description' => 'Open or in progress',
            ];
        }

        return view('dashboard.index', [
            'metrics' => $metrics,
        ]);
    }
}
