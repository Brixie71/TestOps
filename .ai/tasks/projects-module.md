# TestOps — Project Management Module: Implementation Brief

> **How to use this brief:** complete reference implementations for every file in Section 4 were provided in a separate handoff. Where a file already exists on disk, VERIFY it against this brief — do not rewrite working code. Where a file is missing, implement it from the specs here. Every backend assumption is marked `// ADAPT:` and consolidated in Section 3, which MUST be resolved before the mutation dialogs are considered done.

## 1. Context & Objective

TestOps is a Laravel 13 / PHP 8.5 / Blade / Tailwind CSS 4 / Vite / PostgreSQL software testing management platform with native session authentication and an RBAC foundation (Role and Permission models; SUPER_ADMIN system role).

The backend ALREADY implements: projects, membership, ownership (permanent / temporary / none), automatic owner assignment, authorization, concurrency protection, and audit logging — including eight POST mutation routes and controllers.

**Objective — build the frontend read layer for the Project Management module:**

1. **Projects Directory** (`projects.index`) — searchable, paginated table.
2. **Project Details** (`projects.show`) — Overview, Members, Ownership.
3. **Sidebar "Projects" module** — data-driven, future-groupable.
4. **Eight mutation dialogs** — POSTing to the existing backend routes.
5. **Shared components** for reuse by future modules.

Visual references (the design system to extend, NOT redesign): the login page, `layouts/app.blade.php`, the settings module, and the User Directory (`resources/views/settings/users.blade.php` — the canonical table/search/pagination pattern).

Authorization levels the UI must accommodate (display only — never enforce):

- **SUPER_ADMIN** — all projects, unrestricted management.
- **SYSTEM_ADMIN** — all projects under the access policy, manages membership and ownership, does NOT receive SUPER_ADMIN privileges.
- **PROJECT_ADMIN** — member projects only, manages membership/roles per project permissions, CANNOT modify designated ownership.

## 2. Ground Rules

- **Do not modify:** mutation controllers, FormRequests, or the eight existing mutation routes; models, migrations, seeders, RBAC; authentication or session config; the Settings module, Dashboard, login, or theme toggle mechanism.
- **Do not:** install packages; introduce a frontend framework (Blade + vanilla JS only); implement authorization logic in the frontend (`$can` flags gate affordances ONLY — the backend enforces); invent endpoints, actions, or business rules beyond Section 9; add spinners/skeletons (server-rendered app — the state model is: validation failure → dialog reopens with errors; success → `session('status')` banner; unauthorized → 403; missing project → 404).
- Every mutation form must: be `method="POST"`, include `@csrf`, post to the named route, and **require a reason field** (audit trail).
- **Discipline:** read `.ai/rules/index.md` first if present; work incrementally; report ACTUAL command output — never claim verification that was not executed.

## 3. Backend Contract Verification — MANDATORY FIRST STEP

Run `php artisan route:list --except-vendor -v` and read the mutation controllers, Project model, and relevant migrations. Verify each assumption; apply the "if different" rule; record findings in your report.

| # | Assumption in handoff code | Verify via | If different |
|---|---|---|---|
| 1 | Mutation field names: `user_id`, `project_role_id`, `assignment_type`, `reason`, `enabled` | Mutation controllers / FormRequests | Rename fields in the dialog components (§8.3) to match validation |
| 2 | Mutation routes accept a `{project}` parameter | `route:list -v` | Adjust every `route('projects.…', $project)` call |
| 3 | `$project->owner` is a belongsTo User relation; User uses SoftDeletes | Project / User models | Update eager-loads and the `$ownerDeactivated` checks |
| 4 | `$project->ownership_type` ∈ `permanent \| temporary \| null` | Project migration/model | Update ownership-badge map (§7.5) and dialog radio values |
| 5 | `$project->auto_assign_owner` is boolean | Project migration/model | Update Overview pill and auto-assignment dialog defaults |
| 6 | `$project->status` values (e.g. `active`, `archived`) | Project migration/enum | Extend the `x-status-badge` match map (§7.3) |
| 7 | Member's project role via `$member->projectRole->name` | ProjectRelationshipTest / pivot shape | Adjust the Members table accessor |
| 8 | Job title via `$member->job_title ?? $member->department` | users migration | Adjust the column source |
| 9 | `$can` flag keys (see §9) | Existing policies / gates / services | Wire in `show()`; keep the keys identical |
| 10 | `$eligibleUsers` / `$eligibleOwners` / `$projectRoles` provided by `show()` | Backend membership directory contract | Populate in `show()`; the directory returns ACTIVE members only |

## 4. File Plan

| File | Action | Spec |
|---|---|---|
| `routes/web.php` | Modify | Add 2 GET read routes inside the existing auth group (§5) |
| `app/Http/Controllers/ProjectController.php` | Create | Thin read controller, all assumptions marked `// ADAPT:` (§5) |
| `resources/views/components/sidebar.blade.php` | Modify | Data-driven; adds Projects; preserves drawer contract (§6) |
| `resources/views/components/pagination.blade.php` | Create | Shared windowed pagination (§7.1) |
| `resources/views/components/empty-state.blade.php` | Create | §7.2 |
| `resources/views/components/status-badge.blade.php` | Create | §7.3 |
| `resources/views/components/dialog.blade.php` | Create | Modal shell with validation-reopen (§7.4) |
| `resources/views/components/projects/ownership-badge.blade.php` | Create | Exact semantic labels (§7.5) |
| `resources/views/projects/index.blade.php` | Create | Directory (§8.1) |
| `resources/views/projects/show.blade.php` | Create | Details + dialogs + script (§8.2–8.3) |
| `resources/views/components/projects/dialogs/` × 8 | Create | §8.3 |

`resources/views/settings/users.blade.php` stays untouched (tested, working).

## 5. Routes & Controller

`routes/web.php` — add inside the existing `Route::middleware('auth')->group(...)`:

```php
    /*
    | Projects (read pages) — mutation routes already exist in the backend.
    */
    Route::get('projects', [\App\Http\Controllers\ProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/{project}', [\App\Http\Controllers\ProjectController::class, 'show'])->name('projects.show');
```

`app/Http/Controllers/ProjectController.php`:

```php
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
            // ADAPT (3): eager-load the owner relation (withTrashed only if
            // User uses SoftDeletes).
            ->with(['owner' => fn ($q) => $q->withTrashed()])
            // ADAPT: visibility — SUPER_ADMIN/SYSTEM_ADMIN see all projects;
            // everyone else sees projects they are a member of, via the
            // existing authorization service.
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
        // ADAPT: authorize via the existing policy/service — unauthorized
        // authenticated users abort(403). Guests never reach this.

        return view('projects.show', [
            'project' => $project,
            // ADAPT (7, 8): paginate members; resolve role + job title per pivot.
            'members' => $project->members()->paginate()->withQueryString(),
            // ADAPT (10): eligible ACTIVE members for adding, ownership
            // candidates, assignable project roles.
            'eligibleUsers' => collect(),
            'eligibleOwners' => collect(),
            'projectRoles' => collect(),
            // ADAPT (9): resolve from existing policies/gates. UI affordances
            // only — mutation controllers still enforce.
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
```

Search guards are deliberately strict (`!== null && !== ''`) — PHP treats the string `'0'` as falsy; this project has a regression test defending that rule.

## 6. Sidebar

Full replacement of `resources/views/components/sidebar.blade.php`. The outer contract MUST be preserved exactly (the drawer script in `layouts/app` depends on it): `id="app-sidebar"`, `aria-label="Application sidebar"`, the off-canvas classes (`invisible fixed … -translate-x-full … lg:sticky lg:visible lg:translate-x-0`), and the empty-`<nav>`-to-filled transition.

```blade
{{-- Global authenticated sidebar. Data-driven: future grouped navigation
     (parent item + children) is an array edit, not a structural redesign. --}}

@php
    $isActive = fn (string $pattern): bool => request()->routeIs($pattern);

    $groups = [
        [
            'items' => [
                [
                    'label' => 'Projects',
                    'route' => 'projects.index',
                    'active' => $isActive('projects.*'),
                    'children' => [], // Reserved for grouped navigation — do not populate yet.
                ],
            ],
        ],
    ];
@endphp

<aside
    id="app-sidebar"
    aria-label="Application sidebar"
    class="invisible fixed inset-y-0 left-0 z-30 flex w-64 shrink-0 -translate-x-full flex-col border-r border-slate-200 bg-white transition-[transform,visibility] duration-200 lg:sticky lg:visible lg:bottom-auto lg:top-16 lg:z-auto lg:h-[calc(100vh-4rem)] lg:translate-x-0 lg:self-start"
>
    <nav aria-label="Main navigation" class="flex-1 overflow-y-auto px-3 py-4">
        <ul class="space-y-1">
            @foreach ($groups as $group)
                @foreach ($group['items'] as $item)
                    <li>
                        <a
                            href="{{ route($item['route']) }}"
                            @if ($item['active']) aria-current="page" @endif
                            @class([
                                'flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500',
                                'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' => $item['active'],
                                'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100' => ! $item['active'],
                            ])
                        >
                            <svg class="h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                            </svg>
                            {{ $item['label'] }}
                        </a>

                        @if (! empty($item['children']))
                            <ul class="mt-1 ml-7 space-y-1">
                                @foreach ($item['children'] as $child)
                                    <li>
                                        <a href="{{ route($child['route']) }}" @if ($child['active']) aria-current="page" @endif
                                            class="block rounded-lg px-3 py-1.5 text-sm text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100">
                                            {{ $child['label'] }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            @endforeach
        </ul>
    </nav>
</aside>
```

If other sessions added sidebar items, MERGE them into `$groups` — that array is now the single source of navigation. Do not implement dropdown/group UI yet.

## 7. Shared Components

### 7.1 `components/pagination.blade.php` — props: `$paginator`, `$label` (default `'results'`)

Windowed page list (all pages when ≤ 7; otherwise `1 … c-1 c c+1 … n`), Prev/Next always visible, page numbers hidden `md:flex`, `aria-current="page"` on current, `rel="prev/next"`, disabled spans with `aria-disabled="true"`. Filter preservation comes from the controllers' `withQueryString()` — no view-side appends.

### 7.2 `components/empty-state.blade.php` — props: `$heading`, `$description` (optional); slots: `icon` (optional, `@isset`-guarded), `action` (optional)

Centered, muted icon tile, `text-sm` heading, `text-slate-500` description.

### 7.3 `components/status-badge.blade.php` — prop: `$status`

Pill with dot. `// ADAPT (6):` map — `active` → emerald; unknown → neutral slate; label `ucfirst()`. Extend the match map when the real status enum is confirmed.

### 7.4 `components/dialog.blade.php` — props: `$id`, `$title`, `$description` (optional), `$context` (optional)

`role="dialog"`, `aria-modal`, labelled/described by id. **Server-rendered reopen**: visible only when `$context !== null && $errors->any() && old('dialog') === $context`; otherwise hidden. Backdrop and close button carry `data-dialog-close`. Panel: `max-w-lg rounded-xl` card; body bottom-sheet on mobile (`items-end`) → centered `sm:items-center`.

### 7.5 `components/projects/ownership-badge.blade.php` — prop: `$state`

**EXACT semantic labels, no expiry language anywhere:**

| state | label | color |
|---|---|---|
| `permanent` | Assigned Administrator | indigo |
| `temporary` | Temporary Admin | sky (deliberately NOT amber — amber means pending/Invited; temporary ownership is a valid operating state) |
| `none` / unknown | No Admin Assigned | slate |

A soft-deleted owner must render as an **amber `Deactivated` chip** in the views — NEVER as "No Admin Assigned" (ownership information is preserved).

## 8. Page Views & Dialogs

### 8.0 Design vocabulary (match the User Directory exactly)

- card = `rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900`
- table head = `bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-950/60 dark:text-slate-400`
- rows `divide-y divide-slate-100 dark:divide-slate-800` with hover tint
- inputs = `rounded-lg border-slate-200 dark:border-slate-700 … focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20`
- buttons — primary `bg-indigo-600 hover:bg-indigo-500 text-white`, secondary bordered slate, danger `bg-red-600`, ghost text links
- user/owner chips = indigo square with first initial; project codes = `font-mono text-[11px]` slate chip
- Every new utility class requires `npm.cmd run build` before it exists in the bundle.

### 8.1 `projects/index.blade.php`

Extends `layouts.app`. Structure: header (h1 "Projects" + description) → card with GET search form (name or code, `autocomplete="off"`, Search button, conditional Clear) → summary row ("Showing X–Y of Z projects", `aria-live="polite"`) → table (Project = name link + code chip; Description `line-clamp-2 max-w-xs`; Status badge; Administrator cell; open chevron with `aria-label`) → `x-pagination` → empty states.

Critical logic:

```blade
@php
    $hasFilters = $search !== null && $search !== '';
    $owner = $project->owner;
    $ownerDeactivated = $owner && method_exists($owner, 'trashed') && $owner->trashed();
@endphp
```

Administrator cell: owner present + deactivated → chip + name + amber "Deactivated" chip; owner active → chip + name + `x-projects.ownership-badge :state="$project->ownership_type"`; no owner → `<x-projects.ownership-badge state="none" />`. Empty states: filtered → "No matching projects" + Clear action; unfiltered → "No projects yet".

### 8.2 `projects/show.blade.php`

Extends `layouts.app`. Back link → header (name + code chip + status badge) → `session('status')` success banner → error banner only when `! old('dialog')` → three sections:

- **Overview** (`<dl>` card, 2-col `sm:grid-cols-2`): Description; Designated administrator (chip + name + Deactivated chip when trashed); Ownership assignment (badge / "Retained — owner is deactivated" / none-badge); Automatic owner assignment (Enabled=emerald / Disabled=slate pill).
- **Members**: heading + description; `add_member`-gated "Add member" button (`data-dialog-target="dialog-add-member"`); table Member / Email / Job title / Project role / Actions — Change role + Remove buttons per row, each gated by its `$can` flag and carrying `data-user-id="{{ $member->id }}"` and `data-member-name="{{ $member->name }}"`; the Actions column header renders only when either flag is true; `x-pagination` when `hasPages()`; empty state.
- **Ownership**: owner block (large chip, name, email or "Deactivated account") + badges incl. "Auto-assignment: Enabled/Disabled"; action row gated by `$can` AND state: Assign owner only when no owner; Transfer owner and Change assignment type only when an ACTIVE owner exists; Unassign owner (danger styling) whenever an owner exists; "Automatic assignment…" always when flagged. Footnote when `ownership_type === 'temporary'`: "Temporary ownership remains in effect until changed. There is no automatic expiration."

### 8.3 Dialogs — eight components in `components/projects/dialogs/`

Each wraps `x-dialog` with unique id + context. Shared skeleton rules: hidden `name="dialog"` context field; error block rendered only when `old('dialog') === '<context>'`; `old()` repopulation on every input; **required reason textarea** (rows=3, maxlength=1000) labelled "Recorded in the project audit log."; Cancel (`data-dialog-close`) + submit; danger styling ONLY for remove-member and unassign-owner.

Canonical example — `add-member.blade.php`:

```blade
<x-dialog id="dialog-add-member" context="add-member" title="Add member" description="Grant an eligible user access to this project.">
    <form method="POST" action="{{ route('projects.members.add', $project) }}" class="space-y-4">
        @csrf
        <input type="hidden" name="dialog" value="add-member">

        @if ($errors->any() && old('dialog') === 'add-member')
            <div role="alert" class="rounded-lg border border-red-500/25 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-400">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            </div>
        @endif

        <div>
            <label for="add-member-user" class="block text-sm font-medium text-slate-700 dark:text-slate-300">User</label>
            <select id="add-member-user" name="user_id" required class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Select a user&hellip;</option>
                @foreach ($eligibleUsers as $candidate)
                    <option value="{{ $candidate->id }}" @selected((string) old('user_id') === (string) $candidate->id)>{{ $candidate->name }} ({{ $candidate->email }})</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="add-member-role" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Project role</label>
            <select id="add-member-role" name="project_role_id" required class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Select a role&hellip;</option>
                @foreach ($projectRoles as $role)
                    <option value="{{ $role->id }}" @selected((string) old('project_role_id') === (string) $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="add-member-reason" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Reason <span class="text-red-500" aria-hidden="true">*</span></label>
            <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">Recorded in the project audit log.</p>
            <textarea id="add-member-reason" name="reason" required rows="3" maxlength="1000" class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">{{ old('reason') }}</textarea>
        </div>

        <div class="flex justify-end gap-2.5 pt-1">
            <button type="button" data-dialog-close class="rounded-lg border border-slate-200 px-3.5 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Cancel</button>
            <button type="submit" class="rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white transition-colors hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">Add member</button>
        </div>
    </form>
</x-dialog>
```

The remaining seven follow the same skeleton:

| File | context | Route | Fields beyond reason | Notes |
|---|---|---|---|---|
| `change-role` | `change-role` | `projects.members.change-role` | hidden `user_id` (data-fill), `project_role_id` select | Confirms member name via `data-fill="member-name"` |
| `remove-member` | `remove-member` | `projects.members.remove` | hidden `user_id` (data-fill) | DANGER submit; consequence copy |
| `assign-owner` | `assign-owner` | `projects.ownership.assign` | `user_id` select from `$eligibleOwners`, `assignment_type` radios (permanent default) | |
| `transfer-owner` | `transfer-owner` | `projects.ownership.transfer` | `user_id` ("New owner"), `assignment_type` radios (pre-check current type) | Shows current owner |
| `unassign-owner` | `unassign-owner` | `projects.ownership.unassign` | — | DANGER; "will show No Admin Assigned until a new owner is assigned" |
| `assignment-type` | `assignment-type` | `projects.ownership.change-type` | `assignment_type` radios | Radio copy: temporary = "remains in effect until changed" |
| `auto-assignment` | `auto-assignment` | `projects.ownership.change-auto-assignment` | `enabled` radios (1/0, pre-checked from `$project->auto_assign_owner`) | Description states it is separate from the current assignment |

Dialog script (in `show.blade.php`) — required behaviors: open via `data-dialog-target` (populates `[data-fill="user-id"]` values and `[data-fill="member-name"]` text from trigger data attributes; focuses first field); close via any `[data-dialog-close]` (Cancel, X, backdrop); Escape closes; Tab/Shift+Tab focus trap; focus returns to the triggering button; body scroll locks while open — including on page load when a dialog was server-rendered open after a validation failure.

## 9. Page-to-Backend Integration Mapping

**Reads:**

| Page | Route | Data required | Auth handling |
|---|---|---|---|
| Directory | `projects.index` | `$projects` (paginated, `withQueryString()`, owner eager-loaded incl. trashed), `$search` | Backend filters visibility per role |
| Details | `projects.show` | `$project`, `$members` (paginated), `$eligibleUsers`, `$eligibleOwners`, `$projectRoles`, `$can` | Backend aborts 403; UI renders flags |

**Mutations (all POST, all require `reason`):**

| UI action | Route name | Posted fields | Gate flag |
|---|---|---|---|
| Add member | `projects.members.add` | `user_id`, `project_role_id`, `reason` | `add_member` |
| Change role | `projects.members.change-role` | `user_id`, `project_role_id`, `reason` | `change_member_role` |
| Remove member | `projects.members.remove` | `user_id`, `reason` | `remove_member` |
| Assign owner | `projects.ownership.assign` | `user_id`, `assignment_type`, `reason` | `assign_owner` |
| Transfer owner | `projects.ownership.transfer` | `user_id`, `assignment_type`, `reason` | `transfer_owner` |
| Unassign owner | `projects.ownership.unassign` | `reason` | `unassign_owner` |
| Change assignment type | `projects.ownership.change-type` | `assignment_type`, `reason` | `change_assignment_type` |
| Auto-assignment | `projects.ownership.change-auto-assignment` | `enabled`, `reason` | `toggle_auto_assignment` |

Note: the hidden `dialog` marker field travels with each submission. Default Laravel validation ignores extras; if any FormRequest is strict, switch the reopen mechanism to a session flash instead of changing the backend contract.

## 10. Verification & Out of Scope

Run and report ACTUAL output:

```
php artisan route:list --except-vendor -v     # 2 read routes + reconcile 8 mutation URIs
php artisan view:cache && php artisan view:clear   # compiles all Blade — catches syntax slips
php artisan test --compact                    # baseline: 117 passed, 252 assertions
.\vendor\bin\pint.bat app\Http\Controllers\ProjectController.php
npm.cmd run build                             # new utilities enter the CSS bundle
```

Manual matrix (`php artisan serve` + DevTools device toolbar):

1. Directory: search incl. `"0"` and Clear; pagination preserves `?search=`.
2. All four ownership renderings: permanent / temporary / none / Deactivated owner (deactivated must NEVER display "No Admin Assigned").
3. Dialogs: open via trigger, close via Cancel/X/backdrop/Escape; focus returns to trigger; per-row values populate Change role / Remove.
4. Submit a dialog empty → Laravel validation → the SAME dialog reopens with the error summary and the reason text preserved.
5. Flag-disabled actions never render; unauthorized user → 403; guest → login.
6. Light AND dark themes on both pages; 375px width — tables scroll inside their card, no page overflow; dialogs behave as bottom sheets.
7. Dashboard and Settings unaffected; sidebar drawer still works on mobile.

**Out of scope (do not build):** grouped sidebar dropdowns (structure reserved via `children`), any new endpoints or mutation controllers, project CRUD, business logic in Blade, dashboard widgets, frontend frameworks.

Suggested commit:

```
feat(projects): add Projects Directory, Project Details, and ownership/membership dialogs
```

---

## Addendum — handoff-state notes (written 2026-11-08; verify, don't trust)

Companion inspection report: `.ai/tasks/projects-read-inspection-4c.md` — records the Part 4C read attributes and the open Decisions A–D referenced by this brief. Read it before Section 3.

Some of this brief's Section 4 files were already wired into the repo by a concurrent session. Verify, do not rewrite:

- `routes/web.php` — the 2 GET read routes (`projects.index`, `projects.show`) are already registered inside the auth group, now behind an interim `super.admin` middleware (**Decision E**, below); all 10 project routes (2 GET + 8 POST) resolve via `php artisan route:list --except-vendor --path=projects`. **The corrected POST route names in §8.3/§9 (`projects.ownership.change-type`, `projects.ownership.change-auto-assignment`) are pre-verified facts from `route:list` — no reconciliation needed for them.** (The `changeRole` controller-method name is a non-issue: `route()` resolves by route name, not method name.) No dialog components exist on disk yet, so there is nothing to patch in components — use the names in §8.3/§9 verbatim when building the eight dialogs.
- `app/Http/Controllers/ProjectController.php` — exists as a conservative stub: `index()`/`show()` present but `show()` has no authorization call and all eight `$can` flags are hard-coded `false`, data collections empty, everything marked `// ADAPT:`. Treat as the §5 starting point to be completed, not as working code.
- `resources/views/projects/index.blade.php`, `show.blade.php`, and `components/projects/ownership-badge.blade.php` EXIST (concurrent-session work — verify against §8/§7.5, don't rewrite blindly). `components/sidebar.blade.php` — the §6 implementation is already applied. The four §7 shared components have been RECONCILED to the §4 paths: `components/dialog.blade.php`, `components/empty-state.blade.php`, `components/pagination.blade.php` (the mis-placed "pagenation" copy moved and renamed), `components/status-badge.blade.php`; the empty mis-placed placeholder in `components/projects/dialogs/dialog.blade.php` was removed. Components for Future-work note: `show.blade.php` already renders `<x-projects.dialogs.* />` × 8, but the eight dialog files DO NOT EXIST yet — `php artisan view:cache` currently fails on `projects.dialogs.add-member` for exactly that reason; creating the eight dialogs in `components/projects/dialogs/` (§8.3, using the §9 route names verbatim) is the fix.

- **Decision E — interim read-route lock (APPLIED, pending removal).** `index()` currently lists all projects to any authenticated user and `show()` returns member data without authorization. Until real visibility scoping and show()-level authorization land, both GET read routes carry `middleware('super.admin')` — non-Super-Admins get 403 from the sidebar link (honest backend enforcement for an interim state). Removing this middleware is part of OpenCode's wire-the-real-scope task, not a separate step.
- Suite baseline at brief-snapshot: `php artisan test --compact` → **429 passed / 1108 assertions** (supersedes the "117 passed / 252 assertions" figure in §10 — that was an earlier-stage number).
- Constraint: no backend, model, policy, migration, or seeder changes are authorized by this brief; if a §3 assumption fails, report it and adapt the frontend only.
- Discipline reminders already stated in §2 apply to the addendum too: report ACTUAL output, never stage/commit/push without explicit user authorization, never touch `database/seeders/TestUserSeeder.php`.

## Addendum 2 — read-layer completion (written 2026-11-08, after execution)

- **`maxlength="500"` supersedes §8.3's 1000.** The backend reason validation caps at 500; all eight dialogs ship with `maxlength="500"`. A later reader must NOT "restore" 1000. Field names are the VERIFIED backend contract: `role_id` (not `project_role_id`), `ownership` columns `owner_assignment_type` / `auto_assign_representative_owner`, member-role display resolves via the controller's id-keyed roles map (`$projectRoles->get($member->pivot->role_id)?->name`) — there is no `projectRole` accessor.
- **`{member}` binds `User` by id** (no custom `getRouteKeyName`). Change-role and remove-member carry the target in the URL via a server-side `route(...)` template holding the literal `__MEMBER__` placeholder (`data-action-template`), a hidden `dialog_member` (`data-fill="member-key"`, restored from `old('dialog_member')`), and a hidden `member_name` restore field. The page script rewrites the form action on BOTH open paths: trigger-open and the server-rendered validation-reopen. No dead posted `user_id` in those two forms.
- **Decision E closed.** The interim `Route::middleware('super.admin')` wrapper on the two GET routes was replaced by `Route::middleware('active.account')` (stack parity with the mutation routes) AFTER the read tests passed against the scoped controller. Exact boundary diff: `super.admin` → `active.account` on `projects.index` and `projects.show` (comment block updated; route names/URIs unchanged).
- **Dialogs render only when their `$can` flag is true** (UI affordance gating now covers dialog DOM, not just triggers). Unassign-owner dialog copy deliberately avoids the literal "No Admin Assigned" label so the deactivated-owner rendering assertions hold.
- Final suite state: `php artisan test --compact` → **444 passed / 1171 assertions** (baseline 429 / 1108 + 15 new read tests in `tests/Feature/Projects/ProjectReadHttpTest.php`; the interim-lock 403s observed mid-session cleared exactly at the lock removal).
