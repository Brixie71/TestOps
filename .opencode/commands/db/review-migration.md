---
description: Review a Laravel migration before it is applied
---

Review the migration supplied by the developer or found in the
repository.

Read `@.ai/rules/index.md`, `@.ai/rules/migrations.md`, and
`@.ai/guides/DATABASE-CHEATSHEET.md`.

Review: - PHP syntax and Laravel Schema Builder usage. - Table and
column names. - Data types, lengths, nullability, and defaults. -
Foreign-key targets and compatible key types. - Cascade, null-on-delete,
and restrict-on-delete choices. - Unique constraints and indexes. -
PostgreSQL/SQLite portability where practical. - `up()` behavior. -
`down()` behavior and whether it can restore structure/data. - Whether
the migration modifies an already-applied migration. - Existing data and
deployment safety.

Response: 1. State migration **Name** and **Description**. 2. Give a
concise pass/fail result. 3. Identify exact problematic lines and exact
corrections. 4. Do not apply the migration. 5. If it passes, provide the
next commands: `php artisan migrate` `php artisan migrate:status` 6. Do
not claim it was applied until status output confirms `Ran`.
