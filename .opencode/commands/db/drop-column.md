---
description: Review and plan a destructive Laravel column removal
---

Treat column removal as destructive.

Read `@.ai/rules/index.md`, `@.ai/rules/migrations.md`,
`@.ai/guides/DATABASE-CHEATSHEET.md`, and
`@.ai/guides/DATABASE-WORKFLOW.md`.

1.  Identify the exact table and column, its current definition, and all
    application references.
2.  State **Name** and **Description** of the proposed migration.
3.  Explain data loss and dependencies before giving an implementation.
4.  Ask for explicit user approval before proceeding with the
    destructive change.
5.  Show the command:
    `php artisan make:migration drop_<column>_from_<table>_table`
6.  Show the exact `up()` code using `dropColumn()` and explain that the
    data is not recoverable through rollback.
7.  Show a `down()` implementation that recreates the original column
    definition if known. If the definition or data restoration cannot be
    guaranteed, state that limitation.
8.  Review the migration before applying it.
9.  Only after explicit approval, show `php artisan migrate`, then
    `php artisan migrate:status`.
10. Never use `migrate:fresh` as a shortcut.
