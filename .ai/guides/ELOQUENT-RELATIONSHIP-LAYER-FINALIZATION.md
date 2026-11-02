# Eloquent Relationship Layer: Finalization and Completion Checklist

**Project:** TestOps
**Document Type:** AI Workflow Reference and Implementation Gate
**Applies To:** Laravel Eloquent Models, Relationships, Pivot Tables, Model Attributes, Factories, and Relationship Tests
**Status:** Finalization Guide for the Approved RBAC Foundation
**Technical Baseline:** Laravel 13, PHP 8.5, PostgreSQL Target, SQLite-Compatible Testing, PHPUnit 12

---

## 1. Purpose

This document defines the final checks required before considering the TestOps Eloquent relationship layer complete and moving to the next development area.

It serves as a reference for both the developer and AI coding assistants working inside the TestOps repository.

It supplements existing project rules and reference guides. It does not replace them.

The scope covers the currently approved RBAC relationship foundation:

- User-to-system-role assignment through `users.role_id`.
- Project ownership through `projects.owner_id`.
- Project membership and project-role assignment through `project_user`.
- Role-to-permission assignment through `permission_role`.
- Reverse Eloquent relationships required to retrieve these assignments.

The implementation has reached the following verified checkpoint:

```text
Tests: 8 passed (19 assertions)
```

Pint was also run against the recently modified `Role.php` and `ProjectRelationshipTest.php`, followed by another successful focused test run.

This result verifies the current focused relationship tests. It does not establish that every authorization, application workflow, or data-integrity requirement has been completed.

## 2. Definition of Completion

The Eloquent relationship layer may be considered complete for the currently approved RBAC foundation when:

1. Actual database migrations support every declared relationship.
2. Each relationship uses the correct foreign key or pivot table.
3. System-level and project-level role assignments remain distinct.
4. Project ownership remains distinct from project membership and project administration.
5. Required pivot metadata is explicitly exposed.
6. Relationship methods use appropriate Eloquent return types.
7. Mass-assignment and serialization configurations are intentional.
8. Focused relationship tests cover the required directions and pivot information.
9. Modified PHP files pass Laravel Pint.
10. Focused tests pass after formatting.
11. Known limitations and deferred decisions are documented.

**Important:** Do not declare the entire RBAC authorization system complete merely because its Eloquent relationships pass. Authorization policies, permission enforcement, role lifecycle rules, and UI visibility are separate implementation concerns.

## 3. Mandatory AI Preflight

Before planning or editing related files, AI assistants must:

1. Read `.ai/rules/index.md`.
2. Identify applicable rule files based on the target file paths.
3. Read the applicable rule files before editing.
4. Read `.ai/guides/ELOQUENT-RELATIONSHIPS-AND-MODEL-EXCEPTIONS.md`.
5. Read `.ai/guides/DATABASE-CHEATSHEET.md` and `.ai/guides/DATABASE-WORKFLOW.md` when database structure or migrations are involved.
6. Inspect the actual model and migration files.
7. Check `php artisan migrate:status` before proposing schema changes.
8. Explain the proposed change before asking the developer to implement it.

Never infer the current database schema from an old conversation, proposed design, or this document alone.

When the developer is learning, work incrementally. Do not generate or modify the entire relationship layer in one pass unless explicitly requested.

## 4. Approved Relationship Map

The following table describes the approved RBAC relationship design.

The actual repository migrations remain authoritative.

| Model      | Relationship           | Eloquent Method                   | Database Source     |
| ---------- | ---------------------- | --------------------------------- | ------------------- |
| Project    | Belongs to owner       | `owner(): BelongsTo`              | `projects.owner_id` |
| Project    | Has project members    | `members(): BelongsToMany`        | `project_user`      |
| User       | Belongs to system role | `role(): BelongsTo`               | `users.role_id`     |
| User       | Belongs to projects    | `projects(): BelongsToMany`       | `project_user`      |
| Role       | Has system-role users  | `users(): HasMany`                | `users.role_id`     |
| Role       | Has permissions        | `permissions(): BelongsToMany`    | `permission_role`   |
| Role       | Has project members    | `projectMembers(): BelongsToMany` | `project_user`      |
| Permission | Has roles              | `roles(): BelongsToMany`          | `permission_role`   |

### 4.1 Relationship Semantics

The following distinctions must remain intact:

- `User::role()` and `Role::users()` represent system-level role assignments through `users.role_id`.
- `Project::members()` and `User::projects()` represent project membership through `project_user`.
- The `role_id` stored in `project_user` represents the user's role within that particular project.
- `Role::projectMembers()` retrieves project-role assignments through `project_user`.
- `Role::users()` does not retrieve project-level role assignments.
- `Project::owner()` represents project ownership through `projects.owner_id`.
- Project ownership must not be inferred from membership or pivot role.
- `Role::permissions()` and `Permission::roles()` represent the many-to-many mapping through `permission_role`.

Do not merge or rename these relationships in ways that obscure their different responsibilities.

## 5. Pivot Table Verification

### 5.1 project_user

The currently applied pivot migration contains:

- `project_id`
- `user_id`
- `role_id`
- `created_at`
- `updated_at`
- A unique constraint on `(project_id, user_id)`

The project-to-member relationship must expose the project assignment's role:

```php
return $this->belongsToMany(User::class)
    ->withPivot('role_id')
    ->withTimestamps();
```

The reverse role-to-project-members relationship must explicitly identify the pivot table:

```php
return $this->belongsToMany(User::class, 'project_user')
    ->withPivot('project_id')
    ->withTimestamps();
```

The explicit `project_id` field is important because the same user can hold the same role in multiple projects.

Consequently, `Role::projectMembers()` may return multiple user model instances representing different pivot assignments.

When project context matters, callers must inspect:

```php
$member->pivot->project_id;
```

Do not assume that one user can only appear once in the relationship collection.

### 5.2 permission_role

Verify that the pivot table contains:

- `permission_id`
- `role_id`
- Timestamps
- A unique constraint on the permission-role pair
- Appropriate foreign-key constraints

The conventional `belongsToMany()` relationship may use Laravel's inferred pivot table name when the actual table follows Laravel naming conventions.

### 5.3 Foreign-Key Deletion Behavior

Do not change deletion behavior casually.

The currently applied `project_user.role_id` foreign key uses cascade deletion. An earlier design discussion considered restricting deletion instead.

This difference must be reviewed deliberately before implementing role deactivation or deletion workflows.

Any future schema correction must use a new migration.

Never modify an already-applied migration to rewrite database history.

## 6. Model-by-Model Final Review

### 6.1 Project Model

Verify the following:

- `owner()` returns `BelongsTo`.
- `owner()` uses `owner_id`.
- `members()` returns `BelongsToMany`.
- `members()` exposes `role_id` through `withPivot('role_id')`.
- `members()` includes `withTimestamps()`.
- Project ownership is not derived from the member collection.

### 6.2 User Model

Verify the following:

- `role()` returns `BelongsTo`.
- `projects()` returns `BelongsToMany`.
- `projects()` exposes the project assignment's `role_id`.
- `projects()` includes pivot timestamps.
- System-level role assignment is protected by the intended application workflow.

**Mass-assignment warning:**

Do not casually add `role_id` to the general fillable list.

A user must not be able to assign or change their own system-level role through an ordinary profile update.

Likewise, a user must not be able to grant themselves a project role by submitting an arbitrary `role_id`.

### 6.3 Role Model

Verify the following:

- `users()` returns `HasMany`.
- `users()` represents only system-level assignments through `users.role_id`.
- `permissions()` returns `BelongsToMany`.
- `permissions()` includes pivot timestamps.
- `projectMembers()` returns `BelongsToMany`.
- `projectMembers()` explicitly uses `project_user`.
- `projectMembers()` exposes `project_id`.
- `projectMembers()` includes pivot timestamps.

Method names and comments must make the distinction between system users and project members clear.

### 6.4 Permission Model

Verify the following:

- `roles()` returns `BelongsToMany`.
- The relationship uses `permission_role`.
- Pivot timestamps are included.

## 7. Mass Assignment and Model Attribute Safety

Relationship correctness does not automatically guarantee mass-assignment safety.

Review each model's mass-assignment configuration against its intended write operations.

Important rules:

- Users must not assign their own system-level roles through ordinary profile updates.
- Project members must not grant themselves project roles through arbitrary request data.
- Project ownership changes must occur through authorized application workflows.
- Project-role assignments must occur through authorized application workflows.
- Role and permission definitions are centrally managed by the Super Admin.
- Project Admins may assign eligible existing roles only where their permissions allow it.
- Project Admins cannot create or modify role definitions.

Laravel 13 attribute-based configuration, such as `#[Fillable(...)]` and `#[Hidden(...)]`, may be used according to the project's established convention.

Do not replace the project's existing convention without justification and approval.

AI assistants must inspect the current model configuration before recommending changes.

Do not blindly add every foreign key to a fillable list.

## 8. Relationship Test Coverage

Current relationship test file:

`tests/Feature/Models/ProjectRelationshipTest.php`

The file currently covers eight cases:

1. A project belongs to its owner.
2. A project has members with assigned project roles.
3. A user belongs to projects as a member.
4. A user belongs to its assigned system role.
5. A role has users assigned through the system role foreign key.
6. A role has assigned permissions.
7. A permission has roles assigned to it.
8. A role has project members assigned through `project_user`.

Latest recorded focused test result:

```text
Tests: 8 passed (19 assertions)
```

### 8.1 Required Assertions

Relationship tests should verify more than the returned model type.

Where relevant, verify:

- The related model is the expected record.
- The correct foreign key or pivot table is used.
- Required pivot metadata is present.
- Reverse relationships return the expected records.
- System and project role assignments are not mixed.
- A user can have different project roles across projects.
- A user can hold the same project role in multiple projects through distinct pivot assignments.

### 8.2 Additional Tests for Future Authorization Work

The following are recommended future tests, not existing verified tests:

- A user with a system role is not automatically treated as a project member.
- Project ownership does not automatically imply a project role unless the application explicitly assigns one.
- A user's role in one project does not change their role in another project.
- Role deletion or deactivation follows the deliberately selected lifecycle and foreign-key behavior.
- Unauthorized users cannot modify system roles, permissions, project ownership, or project-role assignments.

These tests primarily belong to future authorization and feature testing.

Add them when the corresponding application workflows are implemented.

## 9. Formatting and Verification Workflow

After changing relationship code or relationship tests, run Laravel Pint against the changed PHP files.

The project may not yet be initialized as a Git repository. Therefore, `--dirty` may not be usable.

Example:

```powershell
vendor/bin/pint app/Models/Project.php app/Models/User.php app/Models/Role.php app/Models/Permission.php tests/Feature/Models/ProjectRelationshipTest.php --format agent
```

Then run the focused relationship test:

```powershell
php artisan test --compact tests/Feature/Models/ProjectRelationshipTest.php
```

Before declaring the relationship layer fully finalized, run the entire test suite:

```powershell
php artisan test --compact
```

Do not report tests as passing unless the command was actually executed and its output was observed.

If Pint changes files, rerun the focused tests after formatting.

A successful test run before formatting is not the final verification checkpoint.

## 10. Schema and Relationship Change Rules

AI assistants must follow these rules:

- Inspect actual migrations before proposing relationships.
- Do not invent foreign keys or pivot fields.
- Do not create migrations solely to make an assumed relationship compile.
- Explain whether a proposed change is model-only, test-only, or schema-changing.
- For schema changes, follow the database workflow guide.
- Review migrations before applying them.
- Never edit an applied migration.
- Never use `migrate:fresh`, `migrate:refresh`, or destructive reset commands as routine troubleshooting.
- Do not delete data or change foreign-key deletion behavior without explicit review and approval.
- Use explicit relationship arguments when Laravel conventions do not match the actual schema.
- Add only relationships required by the approved project scope.

## 11. RBAC Boundaries That Must Remain Intact

The relationship layer supports the approved RBAC design.

The following domain rules must remain intact:

- A user has one system-level role.
- A user can belong to multiple projects.
- A user has exactly one project-level role per project.
- Different project roles for the same user across projects are supported.
- Multiple Admins may manage one project.
- Project ownership and Admin assignment are separate concepts.
- A project may have no Project Admin when the Super Admin manages it directly.
- Project Admins cannot create or modify role definitions.
- Role definitions are centrally managed by the Super Admin.
- Custom roles are created centrally and may be reused across projects.
- Project Admins may assign existing roles only where authorized.
- Backend authorization must enforce permissions.
- Hiding navigation items or buttons is not a security mechanism.

Do not add Team, multi-tenancy, Report, or other unapproved domain models as part of relationship cleanup.

Those require separate approval.

## 12. Known Decisions and Deferred Work

Keep the following status visible to future AI-assisted development.

| Item                                              | Current State                                                               |
| ------------------------------------------------- | --------------------------------------------------------------------------- |
| System role assignment                            | Implemented through `users.role_id`                                         |
| Project role assignment                           | Implemented through `project_user.role_id`                                  |
| Role-to-permission mapping                        | Implemented through `permission_role`                                       |
| Project ownership                                 | Implemented through `projects.owner_id`                                     |
| Role-to-project-member lookup                     | Implemented through `Role::projectMembers()`                                |
| `project_user.role_id` deletion behavior          | Applied migration uses cascade; review before role lifecycle implementation |
| Role deactivation/deletion policy                 | Not finalized here                                                          |
| Authorization policies and permission enforcement | Separate future implementation                                              |
| API authentication strategy                       | Not decided here                                                            |
| TestRun/TestResult and Defect relationships       | Outside this RBAC completion checkpoint                                     |

Do not silently resolve deferred decisions in code.

## 13. Final Completion Checklist

Use this checklist before declaring the current relationship layer complete.

- [ ] Read `.ai/rules/index.md`.
- [ ] Read all applicable rule files.
- [ ] Read the Eloquent relationship and model-exception guide.
- [ ] Inspected actual model files and applied migrations.
- [ ] Verified `Project::owner()`.
- [ ] Verified `Project::members()`.
- [ ] Verified `User::role()`.
- [ ] Verified `User::projects()`.
- [ ] Verified `Role::users()` is system-role-only.
- [ ] Verified `Role::permissions()`.
- [ ] Verified `Role::projectMembers()` uses `project_user`.
- [ ] Verified `Permission::roles()`.
- [ ] Verified required pivot fields and timestamps.
- [ ] Reviewed mass-assignment safety.
- [ ] Reviewed relationship test coverage.
- [ ] Ran Pint against all changed PHP files.
- [ ] Ran focused relationship tests after Pint.
- [ ] Ran the full test suite before final completion declaration.
- [ ] Recorded known limitations and deferred decisions.
- [ ] Confirmed no unapproved domain models or schema changes were introduced.

## 14. AI Assistant Completion Behavior

When the developer asks to finalize or audit the Eloquent relationship layer:

1. Report what is verified and what remains unverified.
2. Inspect the current repository instead of assuming this document describes the latest code.
3. Do not repeat completed work without a reason.
4. Do not make schema changes during a model-only audit.
5. Do not declare the entire RBAC system complete when only relationships are complete.
6. Present one next action at a time when guiding the developer.
7. Explain the reason for each requested change.
8. Wait for the developer's output before proceeding to the next step.
9. Update this guide or project rules only when the workflow itself has materially changed.
10. Keep development within the approved TestOps scope.

## 15. Completion Status at Document Creation

At the time this document was prepared:

- Eight relationship test cases had passed with 19 assertions.
- Pint had been run against `Role.php` and `ProjectRelationshipTest.php`.
- The focused test suite passed again after formatting.
- The relationship implementation was considered complete for the currently implemented and tested RBAC foundation.
- The full test suite had not yet been reported as run after the final relationship changes.
- Authorization enforcement and deferred decisions remained separate work.

Therefore, the accurate status is:

**Eloquent relationships: implemented and focused-tested.**

**Final project-wide verification: pending.**

**Authorization implementation: separate scope.**
