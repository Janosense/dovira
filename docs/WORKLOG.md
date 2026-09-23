# Worklog — Dovira

<!-- Add-only project memory for the agent: entries are never edited or
     removed. Written ONLY by /close-step, /fix-step (a fix after a step's
     close) and /adhoc (off-cycle tasks), newest entry at the TOP, directly
     under the entry format. A fresh Claude Code session reads the latest 5
     entries at start (CLAUDE.md core rule 6).
     Keep entries 3–6 lines; this is a memory index, not a diary — details live
     in commits and verification guides. -->

Entry format:

## {{YYYY-MM-DD}} — [{{feature}}] Sprint {{N}} Step {{M}} — {{title}}
(ad-hoc tasks: `## {{YYYY-MM-DD}} — [adhoc] [{{feature}}] — {{title}}`;
a fix after a step's close:
`## {{YYYY-MM-DD}} — [{{feature}}] Sprint {{N}} Step {{M}} — fix: {{what failed}}`;
the last step of a sprint ends its entry with `Sprint {{N}} complete — all
steps closed, {{sprint branch}} merged into main`)
- Changed: {{what, at module/feature level}}
- Decisions: {{key ones made or DECISIONS.md entries added, or "—"}}
- Open: {{unresolved questions carried forward, or "—"}}

---

## 2026-09-23 — [city-popup] Sprint 1 Step 1 — Delta-audit and Vitest in the gate
- Changed:
  - The theme has a Vitest suite. vitest `^3.2` (3.2.7, on the theme's Vite 5.4.19) is in `package.json` `devDependencies` with `npm test`.
  - `vitest.config.js`: `node` environment, `tests/js/**/*.test.js`, the `@scripts` alias, and `expect.requireAssertions`, so a test that asserts nothing fails.
  - `tests/js/smoke.test.js` imports `core`'s `toggle-cities.js` through the alias.
  - `bin/check.sh` has six stages: Vitest runs after the theme's PHPUnit. Before any stage it stops without `node`/`npm`, runs `npm install` when `node_modules/` is missing, and checks that Vite loads on this OS.
  - `npm run build` rebuilds `assets/` byte-identical. No site code changed.
- Shared code:
  - The theme's `package.json`: the Vite build of `core` and `search-stats` still builds.
  - `bin/check.sh`: it gates every feature's commits and must now run on the host. `ddev exec bash bin/check.sh` stops before stage 1 while `node_modules/` is the host's darwin install.
- Decisions:
  - New DECISIONS entry "The gate runs on the host; before stage 1 it checks that Vite loads on this OS". It is plan Question 1, resolved A. It supersedes the Vitest entry's "(the DDEV web container has them)".
  - Settled in the plan: `requireAssertions` for TESTING's asserts-nothing rule, and the smoke test at the `tests/js/` root.
- Open:
  - **For Step 2's plan:** the Polylang strings `kharkiv` / `kyiv` are untranslated (uk and ru return the key). The city names live in the `Dovira: Cities` strings (`Харків` → `Харьков`, `Київ` → `Киев`), while Step 2 says its buttons reuse `kharkiv` / `kyiv`.
  - **For Step 3:** `app.js` awaits its 14 imports in one IIFE with no `try`/`catch`, so one throw stops every module after it. The existing `data-city` sits on `city-toggle` buttons and `li`s (employees, vacancies blocks), so the switcher's selector must be `a[data-city]`.
  - TECH-STACK → Check command still says "111 files today" for `php -l`; the gate counts 134.

## 2026-09-23 — [adhoc] — Playbook v1.17 → v1.25
- Changed: `.claude/commands/` (`plan-step`, `do-step`, `close-step`, `fix-step`, `adhoc`) and `templates/*.md` copied from the playbook at v1.25. A step is now verified on its task branch before `/close-step`, and its state reads `awaiting verification`. In root `CLAUDE.md`, Core rules and Step protocol are word for word from the template and the header says v1.25. The Documentation table names CONVENTIONS and drops the `SPRINT-N-CLOSE.md` and `LEARNINGS.md` rows, and the Git model ends with the template's deploy sentence. `docs/TECH-STACK.md` gets the new ANTI-PATTERNS comment and a CONVENTIONS section (`none yet`). The header comments and entry formats of WORKLOG, LEARNINGS and DECISIONS, and `docs/features/README.md`, now come from the playbook. No application code changed.
- Unchanged on purpose: `DATA-MODEL.md`, whose playbook comment already matched; its extra Adoption note is project text. No `SPRINT-*-PLAN.md` said "implemented, awaiting close", and `close-sprint.md` was already gone.
- Decisions: —
- Open: the existing LEARNINGS entries keep the old format (Incident / Root cause / Transferred to playbook) under the new playbook-only header, and the retro decides their fate. Root `CLAUDE.md` says `main` in the template sentence and `master` in the Deploy and Git-model slots, since local `master` tracks `origin/main`. search-stats `FEATURE.md` and `SPRINT-1/2.md` still carry `playbook: v1.21` markers.

## 2026-09-23 — [search-stats] Sprint 2 Step 3 — The three blocks in the message
- Changed: the morning report now carries the search blocks.
  - `Renderer::blocks()` writes FEATURE.md → UI: up to three blocks, `—` for an empty list, no block for an empty level. Queries are cut at 40 characters plus `…`. Titles come from the Ukrainian post through `pll_get_post()`, or `(видалено)`. Counts are plain digits.
  - `bootstrap.php` hooks `Renderer::add_to()` to the plugin's `gatb_extra_blocks`, only when `GaTelegramBridge\Plugin` is loaded.
  - Theme tests 83 → 92. 134 files linted.
- Shared code: none changed. The theme now consumes the plugin's public filter, and the plugin itself is untouched.
- Decisions:
  - DECISIONS "Values in a Telegram message are escaped with double encoding, never with `esc_html()`", plus a TECH-STACK ANTI-PATTERNS line and a LEARNINGS entry. The step text said `esc_html`, but WordPress keeps `&nbsp;`/`&copy;`, and Telegram refuses them. One public search could have made the report unsendable, and Brain\Monkey's stub would have hidden it.
  - Settled in the plan: the 40-character cut; stored titles are decoded before escaping; `BootstrapFilterTest` tests the callback, since `bootstrap.php` is never loaded in tests.
- Verified locally with seeded rows, then deleted:
  - the real message, rendered from the shell, has the three blocks between the devices block and the link;
  - the Russian page 1630 prints `Послуги`, and a missing post prints `(видалено)`;
  - `&nbsp; <b> &copy;` prints as text, and the 40-character cut is exact;
  - *Preview* was read element-only, with no screenshot;
  - an empty table leaves the plugin's message alone;
  - with the plugin skipped, the theme hooks and loads nothing.

  *Send now* was not pressed.
- Open (the sprint boundary is the developer's):
  - Hand deploy `master` → Kharkiv, merge `master` → `kyiv` → Kyiv. That carries theme and plugin 0.3.0 and the Sprint 1 checks (the table is created, one real search per level). Then the first morning report on each install.
  - Rotate the local service-account key.
  - Decide whether to name the site zone "Київ".
  - `/adhoc` candidates: the plugin still escapes GA labels and page titles with `esc_html()`; `search.php:78` reflected XSS; `wp is not defined`.
  - 20 LEARNINGS entries are `pending` for the retro, four of them from this sprint.

Sprint 2 complete — all steps closed, search-stats/sprint-2-report merged into main (`master`, simple git model)

## 2026-09-23 — [search-stats] Sprint 2 Step 2 — Aggregation: top-5 per level for yesterday and for 28 days
- Changed: the reading side of the daily report, in `inc/features/search-stats/`. Nothing calls it yet; Step 3 hooks it into `gatb_extra_blocks`.
  - `Periods` turns a clock into yesterday and the 28 calendar days ending with it. The bounds are half-open UTC edges of whole days in `wp_timezone()`, so a clock-change night is 23 or 25 h.
  - `Repository::top()` runs one query per level and period: `GROUP BY query_text, context_id`, ordered by count, then latest search, `LIMIT 5`. It returns `TopQuery` objects; `nothing_found` = `MAX(results) = 0`.
  - `Stats::build()` fills six lists in `SearchStats`, with no cache.
  - Theme tests 63 → 83. 131 files linted.
- Shared code: `tests/WpdbDouble.php` gains `get_results()`, answering from a `$results` queue and recording `$selected`. Only `search-stats` tests use it, and nothing existing changed. No template, JS or `core` file changed.
- Decisions: none new in DECISIONS.md. Settled in the plan:
  - the level names come from `RecordController`'s constants;
  - "28 days" is the 28 calendar days ending yesterday, as the plugin counts them;
  - readonly promoted properties, but not readonly classes, since production runs PHP ≥ 8.1 because of the plugin.
- Verified locally with 13 seeded rows placed around the period edges (seeded, then deleted):
  - a row at yesterday's local midnight is in, and one at today's local midnight is out;
  - rows from 30 days back and from today are in no list;
  - on a tie, the newer search comes first;
  - results 3 then 0 is not flagged, and 0 then 0 is.
- Open:
  - The site's zone has no name: `gmt_offset` 3, no `timezone_string`, locally and in the committed `mysql.sql`. `wp_timezone()` is therefore `+03:00` with no DST, so in winter "yesterday" would run 23:00–23:00 Kyiv time. Naming the city in Settings → General on both installs is the developer's call; it also moves the plugin's send time.
  - The collation `utf8mb4_unicode_520_ci` groups `ґ`/`г` and `ё`/`е` as one query; this is documented, not changed.
  - The plan's claim about `level_created_at` was not checked with `EXPLAIN`. On the empty local table MariaDB chose `created_at` (LEARNINGS 2026-09-23). Read it with Step 3 on real data.

## 2026-09-23 — [search-stats] Sprint 2 Step 1 — Plugin 0.3.0: the `gatb_extra_blocks` filter and the length guard
- Changed:
  - The plugin `ga-telegram-bridge` is **0.3.0**.
  - `MessageRenderer` applies `gatb_extra_blocks` (`array()`, `Report`). Added HTML blocks are printed after the plugin's own and before the GA link, untouched. A non-array return is ignored, and non-string or blank entries are dropped.
  - `compose()` drops the last added block while the text as Telegram counts it is over `TelegramClient::MAX_TEXT_LENGTH` (4096), and returns how many it dropped; `render()` is its HTML. The count is UTF-16 units, with tags stripped and entities decoded, measured before `gatb_message_html`.
  - `Runner` adds one plural sentence to the log line of a sent or refused run that lost blocks. `.pot` 131 → 132 entries, uk `.po`/`.mo` rebuilt.
  - Readme: 0.3.0 changelog and FAQ, ASCII only. Tests 297 → 309.
- Shared code:
  - Only the plugin, whose public surface gains its third filter. Consumers: `ga-telegram-bridge` itself, and `search-stats` from Step 3.
  - With nothing hooked, the message is byte-for-byte 0.2.0's. No theme file changed.
- Decisions: none new in DECISIONS.md. Settled in the plan:
  - UTF-16 units, which never undercount what Telegram shows;
  - measure before `gatb_message_html`;
  - the count travels out through `compose()`, not through state;
  - the note goes on refused runs too;
  - the unreleased GA link from the 2026-09-16 adhoc goes into the 0.3.0 changelog.

  Settled in the tasks, from the gate: a filter's return goes through a helper typed `mixed` (`as_blocks()`, like `as_report()`), because phpstan-wordpress takes the hook docblock as the return type.
- Verified locally:
  - live render and *Preview*: the probe blocks sit between the devices block and the link, and a 5 000-character block is dropped (`dropped=1`);
  - the three uk plural forms;
  - the settings screen shows 0.3.0.

  *Send now* was not pressed: it posts to the configured chat, so it is §7 of the guide.
- Open:
  - **Rotate the local install's service-account key.** A whole-page screenshot of Settings captured part of it (LEARNINGS 2026-09-23, a repeat of 2026-09-10).
  - Preview shows `&amp;` as `&`, because `esc_html()` does not double-encode. This predates the step; candidate for `/adhoc`.
  - The `.pot` header still says 0.2.0.
  - Both productions get 0.3.0 at the sprint-boundary deploy.

## 2026-09-23 — [search-stats] Sprint 1 Step 4 — Recording from the browser on all three levels
- Changed:
  - All three searches are now recorded from the browser by `source/scripts/features/search-stats/record.js`, through Step 3's route.
  - Header search: `search.php` prints the query and `ResultsCount::shown()`, which counts what the page lists: price rows, top-level services, posts, pages, employees. `recordSiteSearch()`, called by `app.js`, sends it once per results page, and nothing for an empty query.
  - The two filters: `debouncedRecorder()` sends after 1500 ms of rest or on blur, at least 3 characters, never the value that input sent last. The context comes from `data-search-stats-context` on the input.
  - Chrome sends through `sendBeacon`; `fetch(keepalive)` is the fallback.
  - `assets/` was rebuilt twice, and a fresh build reproduces it byte for byte.
  - Theme tests: 58 → 63. 124 files linted.
- Shared code, all `core`, each change additive, with the existing lines unchanged:
  - `search.php` gains two attributes on its results section;
  - `app.js` gains one import and one call;
  - the `services` block template and `single-service.php` gain one attribute each;
  - `services-search.js` and `init-service-price-lists.js` gain one import and one call each.
- Decisions:
  - DECISIONS "Each filter's input carries the id of the post it searches in" amends the old "templates need nothing" clause. That was plan Question 1, approved as recommended.
  - Resolved in the plan by precedence: repeats are compared with the **last** value sent (DECISIONS), not with every value; FEATURE.md was reworded to match.
  - TECH-STACK ANTI-PATTERNS: no PHP-side recording.
- Verified locally in Chrome:
  - one row per search on each level, with contexts 12 (page) and 18 (service), and the Russian page printing 1630;
  - blur sends at once and cancels the running pause;
  - a retyped value, the reset button and an empty `?s=` send nothing;
  - `curl` of results pages leaves no row;
  - the filters still filter.
- Open:
  - The production check is repeated on each install after the sprint-boundary hand deploy, with the test rows deleted afterwards.
  - Seen, not caused here: every page logs `wp is not defined`. `starter_theme_defer_scripts()` defers `wp-i18n`, whose inline `-after` script then runs first. Candidate for `/adhoc`, as is `search.php:78`.

## 2026-09-23 — [search-stats] Sprint 1 Step 3 — The REST route that records a search
- Changed:
  - `POST dovira/v1/search-stats/record` is live: public, reads the JSON body only, and is the only writer of `{prefix}dovira_search_queries`.
  - `RecordController` checks everything before its one write: `level`; the query after normalization (1–100 characters for `site`, 3–100 for the filters); for the two filters, a `context_id` that is a published post (a `service` post for `service`); `results` required for `site` and refused on the other levels.
  - Answers: `204` with no body, `400` `WP_Error` with no row, `500` when the insert fails.
  - `Normalizer` is the one place text is normalized. `Repository::insert()` stores `created_at` in UTC.
  - Theme tests: 15 → 58. 122 files linted.
- Shared code:
  - `inc/rest-api.php` gains the controller at the end of the `rest_api_init` closure. `core`'s Telegram and Questionary routes are unchanged and still answer.
  - The theme test harness: `tests/bootstrap.php` now loads five WordPress REST classes from the committed core, and `WpdbDouble` records `insert()`.
- Decisions: none new in DECISIONS.md. Settled in the plan:
  - a failed insert answers 500, as the Questionary route does;
  - `context_id` and `results` must be JSON integers;
  - a `results` key sent with a filter level is refused, even when it is `null`;
  - `context_id` must be above 0 before `get_post_status()` is called, because `get_post( 0 )` falls back to the global post.
  - FEATURE.md → Interfaces now names the error body and the 500.
- Verified on the local install:
  - one row per level, stored in UTC;
  - nine refusals (bad level, short query, page as a service, draft, stray or missing results, form body, malformed JSON, GET), none of which wrote a row;
  - a 500 with the table renamed away, which did not recreate it.
- Open:
  - Step 4 must send `context_id` and `results` as numbers, not strings.
  - Whether production answers through whatever sits in front of `/wp-json/` is checked with Step 4's production search at the sprint-boundary deploy.
  - `search.php:78` (the reflected XSS) is still waiting for an `/adhoc`.

## 2026-09-23 — [search-stats] Sprint 1 Step 2 — Feature bootstrap, the table and the purge
- Changed:
  - The feature is registered: one line in `functions.php`, plus `inc/features/search-stats/bootstrap.php`.
  - `Schema` creates `{prefix}dovira_search_queries` (six columns, keys `level_created_at` and `created_at`) with `dbDelta()`. It runs on `after_switch_theme`, and on `init` only while the autoloaded option `dovira_search_stats_db_version` is behind `VERSION = 1`. The version is written only when dbDelta leaves no error.
  - `Purge` registers the daily `dovira_search_stats_purge` event on `init` when it is absent, and deletes rows whose `created_at` (UTC) is older than `apply_filters( 'dovira_search_stats_retention_days', 90 )` days, never fewer than 29.
  - Theme tests: 1 → 15. 117 files linted.
- Shared code:
  - `functions.php` (one feature line, loaded by `core` and `search-stats` on every request).
  - `tests/bootstrap.php` (defines `DAY_IN_SECONDS`).
  - A new shared test double, `tests/WpdbDouble.php`.
  - No `core` template, route or data changed.
- Decisions: none new in DECISIONS.md. Settled in the plan:
  - The purge hook takes 0 arguments, because `do_action()` without arguments passes `''` to `?int $now`.
  - Tests load the code under test by path, never through a new PSR-4 entry, because the gate would not refresh an existing `tests/vendor/` autoloader (TESTING.md → Rules).
  - `level` and `query_text` are `NOT NULL`.
- Verified on the local install from a dropped table:
  - one page load created the table, the version (autoload `on`) and the event;
  - a second dbDelta is a no-op;
  - the purge removed a 100-day row and kept an 89-day one;
  - the retention reads `90 29 120` for no filter, a filter of 5 and a filter of 120.
- Open: whether both productions create the table on the first request after the hand deploy (the database user needs CREATE), checked at the sprint-boundary deploy. Nothing writes rows until Step 3's route.

## 2026-09-23 — [search-stats] Sprint 1 Step 1 — Delta-audit and the theme's test suite in the gate
- Changed:
  - The theme has a unit suite in `wp-content/themes/dovira/tests/`, its own dev-only Composer project: PHPUnit 12.5.35, Brain\Monkey 2.7.0, platform PHP 8.3, `failOnRisky`, and one smoke test. Its `vendor/` is gitignored and its lock uncommitted.
  - `bin/check.sh` gains stage 5, `PHPUnit (theme dovira)`, and installs that tooling when `tests/vendor/` is missing.
  - Green on the host and in DDEV: 297 plugin tests, 111 theme files linted, 1 theme test. It fails when the smoke test's assertion is removed.
  - The audit's list of touchpoints for Steps 2–4 is in `SPRINT-1-PLAN.md`.
- Shared code: `bin/check.sh` (gates every commit of `core`, `ga-telegram-bridge`, `search-stats`), the theme `.gitignore`, and a new theme-wide `tests/` directory, where later theme features' tests go too. The theme's `composer.json` and its committed `vendor/` are untouched. Nothing the site loads changed.
- Decisions:
  - DECISIONS "The theme's test tooling is its own Composer project in `tests/`" amends one clause of the 2026-09-22 entry. The step text assumed the theme's `vendor/` was gitignored, but it is committed and loaded on every request, so dev packages in the theme's `composer.json` would ship or break every page.
  - Chosen at `/plan-step` as recommended.
  - TECH-STACK ANTI-PATTERNS gained the matching line, and LEARNINGS an entry.
- Open:
  - `search.php:78` echoes the search query unescaped (reflected XSS, confirmed locally). It goes to `/adhoc`, not this sprint.
  - Step 4 must count only the results `search.php` actually shows.
  - Whether the hand deploy leaves `tests/vendor/` behind is checked at the sprint-boundary deploy.

## 2026-09-16 — [adhoc] [ga-telegram-bridge] — The report ends with a link into the GA4 property
- Changed: `MessageRenderer` closes every report, after its last block and one blank line, with `🔗 <a href="https://analytics.google.com/analytics/web/#/p{property_id}/reports/intelligenthome">Детальніше в Google Analytics</a>`; the failure notice has none. The property id is URL-encoded as `GaClient` encodes it, which is what makes the escaping real — the `esc_url` test stub escapes neither quotes nor brackets. One new string (`.pot` 130 → 131), a readme FAQ entry on who can open the link, and 294 → 297 tests.
- Shared code: none of the theme, no gate config. `MessageRenderer` now reads one setting, the property id — its docblock said "no settings" and no longer does. The `gatb_report_data` payload is untouched: the id comes from `Settings`, not from the `Report`.
- Decisions: DECISIONS "The message ends with a link into the GA4 property" (2026-09-16), written by the developer and committed with the code. Settled inside the task: no guard for an empty property id, because no report can be built without one.
- Verified on the local install, sending nothing: the rendered report ends with one blank line and the link in Ukrainian with `p533779496`; `render_failure()` has no link.
- Open: production is still 0.2.0 without the link until the next hand deploy — no version bump or changelog entry was asked for, so `master` and the deployed 0.2.0 now differ; `kyiv` gets `master` merged in with that deploy. LEARNINGS gained an entry: the plan's list of tests the change breaks came from one grep and missed two.

## 2026-09-16 — [ga-telegram-bridge] Sprint 3 closed
- Merged: simple git model, so the three step branches went straight into `master` (`7d338fe1`, `b4ce8e03`, `932f2715`), and `master` is merged into `kyiv` (`bc1be64b`). `bin/check.sh` exit 0 on both: `OK (294 tests, 1060 assertions)`, PHPStan level 8 no errors, 108 theme files linted — and "green" now excludes a test that asserts nothing, since `failOnRisky` went in with Step 1.
- Deployed: both productions by hand, and the morning report on each install arrived carrying the 28-day sparkline — confirmed by the developer on 2026-09-16. The owner now sees the trend without opening anything, which was the sprint's goal, and the plugin is 0.2.0 on both.
- Carried: the October clock change (2026-10-25/26) — the date is five weeks out, so the run-log rows confirm it at the next boundary, as that item's own wording provides. The mechanism is proven by a fixed-clock test over the real nights and by an event put an hour early and corrected by one run on the local install.
- Open beyond the Definition of Done: fifteen LEARNINGS entries still `pending` for the retro, and three contradictions `SPRINT-3-CLOSE.md` lists — the screen registry, `Requires at least: 7.1` versus "any WordPress site", and `origin/main`, which is now 24 commits behind `master` while two documents call it unused. `FEATURE.md` → Roadmap ends here: everything beyond Sprint 3 is "Later (not planned)".

## 2026-09-16 — [ga-telegram-bridge] Sprint 3 Step 3 — Re-anchor the daily event after every scheduled run
- Changed: `Scheduler::run_daily()` registers the next `gatb_daily_report` from the configured local time when it ends, in a `finally`, instead of leaving it to WordPress's fixed 86 400 s interval — so the report keeps its time of day across a clock change with nobody re-saving the settings. Only `DAILY_HOOK` is cleared, so a retry the same run booked survives; *Send now* and a retry never re-anchor. `reschedule()` takes the optional clock the rest of the plugin's entry points take. The plugin is **0.2.0** (header and the readme's `Stable tag`). 288 → 294 tests.
- Shared code: none of the theme, no gate config. Inside the plugin only `Scheduler` changed; `Runner` was not touched, and `reschedule()`'s new parameter is optional, so every existing caller behaves as before.
- Decisions: none new — DECISIONS "The daily event is re-anchored after every scheduled run" (2026-09-16) is what this implements, and its consequences are now true. Settled inside the task: the re-anchor sits in a `finally` so a run that fails in a way `Runner` does not catch still leaves a schedule behind; the clock parameter follows the file's own convention rather than stubbing `time()`, which Brain\Monkey cannot do.
- Verified on the local install without sending anything: the daily event was put an hour early — what a clock change leaves behind — and one run moved it back to 09:00, with the date guard stopping the report (log rows 7 → 7) and exactly one event registered afterwards. The command is §2 of the guide and prints what the guide says it prints.
- Open: the real night of **2026-10-25** is the only proof that matters and it belongs to the sprint's Definition of Done, after the deploy. LEARNINGS gained an entry: a number this plan asserted (an interval of 23 h) was true of the production scenario but not of the test the plan asked for, and the gate caught it on the first run.

## 2026-09-16 — [ga-telegram-bridge] Sprint 3 Step 2 — The sparkline in the message
- Changed: new pure `src/Sparkline.php` — one block character per value, scaled between the series' own ends rather than from zero, a flat period (all zeros included) drawn at mid height, `null` or empty drawn as nothing. `MessageRenderer::visitors()` gains a fourth line under *За 28 днів*: `<code>{28 chars}</code> {min}–{max}`, no heading and no blank line of its own. `readme.txt` describes the line and opens a `0.2.0` changelog. 277 → 288 tests.
- Shared code: none of the theme, no gate config. Inside the plugin only `MessageRenderer::visitors()` changed; the `gatb_report_data` and `gatb_message_html` filters are untouched, and a report built by Sprint 2 code still renders — the line is added only when `visitors_by_day` is there.
- Decisions: none new in DECISIONS.md; the sparkline was decided on 2026-09-16 and this step implements it. Settled inside the tasks: the `<code>` wrapper stays outside the translated string (markup is not a translator's business), leaving one new string — the range format; and the empty-series guard tests the array rather than the rendered string, which is what PHPStan can prove and what keeps `min()` off an empty array.
- Verified on the local install by rendering the real message: the line sits directly under *За 28 днів* as `<code>▅▄▁▃▂▆▇▆▂▅▇▅██▆▄▅▂▁█▆▇▆▄▄▇▄█</code> 43–77`, in Ukrainian, with the blank line after it being the block break. The preview, a real *Надіслати зараз* and a phone screenshot are §2–§3 of the guide.
- Open: how the row draws in the owner's own Telegram client — different clients pick different monospace fonts, and the first morning after the deploy is the only real answer. The changelog says 0.2.0 while the header still says 0.1.0; Step 3 bumps it.

## 2026-09-16 — [ga-telegram-bridge] Sprint 3 Step 1 — Trend data: block `trend`, the daily-visitors request and `Report::visitors_by_day`
- Changed: `Settings` gains a sixth block `trend` and a second block merge — a stored row that does not name a block now takes its default, so an install configured in Sprint 2 finds `trend` on, while the form keeps "an unticked box is off" in its own `submitted_blocks()`. `ReportBuilder` asks a seventh report (`activeUsers` by `date`, 28 days, ordered by the dimension, limit 28; 5 + 2 requests, still two calls) and parses it by day into `Report::$visitors_by_day` (`array<string,int>`, `Y-m-d`, oldest first, a missing day is 0). Screen `Settings` shows the sixth checkbox with a description; two new strings translated. `failOnRisky="true"` closes the gate hole Sprint 2 deferred. 269 → 277 tests.
- Shared code: none of the theme. Inside the plugin the `gatb_report_data` filter payload gains `visitors_by_day` — the plugin's public surface, recorded in `FEATURE.md` → Interfaces; nothing moved, one readonly property was appended.
- Decisions: DECISIONS "The tests' pinned clock belongs to the GA recordings" — the trend report is the first fixture whose rows are calendar dates, so `NOON` moved to the recording day (2026-09-16 12:00 UTC, report date 2026-09-15) rather than re-recording call-1 or synthesising dates. Chosen by the developer mid-step, after the step was stopped.
- Verified on the local install, whose settings were last saved in Sprint 2: the stored row still lists five blocks and `Settings::blocks()` returns six with `trend => true`; a live `ReportBuilder::build()` printed 28 ascending days, 2026-08-19 → 2026-09-15, min 43 max 77, sum 1761 against a 28-day figure of 1520 (a daily sum is not a period total, by design).
- Open: the message is untouched — the sparkline is Step 2, and the guide checks that nothing leaked into the visitors block. The Ukrainian description says «Спарклайн»; if that reads badly it is one word in the `.po` plus a `make-mo`. LEARNINGS gained an entry: the plan read the code and the tests but not what the fixtures silently assume, and a cost quoted to the developer mid-step was reasoned rather than checked.

## 2026-09-16 — [ga-telegram-bridge] Sprint 2 closed
- Merged: simple git model, so the four step task branches went straight into `master` (`818a8da`, `2e071b6`, `320f147`, `79967be`), pushed to `origin/main` at `730ef5a`, and `master` is merged into `kyiv` (`06fc831` remote, `7b057c9` local). `bin/check.sh` green on `master` at `0cfb3d3`: exit 0, `OK (269 tests, 854 assertions)`, PHPStan level 8 no errors, 108 theme files linted.
- Deployed: both productions by hand, the two constants in each `wp-config.php`, and the owner received the Kharkiv and Kyiv reports on two consecutive mornings — confirmed by the developer on 2026-09-16, which ticks the last two Definition of Done items (5/5). The plugin has now run by schedule on both installs.
- Carried: nothing as a Definition of Done item. Left standing and named in `SPRINT-3.md` → Risks: the screen registry (`docs/DESIGN.md` → Screens lists neither plugin screen while DECISIONS "No UI design phase…" forbids DESIGN.md changes), GA's own English labels (deferred again in Sprint 3's Out of scope), the local install's secret rotation, and Contradiction 5 — `master`'s upstream is `origin/main` while DECISIONS calls `origin/main` unused, to settle before the Sprint 3 boundary.
- Open: the retro over `SPRINT-2-CLOSE.md` has not run — its eight LEARNINGS entries, and five left from Sprint 1, are still `Transferred to playbook: pending`. `master` is three commits ahead of `origin/main` (docs and commands only).

## 2026-09-15 — [adhoc] — Playbook v1.14 → v1.17
- Changed: `.claude/commands/` copied from the playbook (new `/close-sprint`; `close-step`, `do-step`, `fix-step`, `plan-step` updated) and `templates/` added at the root. In root `CLAUDE.md`, Core rules (9 → 6) and Step protocol are now word for word from the template without "(PLAYBOOK CORE)", the header says v1.17, the `Origin:` and developer-reviewed `Verification:` lines are gone, and the Git model says deploys happen at the sprint boundary via `/close-sprint`. Features keeps only the router sentence and the table. The rule numbers in the TECH-STACK, DATA-MODEL and WORKLOG header comments now read 1/5/6. `bin/check.sh` passes.
- Decisions: —
- Open: the Definition of Done boxes in ga-telegram-bridge `SPRINT-1.md` and `SPRINT-2.md` are all unticked, and under v1.17 `/plan-step` for Sprint 3 needs `/close-sprint` first. "core rule N" citations in DECISIONS, LEARNINGS and the sprint files still use the v1.14 numbers, and so does `PRODUCTION-CHECKLIST.md` ("core rule 6" is now 3). This file's header still doesn't name `/close-sprint` as a writer. `kyiv` gets `master` merged in by hand.

## 2026-09-10 — [adhoc] [ga-telegram-bridge] — Report format: spaced sections, icons, top pages linked and named by their post
- Changed: `ReportBuilder` groups both top-pages requests by `pagePath` alone and names each row by the post `url_to_postid()` finds at that address, trusted only when its permalink is that address (otherwise the path); new `ReportBuilder::page_url()`. `MessageRenderer` links each page, sets the printed blocks apart with one blank line, puts an emoji before every heading (👥 📄 📅 🧭 📍 📱, outside the translated strings — no `.pot`/`.mo` change) and lists cities and devices one row per line; sources keep their line. Call-1's two pages reports re-recorded against the live property; new `tests/SitePosts.php`; 265 → 269 tests.
- Decisions: DECISIONS "Top pages are counted by path and named by their post" — supersedes "labelled by `pageTitle`" and settles the duplicate-path item both sprint closes deferred (the home page had taken places 1 and 2 of the 28-day five).
- Verified on the local site against the live property: the rendered message and the admin *Попередній перегляд* both show the new format, and all six links open their pages. Nothing was sent to Telegram.
- Open: how the links and spacing look in a real Telegram client — *Надіслати зараз*, or the first scheduled morning after the deploy; merging `master` into `kyiv` and both production deploys are manual. A translated page whose slug WordPress maps to the other language is labelled by its path. A browser screenshot of screen `Settings` captured the dev install's key and token (LEARNINGS) — rotating them is now more pressing.

## 2026-09-10 — [ga-telegram-bridge] Sprint 2 Step 4 — Production readiness on both Dovira installs
- Changed: screen `Settings` now names the build it is running — `Версія плагіна 0.1.0`, read from the plugin header (`Plugin::version()`), printed once above the run log and never per row — and links into `readme.txt` from the four sections that raise a question (Google → Installation 1, Telegram → 2, Розклад → 5, Журнал запусків → the FAQ). New `docs/features/ga-telegram-bridge/PRODUCTION-CHECKLIST.md`: sections A–F worked through once per install, with `ARCHITECTURE.md` → Environments recording the same shape. 5 new tests, 265 in all; five new strings translated.
- Shared code: none of the theme, no gate config. Inside the plugin only `Admin` and `Plugin` gained methods; four existing tests learned to stub `get_file_data`/`plugins_url` because the run log now calls them.
- Decisions: none new in DECISIONS.md. Settled in the tasks: the version is read, not stored (a constant beside the header would be the second copy the next-run decision already refused), and the run log gains a line rather than a seventh column, since the step asks for no DATA-MODEL change and the six columns are deliberate.
- Verified on the dev site, with the screen open in a browser: both secrets were moved into DDEV's `wp-config.php` — copied inside the container, never printed, the stored values left as a fallback — and *Перевірити GA* (property 533779496, Europe/Kiev) and *Перевірити Telegram* both still passed from the constants. That is the checklist's riskiest line rehearsed before either production reads it, and it is the first time the constants path has run outside unit tests.
- Found by clicking the links, not by testing them: this server serves `readme.txt` as `text/plain` with no charset, so the browser guessed a Cyrillic codepage and 28 typographic characters became mojibake. The readme is ASCII now (`a4c1d28`), the plugin's `CLAUDE.md` says why, and LEARNINGS has the rule: a target the UI points at is opened and read, not asserted.
- Open: **both production installs** — sections B–E of the checklist and the two consecutive mornings are the sprint boundary's, and they are the sprint's Definition of Done. The dev install now keeps its secrets in both places; clearing the stored copies waits for a rotation (`spike/` and the key file went at the Sprint 1 boundary, so that row is the only copy on this machine). This tick closes Sprint 2: `SPRINT-2-CLOSE.md` is the handoff.

## 2026-09-10 — [ga-telegram-bridge] Sprint 2 Step 3 — Lifecycle, readme, translations
- Changed: new `uninstall.php` (the three options, the cached Google token and both cron events, removed when the plugin is deleted; deactivation still takes only the schedule) with `UninstallTest` — 5 tests, 260 in all — and `readme.txt` rewritten into the WordPress readme format with the Google Cloud and Telegram walkthroughs, the two `wp-config.php` constants, a crontab line for `DISABLE_WP_CRON` installs, an FAQ and a real changelog.
- Shared code: none of the theme. One project-level change inside the plugin: `phpstan.neon.dist` lists its paths file by file, so `uninstall.php` was added there — without it the new file would have been the only source file outside static analysis.
- Decisions: none new in DECISIONS.md. Settled inside the tasks: `uninstall.php` runs with the plugin unloaded, so it spells its option names out and the test holds each literal against the class constant; cleanup is single-site, like the rest of the plugin; the version stays 0.1.0 and the changelog entry now describes what 0.1.0 contains, because packaging is out of scope.
- Verified on the dev site: a plain HTTP request to `uninstall.php` returned an empty 200 and deleted nothing; deactivation kept all three rows and took both events; `wp plugin uninstall --skip-delete` left no `gatb_` option, no transient and no cron event — only the temporary backup row this session made, which the plugin does not own — and everything was restored afterwards, including the five log rows.
- **The report went out by itself for the first time**: the newest log row reads `cron · 2026-09-09 · Надіслано · #1`, delivered by the schedule with nobody pressing anything. Two consecutive mornings on the production installs are still Step 4 and the sprint boundary.
- Open: the readme is only proven by a setup someone runs from it without knowing the answers — §5 of the verification guide, and the one thing this step cannot self-check. LEARNINGS gained two entries for the retro: a test that asserted nothing still passed the gate (`failOnRisky` is not set), and a plan file list that again missed a config the gate reads.

## 2026-09-09 — [ga-telegram-bridge] Sprint 2 Step 2 — Retries and the failure notice
- Changed: `Runner` (a failed scheduled run books another attempt an hour later while the day has attempts left; the last one sends `MessageRenderer::render_failure()` to the chat as a best effort, resets the counter and leaves the day unsent; a retry whose day the property has already left behind reports that day instead of another one), `Scheduler` (`schedule_retry()`, `run_retry()`, and both hooks cleared with `wp_unschedule_hook()`), `RunLog::reset_attempt()`, `TelegramClient` (a 429 naming a wait of at most 30 s is waited out once inside the same run) and the `Максимум спроб` description, which now says attempts rather than repeats. 17 new unit tests, 255 in all; three new strings translated.
- Shared code: none of the theme, no project tooling. Inside the plugin `Runner::run()` gained a fourth parameter and `TelegramClient::send_message()` an optional injected wait; both are called with the same arguments as before by everything that existed.
- Decisions: DECISIONS "Only the schedule retries, and a retry cannot outlive the day it was booked for" — *Send now* counts its attempt and stops there (approved as recommended when the plan was approved), and a retry that finds the property's day has turned gives that day up rather than sending another day's numbers under its date, because making the GA ranges absolute would reopen a fixed decision.
- Found in core while planning, not after: `wp_clear_scheduled_hook()` matches the arguments an event was registered with, so it would have walked past a retry carrying its date — deactivation now uses `wp_unschedule_hook()`, which `uninstall.php` in Step 3 needs too.
- Verified on the dev site, which is configured now: a broken property id with two attempts gave one `Розклад` failure, a `gatb_retry_report` booked an hour out carrying `2026-09-08`, then a `Повтор` row at attempt 2 whose notice really arrived in the Telegram chat, with the counter back at 0; a failed *Надіслати зараз* booked nothing, and a pending retry did not survive deactivation. The install was put back and the daily event re-anchored at 09:00.
- Open: the delivery of a **scheduled** report is still unproven end to end — the two consecutive real mornings on both production installs stay Step 4 and the sprint boundary. The daily event drifts by an hour across a DST change until the settings are saved again (retro or `/adhoc`). The service-account key and the bot token are in `gatb_settings` in the database on dev, not in the `wp-config.php` constants, so they would travel in a `mysql.sql` dump; reading that row printed both into a session transcript (LEARNINGS), so rotating them is worth considering.

## 2026-09-09 — [ga-telegram-bridge] Sprint 2 Step 1 — Scheduler
- Changed: `Scheduler` (the next occurrence of the configured time in the site's zone, one recurring `gatb_daily_report` re-registered on every settings save and on activation, both hooks cleared on deactivation, the callback that runs `Runner` as `cron` with the date guard on, and the cron-hit note), `RunLog` (`gatb_state` gains `last_cron_hit`; state writes merge instead of replacing) and, on screen `Settings`, the Schedule section that says when the next run is due and warns when `DISABLE_WP_CRON` is set with nothing calling `wp-cron.php`. 24 new unit tests, 238 in all; four new strings translated.
- Shared code: none of the theme, no project tooling. Inside the plugin only `Plugin::boot()` and `Plugin::activate()` gained lines; `Runner` was not touched.
- Decisions: DECISIONS "The schedule is registered from the settings alone, and the next run is never stored" — `wp_next_scheduled()` is the one copy, `gatb_state.next_run` is not created, and the event is registered whatever the install holds. The date guard stays on the property's day (DECISIONS from Step 8), so `SPRINT-2.md` Step 1's "yesterday in the site time zone" is settled against the sprint text, as is the next-run column `FEATURE.md` gave screen `Run log`.
- Found on the screen, not in the tests: the first save on a fresh install scheduled nothing, because core routes a write through `add_option()` while the stored row still equals the registered default. Both actions now reschedule (commit `f3f9887`); LEARNINGS has the entry and the two rules it leaves.
- Open: the scheduled run's **delivery** is still unproven — the dev install is back at defaults and the service-account key went with `spike/` at the sprint boundary, so a forced run proves the firing, the trigger and the log row, and that row reads `Помилка`. A `daily` event drifts by an hour across a DST change until the settings are saved again: for Step 2 or the retro.

## 2026-09-09 — [ga-telegram-bridge] Sprint 1 Step 8 — "Send now" and RunLog
- Changed: `RunLog` (the options `gatb_log`, 30 runs newest first, and `gatb_state`, the day last delivered plus the attempt counter — both non-autoloaded, neither able to hold a secret), `Runner` (the one path a report takes: build → date guard → render → send → log → remember) and, on screen `Settings`, a fourth button *Надіслати зараз* with screen `Run log` under it. 28 new unit tests, 215 in all; the Ukrainian translation regenerated for the fourteen new strings.
- Shared code: none of the theme, no project tooling. Inside the plugin `ReportBuilder::build()` changed shape slightly — its short-answer check moved into a private helper so that its `@throws` tag stopped hiding `GoogleAuthException` from PHPStan — and it now passes the injected clock through to `Runner`.
- Decisions: DECISIONS "The date guard runs after the report is built, not before" — the day a report is about is the property's, and only Google's answer knows it, so guarding first would compare two calendars. SPRINT-2 Step 1 says the opposite and is listed in SPRINT-1-CLOSE.md → Contradictions.
- Verified on the screen, not only in tests (the rule Step 7's LEARNINGS entry left): with a property configured and no bot token, *Надіслати зараз* built the report against the live property, refused at Telegram, showed exactly one notice and wrote one `Помилка` row dated with the property's day.
- Open: **the delivery itself is still unproven** — no bot token or chat id exists on the dev site, so the sprint's Definition of Done ("Send now delivers the report there") is open and so is the deploy to dev. The spike directory and the service-account key are still on disk; no key has ever been committed (`git log -S "BEGIN PRIVATE KEY"` finds only test placeholders). This tick closes Sprint 1: `SPRINT-1-CLOSE.md` is the handoff.

## 2026-09-09 — [ga-telegram-bridge] Sprint 1 Step 7 — MessageRenderer and Preview
- Changed: `MessageRenderer` (a `Report` becomes the HTML message fixed by FEATURE.md → UI, plus the failure notice; the two public filters `gatb_report_data` and `gatb_message_html` are applied here for the first time and their signatures written into FEATURE.md), a third button *Попередній перегляд* in the Connection section of screen `Settings` that builds the real report and shows the message without sending it, and the plugin's Ukrainian translation — 94 of 101 strings in `languages/ga-telegram-bridge-uk.mo`. 27 new unit tests, 187 in all.
- Shared code: none of the theme, no project tooling. Inside the plugin the screen changed for every feature that might ever live here: `Admin::render_page()` no longer calls `settings_errors()`, which is now recorded in the plugin's `CLAUDE.md` → Admin.
- Decisions: none new in DECISIONS.md; "No UI design phase; message format and configurable blocks" is implemented as written, with its template corrected in the same commit where the rendering differed — the Ukrainian date reads `8 Вересня (Вівторок)` because WordPress declines and capitalises it, and a block with no rows is left out like a switched-off one. One constraint made explicit in code: Telegram accepts only four named HTML entities, and Ukrainian `number_format_i18n()` returns the string `1&nbsp;560`, so every number is entity-decoded on the way out.
- Fixed in passing, with the user's agreement: **every settings notice had been printed twice since Step 4** — wp-admin prints the notices of screens under Settings itself (`admin-header.php` → `options-head.php`), and the screen printed them again. Found by opening the page in a browser, which no unit test can do; the preview was being printed twice too, once as a mangled bold paragraph. LEARNINGS: "A screen was called finished without anyone opening it".
- Open: the message is still unproven against a real Telegram bot — no token is configured anywhere, so that waits for Step 8's *Send now*. `docs/DESIGN.md` → Screens still lists no screen of this plugin (DECISIONS forbids it for this feature) — for the retro. The spike directory and the Google key are still on disk (sprint Definition of Done). The staged `templates/*` deletions from outside the session are in none of this step's commits.

## 2026-09-09 — [ga-telegram-bridge] Sprint 1 Step 6 — ReportBuilder
- Changed: three classes, no hook and no screen — `Dynamics` (the seven-day baseline divides by seven, a change against a zero baseline is null, shares are rounded row by row), `Report` (a readonly value object whose optional blocks separate "switched off" from "on but empty") and `ReportBuilder` (six GA4 reports composed into at most two `batchRunReports` calls, parsed back into one object with shares and changes already computed). 34 new unit tests, one of them walking all sixteen block combinations.
- Shared code: none — no theme code, no hook, no option, no project-level tooling touched this time.
- Decisions: none new; DECISIONS "Report content and comparison baselines" is implemented as written. Two constraints made explicit in code and docs: the Data API takes five requests per batch call, so a full report of six cannot be one call — that is where FEATURE.md's "never more than 2" comes from; and which day the report covers is read from the property's own reporting time zone, not the server clock.
- Open: the 28-day pages block lists the same path twice when its title changed during the period (recorded in the fixture: `/` appears as two titles, eating two of the five slots) — the step fixes the dimensions as pagePath + pageTitle, so this is for Step 7 or the retro to settle. The empty-property shape is still a written fixture. The Google key and `spike/` are still on disk and were used to record this step's fixtures; they go at the sprint boundary.

## 2026-09-09 — [ga-telegram-bridge] Sprint 1 Step 5 — TelegramClient and "Check Telegram"
- Changed: `TelegramClient::send_message()` (one HTML message to one chat via `api.telegram.org`, link previews off, 15 s) with `TelegramException` and one mapped sentence per failure — a revoked token, a mistyped one, a chat that cannot be found, unreadable markup, a bot that may not post there, flood control naming `retry_after`, 5xx, and an unreachable host; a second button in the Connection section of screen `Settings` that really posts a test message. 28 new unit tests on 7 fixtures (2 recorded live — a wrong token needs no credential — and 5 written from Telegram's documentation).
- Shared code: none of the theme. Two project-level changes, both gating every feature: `bin/check.sh` and the plugin's `composer analyse` now pass `--memory-limit=1G` to PHPStan (commit `5937251`), and `phpunit.xml.dist` fixes the suite order so the class defining the `GATB_*` constants runs last.
- Decisions: none new; no fixed decision reopened. Two settled inside the tasks: the token is scrubbed out of every message because it travels in the request URL (a transport error would otherwise quote it back), and the sprint's `disable_web_page_preview` is sent as `link_preview_options` instead — the old field is no longer in the Bot API documentation.
- Open: the 200, 400, 403 and 429 responses are all written fixtures and are proven only by the manual verification with a real bot, which does not exist yet — no token is configured on the dev site. `docs/DESIGN.md` → Screens still lists no screen of this plugin (DECISIONS forbids it for the feature) — for the retro. The spike directory and the Google key are still on disk (sprint Definition of Done). The staged `templates/*` deletions from outside the session are in none of this step's commits.

## 2026-09-09 — [ga-telegram-bridge] Sprint 1 Step 4 — GoogleAuth, GaClient and "Check GA"
- Changed: `GoogleAuth` (RS256 JWT signed with the service-account key, token exchange, cache in the transient `gatb_google_access_token` for `expires_in - 60`) and `GaClient` (`batch_run_reports()`, `check_connection()`, one mapped message per failure), each with its own exception type; a *Check GA* button in a new Connection section of screen `Settings`, behind an admin-post nonce. 39 new unit tests on the six responses the Step 2 spike recorded plus two written from Google's documentation; the JWT signature is signed and verified for real against a throwaway key pair the bootstrap generates.
- Shared code: none of the theme. One project-level change: `phpstan.neon.dist` now caps PHPStan at two parallel workers (commit `e5c5cbb`) — the gate crashed on code with no errors because the worker pool scales with the CPU count. That gate covers every feature.
- Decisions: DECISIONS ""Check GA" proves the connection through the Data API, not the Admin API" — a read-only probe with the real key showed the Admin API is a separate service and is disabled in the Cloud project, so the check names the property id with the reporting time zone from the report's own metadata instead of the property's display name. One API to enable per install, not two.
- Open: the 429 quota mapping stays documented-but-not-observed, tested on a written fixture. The spike directory and the service-account key are still on disk (sprint Definition of Done). `docs/DESIGN.md` → Screens still lists no screen of this plugin, because DECISIONS forbids DESIGN.md changes for the feature — for the retro. The staged `templates/*` deletions from outside the session are in none of this step's commits.

## 2026-09-09 — [ga-telegram-bridge] Sprint 1 Step 3 — Settings and the admin page
- Changed: two classes in the plugin — `Settings` (the option `gatb_settings`, created non-autoloaded by the activation hook: defaults, per-field validation, typed getters, the two secrets overridden by `GATB_GA_SERVICE_ACCOUNT_JSON` / `GATB_TELEGRAM_BOT_TOKEN`) and `Admin` (screen `Settings` under Settings → "GA → Telegram", Settings API, `manage_options`, four sections, no CSS or JS); 59 new unit tests, `docs/DATA-MODEL.md` gained the plugin's options section and `readme.txt` its configuration section.
- Shared code: none touched. The plugin still references no theme code, option or hook; the only project-level files changed are docs.
- Decisions: none new — no fixed decision was reopened. Two rules settled inside the tasks: an empty send time or attempt count keeps the stored value (unlike the four credentials, those two have no unset state), and each block checkbox is preceded by a hidden field so that unticking every block arrives as zeros rather than as nothing. Both are written into DATA-MODEL.md.
- Open: `docs/DESIGN.md` → Screens is a registry that lists admin screens of `core`, but DECISIONS "No UI design phase" forbids DESIGN.md changes for this feature, so the plugin's `Settings` screen is not in it — for the sprint retro to settle. The staged `templates/*` deletions from outside the session are still in the working tree and in none of this step's commits.

## 2026-09-09 — [ga-telegram-bridge] Sprint 1 Step 2 — Spike: service account → GA4 Data API
- Changed: no production code. `spike/` (gitignored, PHPCS-excluded) holds a throwaway probe that ran the real path — RS256 JWT → token exchange → `runReport` / `batchRunReports` — against the Kharkiv property 533779496; six recorded responses kept for Step 4's fixtures, findings written to `docs/LEARNINGS.md`.
- Shared code: none touched. The only tracked changes are two ignore/exclude lines in the plugin and the LEARNINGS entry.
- Decisions: none — no finding contradicts a fixed decision, so DECISIONS.md is untouched. Key findings for later steps: `batchRunReports` returns rows ordered by metric value, so Step 6 must key them by the `dateRange` dimension; the token endpoint and the Data API use two different error shapes, so Step 4 needs a parser for each; the `private_key` needs no newline repair.
- Open: the quota 429 stays documented-but-not-observed (unreachable from one process; ~6 of 200 000 daily tokens used). The spike directory and the service-account key are still on disk — removed at the sprint boundary per the Definition of Done, not at this close, because the manual verification runs the probe.

## 2026-09-08 — [ga-telegram-bridge] Sprint 1 Step 1 — Delta-audit, plugin skeleton, check command
- Changed: new code area `wp-content/plugins/ga-telegram-bridge/` — plugin header (WP 7.1 / PHP 8.1), own PSR-4 autoloader, `Plugin::boot()` loading the textdomain, activation guard on the OpenSSL extension, dev-only PHPUnit/PHPCS/PHPStan tooling and 3 unit tests; plus the project's first check command `bin/check.sh` (PHPCS → PHPStan → PHPUnit in the plugin, then `php -l` over the theme's 108 files).
- Shared code: none touched — the plugin references no theme code, function, option or hook. `bin/check.sh` is new project-level tooling that gates every feature from now on.
- Decisions: DECISIONS "The check command runs on PHP 8.3+, tooling pinned to what 8.3 accepts" (PHPUnit ^12 / PHPCS ^3 pins, committed lock, PHPStan level 8 at phpVersion 8.1). The delta-audit confirmed no theme coupling: the theme's Telegram bot is a separate ARCHITECTURE → Integrations row, not a shared surface.
- Open: activation on a host without OpenSSL cannot be reproduced locally (the extension is compiled into both PHP binaries) — covered by the unit test and a `wp eval` of the guard. `templates/*` deletions staged in the working tree from outside this session were left untouched and are in none of this step's commits.
