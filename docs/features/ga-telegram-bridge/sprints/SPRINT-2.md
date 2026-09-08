# SPRINT 2 — Runs by itself (after Sprint 1)

<!-- Written by discovery (Phase C) together with every other sprint of the
     plan — never by Claude Code. Rewritten by a re-planning chat only while
     no step is closed; afterwards steps may only be appended. When the last
     step closes, /close-step writes SPRINT-2-CLOSE.md next to this file. -->
**Branch:** `ga-telegram-bridge/sprint-2-runs-by-itself` → `master`
**Goal:** The owner of each Dovira city site receives the daily report every
morning without anyone touching the admin: the plugin fires at the configured
local time, retries an hour later on a Google or Telegram failure, tells the
chat when a day could not be reported, never sends a day twice, shows the next
run and the recent history in the admin, and cleans up after itself on
uninstall. A readme lets another site set the plugin up from scratch.

## Fixed decisions
- WP-Cron daily event, hourly single-event retries, max attempts setting, failure notice, date guard — DECISIONS "Scheduling, retries and idempotency on WP-Cron"
- Storage in `gatb_settings` / `gatb_state` / `gatb_log`, `uninstall.php` removes them — DECISIONS "Plugin structure, storage and secrets"
- Everything fixed in Sprint 1 stays fixed (see `SPRINT-1.md` → Fixed decisions)
- Read `sprints/SPRINT-1-CLOSE.md` first: its Deferred and Contradictions lists are this sprint's day-1 agenda

## Steps

### [ ] Step 1 — Scheduler
- **Tasks:**
  - `Scheduler`: on settings save (and on activation when settings exist) compute the next occurrence of the configured `HH:MM` in the site time zone (`wp_timezone()`), convert to a UTC timestamp and register `wp_schedule_event(ts, 'daily', 'gatb_daily_report')` after clearing any existing one; deactivation clears both hooks.
  - The `gatb_daily_report` callback calls `Runner::run('cron')` with the date guard on (target date = "yesterday" in the site time zone, stored as `Y-m-d` in `gatb_state.last_report_date` after success).
  - Screen `Settings`: show "Next run: {local datetime}" from `wp_next_scheduled()`, and a warning when `DISABLE_WP_CRON` is true and no external cron hit `wp-cron.php` in the last 24 h (last-hit timestamp kept in `gatb_state`).
- **Tests:** next-occurrence maths across DST and around midnight (fixed clock); re-registration on save replaces the old event; the date guard blocks a second cron run for the same date and allows the next day.
- **Verification (manual):** on the local site set the time to a few minutes ahead, hit the front page after that time — the report arrives once; hit it again — nothing is sent, `Run log` shows one entry; `wp cron event list` shows a single `gatb_daily_report`.
- **Docs to update:** `docs/DATA-MODEL.md` (`gatb_state.next_run`, `last_cron_hit`); `docs/ARCHITECTURE.md` → Data flows ("Daily GA report" flow, scheduled part).
- **Depends on:** —

### [ ] Step 2 — Retries and the failure notice
- **Tasks:**
  - In `Runner`: on `GoogleAuthException` / `GaClientException` / `TelegramException` increment `gatb_state.attempt`, log `failed` with the mapped reason (no secrets), and schedule `wp_schedule_single_event(now + 1h, 'gatb_retry_report', [date])` while `attempt < max_attempts`; on the final failure send `MessageRenderer::renderFailure(date)` (best effort — a failure of the notice itself is only logged) and reset `attempt`.
  - Telegram 429: honour `retry_after` inside the same run once, then treat as a failure.
  - Reset `attempt` to 0 on success; the retry callback uses the date it was scheduled with, not "yesterday" at retry time.
- **Tests:** attempt counting and scheduling per failure type; the final-attempt notice; a retry after midnight still reports the original date; success resets state; the notice never contains a token or key fragment (assert on the fixture messages).
- **Verification (manual):** on local, set `max_attempts` = 2, break the property id, trigger the cron: `Run log` shows `failed` + a scheduled retry; run `wp cron event run gatb_retry_report` — the chat receives the failure notice, the log shows the reason.
- **Docs to update:** `docs/ARCHITECTURE.md` → Integrations (failure behaviour for both rows); `docs/LEARNINGS.md` if a failure mode surprised.
- **Depends on:** Step 1

### [ ] Step 3 — Lifecycle, readme, translations
- **Tasks:**
  - `uninstall.php`: delete the three options, the transient and both cron hooks; deactivation hook clears cron only (settings survive deactivation).
  - `readme.txt` (WordPress readme format): what the plugin sends, Google Cloud setup (project, enable Google Analytics Data API, service account, Viewer on the property, JSON key), Telegram setup (bot, chat id, channel admin), the two `wp-config.php` constants, the WP-Cron caveat with the `DISABLE_WP_CRON` + system cron recipe, data freshness note, requirements (PHP 8.1, `openssl`, outbound HTTPS).
  - Regenerate the `.pot`, complete `uk_UA` for all strings added in Sprints 1–2.
- **Tests:** `uninstall.php` removes every key it owns (Brain\Monkey expectations on `delete_option`/`wp_clear_scheduled_hook`).
- **Verification (manual):** deactivate → `wp cron event list` has no `gatb_*`; delete the plugin from wp-admin → no `gatb_*` rows in `wp_options`; a fresh local install set up by following only the readme reaches a successful "Send now".
- **Docs to update:** `docs/DATA-MODEL.md` (uninstall note); `docs/features/ga-telegram-bridge/FEATURE.md` → Interfaces if any hook was added.
- **Depends on:** Step 2

### [ ] Step 4 — Production readiness on both Dovira installs
- **Tasks:**
  - Pre-flight checklist executed by the developer (this step writes and runs it, not code): both `wp-config.php` files carry the two constants; each install's numeric property id points at its own GA4 property (the one whose web data stream has measurement id `G-HKYZFG0E2W` for Kharkiv and `G-Q597WTF16L` for Kyiv, per `docs/ARCHITECTURE.md` → Integrations — a measurement id is not a property id); each install has its own chat id; send time agreed with the owner; `Check GA` and `Check Telegram` green on both.
  - Harden the admin page for production: the run log shows the plugin version; the settings page links to the readme sections.
  - Confirm that hosts allow outbound HTTPS to `oauth2.googleapis.com`, `analyticsdata.googleapis.com`, `api.telegram.org`, and have `openssl`.
- **Tests:** — (operational step; existing suite stays green)
- **Verification (manual):** verified by the sprint-boundary deploy: after the deploy of `master` (Kharkiv) and `kyiv` (Kyiv), two consecutive mornings deliver both reports; `Run log` on each site shows `sent` entries with `trigger: cron`.
- **Docs to update:** `docs/ARCHITECTURE.md` → Environments (plugin configuration per install); `docs/WORKLOG.md` via /close-step; `docs/DECISIONS.md` if the owner changes the block set or time.
- **Depends on:** Step 3

## Definition of Done
- [ ] Every step closed via /close-step (report + verification guide + worklog)
- [ ] `bin/check.sh` green on the sprint branch
- [ ] Docs match reality (DATA-MODEL, ARCHITECTURE, DECISIONS, TECH-STACK current)
- [ ] Merged to `master`; `master` merged into `kyiv`; both productions deployed by hand; constants set in both `wp-config.php`; the owner receives the Kharkiv and Kyiv reports on two consecutive mornings
- [ ] `SPRINT-1-CLOSE.md` Deferred/Contradictions items settled or carried into DECISIONS.md

## Out of scope
- Key events / conversions block — not planned (FEATURE.md → Roadmap)
- Weekly digest, second template — not planned
- Several properties or several chats per install — not planned
- WP-CLI command / REST trigger for external cron — not planned; system cron calling `wp-cron.php` is the documented alternative
- Packaging (zip build, wordpress.org submission) — not planned
- Changes to the theme's Telegram bot (DECISIONS open questions 1–2) — separate discovery

## Risks / notes
- The owner's chats: a channel needs the bot as administrator; a private chat needs the owner to have started the bot first — chase on day 1 of Step 4.
- WP-Cron on the production hosts depends on traffic; a quiet morning delays the report — accepted, documented in the readme.
- If the dev host blocks outbound HTTPS or lacks `openssl`, Step 4 raises it — production hosts must be checked before the deploy.
