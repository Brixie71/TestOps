---
description: Implement a feature using existing TestOps architecture and conventions, with tests and Pint verification
---

Implement the requested feature: $ARGUMENTS

Follow the `testops-laravel-engineering` skill workflow and the matching domain
skill for the area (e.g. `passport-development`, `laravel-best-practices`,
`testing-best-practices`). Project rules live in `AGENTS.md`, `.ai/rules/`, and
`.ai/guides/`.

1. Inspect before changing: read the relevant existing controllers, services,
   models, middleware, policies, migrations, routes, and tests.
2. Reuse existing architecture and patterns. Avoid unnecessary abstractions.
   Check siblings for correct structure, naming, and style before creating a
   file; prefer `php artisan make:` commands for new classes.
3. Implement only within the requested scope. Do not implement requirements
   that contradict approved project specifications. Never invent missing
   requirements - stop and report the blocker if essential detail is missing.
4. Preserve existing user changes; avoid unrelated modifications.
5. Add or update automated tests for behavior and logic changes, using test
   factories and existing states.
6. Run focused tests:

   ```powershell
   php artisan test --filter=RelevantTestName
   ```

   Fix real defects, not assertions. Rerun after each change until green.
7. Format modified PHP files:

   ```powershell
   .\vendor\bin\pint --dirty
   ```

8. Run the wider affected test set when the change touches shared
   infrastructure, and inspect `git diff --stat` to confirm the change is
   scoped.
9. Report: Objective, Findings, Changes, Verification, Issues, Next Step.
   Report only tests actually run with their actual results.

Never commit or push, never run destructive database operations, and never
expose secrets in output.
