---
description: Plan a safe Laravel column alteration or rename
---

Guide the developer through modifying one existing column.

Read `@.ai/rules/index.md`, `@.ai/rules/migrations.md`,
`@.ai/guides/DATABASE-CHEATSHEET.md`, and
`@.ai/guides/DATABASE-WORKFLOW.md`.

1.  Inspect current migration status and the original column definition.
2.  State the migration **Name** and **Description**.
3.  Show the exact command:
    `php artisan make:migration change_<column>_on_<table>_table` For a
    rename, use a descriptive `rename_...` migration name.
4.  Document the old definition and proposed new definition, including
    type, length, nullability, default, indexes, and foreign keys.
5.  Show exact `up()` and `down()` code. The rollback must restore the
    actual previous definition, not a guess.
6.  Search for code references that may depend on the old name/type.
7.  Explain data truncation, conversion, and database-driver risks.
8.  Let the developer implement and submit the migration for review
    unless they request direct editing.
9.  After explicit approval, show `php artisan migrate`, then
    `php artisan migrate:status`.
10. Do not edit a migration that has already run.
