---
title: OAuth 2.0 Service-Client Authorization
scope: OAuth foundation (implemented) and Future Release items
status: Approved baseline — implemented and tested
---

# TestOps OAuth 2.0 Service-Client Authorization

This document records the approved OAuth 2.0 / Laravel Passport policy and the
service-client authorization foundation implemented in TestOps. Claims here
are backed by tests listed in Section 10. The basis is Laravel Passport
13.10.0 with published, unmodified Passport migrations.

## 1. Approved OAuth Grant Policy

| Grant / Feature | Policy |
| --- | --- |
| Client Credentials | Enabled |
| Authorization Code | Enabled |
| PKCE | Mandatory, `S256` only (`EnforceAuthorizationCodePkce` on Passport routes) |
| Refresh Token | Enabled for Authorization Code |
| Device Authorization | Disabled (Future Release item) |
| Password Grant | Disabled |
| Implicit Grant | Disabled |
| Personal Access Tokens | Not required for this implementation |

## 2. Token Expiration Policy

| Token | Lifetime |
| --- | --- |
| Access tokens (authorization code) | 15 minutes (`PT15M`) |
| Client-credentials access tokens | 15 minutes (`PT15M`) |
| Authorization-code refresh tokens | 7 days (`P7D`) |

Machine tokens cannot be refreshed from client-credentials tokens (RFC 6749:
the grant emits no refresh token).

## 3. Registered OAuth Scopes

Scope definitions are centralized in
`app/Services/Authorization/OAuthScopes.php` and registered through
`Passport::tokensCan()` in `AppServiceProvider`:

| Scope | Description |
| --- | --- |
| `system-settings:read` | Read permitted system settings. |
| `system-settings:write` | Update permitted system settings. |

Machine clients must never receive `system-settings:write` in this release.
The machine-client scope allowlist lives in one place
(`OAuthScopes::machineClientScopeAllowlist()`), so the policy is enforced
exactly once:

- Token issuance cannot grant the write scope to client-credentials tokens
  (`ScopeRepository::finalizeScopes()`).
- Globally registered scopes are **not** a per-client allowlist: Passport's
  owned `oauth_clients` migration has no scopes column, and scope
  enforcement for machines happens at issuance and at request time as
  documented here.

Unregistered scopes cannot appear in issued tokens. Passport rejects the
request with `invalid_scope` (HTTP 400).

## 4. OAuth Scopes vs. Application Permissions

These are three separate authorization layers:

1. **OAuth scopes** (`system-settings:*`) — token-level capability
   restrictions enforced by Passport. They never replace TestOps
   application authorization and never map to RBAC roles.
2. **Service-client permissions** (`system-settings.*`) — application-level
   permissions stored in `service_client_permissions` and provisioned
   exclusively through trusted application-side code. Machine clients can
   never grant permissions to themselves, and no API exists for clients to
   modify their own permissions.
3. **Human RBAC** — system/project roles plus `Permission` codes (existing
   `PermissionChecker`, `roles`, `permissions`, `permission_role` tables).

## 5. Service-Client Permission Storage

Table `service_client_permissions` (application-owned migration; Passport
migrations are untouched):

| Column | Type | Notes |
| --- | --- | --- |
| `id` | big integer | Primary key |
| `oauth_client_id` | uuid | FK → `oauth_clients.id`, `ON DELETE CASCADE` |
| `permission` | string | Application permission identifier |
| `created_at`, `updated_at` | timestamps | Standard timestamps |

Constraints: unique composite index on (`oauth_client_id`, `permission`);
foreign key with cascade delete so deleting an OAuth client removes its
service permissions.

Initial permission: `system-settings.read` (constant
`ServiceClientAuthorizer::SYSTEM_SETTINGS_READ`). **No write permission is
assigned to machine clients in this release.**

## 6. Provisioning Procedure

Assigning a service permission is an application-side operation performed by
trusted code (console command, seeder, or internal administrative tooling),
never through a client-facing API:

```php
App\Models\ServiceClientPermission::query()->create([
    'oauth_client_id' => $client->getKey(),
    'permission' => App\Services\Authorization\ServiceClientAuthorizer::SYSTEM_SETTINGS_READ,
]);
```

- The unique index rejects duplicate assignments.
- The foreign key rejects unknown clients.
- Deleting the OAuth client cascades the permission records.
- Revoking/deleting a client does not automatically revoke its issued
  access/refresh tokens; see Section 8.

## 7. Machine-Client Authorization Flow

Endpoints (response envelope `{ "data": {...}, "meta": [], "links": [] }`):

- `GET /api/v1/system-settings/email-link-expiration`
  (route `system-settings.email-link-expiration.show`)
- `PUT /api/v1/system-settings/email-link-expiration`
  (route `system-settings.email-link-expiration.update`)

Middleware `EnsureEmailLinkExpirationAccess` (parameter `read`/`write`)
applies the following order:

1. **Token authentication** — Resource-Server validation of the bearer
   token; invalid, expired, or revoked tokens are rejected with HTTP 401.
2. **Principal resolution** — if the api guard resolves a User, the request
   takes the human path; a client-credentials token never resolves to a
   User and always takes the machine path.
3. **Machine path** —
   - fresh active (non-revoked) client lookup; revoked or unknown clients are rejected with HTTP 401.
   - write operations are denied for machine clients with HTTP 403 (disabled this release).
   - read operations require BOTH the `system-settings:read` token scope AND the
     `system-settings.read` service permission; failures are HTTP 403.
4. **Human path** — no OAuth scope is required; the existing RBAC rules
   apply unchanged:
   - read: Super Admin, or a system role holding `settings.email_link_expiration.view` (identical to the web behavior).
   - write: Super Admin only.

HTTP status semantics: 401 unauthenticated, 403 unauthorized, 422 validation
failure.

`ServiceClientAuthorizer` is the reusable decision service for the machine
layer; future service permissions add their own identifiers and can reuse the
same checks without new authorization infrastructure.

## 8. Revocation and Lifecycle Safeguards

### 8.1 Implemented behavior (current release)

- **Access tokens**: revocation (`oauth_access_tokens.revoked`) is checked
  by the resource server on every authenticated request; revoked tokens fail
  with HTTP 401. `Laravel\Passport\AccessToken::revoke()` /
  `Token::revoke()` are the supported actions.
- **Refresh tokens**: refresh-token rotation is in effect because
  `Passport::$revokeRefreshTokenAfterUse` defaults to `true` in Laravel
  Passport 13.10; the application relies on that framework default and does
  not explicitly configure the property. Refresh tokens are also validated
  on use.
- **Client revocation**: service middleware performs a fresh non-memoized
  client lookup, so `ServiceClientAuthorizer` rejects a revoked OAuth client
  during machine authorization even though Passport's `ClientRepository`
  memoizes client instances per process.
- **Web logout**: ordinary web logout (`AuthenticatedSessionController`)
  invalidates only the current web session; it never touches Passport API
  tokens or the user's other active sessions.
- **Authorized lifecycle cleanup**: follow the Passport maintenance
  approaches when operational scheduling is approved
  (`php artisan passport:purge [--revoked] [--expired]`); no speculative
  lifecycle automation has been added in this release.
- **Service permission cleanup**: cascade delete via the foreign key when
  the OAuth client is deleted.

### 8.2 Pending requirements — user account lifecycle (not implemented)

The following lifecycle revocation requirements are approved for future
account-management integration. None of them is implemented in this release:
there are currently no account-management handlers to integrate these
operations with, and no standalone lifecycle service, observer, or speculative
automation exists.

- User soft deletion must revoke all associated OAuth access tokens
  (Passport relationship: `HasApiTokens::tokens()`) and must also revoke the
  associated refresh tokens (`Laravel\Passport\RefreshToken::revoke()`).
- Future account deactivation must revoke the user's access and refresh
  tokens when that lifecycle transition is formally introduced (see the
  account status policy in Section 8.4).
- Token revocation and the corresponding account lifecycle operation must
  execute transactionally.
- Ordinary web logout must continue to invalidate only the current web
  session; it must never revoke the user's unrelated API tokens (this
  remains implemented behavior and must be preserved).

Passport supplies the primitives (the user-bound token relationship and
token-level revocation methods, including `AuthorizedAccessTokenController`);
TestOps currently wires none of them into an account lifecycle. Until an
approved account-flow work item adds this integration, do not represent
lifecycle revocation as done.

### 8.3 Pending requirements — OAuth client lifecycle (not implemented)

The following requirements apply to future OAuth-client-management
integration. No client-management handler exists in this release; besides
the revoked-client denial implemented in Section 8.1, no client lifecycle
revocation integration is in place:

- Client revocation must revoke all associated access tokens and refresh
  tokens (Passport relationship: `Client::tokens()`).
- Client deletion must revoke the associated credentials before deleting
  the client.
- Permission records in `service_client_permissions` are cleaned up through
  the existing foreign-key cascade (implemented behavior; see Section 5).
- Client lifecycle operations and credential revocation must execute
  transactionally.
- Do not assume deleting an OAuth client automatically revokes its access
  or refresh tokens; that revocation is explicitly pending integration.

### 8.4 Account status policy

- Existing account statuses remain `invited` and `active`
  (`users.account_status`; default `invited`, and login accepts only
  `active`).
- Do not introduce a `deactivated` status or define new account transitions
  in this release.
- Future deactivation behavior remains subject to a separately approved
  account lifecycle specification.

## 9. Future Releases (documented items only)

- **Device Authorization Grant** — scaffold/documentation item only;
  disabled by `Passport::$deviceCodeGrantEnabled = false` and no device
  routes are registered.
- **General System Settings API** — no placeholder controllers or routes
  exist; add them only with an approved specification.
- **Machine-client write access** — remains disabled in this release. The write scope stays registered but is not required anywhere, and no service permission grants machine writes.
- **OAuth token lifecycle integration** — pending requirements documented in Sections 8.2 and 8.3; no account-management or OAuth-client-management handlers exist in this release.

## 10. Test Pointer

- `tests/Feature/Authentication/PassportConfigurationTest.php` — grants disabled, lifetimes, scope registry.
- `tests/Feature/Authentication/OAuthScopeIssuanceTest.php` — issuance scope policy.
- `tests/Feature/Authentication/AuthorizationCodePkceMiddlewareTest.php` — PKCE enforcement.
- `tests/Feature/Models/ServiceClientPermissionTest.php` — storage constraints and cascade.
- `tests/Feature/Authorization/ServiceClientAuthorizationTest.php` — machine
  + human API authorization, revoked clients/tokens, human RBAC retention.
- `tests/Feature/Authorization/EmailLinkExpirationAuthorizationTest.php` —
  pre-existing web (session) authorization behavior, unchanged.
