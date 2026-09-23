# SPRINT 1 — Every search lands in the table (from 2026-09-23)

<!-- playbook: v1.21. Written by discovery (Phase C) together with every
     other sprint of the plan — never by Claude Code. Rewritten by a
     re-planning chat only while no step is closed; afterwards steps may
     only be appended. This file
     describes the sprint and nothing else: goal, fixed decisions, steps.
     The only thing written here during the sprint is the tick in a step's
     heading, by /close-step. -->
**Branch:** `search-stats/sprint-1-recording` task branches → `master` (then `master` → `kyiv`)
**Goal:** On the local install, and on both productions after the developer's
hand deploy, every search a visitor makes — in the header form, in the
`services` block filter and in a service's price-list filter — leaves exactly
one row in `{$wpdb->prefix}dovira_search_queries` with its level, normalized
text, the page or service it belongs to and, for the header search, how many
results the page showed; a crawler fetching `?s=` leaves none, a query typed
letter by letter leaves one, and rows older than the retention period
disappear on their own. The gate (`bin/check.sh`) runs the theme's own unit
tests and every REST refusal is covered by one. Nothing changes in the
Telegram message yet.

## Fixed decisions
- DECISIONS "A theme feature that records site search, plugged into the daily report through a new plugin filter" (2026-09-22): the feature lives in the theme under `inc/features/search-stats/`; the shared files it touches are named there and each is a plan task marked "touches shared code".
- DECISIONS "Search events live in a custom table with a retention purge" (2026-09-22): one table, `dbDelta` from a schema version option, purge from cron, retention filterable (default 90, floor 29), no personal data.
- DECISIONS "The theme gets PHPUnit + Brain\Monkey, run by the check command" (2026-09-22): the two dev dependencies are approved; no PHPCS/PHPStan for the theme.
- DECISIONS "Every search is recorded from the browser through one public REST route" (2026-09-22): route, body, validation rules, pause of 1500 ms, ≥3 characters, once per value per input, `search.php` data attributes, `204`/`400`.
- Root `CLAUDE.md` domain invariant 8 (public `dovira/v1` routes validate before writing) and the theme `CLAUDE.md` (REST controllers extend `WP_REST_Controller`, registered in `inc/rest-api.php`; features register through one line in `functions.php`).

## Steps
<!-- Ordered by dependency. One step = one /plan-step → /do-step → /close-step
     cycle and must fit one working session. Every step has all five
     subsections, even if a subsection is "—". The checkbox in the heading is
     ticked by /close-step. -->

### [x] Step 1 — Delta-audit and the theme's test suite in the gate
- **Tasks:**
  - Delta-audit: read `docs/features/core/FEATURE.md`, `docs/features/ga-telegram-bridge/FEATURE.md` and the shared code this feature will touch (`search.php`, the two JS filter modules, `app.js`, `inc/rest-api.php`, `functions.php`); confirm the plan conflicts with nothing, list the touchpoints in the step report (no code change from the audit itself).
  - Add `phpunit/phpunit ^12.5` and `brain/monkey ^2.7` to `require-dev` of the theme's `composer.json` (**touches shared code — may affect other features:** `composer.json` of the theme; consumers: none at runtime, dev tooling only); `tests/bootstrap.php` (Composer autoload, Brain\Monkey setup, no WordPress bootstrap), `tests/TestCase.php`, `phpunit.xml.dist` with `failOnRisky="true"` and the suite under `tests/Unit/`; one smoke test.
  - `bin/check.sh` (**touches shared code:** the project gate; consumers: every commit of every feature) gains a fifth stage `PHPUnit (theme dovira)` after `php -l`, running `composer install` in the theme when `vendor/` is missing, exactly as it does for the plugin.
  - The theme's `.gitignore` keeps `vendor/` and `composer.lock` out, per its `CLAUDE.md`; `.phpunit.cache/` is ignored too.
- **Tests:** the smoke test proves the bootstrap (a Brain\Monkey stub of one WordPress function is callable); `bin/check.sh` exits 0 with the new stage printed.
- **Verification (manual):** run `bin/check.sh` from a clean checkout of the theme's `vendor/`: five stages print, the last three lines are the theme's PHPUnit summary and `check: all green`; delete the smoke test's assertion and see the gate fail on the risky test.
- **Docs to update:** `docs/TECH-STACK.md` → Check command (five stages) and Approved dependencies log (two rows for the theme); `docs/TESTING.md` → How to run (the theme suite), Fixtures, Rules; the theme `CLAUDE.md` → Local commands (`composer test`).
- **Depends on:** —

### [ ] Step 2 — Feature bootstrap, the table and the purge
- **Tasks:**
  - `inc/features/search-stats/bootstrap.php` (requires the feature's classes, registers hooks) and one line in `functions.php` (**touches shared code:** `functions.php`; consumers: every theme feature).
  - `Schema` class: the `CREATE TABLE` for `{$wpdb->prefix}dovira_search_queries` (`id` BIGINT UNSIGNED AUTO_INCREMENT, `level` VARCHAR(16), `query_text` VARCHAR(100), `context_id` BIGINT UNSIGNED NOT NULL DEFAULT 0, `results` INT UNSIGNED NULL, `created_at` DATETIME NOT NULL; keys on (`level`, `created_at`) and (`created_at`)), applied through `dbDelta()` on `after_switch_theme` and on `init` when `dovira_search_stats_db_version` is behind the class constant — never on every request.
  - `Purge` class: `DELETE … WHERE created_at < now − retention` on the daily cron hook `dovira_search_stats_purge`, registered on `init` when not scheduled; retention = `apply_filters( 'dovira_search_stats_retention_days', 90 )`, clamped to ≥ 29 in the class — the number 90 appears once, as a named constant.
- **Tests:** `SchemaTest` (the SQL `dbDelta` receives: table name from `$wpdb->prefix`, every column and key; the version option is written after a successful run and not touched when current), `PurgeTest` (the SQL built for a given clock and retention; a filter returning 5 is clamped to 29; the event is scheduled once).
- **Verification (manual):** on the local install `ddev wp db query "DESCRIBE wp_dovira_search_queries"` lists the six columns; `ddev wp option get dovira_search_stats_db_version` prints the version; `ddev wp cron event list` shows `dovira_search_stats_purge`; insert a row dated 100 days ago and one dated yesterday, run `ddev wp cron event run dovira_search_stats_purge` — only the old one is gone.
- **Docs to update:** `docs/DATA-MODEL.md` → new section for the table, the option and the hook (with the manual `DROP TABLE` note); `docs/ARCHITECTURE.md` → Modules (one row for the feature) — the Feature map row already exists.
- **Depends on:** Step 1

### [ ] Step 3 — The REST route that records a search
- **Tasks:**
  - `Normalizer` class (`mb_strtolower`, trim, collapse whitespace) — the one place text is normalized.
  - `RecordController extends WP_REST_Controller`, registered in `inc/rest-api.php` (**touches shared code:** `inc/rest-api.php`; consumers: `core`'s Telegram and Questionary routes) as `POST dovira/v1/search-stats/record`, `permission_callback` `__return_true`, JSON body only; validation per the fixed decision: `level` ∈ {`site`, `services`, `service`}; normalized `query` 1–100 characters for `site`, 3–100 otherwise; `context_id` a published post for `services` and `service` (any post type for `services`, `service` post for `service`), ignored and stored as 0 for `site`; `results` a non-negative integer, required for `site`, refused when present otherwise.
  - `Repository::insert()` — one `$wpdb->insert()` with `created_at` = `current_time( 'mysql', true )`; the response is `204` with no body, or `400` `{code, message}` before any write.
- **Tests:** `RecordControllerTest` — test-critical zone: one test per refusal (bad level, short query, long query, empty after normalization, missing/unknown/unpublished `context_id`, wrong post type for `service`, missing `results` for `site`, `results` present for a JS level, negative `results`, non-JSON body) asserting no insert; the success path per level asserting the exact row; `NormalizerTest` (case, edges, inner whitespace, non-Latin).
- **Verification (manual):** `curl -X POST https://dovira.ddev.site/wp-json/dovira/v1/search-stats/record -H 'Content-Type: application/json' -d '{"level":"site","query":"  Вакцинація  ","results":0}'` returns 204 and one row `site | вакцинація | 0 | 0`; the same with `"level":"x"` returns 400 and no row.
- **Docs to update:** `docs/ARCHITECTURE.md` → Modules row of the feature (the route) and the diagram line `dovira/v1 (Telegram, Questionary, search-stats)`; `docs/DATA-MODEL.md` → Relations (`context_id` → `wp_posts.ID`, not enforced).
- **Depends on:** Step 2

### [ ] Step 4 — Recording from the browser on all three levels
- **Tasks:**
  - `source/scripts/features/search-stats/record.js`: `recordSearch(level, query, contextId, results)` posting JSON with `navigator.sendBeacon` (a `Blob` of type `application/json`) and `fetch(…, {keepalive: true})` as fallback; a `debouncedRecorder(input, level, contextId)` helper with the 1500 ms pause (named constant), the ≥ 3-character rule and "never the same value twice from this input in this page view".
  - `source/scripts/modules/services-search.js` and `source/scripts/modules/init-service-price-lists.js` (**touches shared code:** the two filters; consumers: the `services` block and `single-service.php`) call the helper; the context id comes from a `data-search-stats-context` attribute printed next to each input (`get_the_ID()` of the hosting page / the service) — `search.php` (**touches shared code:** consumers: the header form) prints `data-search-stats-query` and `data-search-stats-results` on the results section, computed from the three result sets the template already builds; `app.js` (**touches shared code**) imports the module and calls `recordSearch` once when those attributes are present.
  - `npm run build`; the committed `assets/` are rebuilt.
- **Tests:** — (no JS test runner in the project; the route's tests cover the server side). The PHP that prints the attributes gets a test on the results-count helper extracted from `search.php` (`ResultsCountTest`).
- **Verification (manual):** on the local install, with the Network tab open: type `вакцинація` in the header form and submit — one request to `…/record` with `level: site` and the page's results count, one row; on the services page type `вак`, `вакц`, `вакцин` without pausing — one request after the pause, `level: services`, `context_id` = the page id; on a service page type `кіт`, wait, then delete and retype `кіт` — one request only; `curl 'https://dovira.ddev.site/?s=test'` — no request, no row. Repeat once on `kyiv.dovira.vet` after the hand deploy: the rows carry that install's prefix and post ids.
- **Docs to update:** `docs/ARCHITECTURE.md` → Data flows (new flow "Site search → statistics row"); `docs/DOMAIN.md` → the terms were added by discovery, confirm they match the code; `docs/TECH-STACK.md` → ANTI-PATTERNS gains "do not record a search server-side in `search.php` — the browser module is the one recorder".
- **Depends on:** Step 3
