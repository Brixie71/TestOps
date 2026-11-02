---
description: Inspect Laravel migration status and current database
  schema state
---

Inspect the current Laravel database migration state before any schema
work.

1.  Read `@.ai/rules/index.md`, `@.ai/rules/migrations.md`, and
    `@.ai/guides/DATABASE-WORKFLOW.md`.
2.  Run or ask the developer to run:

``` powershell
php artisan migrate:status
```

3.  Inspect relevant files under `database/migrations/` and relevant
    models under `app/Models/`.
4.  Report which migrations are `Ran` and which are pending.
5.  Do not modify files or run migrations.
6.  State what is verified and what still needs inspection.
7.  Always show the exact commands used or needed.
