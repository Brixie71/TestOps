---
description: Review requested Laravel implementation or current changes for correctness, conventions, security, and tests (read-only)
---

Review the requested Laravel implementation or current changes: $ARGUMENTS

Read-only. Do not modify files unless explicitly requested. Relevance of
`$ARGUMENTS`: a feature name, file path, or omission meaning "review current
uncommitted changes".

1. Establish the review scope:

   ```powershell
   git status --short
   git diff --stat
   git diff
   ```

   When arguments name a feature instead, inspect the implementing files with
   targeted searches regardless of Git state.
2. Read the code under review and its surrounding conventions (`AGENTS.md`,
   matching `.ai/rules/` files for paths in scope, and the relevant domain
   skill such as `laravel-best-practices` or `testing-best-practices`).
3. Review across:
   - **Correctness** - actual behavior vs apparent intent; edge cases.
   - **Laravel conventions** - naming, structure, supported APIs matching
     installed versions, no ad-hoc replacements for framework features.
   - **Architecture** - reuse of existing services/models/policies instead of
     parallel patterns; proper layering.
   - **Validation** - request validation, missing rules, mass-assignment
     exposure.
   - **Authentication** - guards, token usage, route protection.
   - **Authorization** - policies/middleware/gates coverage; client-supplied
     data as authority is a defect.
   - **Database integrity** - migration quality, nullability, indexes and
     foreign-key choices, existing-data safety.
   - **Error handling** - meaningful failures; no swallowed exceptions.
   - **Automated test coverage** - meaningful coverage for changed behavior;
     missing failure-mode tests.
   - **Regression risks** - behavior changes for existing data and callers.
4. Report findings by severity: **Critical** (breaks, insecure, data loss),
   **Important** (defects likely to matter), **Minor** (style, polish).
   Cite exact files/lines and concrete evidence for every finding; suggest the
   minimal correction for each without implementing it.
5. End with an overall verdict: ready to finalize, needs fixes, or needs
   decisions.
