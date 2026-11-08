---
paths:
  - 'app/Http/Controllers/*.php'
---

# Controllers

## Resource-oriented thin controllers; Action classes are optional
Controllers use standard resource actions (index/show/store/update/destroy) and stay thin: coordinate validation, authorization, the application operation, and the response only. Extract {Verb}{Noun}Action classes into app/Actions/ only when business logic is sufficiently complex or genuinely reused; simple operations stay inline in the controller. Use constructor/promoted property injection; never call app() or resolve() inside controllers. Interfaces only at real external boundaries (e.g. a future test-runner integration), bound in a service provider - not for internal domain logic. Use enums for stateful labels with TitleCase keys when their state column is implemented; no status enums exist yet, so add one then (e.g. TestResultStatus when the Defect Tracking layer lands), and TestRunStatus only if a run status is later justified.
