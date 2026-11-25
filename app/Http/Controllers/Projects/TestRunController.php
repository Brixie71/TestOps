<?php

namespace App\Http\Controllers\Projects;

use App\Actions\ManageTestRunAction;
use App\Actions\RecordTestResultAction;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\TestResult;
use App\Models\TestRun;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Thin controller for test runs: start a run over selected cases, view the
 * frozen snapshots, and complete or abort an open run. Reads gate on the
 * viewTestRuns ability, every mutation on manageTestRuns.
 */
class TestRunController extends Controller
{
    public function __construct(
        private readonly ManageTestRunAction $manageTestRunAction,
        private readonly RecordTestResultAction $recordTestResultAction,
    ) {}

    public function index(Request $request, Project $project): View
    {
        Gate::authorize('viewTestRuns', $project);

        return view('projects.test-runs.index', [
            'project' => $project,
            'runs' => $project->testRuns()
                ->withCount('cases')
                ->orderByDesc('sequence')
                ->get(),
            'suites' => $project->testSuites()
                ->with(['testCases' => fn ($query) => $query->orderBy('sequence')])
                ->orderBy('name')
                ->get(),
            'canManage' => Gate::allows('manageTestRuns', $project),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manageTestRuns', $project);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'case_ids' => ['required', 'array', 'min:1'],
            'case_ids.*' => ['integer', Rule::exists('test_cases', 'id')->where('project_id', $project->id)],
        ]);

        $run = $this->manageTestRunAction->start(
            $request->user(),
            $project,
            $validated['name'],
            array_map('intval', $validated['case_ids']),
        );

        return redirect()
            ->route('projects.test-runs.show', [$project, $run])
            ->with('status', $run->displayKey().' started.');
    }

    public function show(Request $request, Project $project, TestRun $testRun): View
    {
        Gate::authorize('viewTestRuns', $project);

        return view('projects.test-runs.show', [
            'project' => $project,
            'run' => $testRun->load(['cases.result.recorder', 'author']),
            'canManage' => Gate::allows('manageTestRuns', $project),
        ]);
    }

    public function recordResult(Request $request, Project $project, TestRun $testRun): RedirectResponse
    {
        Gate::authorize('manageTestRuns', $project);

        $validated = $request->validate([
            'test_run_case_id' => ['required', 'integer'],
            'status' => ['required', Rule::in(TestResult::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Resolve the frozen case inside the run: an id from another run is
        // a 404, never a cross-run write.
        $runCase = $testRun->cases()->findOrFail((int) $validated['test_run_case_id']);

        $notes = trim((string) ($validated['notes'] ?? ''));

        $this->recordTestResultAction->record(
            $request->user(),
            $testRun,
            $runCase,
            $validated['status'],
            $notes === '' ? null : $notes,
        );

        return redirect()
            ->route('projects.test-runs.show', [$project, $testRun])
            ->with('status', 'Result recorded for case #'.$runCase->position.'.');
    }

    public function complete(Request $request, Project $project, TestRun $testRun): RedirectResponse
    {
        Gate::authorize('manageTestRuns', $project);

        $this->manageTestRunAction->complete($request->user(), $testRun);

        return redirect()
            ->route('projects.test-runs.show', [$project, $testRun])
            ->with('status', $testRun->displayKey().' completed.');
    }

    public function abort(Request $request, Project $project, TestRun $testRun): RedirectResponse
    {
        Gate::authorize('manageTestRuns', $project);

        $this->manageTestRunAction->abort($request->user(), $testRun);

        return redirect()
            ->route('projects.test-runs.show', [$project, $testRun])
            ->with('status', $testRun->displayKey().' aborted.');
    }
}
