# Stage 2 Part 4C — Project Read/Display Integration Inspection Report

**(Read-only — no files touched this round; Git status confirmed below.)**

> Companion to `.ai/tasks/projects-module.md`. This document records the approved
> inspection findings and the open Decisions A–D referenced by the brief.

## 1. Existing implementation findings (exact paths)

| Area | Finding |
|---|---|
| Routes | `routes/web.php` — Part 4B added the 8 membership/ownership POST routes under `auth` + `active.account`. **No project listing / detail routes exist.** |
| Models | `app/Models/Project.php` — fillable `[name, code, description, status]` (factory default `status: 'active'`); `owner()` belongsTo, `members()` belongsToMany `withPivot('role_id')` + timestamps; `auto_assign_representative_owner` bool cast; not soft-deletable. `app/Models/User.php` — `role()`, `projects()` (pivot `role_id`), SoftDeletes; displayable profile fields from migration `2026_11_04_070639`: `contact_number`, `job_title`, `department`, plus `name`/`email`. |
| Policy | `app/Policies/ProjectPolicy.php` — `view()` exists via `canPerform('projects.view')`: owner OR member whose project role holds `projects.view` (SYSTEM_ADMIN requires **membership** in `hasProjectAccess`; no cross-project view grant). **No `viewAny` exists.** `manageMembers`/`assignRoles`/`manageOwnership` as previously implemented. |
| PermissionChecker | `app/Services/Authorization/PermissionChecker.php` — system-scope only: `isSuperAdmin()` + `role->permissions()` lookup. `projects.view` permission exists (PROJECT_ADMIN holds it via ProjectRoleSeeder); SYSTEM_ADMIN holds only `projects.manage-members` + `projects.manage-ownership`. |
| Read precedent | `app/Http/Controllers/Settings/UserDirectoryController.php` — the only read/page controller: inline `$request->validate()` for filters, query builder with `when()`, `orderBy`, `paginate(15)->withQueryString()`, passes `canManageAll => PermissionChecker::isSuperAdmin($user)` to the view for control visibility. `PermissionChecker::allows` is authoritatively system-scope. |
| Views/layouts | `resources/views/projects/**` does **not exist**. `layouts/app.blade.php` is the authenticated layout (dashboard, profile) with the theme toggle. `components/sidebar.blade.php` is structurally present but **deliberately empty** with the comment "navigation items will be introduced in a later development stage." `components/navbar.blade.php` is completed. Settings area (`components/settings/sidebar.blade.php`) is data-driven with role-aware hooks and SUPER_ADMIN-gated. |
| Actions (display inputs) | All eight Part 2/3 actions return the reloaded `Project`; authorization + invariants live there. Part 4B controllers `app/Http/Controllers/Projects/{ProjectMembershipController,ProjectOwnershipController}.php` and their route names are the mutation endpoints the detail page's controls would target. |
| Tests | `tests/Feature/Settings/UserLifecycleHttpTest.php` + `tests/Feature/Projects/Project{Membership,Ownership}HttpTest.php` establish per-area HTTP feature conventions with `createSuperAdmin()`/`createSystemAdmin()` helpers and server-side assertions. |

## 2. Current read authorization behavior

- Per-project `view` is **membership- or owner-gated**: plain `Gate::authorize('view', $project)` works today, but SYSTEM_ADMIN can only view projects they are a member of; SA bypass covers everything.
- There is **no** list-level ability, no project route, no controller, no view. A directory view would need either a controller-side filter (member projects only, full list for SA — the `UserDirectoryController` + `canManageAll` pattern) or a new `viewAny` policy ability (requires approval + a system permission wiring decision).

## 3. Proposed read-access matrix

| Viewer | Project directory | Project details | Membership display | Management controls |
|---|---|---|---|---|
| SUPER_ADMIN | All projects | All projects | Full | All (per ability flags) |
| SYSTEM_ADMIN | All projects (recommended; cross-project authority precedent) | **Member projects today; all projects only with approved policy extension (Decision A)** | Full | All |
| PROJECT_ADMIN / member | Own member projects | Own member projects | Full | Per project-scoped permissions |
| Other project roles | Own member projects | Own member projects (if role holds `projects.view`) | Full | None unless role grants |
| Non-member | Not listed | 403 (`view` denies) | none | none |
| Guest | login redirect | login redirect | — | — |
| Deactivated user | `active.account` → session logout → login redirect | same | — | — |

No rights invented beyond the two flagged decisions; everything else is exactly current policy behavior.

## 4. Open decisions requiring approval

1. **Decision A — SYSTEM_ADMIN cross-project detail view.** `view()` currently requires membership for SYS, so a directory that lists all projects for SYS would contain 403 links. Option 1 (recommended, smallest): extend `view()` with the existing `hasSystemGrant` pattern (one line, mirrors `manageMembers`) — SYS then views any project without membership. Option 2: SYS directory shows member projects only. Either way, actions/policies rest unchanged for mutations.
2. **Decision B — directory listing rule.** Option 1 (recommended): membership-scoped list for everyone except SA (all) + SYS (all, if Decision A.1 passes). Option 2: add `viewAny()` ability to the policy (needs a decision on which system permission anchors it — `projects.manage-members`/`projects.manage-ownership` are the only seeded SYS grants).
3. **Decision C — navigation.** The global sidebar is explicitly marked as deferred-empty. Add a single "Projects" item during this stage (my recommendation, since this stage ships the pages), or keep deferred.
4. **Decision D — orphaned owner edge.** `Project::owner` resolves to `null` for a soft-deleted designated owner (bounded state: `owner_id` set, FK prevents permanent deletion, so the row exists). Display defensively as the "No Admin Assigned" indicator with the raw `owner_id` hidden, or resolve with `withTrashed()`. Recommendation: resolve the owner user with `withTrashed()` when `owner_id` is non-null but the relation resolves null, and label the member row "Deactivated".
5. **Decision E — interim read-route lock (APPLIED 2026-11-08).** The `projects.index` / `projects.show` routes initially landed with no authorization: `index()` lists all projects to any authenticated user and `show()` returns `$project->members()` with no `Gate::authorize` call. Interim lock applied: both GET routes wrapped in `Route::middleware('super.admin')` (same alias as the settings group). Non-Super-Admins get 403 from the sidebar link until real authorization lands — accepted interim trade-off. OpenCode's first implementation task is to wire real visibility scope in `index()`, authorization in `show()`, and **remove** this middleware.

## 5. Proposed architecture (smallest, conventions-compliant)

- **Routes** (added to the existing `auth` + `active.account` group):
  - `GET projects` → `projects.index` → `ProjectController::index`
  - `GET projects/{project}` → `projects.show` → `ProjectController::show`
- **Controller**: `app/Http/Controllers/Projects/ProjectController.php` — one class, `index()` + `show()` (matches the controllers rule's standard resource actions; mirrors `UserDirectoryController` style: inline filter validation, query builder, `paginate(15)->withQueryString()`). Membership display lives **on the detail page** (a separate members endpoint isn't warranted for one eager-loadable relation). No mutation logic; management control visibility computed with `Gate::allows` mirrors of the action requirements (add = `manageMembers && assignRoles`, change-role = `assignRoles`, remove = `manageMembers`, ownership = `manageOwnership` type flag), passed as scalar flags — **not** the authorization boundary.
- **Views** (new): `resources/views/projects/index.blade.php` and `resources/views/projects/show.blade.php`, both `@extends('layouts.app')`, no new components; ownership indicator mapping exactly as required (`permanent` → Assigned Administrator, `temporary` → Temporary Admin, NULL/none → No Admin Assigned).
- **Navigation** (if Decision C.1): a single data-driven edit to `components/sidebar.blade.php` adding the Projects link — no settings-sidebar involvement (projects are workspace, not settings).
- **No policy/seeders/actions/models changes** except the possible one-line Decision A.1.

## 6. Query & data-loading strategy

- **Directory**: one paginated query. Members list via `auth()->user()->projects()` pivot join (no policy-by-row looping); SA/SYS branch fetches all `Project::query()` (status filter optional like the users directory). Eager-load `owner` (`with('owner')`) — 1 extra query; soft-deleted owner handled per Decision D.
- **Detail**: `Project::query()->findOrFail($id)` + `Gate::authorize('view', $project)`; eager-load `owner` and `members` (pivot `role_id`, ordered by member name); then **one** follow-up `Role::whereIn('id', $pivotRoleIds)->get()->keyBy('id')` so member-role display is 3 queries total with zero N+1. Members paginate the same way as the users directory for consistency.
- Defensive loading: missing/inconsistent ownership (`owner_id` set with NULL type — schema-impossible, but displayed via the default indicator) never throws; no new models, tables, or dependencies.

## 7. Test plan (`tests/Feature/Projects/ProjectIndexHttpTest.php`, `ProjectShowHttpTest.php`)

- **Server-side access, not button visibility** (assert status codes + page content):
- Index: guest → login; deactivated → login redirect; SA sees all (incl. non-member); member sees only own project; non-member sees empty; SYS matrix per Decision A verdict.
- Show: guest/deactivated as above; non-member → 403; member w/ `projects.view` → 200 + name/code/description/status; member without view permission → 403; SYS cross-project per Decision.
- Owner rendering: owned permanent/project, temporary indicator, unowned shows "No Admin Assigned" + auto-assignment setting state; Decision D edge (soft-deleted owner) renders defensively.
- Members list: active members with profile info + project role codes; soft-deleted member excluded (or labeled per decision); designated owner clearly indicated; correct role on each row.
- Control visibility flags asserted per matrix (member w/o manage permissions sees no controls; PROJECT_ADMIN sees membership controls; nobody but SA/SYS sees ownership controls) — with server-side authorization verified separately by the already-green Part 4B mutation tests.
- Query behavior: assert N+1-free pattern (fine-grained DB query counting, e.g. `DB::enableQueryLog` count bounded), pagination rendering of member list.

## 8. Git status (at time of Part 4C inspection)

`git diff --cached --stat` — **empty, nothing staged**. Working tree carries the accumulated Stage 1–4B modifications (`.ai/*`, `AGENTS.md`, docs, `routes/web.php`, `app/Models/Project.php`, `ProjectPolicy.php`, `ProjectFactory.php`, `UserDirectoryController.php`, users blade, `bootstrap/app.php`) and untracked stage artifacts (`app/Actions/`, `app/Http/Controllers/Projects/`, `app/Services/{Auth,Projects,Users}/`, ownership migration, `ProjectRoleSeeder`/`ProjectAuthorizationPermissionSeeder`/`UserManagementPermissionSeeder`, `tests/Feature/{Actions,Projects,Models,Authorization,Http,Settings,Services}/`). `database/seeders/TestUserSeeder.php` — **untracked, local-only, untouched**. No migrations run, nothing committed or pushed.
