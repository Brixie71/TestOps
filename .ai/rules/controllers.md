---
paths:
  - 'app/Http/Controllers/*.php'
---

# Controllers

## Resource-oriented thin controllers; Action classes are optional
Controllers use standard resource actions (index/show/store/update/destroy) and stay thin: coordinate validation, authorization, the application operation, and the response only. Extract {Verb}{Noun}Action classes into app/Actions/ only when business logic is sufficiently complex or genuinely reused; simple operations stay inline in the controller. Use constructor/promoted property injection; never call app() or resolve() inside controllers. Interfaces only at real external boundaries (e.g. a future test-runner integration), bound in a service provider - not for internal domain logic.

## State labels: string constants across the testing domain
TestCase, TestRun, TestResult, and Defect statuses (plus case and defect severities) are string constants with `STATUSES` / `SEVERITIES` allowlists because the testing domain shares the existing plain-string convention. Do not retrofit enums into already-shipped columns; introduce them only in a separately approved change.
