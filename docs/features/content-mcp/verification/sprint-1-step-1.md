# Verification — content-mcp, Sprint 1, Step 1

Checks that `dev.dovira.vet` serves the `dovira` MCP server with one tool, that
claude.ai connects to it with a person's own application password, and that
roles show. The dump spike's numbers are already measured and recorded (step
report in `SPRINT-1-PLAN.md`, `PRODUCTION-CHECKLIST.md` → B); nothing to redo.

Branch: `content-mcp/sprint-1-read-and-price`.

---

1. **Prepare dev, then put the task branch on it.**
   - `origin/dev` is 237 commits behind this branch. Pushing deploys
     everything `master` has gained since dev was last updated, not only this
     step.
   - Before the push, check two things in dev's
     `/home/dovirave/dovira.vet/dev/wp-config.php` and admin (WORKLOG
     2026-10-20 and 2026-11-04):
     - `DOVIRA_TELEGRAM_BOT_TOKEN` and `DOVIRA_TELEGRAM_JOIN_PASSWORD` are
       defined. Without them, form notifications on dev stop, though records
       are still saved.
     - Polylang is ≥ 3.9 (Plugins screen).
   - Then push. It is a fast-forward, no force needed:
     ```
     git push origin content-mcp/sprint-1-read-and-price:dev
     ```
   - Wait for the GitHub Actions run «FTP deploy on push» to finish green.
2. **Activate the plugin on dev.** `https://dev.dovira.vet/wp-admin/plugins.php`
   → «MCP Adapter» 0.7.1 → Activate. Or over SSH:
   `cd /home/dovirave/dovira.vet/dev && wp plugin activate mcp-adapter`.
   - Expected: activated with no error notice.
   - The site's front page and an admin page still open.
   - `wp mcp-adapter list` shows `dovira` with 1 tool, plus
     `mcp-adapter-default-server`.
3. **No password → refused.** Run:
   ```
   curl -s -w '\nHTTP %{http_code}\n' -H 'Content-Type: application/json' -H 'Accept: application/json, text/event-stream' https://dev.dovira.vet/wp-json/mcp/dovira -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}'
   ```
   Expected: `rest_forbidden`, **HTTP 401**. Nothing about the site is returned.
4. **Editor: application password and connector.**
   1. Log into dev as an **editor** user. If there is none, create one
      (Users → Add New, role «Редактор»).
   2. Profile → Application Passwords → name `Claude` → Add. Copy the password
      once.
   3. Build the header value on your machine, then delete the line from your
      shell history:
      ```
      printf '%s' 'editor-login:xxxx xxxx xxxx xxxx xxxx xxxx' | base64
      ```
   4. In claude.ai → Settings → Connectors → Add custom connector. Fill in:
      - name `Dovira dev (editor)`;
      - URL `https://dev.dovira.vet/wp-json/mcp/dovira`;
      - request header `Authorization: Basic <the base64>`.

      **Write down:** what claude.ai offered for authentication, whether the
      header had to be typed by hand, and where (the field's name). If
      claude.ai has no way to send a header, stop here and report it. That
      finding reopens DECISIONS «Each person connects with an application
      password».
   5. Expected: the connector saves and connects. Its tool list shows exactly
      one tool, **`dovira-describe-site`** («Опис сайту»), and no
      `mcp-adapter-*` tools.
5. **Editor: the call.** In a new chat with that connector on: «Опиши сайт,
   до якого ти підключений.»
   - Expected: the agent calls `dovira-describe-site`.
   - The answer has `city: kharkiv`, `languages: [uk, ru]`, the editor's
     login, `roles: [editor]` and **`can_delete: false`**.
6. **Administrator: the same, with deletion allowed.** Repeat 4–5 with an
   **administrator's** own application password, as a second connector
   `Dovira dev (admin)` or by replacing the header.
   - Expected: `roles` contains `administrator` and **`can_delete: true`**.
7. **The tool is not on the adapter's default server** (negative check). In
   claude.ai, ask: «Виклич інструмент mcp-adapter-discover-abilities».
   - Expected: the agent says no such tool exists on this connector. The
     `dovira` server lists only `dovira-describe-site`.
8. **The spike script cannot be run from the web** (negative check):
   ```
   curl -s -o /dev/null -w '%{http_code} %{size_download}\n' https://dev.dovira.vet/_to_delete/content-mcp-dump-spike.php
   ```
   - Expected: `200 0` or a 403/404, never any output.
   - No new file appears in `/home/dovirave/dovira-dump-spike/`.
   - Delete the spike's dump file there by hand afterwards.
9. **The gate.** On the task branch, on the host:
   `bash bin/check.sh` → ends with `==> check: all green`, exit 0, and the theme
   PHPUnit stage shows **182 tests**.

Report the notes from 4.4 when you close the step. `/close-step` carries them
into the WORKLOG, and `PRODUCTION-CHECKLIST.md` → A4 is corrected if
claude.ai's form differs.
