---
paths:
  - 'database/factories/*.php'
---

# Factories

## Factories and seeders when useful, never automatic
Start new models with php artisan make:model -m. Add --factory / --seed only when useful for development or testing, not automatically for every model. Whatever factories exist must define custom states (e.g. passed(), failed(), blocked() on TestResultFactory) and tests must prefer those states over manually setting attributes.
