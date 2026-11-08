<?php

namespace App\Http\Controllers\Projects;

use App\Actions\AddProjectMemberAction;
use App\Actions\ChangeProjectMemberRoleAction;
use App\Actions\RemoveProjectMemberAction;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Thin mutation layer for project membership: validates request shape and
 * delegates every business rule to the Stage 2 actions, which own the
 * transaction, project-row locking, authorization re-evaluation through
 * ProjectPolicy, invariants, and the audit records.
 */
class ProjectMembershipController extends Controller
{
    public function __construct(
        private readonly AddProjectMemberAction $addProjectMemberAction,
        private readonly ChangeProjectMemberRoleAction $changeProjectMemberRoleAction,
        private readonly RemoveProjectMemberAction $removeProjectMemberAction,
    ) {}

    public function add(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->addProjectMemberAction->execute(
            $request->user(),
            $project,
            User::query()->findOrFail($validated['user_id']),
            (int) $validated['role_id'],
            $validated['reason'],
        );

        return redirect()->back();
    }

    public function changeRole(Request $request, Project $project, User $member): RedirectResponse
    {
        $validated = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->changeProjectMemberRoleAction->execute(
            $request->user(),
            $project,
            $member,
            (int) $validated['role_id'],
            $validated['reason'],
        );

        return redirect()->back();
    }

    public function remove(Request $request, Project $project, User $member): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->removeProjectMemberAction->execute(
            $request->user(),
            $project,
            $member,
            $validated['reason'],
        );

        return redirect()->back();
    }
}
