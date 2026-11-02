# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

| Applies to | Rule file |
| --- | --- |
| app/**/*.php | .ai/rules/app.md |
| composer.json | .ai/rules/composer-json.md |
| app/Http/Controllers/*.php | .ai/rules/controllers.md |
| database/factories/*.php | .ai/rules/factories.md |
| database/migrations/*.php | .ai/rules/migrations.md |
| app/Models/*.php | .ai/rules/models.md |
| routes/*.php | .ai/rules/routes.md |
| tests/*.php | .ai/rules/tests.md |

## Reference Guides

Consult these guides when working on the corresponding areas of the project.

| Topic | Reference guide |
| --- | --- |
| Database design and conventions | .ai/guides/DATABASE-CHEATSHEET.md |
| Database migration workflow | .ai/guides/DATABASE-WORKFLOW.md |
| Eloquent relationships and model exceptions | .ai/guides/ELOQUENT-RELATIONSHIPS-AND-MODEL-EXCEPTIONS.md |
| Eloquent relationship layer finalization | .ai/guides/ELOQUENT-RELATIONSHIP-LAYER-FINALIZATION.md |

### Reference Guide Instructions

- Read the relevant reference guide before implementing related features.
- Follow the established database and Eloquent conventions.
- Inspect actual models and migrations before assuming relationships.
- Check the exception diagnostic reference when Laravel errors occur.
- Reference guides supplement the applicable rule files; they do not replace them.
- Before declaring the Eloquent relationship layer complete, follow `.ai/guides/ELOQUENT-RELATIONSHIP-LAYER-FINALIZATION.md` and verify the final formatting and test results.
