---
paths:
  - 'database/migrations/*.php'
---

# Migrations

## PostgreSQL target with MVP-simple schema design
The target database is PostgreSQL (pgsql); SQLite stays a local/test convenience for now. Migrations must use portable / Postgres-compatible syntax. Follow conventional Laravel column patterns: plural snake_case tables, timestamps, casts, pivot tables named in alphabetical order where needed. Do not mandate UUIDs, JSONB, full-text/tsvector indexes, or composite indexes; add any of these only when a concrete requirement exists and note the requirement in the migration review. Never edit a deployed migration; add a new one via php artisan make:migration.
