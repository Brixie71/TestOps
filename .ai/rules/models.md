---
paths:
  - 'app/Models/*.php'
---

# Models

## Approved TestOps MVP domain model scope

The approved TestOps MVP includes these domain models:

- Project
- TestSuite
- TestCase
- TestRun
- TestResult
- Defect
- User

Defect Tracking is part of the MVP, not a future feature.

### Approved RBAC foundation models

The following models are explicitly approved as an RBAC foundation and are exceptions to the MVP domain model list above:

- Role
- Permission

RBAC supports both system-level and project-level role assignments.

- A user has one system-level role through `users.role_id`.
- A user may belong to multiple projects.
- A user has exactly one project-level role per project through `project_user.role_id`.
- Role definitions and permissions are centrally managed by the Super Admin.
- Project Admins may assign authorized roles but cannot create or modify role definitions.
- System-level role assignments and project-level role assignments must remain semantically distinct.

Do not introduce additional authorization models, tables, relationships, or authorization layers unless explicitly approved in a separate review.

### Out-of-scope models

Team, multi-tenancy, Report, and any other unapproved models remain out of scope. Do not scaffold future-proofing tables, columns, or relationships for them.

Implement only the approved RBAC foundation and authorization behavior genuinely required by the MVP.
