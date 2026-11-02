# AI Database Development Workflow

**Purpose:** Reusable operating procedure for AI assistants helping
develop Laravel database schemas, especially RBAC.\
**Primary reference:** `.ai/guides/DATABASE-CHEATSHEET.md`

## Mandatory behavior

When the developer asks to create, modify, inspect, or remove database
structures:

1.  Read `.ai/rules/index.md`.
2.  Read every matching rule file for the paths being changed,
    especially `.ai/rules/migrations.md` and `.ai/rules/models.md`.
3.  Read `.ai/guides/DATABASE-CHEATSHEET.md`.
4.  Inspect current migration status and relevant migration/model files.
5.  Explain the change before editing.
6.  Always show exact terminal commands.
7.  Work one logical schema change at a time unless the developer
    explicitly requests a batch.
8.  If the developer is practicing, do not write the migration for them
    unless they ask. Give the table specification and exact Laravel
    lines, then review their implementation.
9.  Never run a migration or destructive command without explicit user
    approval.
10. After migration, verify with `php artisan migrate:status`.

## Standard sequence

Use this exact order:

### 1. Identify the task

State: - **Migration name** - **Table name** - **Description** - Whether
this is a new table, adding/removing/changing columns, a pivot table, or
a relationship change. - Data-impact and rollback considerations.

If the user has not specified enough to design the schema safely, ask
one focused question.

### 2. Inspect current state

Show and run:

``` powershell
php artisan migrate:status
```

Inspect relevant files:

``` powershell
Get-ChildItem database/migrations
Get-Content database/migrations/<relevant_migration>.php
```

Do not assume a table or column exists because it was discussed earlier.
Confirm from the current files/status or schema inspection.

### 3. Generate the migration

Show the exact command first.

New table:

``` powershell
php artisan make:migration create_<table_name>_table
```

Add a column:

``` powershell
php artisan make:migration add_<column>_to_<table_name>_table
```

Remove a column:

``` powershell
php artisan make:migration drop_<column>_from_<table_name>_table
```

Modify a column:

``` powershell
php artisan make:migration change_<column>_on_<table_name>_table
```

Model plus migration:

``` powershell
php artisan make:model <ModelName> -m
```

After generation, ask for or inspect the actual generated filename.
Never invent its timestamp.

### 4. Specify the table

Before code, provide a table with:

  Required item            Description
  ------------------------ -----------------------------------------------
  **Name**                 Exact database table name
  **Description**          What the table represents
  **Column**               Exact column name
  **Laravel definition**   Full `$table->...` line
  **Purpose**              Why the column exists
  **Constraints**          Foreign key, unique, index, default, nullable
  **Delete behavior**      Cascade, null, restrict, or no foreign key

For every foreign key, identify the referenced table and key. Explain
why the chosen deletion behavior is appropriate.

For pivot tables, explicitly state: - The two or more related tables. -
The foreign key columns. - Whether the pivot stores relationship
attributes (for example, `role_id`). - The unique pair or other key
constraint. - Whether a separate `id` is necessary. Do not add one by
habit.

### 5. Show `up()` and `down()`

For a new table, show the full `Schema::create()` block and the matching
`Schema::dropIfExists()` rollback.

For an existing table, show the exact `Schema::table()` changes and a
rollback that restores the previous schema.

Never provide a rollback that falsely implies deleted data can be
recovered. Explain when a rollback only restores structure, not data.

If the developer is manually learning: - Present the specification and
exact lines. - Let the developer write the code. - Review their pasted
code. - Do not move to migration until it passes review.

### 6. Review before migration

Check: - Correct Laravel/PHP syntax. - Correct table and column names. -
Correct foreign-key targets. - Compatible key types. - Nullability and
default values. - Unique constraints and indexes. - Referential deletion
behavior. - PostgreSQL/SQLite portability where practical. - Correct
rollback. - Existing data impact. - Compliance with
`.ai/rules/migrations.md`.

If errors exist, identify only the necessary corrections and wait for
the developer to update the file.

### 7. Apply the migration

After review and explicit go-ahead, show:

``` powershell
php artisan migrate
```

Do not bundle this with destructive reset commands.

### 8. Verify

Show:

``` powershell
php artisan migrate:status
```

Ask the developer to paste the output when the assistant cannot inspect
it directly.

Confirm: - The intended migration appears. - Its status is `Ran`. - Its
batch number is visible. - No unrelated migration was assumed to have
run.

If migration fails, stop and diagnose the error. Do not recommend
`migrate:fresh` as a reflex.

### 9. Test and continue

When appropriate, run a narrow test:

``` powershell
php artisan test --compact
```

For model/relationship changes, add or run focused tests. Then state the
verified result and recommend one next step.

## RBAC-specific design workflow

Use this order as a starting point, but inspect actual project state
before each step:

1.  `roles` table
2.  `permissions` table
3.  `permission_role` pivot
4.  Add `role_id` to `users` for the system-level role
5.  `projects` table with a distinct owner reference
6.  `project_user` pivot with `project_id`, `user_id`, and project-level
    `role_id`
7.  Eloquent relationships
8.  Role and permission seed data only after names and intended behavior
    are approved
9.  Authorization policies/middleware and backend enforcement
10. Feature tests for allowed and denied access

### RBAC invariants to preserve

-   A user has at most one system role via `users.role_id`.
-   A user can belong to multiple projects.
-   A user has one project role per project via `project_user.role_id`.
-   A user's project role may differ between projects.
-   `project_user` must prevent duplicate `(project_id, user_id)` pairs.
-   Project ownership and Admin membership are separate.
-   Role definitions are centrally managed by Super Admin.
-   Project Admins may assign available roles only within their
    authorization.
-   Backend permission checks are mandatory; UI hiding alone is not
    authorization.

## Destructive operation policy

The AI must not execute or casually recommend these against a persistent
database:

``` powershell
php artisan migrate:rollback
php artisan migrate:reset
php artisan migrate:refresh
php artisan migrate:fresh
```

Before any such command: 1. Explain exactly what it rolls back or drops.
2. Identify the target database/environment. 3. Explain data-loss risk.
4. Ask for explicit confirmation. 5. Prefer a new forward migration when
that is safer.

Dropping columns/tables, truncating tables, and deleting migrations also
require explicit approval.

## Required response template

Use this structure in every database task:

1.  **Name**
2.  **Description**
3.  **Current status command**
4.  **Migration-generation command**
5.  **Column specification table with exact Laravel lines**
6.  **`up()` implementation**
7.  **`down()` implementation**
8.  **Review checklist**
9.  **Migration command**
10. **Status verification command**
11. **Next step**

Do not omit commands. Do not claim success without verification.
