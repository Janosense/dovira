# Verification — search-stats, Sprint 2, Step 2

**Aggregation: top-5 per level for yesterday and for 28 days** · written at close on 2026-09-23
Branch merged: `search-stats/sprint-2-report` → `master`

What this step promises:
- **Two periods in the site's zone.** "Yesterday" and "the last 28 days" are
  whole calendar days in `wp_timezone()`. They end at today's local midnight
  and are turned into UTC bounds `[from, to)`: the first second is included
  and the last is not.
- **The top five.** For each of the three levels and each period, the five
  most frequent queries come back, with the most frequent first. Between
  equal counts, the query searched most recently comes first.
- **Nothing found.** A query is flagged only when every search of it in the
  period found nothing. One search that found something clears the flag.
  The two filter levels are never flagged.
- **Nothing stored and nothing wired.** Every call reads the table again.
  Nothing calls this code until Step 3 hooks it into the report, so the
  site and the morning message are unchanged.

Run the commands from the repo root (`/Users/tymofii/Projects/php/dovira`).
Every expected output below was observed on 2026-09-23 on the local install
while this guide was written. The seeded rows were then deleted.

## 1. The gate is green
```bash
bash bin/check.sh; echo "exit=$?"
```
Expected:
- `OK (309 tests, 1103 assertions)` for the plugin;
- `131 files checked`;
- `OK (83 tests, 212 assertions)` for the theme (63 before this step);
- `==> check: all green` and `exit=0`.

## 2. The step's own tests
```bash
(cd wp-content/themes/dovira/tests && vendor/bin/phpunit --testdox --filter '/(PeriodsTest|RepositoryTopTest|StatsTest)::/')
```
Expected: `OK (20 tests, 44 assertions)`. Among them:
- **Periods.** The two periods for seven Europe/Kyiv clocks:
  - both sides of local midnight;
  - the spring night, when yesterday is 23 hours;
  - the autumn night, when yesterday is 25 hours;
  - 28 days across each clock change.

  A fixed offset has no clock change.
- **Repository Top.** The query for each level and period, and the
  nothing-found rule.
- **Stats.** Six queries in order with the clock's bounds, and six empty
  lists for an empty table.

## 3. The zone and the table before seeding
```bash
ddev wp eval 'echo wp_timezone()->getName(), "\n";'
ddev wp db query "SELECT COUNT(*) FROM wp_dovira_search_queries" --skip-column-names
```
Expected:
- `+03:00`: a bare offset, because the site's Settings → General names no
  city. If your install prints something else, replace every `'+03:00'` in
  §4 with that value.
- `0`. Other rows in the table would take places in the top five and change
  the lists in §5.

## 4. Seed rows around the period edges
All times are relative to **today's local midnight** (`@t`, in UTC). SQL
computes it from the database's own clock, independently of the code under
test. Every text starts with `zz-verify` so §7 can delete exactly these rows.
```bash
ddev wp db query "SET @t = CONVERT_TZ( DATE( CONVERT_TZ( UTC_TIMESTAMP(), '+00:00', '+03:00' ) ), '+03:00', '+00:00' );
INSERT INTO wp_dovira_search_queries (level, query_text, context_id, results, created_at) VALUES
('site', 'zz-verify вакцинація', 0, 5, @t - INTERVAL 20 HOUR),
('site', 'zz-verify вакцинація', 0, 5, @t - INTERVAL 19 HOUR),
('site', 'zz-verify вакцинація', 0, 5, @t - INTERVAL 18 HOUR),
('site', 'zz-verify рентген', 0, 3, @t - INTERVAL 17 HOUR),
('site', 'zz-verify рентген', 0, 0, @t - INTERVAL 16 HOUR),
('site', 'zz-verify груминг', 0, 0, @t - INTERVAL 15 HOUR),
('site', 'zz-verify груминг', 0, 0, @t - INTERVAL 14 HOUR),
('services', 'zz-verify кастрація', 12, NULL, @t - INTERVAL 13 HOUR),
('service', 'zz-verify узі', 18, NULL, @t - INTERVAL 12 HOUR),
('site', 'zz-verify межа', 0, 1, @t - INTERVAL 1 DAY),
('site', 'zz-verify північ', 0, 1, @t),
('site', 'zz-verify тиждень тому', 0, 4, @t - INTERVAL 6 DAY),
('site', 'zz-verify старий', 0, 4, @t - INTERVAL 30 DAY);"
ddev wp db query "SELECT COUNT(*) FROM wp_dovira_search_queries WHERE query_text LIKE 'zz-verify%'" --skip-column-names
```
Expected: `Success: Query succeeded.`, then `13`.

What each row is for:
- **`вакцинація` ×3, results 5.** The most frequent query, found each time.
- **`рентген`, results 3 then 0.** Found something once, so it is **not**
  flagged.
- **`груминг`, results 0 and 0.** Found nothing each time, so it is flagged.
  It has the same count as `рентген` but was searched later, so it comes
  first.
- **`кастрація` (page 12) and `узі` (service 18).** The two filter levels,
  with no results count and never flagged.
- **`межа`, at yesterday's local midnight.** The first second of yesterday,
  which is included.
- **`північ`, at today's local midnight.** The first second of today, which
  is not yesterday.
- **`тиждень тому`, 6 days back.** In the 28 days, not yesterday.
- **`старий`, 30 days back.** Outside both periods.

## 5. The six lists
This reads the table and sends nothing:
```bash
ddev wp eval 'foreach ( get_object_vars( dovira\SearchStats\Stats::build() ) as $list => $rows ) { echo $list, ":", "\n"; foreach ( $rows as $top ) { printf( "  %s (context %d) x%d%s\n", $top->query, $top->context_id, $top->count, $top->nothing_found ? " · nothing found" : "" ); } }'
```
Expected, exactly:
```
site_yesterday:
  zz-verify вакцинація (context 0) x3
  zz-verify груминг (context 0) x2 · nothing found
  zz-verify рентген (context 0) x2
  zz-verify межа (context 0) x1
site_28_days:
  zz-verify вакцинація (context 0) x3
  zz-verify груминг (context 0) x2 · nothing found
  zz-verify рентген (context 0) x2
  zz-verify межа (context 0) x1
  zz-verify тиждень тому (context 0) x1
services_yesterday:
  zz-verify кастрація (context 12) x1
services_28_days:
  zz-verify кастрація (context 12) x1
service_yesterday:
  zz-verify узі (context 18) x1
service_28_days:
  zz-verify узі (context 18) x1
```
Read it for the **negative checks**:
- `старий` is in **no** list: 30 days is outside the 28.
- `північ` is in **no** list: today is neither yesterday nor part of the 28
  days.
- `тиждень тому` is **not** in yesterday.
- `рентген` carries **no** marker, although one of its searches found
  nothing.
- The filter rows carry **no** marker.

## 6. Nothing is stored, and nothing calls it yet
```bash
ddev wp option list --search='dovira_search_stats*' --fields=option_name,option_value,autoload
grep -rln 'Stats::build' wp-content/themes/dovira/inc wp-content/themes/dovira/*.php
```
Expected:
- The only option is the Sprint 1 schema version, `dovira_search_stats_db_version	1	on`.
  The build left no cache behind.
- The only file is `wp-content/themes/dovira/inc/features/search-stats/SearchStats.php`,
  where `Stats::build` appears in a docblock. No template, hook or
  `bootstrap.php` line calls it. The plugin's *Preview* is therefore
  unchanged by this step.

## 7. Delete the seeded rows
```bash
ddev wp db query "DELETE FROM wp_dovira_search_queries WHERE query_text LIKE 'zz-verify%'"
ddev wp db query "SELECT COUNT(*) FROM wp_dovira_search_queries" --skip-column-names
```
Expected: `Rows affected: 13`, then `0`.

## Not locally verifiable
n/a. Nothing on production changes until Step 3 hooks the lists into the
report. Which key MariaDB uses for the query on production data can be read
then with `EXPLAIN`; on the empty local table it chose `created_at`
(docs/DATA-MODEL.md).
