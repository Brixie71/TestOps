---
description: Review and plan a destructive Laravel table removal
---

Treat table removal as highly destructive.

Read `@.ai/rules/index.md`, `@.ai/rules/migrations.md`,
`@.ai/guides/DATABASE-CHEATSHEET.md`, and
`@.ai/guides/DATABASE-WORKFLOW.md`.

1.  Identify the exact table, foreign-key dependencies, model, routes,
    queries, factories, seeders, and tests that use it.
2.  State migration **Name** and **Description**.
3.  Explain exactly what data and relationships will be lost.
4.  Ask for explicit user approval before generating or applying the
    destructive migration.
5.  Show the command:
    `php artisan make:migration drop_<table_name>_table`
6.  Show the exact `up()` code using `Schema::dropIfExists()`.
7.  Show a `down()` method that recreates the original table structure
    if it is known. Explain that schema recreation does not restore
    deleted records.
8.  Review foreign-key dependency order and rollback order.
9.  After explicit approval, show `php artisan migrate`, then
    `php artisan migrate:status`.
10. Never recommend a database reset to avoid designing a safe
    migration.
