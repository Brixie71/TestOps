# TestOps

A software testing and quality assurance management platform, built on Laravel.

> **Development status:** TestOps is under active development. It is **not production-ready**. Data models, routes, commands, and procedures described here may still change.

## Project overview

TestOps is intended to become a management platform for software testing and quality assurance work: organizing testing effort into projects, assigning team members with project-scoped roles, and — over time — managing test assets, executions, and reporting.

The repository currently contains the platform **foundation**: an authentication shell, role and permission data models, and a layered authorization system that distinguishes system-scoped administration from project-scoped membership. Application features on top of this foundation are being built incrementally.

Only the functionality listed under [Current implementation](#current-implementation) exists in the codebase. Everything else is planned work.

## Technology stack

| Layer                    | Technology                                                     | Notes                                                            |
| ------------------------ | -------------------------------------------------------------- | ---------------------------------------------------------------- |
| Application framework    | Laravel `^13.17`                                               | `composer.json` constraint                                       |
| Language                 | PHP `^8.3`                                                     | Developed and run locally on PHP 8.5                             |
| Service API authorization | Laravel Passport `^13.10`                                     | OAuth2 for the service API; web sessions are separate            |
| Application database     | PostgreSQL (intended)                                          | Configured through the standard `DB_*` settings                  |
| Automated test database  | SQLite, in-memory (`:memory:`)                                 | Configured in `phpunit.xml`; no external database needed to test |
| Templating               | Blade                                                          | All views in `resources/views`                                   |
| Styling                  | Tailwind CSS 4 (`^4.0.0`, `@tailwindcss/vite` plugin)          |                                                                  |
| Asset bundling           | Vite `^8.0.0`                                                  |                                                                  |
| Testing                  | PHPUnit `^12.5.12`                                             |                                                                  |
| Code style               | Laravel Pint `^1.27`                                           |                                                                  |
| Production email         | Resend (`resend/resend-php`)                                   | Verified sender; credentials stay in `.env`                       |
| Local email infrastructure | Haraka 3 + Mailpit, orchestrated with Podman Compose         | See [Email infrastructure](infrastructure/email/README.md)       |

Sessions, cache, and queue connections are configured for database-driven storage by default (`SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`).

## Current implementation

The following has been implemented and verified in the codebase.

### Authentication and accounts

- `GET /login` and `POST /login` for guest users, handled by `AuthenticatedSessionController`; `POST /logout` for authenticated users.
- Invitation-based account creation, invitation recovery, email verification, and forced password reset through single-use emailed links. Every guest POST is throttled per IP.
- An authenticated application shell: landing page, navbar, sidebar, and theme toggle.
- Self-service profile: name, contact number, job title, and department; password change; email-address change with verification and cancellation; notification preferences.
- Account lifecycle: deactivation (soft delete), reactivation, permanent deletion with the acting administrator's password and a recorded reason, and bulk deactivation.

### Authorization foundation

Role and permission data models and supporting migrations:

| Migration                                         | Purpose                                                               |
| ------------------------------------------------- | --------------------------------------------------------------------- |
| `create_roles_table`                              | Roles with `name`, `code`, `description`, and `scope`                 |
| `create_permissions_table`                        | Permissions with `name`, `code`, and `description`                    |
| `create_permission_role_table`                    | Role ↔ permission assignment pivot                                    |
| `add_role_id_to_users_table`                      | Assigns a system-scope role to each user                              |
| `create_projects_table`                           | Projects with a unique `code`, an `owner_id`, and a `status`          |
| `create_project_user_table`                       | Project membership pivot including the member's role                  |

Authorization behavior:

- `PermissionChecker` (system scope): users without a system-scope role are denied; users with the `SUPER_ADMIN` system role are always allowed; other system roles inherit permissions only through their role-permission assignments.
- `ProjectPolicy`: Super Admin has unrestricted project access (policy `before` hook); project owners manage their own projects; members act through project-scoped roles or, for `SYSTEM_ADMIN`, through a system permission combined with an active project membership. Ownership does not bypass membership requirements for system admins. Testing read abilities (`viewTestCases`, `viewTestRuns`, `viewDefects`) grant cross-project visibility to `SYSTEM_ADMIN` through their system-scope permissions; testing writes stay owner/member-only.
- `ProjectVisibility`: the single query-side implementation of project scope, used by the projects directory and the dashboard. It mirrors `ProjectPolicy`, and parity is asserted by `tests/Feature/Services/Authorization/ProjectVisibilityTest.php`.
- Registered middleware aliases in `bootstrap/app.php`:
  - `super.admin` — `EnsureUserIsSuperAdmin`: allows only authenticated users whose assigned role has code `SUPER_ADMIN` and scope `system` (verified by role code and scope, never by name or email).
  - `permission` — `EnsurePermission`: accepts a permission code argument, e.g. `permission:projects.view`, and returns HTTP 403 (or redirects guests) otherwise.
- Seeders: `RoleSeeder` creates the `SUPER_ADMIN` and `SYSTEM_ADMIN` system roles; `ProjectRoleSeeder` creates `PROJECT_ADMIN` and its project-scoped permission set; `ProjectAuthorizationPermissionSeeder` grants `SYSTEM_ADMIN` the cross-project management and read-only testing permissions. `SuperAdminSeeder` verifies existing Super Admin accounts but does **not** create users (see [Known limitations](#known-limitations)).

### Projects and membership

- Projects directory with role-scoped visibility and search, and a project details page.
- Membership add/remove and role changes, ownership assignment/transfer/unassignment, assignment type, and automatic owner assignment. Every mutation is transactional, re-authorizes inside the transaction, and is audited.
- Project Activity email notifications for membership and ownership changes, delivered to the affected member and the project owner, deduplicated, never to the actor, and suppressed when the recipient has disabled the optional category.

### Testing domain

- Test suites and test cases with ordered steps and per-project keys (`TC-#`).
- Test runs that freeze case title, steps, and severity at start time (`TR-#`), with start, complete, and abort.
- One current result per frozen case (`passed`, `failed`, `blocked`, `skipped`, `not_run`), re-recordable while the run is open.
- Project defects (`DEF-#`) optionally traced to a result, with severity and status transitions.
- Per-run evidence sheet (`projects/{project}/test-runs/{run}/report`) showing the frozen steps, each case's result and notes, and the defects traced to it, with a CSV export of the same rows. It is read-only and follows `viewTestRuns`; defect data is omitted without `viewDefects`.
- Project-scoped permissions for every read and write; `SYSTEM_ADMIN` is read-only; all case, run, result, and defect changes are audited.

### Administration

- User directory, invites and invitation management, system-role assignment, password management and forced reset, administrative profile editing, email-change cancellation, and bulk operations.
- Role and permission administration, including safe deletion rules for custom roles.
- System announcements to every active account.
- Audit log with filtering and CSV/PDF export.
- Email delivery history with manual retry for eligible failures.

### Application routes

Routes are grouped by module and each mutation is authorized server-side by the controller, action, and policy. Use `php artisan route:list` for the full, current list.

| Area | Routes |
| --- | --- |
| Landing and authentication | `/`, `/login`, `/logout`, invitation/verification/password-reset links |
| Dashboard | `/dashboard` — read-only summary scoped to visible projects |
| Profile | `/profile` plus password, email, and notification endpoints |
| Projects | `/projects`, `/projects/{project}`, testing workspace, test runs, results, defects |
| Settings | `/settings/*` per module, each behind `super.admin`, `active.account`, and the module's permission |

`/settings` and `/settings/about` remain informational views. Every other listed area has working behavior behind it.

### Email delivery

Production delivery uses the Resend transport (`MAIL_MAILER=resend`) with the verified sender `TestOps <no-reply@testops.site>`. `RESEND_API_KEY` lives in `.env` only and is never committed.

A local Haraka + Mailpit stack running under Podman Compose supports outbound email development and testing. See [`infrastructure/email/README.md`](infrastructure/email/README.md).

## Known limitations

- **Initial Super Admin provisioning is not implemented.** Automatic Super Admin provisioning through environment variables has been removed. `RoleSeeder` seeds only the `SUPER_ADMIN` role — not a user — and `SuperAdminSeeder` explicitly refuses to auto-provision accounts. No supported provisioning command currently exists; creating the first Super Admin account requires a controlled administrative setup procedure that has not yet been documented or implemented. Do not create accounts by manually inserting password hashes into the database.
- Testing retention is enforced: deleting a project is refused while it holds any test suites, cases, runs, or defects, so project deletion cannot cascade testing history. Runs and defects are immutable by design: runs end as `completed` or `aborted`, defects as `resolved`, `closed`, or `wont_fix`, and neither has a delete path. Test-case attachments are not implemented.
- The dashboard is implemented as a read-only personal summary: scoped KPI cards plus lists for visible projects, runs in progress, and open defects. Charts, an activity feed, and exports remain out of scope.
- Production deployment is not automated in this repository.

## Prerequisites

- **PHP 8.3 or newer** with the extensions Laravel requires (including `pdo_sqlite` for the test suite and the `pdo_pgsql` driver when using PostgreSQL).
- **Composer 2**.
- **Node.js (LTS) and npm** for asset bundling.
- **A database** for local application data. PostgreSQL is the intended application database (configure the `DB_CONNECTION=pgsql` block in `.env`); without configuration, Laravel falls back to a local SQLite database file. Automated tests always use in-memory SQLite.
- **Podman Desktop with the Podman CLI and Compose support** — required only for the local email infrastructure. On Windows, the Podman machine must be running.

## Installation

Run the commands from the repository root. PowerShell is the primary reference; bash equivalents are noted where they differ.

1. **Clone the repository**

   ```powershell
   git clone <repository-url> TestOps
   cd TestOps
   ```

2. **Install PHP dependencies**

   ```powershell
   composer install
   ```

3. **Create your local environment file**

   ```powershell
   Copy-Item .env.example .env
   php artisan key:generate
   ```

   In bash: `cp .env.example .env`.

4. **Configure the database** in `.env` before migrating. To use PostgreSQL, uncomment and complete the `DB_CONNECTION=pgsql` settings (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`). Create the database itself so migrations have somewhere to write. The `config/database.php` default connection falls back to SQLite when `DB_CONNECTION` is not set.

   > [!NOTE]
   > If you keep the SQLite fallback, create the empty database file before migrating. It is **not tracked in Git**, so fresh clones do not include it:

   ```powershell
   if (-not (Test-Path "database\database.sqlite")) { New-Item -ItemType File "database\database.sqlite" }
   ```

   In bash: `touch database/database.sqlite`.

   The check matters: a bare `New-Item -ItemType File ... -Force` silently truncates an existing file to zero bytes, which would wipe an already-migrated database if re-run later.

5. **Run the migrations**

   ```powershell
   php artisan migrate
   ```

6. **Install npm dependencies and build assets**

   ```powershell
   npm install
   npm run build
   ```

   In bash: `npm install && npm run build`.

### One-shot setup script

Alternatively, the `setup` Composer script bundles the steps above:

```powershell
composer setup
```

It runs: `composer install` → copy `.env.example` to `.env` (only if `.env` does not exist) → `php artisan key:generate` → `php artisan migrate --force` → `npm install --ignore-scripts` → `npm run build`.

> [!IMPORTANT]
> `composer setup` copies the default `.env.example` contents and immediately runs migrations against whatever database settings that file contains. If you need PostgreSQL, create and configure `.env` yourself first (steps 3–4); `composer setup` will keep your existing `.env` and migrate against it. If you keep the SQLite fallback instead, run the database-file creation command from step 4 first — the script migrates non-interactively, so the file must already exist.

## Development commands

Verified commands, run from the repository root unless noted.

| Purpose                                       | Command                      | Notes                                                                                     |
| --------------------------------------------- | ---------------------------- | ----------------------------------------------------------------------------------------- |
| Start the full development environment        | `composer dev` (or `php artisan dev`) | Starts `serve`, `queue:listen`, and `vite` together (see `php artisan dev:list`)  |
| Serve the application only                    | `php artisan serve`          | Development HTTP server                                                                   |
| Run Vite (assets with hot reload)             | `npm run dev`                | Included as part of `composer dev`                                                        |
| Build production assets                       | `npm run build`              |                                                                                           |
| Run database migrations                       | `php artisan migrate`        | Re-run everything locally with `php artisan migrate:fresh` (drops data)                   |
| Run the automated tests                       | `composer test`              | Clears config, then `php artisan test`; tests use in-memory SQLite automatically          |
| Format changed PHP files                      | `vendor/bin/pint --dirty`    | Laravel Pint                                                                              |
| Clear framework caches                        | `php artisan optimize:clear` | Also `config:clear`, `route:clear`, and `view:clear` individually                         |

## Email infrastructure

TestOps ships local email testing infrastructure — Haraka for authenticated SMTP submission and Mailpit for message capture and inspection, orchestrated with Podman Compose. Setup, operations, and Laravel integration are documented in [`infrastructure/email/README.md`](infrastructure/email/README.md).

## AI assistant instructions

This repository keeps agent-facing instructions in version control:

- `AGENTS.md` — repository-wide engineering rules and verification contract.
- `.ai/rules/` — path-scoped rules; `.ai/rules/index.md` maps globs to the matching file.
- `.ai/guides/` — domain guides for database design, Eloquent relationships, user management, and setup.
- `.opencode/` — slash commands and harness configuration.
- `.agents/skills/` — project skills loaded on demand.
- `.scratch/` — local issue tracker and feature specs. It is working material, not application code.

## Security

- **Never commit environment files or secrets.** `.env`, tokens, API credentials, and private keys must stay out of version control. This applies to the root repository (`.env` is Git-ignored) and to the email infrastructure, where the local `.env`, generated Haraka authentication configuration, and generated TLS key/certificate files are also Git-ignored.
- **Use local development credentials only.** Any credential created for this project must not be reused in other systems, in production, or in personal accounts.
- **The local email infrastructure is not production email hosting.** It is a loopback-bound, self-signed-certificate development stack for testing outbound email behavior. It must not be exposed to other networks.
- **No security claims beyond what is verified here.** The authorization layer and email infrastructure are development foundations; they have not been audited or verified against production security requirements. Do not treat this documentation as a security guarantee.

## Roadmap

Planned capabilities, in **no particular order**:

1. Test-case attachments, reporting, and exports.
2. Production deployment and secrets management.

The implementation order may change as development progresses.

---

*Licensed under the [MIT license](composer.json).*
