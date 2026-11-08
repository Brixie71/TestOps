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

        $user = $request->user();

        $isSystemAdmin = $user->role?->scope === 'system'
            && $user->role?->code === 'SYSTEM_ADMIN';

        $systemAdminCanViewProjects = $isSystemAdmin
            && $this->permissionChecker->allows($user, 'projects.view');

        $projects = Project::query()
            // Soft-deleted owners stay resolvable: the directory must render
            // the amber "Deactivated" chip, never "No Admin Assigned".
            ->with(['owner' => fn ($q) => $q->withTrashed()])

            ->when(! $this->viewsAllProjects($user), function (Builder $query) use (
                $user,
                $isSystemAdmin,
                $systemAdminCanViewProjects,
            ): void {
                if ($isSystemAdmin) {
                    // SYSTEM_ADMIN must have the system-level view permission.
                    // Ownership alone does not bypass this requirement.
                    if (! $systemAdminCanViewProjects) {
                        $query->whereRaw('1 = 0');

                        return;
                    }

                    // The policy requires membership for restricted SYSTEM_ADMIN
                    // access, regardless of the project's ownership.
                    $query->whereHas('members', fn (Builder $memberQuery) => $memberQuery
                        ->whereKey($user->getKey()));

                    return;
                }

                $viewRoleIds = Role::query()
                    ->where('scope', 'project')
                    ->whereHas('permissions', fn (Builder $permissionQuery) => $permissionQuery
                        ->where('code', 'projects.view'))
                    ->select('id');

                // Ordinary users can view projects they own or projects where
                // their project-scoped role grants projects.view.
                $query->where(function (Builder $query) use ($user, $viewRoleIds): void {
                    $query
                        ->where('owner_id', $user->getKey())
                        ->orWhereHas('members', function (Builder $memberQuery) use ($user, $viewRoleIds): void {
                            $memberQuery
                                ->whereKey($user->getKey())
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
            'transfer_owner' => Gate::allows('manageOwnership', $project) && $project->owner_id !== null,
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

    /**
     * Cross-project directory visibility, mirroring ProjectPolicy::view():
     * SUPER_ADMIN sees all projects, and a SYSTEM_ADMIN holding either
     * existing system-scope management permission (projects.manage-members
     * / projects.manage-ownership) views cross-project without membership.
     * Everyone else — including a SYSTEM_ADMIN without those grants — is
     * scoped by the ordinary owner-or-member rules. PermissionChecker
     * requires a system-scope role for the grants, so a project-scoped
     * role can never obtain directory-wide visibility through this.
     */
    private function viewsAllProjects(User $user): bool
    {
        if ($this->permissionChecker->isSuperAdmin($user)) {
            return true;
        }

        $role = $user->role;

        if ($role === null || $role->scope !== 'system' || $role->code !== 'SYSTEM_ADMIN') {
            return false;
        }

        return $this->permissionChecker->allows($user, 'projects.manage-members')
            || $this->permissionChecker->allows($user, 'projects.manage-ownership');
    }
}
