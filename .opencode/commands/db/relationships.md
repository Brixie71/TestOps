---
description: Design, implement, or review Eloquent relationships using the actual TestOps schema
---

# Eloquent Relationship Workflow

Follow the project rules and `.ai/guides/ELOQUENT-RELATIONSHIPS-CHEATSHEET.md`.

## Required workflow

1. Read `.ai/rules/index.md`.
2. Read applicable rules, especially `.ai/rules/models.md` and `.ai/rules/app.md`.
3. Read `.ai/guides/ELOQUENT-RELATIONSHIPS-CHEATSHEET.md`.
4. Inspect relevant model files and migrations. Check migration status when relevant.
5. Identify actual foreign keys, pivot tables, pivot attributes, and deletion behavior.
6. Determine cardinality and select the appropriate Eloquent method: `belongsTo`, `hasOne`, `hasMany`, `belongsToMany`, or `hasManyThrough`.
7. Explain the relationship direction, cardinality, schema evidence, and purpose.
8. Show the exact model file, imports, and relationship method.
9. Work one logical step at a time when the developer is learning or requests guided implementation. Do not implement every relationship at once.
10. Never invent models, tables, keys, pivot columns, or cardinality.
11. Do not modify migrations to accommodate an assumed relationship. Explain schema gaps and seek approval before proposing schema changes.
12. Preserve explicit return types and project conventions.
13. Review the result and run the narrowest relevant tests.

## Required response order

1. Relationship: source model → related model.
2. Type: Eloquent method and cardinality.
3. Schema evidence: exact foreign key or pivot table.
4. Purpose: what the relationship enables.
5. Implementation: imports and method.
6. Verification: how to inspect or test it.

## Safety and scope

- Treat existing migrations as the source of truth for database structure.
- A proposed relationship map does not prove that its foreign keys exist.
- Defect ↔ TestResult cardinality is not finalized. Do not implement until the developer confirms it.
- Do not add Project → TestResults through TestRuns unless actual keys and use case justify it.
- Do not use `hasManyThrough` merely because an intermediate model exists.
- Never run destructive commands such as `migrate:fresh` during relationship work.
