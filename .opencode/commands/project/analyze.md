---
description: Analyze a feature, requirement, or problem - existing implementation, files, dependencies, risks, and recommended approach (read-only)
---

Analyze the requested feature, requirement, or problem: $ARGUMENTS

Do not implement anything unless explicitly requested in the arguments.

Follow the `testops-laravel-engineering` skill: inspect existing implementation
first, use targeted PowerShell searches, and reuse established project context
instead of rediscovering it.

1. Restate the request as the objective. Identify what is being asked.
2. Search for existing implementation and related conventions:

   ```powershell
   Get-ChildItem ".\app" -Recurse -File -Filter "*.php" |
       Select-String -Pattern "RelevantTerm" -List
   git log --oneline -10 -- .\app
   ```

3. Inspect the relevant files: models, services, controllers, middleware,
   policies, migrations, routes, and existing tests for the area.
4. Identify:
   - Existing implementation that already covers part of the request.
   - Relevant files and their responsibilities.
   - Dependencies between the affected classes and database tables.
   - Architectural implications (conventions in `AGENTS.md`, `.ai/rules/`,
     `.ai/guides/`, and the matching domain skill such as
     `laravel-best-practices`, `passport-development`, or
     `testing-best-practices`).
   - Risks: data implications, security, breaking changes, regression surface.
5. Recommend an implementation approach: ordered steps, files to create or
   change, tests to add, and verification commands.

Read-only. Report findings precisely with file paths. If the request depends on
an unapproved decision, flag the blocker instead of inventing a requirement.
