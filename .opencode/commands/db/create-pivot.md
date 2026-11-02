---
description: Design and guide creation of a Laravel pivot or junction
  table
---

Design one pivot table for a many-to-many relationship, optionally with
relationship attributes.

Read `@.ai/rules/index.md`, `@.ai/rules/migrations.md`,
`@.ai/guides/DATABASE-CHEATSHEET.md`, and
`@.ai/guides/DATABASE-WORKFLOW.md`.

1.  Identify the two related tables and explain the relationship in
    plain language.
2.  State pivot table **Name** and **Description**.
3.  Inspect current migrations and status using
    `php artisan migrate:status`.
4.  Show the exact command:
    `php artisan make:migration create_<pivot_name>_table`
5.  List every foreign-key column with its exact full Laravel
    definition, referenced table, and deletion behavior.
6.  Decide whether the pivot needs relationship attributes such as
    `role_id`, membership status, or assigned date.
7.  Decide whether a surrogate `id` is necessary. Do not add
    `$table->id()` by habit.
8.  Define a unique pair or other constraint that prevents invalid
    duplicate relationships.
9.  Show exact `up()` and `down()` code.
10. Let the developer implement and submit it for review unless they
    request direct editing.
11. After approval, show `php artisan migrate`, then
    `php artisan migrate:status`.
12. Do not create Eloquent relationships or extra tables unless
    separately requested.
