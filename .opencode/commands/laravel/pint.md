---
description: Inspect modified PHP files and run Laravel Pint, reporting formatting changes
---

Run Laravel Pint on the project's modified PHP files.

1. Inspect what is actually modified before formatting:

   ```powershell
   git status --short
   git diff --stat
   ```

   If there are no modified PHP files, say so and stop - do not run Pint over
   unrelated files.
2. Run Pint only for the current changes:

   ```powershell
   .\vendor\bin\pint --dirty
   ```

   For a single specific file when requested, use
   `.\vendor\bin\pint ".\app\Path\To\File.php"`.
3. Inspect the resulting diff to see what actually changed:

   ```powershell
   git diff --stat
   git diff --check
   ```

4. Report: files that were modified by Pint (exact paths) and the nature of the
   formatting changes. If Pint changed nothing, state that plainly.
5. Formatting-only changes require no new tests, but confirm the relevant tests
   still pass when the diff is large or touches behavior-adjacent formatting.

Pint does not modify vendor dependencies or non-PHP files. Report any Pint
errors exactly as they appeared.
