---
description: Security review of a feature or implementation - authn, authz, injection, sensitive data, token handling (read-only)
---

Perform a security review of: $ARGUMENTS

Arguments: a feature, file path, route, or omitted meaning "current uncommitted
changes". Read-only; do not modify files.

Prioritize exploitable issues backed by concrete evidence over theoretical
lists.

1. Establish scope and read the implementation:

   ```powershell
   git status --short
   git diff
   ```

   or targeted searches for the named area. Use the `testops-laravel-engineering`
   skill discipline and `laravel-best-practices` security rules.
2. Review each surface:
   - **Authentication** - guards in `config/auth.php`, route protection,
     token/personal-access-token usage, session vs API boundaries.
   - **Authorization** - policies, gates, middleware for every route/action;
     resource ownership checks; missing checks on new endpoints.
   - **Privilege escalation** - horizontal and vertical; capability the
     authenticated context shouldn't reach.
   - **Input validation** - FormRequests/rules, mass-assignment
     (`$fillable`/`$guarded`), integer/string coercion on ids.
   - **Injection risks** - raw SQL, string-built queries, unescaped output,
     unsafe shell passing.
   - **Sensitive data exposure** - secrets, encryption keys or salts in output,
     logs, errors, or responses; verification of "never print key contents"
     rules in diagnostic flows.
   - **Session and token handling** - token lifetimes (`tokensExpireIn`
     family), revocation on logout/rotation, PKCE enforcement points.
   - **Database integrity** - destructive requests, unchecked deletes,
     transaction discipline around multi-step writes.
   - **Auditability** - whether security-relevant writes leave a trace
     (model events/logs exist, not the log contents).
   - **Secure error handling** - internal details leaking to clients; test
     noise masquerading as error-safety.
3. Trace attacker-reachable paths: how would an unauthenticated user, an
   authenticated user without the role, and a machine client each attempt to
   abuse this code?
4. Report findings ranked by exploitability: **Critical** (exploitable as
   described), **High** (exploitable with additional access), **Medium**,
   **Informational**. Every finding must cite exact files/lines and the attack
   path in one or two sentences; provide minimal containment for each; note
   which findings deserve automated regression tests.
5. Verify the report claim against observed code - do not include a finding you
   cannot evidence.
