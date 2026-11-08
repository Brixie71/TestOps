<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Read layer for the Projects module: the Projects directory and the
 * project details page.
 *
 * Authorization and visibility are backend-enforced: the policy is the
 * boundary for show(), and the directory is scoped per role in index().
 * The $can flags passed to the view gate UI affordances ONLY — the
 * mutation actions and controllers still enforce every request.
 */
class ProjectController extends Controller
{
    public function __construct(
        private readonly PermissionChecker $permissionChecker,
    ) {}

    public function index(Request $request): View
    {
        $search = $request->query('search');

        $projects = Project::query()
            // Soft-deleted owners stay resolvable: the directory must render
            // the amber "Deactivated" chip, never "No Admin Assigned".
            ->with(['owner' => fn ($q) => $q->withTrashed()])
            ->when(! $this->permissionChecker->isSuperAdmin($request->user()), function (Builder $query) use ($request): void {
                $viewRoleIds = Role::query()
                    ->where('scope', 'project')
                    ->whereHas('permissions', fn (Builder $permissionQuery) => $permissionQuery->where('code', 'projects.view'))
                    ->select('id');

                $query->where(function (Builder $query) use ($request, $viewRoleIds): void {
                    // Mirrors ProjectPolicy::view() for non-Super-Admins:
                    // owner OR member whose project-scoped role grants
                    // projects.view.
                    $query
                        ->where('owner_id', $request->user()->getKey())
                        ->orWhereHas('members', function (Builder $memberQuery) use ($request, $viewRoleIds): void {
                            $memberQuery
                                ->whereKey($request->user()->getKey())
                                ->whereIn('project_user.role_id', $viewRoleIds);
                        });
                });
            })
            ->when($search !== null && $search !== '', function (Builder $query) use ($search): void {
                $term = '%'.mb_strtolower($search).'%';

                $query->where(function (Builder $query) use ($term): void {
                    $query
                        ->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(code) LIKE ?', [$term]);
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('projects.index', [
            'projects' => $projects,
            'search' => $search,
        ]);
    }

    public function show(Request $request, Project $project): View
    {
        // Policy is the authorization boundary: unauthorized authenticated
        // users abort(403); guests never reach this.
        Gate::authorize('view', $project);

        $project->load(['owner' => fn ($q) => $q->withTrashed()]);

        // Active membership display: the SoftDeletes global scope on the
        // related User model already excludes soft-deleted members.
        $members = $project->members()
            ->orderBy('users.name')
            ->paginate(15)
            ->withQueryString();

        // Keyed by id: the member table resolves its project role through
        // $member->pivot->role_id (no projectRole accessor exists).
        $projectRoles = Role::query()
            ->where('scope', 'project')
            ->orderBy('name')
            ->get();

        // Eligible add-member candidates mirror ProjectMembershipRules:
        // active, non-soft-deleted accounts that are not already members.
        $eligibleUsers = User::query()
            ->where('account_status', 'active')
            ->whereNotIn('id', function (QueryBuilder $query) use ($project): void {
                $query->select('user_id')->from('project_user')->where('project_id', $project->getKey());
            })
            ->orderBy('name')
            ->get();

        // Eligible ownership candidates mirror assertEligibleOwner():
        // active members holding the canonical PROJECT_ADMIN project role.
        $adminRole = $projectRoles->firstWhere('code', 'PROJECT_ADMIN');

        $eligibleOwners = $adminRole === null
            ? collect()
            : $project->members()
                ->where('users.account_status', 'active')
                ->wherePivot('role_id', $adminRole->getKey())
                ->orderBy('users.name')
                ->get();

        $ownerActive = ($owner = $project->owner) !== null && ! $owner->trashed();

        $can = [
            // Adding a member always assigns a project role, so the view
            // affordance requires BOTH abilities (mirrors the action gate).
            'add_member' => Gate::allows('manageMembers', $project) && Gate::allows('assignRoles', $project),
            'change_member_role' => Gate::allows('assignRoles', $project),
            'remove_member' => Gate::allows('manageMembers', $project),
            'assign_owner' => Gate::allows('manageOwnership', $project) && $project->owner_id === null,
            'transfer_owner' => Gate::allows('manageOwnership', $project) && $ownerActive,
            'unassign_owner' => Gate::allows('manageOwnership', $project) && $project->owner_id !== null,
            'change_assignment_type' => Gate::allows('manageOwnership', $project) && $ownerActive,
            'toggle_auto_assignment' => Gate::allows('manageOwnership', $project),
        ];

        return view('projects.show', [
            'project' => $project,
            'members' => $members,
            'projectRoles' => $projectRoles,
            'eligibleUsers' => $eligibleUsers,
            'eligibleOwners' => $eligibleOwners,
            'can' => $can,
        ]);
    }
}
