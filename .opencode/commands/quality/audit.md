---
description: Structured quality audit of a feature or change - correctness, maintainability, security, test coverage, rule consistency (read-only)
---

Perform a structured quality review of: $ARGUMENTS

Arguments: a feature, file, module, or omitted meaning "current uncommitted
changes". Read-only; do not modify files.

Use the `testing-best-practices` skill for test-value evaluation and the
`laravel-best-practices` skill for code-quality checks. Follow project rules in
`AGENTS.md` and `.ai/rules/`.

1. Define the audit scope from `$ARGUMENTS` or the Git working tree; read the
   code under review.
2. Evaluate each dimension with concrete evidence:
   - **Correctness** - does the implementation do what approved requirements
     imply; unhandled edge cases.
   - **Maintainability** - naming clarity, duplication, abstraction
     appropriateness, dead code, coupling.
   - **Security** - authorization checks, validation, secrets/keys exposure,
     injection surfaces, sensitive data handling. Escalate anything exploitable
     and reference `/security/review` as the deep-dive.
   - **Test coverage** - behavior and failure modes actually covered; tests
     that only exercise happy paths; absence of regression value in
     decorative tests.
   - **Rule consistency** - conformance to `.ai/rules/` for each file's path
     and to `.ai/guides/` conventions. For database work use the db commands
     (`/db/review-migration` for a migration deep-dive).
3. Audit the evidence, not intentions: every finding cites exact paths/lines
   and observed code.
4. Report findings grouped by dimension with severity (Critical / Important /
   Minor), each with its concrete evidence and a minimal suggested correction.
5. Provide an overall quality verdict and the top three priorities.

Verify coverage claims by reading or running the referenced tests, not by
assuming they exist.
