# Laravel Database Cheat Sheet

**Purpose:** Practical reference for creating, changing, reviewing, and
removing database structures in Laravel applications.\
**Project baseline example:** Laravel 13, PHP 8.5, PostgreSQL target,
SQLite may be used for local/testing.

## 1. Non-negotiable workflow

For every schema task, the AI must show the exact command before asking
the developer to run it.

1.  Clarify the intended schema change and its purpose.
2.  Inspect current migration status and relevant existing migrations.
3.  Show the Artisan command to generate the migration.
4.  Identify the exact migration file Laravel created.
5.  Explain the table name, column names, types, constraints, defaults,
    indexes, and relationships.
6.  Show the exact `up()` and `down()` code for the requested change, or
    guide the developer to write it manually if they are practicing.
7.  Review the developer's actual migration before applying it.
8.  Show `php artisan migrate`.
9.  Show `php artisan migrate:status` and verify the new migration is
    `Ran`.
10. Run the narrowest relevant tests or schema checks when applicable.
11. Summarize what changed and what should happen next.

Do not skip status checks. Do not claim a migration succeeded until the
user provides output or the tool verifies it.

## 2. Inspect before changing

``` powershell
php artisan migrate:status
```

Inspect migration files:

``` powershell
Get-ChildItem database/migrations
Get-Content database/migrations/<migration_file>.php
```

Inspect model files when relationships are involved:

``` powershell
Get-Content app/Models/<Model>.php
```

For a PostgreSQL connection, inspect the active Laravel database
configuration without exposing credentials:

``` powershell
php artisan about
```

Never ask the user to paste passwords, connection secrets, or `.env`
contents containing credentials.

## 3. Create a table

### Command

``` powershell
php artisan make:migration create_<table_name>_table
```

For a table and model together:

``` powershell
php artisan make:model <ModelName> -m
```

### Example: `projects`

**Name:** `projects`\
**Description:** Stores projects managed in the application.

  ----------------------------------------------------------------------------------------------------------------------------
  Column                  Exact Laravel definition                                                     Purpose
  ----------------------- ---------------------------------------------------------------------------- -----------------------
  Primary key             `$table->id();`                                                              Auto-incrementing
                                                                                                       primary key

  Owner                   `$table->foreignId('owner_id')->constrained('users')->restrictOnDelete();`   References the owning
                                                                                                       user

  Name                    `$table->string('name');`                                                    Human-readable project
                                                                                                       name

  Code                    `$table->string('code', 50)->unique();`                                      Stable unique project
                                                                                                       identifier

  Description             `$table->text('description')->nullable();`                                   Optional project
                                                                                                       details

  Status                  `$table->string('status', 20)->default('active');`                           Project lifecycle
                                                                                                       status

  Timestamps              `$table->timestamps();`                                                      `created_at` and
                                                                                                       `updated_at`
  ----------------------------------------------------------------------------------------------------------------------------

**`up()` table definition:**

``` php
Schema::create('projects', function (Blueprint $table) {
    $table->id();
    $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
    $table->string('name');
    $table->string('code', 50)->unique();
    $table->text('description')->nullable();
    $table->string('status', 20)->default('active');
    $table->timestamps();
});
```

**`down()` definition:**

``` php
Schema::dropIfExists('projects');
```

### Apply and verify

``` powershell
php artisan migrate
php artisan migrate:status
```

Expected result: the new migration appears with a batch number and
`Ran`.

## 4. Add a table or column to an existing table

Do not edit a migration that has already run in a shared or persistent
database. Generate a new migration.

### Add a column

``` powershell
php artisan make:migration add_<column>_to_<table>_table
```

Example: add a nullable system role reference to users.

**Name:** `users.role_id`\
**Description:** Optional system-level role assigned to a user.

``` php
Schema::table('users', function (Blueprint $table) {
    $table->foreignId('role_id')
        ->nullable()
        ->constrained('roles')
        ->nullOnDelete();
});
```

Rollback:

``` php
Schema::table('users', function (Blueprint $table) {
    $table->dropConstrainedForeignId('role_id');
});
```

Then:

``` powershell
php artisan migrate
php artisan migrate:status
```

## 5. Foreign keys and deletion behavior

  --------------------------------------------------------------------------
  Definition                 Meaning                 Use carefully
  -------------------------- ----------------------- -----------------------
  `->constrained()`          Convention-based        Ensure referenced table
                             foreign key reference   and key match

  `->constrained('users')`   Explicitly references   Use when the column
                             `users.id`              name is not
                                                     conventional

  `->cascadeOnDelete()`      Deletes dependent rows  Suitable for disposable
                             when parent is deleted  pivot rows

  `->nullOnDelete()`         Sets the foreign key to Column must be nullable
                             `NULL` when parent is
                             deleted

  `->restrictOnDelete()`     Prevents parent         Useful for ownership or
                             deletion while          protected records
                             references exist

  `->default('active')`      Sets a default value    Choose a valid domain
                                                     default

  `->nullable()`             Allows SQL `NULL`       Do not use as a
                                                     substitute for clear
                                                     business rules

  `->unique()`               Enforces uniqueness     Consider scope and
                                                     case-sensitivity
                                                     requirements

  `->index()`                Adds an index           Add for demonstrated
                                                     query patterns, not
                                                     automatically
  --------------------------------------------------------------------------

Foreign-key columns must use compatible types with the referenced
primary key.

## 6. Create a pivot / junction table

A pivot table connects records in a many-to-many relationship. It may
also hold attributes describing the relationship, such as a
project-specific role.

### Example: `permission_role`

**Name:** `permission_role`\
**Description:** Connects roles to permissions. A role can have many
permissions, and a permission can be assigned to many roles.

``` php
Schema::create('permission_role', function (Blueprint $table) {
    $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
    $table->foreignId('role_id')->constrained()->cascadeOnDelete();
    $table->unique(['permission_id', 'role_id']);
    $table->timestamps();
});
```

Rollback:

``` php
Schema::dropIfExists('permission_role');
```

### Example: `project_user`

**Name:** `project_user`\
**Description:** Connects users to projects and stores the user's
project-level role.

``` php
Schema::create('project_user', function (Blueprint $table) {
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('role_id')->constrained()->restrictOnDelete();

    $table->unique(['project_id', 'user_id']);
    $table->timestamps();
});
```

Rollback:

``` php
Schema::dropIfExists('project_user');
```

A pivot table does not automatically require an `id` column. Use a
unique pair or other key design that matches the relationship. Add a
surrogate primary key only when there is a concrete reason, such as
independently addressing membership records.

## 7. Modify an existing table

Generate a new migration for each logical change.

### Rename a column

``` powershell
php artisan make:migration rename_<old_column>_to_<new_column>_in_<table>_table
```

Example `up()`:

``` php
Schema::table('projects', function (Blueprint $table) {
    $table->renameColumn('name', 'title');
});
```

Example `down()`:

``` php
Schema::table('projects', function (Blueprint $table) {
    $table->renameColumn('title', 'name');
});
```

Before renaming, search application code, API resources, factories,
seeders, tests, and queries for references to the old column. Confirm
database-driver support and review Laravel documentation if behavior is
version-specific.

### Change a column definition

``` powershell
php artisan make:migration change_<column>_on_<table>_table
```

Example:

``` php
Schema::table('projects', function (Blueprint $table) {
    $table->string('status', 30)->default('active')->change();
});
```

Rollback must explicitly restore the previous definition. Record the old
type, length, nullability, default, and other relevant attributes before
changing it. Do not guess the prior definition.

## 8. Delete a column or table

Destructive changes require explicit user approval and a data-impact
explanation.

### Drop a column

Command:

``` powershell
php artisan make:migration drop_<column>_from_<table>_table
```

Example `up()`:

``` php
Schema::table('projects', function (Blueprint $table) {
    $table->dropColumn('legacy_code');
});
```

A safe rollback requires recreating the column with its original
definition. However, the deleted data cannot be restored by the
rollback. Explain this limitation before approval.

### Drop a table

Command:

``` powershell
php artisan make:migration drop_<table_name>_table
```

Example:

``` php
public function up(): void
{
    Schema::dropIfExists('legacy_records');
}

public function down(): void
{
    // Recreate the original table definition here.
    // Do not pretend that dropping the table preserves its data.
}
```

Do not provide an empty or misleading rollback for destructive changes.
If the original schema cannot be reconstructed, state that limitation.

## 9. Rollback and reset commands

These commands can affect real data. Explain their scope and ask for
explicit confirmation before recommending execution against a persistent
database.

``` powershell
php artisan migrate:rollback
php artisan migrate:rollback --step=1
php artisan migrate:reset
php artisan migrate:refresh
php artisan migrate:fresh
```

-   `migrate:rollback`: rolls back the latest migration batch.
-   `migrate:rollback --step=1`: rolls back one migration.
-   `migrate:reset`: rolls back all migrations.
-   `migrate:refresh`: rolls back all migrations and runs them again.
-   `migrate:fresh`: drops all tables and runs migrations again.

**Never casually recommend `migrate:fresh`, `migrate:refresh`, or
`migrate:reset`.** They can destroy application data. Prefer additive
migrations.

## 10. Database creation versus table creation

These are different operations:

-   **Create the database:** provision the PostgreSQL database itself,
    for example `testops`.
-   **Create tables:** run Laravel migrations inside that database.

Example PostgreSQL command, run in a PostgreSQL shell by an authorized
database administrator:

``` sql
CREATE DATABASE testops;
```

Then configure the Laravel connection in `.env` using the correct local
values. Never commit `.env` or expose credentials.

After the database exists and Laravel is configured:

``` powershell
php artisan migrate:status
php artisan migrate
php artisan migrate:status
```

If the database already exists, do not create it again. Inspect the
configured connection and migration status first.

## 11. RBAC schema reference

This section records the current TestOps RBAC design, not a universal
schema for every application.

  ---------------------------------------------------------------------------------------------------------------------------------------------
  Table / field                     Name                    Description / exact definition
  --------------------------------- ----------------------- -----------------------------------------------------------------------------------
  `roles.id`                        Primary key             `$table->id();`

  `roles.name`                      Display name            `$table->string('name');`

  `roles.code`                      Stable role identifier  `$table->string('code', 50)->unique();`

  `roles.description`               Role description        `$table->text('description')->nullable();`

  `roles.scope`                     Role scope              `$table->string('scope', 20);` Values currently intended: `system` or `project`.

  `roles.timestamps`                Timestamps              `$table->timestamps();`

  `permissions.id`                  Primary key             `$table->id();`

  `permissions.name`                Display name            `$table->string('name');`

  `permissions.code`                Stable permission       `$table->string('code', 100)->unique();`
                                    identifier

  `permissions.description`         Permission description  `$table->text('description')->nullable();`

  `permissions.timestamps`          Timestamps              `$table->timestamps();`

  `permission_role.permission_id`   Permission reference    `$table->foreignId('permission_id')->constrained()->cascadeOnDelete();`

  `permission_role.role_id`         Role reference          `$table->foreignId('role_id')->constrained()->cascadeOnDelete();`

  `permission_role pair`            Unique mapping          `$table->unique(['permission_id', 'role_id']);`

  `users.role_id`                   System role reference   `$table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();`

  `projects.owner_id`               Project owner           `$table->foreignId('owner_id')->constrained('users')->restrictOnDelete();`

  `project_user.project_id`         Project reference       `$table->foreignId('project_id')->constrained()->cascadeOnDelete();`

  `project_user.user_id`            User reference          `$table->foreignId('user_id')->constrained()->cascadeOnDelete();`

  `project_user.role_id`            Project role reference  `$table->foreignId('role_id')->constrained()->restrictOnDelete();`

  `project_user pair`               One membership per      `$table->unique(['project_id', 'user_id']);`
                                    project/user
  ---------------------------------------------------------------------------------------------------------------------------------------------

### RBAC rules

-   A user has at most one system-level role through `users.role_id`.
-   A user can belong to multiple projects.
-   A user has one project-level role per project through
    `project_user.role_id`.
-   The same user may have different project roles in different
    projects.
-   Roles are centrally managed; Project Admins assign available roles
    but do not define role definitions.
-   Super Admin has global access; Project Admin access is limited to
    authorized projects.
-   Project ownership (`projects.owner_id`) is separate from project
    Admin membership.
-   Backend authorization must enforce permissions. Hiding UI elements
    is not security.
-   Do not seed example roles or permissions unless their names and
    behavior have been approved.

## 12. AI response format for every schema task

Always present the work in this order:

### A. Name and description

State the migration/table name and why it exists.

### B. Current state

Show:

``` powershell
php artisan migrate:status
```

Inspect relevant migrations and existing schema before proposing a
change.

### C. Generate command

Show the exact `php artisan make:migration ...` or
`php artisan make:model ... -m` command.

### D. Table definition

List every column with: - Column name - Laravel definition (exact code
line) - SQL/database purpose - Nullability/default - Foreign-key target
and deletion behavior - Unique/index constraints

### E. Migration code

Show the exact `up()` and `down()` method bodies. If the user is
practicing, provide the specification and let them write it, then review
their code.

### F. Review

Check table naming, foreign keys, types, nullability, indexes, data
safety, rollback behavior, and project rules.

### G. Apply

Show:

``` powershell
php artisan migrate
```

### H. Verify

Show:

``` powershell
php artisan migrate:status
```

Ask the user to paste the output. Confirm only what the output proves.

### I. Next step

Recommend one logical next step. Do not silently generate or apply
multiple migrations.

## 13. Project-specific safety rules

-   Read `.ai/rules/index.md` before planning or editing project files.
-   Read the matching migration/model rule file before editing files in
    those paths.
-   Do not modify migrations that have already run; create a new
    migration.
-   Keep migrations portable between PostgreSQL and SQLite where
    practical.
-   Use conventional Laravel naming.
-   Avoid UUIDs, JSONB, full-text indexes, and composite indexes unless
    a concrete requirement justifies them.
-   Do not invent requirements or silently add tables, columns,
    permissions, or roles.
-   Preserve the user's learning workflow: explain, show exact commands
    and code, then let the user implement and submit it for review.
-   Use one implementation step at a time unless the user explicitly
    asks for a complete batch.
