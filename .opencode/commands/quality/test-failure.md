---
description: Investigate failing tests - find root cause, decide test vs implementation defect, minimal safe correction (may fix)
---

Investigate failing tests: $ARGUMENTS

Arguments may be a test name, file path, filter, or omitted to mean "current
failing tests". Follow the `testing-best-practices` and
`testops-laravel-engineering` skills.

1. Reproduce exactly:

   ```powershell
   # Targeted first
   php artisan test tests/Path/To/FailingTest.php --filter=testName
   ```

   Capture the full failure output - exact assertion messages, exception types,
   traces - before changing anything.
2. Identify the failure cause. Distinguish:
   - A genuine implementation defect (behavior contradicts approved
     requirements or intended design).
   - An incorrect test (outdated expectations against an approved requirement).
   - Environment or quoting problems (PowerShell quoting, cache, database
     state).
   Inspect the relevant implementation, test setup, and any vendor behavior
   implicated.
3. Determine which side is correct against approved requirements. Never change
   an approved requirement to make a test pass, and never remove or weaken
   assertions merely to obtain a passing suite.
4. Make the smallest safe correction when the defect is clear. Do not rebuild
   unrelated code; preserve existing user changes.
5. Add regression tests for newly discovered behavior when meaningful, using
   test factories and existing states.
6. Rerun the fixed tests, then any neighboring tests that could share the
   cause.

   ```powershell
   php artisan test --filter=AffectedArea
   ```

7. Report: exact original failure, root cause, verdict (test or implementation
   was wrong), the correction, and observed results after the fix.
