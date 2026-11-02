---
paths:
  - 'app/**/*.php'
---

# App

## Workflow discipline: rules index, docs search, narrowest tests
Before creating or editing files, read .ai/rules/index.md when this directory exists. Search Laravel ecosystem documentation (Boost search-docs) before relying on ecosystem APIs, behavior, or version-specific syntax. Run the narrowest test set covering each change (php artisan test --compact with a file path or --filter) and rerun after every change. Create documentation files only when explicitly requested. Unresolved revision items are not approved features or requirements: the Revision 2 SQL command item stays unclarified until the user states otherwise, so design nothing around it.
