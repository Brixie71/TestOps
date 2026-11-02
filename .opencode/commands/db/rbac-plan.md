---
description: Plan the next RBAC schema step without silently expanding
  scope
---

Use the approved TestOps RBAC requirements and current repository state
to plan the next single database step.

Read `@.ai/rules/index.md`, `@.ai/rules/migrations.md`,
`@.ai/rules/models.md`, `@.ai/guides/DATABASE-CHEATSHEET.md`, and
`@.ai/guides/DATABASE-WORKFLOW.md`.

RBAC invariants: - A user has at most one system-level role through
`users.role_id`. - A user may belong to multiple projects. - A user has
one project-level role per project through `project_user.role_id`. - A
user's project role may differ between projects. - Project ownership
(`projects.owner_id`) is separate from project Admin membership. - Roles
and permissions are centrally defined; Project Admins assign available
roles but do not create role definitions. - Backend authorization must
enforce permissions.

Process: 1. Inspect current migrations and run
`php artisan migrate:status`. 2. Identify what already exists. Do not
recreate or duplicate existing tables. 3. State the next single step's
**Name** and **Description**. 4. Provide the exact migration-generation
command. 5. Provide a column specification table with every full Laravel
definition. 6. Explain foreign keys, unique constraints, nullability,
defaults, and deletion behavior. 7. Show the exact `up()` and `down()`
code, or let the developer implement it manually. 8. Do not invent new
roles, permissions, tables, or authorization requirements. 9. Do not
migrate until reviewed and explicitly approved. 10. After approval, show
`php artisan migrate` and `php artisan migrate:status`. 11. Stop after
this one step and wait for the developer.
