<?php

namespace App\Http\Controllers\Projects;

use App\Actions\AssignProjectOwnerAction;
use App\Actions\ChangeProjectAutoAssignmentSettingAction;
use App\Actions\ChangeProjectOwnerAssignmentTypeAction;
use App\Actions\TransferProjectOwnerAction;
use App\Actions\UnassignProjectOwnerAction;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Thin mutation layer for project ownership management: validates request
 * shape and delegates every business rule to the Stage 2 actions, which
 * own the transaction, project-row locking, authorization re-evaluation
 * through ProjectPolicy, invariants, and the audit records.
 *
 * Protected ownership fields (owner_id, owner_assignment_type,
 * auto_assign_representative_owner) are never accepted from the request —
 * the actions receive explicit scalar values only.
 */
class ProjectOwnershipController extends Controller
{
    public function __construct(
        private readonly AssignProjectOwnerAction $assignProjectOwnerAction,
        private readonly TransferProjectOwnerAction $transferProjectOwnerAction,
        private readonly UnassignProjectOwnerAction $unassignProjectOwnerAction,
        private readonly ChangeProjectOwnerAssignmentTypeAction $changeProjectOwnerAssignmentTypeAction,
        private readonly ChangeProjectAutoAssignmentSettingAction $changeProjectAutoAssignmentSettingAction,
    ) {}

    public function assign(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'assignment_type' => ['required', 'in:permanent,temporary'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->assignProjectOwnerAction->execute(
            $request->user(),
            $project,
            User::query()->findOrFail($validated['user_id']),
            $validated['assignment_type'],
            $validated['reason'],
        );

        return redirect()->back();
    }

    public function transfer(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'assignment_type' => ['required', 'in:permanent,temporary'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->transferProjectOwnerAction->execute(
            $request->user(),
            $project,
            User::query()->findOrFail($validated['user_id']),
            $validated['assignment_type'],
            $validated['reason'],
        );

        return redirect()->back();
    }

    public function unassign(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->unassignProjectOwnerAction->execute(
            $request->user(),
            $project,
            $validated['reason'],
        );

        return redirect()->back();
    }

    public function changeAssignmentType(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'assignment_type' => ['required', 'in:permanent,temporary'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->changeProjectOwnerAssignmentTypeAction->execute(
            $request->user(),
            $project,
            $validated['assignment_type'],
            $validated['reason'],
        );

        return redirect()->back();
    }

    public function changeAutoAssignment(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->changeProjectAutoAssignmentSettingAction->execute(
            $request->user(),
            $project,
            $validated['enabled'],
            $validated['reason'],
        );

        return redirect()->back();
    }
}
