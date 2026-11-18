# TestOps Eloquent Relationships Cheat Sheet

**Purpose:** Reference for developers and AI coding assistants working with Laravel Eloquent relationships.

## 1. Relationship types

### `belongsTo()` — Many-to-One
The current model contains a foreign key referencing another model.

Example: `projects.owner_id` references `users.id`.

```php
public function owner(): BelongsTo
{
    return $this->belongsTo(User::class, 'owner_id');
}
```

Use cases: Project → Owner; TestCase → TestSuite; TestResult → TestRun; TestResult → TestCase.

### `hasOne()` — One-to-One
The related model contains a foreign key referencing the current model.

```php
public function profile(): HasOne
{
    return $this->hasOne(Profile::class);
}
```

Use cases: User → Profile; User → Settings.

### `hasMany()` — One-to-Many
The related table contains a foreign key referencing the current model.

```php
public function testSuites(): HasMany
{
    return $this->hasMany(TestSuite::class);
}
```

Use cases: Project → TestSuites; TestSuite → TestCases; TestRun → TestResults. Project → Defects only if `defects` has `project_id`.

### `belongsToMany()` — Many-to-Many
Both models can have multiple associated records. A pivot table stores the associations.

```php
public function members(): BelongsToMany
{
    return $this->belongsToMany(User::class)
        ->withPivot('role_id')
        ->withTimestamps();
}
```

Use cases: Project ↔ Users through `project_user`; Role ↔ Permissions through `permission_role`.

### `hasManyThrough()` — Through an intermediate model
Access records through an intermediate model.

```php
public function testResults(): HasManyThrough
{
    return $this->hasManyThrough(TestResult::class, TestRun::class);
}
```

Use only when the actual foreign-key structure and intended semantics support it. Verify key mapping before implementation.

## 2. Typical counterparts

| Relationship | Typical counterpart |
|---|---|
| `hasOne()` | `belongsTo()` |
| `hasMany()` | `belongsTo()` |
| `belongsTo()` | `hasOne()` or `hasMany()` |
| `belongsToMany()` | `belongsToMany()` |
| `hasManyThrough()` | Depends on model structure |

## 3. Foreign keys and pivot tables

Laravel convention: a `Project` model uses the `projects` table; a child table commonly references it with `project_id`.

```php
$table->foreignId('project_id')->constrained()->cascadeOnDelete();
```

For a custom foreign key:

```php
$table->foreignId('owner_id')->constrained('users');
```

Pivot table conventions:
- Use singular model names, alphabetically ordered.
- Include both foreign keys.
- Add extra pivot columns only when required.

Example `permission_role`:

```php
$table->foreignId('permission_id')->constrained()->cascadeOnDelete();
$table->foreignId('role_id')->constrained()->cascadeOnDelete();
$table->unique(['permission_id', 'role_id']);
$table->timestamps();
```

For pivot attributes in Eloquent, use `withPivot()`; use `withTimestamps()` when the pivot has timestamps.

## 4. Relationship return type imports

```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
```

Declare an explicit return type on every relationship method.

## 5. TestOps relationship map

This is a proposed map based on the approved domain and discussed schema. Confirm actual foreign keys and cardinality before implementation.

| Model | Method | Related model | Type | Required schema |
|---|---|---|---|---|
| Project | owner | User | belongsTo | `projects.owner_id` |
| Project | members | User | belongsToMany | `project_user` |
| User | projects | Project | belongsToMany | `project_user` |
| TestSuite | project | Project | belongsTo | `test_suites.project_id` |
| TestSuite | testCases | TestCase | hasMany | `test_cases.test_suite_id` |
| TestCase | testSuite | TestSuite | belongsTo | `test_cases.test_suite_id` |
| TestRun | project | Project | belongsTo | `test_runs.project_id` |
| TestRun | testResults | TestResult | hasMany | `test_results.test_run_id` |
| TestResult | testRun | TestRun | belongsTo | `test_results.test_run_id` |
| TestResult | testCase | TestCase | belongsTo | `test_results.test_case_id` |
| Defect | testResult | TestResult | belongsTo | Requires `defects.test_result_id`; cardinality unconfirmed |
| Role | permissions | Permission | belongsToMany | `permission_role` |
| Permission | roles | Role | belongsToMany | `permission_role` |

**Cautions:** Defect ↔ TestResult cardinality remains undecided. Do not implement it until confirmed. Project → TestResults through TestRuns may be possible, but verify keys and use case first.

## 6. AI coding assistant rules

1. Read `.ai/rules/index.md`.
2. Read applicable model and application rules.
3. Inspect model files and existing migrations.
4. Identify actual foreign keys, pivot tables, pivot attributes, and deletion behavior.
5. Determine cardinality before choosing an Eloquent method.
6. Explain the relationship and purpose before proposing code.
7. Work one logical step at a time when the developer is learning or requests guided work.
8. Use explicit return types.
9. Specify custom keys and pivot tables when conventions do not match the schema.
10. Never invent tables, models, keys, pivot columns, or relationships.
11. Do not change migrations to make an assumed relationship work.
12. Review the implementation and run focused tests.

## 7. Quick decision guide

| Question | Method |
|---|---|
| Does this model reference one parent through a foreign key? | `belongsTo()` |
| Does this model have one related record? | `hasOne()` |
| Does this model have multiple related records? | `hasMany()` |
| Can both models have multiple associated records? | `belongsToMany()` |
| Are records reached through an intermediate model? | `hasManyThrough()` |

**Core rule:** Let the actual database schema determine the relationship. Eloquent methods describe the database structure; they do not replace it.
