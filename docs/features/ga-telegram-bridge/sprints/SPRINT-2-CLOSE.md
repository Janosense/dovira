# SPRINT 2 CLOSE — Runs by itself (ga-telegram-bridge)

Written by `/close-step` at the close of Step 4 on 2026-09-10, from the files and the code.
It is the handoff to the sprint boundary, to the retro, and to whatever follows this feature.

## Definition of Done

| Item | Evidence |
|---|---|
| Every step closed via /close-step (report + verification guide + worklog) | All four steps ticked in `SPRINT-2.md`; guides `verification/sprint-2-step-1.md` … `-4.md`; four WORKLOG entries (2026-09-09 ×2, 2026-09-10 ×2) |
| `bin/check.sh` green on the sprint branch | `OK (265 tests, 843 assertions)`, PHPCS + PHPStan level 8 clean, 108 theme files linted — run at the close of Step 4 on the task branch and again on `master` after the merge. The suite grew from 215 at the end of Sprint 1 |
| Docs match reality (DATA-MODEL, ARCHITECTURE, DECISIONS, TECH-STACK current) | DATA-MODEL carries `last_cron_hit`, the attempt counter's reset, the retry hook's argument and the exact uninstall list; ARCHITECTURE has the failure behaviour in both integration rows, the scheduled half of the data flow, and now the per-install configuration paragraph under Environments; DECISIONS gained two entries this sprint (the schedule and the next run, 2026-09-09; only the schedule retries, 2026-09-09); TECH-STACK is unchanged because no dependency was added. **One known gap**, carried since Sprint 1: `DESIGN.md` → Screens still lists neither screen of this plugin |
| Merged to `master`; `master` merged into `kyiv`; both productions deployed by hand; constants set in both `wp-config.php`; the owner receives the Kharkiv and Kyiv reports on two consecutive mornings | **open** — every step is merged to `master`, but `git ls-tree` finds `wp-content/plugins/ga-telegram-bridge` on **`master` only**: not on `dev`, not on `kyiv`, on neither remote. Nothing has been deployed anywhere; the plugin has run on the local DDEV install and nowhere else. The whole item is the sprint boundary's, and `PRODUCTION-CHECKLIST.md` is the procedure for it |
| `SPRINT-1-CLOSE.md` Deferred/Contradictions items settled or carried into DECISIONS.md | Settled: `spike/` and the key file are gone from disk; the failure notice ships (Step 2); scheduling, retries, the next-run display and `last_cron_hit` ship (Steps 1–2); `uninstall.php` and the deactivation hook ship (Step 3); Contradictions 1, 2, 3, 4 and 6 are resolved, four of them by the two new DECISIONS entries. Still open: the push to `dev`, GA's English labels, the duplicate path in the 28-day pages block, the theme's own Telegram bot, and Contradiction 5 (the screen registry) — all listed under Deferred below |

## Built

Everything in `wp-content/plugins/ga-telegram-bridge/` (namespace `GaTelegramBridge`).
What Sprint 2 adds to what Sprint 1 left:

| File | What this sprint put there |
|---|---|
| `src/Scheduler.php` | the only class that touches cron: `reschedule()` (the next occurrence of the configured `HH:MM` in the site's zone, one recurring `gatb_daily_report`), `next_run()`, `schedule_retry()` / `RETRY_DELAY`, `run_daily()`, `run_retry()`, `clear()` through `wp_unschedule_hook()`, `note_cron_hit()`, `external_cron_missing()` |
| `src/Runner.php` | the failure half: `run( $trigger, $bypass_date_guard, $now, $for_date )`, `failed()` (book another attempt while the day has them, otherwise notify the chat and reset the counter), `is_automatic()`, `notify()` — and a retry whose day the property has left behind gives that day up |
| `src/RunLog.php` | `gatb_state.last_cron_hit`, merging state writes, `reset_attempt()` |
| `src/TelegramClient.php` | one in-run wait on a 429 naming `retry_after` ≤ 30 s, then the failure it looks like |
| `src/Admin.php` | the Schedule section's *Наступний запуск* and the `DISABLE_WP_CRON` warning; the run log's version line; four links into `readme.txt` (`readme_url()`, `readme_link()`) |
| `src/Plugin.php` | the retry hook registered with one accepted argument, both settings-save actions, `wp_loaded` → cron hit, and `version()` — the header read, never copied |
| `uninstall.php` | the three options, the cached token and both cron events, removed when the plugin is deleted; literal names, held against the class constants by `UninstallTest` |
| `readme.txt` | the WordPress readme: what the plugin sends, Google Cloud and Telegram step by step, the two constants, the WP-Cron recipe, the FAQ, the changelog. ASCII, because the screen links to it |
| `languages/` | 127 `.pot` entries, 119 translated in `uk` |
| `tests/Unit/` | 50 new tests (215 → 265) across `SchedulerTest`, `RunnerTest`, `RunLogTest`, `TelegramClientTest`, `PluginTest`, `AdminTest`, `AdminSendNowTest` and the new `UninstallTest` |
| `docs/features/ga-telegram-bridge/PRODUCTION-CHECKLIST.md` | sections A–F, worked through once per install; rehearsed on the dev install at Step 4 |

Data added this sprint: `gatb_state.last_cron_hit`; cron hooks `gatb_daily_report`
(recurring, no arguments) and `gatb_retry_report` (single, carrying its day).
No new option, no table, no dependency.

## Not locally verifiable

- **Both production installs.** Sections B–E of `PRODUCTION-CHECKLIST.md`: the two
  constants in each `wp-config.php`, *Check GA* and *Check Telegram* on each, the send
  time, the outbound HTTPS and `openssl` of the two hosts — and **two consecutive
  mornings** whose newest run log row reads *Розклад · Надіслано · Спроба 1*. That last
  one is the sprint's Definition of Done and the reason this sprint exists.
- **A fresh install set up from the readme alone**, ending in a successful *Send now*
  (Step 3, §5 of its guide). It needs a service-account key and a bot token typed in by
  someone who does not already know the answers.
- **Telegram's flood limit.** One bot sending one message a day cannot provoke a 429;
  the fixture is written from Telegram's documentation (Step 2).
- Still open from Sprint 1: **a GA4 property with no traffic**, **activation without
  OpenSSL**, **the 429 quota mapping**.

## Deferred

- **The deploy itself** — `master` to Kharkiv by hand, `master` merged into `kyiv` and
  deployed to Kyiv, and the push to `dev` that Sprint 1 already deferred. The plugin is
  on `master` alone; no step may deploy (root `CLAUDE.md` → git model).
- **The daily event drifts by an hour across a DST change** until the settings are saved
  again (Steps 1 and 2 WORKLOG). WordPress's `daily` schedule is a fixed 86 400 s
  interval; nothing re-anchors it. Retro or `/adhoc`.
- **Rotate the dev install's service-account key and bot token.** They were printed into
  a session transcript during Step 2 (LEARNINGS, 2026-09-09), and since Step 4 they live
  both in that install's `wp-config.php` and in its `gatb_settings` row. Clearing the
  stored copies waits for a rotation, because `spike/` and the key file went at the
  Sprint 1 boundary and that row is now the only copy on this machine.
- **`failOnRisky="true"` in `phpunit.xml.dist`** — a one-line change with no failing test
  behind it today, deliberately left out of Step 3 (LEARNINGS, 2026-09-10).
- Carried unchanged from Sprint 1: **GA's own labels stay English** in the Ukrainian
  message (`mobile`, `Organic Search`); **the 28-day pages block can list the same path
  twice** under two titles; **the theme's own Telegram bot** (token as a string literal,
  public webhook routes — two open questions in DECISIONS from the adoption audit).

## Contradictions

Cross-read of `FEATURE.md` (Roadmap, UI), `SPRINT-2.md` → Out of scope, `docs/DESIGN.md`
and `docs/DECISIONS.md`. There is no `SPRINT-3.md`, and the Roadmap does not ask for one.
Listed, not resolved.

1. **The screen registry.** `docs/DESIGN.md` → Screens is the project's registry of
   screens and lists neither of this plugin's two, while DECISIONS "No UI design phase;
   message format and configurable blocks" forbids `DESIGN.md` changes for this feature.
   Unresolved since Sprint 1 Step 3, and now two sprints old.
2. **"Any WordPress site" versus `Requires at least: 7.1`.** `FEATURE.md` → Purpose says
   the plugin works on any WordPress site; the header and `readme.txt` declare 7.1,
   which is what this repository ships. DECISIONS "The check command runs on PHP 8.3+"
   parks lowering it as a packaging question, and `SPRINT-2.md` → Out of scope excludes
   packaging — so the claim and the header disagree until someone installs it elsewhere.
3. **What a step may do.** `SPRINT-2.md` → Step 4 is titled "Production readiness on
   both Dovira installs" and its Tasks say the checklist is "executed by the developer",
   while its own Verification says it is "verified by the sprint-boundary deploy" and the
   git model forbids a step to deploy. The step was planned and closed as: write and
   rehearse here, execute at the boundary. A future sprint file should not phrase a step
   as work on a live environment.
4. **The dev environment is in the git model but not in the feature's plan.**
   `ARCHITECTURE.md` → Environments has `dev.dovira.vet` deploying on every push to
   `dev`, and Sprint 1 deferred configuring the plugin there; neither Sprint 2's steps
   nor its Definition of Done mention `dev` at all — the DoD names only the two
   productions.

## LEARNINGS

Entries from this sprint still marked `Transferred to playbook: pending` — the retro fills them:

1. **The first settings save on a fresh install scheduled nothing** (Step 1). Core routes
   a write through `add_option()` while the stored row still equals the registered
   default, so only `add_option_{option}` fires. Rule taken: read core before relying on
   which hook fires.
2. **Running a cron event by hand moved the daily slot** (Step 1). `wp cron event run`
   reschedules from now, so a verification run shifts the next real one.
3. **A whole options row was printed to read two of its fields, and it held the real key
   and the bot token** (Step 2). Rule taken: never print a row that can contain a
   credential — read the fields you need by name, one at a time.
4. **The plan described the dev site from memory, and the dev site had moved on**
   (Step 2). Rule taken: a plan's claims about an environment are checked against it, not
   recalled.
5. **The plan's file list missed the code area's own `CLAUDE.md`** (Step 2) and, one step
   later, **a config the gate reads** (Step 3). Rule taken, wider: when a step adds a
   file, check each gate config for whether it has to learn about it.
6. **A test that asserted nothing passed the gate** (Step 3). `phpunit.xml.dist` sets
   `failOnWarning`/`failOnNotice`/`failOnDeprecation` but not `failOnRisky`.
7. **The link was asserted, not opened, and what it opened was unreadable** (Step 4).
   Rule taken, widening Sprint 1's screen rule: a target the UI points at is opened and
   read in a browser, not fetched and not asserted.

Sprint 1's five entries are still `pending` as well; this is the first retro that can
close either set.
