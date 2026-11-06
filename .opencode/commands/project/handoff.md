---
description: Generate a transferable handoff report - context, decisions, completed work, files, verification, and exact next step (read-only)
---

Generate a handoff report that another AI assistant or developer could use to
continue TestOps work without repeating investigations. Read-only; do not
modify files.

Gather evidence first:

```powershell
git status --short
git diff --stat
git log --oneline -15
php artisan migrate:status
```

Inspect relevant files referenced by the changes to describe them accurately.

Produce the report with exactly these sections:

1. **Project Context** - TestOps as a Laravel/PHP project on Windows
   (PowerShell 5.1), PostgreSQL database, Passport OAuth 2.0, PHPUnit; key
   instruction sources: `AGENTS.md`, `.ai/rules/`, `.ai/guides/`, and the
   `testops-laravel-engineering` skill plus related domain skills.
2. **Current Objective** - the task being worked on.
3. **Approved Decisions** - established policies that must not be reopened,
   including the approved Passport baseline (grants, PKCE, token lifetimes) and
   the service-client authorization separation (OAuth scopes vs
   application-level permissions vs human roles).
4. **Completed Work** - what is implemented and verified, with evidence.
5. **Modified Files** - exact paths, grouped by area, with one-line purpose for
   each.
6. **Tests and Results** - tests that were run and their actual observed
   results; state clearly if anything has not been run.
7. **Outstanding Issues** - failures, risks, unresolved decisions.
8. **Exact Next Step** - the next logical task keyed to approved requirements.

Rules: only report results that have actual observable backing; never expose
secrets, keys, or credential values; do not run migrations or tests as part of
this command.
