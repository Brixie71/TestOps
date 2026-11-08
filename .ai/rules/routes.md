---
paths:
  - 'routes/*.php'
---

# Routes

## REST API under /api/v1; Passport OAuth authentication
Version all API routes under /api/v1 (apiPrefix set in bootstrap/app.php when routes are added). Use plural resource URL segments (e.g. /api/v1/test-suites/{suite}/runs/{run}) with ->scopeBindings() on nested resources. Every JSON response is an Eloquent API Resource (e.g. TestRunResource) with a consistent { data, meta, links } envelope. HTTP semantics: 201 on create, 204 on delete, 422 for validation, and correct 401 vs 403 usage. API authentication is implemented with Passport OAuth (client-credentials tokens for machine clients, OAuth user tokens for human users) and per-route scope/permission middleware; see .ai/rules/oauth.md and docs/oauth-service-authorization.md. Do not assume or introduce Sanctum.
