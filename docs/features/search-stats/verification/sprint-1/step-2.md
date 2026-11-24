# Verification — search-stats, Sprint 1, Step 2

**Feature bootstrap, the table and the purge** · written at close on 2026-09-23
Branch merged: `search-stats/sprint-1-recording` → `master`

What this step promises:
- The first request after the code arrives creates
  `{prefix}dovira_search_queries` and records schema version `1`. After that,
  dbDelta runs only when the version is behind.
- A daily WP-Cron event deletes rows older than 90 days. A filter can change
  that, but never below 29 days.
- Nothing writes rows yet (that is Step 3), and nothing visitors see changes.

Run everything from the repo root (`/Users/tymofii/Projects/php/dovira`) against
the local install. Every expected output below was observed on 2026-09-23 while
this guide was written.

## 1. The gate is green
```bash
bash bin/check.sh; echo "exit=$?"
```
Expected:
- `==> php -l (theme dovira)` with `117 files checked`;
- `==> PHPUnit (theme dovira)` with `OK (15 tests, 23 assertions)`;
- `==> check: all green` and `exit=0`.

## 2. Start from what a fresh deploy finds
Remove what this step created locally, in this order. The first command loads
WordPress; the second does not, so nothing recreates the table in between.
```bash
ddev wp cron event delete dovira_search_stats_purge
ddev wp db query "DROP TABLE IF EXISTS wp_dovira_search_queries; DELETE FROM wp_options WHERE option_name = 'dovira_search_stats_db_version'"
```

**Negative check.** A `wp db` command does not load WordPress, so it must not
create anything:
```bash
ddev wp db query "SHOW TABLES LIKE 'wp_dovira_search_queries'"
ddev wp db query "SELECT COUNT(*) AS n FROM wp_options WHERE option_name = 'dovira_search_stats_db_version'"
```
Expected: the first prints nothing, and the second prints `n` / `0`.

## 3. One page load creates the table, the version and the event
```bash
curl -sk -o /dev/null -w '%{http_code}\n' https://dovira.ddev.site/
ddev wp db query "DESCRIBE wp_dovira_search_queries"
ddev wp db query "SHOW INDEX FROM wp_dovira_search_queries"
ddev wp db query "SELECT option_value, autoload FROM wp_options WHERE option_name = 'dovira_search_stats_db_version'"
ddev wp cron event list --fields=hook,recurrence | grep dovira
```
Expected:
- `200`.
- Six columns: `id bigint(20) unsigned NO PRI auto_increment`,
  `level varchar(16) NO`, `query_text varchar(100) NO`,
  `context_id bigint(20) unsigned NO default 0`,
  `results int(10) unsigned YES NULL`, `created_at datetime NO`.
- Three keys: `PRIMARY` on `id`; `level_created_at` on `level`, then
  `created_at`; `created_at` on `created_at`.
- The option is `1`, with autoload `on`.
- `dovira_search_stats_purge   1 day`.

## 4. dbDelta runs only when the version is behind
**Negative check.** On a current install, `maybe_upgrade()` must not call
dbDelta. The `dbdelta_queries` filter fires inside every dbDelta run:
```bash
ddev wp eval 'add_filter( "dbdelta_queries", function ( $q ) { echo "dbDelta ran\n"; return $q; } ); dovira\SearchStats\Schema::maybe_upgrade(); echo "done\n";'
```
Expected: only `done`. `dbDelta ran` must **not** print.

Now put the version behind and let one WordPress load catch up:
```bash
ddev wp option update dovira_search_stats_db_version 0
ddev wp db query "SELECT option_value FROM wp_options WHERE option_name = 'dovira_search_stats_db_version'"
ddev wp option get dovira_search_stats_db_version
```
Expected:
- The `db query` (no WordPress load) shows `0`.
- `option get` prints `1`: loading WordPress for that command ran the upgrade
  on `init` before the command read the option.

## 5. A second dbDelta finds nothing to change
```bash
ddev wp eval 'require_once ABSPATH . "wp-admin/includes/upgrade.php"; var_export( dbDelta( dovira\SearchStats\Schema::create_table_sql(), false ) ); echo "\n";'
```
Expected: `array (` `)`, i.e. empty. The column definitions match what
MariaDB reports, so an upgrade run never re-alters the table.

## 6. The purge event comes back by itself
```bash
ddev wp cron event delete dovira_search_stats_purge
ddev wp cron event list --fields=hook,recurrence | grep dovira
```
Expected: `Deleted a total of 1 cron event`, then
`dovira_search_stats_purge   1 day` again. The `list` command's own
WordPress load re-registered the missing event on `init`.

## 7. The purge deletes only rows older than the retention period
```bash
ddev wp db query "INSERT INTO wp_dovira_search_queries (level, query_text, context_id, results, created_at) VALUES ('site','check-100-days',0,0,UTC_TIMESTAMP() - INTERVAL 100 DAY), ('site','check-89-days',0,0,UTC_TIMESTAMP() - INTERVAL 89 DAY), ('site','check-yesterday',0,0,UTC_TIMESTAMP() - INTERVAL 1 DAY)"
ddev wp cron event run dovira_search_stats_purge
ddev wp db query "SELECT query_text FROM wp_dovira_search_queries WHERE query_text LIKE 'check-%' ORDER BY created_at"
```
Expected: `Executed a total of 1 cron event`, then exactly two rows,
`check-89-days` and `check-yesterday`.

**Negative check:** the 89-day row survives, so the purge never cuts inside the
90-day period.

Running the event by hand also moves its daily slot to now. That is harmless
for a purge, whose time of day does not matter.

Clean up:
```bash
ddev wp db query "DELETE FROM wp_dovira_search_queries WHERE query_text LIKE 'check-%'"
```

## 8. The retention filter, and its floor
```bash
ddev wp eval 'echo dovira\SearchStats\Purge::retention_days(), " "; add_filter( "dovira_search_stats_retention_days", fn () => 5 ); echo dovira\SearchStats\Purge::retention_days(), " "; add_filter( "dovira_search_stats_retention_days", fn () => 120, 20 ); echo dovira\SearchStats\Purge::retention_days(), "\n";'
```
Expected: `90 29 120`: the default, a filter of 5 raised to the floor, and a
longer period honoured.

**Negative check:** no filter value can make the purge keep fewer than 29 days.

## 9. The site is unaffected
```bash
curl -sk -o /dev/null -w '%{http_code}\n' https://dovira.ddev.site/
curl -sk -o /dev/null -w '%{http_code}\n' 'https://dovira.ddev.site/?s=test'
ddev wp db query "SELECT COUNT(*) AS n FROM wp_dovira_search_queries"
```
Expected: `200`, `200`, and `n` / `0`. A search does not record anything yet;
the REST route is Step 3 and the browser module is Step 4.

## Not locally verifiable
On each production install, the first request after the hand deploy must
create `{prefix}dovira_search_queries`, write `dovira_search_stats_db_version`
= `1` and register `dovira_search_stats_purge`. That also proves the
database user is allowed to create tables. If it cannot, dbDelta is retried on
every request until it can.

This is checked at the sprint-boundary deploy, over SSH on each install, after
one page load:
```bash
wp db query "DESCRIBE $(wp db prefix)dovira_search_queries"
wp option get dovira_search_stats_db_version   # 1
wp cron event list --fields=hook,recurrence | grep dovira_search_stats_purge
```
