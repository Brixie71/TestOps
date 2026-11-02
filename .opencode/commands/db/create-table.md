---
description: Plan and guide creation of one Laravel database table
---

Guide the developer through creating exactly one new Laravel database
table.

Read `@.ai/rules/index.md`, `@.ai/rules/migrations.md`,
`@.ai/guides/DATABASE-CHEATSHEET.md`, and
`@.ai/guides/DATABASE-WORKFLOW.md`.

Follow the required workflow: 1. Ask for the table name and purpose only
if not already clear. 2. State **Name** and **Description**. 3. Inspect
migration status using `php artisan migrate:status` and inspect related
migrations. 4. Show the exact generation command first:
`php artisan make:migration create_<table_name>_table` If a model is
also requested, show `php artisan make:model <ModelName> -m`. 5. Provide
a complete column specification table. For every column include its
name, purpose, and exact Laravel `$table->...` line. Include foreign
keys, nullability, defaults, indexes, and unique constraints. 6. Show
the exact `up()` table definition and `down()` method. 7. Preserve the
developer's manual-learning preference: unless they ask you to write the
file, let them implement it and then review their pasted migration. 8.
Do not migrate until the developer explicitly approves after review. 9.
Then show `php artisan migrate`. 10. Then show
`php artisan migrate:status` and verify the result. 11. Work on one
table only. Do not create related tables automatically.
