---
description: Continue approved implementation work - inspect state, pick the next logical task, and implement without repeating settled decisions
---

Continue the current TestOps implementation from observed state.

1. Inspect project instructions and conventions: `AGENTS.md`, `.ai/rules/index.md`
   plus matching rule/guide files, and the `testops-laravel-engineering` skill.
2. Inspect current implementation state:

   ```powershell
   git status --short
   git diff --stat
   php artisan migrate:status
   ```

3. Identify the next logical task according to already approved requirements.
   Sources of approved requirements: the user's stated task, existing test
   expectations, committed specs in documentation, and established registry
   conventions. Do not reopen settled decisions (for example the approved
   Passport policy tableau in the `testops-laravel-engineering` skill) and do
   not repeat completed work.
4. Implement the next task following `testops-laravel-engineering`: inspect
   first, reuse existing architecture, add meaningful tests, run focused tests,
   then `.\vendor\bin\pint --dirty` for modified PHP files.
5. Verify and report using the skill's reporting format
   (Objective / Findings / Changes / Verification / Issues / Next Step).
6. Continue through independent implementation and verification steps without
   asking for approval on already approved decisions. Stop when an essential
   approved requirement is missing - explain the blocker precisely instead of
   inventing a requirement or policy.

Never weaken tests to make a suite pass, never run destructive database
operations, never commit or push automatically.
