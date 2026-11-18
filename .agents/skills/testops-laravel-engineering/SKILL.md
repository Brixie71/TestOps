---
name: testops-laravel-engineering
description: Inspection and debugging workflow for the TestOps Laravel app (PostgreSQL, Passport OAuth, Artisan, Tinker, PowerShell 5.1). Load for non-trivial tasks that inspect or change Laravel code, database schema, OAuth configuration, or tests.
compatibility: opencode
---

# TestOps Laravel Engineering Workflow

Process skill for the TestOps Laravel application: how to inspect, diagnose, and verify. It does not define how to write code or how to report; see "Precedence" below.

## Precedence

`AGENTS.md` wins on every overlap. Specifically:

- Report format, length, and next-step rules: AGENTS.md section 2 (REPORT).
- Which checks to run for a change: AGENTS.md section 5 (Verification Contract).
- Commands, guardrails, and git rules: AGENTS.md section 8 (Project Facts).

This skill adds only what AGENTS.md does not cover.

## Related Skills

Load the matching skill when its domain is central to the task:

- `passport-development`: OAuth2 grants, scopes, clients, token lifetimes, PKCE.
- `laravel-best-practices`: models, controllers, migrations, queries, caching, security.
- `testing-best-practices`: test coverage, naming, isolation, assertions.
- `infer-conventions`: matching existing patterns when adding new files.

For any task involving Passport/OAuth configuration, runtime grants, scopes, or service-client authorization, also read `references/passport-inspection.md` in this skill's folder. Skip it otherwise.

## Core Principles

1. Inspect existing implementation before changing anything.
2. Prefer targeted inspection over reading whole directories or dumping large output.
3. Reuse existing architecture, services, and conventions.
4. Never assume a command succeeded; read its output.
5. Never invent project requirements. Do not re-ask approved decisions.
6. Preserve existing user changes; avoid unrelated modifications.
7. When framework behavior is uncertain, read the installed package source, not memory.

Run independent read-only inspections together when efficient. Do not hide important results or merge unrelated implementation steps.

## Tool Choice

Order of preference for inspection:

1. **Boost MCP tools** first: `database-schema` for table structure, `database-query` for read-only queries, `search-docs` for version-specific Laravel ecosystem behavior.
2. **Artisan** for routes, config, migration status (`php artisan route:list --path=api`, `config:show`, `migrate:status`, `about`).
3. **Dedicated read/search tools**, or PowerShell (`Get-Content`, `Select-String -Context 3,8`, `Get-ChildItem -Recurse -File -Filter "*.php"`) for piped or filtered searches.
4. **Tinker** only for runtime state that nothing above can show (e.g. Passport static properties, resolved container bindings).

## PowerShell 5.1 Gotchas

- `&&` is not supported. Chain with `;` or run commands separately.
- Double-quoted strings interpolate `$variables`. Use single-quoted `--execute` arguments when the PHP contains `$`.
- A literal single quote inside a single-quoted string must be doubled (`''`).
- PowerShell 5.1 can mangle embedded double quotes when passing arguments to native programs. If Tinker reports a PHP parse error, check quoting before touching application code; switching the quote style or using PHP single-quoted strings usually fixes it.
- Use backtick (`` ` ``) for line continuation.

## Tinker Safety

- Read-only inspection only. No destructive operations, no model creation (prefer tests with factories).
- Never print private keys, client secrets, passwords, tokens, or `.env` contents.
- Verify runtime state rather than trusting declared class defaults.

## Database and Migration Workflow

Investigate in this order:

1. The relevant migration, then the model and its relationships.
2. Existing conventions: see `.ai/guides/DATABASE-CHEATSHEET.md` and `DATABASE-WORKFLOW.md`.
3. Actual schema (Boost `database-schema`) when the migration alone is not enough.
4. Migration execution status: `php artisan migrate:status`. Never assume a migration has run.
5. Indexes, unique constraints, foreign keys, delete behavior, nullability.
6. Existing data and lifecycle implications, including SoftDeletes, before proposing anything destructive.

Rules:

- Never modify vendor migrations or recreate existing tables without an explicit requirement.
- Never run `migrate:fresh`, `migrate:refresh`, or destructive rollbacks without explicit authorization.
- Review a migration before executing it; verify `migrate:status` after an approved run.
- The dev database is PostgreSQL. Do not assume MySQL behavior (identifier casing, constraint/index naming, `ON DELETE` semantics).

## Vendor Source Investigation

When documentation is ambiguous, outdated, or version-specific:

1. Confirm the installed version: `composer show <vendor/package>`.
2. Search the package source for the specific method or property (`Select-String -Pattern "..." -Context 2,5`).
3. Read the surrounding implementation; trace callers only if needed.
4. Compare observed behavior with the application's configuration.
5. Cover important behavior with a test.

Vendor files are read-only reference. Never edit them to work around an app problem, and never assume behavior from another package version.

## Testing Notes

Run commands and scope per AGENTS.md section 5. Additional rules:

- Fix implementation defects, not assertions. Never remove or weaken a failing assertion to get a pass.
- Do not skip tests without documenting why.
- Do not replace integration tests with mocks that bypass the behavior under test.
- For configuration changes, test the effective configuration. `tests/Feature/Authentication/PassportConfigurationTest.php` is the established pattern.

## Error Investigation

When a command fails:

1. Capture the exact error.
2. Identify the source: PowerShell, PHP, Laravel, database, or a package.
3. Inspect the relevant implementation or output.
4. Find the root cause, then make the smallest safe fix.
5. Rerun the failed operation and check for regressions.

Do not change application code first when the cause may be command syntax, quoting, environment configuration, or a stale cache (`php artisan config:clear` only when a config change is involved).

## Efficiency

- Do not rediscover known configuration or re-read unchanged files.
- Continue through independent implementation and verification steps without pausing after harmless commands.
- Stop only for an essential ambiguity, a security issue, or a destructive operation needing approval.
