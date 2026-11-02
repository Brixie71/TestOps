
# Eloquent Relationships and Model Exceptions

## Purpose

This guide documents Eloquent relationship conventions, database prerequisites, and common Laravel exceptions encountered during TestOps development.

AI coding assistants must consult this guide when creating or modifying:

- Eloquent models
- Model relationships
- Database migrations
- Model factories
- Pivot tables
- Relationship tests
- Mass-assignment configurations

The objective is to identify relationship requirements and common implementation problems before they become runtime exceptions.

---

## 1. Relationship Implementation Workflow

Before implementing an Eloquent relationship, follow this workflow:

1. Read `.ai/rules/index.md`.
2. Identify and read the applicable project rules.
3. Inspect the actual model files.
4. Inspect the relevant database migrations.
5. Identify the correct relationship type.
6. Verify foreign keys and pivot table structures.
7. Check nullability, uniqueness, and deletion behavior.
8. Explain the proposed implementation before editing.
9. Implement one relationship at a time when working interactively.
10. Create focused database-backed tests.
11. Run the narrowest relevant test suite.
12. Run Laravel Pint on changed PHP files.

**Important:** Never assume that a relationship exists simply because two models are related conceptually.

The actual database schema must support the relationship.

Do not create unnecessary migrations just to make an Eloquent relationship work.

---

## 2. Eloquent Relationship Types

### 2.1 Belongs To

Use `belongsTo()` when the current model contains the foreign key.

Example:

`projects.owner_id` references `users.id`.

```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;

public function owner(): BelongsTo
{
    return $this->belongsTo(User::class, 'owner_id');
}
```

Relationship direction:

Project → User

Database foreign key:

`projects.owner_id`

### 2.2 Has Many

Use `hasMany()` when another table contains the foreign key referencing the current model.

Example:

`users.role_id` references `roles.id`.

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function users(): HasMany
{
    return $this->hasMany(User::class);
}
```

Relationship direction:

Role → Users

### 2.3 Has One

Use `hasOne()` when one model owns exactly one related record through a foreign key.

```php
use Illuminate\Database\Eloquent\Relations\HasOne;

public function profile(): HasOne
{
    return $this->hasOne(Profile::class);
}
```

Only use this relationship when the database structure supports the intended one-to-one constraint.

### 2.4 Belongs To Many

Use `belongsToMany()` when two models are connected through a pivot table.

Example:

Projects and Users are connected through `project_user`.

Project model:

```php
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

public function members(): BelongsToMany
{
    return $this->belongsToMany(User::class)
        ->withPivot('role_id')
        ->withTimestamps();
}
```

User model:

```php
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

public function projects(): BelongsToMany
{
    return $this->belongsToMany(Project::class)
        ->withPivot('role_id')
        ->withTimestamps();
}
```

The `withPivot()` method exposes additional pivot columns.

The `withTimestamps()` method tells Laravel to maintain pivot timestamps.

Only use these methods when the actual pivot table contains the corresponding columns.

### 2.5 Relationship Naming

Follow Laravel naming conventions:

| Relationship | Method naming |
|---|---|
| Belongs To | Singular |
| Has One | Singular |
| Has Many | Plural |
| Belongs To Many | Plural |

Examples:

- `owner()`
- `role()`
- `users()`
- `members()`
- `projects()`
- `permissions()`

Always provide the correct return type:

- `BelongsTo`
- `HasOne`
- `HasMany`
- `BelongsToMany`

---

## 3. Pivot Table Requirements

Before implementing a many-to-many relationship, verify the following:

- The pivot table exists.
- The table name follows Laravel's alphabetical singular model naming convention unless explicitly overridden.
- Both foreign keys exist.
- Both foreign keys reference the correct tables.
- Required additional pivot columns exist.
- Pivot timestamps exist if `withTimestamps()` is used.
- Unique constraints match the intended business rules.
- Foreign-key deletion behavior is intentional.

### TestOps project_user structure

The current `project_user` table contains:

| Column | Purpose |
|---|---|
| project_id | References projects.id |
| user_id | References users.id |
| role_id | References roles.id |
| created_at | Membership creation timestamp |
| updated_at | Membership update timestamp |

Unique constraint:

`UNIQUE(project_id, user_id)`

This ensures:

- A user can belong to multiple projects.
- A user can have only one membership per project.
- A user can have exactly one project role per project.

### Important schema discrepancy

The current `project_user.role_id` migration uses:

`cascadeOnDelete()`

Earlier design decisions proposed:

`restrictOnDelete()`

These behaviors are different.

- `cascadeOnDelete()` deletes associated project memberships when a role is deleted.
- `restrictOnDelete()` prevents deletion of a role while memberships reference it.

AI assistants must identify this discrepancy during schema reviews.

Never silently modify an already-applied migration.

If a change is approved, create a new migration to modify the foreign-key constraint safely.

---

## 4. Model Factory Requirements

### 4.1 HasFactory Trait

Calling:

```php
Project::factory()->create();
```

requires the model to use Laravel's `HasFactory` trait.

Example:

```php
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Project extends Model
{
    use HasFactory;
}
```

A factory class existing inside `database/factories` does not automatically make `Model::factory()` available.

Always verify both:

1. The factory class exists.
2. The model uses `HasFactory`.

### 4.2 Related Model Factories

When a model requires another model's foreign key, prefer using its factory.

Example:

```php
'owner_id' => User::factory(),
```

This automatically creates a valid User record when creating a Project through its factory.

### 4.3 Factory Creation Policy

Do not automatically generate factories for every model.

Create factories when they provide actual value for:

- Database-backed testing.
- Development data.
- Relationship testing.
- Reproducible test scenarios.

Follow `.ai/rules/factories.md`.

---

## 5. Mass Assignment Exception

### Exception

A model operation may produce:

```text
Illuminate\Database\Eloquent\MassAssignmentException

Add [name] to fillable property to allow mass assignment
on [App\Models\Role].
```

### Cause

This commonly occurs when using:

```php
Role::query()->create([
    'name' => 'Tester',
    'code' => 'TESTER',
    'scope' => 'project',
]);
```

Laravel protects model attributes against unauthorized mass assignment.

If the supplied attributes are not configured as mass assignable, Laravel rejects the operation.

### TestOps convention

TestOps uses Laravel 13 model attributes, including `#[Fillable]`.

Follow the existing convention where appropriate.

Example:

```php
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['name', 'code', 'description', 'scope'])]
class Role extends Model
{
    //
}
```

Only include attributes that the application is intentionally allowed to mass assign.

Do not include:

- Primary keys
- Creation timestamps
- Update timestamps

unless there is a specific, reviewed requirement.

### Avoid these workarounds

Do not resolve mass-assignment exceptions by casually:

- Disabling mass-assignment protection globally.
- Adding every database column to the fillable configuration.
- Calling `Model::unguard()` globally.
- Using force-assignment methods simply to bypass the exception.

If mass assignment is inappropriate for a specific operation, assign attributes deliberately and save the model.

Do not weaken the model's general protection unnecessarily.

---

## 6. Relationship Testing Conventions

Database-backed relationship tests must:

- Extend `Tests\TestCase`.
- Use `RefreshDatabase`.
- Use factories where available and useful.
- Create valid related records for required foreign keys.
- Assert actual Eloquent relationships.
- Verify important pivot attributes.
- Avoid mocking the database.
- Use descriptive test names.

### Example: Belongs To

```php
public function test_project_belongs_to_its_owner(): void
{
    $project = Project::factory()->create();

    $this->assertInstanceOf(User::class, $project->owner);

    $this->assertSame(
        $project->owner_id,
        $project->owner->id
    );
}
```

### Example: Belongs To Many

```php
$project = Project::factory()->create();

$member = User::factory()->create();

$role = Role::query()->create([
    'name' => 'Tester',
    'code' => 'TESTER',
    'scope' => 'project',
]);

$project->members()->attach($member->id, [
    'role_id' => $role->id,
]);

$this->assertTrue(
    $project->members->contains($member)
);

$this->assertSame(
    $role->id,
    $project->members->first()->pivot->role_id
);
```

This example requires:

- A valid Project factory.
- A valid User factory.
- A Role model configured for the supplied mass-assignment attributes.
- An existing `project_user` pivot table.
- A valid project-level role.

### Test naming

Use behavior-oriented names:

```php
test_project_belongs_to_its_owner()

test_project_has_members_with_assigned_project_roles()

test_user_can_retrieve_projects_they_belong_to()
```

Avoid vague names such as:

```php
test_example()

test_relationship()

test_it_works()
```

---

## 7. Current TestOps Relationship Map

The following relationships have been implemented.

| Model | Method | Related Model | Relationship |
|---|---|---|---|
| Project | owner() | User | Belongs To |
| Project | members() | User | Belongs To Many |
| User | projects() | Project | Belongs To Many |
| User | role() | Role | Belongs To |
| Role | users() | User | Has Many |
| Role | permissions() | Permission | Belongs To Many |
| Permission | roles() | Role | Belongs To Many |

### Important distinction: System Role vs Project Role

TestOps uses two separate role assignments.

**System-level role**

Stored in:

`users.role_id`

This determines the user's global role.

Relationship:

`User::role()`

**Project-level role**

Stored in:

`project_user.role_id`

This determines the user's role within an individual project.

Relationship:

`Project::members()`

The same user may have different project roles across different projects.

Never confuse these two role assignments.

### Project Ownership vs Membership

These are separate concepts.

Project ownership is stored in:

`projects.owner_id`

Project membership is stored in:

`project_user`

Removing a user's project membership must not automatically rewrite project ownership history.

---

## 8. Exception Diagnostic Reference

| Exception or symptom | Likely cause | First inspection |
|---|---|---|
| Call to undefined method Model::factory() | Missing HasFactory trait or incomplete factory setup | Model and factory |
| MassAssignmentException | Attributes are not mass assignable | Model Fillable configuration |
| Foreign-key constraint violation | Missing or invalid related record | Migration and test setup |
| Duplicate key violation | Duplicate pivot membership | Pivot unique constraint |
| Unknown column on pivot | Missing or incorrectly named pivot field | Pivot migration |
| Missing pivot timestamp column | withTimestamps() used without timestamp columns | Pivot migration |
| Relationship returns no records | Incorrect foreign key, pivot configuration, or missing attachment | Models and migrations |
| Role deletion removes memberships | Cascading foreign-key behavior | Pivot migration |
| Incorrect pivot role retrieved | Wrong role_id assigned or incorrect pivot mapping | Pivot migration and test |

These are diagnostic starting points, not guaranteed explanations.

Always inspect the stack trace and relevant source files before changing code.

---

## 9. AI Assistant Preflight Checklist

Before generating or modifying Eloquent code, verify:

### Project Rules

- [ ] Read `.ai/rules/index.md`.
- [ ] Read applicable model rules.
- [ ] Read applicable migration rules.
- [ ] Read applicable factory rules.
- [ ] Read applicable test rules.

### Relationships

- [ ] Inspected the actual model files.
- [ ] Inspected the actual migrations.
- [ ] Identified the correct relationship type.
- [ ] Verified foreign-key names.
- [ ] Verified pivot table structure.
- [ ] Verified additional pivot columns.
- [ ] Verified pivot timestamp columns.
- [ ] Verified uniqueness constraints.
- [ ] Reviewed foreign-key deletion behavior.

### Factories

- [ ] Verified HasFactory is present when Model::factory() is used.
- [ ] Verified the corresponding factory exists.
- [ ] Verified related foreign keys can be populated.
- [ ] Avoided unnecessary factories.

### Mass Assignment

- [ ] Checked the model's Fillable configuration.
- [ ] Verified all attributes passed to create() are permitted.
- [ ] Avoided globally disabling mass-assignment protection.
- [ ] Avoided unnecessary force-assignment methods.

### Testing

- [ ] Used RefreshDatabase.
- [ ] Used actual database records.
- [ ] Used factories where appropriate.
- [ ] Tested relationship retrieval.
- [ ] Tested relevant pivot attributes.
- [ ] Used descriptive test names.
- [ ] Ran the narrowest relevant test suite.
- [ ] Ran Laravel Pint on changed PHP files.

If any requirement is unknown, inspect the relevant file or ask the user before proceeding.

---

## 10. Required AI Assistant Behavior

When implementing Eloquent relationships:

1. Explain the relationship type.
2. Identify the required foreign key or pivot table.
3. Confirm that the database schema supports it.
4. Explain the relationship method.
5. Implement incrementally when guiding the user.
6. Verify the implementation with focused tests.

When an exception occurs:

1. Identify the exact exception.
2. Explain its likely cause.
3. Inspect the relevant source file.
4. Check applicable project rules.
5. Recommend the smallest correct fix.
6. Explain any security or data-integrity implications.
7. Make one change at a time during interactive guidance.
8. Re-run the focused test.

Never silently bypass exceptions.

Never modify applied migrations without an approved migration strategy.

Never invent database columns, foreign keys, relationships, or factory behavior.

The priority is correct Eloquent behavior, database integrity, maintainability, and understandable implementation.
