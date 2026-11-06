# AGENTS.md

This file defines the shared development workflow and engineering
standards for AI agents working in this repository. Follow it together
with the applicable project rules, skills, and approved specifications.

------------------------------------------------------------------------

# 1. Development Workflow

## 1.1 Standard task lifecycle

For every non-trivial task, follow this workflow:

1.  **Understand**: identify the requested outcome, acceptance criteria,
    constraints, and relevant approved decisions.
2.  **Inspect**: examine the current implementation, related tests,
    sibling files, project rules, and Git working tree.
3.  **Plan**: identify the smallest safe implementation and the files
    likely to be affected.
4.  **Implement**: make focused changes that follow existing
    architecture and conventions.
5.  **Verify**: run relevant tests, formatting, and other checks
    appropriate to the change.
6.  **Review**: inspect the final diff for regressions, unrelated edits,
    secrets, and incomplete work.
7.  **Report**: summarize what changed, what was verified, and what
    remains.

Do not mechanically ask for approval at every stage. Proceed when the
requirement and implementation boundary are already clear. Ask for
clarification only when a genuinely essential requirement is missing,
approved decisions conflict, or an action is destructive or
security-sensitive and not already authorized.

For small, unambiguous changes, use a proportionate version of this
workflow. Do not create unnecessary plans or reports for trivial edits.

## 1.2 Requirement and decision handling

-   Treat explicit user-approved specifications and decisions as the
    source of truth.
-   Inspect existing project documentation and AI rules before assuming
    a requirement is new.
-   Do not reopen settled decisions or ask the user to reconfirm them
    without a concrete reason.
-   Do not silently change approved behavior, security boundaries, API
    contracts, or data models.
-   If implementation reveals a conflict with an approved decision, stop
    that part, explain the conflict, and continue with independent safe
    work where possible.
-   Distinguish implemented behavior from planned or Future Release
    functionality.
-   Never invent business rules, permission grants, role mappings,
    data-retention rules, or security policy.
-   When a decision is not specified, prefer the least-privileged and
    least-surprising behavior, but document the assumption and seek
    approval if it materially affects security, data integrity, or
    product behavior.

## 1.3 Repository safety

Before modifying files:

-   Inspect `git status --short`.
-   Identify existing user changes and untracked files.
-   Do not overwrite, revert, delete, or reformat unrelated user work.
-   Keep changes limited to the requested scope.
-   Do not commit, push, merge, rebase, reset, clean, or stash unless
    explicitly authorized.
-   Never stage secrets, credentials, environment files, generated
    private keys, or local-only artifacts.
-   Do not run destructive database, filesystem, or deployment commands
    without explicit authorization.
-   Do not use broad replacement or mass-formatting operations when a
    targeted change is sufficient.

Before finishing, inspect `git diff --check`, `git diff --stat`,
`git status --short`, and the relevant diff.

## 1.4 Use of reusable commands and skills

-   Inspect available project commands and skills before recreating a
    workflow.
-   Use the relevant skill whenever its domain applies.
-   Prefer existing slash commands for repeatable operations such as
    project status, database inspection, testing, code review, and
    security review.
-   Skills provide reusable procedures and domain knowledge; commands
    provide explicit repeatable actions.
-   Do not duplicate existing commands or skills without a clear
    improvement.
-   Keep project-specific instructions in the repository and avoid
    assuming they apply to unrelated projects.
-   If a required skill is unavailable, proceed using the applicable
    rules in this file and report the missing skill only when it
    materially affects the task.

------------------------------------------------------------------------

# 2. Laravel Boost Guidelines

## 2.1 Foundational context

This application is a Laravel application running on PHP 8.5. Always use
APIs that match the installed major version of each package. Do not
assume a version.

Before relying on a package API, confirm its installed version:

-   PHP packages: run `composer show --direct` for direct dependencies,
    or `composer show <vendor/package>` for a specific package.
-   JavaScript packages: inspect `package.json` and the lockfile for
    installed versions.

## 2.2 Skills activation

This project has domain-specific skills available in `**/skills/**`.

You MUST activate the relevant skill whenever you work in that domain.
Do not wait until encountering a problem.

Before writing tests, read the `testing-best-practices` skill.

## 2.3 Conventions

-   Follow existing application conventions.
-   When creating or editing a file, inspect sibling files for
    structure, naming, and implementation patterns.
-   Use descriptive variable and method names.
-   Reuse existing components, services, policies, middleware, and
    abstractions where appropriate.
-   Avoid introducing abstractions that do not solve a demonstrated
    need.

## 2.4 Verification scripts

-   Do not create verification scripts or use Tinker to verify behavior
    when automated tests already cover that behavior.
-   Prefer unit and feature tests as repeatable evidence.
-   Use Tinker for targeted runtime inspection or debugging when tests
    do not adequately expose the relevant runtime state.
-   Do not leave temporary verification scripts in the repository unless
    explicitly requested.

## 2.5 Application structure and architecture

-   Follow the existing directory structure.
-   Do not create new base folders without approval.
-   Do not change application dependencies without approval.
-   Do not modify files under `vendor/` to implement application
    behavior.
-   Prefer framework-supported extension points and APIs.

## 2.6 Frontend bundling

If a frontend change does not appear in the UI or a
`Unable to locate file in Vite manifest` error occurs, run
`npm run build` or ask the user to run `npm run dev` or
`composer run dev`, as appropriate.

## 2.7 Documentation files

Only create or modify documentation files when explicitly requested or
when the task's approved acceptance criteria require documentation
updates.

------------------------------------------------------------------------

# 3. Laravel Boost Tools and Documentation

## 3.1 Tool preference

Laravel Boost is an MCP server with tools designed for this application.
Prefer Boost tools over manual alternatives when they are available and
appropriate.

-   Use `database-query` for read-only database queries instead of raw
    SQL in Tinker.
-   Use `database-schema` to inspect table structures before designing
    migrations or models.
-   Use `get-absolute-url` to resolve the correct scheme, domain, and
    port before sharing a project URL.
-   Use `browser-logs` to inspect browser errors, exceptions, and recent
    logs. Ignore old logs unless investigating a historical issue.

## 3.2 Searching documentation

Use `search-docs` before changes that depend on Laravel ecosystem APIs,
behavior, configuration, or version-specific syntax.

-   Skip documentation searches for copy-only edits and changes where
    package documentation is irrelevant.
-   Reuse sufficient documentation results already available in the
    current context.
-   Pass a `packages` array when the relevant packages are known.
-   Use multiple broad, topic-based queries when investigating a
    feature.
-   Do not include package names in search queries when package context
    is already supplied.

Search syntax:

1.  Unquoted words use auto-stemmed AND logic.
2.  Quoted phrases require adjacent words in order.
3.  Combine words and phrases for mixed matching.
4.  Use multiple queries for OR-style discovery.

## 3.3 Project-specific rules

When `.ai/rules/` exists, it is authoritative project guidance.

Before entering plan mode or creating/editing files:

1.  Open `.ai/rules/index.md`.
2.  Identify every rule file whose path globs cover the files in scope.
3.  Read every matching rule file.
4.  Run `grep -rin 'keyword' .ai/rules` for relevant terms to catch
    rules that path matching alone may miss.
5.  Follow all applicable rules before writing code.

If `.ai/rules/` does not exist, continue using this file and other
applicable project guidance.

Do not assume that reading only the index is sufficient.

## 3.4 Artisan

Run Artisan commands directly through the command line.

-   Use `php artisan list` to discover commands.
-   Use `php artisan [command] --help` to inspect options.
-   Use `php artisan route:list` to inspect routes.
-   Filter routes with options such as `--method=GET`, `--name=users`,
    `--path=api`, `--except-vendor`, or `--only-vendor`.
-   Read configuration using `php artisan config:show app.name`,
    `php artisan config:show database.default`, or inspect the relevant
    configuration file.

Pass `--no-interaction` to Artisan commands where supported and
appropriate. Supply the required options explicitly.

## 3.5 Tinker

-   Use Tinker to execute PHP in the Laravel application context for
    targeted debugging and runtime inspection.
-   Prefer existing Artisan commands and automated tests when they
    provide sufficient verification.
-   Do not create models or persistent records in Tinker without user
    approval.
-   Use single quotes around the Tinker expression to prevent shell
    expansion:

``` powershell
php artisan tinker --execute 'Your::code();'
```

-   Use double quotes for PHP strings inside the expression:

``` powershell
php artisan tinker --execute 'User::where("active", true)->count();'
```

-   Avoid complicated nested quoting. If a parse error occurs, inspect
    command quoting before changing application code.
-   Never print secrets, private keys, passwords, or token contents.

------------------------------------------------------------------------

# 4. PHP Standards

-   Always use curly braces for control structures, including
    single-line bodies.
-   Use PHP 8 constructor property promotion where appropriate.
-   Do not add empty zero-parameter constructors unless the constructor
    is private and necessary.
-   Declare explicit return types and type hints for method parameters.
-   Use TitleCase for enum keys.
-   Prefer PHPDoc blocks over inline comments.
-   Add inline comments only for exceptionally complex logic.
-   Use array-shape PHPDoc definitions where they improve clarity and
    static analysis.
-   Follow the project's existing formatting and naming conventions.

------------------------------------------------------------------------

# 5. Laravel Architecture and Implementation

## 5.1 Laravel conventions

-   Use `php artisan make:` commands to create Laravel files such as
    migrations, controllers, models, requests, resources, and tests.
-   Use `php artisan make:class` for generic PHP classes where
    appropriate.
-   Check Artisan help before selecting generator options.
-   Pass `--no-interaction` to supported Artisan generation commands.
-   Do not create new models without approval when the model introduces
    a new domain concept or changes the data model.
-   When creating an approved model, inspect whether a factory and
    seeder are appropriate. Do not create unnecessary seeders or
    factories.

## 5.2 APIs and Eloquent Resources

-   Use API versioning and Eloquent API Resources by default unless
    existing routes establish a different convention.
-   Follow the established API response envelope and error-handling
    conventions.
-   Validate input through the appropriate Laravel validation mechanism.
-   Keep authentication and authorization checks explicit.
-   Use named routes and the `route()` helper when generating links to
    application routes.
-   Do not expose internal model fields or sensitive attributes through
    API responses.

## 5.3 Service and domain boundaries

-   Keep controllers focused on HTTP concerns.
-   Reuse existing domain services and authorization mechanisms.
-   Do not introduce repositories, managers, factories, or service
    layers solely for naming or indirection.
-   Keep business rules in the established domain layer.
-   Avoid duplicating permission checks across controllers when a shared
    policy, gate, middleware, or service is the established design.
-   Preserve transaction boundaries and consistency requirements.

------------------------------------------------------------------------

# 6. Database and Migration Workflow

Before designing or modifying a migration:

1.  Read `.ai/rules/index.md` and all applicable database rules.
2.  Inspect the relevant existing migration files.
3.  Inspect the corresponding models and relationships.
4.  Inspect the actual database schema using Boost `database-schema`
    when available.
5.  Check migration status.
6.  Review column types, nullability, defaults, indexes, unique
    constraints, foreign keys, and delete behavior.
7.  Consider existing data, soft deletes, retention, and application
    lifecycle behavior.

Migration requirements:

-   Never modify Laravel or package vendor migrations to implement
    application behavior.
-   Do not assume a migration has run; verify with
    `php artisan migrate:status`.
-   Use the correct database-specific types and behavior.
-   Add foreign keys and indexes when justified by the domain and
    lifecycle.
-   Consider whether cascading deletion, restriction, or explicit
    application cleanup is appropriate.
-   Do not add constraints that conflict with established lifecycle
    requirements.
-   Do not run `migrate:fresh`, `migrate:refresh`, destructive
    rollbacks, or data deletion without explicit authorization.
-   Review generated migration contents before execution.
-   After an authorized migration, verify its status and inspect the
    resulting schema.

For PostgreSQL, use PostgreSQL-compatible types, constraints, and SQL
behavior. Do not assume MySQL semantics.

------------------------------------------------------------------------

# 7. Authentication, Authorization, and Security

## 7.1 General security principles

-   Apply least privilege.
-   Deny by default when authorization is uncertain.
-   Validate all untrusted input.
-   Never trust client-supplied role, permission, ownership, or identity
    claims without server-side verification.
-   Protect secrets and sensitive data in logs, responses, files, and
    Git.
-   Consider authentication, authorization, auditability, revocation,
    and lifecycle behavior together.
-   Add tests for both permitted and denied paths.
-   Do not weaken security controls to make tests pass.

## 7.2 Human authorization

-   Preserve the distinction between authentication and authorization.
-   Follow existing TestOps role and permission rules.
-   Do not grant privileges based solely on a role or identifier
    supplied by a request.
-   Protect privileged actions with the established authorization and
    password-confirmation requirements.
-   Preserve audit requirements for sensitive administrative actions.

## 7.3 OAuth and machine-client authorization

Treat these as separate concepts:

1.  OAuth token scopes define token-level capabilities.
2.  Application-level service permissions define what a machine client
    may do in TestOps.
3.  Human RBAC defines what an authenticated user may do.

A machine client is a service principal, not a human user.

Do not map machine clients to human administrator roles. Do not assume a
valid OAuth token automatically authorizes an application operation.
Enforce both token scope and application permission where required.

Follow the approved TestOps OAuth specification and current
implementation. Do not enable additional grant types, expand
service-client permissions, or change token lifetimes without approval.

## 7.4 Secrets and cryptographic material

-   Never print private-key contents, passwords, client secrets, API
    keys, or credentials.
-   Verify key availability and validity without displaying key
    material.
-   Keep local secret files excluded from Git.
-   Do not commit `.env`, private keys, or generated credentials.
-   Do not rotate or replace cryptographic keys without explicit
    authorization and a documented migration plan.

------------------------------------------------------------------------

# 8. Testing and Quality Assurance

## 8.1 Test requirements

-   Add or update tests for behavior and logic changes when tests
    provide meaningful regression coverage.
-   Pure copy, styling, and layout-only changes do not require tests.
-   Test the changed behavior and important failure modes.
-   Do not add tests unrelated to the change.
-   Read the `testing-best-practices` skill before writing tests.
-   Prefer feature tests for behavior that crosses Laravel application
    boundaries.
-   Use unit tests for isolated logic where appropriate.
-   Use factories and existing factory states rather than manually
    constructing complex model records.

## 8.2 Test creation

Use PHPUnit:

``` powershell
php artisan make:test --phpunit SomeFeatureTest
```

Do not include the suite directory in the test name.

For unit tests:

``` powershell
php artisan make:test --phpunit --unit SomeUnitTest
```

Follow existing test directory and naming conventions.

## 8.3 Test execution

-   Run the narrowest test set that covers the change.
-   Use `php artisan test --compact <path>` or `--filter=<testName>`
    where appropriate.
-   Rerun a test after changing it.
-   Run the full test suite when the change has broad impact, affects
    shared infrastructure, or is part of final integration verification.
-   Report the exact test command and actual result.
-   Investigate failures rather than suppressing them.

## 8.4 Regression and failure analysis

When tests fail:

1.  Capture the exact failure.
2.  Determine whether the failure is in the test, implementation,
    environment, database, or dependency.
3.  Inspect the relevant code and test setup.
4.  Identify the root cause.
5.  Apply the smallest safe correction.
6.  Add regression coverage when appropriate.
7.  Rerun the affected tests.
8.  Run broader tests when the fix could affect shared behavior.

Do not remove assertions, skip tests, or change expected behavior merely
to obtain a passing suite.

------------------------------------------------------------------------

# 9. Laravel Pint

If PHP files are modified, run:

``` powershell
vendor/bin/pint --dirty --format agent
```

-   Do not use `vendor/bin/pint --test --format agent`.
-   Use Pint to correct formatting issues.
-   Inspect the final diff after formatting to ensure unrelated files
    were not changed.

------------------------------------------------------------------------

# 10. Windows and PowerShell Workflow

This project is developed in a Windows environment using PowerShell.

-   Prefer PowerShell-compatible commands.
-   Do not assume Bash syntax, GNU utilities, or Unix path conventions
    are available.
-   Use `.\` for repository-relative executable paths where appropriate.
-   Use `Get-Content`, `Get-ChildItem`, `Select-String`, and
    `Get-Command` for native inspection.
-   Use `git` commands through PowerShell.
-   Keep commands targeted and readable.
-   Use PowerShell line continuation with the backtick only when needed;
    avoid trailing whitespace after it.
-   Be careful with nested PowerShell and PHP quoting.
-   Do not clear terminal output or discard useful diagnostics before
    they have been captured.

Useful inspection commands:

``` powershell
git status --short
git diff --check
git diff --stat
Get-Content ".\path\to\file.php"
Get-ChildItem ".\path" -Recurse -File
Get-ChildItem ".\path" -Recurse -File -Filter "*.php" |
    Select-String -Pattern "searchTerm"
Select-String -Path ".\path\to\file.php" -Pattern "methodName" -Context 3,8
```

------------------------------------------------------------------------

# 11. Frontend and Dependency Management

-   Inspect `package.json` and the lockfile before relying on JavaScript
    package APIs.
-   Do not change dependencies or lockfiles without approval.
-   Reuse existing frontend components and conventions.
-   Keep frontend and backend validation consistent where appropriate.
-   For Vite-related build or manifest issues, use the documented
    frontend build workflow.
-   Do not introduce a new frontend framework or build tool without
    approval.

------------------------------------------------------------------------

# 12. Documentation and AI Instructions

-   Keep documentation consistent with implemented behavior.
-   Clearly label planned functionality and Future Release items.
-   Do not document an endpoint, feature, or security control as
    implemented until verified.
-   Preserve approved specifications and decision history.
-   Update relevant AI rules when an approved architectural or workflow
    decision changes.
-   Avoid duplicating the same rule across multiple files when a single
    authoritative location is practical.
-   When rules conflict, identify the conflict and follow the more
    specific approved project rule; do not silently discard either rule.

------------------------------------------------------------------------

# 13. Git and Change Review

Before finishing any implementation:

``` powershell
git status --short
git diff --check
git diff --stat
git diff
```

Review for:

-   Unrelated modifications.
-   Accidental deletions.
-   Debugging statements.
-   Temporary files.
-   Secrets and credentials.
-   Generated artifacts.
-   Incomplete TODOs.
-   Missing tests.
-   Formatting issues.
-   Documentation that overstates implementation.

Do not commit or push unless explicitly requested.

------------------------------------------------------------------------

# 14. Completion Report

For every substantial task, provide a concise report:

## Objective

What was requested.

## Findings

Relevant discoveries and constraints.

## Changes

Files created, modified, or deleted, with a short explanation.

## Verification

Commands actually executed and their results, including test counts when
available.

## Remaining Issues

Known limitations, unresolved failures, and decisions requiring
approval.

## Next Step

The next logical task, if one remains.

Never claim a command, test, migration, or review succeeded unless it
actually ran and its result was inspected.

For trivial changes, keep the report proportionate.

------------------------------------------------------------------------

# 15. Deployment

Laravel can be deployed using Laravel Cloud. Deployment provider
selection, production configuration, credentials, migrations, and
release actions require explicit project authorization.

Never deploy, alter production data, or change production secrets
without explicit authorization.

------------------------------------------------------------------------

# 16. Existing Project-Specific Rules

The following project rules remain authoritative and must be followed:

-   `.ai/rules/index.md` and all applicable rules under `.ai/rules/`.
-   Relevant skills under the project's skill directories.
-   Approved project specifications and architecture decisions.
-   Existing code conventions and tested application behavior.

This file supplements those rules; it does not override more specific
approved requirements.


# STRICT EXECUTION POLICY: FOLLOW THE SPECIFICATION EXACTLY

These instructions are mandatory and apply throughout the entire task.

## 1. Execute, Do Not Overthink

Your primary responsibility is to complete the explicitly requested task.

- Follow the provided specification as the implementation contract.
- Do not reinterpret, expand, redesign, or improve the requirements unnecessarily.
- Do not introduce additional features, abstractions, workflows, or architecture that were not requested.
- Do not investigate unrelated parts of the application.
- Do not repeatedly reconsider decisions that have already been approved.
- Do not generate alternative designs when the specification already defines the solution.
- Do not spend excessive effort researching possibilities when the installed implementation and project conventions provide sufficient information.

Make reasonable implementation decisions within the approved requirements and proceed.

## 2. Strict Scope Control

Only perform work directly necessary to satisfy the specification.

For every proposed change, ask:

"Is this change necessary to complete the requested task?"

If the answer is no, do not implement it.

Specifically:

- No unrelated refactoring.
- No speculative security enhancements outside the requested scope.
- No unnecessary helper classes or service layers.
- No additional dependencies.
- No unnecessary documentation files.
- No unrelated test files.
- No duplicate implementations.
- No placeholder features.
- No unnecessary configuration changes.
- No modifications to unrelated application functionality.

Do not turn a focused implementation into a broader project cleanup.

## 3. Follow the Provided Verification Checklist

Use the tests and verification procedures explicitly requested in the task.

Do not invent additional verification stages, benchmarking procedures, test suites, or repetitive inspections unless a specific failure or implementation requirement makes them necessary.

Required verification for this task:

1. Laravel Pint on modified PHP files.
2. Relevant new tests.
3. Complete existing test suite.
4. Migration status.
5. Registered route inspection.
6. Final Git diff inspection.
7. Confirmation that secrets and OAuth keys were not staged or modified.

Do not repeat successful checks without a concrete reason.

Do not create tests for hypothetical scenarios unrelated to the requirements.

Do not weaken or remove required tests to obtain passing results.

## 4. Minimal Implementation Principle

Choose the simplest implementation that correctly satisfies the requirements.

- Prefer existing Laravel and Passport functionality.
- Reuse existing application services, middleware, models, and conventions.
- Avoid unnecessary abstractions.
- Avoid premature extensibility.
- Avoid creating generic frameworks for a single feature.
- Do not implement future-release functionality.
- Do not solve problems that the specification does not ask you to solve.

Future extensibility must not become an excuse for overengineering.

## 5. Investigation Limits

Inspect only what is needed to understand and safely implement the task.

Prioritize:

1. Relevant application files.
2. Relevant migrations and models.
3. Relevant middleware, policies, and services.
4. Relevant tests.
5. Relevant installed Passport source when its behavior must be verified.

Do not recursively inspect the entire repository without a concrete reason.

Do not repeatedly read files whose contents have not changed.

Do not search the internet when the installed package source and existing project documentation already answer the question.

## 6. Decision and Confirmation Policy

Do not ask the user to confirm decisions already explicitly approved in the specification.

Do not stop for routine implementation choices that can be resolved by following existing project conventions.

Only stop when:

- An essential requirement is missing.
- Two approved requirements directly conflict.
- A security boundary cannot be implemented safely.
- A destructive or irreversible action is required but not authorized.
- The requested implementation would damage existing functionality or data.

If a blocker affects only one part of the task, continue with independent work that is safe to complete.

Never silently invent a security policy to resolve an ambiguity.

## 7. Implementation Discipline

Work in this order:

1. Inspect the relevant existing implementation.
2. Identify the exact files requiring changes.
3. Implement the requested functionality.
4. Add only the required regression tests.
5. Run the specified verification checklist.
6. Review the final diff.
7. Report completion.

Avoid unnecessary pauses between these steps.

Do not provide lengthy reasoning or repeatedly explain routine decisions.

Do not ask the user to execute individual commands when you can safely execute them through your available tools.

## 8. Completion Means Stop

Once all completion criteria in the original specification are satisfied:

- Stop implementation.
- Do not add additional improvements.
- Do not start unrelated cleanup.
- Do not propose implementing future features as part of the current task.
- Do not continue investigating already resolved areas.

Provide the requested final report.

## 9. Priority Rule

The original task specification defines WHAT must be implemented.

This Strict Execution Policy defines HOW to implement it efficiently.

Both must be followed.

If a potential improvement is outside the original task scope, exclude it.

Your objective is not to produce the largest or most sophisticated implementation.

Your objective is to produce the smallest correct, secure, tested implementation that satisfies every stated requirement.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
