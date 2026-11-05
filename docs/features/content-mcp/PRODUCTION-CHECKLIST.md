# Pre-flight checklist — content-mcp on the Dovira installs

Created by Sprint 1 Step 1 (the spike's numbers), completed by Sprint 2 Step 3
into the production checklist. Work through section A **once per install**,
in order; section D is how the developer runs the connector afterwards. The
installs are independent: `dev.dovira.vet` (where every step is verified),
Kharkiv `dovira.vet` (deployed by hand from `master`) and Kyiv
`kyiv.dovira.vet` (from `kyiv`). Each install has its own connector URL
`https://{host}/wp-json/mcp/dovira`, its own database, its own dump folder and
its own application passwords. No city lives in the code.

Deploying is the user's own action, never a step's (root `CLAUDE.md` → git
model). This checklist starts once the files are on the server.

> **Never paste an application password** into a chat, an issue, a commit or a
> screenshot. It goes only into the person's own connector settings in
> claude.ai or Claude Desktop. Revoking it in the WP profile disconnects that
> person.

---

## A. Per install, before the first session

- [ ] **A1. The hosting lets Claude through.** claude.ai calls the connector
      from Anthropic's cloud with the user agent `Claude-User`. The hosting's
      bot / AI-crawler protection blocks it before WordPress (Sprint 1 Step 1:
      405 on dev, 403 on `dovira.vet`).
      - In the hosting panel of the account, allow the user agent
        `Claude-User` for the install's host, at least on `/wp-json/mcp/`.
        `ClaudeBot`, `GPTBot` and the other training crawlers may stay
        blocked. Without a per-agent switch, ask the hosting support.
      - Check from any terminal:
        ```
        curl -s -o /dev/null -w '%{http_code}\n' -A 'Claude-User' -H 'Content-Type: application/json' https://{host}/wp-json/mcp/dovira -d '{}'
        ```
        It prints **401** (WordPress asks for the password). 403 or 405 means
        the hosting still blocks it.
- [ ] **A2. Activate the plugin.** Plugins → «MCP Adapter» (0.7.1, committed
      under `wp-content/plugins/mcp-adapter/`) → Activate, or
      `wp plugin activate mcp-adapter`. Without it the theme feature stays off
      and the site behaves as before.
- [ ] **A3. Set the dump folder.** Every confirmed write uses it: the first
      write of a session dumps the database there, and a failed dump means no
      write.
      - Create it directly under `/home/dovirave/`, one per install and
        outside every webroot (see B, Webroot layout), e.g. for Kharkiv:
        ```
        mkdir -p /home/dovirave/dovira-mcp-dumps/kharkiv && chmod 700 /home/dovirave/dovira-mcp-dumps/kharkiv
        ```
      - In that install's `wp-config.php`, above
        `/* That's all, stop editing! */`:
        `define( 'DOVIRA_MCP_DUMP_DIR', '/home/dovirave/dovira-mcp-dumps/kharkiv' );`
      - Check from the install's folder:
        `wp eval 'echo DOVIRA_MCP_DUMP_DIR, "\n";'` prints that path.
      - Unset, the feature falls back to `wp-content/backup-db/` behind an
        `.htaccess` deny it writes itself (Apache/LiteSpeed only). If an
        install has to use it, check that a dump's URL answers 403 or 404, as
        in Sprint 1 Step 3's guide, item 11.
- [ ] **A4. The install's city term.**
      `wp term list service-city --fields=term_id,slug,name` lists the
      install's own city with the slug the site URL implies: `kharkiv` on
      `dovira.vet` (and dev), `kyiv` on `kyiv.dovira.vet`. Without it a
      per-city price reads «no price», a per-city price change is refused,
      and a new service's price rows are refused. Fix a wrong slug in
      wp-admin → Services → Cities (the term's id stays, so prices stay).
- [ ] **A5. One application password per person.** Each person creates one in
      their own WordPress profile (Users → Profile → Application Passwords,
      name «Claude»). There are three connector users: the owner
      (administrator), the content manager (editor) and the developer
      (administrator). The password goes only into that person's own
      connector (A6), never anywhere else.
- [ ] **A6. Add the connector in each person's own claude.ai.** Settings →
      Connectors → Add custom connector, exactly as claude.ai's form takes it:
      - URL: `https://{host}/wp-json/mcp/dovira`;
      - authentication: «Без входу» (no sign-in);
      - one request header: name `authorization`, value `Basic ` followed by
        the base64 of `login:application-password`, made on the person's own
        computer, e.g. `printf '%s' 'login:xxxx xxxx xxxx xxxx xxxx xxxx' | base64`.
      - The tool list shows the 14 `dovira-…` tools.
- [ ] **A7. The first session's smoke test**, by each person in a new chat:
      - «Опиши сайт». `dovira-describe-site` names the install's city, the
        languages and the person. `can_delete` is `true` only for an
        administrator.
      - «Зміни ціну на {any row} на {any amount}». A preview is shown. Answer
        «Ні». `ls` of the dump folder shows no new file.
      - A request without the header gets 401:
        `curl -s -o /dev/null -w '%{http_code}\n' -A 'Claude-User' https://{host}/wp-json/mcp/dovira -d '{}'`.
- [ ] **A8. Retention.**
      `wp cron event list --fields=hook,recurrence | grep dovira_mcp` lists
      `dovira_mcp_dump_retention` with `1 day`. It is registered on the first
      page load after the deploy.

## B. Measured by the Sprint 1 spike (2026-11-05)

A PHP-only dump through `$wpdb` (`SHOW TABLES`, `SHOW CREATE TABLE`, 500-row
`INSERT` batches, `gzopen` level 6, `chmod 0600`). Script:
`_to_delete/content-mcp-dump-spike.php`, run with `wp eval-file`.

| | DDEV (local) | `dev.dovira.vet` (shared host) |
|---|---|---|
| Tables / rows | 19 / 32,208 | 18 / 31,579 |
| Raw SQL → `.sql.gz` | 8.68 MB → 0.97 MB | 7.18 MB → 0.70 MB |
| Seconds | 0.48 | 0.75 |
| Peak memory (`memory_get_peak_usage( true )`, WP loaded) | 44.5 MB | 96.5 MB |
| PHP | 8.3.32 | 8.5.12 (CLI) |
| `memory_limit` | −1 (CLI) | −1 under WP-CLI; `php -r` with the account's `php.ini` (`/usr/local/php85/etc/php.ini`): 512M |
| `max_execution_time` | 0 (CLI) | 0 (CLI); **web value not read** |
| `WP_MEMORY_LIMIT` | 40M | 40M |
| `exec` callable | yes | yes (not used: the dump is PHP-only by decision) |
| Folder | `/tmp/dovira-dump-spike` (container) | `/home/dovirave/dovira-dump-spike`, outside the webroot |
| **Web request** (a session's first confirmed write; the dump's footer `-- tables` / `-- finished in`) | 0.25–0.45 s, ~30 MB (Sprint 2 Steps 1–3, over MCP) | to be filled from a real session: Sprint 1 Step 3 (dev) or Sprint 2 Step 3 (productions) |

- **Exit criteria met.** The dump fits one request with large headroom: 0.75 s
  against any web limit of 30 s or more.
- **Memory is the figure to watch.** The 96.5 MB peak is above the 40M
  `WP_MEMORY_LIMIT`. WordPress only *raises* `memory_limit` to that value, so
  what governs a REST request is the web PHP `memory_limit`. The account's
  `php.ini` says 512M. The web-request row above, once filled from a real
  session's dump footer, settles both web values.
- **Webroot layout on the host.** Dev's webroot is
  `/home/dovirave/dovira.vet/dev`, nested inside `/home/dovirave/dovira.vet/`.
  - A dump folder inside `/home/dovirave/dovira.vet/` may be reachable through
    another install's host.
  - So `DOVIRA_MCP_DUMP_DIR` is a folder directly under `/home/dovirave/`, one
    per install (e.g. `/home/dovirave/dovira-mcp-dumps/dev`).
  - The spike's dump file is left in `/home/dovirave/dovira-dump-spike/`; delete
    it by hand.

## C. Installs taken through A

- [x] `dev.dovira.vet`: A1–A8 during Sprints 1–2 (dump folder
      `/home/dovirave/dovira-mcp-dumps/dev`).
- [x] Kharkiv `dovira.vet`, after the hand deploy from `master`: A1–A8, plus
      one consented small change that left one dump in its folder. Verified
      by the user at the close of Sprint 2 Step 3 (2026-11-05); the `curl`
      code and the dump's footer were not reported.
- [x] Kyiv `kyiv.dovira.vet`, after merging `master` into `kyiv` and
      deploying: the same, with its dump folder outside its webroot.
      Verified by the user at the same close; its city term, webroot path,
      `curl` code and dump footer were not reported.

## D. Operations

- **Which dump undoes a session.** Each session's first confirmed change
  writes `dovira-{host}-{YYYY-MM-DD}-{HHMMSS}.sql.gz` into the install's dump
  folder, just before that change. The connector's result named the file
  (`dump`). Otherwise take the newest file older than the change.
- **Restoring a dump** (the developer, over SSH, from the install's folder):
  ```
  gunzip -c /home/dovirave/dovira-mcp-dumps/{install}/{file}.sql.gz | wp db import -
  ```
  - It replaces the **whole** database with its state at the dump, including
    every change anyone made after it (forms submitted, other edits).
  - Each table is dropped and re-created, so it imports over a live
    database. It was verified on DDEV in Sprint 2 Step 3: the content tables'
    checksums equal those before the change.
  - Take a fresh dump first if what came after must be kept (any confirmed
    connector write in a new chat makes one, or `wp db export`).
- **Removing a person.** Revoke their application password in their profile
  (Users → Profile → Application Passwords → Revoke), or
  `wp user application-password list {login}` then
  `wp user application-password delete {login} {uuid}`. Their connector gets
  401 from then on. A leaked password is revoked the same way, and a new one
  is created.
- **Retention.** The daily event deletes this feature's dump files older than
  90 days (filter `dovira_mcp_dump_retention_days`, at least 1); nothing
  else in the folder is touched.
