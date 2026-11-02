---
description: Apply an already-reviewed Laravel migration and verify its
  status
---

Apply only the migration that has already been reviewed and explicitly
approved by the developer.

1.  Confirm the target environment/database and that the developer has
    approved applying the migration.
2.  Do not apply destructive reset/rollback commands.
3.  Show and run or ask the developer to run:

``` powershell
php artisan migrate
```

4.  Then show and run or ask the developer to run:

``` powershell
php artisan migrate:status
```

5.  Verify the intended migration is listed as `Ran` and report its
    batch.
6.  If the command fails, stop and diagnose the actual error. Do not
    suggest `migrate:fresh` as a routine fix.
7.  Do not start the next migration automatically.
