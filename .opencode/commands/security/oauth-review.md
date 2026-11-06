---
description: Review TestOps OAuth 2.0 / Passport implementation against the approved security baseline (read-only)
---

Review the TestOps OAuth 2.0 and Laravel Passport implementation. Read-only;
never print secrets or encryption-key contents.

Activate the `passport-development` skill and follow the Passport inspection
workflow in the `testops-laravel-engineering` skill.

1. Verify the installed version and configuration basis:

   ```powershell
   composer show laravel/passport
   Get-Content ".\config\passport.php"
   Get-Content ".\config\auth.php"
   git status --short -- ".\app", ".\config", ".\vendor\laravel\passport\src"
   ```

2. Check the **approved TestOps baseline** - every row must match observed
   state, and any mismatch is a finding, not something to fix silently:

   | Setting                          | Approved value                 |
   | -------------------------------- | ------------------------------ |
   | Client Credentials               | Enabled                        |
   | Authorization Code               | Enabled                        |
   | PKCE                             | Required, S256 only            |
   | Refresh Token                    | Enabled for Authorization Code |
   | Access token lifetime            | 15 minutes                     |
   | Client Credentials token lifetime| 15 minutes                     |
   | Refresh token lifetime           | 7 days                         |
   | Device Authorization             | Disabled                       |
   | Password Grant                   | Disabled                       |
   | Implicit Grant                   | Disabled                       |

3. Verify runtime grant enablement, not just defaults (Tinker, no secrets in
   output):

   ```powershell
   php artisan tinker --execute='dump(\Laravel\Passport\Passport::$deviceCodeGrantEnabled); dump(\Laravel\Passport\Passport::$passwordGrantEnabled); dump(\Laravel\Passport\Passport::$implicitGrantEnabled);'
   php artisan tinker --execute='dump(\Laravel\Passport\Passport::tokensExpireIn()); dump(\Laravel\Passport\Passport::refreshTokensExpireIn()); dump(\Laravel\Passport\Passport::clientCredentialsTokensExpireIn());'
   ```

4. Verify the implementation details: PKCE middleware
   (`EnforceAuthorizationCodePkce`), scope validation, service-client
   authorization, token revocation, refresh-token rotation, routes.
5. Verify **separation of concerns**: OAuth token scopes, application-level
   service-client permissions, and human user roles are three distinct layers.
   Machine clients are service principals, not human users. Approved policy:
   `system-settings:read` is the OAuth read scope; `system-settings:write` is
   registered but not granted to machine clients; `system-settings.read` is the
   application-level read permission; machine clients need both OAuth scope and
   permission; machine clients cannot inherit `SUPER_ADMIN` or `SYSTEM_ADMIN`;
   machine-client write access is disabled.
6. Check the OAuth client lifecycle: client creation, secret storage, key
   availability (verify existence only, never contents), token cleanup.
7. Inspect vendor source when behavior is uncertain
   (`vendor\laravel\passport\src\`, version 13.x) - read-only reference.
8. Report each baseline point as Matching / Mismatch (with exact observed
   evidence), findings ranked by severity, and tests that already assert the
   approved behavior (`tests/Feature/Authentication/PassportConfigurationTest.php`
   and related).

Never introduce unauthorized grants or expand service-client permissions; flag
rather than fix, and stop for human approval on any proposed policy change.
