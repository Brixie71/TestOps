# TestOps OpenCode Command Library

Reusable slash commands for repetitive TestOps development workflows. Commands
live in `.opencode/commands/`; each nested `.md` file below `commands/` becomes
a slash command whose name is its path with `/` separators (e.g.
`.opencode/commands/laravel/review.md` → `/laravel/review`). OpenCode discovers
the files automatically and reloads changes without a restart.

Commands are prompt templates, not scripts: the agent executes steps through
its normal tool permissions. No embedded shell-interpolation (`!` backtick)
blocks are used, so nothing runs outside the permission flow.

## Conventions

- Read-only commands have "read-only" noted; never modify files through them.
- `$ARGUMENTS` accepts a file, feature, test name, or scope; see each row.
- All shell examples are PowerShell 5.1 (Windows).
- Safety standards for every command: inspect before modifying; respect
  `AGENTS.md`, `.ai/rules/`, and `.ai/guides/`; preserve existing work; never
  expose secrets or keys; never commit or push automatically; never run
  destructive database operations without explicit authorization; never weaken
  tests to pass; never report results that were not observed.

## Command Inventory

### Project

| Command | Description | Read-only | Arguments | Example |
| --- | --- | --- | --- | --- |
| `/project/status` | Inspect branch, working tree, recent changes, architecture, outstanding issues | Yes | None | `/project/status` |
| `/project/analyze` | Analyze a feature/requirement/problem: existing implementation, files, dependencies, risks, recommended approach | Yes | Target (required) | `/project/analyze system settings API` |
| `/project/continue` | Continue approved work from observed state; next logical task | No (may modify within scope) | None | `/project/continue` |
| `/project/handoff` | Transferable handoff report: context, decisions, completed work, files, tests, next step | Yes | None | `/project/handoff` |

### Laravel

| Command | Description | Read-only | Arguments | Example |
| --- | --- | --- | --- | --- |
| `/laravel/implement` | Implement a feature with existing architecture, tests, and Pint | No | Feature/requirement (required) | `/laravel/implement oauth client seeder` |
| `/laravel/test` | Run targeted tests (file/class/filter) or the full suite | No (runs tests, no code changes) | Optional target | `/laravel/test PassportConfigurationTest` |
| `/laravel/pint` | Run Pint on modified PHP files and report formatting changes | No (formatting only) | None (or a file target) | `/laravel/pint` |
| `/laravel/review` | Review implementation/changes: correctness, conventions, security, coverage | Yes | Target | `/laravel/review app/Services/Settings` |

### Quality

| Command | Description | Read-only | Arguments | Example |
| --- | --- | --- | --- | --- |
| `/quality/test-failure` | Investigate failing tests; root cause, verdict, minimal fix, regression tests | No (may fix defects) | Test name/file (optional) | `/quality/test-failure PermissionMiddlewareTest::test_rejects` |
| `/quality/regression` | Identify regressions from a change: callers, coupled code, protected behavior | Yes (runs tests) | Change/scope (optional) | `/quality/regression system settings schema` |
| `/quality/audit` | Structured audit: correctness, maintainability, security, coverage, rule consistency | Yes | Target (optional) | `/quality/audit routes/api.php` |

### Security

| Command | Description | Read-only | Arguments | Example |
| --- | --- | --- | --- | --- |
| `/security/review` | Security review: authn, authz, escalation, validation, injection, secrets, tokens | Yes | Target (optional) | `/security/review oauth clients endpoint` |
| `/security/oauth-review` | Verify Passport implementation against the approved TestOps baseline | Yes | None | `/security/oauth-review` |
| `/security/authorization-review` | Review policies/middleware/gates/permissions and role boundaries | Yes | Target (optional) | `/security/authorization-review system settings` |

### Docs

| Command | Description | Read-only | Arguments | Example |
| --- | --- | --- | --- | --- |
| `/docs/sync` | Update docs and AI rules to match actual implementation | No (docs only) | Scope (optional) | `/docs/sync system settings` |
| `/docs/review` | Find outdated, conflicting, duplicated, or inaccurate documentation | Yes | None | `/docs/review` |

### Handoff

| Command | Description | Read-only | Arguments | Example |
| --- | --- | --- | --- | --- |
| `/handoff/summary` | Session summary: progress, decisions, files, verification, next actions | Yes | None | `/handoff/summary` |

### Database (pre-existing)

| Command | Description | Read-only | Example |
| --- | --- | --- | --- |
| `/db/add-column` | Plan adding a column/foreign key to an existing table | Guides, does not implement | `/db/add-column` |
| `/db/apply-migration` | Apply a reviewed migration and verify status | No (migration after approval) | `/db/apply-migration` |
| `/db/create-pivot` | Design a pivot/junction table | Guides | `/db/create-pivot` |
| `/db/create-table` | Plan creation of one new table | Guides | `/db/create-table` |
| `/db/drop-column` | Review and plan a destructive column removal | Guides; destructive step needs approval | `/db/drop-column` |
| `/db/drop-table` | Review and plan a destructive table removal | Guides; destructive step needs approval | `/db/drop-table` |
| `/db/modify-column` | Plan a safe column alteration/rename | Guides | `/db/modify-column` |
| `/db/rbac-plan` | Plan the next RBAC schema step | Guides | `/db/rbac-plan` |
| `/db/relationships` | Design/implement/review Eloquent relationships | Guides | `/db/relationships` |
| `/db/review-migration` | Review a migration before applying | Yes | `/db/review-migration` |
| `/db/status` | Inspect migration status/schema state | Yes | `/db/status` |

## Related Skills

Commands intentionally delegate deep guidance to skills instead of duplicating
it: `testops-laravel-engineering` (overall engineering workflow),
`laravel-best-practices`, `passport-development`, `testing-best-practices`.

## Notes

- Nested command names match their file paths exactly; no duplicate IDs.
- No command hardcodes a model or agent; none override built-in OpenCode
  command names (all are namespaced under `project/`, `laravel/`, `quality/`,
  `security/`, `docs/`, `handoff/`, `db/`).
