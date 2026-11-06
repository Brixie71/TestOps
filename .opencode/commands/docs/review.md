---
description: Identify outdated, conflicting, duplicated, or inaccurate project documentation (read-only)
---

Identify outdated, conflicting, duplicated, or inaccurate documentation in the
TestOps project. Read-only; do not modify files unless explicitly requested.

1. Inventory the documentation surface:

   ```powershell
   Get-ChildItem ".\.ai", ".\.agents\skills", ".\.claude\skills", ".\.opencode\commands" -Recurse -File -Filter "*.md" |
       Select-Object FullName
   Get-Content ".\README.md"
   Get-Content ".\AGENTS.md"
   ```

2. Verify instructions against observable project state - the core of this
   review:
   - Rules and guides referencing files, commands, tables, or middleware that
     no longer exist (or newly exist but are undefined in docs).
   - Design/descriptions in `.opencode/commands/*.md` that diverge from what
     the repositories' current implementation allows.
   - Migration or schema claims inconsistent with
     `php artisan migrate:status` and actual migration files.
   - Version assumptions contradicted by `composer show --direct`.
   - Claims about enabled/disabled features contradicted by real configuration
     (e.g. Passport grant state).
3. Cross-check consistency across instruction sources: `AGENTS.md` vs
   `CLAUDE.md` vs `.ai/rules/` vs skills - flag rules stated differently in
   two places, and duplicated guidance that could drift.
4. Confirm approved policy statements are consistent everywhere they appear
   (Passport baseline table, scope/permission separation) - divergence between
   copies of approved policy is a critical finding.
5. Distinguish severity: **Critical** (would make an agent implement the wrong
   thing), **Important** (materially stale), **Minor** (cosmetic/typo).
6. Report a findings list with exact file paths, the conflicting passages
   quoted briefly, the observed reality that contradicts them, and a suggested
   correction for each - without applying any change.
