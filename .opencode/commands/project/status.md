---
description: Report project status - Git branch, working tree, recent changes, architecture, and outstanding issues (read-only)
---

Produce a factual status report for the TestOps project. Do not modify any
files.

Follow the inspection discipline of the `testops-laravel-engineering` skill:
inspect before reporting, prefer targeted reads, and never report state you did
not observe.

Collect the raw state:

```powershell
git branch --show-current
git status --short
git log --oneline -10
git diff --stat
```

When relevant, run `php artisan about` and `php artisan migrate:status` for
environment and migration state.

Report:

1. Current branch and uncommitted work summary (modified, staged, untracked,
   grouped by area: app, database, routes, tests, config, docs).
2. Recent commit themes (from `git log --oneline -10`).
3. Architecture snapshot visible in the changes: models, services, middleware,
   policies, seeders, migrations.
4. Outstanding implementation issues: pending migrations, unregistered routes
   (`php artisan route:list --path=api` when relevant), TODO markers in
   modified files.
5. A short **Next Step** assessment based on observed state only.

Rules: read-only; never expose secrets, encryption keys, or credential values
seen in diffs; report exact observed output, not assumptions.
