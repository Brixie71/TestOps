---
description: Summarize the current session - progress, decisions, files changed, verification results, next actions (read-only)
---

Create a concise summary of the current session's work. Read-only; verify state
before writing, never invent results.

1. Collect the session's observable trail:

   ```powershell
   git status --short
   git diff --stat
   ```

   plus whatever commands were actually run earlier in the session (from
   conversation context).
2. Produce the summary with exactly these sections:
   - **Progress** - what was accomplished this session, in a few sentences.
   - **Decisions** - decisions made and approvals received within the session;
     explicitly separate these from pre-existing approved project policy (the
     Passport baseline and permission separation rules are standing policy, not
     session decisions).
   - **Files** - files created, modified, or deleted, with exact paths and a
     one-line purpose each.
   - **Verification** - commands actually executed and their observed results
     (tests with summary lines, Pint output, migration status). Never claim
     any check ran if it did not, and never cite an output that was not
     observed.
   - **Next Actions** - the exact next steps, each tied to the task's approved
     requirements; include any verification commands the next person should
     run.
3. Preserve exact technical details needed to continue without repeating
   investigations: name specific test classes, command invocations, and
   file/line pointers rather than vague references.
4. Do not expose credentials, secrets, or encryption-key contents anywhere in
   the summary; do not run new tests or migrations as part of summarizing.
