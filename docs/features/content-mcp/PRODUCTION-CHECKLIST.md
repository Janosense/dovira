# Pre-flight checklist — content-mcp on the Dovira installs

Created by Sprint 1 Step 1 (the spike's numbers). Sprint 2 Step 3 completes it
into the production checklist. Work through it **once per install**. The
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

- [ ] **A1. Activate the plugin.** Plugins → «MCP Adapter» (0.7.1, committed
      under `wp-content/plugins/mcp-adapter/`) → Activate, or
      `wp plugin activate mcp-adapter`. Without it the theme feature stays off
      and the site behaves as before.
- [ ] **A2. Set the dump folder.** Add
      `define( 'DOVIRA_MCP_DUMP_DIR', '/home/…/…' );` to that install's
      `wp-config.php`. The folder must be writable by PHP and **outside every
      webroot of the hosting account** (see B, Webroot layout).
      - The folder is used from Sprint 1 Step 3 on.
      - Unset, the feature falls back to `wp-content/backup-db/` behind an
        `.htaccess` deny it writes itself, which works on Apache/LiteSpeed only.
- [ ] **A3. One application password per person.** Each person creates one in
      their own WordPress profile (Users → Profile → Application Passwords,
      name «Claude»). There are three connector users: the owner
      (administrator), the content manager (editor) and the developer
      (administrator).
- [ ] **A4. Add the connector in claude.ai.** Settings → Connectors → Add
      custom connector:
      - URL: `https://{host}/wp-json/mcp/dovira`;
      - request header: `Authorization: Basic base64(login:application-password)`.
      - What claude.ai detected and asked for on `dev.dovira.vet` is recorded
        in Sprint 1 Step 1's verification; this line is corrected if it
        differed.
- [ ] **A5. Check.** In a new chat: «Опиши сайт». The agent calls
      `dovira-describe-site`.
      - It must name the install's city and languages and the connected user.
      - `can_delete` is `true` only for an administrator.
      - A request without the header gets HTTP 401.

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

- **Exit criteria met.** The dump fits one request with large headroom: 0.75 s
  against any web limit of 30 s or more.
- **Memory is the figure to watch.** The 96.5 MB peak is above the 40M
  `WP_MEMORY_LIMIT`. WordPress only *raises* `memory_limit` to that value, so
  what governs a REST request is the web PHP `memory_limit`. The account's
  `php.ini` says 512M. Sprint 1 Step 3 verification confirms the dump inside a
  real web request on dev, which settles both web values.
- **Webroot layout on the host.** Dev's webroot is
  `/home/dovirave/dovira.vet/dev`, nested inside `/home/dovirave/dovira.vet/`.
  - A dump folder inside `/home/dovirave/dovira.vet/` may be reachable through
    another install's host.
  - So `DOVIRA_MCP_DUMP_DIR` is a folder directly under `/home/dovirave/`, one
    per install (e.g. `/home/dovirave/dovira-mcp-dumps/dev`).
  - The spike's dump file is left in `/home/dovirave/dovira-dump-spike/`; delete
    it by hand.

## C. Completed by Sprint 2 Step 3

- [ ] Kharkiv `dovira.vet`: dump folder, A1–A5 done, first session's dump present.
- [ ] Kyiv `kyiv.dovira.vet`: the same. Its host and webroot layout are not
      measured yet.
- [ ] Retention: the daily event `dovira_mcp_dump_retention` is scheduled
      (`wp cron event list | grep dovira_mcp`).
