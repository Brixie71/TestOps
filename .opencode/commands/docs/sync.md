---
description: Update documentation and AI rules to match recent implementation changes, without documenting unimplemented functionality
---

Inspect implementation changes and update the relevant documentation and AI
rules so documentation matches the actual implementation: $ARGUMENTS

Arguments: a feature/module scope, or omitted meaning "make existing docs match
current uncommitted changes".

1. Establish what actually changed and is actually implemented:

   ```powershell
   git status --short
   git diff --stat
   ```

   Verify implementation claims against real code and passing tests before
   documenting anything as done. Never document unimplemented functionality as
   completed.
2. Inventory the documentation surface that may be affected: `README.md`,
   `AGENTS.md`, `.ai/rules/*.md` (including `index.md`), `.ai/guides/*.md`,
   `.agents/skills/*/SKILL.md`, `docs/*.md`, and `.opencode/commands/**.md`
   (the `README.md` inventory and every slash-command template).
3. Update accurately:
   - Record newly implemented behavior with the files that implement it.
   - Keep instructions, commands, and rule indexes consistent with reality
     (e.g. update `.ai/rules/index.md` globs only when the referenced files
     exist).
   - Distinguish current-release functionality from Future Release items; mark
     future items explicitly and unambiguously.
   - Preserve approved specifications verbatim in meaning - including the
     approved Passport baseline and scope/permission separation. Update policy
     docs only when the user has explicitly approved a policy change.
4. Match the documentation style of each file you edit; prefer editing existing
   files over creating new ones (documentation files are only created when
   explicitly requested).
5. Confirm claims by cross-checking referenced commands, paths, and tests still
   exist.

Report: files updated, exact claims changed, items verified as implemented
before documentation, and anything Future Release that stays marked as such.
