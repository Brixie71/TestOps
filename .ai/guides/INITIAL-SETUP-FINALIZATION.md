**Project:** TestOps  
**Document type:** AI workflow reference and implementation gate  
**Purpose:** Finalize the initial application foundation before beginning Dashboard development  
**Technical baseline:** Laravel 13.39, PHP 8.5, Blade, Tailwind CSS 4, Vite, PHPUnit, PostgreSQL target, SQLite-compatible testing  
**Repository:** `https://github.com/Brixie71/TestOps`

---

## 1. Purpose and Scope

This document defines the remaining checks required before starting the TestOps Dashboard.

It is intended for the developer and AI coding assistants working inside the TestOps repository. It supplements the existing project rules and guides; it does not replace them.

The goal is to confirm that the application foundation is stable, documented, reproducible, and safe to extend.

This checklist covers:

- Repository and environment readiness
- Laravel application configuration
- Frontend build configuration
- Database and seed data
- Authentication foundation
- RBAC relationship foundation
- Automated tests and code formatting
- AI assistant instructions and repository context
- Documentation and GitHub readiness

**Out of scope:** implementing the Dashboard itself, dashboard widgets, analytics, charts, activity feeds, or new domain models.

Do not begin Dashboard implementation until the required gates in this document have been reviewed.

## 2. Current Known Project Checkpoint

The following items were previously reported as completed. AI assistants must verify the current repository state rather than treating this list as proof that the files have not changed.

| Area | Known checkpoint |
|---|---|
| Laravel application | Laravel 13.39.0 |
| PHP | PHP 8.5.12 |
| Composer | Composer 2.11.1 |
| Frontend | Tailwind CSS 4, Vite, Blade |
| Frontend dependencies | `npm.cmd install` completed |
| Frontend build | `npm.cmd run build` completed successfully |
| Database | PostgreSQL is the target; SQLite is used for compatible local/testing workflows |
| Migrations | Initial application, RBAC, project, and pivot migrations were applied successfully |
| Seeders | RoleSeeder and SuperAdminSeeder completed; repeated seeding was reported idempotent |
| Eloquent relationships | Eight focused relationship tests passed with 19 assertions |
| Formatting | Pint passed on recently modified relationship and authentication files |
| Login view | `resources/views/auth/login.blade.php` exists and uses the named login route |
| Authentication routes | GET/POST login and POST logout routes were registered |
| Git | Repository initialized, initial commit created, GitHub remote configured, initial push reported complete |

The most recent known relationship test result was:

```text
Tests: 8 passed (19 assertions)
```

This result applies only to the focused relationship test file at that checkpoint. It does not establish that the full test suite currently passes.

## 3. Mandatory AI Preflight

Before inspecting, planning, or editing project files, AI assistants must:

1. Read `.ai/rules/index.md`.
2. Identify and read all applicable rule files for the target paths.
3. Read relevant guides under `.ai/guides/`.
4. Inspect the actual files and current Git state.
5. Verify the installed Laravel and PHP versions when version-specific behavior matters.
6. Explain the proposed change and identify whether it affects application code, tests, configuration, or database schema.
7. Make one focused change at a time when guiding the developer.

Relevant existing guides include:

- `.ai/guides/DATABASE-CHEATSHEET.md`
- `.ai/guides/DATABASE-WORKFLOW.md`
- `.ai/guides/ELOQUENT-RELATIONSHIPS-CHEATSHEET.md`
- `.ai/guides/ELOQUENT-RELATIONSHIPS-AND-MODEL-EXCEPTIONS.md`
- `.ai/guides/ELOQUENT-RELATIONSHIP-LAYER-FINALIZATION.md`

Do not invent project conventions or assume a guide is current without reading it.

### AI collaboration rules

- Explain the reason for a change before asking the developer to make it.
- Give one command or implementation step at a time.
- Wait for the developer's output before continuing when the workflow depends on that output.
- Do not silently overwrite working code or redesign unrelated areas.
- Do not generate a complete feature when the developer is asking to learn incrementally.
- Do not claim a command, test, build, or migration succeeded unless its output was observed.
- Do not ask the developer to expose passwords, API keys, tokens, or private connection strings.
- Never place real credentials in source code, committed files, prompts, logs, or examples.

## 4. Repository and Git Verification

Before Dashboard work:

- [ ] Confirm the project is in `C:\Users\Jhon_Brix\Projects\TestOps`.
- [ ] Confirm `git status` is understood and there are no unexplained changes.
- [ ] Confirm `.env` remains ignored.
- [ ] Confirm `auth.json`, `vendor/`, `node_modules/`, and `public/build/` remain ignored.
- [ ] Confirm `.env.example` contains placeholders only.
- [ ] Confirm no API keys, passwords, tokens, private URLs, or local-only secrets are tracked.
- [ ] Confirm the initial commit exists.
- [ ] Confirm the GitHub remote points to the intended repository.
- [ ] Confirm the current branch and remote synchronization state before starting new work.

Useful commands:

```powershell
git status
git remote -v
git branch --show-current
git log -1 --oneline
```

Do not run `git push --force` or rewrite published history as routine setup work.

## 5. Environment and Configuration Verification

Inspect the current configuration rather than assuming it matches this checklist.

### 5.1 Environment files

Verify:

- `.env` exists locally and is ignored by Git.
- `.env.example` documents the required variables with placeholder values.
- `APP_NAME` is set to `TestOps` in `.env.example`.
- `APP_KEY` is generated in the local environment.
- `APP_DEBUG` is appropriate for the local environment and is not enabled in production.
- `APP_URL` matches the local development URL.
- Database variables match the database actually being used.
- Super Admin seed credentials are supplied through local environment configuration and are not committed.

Never copy the real `.env` into `.env.example`.

### 5.2 Runtime checks

Run:

```powershell
php -v
composer --version
php artisan --version
node --version
npm.cmd --version
```

The project uses `npm.cmd` in PowerShell because the `npm.ps1` shim may be blocked by the current execution policy. Do not change the machine's execution policy merely to avoid using `npm.cmd`.

### 5.3 Laravel configuration

Inspect:

- `config/app.php`
- `config/auth.php`
- `config/session.php`
- `config/database.php`
- `config/testops.php`
- `bootstrap/app.php`

Confirm configuration is intentional and consistent with the approved project specification.

Do not run configuration-cache commands as a substitute for diagnosing incorrect environment values.

## 6. Frontend Foundation Verification

The current frontend baseline is Blade, Tailwind CSS 4, and Vite.

Verify:

- [ ] `resources/css/app.css` imports Tailwind CSS 4.
- [ ] Tailwind source directives include the required Laravel view locations.
- [ ] `resources/js/app.js` exists and is loaded through Vite.
- [ ] `vite.config.js` uses the expected Laravel and Tailwind Vite plugins.
- [ ] `resources/views/auth/login.blade.php` uses the Vite assets.
- [ ] Login form method, action, CSRF token, and input names match the backend routes and controller.
- [ ] The password visibility control works without breaking form submission.
- [ ] Validation errors are displayed.
- [ ] The layout remains usable on mobile and desktop.
- [ ] No frontend change has removed or renamed backend integration points.

Run the production build:

```powershell
npm.cmd run build
```

A missing optional font optimization package warning is not automatically a build failure. Record it, but distinguish warnings from actual errors.

Generated build output should remain excluded from Git if that is the project's established convention.

## 7. Database and Seed Data Verification

The database schema must be inspected directly. This document is not a substitute for applied migrations.

### 7.1 Migration status

Run:

```powershell
php artisan migrate:status
```

Confirm the expected migrations are applied in the intended environment.

Do not run destructive reset commands as routine verification:

- `migrate:fresh`
- `migrate:refresh`
- database drops or manual table deletion

Never edit an already-applied migration to rewrite database history. Use a new migration for an approved schema change.

### 7.2 Seeders

Review:

- `database/seeders/DatabaseSeeder.php`
- `database/seeders/RoleSeeder.php`
- `database/seeders/SuperAdminSeeder.php`
- `config/testops.php`

Verify:

- Roles are seeded before the Super Admin account.
- The Super Admin seeder validates required configuration.
- The Super Admin is assigned the intended system role.
- Seeders use idempotent behavior where appropriate.
- Re-running seeders does not create duplicate Super Admin accounts.
- No real password or secret is hardcoded.

If seed behavior is changed, test it in a safe local/test database. Do not reseed a database containing important data without understanding the seeder's effects.

## 8. Authentication Foundation Verification

Authentication is a prerequisite for a role-aware Dashboard.

Approved authentication direction:

- Native Laravel session-based web authentication.
- Email and password login.
- No public self-registration.
- Accounts are provisioned by the Super Admin.
- Session regeneration after successful login.
- Session invalidation and CSRF token regeneration on logout.
- Authentication middleware protects private application routes.
- Password reset is deferred until mail delivery is configured.
- API authentication remains undecided and must not be assumed.

### 8.1 Routes

Inspect `routes/web.php`.

The expected route behavior is:

| Method | Path | Purpose | Middleware |
|---|---|---|---|
| GET | `/login` | Display login form | `guest` |
| POST | `/login` | Authenticate user | `guest` |
| POST | `/logout` | End authenticated session | `auth` |

Route names currently expected:

- `login`
- `login.store`
- `logout`

Verify actual names with:

```powershell
php artisan route:list
```

### 8.2 Controller

Inspect `app/Http/Controllers/AuthenticatedSessionController.php`.

Verify that it contains:

- `create()` to display the login view.
- `store()` to validate credentials and authenticate.
- Session regeneration after successful authentication.
- `destroy()` to log out, invalidate the session, and regenerate the CSRF token.

The login controller must not trust arbitrary role values submitted by the browser.

### 8.3 Login behavior

Manually verify:

- [ ] Login page loads.
- [ ] Invalid credentials show a validation/authentication error.
- [ ] Successful credentials authenticate the seeded Super Admin.
- [ ] Successful login regenerates the session.
- [ ] Logout ends the authenticated session.
- [ ] A guest cannot access protected routes.
- [ ] An authenticated user is redirected appropriately when accessing guest-only login routes.

**Important:** The controller's current known redirect target is `/dashboard`, but the Dashboard route/view may not exist yet. A post-login 404 at that target is a routing/view readiness issue, not proof that authentication itself failed.

Before Dashboard implementation, either:
- create a temporary authenticated landing route, or
- implement the Dashboard route as the first step of the Dashboard feature.

Do not hide a missing route by claiming the login workflow is fully end-to-end verified.

## 9. RBAC Foundation Verification

The approved RBAC model distinguishes system-level roles from project-level roles.

Known relationship design:

- `User::role()` uses `users.role_id`.
- `Project::owner()` uses `projects.owner_id`.
- `Project::members()` uses `project_user`.
- `User::projects()` uses `project_user`.
- `Role::users()` represents system-level role assignments only.
- `Role::permissions()` uses `permission_role`.
- `Role::projectMembers()` uses `project_user` and exposes project context.
- `Permission::roles()` uses `permission_role`.

Important domain boundaries:

- A user's system role is not the same as their role in a project.
- Project ownership is not automatically equivalent to project membership or project administration.
- A user may have different project roles in different projects.
- Project Admins cannot create or modify role definitions.
- Role definitions are centrally managed by the Super Admin.
- Backend authorization must enforce access. Hiding UI elements is not a security boundary.

Before Dashboard work:

- [ ] Re-read `.ai/guides/ELOQUENT-RELATIONSHIP-LAYER-FINALIZATION.md`.
- [ ] Inspect current model relationships and migrations.
- [ ] Confirm the focused relationship test still passes.
- [ ] Review mass-assignment safety.
- [ ] Do not add unapproved domain models.

Run the focused test:

```powershell
php artisan test --compact tests/Feature/Models/ProjectRelationshipTest.php
```

Known historical checkpoint:

```text
Tests: 8 passed (19 assertions)
```

Re-run the test and use the current output as the authoritative result.

## 10. Automated Test and Formatting Gate

Before starting Dashboard implementation:

1. Run the focused relationship tests.
2. Run authentication tests if they exist.
3. Run the full test suite.
4. Fix failures that are part of the existing foundation.
5. Run Pint against changed PHP files.
6. Re-run relevant tests after formatting.

Commands:

```powershell
php artisan test --compact
```

For a project not relying on Git-aware dirty-file detection, pass changed files explicitly:

```powershell
vendor/bin/pint app/Http/Controllers/AuthenticatedSessionController.php --format agent
```

Do not claim the full suite passes based only on the relationship test result.

### Minimum authentication test coverage to establish

If not yet implemented, add focused feature tests for:

- Guest can view login page.
- Valid credentials authenticate the user.
- Invalid credentials do not authenticate the user.
- Successful login regenerates the session.
- Authenticated user can log out.
- Logout invalidates the session.
- Guest is redirected from protected routes.

Do not add all these tests in one large unreviewed change. Follow the project's test rules and implement incrementally.

## 11. Application Entry and Route Readiness

Before the Dashboard feature:

- [ ] Review `routes/web.php` and `bootstrap/app.php`.
- [ ] Confirm the root route behavior is intentional.
- [ ] Confirm `/dashboard` is not accidentally assumed to exist.
- [ ] Decide whether the root route should redirect authenticated users to `/dashboard` and guests to `/login`.
- [ ] Ensure private application routes use authentication middleware.
- [ ] Keep route definitions resource-oriented and consistent with `.ai/rules/routes.md`.
- [ ] Avoid adding dashboard-specific routes or controllers until the Dashboard feature is formally started.

The Dashboard should not become a dumping ground for unrelated business logic. Controllers should remain thin, with data retrieval and authorization responsibilities placed appropriately.

## 12. Documentation Readiness

Before Dashboard work, review the root `README.md`.

It should explain:

- What TestOps is.
- The current technical stack.
- Prerequisites for local development.
- How to install PHP dependencies.
- How to configure `.env`.
- How to generate the application key.
- How to configure the database.
- How to run migrations and seeders safely.
- How to install frontend dependencies.
- How to build frontend assets.
- How to start the local development server.
- How to run tests.
- How to use the Super Admin bootstrap configuration without publishing credentials.

The README must describe the actual working setup. Do not document commands that have not been verified.

The repository should also explain where AI-specific instructions live:

- `.ai/rules/`
- `.ai/guides/`
- `AGENTS.md`
- `CLAUDE.md`
- `.opencode/`
- `.agents/` and `.claude/` skill directories, if these remain part of the intended repository workflow.

Review duplicated AI skill content and retain only what the project intentionally supports. Do not delete files simply because they appear duplicated without checking their purpose and tool compatibility.

## 13. Dashboard Start Gate

The Dashboard implementation may begin when all required items below have been checked.

### Required

- [ ] AI preflight instructions and relevant project guides have been read.
- [ ] Git working tree is understood and no accidental secret files are staged.
- [ ] `.env` and real credentials are excluded from Git.
- [ ] `.env.example` is safe and representative.
- [ ] PHP, Composer, Laravel, Node, and npm versions are verified.
- [ ] Frontend production build succeeds.
- [ ] Migration status is understood.
- [ ] Seeders are reviewed and Super Admin bootstrap behavior is understood.
- [ ] Login view and authentication controller are consistent.
- [ ] Login and logout routes are verified.
- [ ] Authentication behavior is manually checked or covered by tests.
- [ ] RBAC relationship tests pass.
- [ ] Full existing test suite is run and failures are understood.
- [ ] Pint has been run on changed PHP files.
- [ ] README setup instructions are accurate.
- [ ] GitHub repository is synchronized with the intended local state.

### Deferred or not required to start the Dashboard

These are separate concerns unless the approved Dashboard scope explicitly requires them:

- Password reset and outbound email delivery.
- API authentication strategy.
- Production deployment.
- Production secrets management.
- Advanced authorization policies beyond the permissions needed for the Dashboard.
- Dashboard analytics and reporting.
- TestRun, TestResult, and Defect relationship implementation.
- Additional domain models not yet approved.

Do not expand initial setup indefinitely. Once the required gate is satisfied, move to the Dashboard specification.

## 14. AI Assistant Completion Behavior

When asked to finalize the initial setup:

1. Inspect the current repository state.
2. Compare it against this checklist and existing project rules.
3. Separate verified items from unverified items.
4. Report blockers before optional improvements.
5. Do not repeat completed work without a reason.
6. Do not introduce new schema or domain models during a setup audit.
7. Do not implement the Dashboard before the developer approves its scope.
8. Guide the developer one step at a time.
9. Ask for command output when verification depends on the local environment.
10. Update this document only when the setup workflow or approved technical baseline materially changes.

Use these status labels in reports:

- **Verified:** directly inspected or successfully executed in the current repository.
- **Previously reported:** completed in an earlier checkpoint but not re-verified now.
- **Pending:** not yet completed.
- **Blocked:** cannot proceed because a required dependency or decision is missing.
- **Deferred:** intentionally outside the current setup scope.

## 15. Final Status Template

At the end of the setup audit, report:

```text
TestOps Initial Setup

Repository and Git:
Environment and configuration:
Frontend build:
Database and seeders:
Authentication:
RBAC relationships:
Automated tests:
Code formatting:
Documentation:
AI instructions:

Dashboard start gate: READY / NOT READY

Remaining blockers:
Deferred work:
Next approved action:
```

Do not mark the Dashboard start gate as READY while a required item remains unverified or blocked.

---

**Completion principle:** The initial setup is complete when the application can be installed, configured, seeded, built, authenticated, tested, and understood by a new developer or AI assistant using the repository's own instructions. The Dashboard is the next feature, not a substitute for a stable foundation.
