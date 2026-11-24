# Verification — content-mcp, Sprint 1, Step 3

Checks that, in claude.ai on `dev.dovira.vet`, a price row is changed only
after a preview and the person's consent. It must land in both language
copies, and the first write of a chat must leave a new database dump in a
web-closed folder; later writes in the same chat must not.

Branch: `content-mcp/sprint-1-read-and-price`. Use the connectors from Step 1:
`Dovira dev (editor)` and `Dovira dev (admin)`. Unless an item says otherwise,
use the **editor** connector and start a **new chat** for each numbered item.
claude.ai may ask you to allow each tool call; allow it.

The rows named below exist in the local database; dev's amounts may differ.
Note the original amount of every row you change, and put it back at the end
(item 12).

---

1. **Put the task branch on dev.**
   ```
   git push origin content-mcp/sprint-1-read-and-price:dev
   ```
   - It is a fast-forward of what Step 2 pushed.
   - Wait for «FTP deploy on push» to finish green. Nothing to activate.
2. **Set the dump folder on dev** (SSH to the hosting account):
   ```
   mkdir -p /home/dovirave/dovira-mcp-dumps/dev && chmod 700 /home/dovirave/dovira-mcp-dumps/dev
   ```
   - In dev's `wp-config.php` (`/home/dovirave/dovira.vet/dev/wp-config.php`),
     above `/* That's all, stop editing! */`, add:
     ```php
     define( 'DOVIRA_MCP_DUMP_DIR', '/home/dovirave/dovira-mcp-dumps/dev' );
     ```
   - Check, from `/home/dovirave/dovira.vet/dev`:
     `wp eval 'echo DOVIRA_MCP_DUMP_DIR, "\n";'` prints that path.
   - `ls -la /home/dovirave/dovira-mcp-dumps/dev` is empty.
3. **Seven tools.** Open the connector's tool list in claude.ai.
   - It must show exactly these: `dovira-describe-site`, `dovira-list-posts`,
     `dovira-get-post`, `dovira-find-price-rows`, `dovira-list-terms`,
     `dovira-list-media`, `dovira-update-price-row`.
   - If the list still shows five, refresh the connector (Settings →
     Connectors → the connector → reconnect).
4. **Find a row; an empty city column has no price.** Ask «Знайди в прайсі
   рядки "УЗД"».
   - The agent calls `dovira-find-price-rows` and lists rows with their
     service and amount.
   - Compare with the service page «Діагностика: УЗД, ЕХОкГ, тонометрія, ЕКГ»
     on `https://dev.dovira.vet` (Харків tab). A row the page shows as
     «Дізнатись ціну: …» (locally «УЗД моніторинг», «УЗД одного органа»)
     must come back with **no price**, never a number.
5. **Change a per-city price: preview first.** Ask «Зміни ціну на екстрений
   прийом, встанови 550 грн».
   - The agent finds «Екстрений прийом» (service «Приймальне відділення»)
     and calls `dovira-update-price-row` **without** confirmation.
   - It shows you a preview before writing anything, and the preview names:
     - the service and the row;
     - the current amount → 550 for the Ukrainian copy (`uk`);
     - the same row → 550 for the Russian copy (`ru`);
     - no warnings.
   - It asks for your consent.
   - **Before answering**, check that nothing changed: the service page still
     shows the old amount, and `ls /home/dovirave/dovira-mcp-dumps/dev` is
     still empty.
6. **Change your mind: a new preview.** In the same chat answer «Ні, 560».
   - The agent must show a **new** preview with 560 and ask again. It must
     not write 560 with the token of the 550 preview.
   - If it tries anyway, the tool answers «Підтвердження не дійсне для цих
     змін…», and nothing is written.
   - The page still shows the old amount.
7. **Consent: the write and the session's dump.** Answer «Так».
   - The result names the written ids (the service and its Russian copy) and
     a dump file `dovira-dev.dovira.vet-YYYY-MM-DD-HHMMSS.sql.gz`.
   - The Ukrainian page shows 560 for «Екстрений прийом» in the Харків tab,
     and so does `https://dev.dovira.vet/ru/…`, the Russian page of the
     service.
   - Ask «Покажи цей рядок в обох копіях»: `get-post` shows 560 in both.
   - On the server, `ls -la /home/dovirave/dovira-mcp-dumps/dev` shows
     exactly **one** file, mode `-rw-------`.
   - The file opens:
     ```
     zcat /home/dovirave/dovira-mcp-dumps/dev/dovira-dev.dovira.vet-*.sql.gz | head -5
     zcat /home/dovirave/dovira-mcp-dumps/dev/dovira-dev.dovira.vet-*.sql.gz | tail -2
     ```
     The head shows `-- Dovira content-mcp dump`, `-- host: dev.dovira.vet`
     and the date. The tail shows `-- tables: N` and
     `-- finished in X s, peak memory Y MB`. **Report these two lines.** They
     are the dump's cost in a real web request on the host, and
     `/close-step` records them in `PRODUCTION-CHECKLIST.md`.
   - In wp-admin, open the service: the price table opens normally. Each
     row's «Is price general?», its cities and the other cities' columns are
     as before.
8. **A second write in the same chat adds no dump.** In the same chat: «А
   тепер постав 600 грн за Ларинготрахеобронхоскопію».
   - This row of «Ендоскопія» has one price for every city: «Is price
     general?» is on.
   - Preview, consent, write. The result says `"dump": "existing"`.
   - `ls /home/dovirave/dovira-mcp-dumps/dev` still shows **one** file.
   - The page of «Ендоскопія» shows 600 in both language copies.
9. **A price is changed only in the Ukrainian copy.** New chat: «Зміни ціну в
   російській копії послуги Приймальне відділення, рядок 0, на 1 грн».
   - The agent opens the Russian copy and calls `update-price-row` with its
     id. The tool refuses with «Ціну змінюють в українській копії послуги — у
     російську вона переноситься сама.»
   - Nothing is written, and no new dump appears.
10. **A Russian copy with another number of rows gets a warning.**
    - In wp-admin, open the **Russian** copy of «Реабілітація» and add one
      empty row at the end of «Prices». Save.
    - New chat: «Зміни ціну першого рядка послуги Реабілітація на 1000 грн».
    - The preview has one line, the Ukrainian copy only, plus the warning
      «Російська копія (id …) має N рядків цін, українська — M: ціну змінено
      лише в українській…».
    - Do **not** confirm: answer «Ні». Then delete the added row in wp-admin
      and save.
11. **Without the folder constant: the fallback is closed to the web.**
    - In dev's `wp-config.php`, comment out the `DOVIRA_MCP_DUMP_DIR` line.
    - New chat (a new session): change any price by 1 грн, confirm.
    - The result names a new dump file. On the server
      `ls -la /home/dovirave/dovira.vet/dev/wp-content/backup-db/` shows it
      (`-rw-------`), plus `.htaccess` and an empty `index.php`.
    - Open `https://dev.dovira.vet/wp-content/backup-db/{that file name}` in
      a private browser window. It must **not** download: expected 403 (or
      404). **Report the status you got.**
    - Delete that dump file, put the price back (preview, consent), and
      uncomment the `DOVIRA_MCP_DUMP_DIR` line again.
12. **Retention, and putting things back.**
    - `wp cron event list --fields=hook,recurrence | grep dovira_mcp` lists
      `dovira_mcp_dump_retention` with `1 day`.
    - Ask the agent to set every changed row back to its original amount
      (items 7, 8 and 11), with preview and consent as above.
    - Then check both pages show the original amounts again.
13. **The gate.** On the branch, `bin/check.sh` exits 0 (`==> check: all
    green`).

Not covered here: the retention event actually deleting a 90-day-old file. It
won't happen on dev for 90 days; `DumpTest` covers which files it deletes.
