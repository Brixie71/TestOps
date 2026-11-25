<?php

namespace App\Http\Controllers\Projects;

use App\Actions\ManageDefectAction;
use App\Http\Controllers\Controller;
use App\Models\Defect;
use App\Models\Project;
use App\Models\TestResult;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Thin controller for project defects: report a defect, edit its details,
 * and move it through its statuses. Reads gate on the viewDefects ability,
 * every mutation on manageDefects.
 */
class DefectController extends Controller
{
    public function __construct(
        private readonly ManageDefectAction $manageDefectAction,
    ) {}

    public function index(Request $request, Project $project): View
    {
        Gate::authorize('viewDefects', $project);

        return view('projects.defects.index', [
            'project' => $project,
            'defects' => $project->defects()
                ->with(['reporter', 'testResult.runCase.run'])
                ->orderByDesc('sequence')
                ->get(),
            // Only results from this project may be linked to a defect.
            'results' => TestResult::query()
                ->whereHas('runCase.run', fn ($query) => $query->where('project_id', $project->getKey()))
                ->with('runCase.run')
                ->orderByDesc('recorded_at')
                ->limit(200)
                ->get(),
            'canManage' => Gate::allows('manageDefects', $project),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manageDefects', $project);

        $defect = $this->manageDefectAction->create(
            $request->user(),
            $project,
            $this->validatedAttributes($request),
        );

        return redirect()
            ->route('projects.defects.index', $project)
            ->with('status', $defect->displayKey().' reported.');
    }

    public function update(Request $request, Project $project, Defect $defect): RedirectResponse
    {
        Gate::authorize('manageDefects', $project);

        $this->manageDefectAction->update(
            $request->user(),
            $defect,
            $this->validatedAttributes($request),
        );

        return redirect()
            ->route('projects.defects.index', $project)
            ->with('status', $defect->displayKey().' updated.');
    }

    public function transition(Request $request, Project $project, Defect $defect): RedirectResponse
    {
        Gate::authorize('manageDefects', $project);

        $validated = $request->validate([
            'status' => ['required', Rule::in(Defect::STATUSES)],
        ]);

        $this->manageDefectAction->transition($request->user(), $defect, $validated['status']);

        return redirect()
            ->route('projects.defects.index', $project)
            ->with('status', $defect->displayKey().' moved to '.str_replace('_', ' ', $validated['status']).'.');
    }

    /**
     * @return array{title: string, description: ?string, severity: string, test_result_id: ?int}
     */
    private function validatedAttributes(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'severity' => ['required', Rule::in(Defect::SEVERITIES)],
            'test_result_id' => ['nullable', 'integer'],
        ]);

        $description = trim((string) ($validated['description'] ?? ''));

        return [
            'title' => $validated['title'],
            'description' => $description === '' ? null : $description,
            'severity' => $validated['severity'],
            'test_result_id' => isset($validated['test_result_id']) ? (int) $validated['test_result_id'] : null,
        ];
    }
}
