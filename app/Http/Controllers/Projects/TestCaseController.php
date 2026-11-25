<?php

namespace App\Http\Controllers\Projects;

use App\Actions\SaveTestCaseAction;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\TestCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Thin controller for test-case create/update/delete. Validation and the
 * response live here; the audited transaction lives in SaveTestCaseAction.
 */
class TestCaseController extends Controller
{
    public function __construct(
        private readonly SaveTestCaseAction $saveTestCaseAction,
    ) {}

    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manageTestCases', $project);

        $validated = $request->validate([
            'test_suite_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(TestCase::STATUSES)],
            'severity' => ['required', Rule::in(TestCase::SEVERITIES)],
            'steps' => ['nullable', 'array', 'max:50'],
            'steps.*.action' => ['nullable', 'string', 'max:1000'],
            'steps.*.expected_result' => ['nullable', 'string', 'max:1000'],
        ]);

        // Resolve the suite inside the project: a suite id from another
        // project is a 404, never a cross-project write.
        $suite = $project->testSuites()->findOrFail((int) $validated['test_suite_id']);

        $testCase = $this->saveTestCaseAction->create(
            $request->user(),
            $project,
            $suite,
            $this->attributes($validated),
        );

        return redirect()
            ->route('projects.testing.index', [
                'project' => $project,
                'suite' => $testCase->test_suite_id,
            ])
            ->with('status', $testCase->displayKey().' created.');
    }

    public function update(Request $request, Project $project, TestCase $testCase): RedirectResponse
    {
        Gate::authorize('manageTestCases', $project);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(TestCase::STATUSES)],
            'severity' => ['required', Rule::in(TestCase::SEVERITIES)],
            'steps' => ['nullable', 'array', 'max:50'],
            'steps.*.action' => ['nullable', 'string', 'max:1000'],
            'steps.*.expected_result' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->saveTestCaseAction->update(
            $request->user(),
            $testCase,
            $this->attributes($validated),
        );

        return redirect()
            ->route('projects.testing.index', [
                'project' => $project,
                'suite' => $testCase->test_suite_id,
            ])
            ->with('status', $testCase->displayKey().' updated.');
    }

    public function destroy(Request $request, Project $project, TestCase $testCase): RedirectResponse
    {
        Gate::authorize('manageTestCases', $project);

        $suiteId = $testCase->test_suite_id;
        $key = $testCase->displayKey();

        $this->saveTestCaseAction->delete($request->user(), $testCase);

        return redirect()
            ->route('projects.testing.index', ['project' => $project, 'suite' => $suiteId])
            ->with('status', $key.' deleted.');
    }

    /**
     * Normalize validated input into the action's attribute shape.
     *
     * @param  array<string, mixed>  $validated
     * @return array{title: string, description: ?string, status: string, severity: string, steps: list<array{action: string, expected_result: ?string}>}
     */
    private function attributes(array $validated): array
    {
        $steps = [];

        foreach ($validated['steps'] ?? [] as $step) {
            $action = trim((string) ($step['action'] ?? ''));

            if ($action === '') {
                continue;
            }

            $expected = trim((string) ($step['expected_result'] ?? ''));

            $steps[] = [
                'action' => $action,
                'expected_result' => $expected === '' ? null : $expected,
            ];
        }

        $description = trim((string) ($validated['description'] ?? ''));

        return [
            'title' => $validated['title'],
            'description' => $description === '' ? null : $description,
            'status' => $validated['status'],
            'severity' => $validated['severity'],
            'steps' => $steps,
        ];
    }
}
