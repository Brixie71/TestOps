---
paths:
  - 'tests/*.php'
---

# Tests

## Test naming, layout, and defaults per testing-best-practices
Test layout mirrors source: app/Actions/CreateTestRun.php maps to tests/Unit/Actions/CreateTestRunTest.php; controllers land in tests/Feature/Http/Controllers/. Name test_ methods as specifications stating result and condition, e.g. test_returns_201_when_test_run_is_created(), test_failed_result_may_have_associated_defects(); no given/when/then wording; exact status codes in names of API error tests. Feature tests extend the project Tests\TestCase; true unit tests extend PHPUnit\\Framework\\TestCase and do not boot the app. Do not mock the database - use factories plus a migrated database. Use the #[Group] attribute only for run selection/skip, never for file structure.
