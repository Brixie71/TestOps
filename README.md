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
| Application database     | PostgreSQL (intended)                                          | Configured through the standard `DB_*` settings                  |
| Automated test database  | SQLite, in-memory (`:memory:`)                                 | Configured in `phpunit.xml`; no external database needed to test |
| Templating               | Blade                                                          | All views in `resources/views`                                   |
| Styling                  | Tailwind CSS 4 (`^4.0.0`, `@tailwindcss/vite` plugin)          |                                                                  |
| Asset bundling           | Vite `^8.0.0`                                                  |                                                                  |
| Testing                  | PHPUnit `^12.5.12`                                             |                                                                  |
| Code style               | Laravel Pint `^1.27`                                           |                                                                  |
| Local email infrastructure | Haraka 3 + Mailpit, orchestrated with Podman Compose         | See [Email infrastructure](infrastructure/email/README.md)       |

Sessions, cache, and queue connections are configured for database-driven storage by default (`SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`).

## Current implementation

The following has been implemented and verified in the codebase.

### Authentication

- `GET /login` and `POST /login` for guest users, handled by `AuthenticatedSessionController`.
- `POST /logout` for authenticated users.
- An authenticated application shell: landing page, navbar, sidebar, and theme toggle.

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
- `ProjectPolicy`: Super Admin has unrestricted project access (policy `before` hook); project owners manage their own projects; members act through project-scoped roles or, for `SYSTEM_ADMIN`, through a system permission combined with an active project membership. Ownership does not bypass membership requirements for system admins.
- Registered middleware aliases in `bootstrap/app.php`:
  - `super.admin` — `EnsureUserIsSuperAdmin`: allows only authenticated users whose assigned role has code `SUPER_ADMIN` and scope `system` (verified by role code and scope, never by name or email).
  - `permission` — `EnsurePermission`: accepts a permission code argument, e.g. `permission:projects.view`, and returns HTTP 403 (or redirects guests) otherwise.
- Seeders: `RoleSeeder` creates the `SUPER_ADMIN` system role; `SuperAdminSeeder` verifies existing Super Admin accounts but does **not** create users (see [Known limitations](#known-limitations)).

### Application routes

Verified with `php artisan route:list`:

| Method    | Route                              | Name                  | Access                         | Status                    |
| --------- | ---------------------------------- | --------------------- | ------------------------------ | ------------------------- |
| GET/HEAD  | `/`                                | —                     | Public                         | Landing page              |
| GET/HEAD  | `/login`                           | `login`               | Guest                          | Implemented               |
| POST      | `/login`                           | `login.store`         | Guest                          | Implemented               |
| POST      | `/logout`                          | `logout`              | Authenticated                  | Implemented               |
| GET/HEAD  | `/dashboard`                       | `dashboard`           | Authenticated                  | Placeholder view          |
| GET/HEAD  | `/profile`                         | `profile`             | Authenticated                  | Placeholder view          |
| GET/HEAD  | `/settings`                        | `settings.index`      | Super Admin (`super.admin`)    | Placeholder view          |
| GET/HEAD  | `/settings/users`                  | `settings.users`      | Super Admin (`super.admin`)    | Placeholder view          |
| GET/HEAD  | `/settings/roles`                  | `settings.roles`      | Super Admin (`super.admin`)    | Placeholder view          |
| GET/HEAD  | `/settings/permissions`            | `settings.permissions`| Super Admin (`super.admin`)    | Placeholder view          |
| GET/HEAD  | `/settings/about`                  | `settings.about`      | Super Admin (`super.admin`)    | Placeholder view          |

The dashboard, profile, and settings pages are **navigation placeholders only** — static views with no management functionality yet. They are not completed management modules.

### Email infrastructure

A local Haraka + Mailpit stack running under Podman Compose supports outbound email development and testing. See [`infrastructure/email/README.md`](infrastructure/email/README.md).

## Known limitations

- **Initial Super Admin provisioning is not implemented.** Automatic Super Admin provisioning through environment variables has been removed. `RoleSeeder` seeds only the `SUPER_ADMIN` role — not a user — and `SuperAdminSeeder` explicitly refuses to auto-provision accounts. No supported provisioning command currently exists; creating the first Super Admin account requires a controlled administrative setup procedure that has not yet been documented or implemented. Do not create accounts by manually inserting password hashes into the database.
- User Management CRUD, complete audit logging, notification management, email-address history, and related workflows are **planned but not implemented**.
- Application and administration modules built on the authorization foundation are still in progress.

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

## Security

- **Never commit environment files or secrets.** `.env`, tokens, API credentials, and private keys must stay out of version control. This applies to the root repository (`.env` is Git-ignored) and to the email infrastructure, where the local `.env`, generated Haraka authentication configuration, and generated TLS key/certificate files are also Git-ignored.
- **Use local development credentials only.** Any credential created for this project must not be reused in other systems, in production, or in personal accounts.
- **The local email infrastructure is not production email hosting.** It is a loopback-bound, self-signed-certificate development stack for testing outbound email behavior. It must not be exposed to other networks.
- **No security claims beyond what is verified here.** The authorization layer and email infrastructure are development foundations; they have not been audited or verified against production security requirements. Do not treat this documentation as a security guarantee.

## Roadmap

Planned capabilities, in **no particular order** and **not implemented**:

1. User Management module (Super Admin CRUD over users in Settings).
2. Role and permission administration interfaces.
3. Functional dashboard and profile pages.
4. Project and membership management on top of the existing project data model.
5. Audit logging, notification management, and email-address history.
6. Test planning and execution tracking — the core purpose of the platform.

The implementation order may change as development progresses.

---

*Licensed under the [MIT license](composer.json).*
