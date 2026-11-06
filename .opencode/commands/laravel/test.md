---
description: Run relevant Laravel tests - a targeted file, class, or filter when arguments are given, the full suite otherwise
---

Run Laravel tests for $ARGUMENTS

Rules: report only real results with their actual output; never claim tests
passed without a green result you observed; never modify tests to make failures
disappear during a plain test run.

1. If arguments identify a specific test, prefer the most focused form:

   ```powershell
   # By file path
   php artisan test tests/Feature/Authentication/PassportConfigurationTest.php

   # By class or method name
   php artisan test --filter=PassportConfigurationTest
   ```

2. If arguments name a feature or area instead of a test, locate the relevant
   tests under `tests/` first, then run them by file or filter. Inspect hanging
   or arguable mapping with:

   ```powershell
   Get-ChildItem ".\tests" -Recurse -File -Filter "*Test.php" |
       Select-String -Pattern "RelevantTerm" -List
   ```

3. If no arguments are provided, run the full suite:

   ```powershell
   php artisan test
   ```

4. Interpret results accurately: distinguish failed assertions, errors
   (exceptions), and skipped tests. Do not rerun failures repeatedly without
   investigating; switching to `/quality/test-failure` is the natural next
   step.
5. Report: which tests ran, the summary line, the duration, and any failures
   with their exact messages and traces. If tests did not run at all (e.g.
   environment error), say so plainly instead of implying a result.
