# Verification — search-stats, Sprint 1, Step 3

**The REST route that records a search** · written at close on 2026-09-23
Branch merged: `search-stats/sprint-1-recording` → `master`

What this step promises:
- `POST dovira/v1/search-stats/record` is public and reads a JSON body only.
- A valid request writes exactly one row, with the query normalized
  (lowercase, trimmed, whitespace collapsed) and answers `204` with no body.
- Every invalid request answers `400` and writes **nothing**.
- A failed write answers `500`, never a `204`.
- Nothing records a search from the browser yet; that is Step 4.

Run everything from the repo root (`/Users/tymofii/Projects/php/dovira`) against
the local install. Every expected output below was observed on 2026-09-23 while
this guide was written. The ids used are local posts: page `12` (`Послуги`,
which carries the `services` block) and the published service `18`
(`Приймальне відділення`). On another database copy, pick a published page and
a published `service` and use their ids instead.

For brevity, set the route's URL once in your shell:
```bash
U=https://dovira.ddev.site/wp-json/dovira/v1/search-stats/record
```

## 1. The gate is green
```bash
bash bin/check.sh; echo "exit=$?"
```
Expected:
- `==> php -l (theme dovira)` with `122 files checked`;
- `==> PHPUnit (theme dovira)` with `OK (58 tests, 163 assertions)`;
- `==> check: all green` and `exit=0`.

## 2. Note where the table starts
```bash
ddev wp db query "SELECT COALESCE(MAX(id),0) AS max_id, COUNT(*) AS n FROM wp_dovira_search_queries"
```
Write down `max_id`: step 7 deletes only the rows above it. Locally it was `0`
and `0`.

## 3. The route is registered next to the theme's other routes
```bash
ddev wp eval 'foreach ( array_keys( rest_get_server()->get_routes( "dovira/v1" ) ) as $r ) echo $r, "\n";'
```
Expected, in this order:
```
/dovira/v1
/dovira/v1/telegram/handle-updates
/dovira/v1/telegram/send-test-message
/dovira/v1/telegram/reset-telegram-log
/dovira/v1/questionary/save
/dovira/v1/search-stats/record
```
`core`'s four routes are still there. They are registered in the same
`inc/rest-api.php` closure, before the new one.

## 4. One valid request per level writes one row each
```bash
curl -sk -w 'HTTP %{http_code}\n' -X POST $U -H 'Content-Type: application/json' -d '{"level":"site","query":"  Вакцинація  ","results":0}'
curl -sk -w 'HTTP %{http_code}\n' -X POST $U -H 'Content-Type: application/json' -d '{"level":"services","query":"Стерилізація   КОТА","context_id":12}'
curl -sk -w 'HTTP %{http_code}\n' -X POST $U -H 'Content-Type: application/json' -d '{"level":"service","query":"кіт","context_id":18}'
ddev wp db query "SELECT level, query_text, context_id, results, created_at, UTC_TIMESTAMP() AS utc_now FROM wp_dovira_search_queries ORDER BY id"
```
Expected:
- Three lines of `HTTP 204`, with nothing before them. A 204 has no body.
- Three rows:
  ```
  site      вакцинація         0   0
  services  стерилізація кота  12  NULL
  service   кіт                18  NULL
  ```
  - The spaces and case are gone.
  - `site` stores `0` as its context.
  - The two filters store `NULL` results.
  - `created_at` equals `utc_now` to within a second or two, so it is **UTC**,
    not the site's `+03:00`.

## 5. Every invalid request is refused, and writes nothing
**Negative checks.** Each command must answer `HTTP 400` with the code shown.
The last one must answer `404`.

A draft page stands in for an unpublished context:
```bash
D=$(ddev wp post create --post_type=page --post_status=draft --post_title='search-stats check draft' --porcelain | tr -d '\r'); echo "draft=$D"
```

| # | Command | Expected code |
|---|---|---|
| a | `curl -sk -w '\nHTTP %{http_code}\n' -X POST $U -H 'Content-Type: application/json' -d '{"level":"x","query":"кіт","results":0}'` | `search_stats_invalid_level` |
| b | `curl -sk -w '\nHTTP %{http_code}\n' -X POST $U -H 'Content-Type: application/json' -d '{"level":"services","query":"  ва  ","context_id":12}'` | `search_stats_invalid_query` (2 characters after normalization) |
| c | `curl -sk -w '\nHTTP %{http_code}\n' -X POST $U -H 'Content-Type: application/json' -d '{"level":"service","query":"кіт","context_id":12}'` | `search_stats_invalid_context` (a page, not a service) |
| d | `curl -sk -w '\nHTTP %{http_code}\n' -X POST $U -H 'Content-Type: application/json' -d "{\"level\":\"services\",\"query\":\"кіт\",\"context_id\":$D}"` | `search_stats_invalid_context` (the draft) |
| e | `curl -sk -w '\nHTTP %{http_code}\n' -X POST $U -H 'Content-Type: application/json' -d '{"level":"services","query":"кіт","context_id":12,"results":0}'` | `search_stats_invalid_results` (only `site` carries results) |
| f | `curl -sk -w '\nHTTP %{http_code}\n' -X POST $U -H 'Content-Type: application/json' -d '{"level":"site","query":"кіт"}'` | `search_stats_invalid_results` (`site` needs them) |
| g | `curl -sk -w '\nHTTP %{http_code}\n' -X POST $U -d 'level=site&query=кіт&results=0'` | `search_stats_not_json` (a form body, even with every field valid) |
| h | `curl -sk -w '\nHTTP %{http_code}\n' -X POST $U -H 'Content-Type: application/json' -d '{"level":"site","query":'` | `rest_invalid_json`: WordPress refuses it before the route's code runs |
| i | `curl -sk -w '\nHTTP %{http_code}\n' "$U?level=site&query=kit&results=0"` | `rest_no_route`, `HTTP 404`: the route takes `POST` only |

Every body is WordPress's error shape, for example
`{"code":"search_stats_invalid_level","message":"The level must be site, services or service.","data":{"status":400}}`.
The messages of `h` and `i` are WordPress's own and come back in Ukrainian,
escaped as `\u…`.

Then remove the draft and count the rows:
```bash
ddev wp post delete $D --force
ddev wp db query "SELECT COUNT(*) AS n FROM wp_dovira_search_queries"
```
Expected: `Success: Deleted post …`, then `n` equal to `max_id` rows plus the
**3** from step 4. Locally that was `3`. Nine refusals added nothing.

## 6. A failed write answers 500, not 204
Hide the table for one request, then put it back straight away:
```bash
ddev wp db query "RENAME TABLE wp_dovira_search_queries TO wp_dovira_search_queries_off"
curl -sk -w '\nHTTP %{http_code}\n' -X POST $U -H 'Content-Type: application/json' -d '{"level":"site","query":"кіт","results":1}'
ddev wp db query "SHOW TABLES LIKE 'wp_dovira_search_queries%'"
ddev wp db query "RENAME TABLE wp_dovira_search_queries_off TO wp_dovira_search_queries"
ddev wp db query "SELECT COUNT(*) AS n FROM wp_dovira_search_queries"
```
Expected:
- `{"code":"search_stats_not_recorded","message":"The search could not be recorded.","data":{"status":500}}`
  and `HTTP 500`.
  - Locally, WordPress's HTML database-error box
    (`Помилка бази даних WordPress: … doesn't exist`) is printed before the
    JSON. That is because DDEV turns `WP_DEBUG` on (`wp-config-ddev.php:42`);
    an install with `WP_DEBUG` off prints only the JSON.
- `SHOW TABLES` lists only `wp_dovira_search_queries_off`.
  - **Negative check:** the failed request did not recreate the table. The
    stored schema version is current, so `init` never runs dbDelta (Step 2).
  - This is what a production install whose database user could not create
    the table would answer.
- After the rename back, `n` is unchanged (`3` locally).

## 7. Clean up
Use the `max_id` from step 2:
```bash
ddev wp db query "DELETE FROM wp_dovira_search_queries WHERE id > 0"   # 0 = max_id
ddev wp db query "SELECT COUNT(*) AS n FROM wp_dovira_search_queries"
```
Expected: `Rows affected: 3`, then `n` / `0`, or whatever step 2 showed.

## 8. The site and core's routes are unaffected
```bash
curl -sk -o /dev/null -w '%{http_code}\n' https://dovira.ddev.site/
curl -sk -o /dev/null -w '%{http_code}\n' 'https://dovira.ddev.site/?s=test'
curl -sk -o /dev/null -w '%{http_code}\n' -X POST https://dovira.ddev.site/wp-json/dovira/v1/questionary/save -H 'Content-Type: application/json' -d '{}'
ddev wp db query "SELECT COUNT(*) AS n FROM wp_dovira_search_queries"
```
Expected:
- `200`, `200`, `400`. The questionnaire route still answers with its own
  required-field refusal, so the shared `inc/rest-api.php` did not break it.
- `n` / `0`.

**Negative check:** a server-rendered search (`?s=test`) records nothing. Only
the browser module will, from Step 4.

## Not locally verifiable
On each production install the route must answer through whatever sits in
front of `/wp-json/` there, and write into that install's own table. It is
checked at the sprint-boundary deploy by Step 4's production check: one real
search per install, with the row read back over SSH:
```bash
wp db query "SELECT level, query_text, context_id, results, created_at FROM $(wp db prefix)dovira_search_queries ORDER BY id DESC LIMIT 3"
```
A `500` there with `search_stats_not_recorded` means the table does not exist
on that install. See the Step 2 guide's "Not locally verifiable" section.
