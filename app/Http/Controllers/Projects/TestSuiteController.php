<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\TestSuite;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Thin controller for the project testing workspace: the suite browser and
 * suite create/update/delete. Every request is authorized through the
 * project policy; SYSTEM_ADMIN stays read-only for the testing domain.
 */
class TestSuiteController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        Gate::authorize('viewTestCases', $project);

        $suites = $project->testSuites()
            ->withCount('testCases')
            ->orderBy('name')
            ->get();

        $selectedSuite = $this->resolveSelectedSuite($suites, $request->query('suite'));

        $cases = $selectedSuite === null
            ? new Collection
            : $selectedSuite->testCases()
                ->with(['steps', 'author'])
                ->orderBy('sequence')
                ->get();

        return view('projects.testing.index', [
            'project' => $project,
            'suites' => $suites,
            'selectedSuite' => $selectedSuite,
            'cases' => $cases,
            'canManage' => Gate::allows('manageTestCases', $project),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manageTestCases', $project);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $suite = $project->testSuites()->create([
            'name' => $validated['name'],
            'description' => $this->normalizeText($validated['description'] ?? null),
        ]);

        return redirect()
            ->route('projects.testing.index', ['project' => $project, 'suite' => $suite->getKey()])
            ->with('status', 'Test suite created.');
    }

    public function update(Request $request, Project $project, TestSuite $testSuite): RedirectResponse
    {
        Gate::authorize('manageTestCases', $project);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $testSuite->update([
            'name' => $validated['name'],
            'description' => $this->normalizeText($validated['description'] ?? null),
        ]);

        return redirect()
            ->route('projects.testing.index', ['project' => $project, 'suite' => $testSuite->getKey()])
            ->with('status', 'Test suite updated.');
    }

    public function destroy(Request $request, Project $project, TestSuite $testSuite): RedirectResponse
    {
        Gate::authorize('manageTestCases', $project);

        // Cases are authored content: a suite is never deleted out from under
        // them. Runs will add the approved restrictive layer on top.
        if ($testSuite->testCases()->exists()) {
            throw ValidationException::withMessages([
                'suite' => 'Move or delete this suite\'s test cases before deleting the suite.',
            ]);
        }

        $testSuite->delete();

        return redirect()
            ->route('projects.testing.index', $project)
            ->with('status', 'Test suite deleted.');
    }

    /**
     * Resolve the visible suite, falling back to the first suite when the
     * requested one is absent or belongs to another project.
     *
     * @param  Collection<int, TestSuite>  $suites
     */
    private function resolveSelectedSuite(Collection $suites, mixed $requestedId): ?TestSuite
    {
        if (! is_numeric($requestedId)) {
            return $suites->first();
        }

        return $suites->firstWhere('id', (int) $requestedId) ?? $suites->first();
    }

    private function normalizeText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
