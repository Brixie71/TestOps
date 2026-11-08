<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->query('search');

        $projects = Project::query()
            // ADAPT (items 3, 4): eager-load the owner relation (withTrashed
            // only if User uses SoftDeletes) and whatever carries ownership
            // type/status, matching your existing schema.
            ->with(['owner' => fn ($q) => $q->withTrashed()])
            // Visibility: ADAPT to your existing authorization service —
            // SUPER_ADMIN/SYSTEM_ADMIN see all; others see member projects only.
            // ->when(! $request->user()->hasRole('SUPER_ADMIN'), ...)
            ->when($search !== null && $search !== '', function ($query) use ($search): void {
                $term = '%'.mb_strtolower($search).'%';

                $query->where(function ($query) use ($term): void {
                    // ADAPT: confirm searchable columns (name + code assumed).
                    $query
                        ->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(code) LIKE ?', [$term]);
                });
            })
            ->orderBy('name')
            ->paginate()
            ->withQueryString();

        return view('projects.index', [
            'projects' => $projects,
            'search' => $search,
        ]);
    }

    public function show(Request $request, Project $project): View
    {
        // ADAPT: authorize via your existing policy/service. Guests never get
        // here (auth middleware); unauthorized users should abort(403).

        $user = Auth::user();

        return view('projects.show', [
            'project' => $project,

            // ADAPT (item 7, 8, 10): paginate members; resolve each member's
            // project role and job title per your pivot shape.
            'members' => $project->members()->paginate()->withQueryString(),

            // ADAPT (item 10): eligible ACTIVE members for adding, eligible
            // candidates for ownership, and the project's assignable roles.
            'eligibleUsers' => collect(),
            'eligibleOwners' => collect(),
            'projectRoles' => collect(),

            // ADAPT (item 9): resolve from existing policies/gates. Keys gate
            // UI affordances only — the mutation controllers still enforce.
            'can' => [
                'add_member' => false,
                'change_member_role' => false,
                'remove_member' => false,
                'assign_owner' => false,
                'transfer_owner' => false,
                'unassign_owner' => false,
                'change_assignment_type' => false,
                'toggle_auto_assignment' => false,
            ],
        ]);
    }
}
