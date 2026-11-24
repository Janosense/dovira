# Verification — content-mcp, Sprint 2, Step 3

This guide checks two things:

- **The tools and instructions** (`dev.dovira.vet`): the connector lists its
  14 tools in the order a chat uses them, and its instructions name each
  one.
- **The production checklist** (`PRODUCTION-CHECKLIST.md`): it is complete
  enough to take both productions through by following it alone. On each,
  the three people connect, a preview writes nothing, one consented change
  leaves one dump, and only administrators can trash.

**Branch:** `content-mcp/sprint-2-handover`. Read the checklist from this
branch: `docs/features/content-mcp/PRODUCTION-CHECKLIST.md`.

**Productions are deployed from today's `master`**, as the plan's Question 2
decided (B). That `master` already has every tool of Steps 1–2. This step
changes only the instructions' wording, the tools' order and the docs, so
one more hand deploy after `/close-step` brings those.

**Report back** the values marked **Report**. `/close-step` records them.

> Never paste an application password or the `Basic …` value into a chat,
> an issue or a commit.

---

1. **Revoke the leaked password first.** The editor's first application
   password was pasted into a chat during Sprint 1.
   - In `dev.dovira.vet` wp-admin, open the editor user → Application
     Passwords and revoke every password except the one the editor
     connector uses now.
   - If you are not sure which one that is, revoke them all, create a new
     one and update the connector (checklist A6).
2. **Put the task branch on dev.**
   ```
   git push origin content-mcp/sprint-2-handover:dev
   ```
   - This is a fast-forward of what Step 2 pushed.
   - Wait for «FTP deploy on push» to finish green.
3. **The order and the instructions (dev, editor).**
   - **Tool list:** reconnect the connector. The tool list in claude.ai
     shows the 14 tools in this order: `describe-site`, `list-posts`,
     `get-post`, `find-price-rows`, `list-terms`, `list-media`,
     `update-price-row`, `update-post`, `create-term`, `update-term`,
     `upload-media`, `create-post`, `create-translation`, `trash-post`. All
     start with `dovira-`.
   - **Instructions:** in a new chat, ask «Що тобі сказано про цей сервер і
     в якому порядку ти використовуєш інструменти?». The answer reflects
     the instructions:
     - `describe-site` comes first;
     - lists to find things;
     - prices only through the price tools;
     - a term or an image before the post;
     - the status as you name it;
     - the Russian copy only when asked;
     - every write previewed and written only after consent;
     - trash only for administrators.
4. **Dev through the checklist.** Run A1, A4, A7 and A8 of the checklist on
   `dev.dovira.vet`. A2, A3, A5 and A6 are already done there.
   - **A1:** the `curl` with `-A 'Claude-User'` prints `401`. **Report** the
     code.
   - **A4:** the command lists `kharkiv`.
   - **A7:** do it with the editor connector. The preview appears, you answer
     «Ні», and no new dump file appears.
   - **A8:** the command lists `dovira_mcp_dump_retention` with `1 day`.
   - **A consented change:** in a new chat, change any price by 1 грн,
     agree, then put it back in the same chat.
     - The dump folder has one new file.
     - **Report** its last two lines:
       ```
       zcat /home/dovirave/dovira-mcp-dumps/dev/$(ls -t /home/dovirave/dovira-mcp-dumps/dev | head -1) | tail -2
       ```
       These are the web-request numbers that checklist B still lacks.
5. **Kharkiv `dovira.vet`.** Deploy today's `master` by hand, as usual. Then
   take the install through A1–A8 of the checklist, in order.
   - **A1:** the hosting must let `Claude-User` through. **Report** the
     `curl` code (expected `401`).
   - **A3:** e.g. `/home/dovirave/dovira-mcp-dumps/kharkiv`.
   - **A5–A6:** the owner, the content manager and you each create
     «Claude» in your own profile and add the connector in your own
     claude.ai, with the URL `https://dovira.vet/wp-json/mcp/dovira`.
   - **A7:**
     - every person sees `describe-site` name **Харків** and themselves;
     - `can_delete` is `true` for the owner and you, `false` for the content
       manager;
     - a price preview, answered «Ні», leaves no dump.
   - **The editor cannot trash:** the content manager asks «Видали вакансію
     …». The tool reports that this is not allowed.
   - **An administrator can:** you ask the same. You get a preview, and you
     answer «Ні».
   - **One consented small change:** you change any price by 1 грн, agree,
     and put it back.
     - The Kharkiv dump folder has **one** file.
     - **Report** its last two lines (the command of item 4 with the
       Kharkiv folder).
6. **Kyiv `kyiv.dovira.vet`.**
   - Merge `master` into `kyiv` and deploy it by hand:
     ```
     git checkout kyiv && git merge master
     ```
   - Then the same as item 5, with the URL
     `https://kyiv.dovira.vet/wp-json/mcp/dovira` and its own dump folder
     (e.g. `/home/dovirave/dovira-mcp-dumps/kyiv`).
   - **A4 must list `kyiv`.** **Report** its term id and slug.
   - `describe-site` names **Київ**.
   - Kyiv has no blog: «Створи новину …» is refused.
   - **Report** the A1 code and the dump's last two lines.
   - Check that the dump folder is outside the Kyiv webroot. **Report** the
     webroot path.
7. **The gate.** On the branch, `bin/check.sh` exits 0 (`==> check: all
   green`).

**Not here:** the restore (checklist D) was verified on DDEV during the step
(content-table checksums equal before and after). Do not restore a
production database to test it.
