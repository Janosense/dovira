# SPRINT 2 CLOSE — Runs by itself (ga-telegram-bridge)

Written by `/close-sprint` (Phase 1) on 2026-09-15, from the files, the code and git. It
replaces the version `/close-step` wrote at the close of Step 4 on 2026-09-10 and takes in
what reached `master` since: the report-format ad-hoc of 2026-09-10 and playbook v1.17.
It is the handoff to the sprint boundary, to the retro, and to whatever follows this feature.
Phase 2 updated the Definition of Done on 2026-09-16, when the boundary was reported done: the two
rows below that read `open` then were settled, and the sections after them are the 2026-09-15 snapshot.

## Definition of Done

| Item | Evidence |
|---|---|
| Every step closed via /close-step (report + verification guide + worklog) | **ticked** — Steps 1–4 are `### [x]` in `SPRINT-2.md` and `(status: closed)` in `SPRINT-2-PLAN.md`; close commits `6c51a32`, `d1eb20b`, `3bb5d43`, `a40a32e`; guides `verification/sprint-2-step-1.md` … `-4.md`; WORKLOG entries 2026-09-09 ×2 and 2026-09-10 ×2 |
| `bin/check.sh` green on the sprint branch | **ticked** — simple git model: each step's task branch was merged into `master` (`818a8da`, `2e071b6`, `320f147`, `79967be`) and no sprint branch exists, so the gate ran on `master` at `67589b4` on 2026-09-15 and exited 0: PHPCS clean, PHPStan level 8 `[OK] No errors`, `OK (269 tests, 854 assertions)`, 108 theme files linted |
| Docs match reality (DATA-MODEL, ARCHITECTURE, DECISIONS, TECH-STACK current) | **ticked** — DATA-MODEL has `gatb_state.last_cron_hit`, the attempt counter's reset, `gatb_retry_report` with its one argument, and the uninstall list. ARCHITECTURE's plugin row names `Runner`, `RunLog`, `Scheduler` and `uninstall.php`, both integration rows give the failure behaviour, and Environments has the per-install configuration paragraph. DECISIONS gained "The schedule is registered from the settings alone…" and "Only the schedule retries…" (2026-09-09), and the ad-hoc added "Top pages are counted by path and named by their post" (2026-09-10), together with its `FEATURE.md` → UI and `TESTING.md` changes. TECH-STACK is unchanged: no dependency was added. Each step's docs self-check is in its close commit. `DESIGN.md` is outside this item's list, and its gap is Contradiction 1 |
| Merged to `master`; `master` merged into `kyiv`; both productions deployed by hand; constants set in both `wp-config.php`; the owner receives the Kharkiv and Kyiv reports on two consecutive mornings | **ticked** — the repo half verified again on 2026-09-16: the four step merges are on `master`, pushed to `origin/main` (`730ef5a`), and `master` is merged into `kyiv` (`06fc831` on the remote, `7b057c9` locally), where `wp-content/plugins/ga-telegram-bridge` is present. The two production deploys by hand, the two constants in each `wp-config.php` and the two consecutive mornings — sections B–E of `PRODUCTION-CHECKLIST.md` — confirmed by user 2026-09-16; nothing in the repo can show them |
| `SPRINT-1-CLOSE.md` Deferred/Contradictions items settled or carried into DECISIONS.md | **ticked** — Settled: `spike/` is gone; the failure notice ships (Step 2); scheduling, retries, the next-run display and `last_cron_hit` ship (Steps 1–2); `uninstall.php` and the deactivation hook ship (Step 3); the duplicate path in the 28-day pages block is settled by DECISIONS "Top pages are counted by path…"; Contradictions 1, 2, 3, 4 and 6 are resolved. The theme's own Telegram bot is in DECISIONS as open questions 1–2. The last three settled on 2026-09-16: **the push to `dev`** by DECISIONS "The feature's dev site is the local DDEV install; `dev.dovira.vet` is not part of its flow" (2026-09-16, `0cfb3d3`), which closes Contradiction 4 below; **GA's English labels** deferred again in `SPRINT-3.md` → Out of scope; **the screen registry** (Contradiction 1 below) left standing as a known contradiction rather than a blocker, carried in `SPRINT-3.md` → Risks — confirmed by user 2026-09-16 |

## Built

Everything in `wp-content/plugins/ga-telegram-bridge/` (namespace `GaTelegramBridge`).
What Sprint 2 and the ad-hoc that followed it add to what Sprint 1 left:

| File | What this sprint put there |
|---|---|
| `src/Scheduler.php` | the only class that touches cron: `reschedule()` (the next occurrence of the configured `HH:MM` in the site's zone, one recurring `gatb_daily_report`), `next_run()`, `schedule_retry()` / `RETRY_DELAY`, `run_daily()`, `run_retry()`, `clear()` through `wp_unschedule_hook()`, `note_cron_hit()`, `external_cron_missing()` |
| `src/Runner.php` | the failure path: `failed()` (book another attempt while the day has attempts left, otherwise notify the chat and reset the counter), `is_automatic()` and `notify()`. A retry whose day the property has already left behind gives that day up |
| `src/RunLog.php` | `gatb_state.last_cron_hit`, merging state writes, `reset_attempt()` |
| `src/TelegramClient.php` | one in-run wait on a 429 naming `retry_after` ≤ 30 s, then the failure it looks like |
| `src/Admin.php` | the Schedule section's *Наступний запуск* and the `DISABLE_WP_CRON` warning; the run log's version line; four links into `readme.txt` (`readme_url()`, `readme_link()`) |
| `src/Plugin.php` | the retry hook registered with one accepted argument, both settings-save actions, `wp_loaded` → cron hit, and `version()` (read from the plugin header, never copied) |
| `src/ReportBuilder.php`, `src/MessageRenderer.php` | *(ad-hoc 2026-09-10)* both pages requests grouped by `pagePath` alone, each row named by the post at that address (`page_url()`), trusted only when the post's permalink is that address; the message links each page, leaves one blank line between blocks and puts an emoji before each heading |
| `uninstall.php` | removes the three options, the cached token and both cron events when the plugin is deleted. The names are literal, and `UninstallTest` checks them against the class constants |
| `readme.txt` | the WordPress readme: what the plugin sends, Google Cloud and Telegram setup step by step, the two constants, the WP-Cron recipe, the FAQ and the changelog. It is ASCII because the settings screen links to it |
| `languages/` | the `.pot` and the `uk` translation regenerated for the strings of Steps 1–4; the ad-hoc changed neither |
| `tests/` | 54 new tests (215 → 269): 50 across Steps 1–4 in `SchedulerTest`, `RunnerTest`, `RunLogTest`, `TelegramClientTest`, `PluginTest`, `AdminTest`, `AdminSendNowTest` and the new `UninstallTest`, and 4 in the ad-hoc, which also added the `tests/SitePosts.php` stub |
| `docs/features/ga-telegram-bridge/PRODUCTION-CHECKLIST.md` | sections A–F, worked through once per install; rehearsed on the local DDEV install at Step 4 |

Data added this sprint: `gatb_state.last_cron_hit`; cron hooks `gatb_daily_report`
(recurring, no arguments) and `gatb_retry_report` (single, carrying its day).
No new option, no table, no dependency.

## Not locally verifiable

- **Both production installs.** Sections B–E of `PRODUCTION-CHECKLIST.md`: the two
  constants in each `wp-config.php`; *Check GA* and *Check Telegram* on each install; the send
  time; outbound HTTPS and `openssl` on both hosts. Then **two consecutive mornings** whose
  newest run log row reads *Розклад · Надіслано · Спроба 1*: that last check is the sprint's
  Definition of Done.
- **The new message format in a real Telegram client**: the links, the blank lines and the
  icons of the ad-hoc. The local check rendered the message and the admin preview, but sent
  nothing to Telegram. The first scheduled morning after the deploy shows it.
- **A fresh install set up from the readme alone**, ending in a successful *Send now*
  (Step 3, §5 of its guide). It needs someone who doesn't already know the answers to enter a
  service-account key and a bot token.
- **Telegram's flood limit.** One bot sending one message a day cannot provoke a 429;
  the fixture is written from Telegram's documentation (Step 2).
- Still open from Sprint 1: **a GA4 property with no traffic**, **activation without
  OpenSSL**, **the 429 quota mapping**.

## Deferred

- **The deploy itself**: `master` to Kharkiv and `kyiv` to Kyiv, by hand, plus the two
  constants on each host. Both branches hold the plugin on the remote; no step may deploy
  (root `CLAUDE.md` → Git model). *Done at the boundary — confirmed by user 2026-09-16; see
  Definition of Done above.*
- **The daily event drifts by an hour across a DST change** until the settings are saved
  again (Steps 1 and 2 WORKLOG). WordPress's `daily` schedule is a fixed 86 400 s
  interval, and nothing re-anchors it. For the retro or an `/adhoc`.
- **Rotate the local install's service-account key and bot token.** They were printed into
  a session transcript twice: a `wp option get` in Step 2 (LEARNINGS 2026-09-09) and a
  screenshot of screen `Settings` in the ad-hoc (LEARNINGS 2026-09-10). Since Step 4 they are
  stored both in that install's `wp-config.php` and in its `gatb_settings` row. Clearing the
  stored copies waits for the rotation. Also from the ad-hoc's LEARNINGS entry: should screen
  `Settings` print a stored secret back into its field at all?
- **`failOnRisky="true"` in `phpunit.xml.dist`**: a one-line change with no failing test
  behind it today, deliberately left out of Step 3 (LEARNINGS, 2026-09-10).
- Carried unchanged from Sprint 1: **GA's own labels stay English** in the Ukrainian message
  (`mobile`, `Organic Search`), and **the theme's own Telegram bot** (token as a string
  literal, public webhook routes: DECISIONS open questions 1–2). **The push to `dev`**,
  also deferred by Sprint 1, is Contradiction 4.

## Contradictions

Cross-read of `FEATURE.md` (Roadmap, UI), `SPRINT-2.md` → Out of scope, `docs/DESIGN.md`
and `docs/DECISIONS.md`, and git where a file makes a claim about branches. There is no
`SPRINT-3.md`, and the Roadmap ends at Sprint 2 ("Later (not planned)"). Listed, not resolved.

1. **The screen registry.** `docs/DESIGN.md` → Screens is the project's registry of
   screens and lists neither of this plugin's two screens. DECISIONS "No UI design phase;
   message format and configurable blocks" forbids `DESIGN.md` changes for this feature.
   Unresolved since Sprint 1 Step 3.
2. **"Any WordPress site" versus `Requires at least: 7.1`.** `FEATURE.md` → Purpose says
   the plugin works on any WordPress site; the plugin header and `readme.txt` declare 7.1,
   which is the core version this repository ships. DECISIONS "The check command runs on PHP
   8.3+" treats lowering it as a packaging question, and `SPRINT-2.md` → Out of scope
   excludes packaging.
3. **What a step may do.** `SPRINT-2.md` → Step 4 is titled "Production readiness on
   both Dovira installs" and its Tasks say the checklist is "executed by the developer".
   Its own Verification says "verified by the sprint-boundary deploy", and the git model
   forbids a step to deploy. The step was planned and closed as "write and rehearse in the
   step, execute at the boundary".
4. **The `dev` environment.** `ARCHITECTURE.md` → Environments has `dev.dovira.vet`
   deploying only on a push to branch `dev`. `SPRINT-1.md` → Definition of Done ticks
   "deployed to dev by the sprint-boundary push to `dev`; plugin activated and configured on
   dev" (`2b7ffed`, 2026-09-10). Neither `dev` nor `origin/dev` contains
   `wp-content/plugins/ga-telegram-bridge` (`origin/dev`'s newest commit is from 2026-06-05).
   The WORKLOG entries call the local DDEV install "the dev site". Neither Sprint 2's steps
   nor its Definition of Done mention `dev`.
5. **`origin/main`.** DECISIONS "Verification profile and deploy model" says `origin/main`
   is unused, and `ARCHITECTURE.md` → Environments says it "is not part of this flow".
   In git, `master`'s upstream is `origin/main` (`branch.master.merge = refs/heads/main`),
   the remote has no `master`, and `origin/main` is where the plugin and Sprint 2 were
   pushed (`730ef5a`). Production Kharkiv deploys "from `master`".

## LEARNINGS

Entries from this sprint still marked `Transferred to playbook: pending`. The retro fills them
with a version, `local` or `n/a`:

1. **The first settings save on a fresh install scheduled nothing, and the search for why
   started in the wrong place** (Step 1, 2026-09-09).
2. **The plan's file list missed the code area's own CLAUDE.md again** (Step 2, 2026-09-09):
   the entry itself asks the retro to move the rule into `/plan-step`.
3. **The plan described the dev site from memory, and the dev site had moved on**
   (Step 2, 2026-09-09).
4. **A whole options row was printed to read two of its fields, and it held the real key
   and the bot token** (Step 2, 2026-09-09).
5. **The plan's file list missed a config the gate reads, one step after the last time**
   (Step 3, 2026-09-10).
6. **A test that asserted nothing passed the gate** (Step 3, 2026-09-10).
7. **The link was asserted, not opened, and what it opened was unreadable** (Step 4,
   2026-09-10).
8. **A screenshot of the settings screen put the real key and the bot token into the
   transcript** (report-format ad-hoc, 2026-09-10).

"Running a cron event by hand moved the daily slot" (Step 1) is already `n/a — project-technical`.

Sprint 1's retro left five entries `pending`, though its Definition of Done was ticked on
2026-09-10: "A screen was called finished without anyone opening it", "Two files looked
unchanged because DDEV had not synced them yet" (its second half), "An option was put to
the user with a benefit that had never been measured", "The gate failed on code that had no
errors…" and "A pathspec commit silently dropped every new file". This retro can close
both sets.
