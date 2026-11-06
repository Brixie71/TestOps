---
paths:
  - 'app/Services/Authorization/**'
  - 'app/Http/Middleware/**'
  - 'app/Http/Resources/**'
  - 'app/Models/User.php'
  - 'app/Models/ServiceClientPermission.php'
  - 'config/passport.php'
  - 'database/migrations/*service_client*'
  - 'database/migrations/*oauth*'
  - 'routes/api.php'
  - 'docs/oauth-service-authorization.md'
---

# OAuth, Passport, and Service-Client Authorization

Authoritative spec: `docs/oauth-service-authorization.md`. Approved baseline
(`Passport` 13.10.0): client-credentials and authorization-code grants only,
PKCE mandatory with S256, refresh tokens for authorization code, device
authorization disabled, password/implicit disabled, 15-minute access and
client-credentials tokens, 7-day refresh tokens.

- OAuth scopes are token-level capability restrictions; they never replace
  TestOps application authorization or map to human roles.
- Scope definitions are centralized in
  `App\Services\Authorization\OAuthScopes`; register them through
  `Passport::tokensCan(OAuthScopes::definitions())` only.
- Client-credentials (machine) tokens must never receive
  `system-settings:write` in this release; the machine scope allowlist
  extends `Laravel\Passport\Bridge\ScopeRepository` and is bound to
  `Passport\Bridge\ScopeRepository` in AppServiceProvider. Never modify
  vendor files.
- Machine clients are service principals: they are never treated as a User
  and can never hold SUPER_ADMIN or SYSTEM_ADMIN rights. Machine access to
  per-endpoint operations requires BOTH the OAuth scope and the provisioned
  application permission from `service_client_permissions`.
- Service permissions are provisioned only through trusted application-side
  code; clients can never grant permissions to themselves. The initial
  permission is `system-settings.read`; no machine write permission exists
  in this release.
- Human authorization policy is unchanged: read email-link expiration for
  SUPER_ADMIN or system roles holding
  `settings.email_link_expiration.view`; write for SUPER_ADMIN only. Web
  session authentication stays untouched.
- Keep Passport migrations unmodified. Extend authorization through the
  container (e.g. scope repository binding) or application-owned migrations
  (e.g. `service_client_permissions` with cascade FK to `oauth_clients.id`).

## OAuth Token Lifecycle Requirements

- User soft deletion must revoke all associated OAuth access tokens and
  their associated refresh tokens.
- Future account deactivation must revoke the user's access and refresh
  tokens when that lifecycle transition is formally approved and implemented.
- User lifecycle changes and associated token revocation must execute
  transactionally.
- Ordinary web logout must invalidate only the current web session. It must
  not revoke unrelated OAuth/API tokens.
- OAuth client revocation must revoke all associated access tokens and
  refresh tokens.
- OAuth client deletion must revoke associated credentials before deleting
  the client.
- Client lifecycle changes and associated credential revocation must execute
  transactionally.
- Do not assume deleting an OAuth client automatically revokes its access
  tokens or refresh tokens.
- `service_client_permissions` records must be cleaned up through the
  existing foreign-key cascade when their OAuth client is deleted.
- Use Passport-supported token relationships and revocation methods. Never
  modify Passport vendor code or migrations to implement lifecycle behavior.

## Lifecycle Implementation Status

- Passport provides user/client token relationships and token-level
  revocation methods.
- `ServiceClientAuthorizer` rejects revoked OAuth clients during machine
  authorization.
- Full user account lifecycle and OAuth client lifecycle revocation
  integration is NOT YET IMPLEMENTED.
- No account-management or OAuth-client-management handlers currently
  exist to integrate these operations with.
- Do not represent standalone lifecycle services, model observers, or
  automatic revocation hooks as implemented unless they are actually
  implemented, tested, and integrated with the relevant lifecycle flows.
- Do not add speculative lifecycle automation without an approved
  implementation requirement.

## Account Status Policy

- Existing account statuses remain `invited` and `active`.
- Do not introduce a `deactivated` status or define new account transitions
  without an approved account lifecycle specification.
- Future deactivation behavior remains subject to a separately approved
  account lifecycle specification.

## Future Release

- Device Authorization Grant and the general System Settings API are Future
  Release documentation items only.
- Machine-client write access stays disabled in this release.
