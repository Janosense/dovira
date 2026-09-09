# SPRINT 1 CLOSE — Report on demand (ga-telegram-bridge)

Written by `/close-step` at the close of Step 8 on 2026-09-09, from the files and the code.
It is the handoff to the retro, to the re-planning chat if the retro changes anything, and to
`/plan-step ga-telegram-bridge 2 1`.

## Definition of Done

| Item | Evidence |
|---|---|
| Every step closed via /close-step (report + verification guide + worklog) | All eight steps ticked in `SPRINT-1.md`; guides `verification/sprint-1-step-1.md` … `-8.md`; eight WORKLOG entries |
| `bin/check.sh` green on the sprint branch | `OK (215 tests, 687 assertions)`, PHPCS + PHPStan level 8 clean, 108 theme files linted — run at the close of Step 8 on the task branch and again on `master` after the merge |
| Docs match reality (DATA-MODEL, ARCHITECTURE, DECISIONS, TECH-STACK current) | DATA-MODEL has all three options and the transient; ARCHITECTURE has the module row, both integration rows and the "Daily GA report" data flow; DECISIONS gained two entries this sprint (the Data API check, the date guard); TECH-STACK's dependency log lists all seven dev packages. **Two known gaps**, both listed under Contradictions: `DESIGN.md` → Screens lists no screen of this plugin, and `FEATURE.md` → Data still names a `gatb_state.next_run` key that Sprint 2 adds |
| Merged to `master`; deployed to dev by the sprint-boundary push to `dev`; plugin activated and configured on dev with the constants in its `wp-config.php`; "Send now" delivers the report there | **open** — every step is merged to `master`, but nothing has been pushed to `dev` (no step may deploy: root `CLAUDE.md` → git model), and **no Telegram bot exists yet**: no token or chat id has ever been configured on any install, so *Send now* has been proven only to fail correctly. The delivery is the first thing to do at the boundary |
| Spike code removed; no secret or key file in the repo (`git log -p` checked for the JSON key) | **half open** — the repository is clean: `git log --all -S "BEGIN PRIVATE KEY"` matches only test placeholders (`SECRETMATERIAL`, `not-a-real-key`, `NOTAKEY`) and one doc example, and `spike/` has never been committed (gitignored, PHPCS-excluded). But `wp-content/plugins/ga-telegram-bridge/spike/` **is still on disk** with the real `service-acount.json`, `config.local.php`, `dumps/` and `ga-probe.php`: every step from 4 to 8 needed the key to verify against the live property. Deleting the directory is the boundary's job |

## Built

Modules, all in `wp-content/plugins/ga-telegram-bridge/src/` (namespace `GaTelegramBridge`,
PSR-4 through the plugin's own autoloader):

| File | What Sprint 2 inherits |
|---|---|
| `Plugin.php` | `boot()` (five hooks), the OpenSSL activation guard, creation of `gatb_settings` non-autoloaded |
| `Settings.php` | option `gatb_settings`, per-field validation, typed getters, `GATB_*` constant overrides |
| `Admin.php` | screen `Settings` (four sections, four buttons behind admin-post nonces) and screen `Run log`; `take_preview()` on `all_admin_notices`; the screen never calls `settings_errors()` |
| `GoogleAuth.php` + `GoogleAuthException.php` | RS256 JWT, token exchange, transient cache, one mapped message per failure |
| `GaClient.php` + `GaClientException.php` | `batch_run_reports()`, `check_connection()`, one mapped message per failure |
| `ReportBuilder.php`, `Report.php`, `Dynamics.php` | six GA4 reports in at most two calls, parsed into a readonly value object with shares and changes computed |
| `MessageRenderer.php` | the message of `FEATURE.md` → UI and the failure notice; the filters `gatb_report_data` and `gatb_message_html` |
| `TelegramClient.php` + `TelegramException.php` | one HTML message to one chat, one mapped message per failure, the token scrubbed from every message |
| `RunLog.php` | options `gatb_log` (30 runs) and `gatb_state` (`last_report_date`, `attempt`) |
| `Runner.php` | `run( $trigger, $bypass_date_guard, $now )` — the single path a report takes, with the date guard |

Admin-post actions: `gatb_check_ga`, `gatb_check_telegram`, `gatb_preview`, `gatb_send_now`.
Data: `gatb_settings`, `gatb_state`, `gatb_log`, transient `gatb_google_access_token`.
Translation: `languages/ga-telegram-bridge.pot` and the `uk` `.po`/`.mo` (115 strings).
Project tooling created this sprint: `bin/check.sh` and the plugin's PHPUnit / PHPCS /
PHPStan configuration, with a committed `composer.lock`.

## Not locally verifiable

- **The delivery itself.** Nothing has ever been posted to a real chat: `TelegramClient` is
  proven against two recorded refusals (401, 404) and five written fixtures. The one real run
  that settles it is *Send now* with a bot token configured — the first item of the boundary.
- **A GA4 property with no traffic.** `batch-run-reports-no-data.written.json` is written from
  the reference; a property with traffic cannot produce that shape. Verified only by the
  plugin's first morning on a brand-new property.
- **Activation without OpenSSL.** The extension is compiled into both PHP binaries here; the
  guard is covered by a unit test and a `wp eval` of the message, never by a real refusal.
- **The 429 quota mapping.** One PHP process cannot exhaust 200 000 daily tokens (Step 2
  spike); the fixture is written from Google's reference.
- **Both production hosts.** Outbound HTTPS to `googleapis.com` / `api.telegram.org` and the
  presence of `openssl` are checked on the dev machine only — Sprint 2 Step 4.

## Deferred

- **Delete `spike/`** (the real service-account key, `config.local.php`, `dumps/`,
  `ga-probe.php`) — the sprint's own Definition of Done, held open through Steps 4–8 because
  every one of them verified against the live property.
- **Push to `dev`** and configure the plugin there with the two constants in that install's
  `wp-config.php` — the sprint-boundary deploy.
- **The failure notice.** `MessageRenderer::render_failure()` is built and tested; nothing
  sends it. Sprint 2 Step 2.
- **Scheduling, retries, the next-run display, `last_cron_hit`.** Sprint 2 Steps 1–2.
- **`uninstall.php` and the deactivation hook.** Sprint 2 Step 3.
- **GA's own labels stay English** in the Ukrainian message: `mobile`, `desktop`,
  `Organic Search`, `Direct`. They are data, not our copy; Step 7 recorded it as a retro item.
- **The 28-day pages block can list the same path twice** under two titles (recorded in the
  fixture: `/` appears as two titles and takes two of the five slots). Step 6 raised it for
  "Step 7 or the retro"; Step 7 did not change the dimensions, so it is still open.
- **The theme's own Telegram bot** (token as a string literal, public webhook routes) — two
  open questions in DECISIONS from the adoption audit, untouched by this feature.

## Contradictions

Cross-read of `FEATURE.md`, `SPRINT-1.md` → Out of scope, `SPRINT-2.md`, `DESIGN.md` and
`DECISIONS.md`. Listed, not resolved.

1. **Which time zone decides the report's day.** `SPRINT-2.md` → Step 1 says the cron
   callback's "target date = 'yesterday' in the site time zone, stored as `Y-m-d` in
   `gatb_state.last_report_date` after success". Sprint 1 built the opposite and wrote it down:
   the day is the **property's**, read from `metadata.timeZone` (FEATURE.md → Invariants;
   DECISIONS "Report content and comparison baselines"; DECISIONS "The date guard runs after
   the report is built, not before"). Sprint 2 Step 1 cannot be planned as written.
2. **The translation is already shipped, and it is `uk`.** `SPRINT-2.md` → Step 3 asks to
   "regenerate the `.pot`, complete `uk_UA` for all strings added in Sprints 1–2". Step 7
   shipped the translation, and the locale on these installs is **`uk`** — `get_locale()`
   returns `uk`, core's own files are `admin-uk.mo`, and a `uk_UA` file would never load.
   What remains for Sprint 2 is only the strings that sprint adds.
3. **`readme.txt` overlaps.** `SPRINT-2.md` → Step 3 lists writing the readme (what the plugin
   sends, the Google Cloud and Telegram setup, the constants, the requirements). Steps 3, 5, 7
   and 8 already wrote all of that except the `DISABLE_WP_CRON` recipe and the freshness note.
4. **`gatb_state` keys.** `FEATURE.md` → Data lists `last_report_date`, `attempt`, `next_run`;
   Sprint 1 built the first two; `SPRINT-2.md` → Step 1 adds `next_run` **and** `last_cron_hit`,
   which appears in no other file.
5. **Screen registry.** `docs/DESIGN.md` → Screens is the project's registry of admin screens
   and lists none of this plugin's two, because DECISIONS "No UI design phase; message format
   and configurable blocks" forbids `DESIGN.md` changes for this feature. Carried unresolved
   since Step 3.
6. **`FEATURE.md` → UI still lists a *next run* column** for screen `Run log`; Sprint 1's Out
   of scope put the next-run display in Sprint 2, and the built table has six columns without
   it. The line now says so, but the two files still describe different tables until Sprint 2
   Step 1 lands.

## LEARNINGS

Entries from this sprint still marked `Transferred to playbook: pending` — the retro fills them:

1. **A screen was called finished without anyone opening it** (Step 7/8). 187 unit tests and a
   green gate did not see that wp-admin prints the notices of every screen under Settings
   itself; the *Preview* was printed twice and the two *Check* notices had been duplicated
   since Step 4. Rule taken: a step whose deliverable is a wp-admin screen is not implemented
   until the screen has been opened and its buttons pressed.
2. **Two files looked unchanged because DDEV had not synced them yet** (Step 8). A `.po` was
   nearly shipped from a stale `.pot`; and the step plan listed its files without consulting
   the code area's own conventions.
3. **An option was put to the user with a benefit that had never been measured** (Step 5).
   Rule taken: an option's stated cost or benefit is measured before it is offered, or written
   as an estimate in so many words.
4. **The gate failed on code that had no errors, and said the wrong thing about why** (Step 4).
   PHPStan's worker pool, not the analysis, exhausted the memory limit. Rule taken: re-run the
   failing tool single-threaded before raising any limit.
5. **A pathspec commit silently dropped every new file** (Step 1). Rule taken: `git add` before
   every pathspec commit, and read `git show --stat` against the plan's file list afterwards.

Three further entries are marked `n/a — project-technical`: the PHPUnit 12 attribute change and
PHPCS's parallel counter (Step 3), the constant leak between test classes (Step 5), and the
Sprint 1 spike findings about the GA4 Data API.
