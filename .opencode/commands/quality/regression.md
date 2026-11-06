---
description: Identify possible regressions introduced by a change - affected dependencies, call sites, and existing tests (read-only)
---

Identify possible regressions introduced by the requested change: $ARGUMENTS

Arguments: a change description, file path, branch state, or omitted meaning
"the current uncommitted changes". Read-only.

1. Establish the change surface:

   ```powershell
   git status --short
   git diff --stat
   git diff
   ```

   or inspect the named files when the change is already committed.
2. Map affected code with targeted searches - callers of changed methods,
   models coupled through relationships/factories, policies extending the
   changed classes, middleware and routes referencing changed names:

   ```powershell
   Get-ChildItem ".\app", ".\tests" -Recurse -File -Filter "*.php" |
       Select-String -Pattern "ChangedSymbol" -List
   ```

3. Inspect existing tests covering the affected areas and note which behaviors
   are already protected. Identify protected behaviors most likely to break.
4. Cross-check lifecycle implications: existing database rows, migrations
   expecting old shapes, seeder defaults, route contracts, and soft-deletion
   behavior.
5. Run the already-protected tests that best cover the change surface
   (read-only command; runs tests but changes no code):

   ```powershell
   php artisan test --filter=MostRelevantCoverage
   ```

6. Report a ranked regression-risk list: the behavior at risk, the concrete
   evidence (file/line, call site, data dependency), whether an existing test
   covers it, and the suggested verification for the gaps.
