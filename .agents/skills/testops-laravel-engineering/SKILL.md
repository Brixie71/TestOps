---
name: testops-laravel-engineering
description: Standardized Laravel, PostgreSQL, Passport OAuth, PowerShell, Artisan, database inspection, testing, and debugging workflows for the TestOps project.
compatibility: opencode
---

# TestOps Laravel Engineering Workflow

Reusable engineering workflow for the TestOps Laravel application. Use this skill when a task involves inspecting or changing Laravel code, PostgreSQL/database schema, Passport OAuth configuration, Artisan commands, Tinker inspection, PowerShell execution, or tests in this project.

The goal is consistency and speed: fewer repeated inspections, fewer unnecessary confirmations, and no re-deriving configuration that the project has already settled.

**Platform note:** This project runs on Windows. All shell commands are PowerShell 5.1 syntax. Where a dedicated read/grep/glob tool produces the same result, it may be used instead; the PowerShell forms below are the canonical reference and are required for piped, filtered, or context-gated inspection.

## Related Project Skills

Do not duplicate deeper guidance that already exists. Load the matching skill when its domain is central to the task:

- `passport-development` — OAuth2 grants, scopes, clients, token lifetimes, PKCE.
- `laravel-best-practices` — models, controllers, migrations, queries, caching, security.
- `testing-best-practices` — test coverage, naming, isolation, assertions.

This skill governs the *process* (inspection order, verification, reporting); those skills govern *how to write the code*.

## Core Operating Principles

1. Inspect existing implementation before changing anything.
2. Prefer targeted inspection over reading entire directories or dumping excessive output.
3. Reuse existing application architecture.
4. Use PowerShell syntax because the project runs on Windows.
5. Use Artisan and Laravel-supported APIs whenever practical.
6. Inspect installed dependency source when framework behavior is uncertain.
7. Never assume a command succeeded without inspecting its output.
8. Avoid unnecessary confirmation for already approved requirements.
9. Never invent missing project requirements.
10. Preserve existing user changes and avoid unrelated modifications.

Execute independent read-only inspections together when efficient, but never hide important results or merge unrelated implementation steps into one operation.

## PowerShell Inspection Workflow

Standard inspection commands:

Inspect project files:

```powershell
Get-Content ".\path\to\file.php"
```

Find files:

```powershell
Get-ChildItem ".\path" -Recurse -File
```

Search file contents:

```powershell
Get-ChildItem ".\path" -Recurse -File -Filter "*.php" |
    Select-String -Pattern "searchTerm"
```

Inspect code with context:

```powershell
Select-String -Path ".\path\to\file.php" `
    -Pattern "functionName" `
    -Context 3,8
```

Inspect migrations:

```powershell
Get-ChildItem ".\database\migrations" -File |
    Select-Object Name, FullName
```

Find migration definitions:

```powershell
Get-ChildItem ".\database\migrations" -File |
    Select-String -Pattern "create_table|foreignId|foreignUuid|uuid"
```

Inspect Git state:

```powershell
git status --short
git diff --check
git diff --stat
git diff
```

Use these as needed. Do not print excessive vendor or generated-file output when a targeted search is sufficient.

## Laravel Artisan Workflow

Use these as standard project diagnostics:

Environment information:

```powershell
php artisan about
```

Migration status:

```powershell
php artisan migrate:status
```

Route inspection:

```powershell
php artisan route:list
```

Targeted routes:

```powershell
php artisan route:list --path=api
```

Configuration cache clearing:

```powershell
php artisan config:clear
```

Only clear configuration cache when relevant to a configuration change or stale runtime configuration.

Tests:

```powershell
php artisan test
```

Focused tests by name:

```powershell
php artisan test --filter=PassportConfigurationTest
```

Specific test file:

```powershell
php artisan test tests/Feature/Authentication/PassportConfigurationTest.php
```

Laravel Pint:

```powershell
.\vendor\bin\pint
```

When possible, format only modified PHP files (e.g. `.\vendor\bin\pint --dirty`).

Do not run every diagnostic command automatically for every minor change. Select commands based on the affected functionality.

## Tinker Inspection Workflow

Use Artisan Tinker to inspect actual runtime behavior, not declared defaults.

```powershell
php artisan tinker --execute="dump(app()->environment());"
```

Database schema inspection:

```powershell
php artisan tinker --execute="dump(\Illuminate\Support\Facades\Schema::hasColumn('oauth_clients', 'scopes'));"
```

Actual runtime Passport grant properties:

```powershell
php artisan tinker --execute='dump(\Laravel\Passport\Passport::$deviceCodeGrantEnabled); dump(\Laravel\Passport\Passport::$passwordGrantEnabled); dump(\Laravel\Passport\Passport::$implicitGrantEnabled);'
```

Token lifetime configuration:

```powershell
php artisan tinker --execute='dump(\Laravel\Passport\Passport::tokensExpireIn()); dump(\Laravel\Passport\Passport::refreshTokensExpireIn()); dump(\Laravel\Passport\Passport::clientCredentialsTokensExpireIn());'
```

### Tinker Safety Rules

- Prefer read-only inspection.
- Never print private keys, client secrets, passwords, or credentials.
- Never execute destructive database operations through Tinker.
- Use simple PowerShell quoting. PowerShell 5.1 interpolates `$variables` inside double-quoted strings — use single-quoted `--execute` arguments when the PHP contains `$`.
- Single-quoted PowerShell strings cannot contain literal single quotes without doubling (`''`). If a command fails with a PHP parse error, inspect the quoting before changing application code.
- Verify runtime state rather than relying only on declared class defaults.
- Avoid unnecessary nested escaping.

## Database Inspection and Migration Workflow

When investigating database-related changes, in order:

1. Inspect the relevant migration.
2. Inspect the corresponding model and relationships.
3. Inspect existing database conventions.
4. Check actual database schema when necessary.
5. Check migration execution status.
6. Identify indexes, unique constraints, foreign keys, delete behavior, and nullability.
7. Check for existing data and lifecycle implications before proposing destructive changes.

Use Laravel Schema inspection when appropriate:

```php
Schema::hasTable('table_name');
Schema::hasColumn('table_name', 'column_name');
```

Migration rules:

- Never modify vendor migrations.
- Never recreate existing tables without an explicit requirement.
- Never assume a migration has been executed — check `php artisan migrate:status`.
- Never run `migrate:fresh`, `migrate:refresh`, or destructive rollback commands without explicit authorization.
- Review the migration before executing it.
- Preserve existing data.
- Add appropriate indexes and constraints.
- Consider soft deletion and application lifecycle behavior (this project has database guides under `.ai/guides/DATABASE-*.md` — consult them).
- Verify migration status after an approved migration.

This project uses **PostgreSQL** (`database.default = pgsql`). Inspect actual database behavior rather than assuming MySQL-specific behavior (e.g. identifier casing, index/constraint naming, `ON DELETE` semantics).

## Vendor Source Investigation Workflow

When framework or package behavior is uncertain — documentation is ambiguous, outdated, or version-specific:

1. Identify the installed package version (`composer show --direct`, or `composer show <package>`).
2. Locate the relevant implementation file.
3. Search for the specific method or property.
4. Read the surrounding implementation.
5. Trace relevant callers when needed.
6. Compare observed behavior with the application's configuration.
7. Write tests for important behavior.

Example — scope handling in Passport:

```powershell
Get-ChildItem ".\vendor\laravel\passport\src" -Recurse -File -Filter "*.php" |
    Select-String -Pattern "tokensCan|validScopes|hasScope" -Context 2,5
```

Inspect a specific implementation:

```powershell
Get-Content ".\vendor\laravel\passport\src\Passport.php"
```

Rules:

- Vendor source is read-only reference material.
- Never modify vendor files to work around application problems.
- Do not assume behavior from another package version.
- Distinguish declared defaults from actual runtime configuration.
- Do not print sensitive package configuration or key contents.

## Passport OAuth Inspection Workflow

When a task involves Laravel Passport, inspect the installed implementation and the existing application configuration. Verify:

- Installed Passport version.
- Passport configuration (`config/passport.php`).
- Authentication guards (`config/auth.php`).
- User model traits (`HasApiTokens` and related).
- OAuth migration schemas.
- OAuth route registration.
- Grant enablement (runtime, via Tinker).
- Token expiration (runtime, via Tinker).
- Scope registration.
- Client-level restrictions.
- Service-client authorization.
- PKCE enforcement.
- Token revocation.
- Encryption key availability without printing key contents.

### Approved TestOps Baseline

These are the approved, established policies. Do not change them without explicit approval:

| Setting                          | Approved value                             |
| -------------------------------- | ------------------------------------------ |
| Client Credentials               | Enabled                                    |
| Authorization Code               | Enabled                                    |
| PKCE                             | Required, S256 only                        |
| Refresh Token                    | Enabled for Authorization Code             |
| Access token lifetime            | 15 minutes                                 |
| Client Credentials token lifetime| 15 minutes                                 |
| Refresh token lifetime           | 7 days                                     |
| Device Authorization             | Disabled                                   |
| Password Grant                   | Disabled                                   |
| Implicit Grant                   | Disabled                                   |

If observed runtime behavior differs from this baseline, flag it as a finding — do not silently "fix" it toward either direction.

### Scope and Permission Separation

Never confuse:

1. OAuth token scopes.
2. Application-level service-client permissions.
3. Human user roles and permissions.

Machine clients are service principals, not human users.

Existing approved service authorization policy:

- `system-settings:read` is the OAuth read scope.
- `system-settings:write` is registered as a scope definition but is not granted to machine clients in the initial release.
- `system-settings.read` is the application-level read permission.
- Machine clients require both the OAuth read scope and the application-level permission.
- Machine clients cannot inherit `SUPER_ADMIN` or `SYSTEM_ADMIN` roles.
- Machine-client write access remains disabled.

Do not change these policies without explicit approval.

### Existing OAuth Services and Revocation Ownership

- `App\Services\Authorization\OAuthScopes` — the centralized scope registry,
  registered only through `Passport::tokensCan(OAuthScopes::definitions())`.
- `App\Services\Authorization\ServiceClientAuthorizer` — machine-client
  authorization: fresh active-client lookup plus the provisioned
  application-level permission check.
- `App\Services\Auth\RevokesUserCredentials::revokeForUser($userId)` — the
  only user-bound credential revocation point. It bulk-revokes the user's
  active access tokens, their linked refresh tokens (including refresh
  tokens linked to already-revoked access tokens), and outstanding
  authorization codes; returns the three aggregate counts
  (`access_tokens`, `refresh_tokens`, `authorization_codes`) and nothing
  else; owns no transaction and writes no audit log (the caller owns both);
  never touches client-credentials/machine tokens (`user_id = null`). It is
  reused for future user lifecycle work — do not re-derive revocation
  queries inline.

## Testing and Regression Workflow

When implementing changes:

1. Identify affected tests.
2. Run focused tests first.
3. Inspect failures.
4. Fix actual implementation defects.
5. Add regression tests where meaningful.
6. Run the full test suite when appropriate.
7. Run Pint on modified PHP files.
8. Inspect the Git diff.

Never:

- Remove failing assertions just to obtain a passing suite.
- Skip tests without documenting why.
- Claim tests passed without actual output.
- Replace meaningful integration tests with mocks that bypass the behavior being tested.
- Modify unrelated tests to accommodate an implementation defect.

For configuration-related changes, add automated tests that verify actual effective configuration (see `tests/Feature/Authentication/PassportConfigurationTest.php` as the established pattern).

## Error Investigation Workflow

When a command fails:

1. Capture the exact error.
2. Identify whether it originates from PowerShell, PHP, Laravel, the database, or a package.
3. Inspect the relevant implementation.
4. Determine the root cause.
5. Make the smallest safe correction.
6. Repeat the failed operation.
7. Verify that the correction did not introduce regressions.

Do not immediately change application code when the failure may be caused by command syntax, quoting, environment configuration, or stale caches.

## Implementation Completion Workflow

For completed implementation tasks, run the checks appropriate to the change:

```powershell
php artisan test
```

```powershell
.\vendor\bin\pint --dirty
```

```powershell
php artisan migrate:status
```

```powershell
git diff --check
```

```powershell
git status --short
```

Inspect route registration if routes were modified (`php artisan route:list --path=api`).

Use judgment — do not run irrelevant checks simply to make the completion report longer.

## Reporting Format

After each substantial task, report:

### Objective
What was implemented or investigated.

### Findings
Important technical discoveries.

### Changes
Files created, modified, or deleted.

### Verification
Commands actually executed and their results.

### Issues
Remaining failures, risks, or unresolved decisions.

### Next Step
The next logical implementation task.

Keep reports concise but technically precise.

## Efficiency Rules

The primary purpose of this skill is to reduce repetitive prompting and unnecessary tool usage:

- Reuse previously established project context; avoid rediscovering known configuration.
- Do not repeatedly inspect unchanged files.
- Batch independent read-only commands when useful.
- Prefer targeted PowerShell searches over broad directory dumps.
- Use Tinker for actual runtime verification, tests for repeatable verification, and installed vendor source when documentation is insufficient.
- Do not ask for confirmation on already approved decisions.
- Continue through independent implementation and verification steps; do not stop after every harmless command.
- Stop when an essential ambiguity, security issue, or destructive operation requires human approval.
