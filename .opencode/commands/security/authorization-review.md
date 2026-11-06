---
description: Review authorization logic for a feature - policies, middleware, gates, permissions, role boundaries, escalation paths (read-only)
---

Review the authorization logic of: $ARGUMENTS

Arguments: a feature, controller, model, route group, or omitted meaning
"current uncommitted changes". Read-only.

1. Map the authorization-relevant code with targeted searches:

   ```powershell
   Get-ChildItem ".\app\Policies", ".\app\Http\Middleware", ".\app\Models" -Recurse -File -Filter "*.php" |
       Select-String -Pattern "authorize|can|cannot|Gate::|permission" -List
   Get-Content ".\routes\api.php"
   ```

   Read the policies, middleware (`PermissionMiddleware`,
   `EnforceAuthorizationCodePkce`), and permission service implementing the
   feature, plus the seeders defining roles/permissions.
2. Distinguish the three layers - this confusion is the most common TestOps
   authorization defect:
   - OAuth token scopes (machine-client API capability, e.g.
     `system-settings:read`).
   - Application-level service-client permissions (e.g.
     `system-settings.read`).
   - Human user roles and permissions (`SUPER_ADMIN`, `SYSTEM_ADMIN`, project
     roles).
   Machine clients are service principals, not human users; they cannot inherit
   human admin roles, and machine-client write access remains disabled.
3. For every reviewed action, confirm the chain: route middleware, policy
   coverage for the model action, gate/permission naming consistency, and
   correct error responses (401 vs 403).
4. Hunt for the classic elevation paths:
   - Client-supplied values treated as authority (ids of other users/projects,
     role names, permission arrays in requests).
   - Missing policy checks on new routes and public properties.
   - Horizontal escalation (accessing sibling resources of the same role).
   - Permission checks that exist but wrap nothing (no enforcement point).
5. Check existing authorization tests cover both positive and negative cases
   for the reviewed actions (see `tests/Feature/Authorization/` for patterns).
6. Report findings by severity with exact file/line evidence: missing checks
   above naming/wiring issues; flag, never silently expand, any role or
   permission set. Suggested minimal corrections and the tests to add.
