---
description: Plan and guide adding a column or foreign key to an
  existing Laravel table
---

Guide the developer through adding one or more explicitly requested
columns to an existing table.

Read `@.ai/rules/index.md`, `@.ai/rules/migrations.md`,
`@.ai/guides/DATABASE-CHEATSHEET.md`, and
`@.ai/guides/DATABASE-WORKFLOW.md`.

1.  State the migration name, target table, column name(s), and purpose.
2.  Inspect `php artisan migrate:status` and the existing table
    migration/model. Do not edit an already-applied migration.
3.  Show the exact command first:
    `php artisan make:migration add_<column>_to_<table>_table`
4.  For every column, show its full Laravel definition and explain type,
    nullability, default, foreign-key target, and delete behavior.
5.  Show the exact `Schema::table()` code for `up()` and the matching
    `down()` code.
6.  Consider existing rows before proposing a non-null column. Explain
    whether a nullable column, safe default, or staged data migration is
    needed.
7.  Let the developer implement the migration unless they explicitly
    request direct editing.
8.  Review before approval.
9.  After explicit approval, show `php artisan migrate`, then
    `php artisan migrate:status`.
10. Do not bundle unrelated schema changes.
