# SPRINT 3 — Trend at a glance (from 2026-09-16)

<!-- Written by a re-planning chat (Feature mode, playbook v1.17) after
     `/close-sprint` closed Sprint 2 — never by Claude Code. Rewritten by a
     re-planning chat only while no step is closed; afterwards steps may only
     be appended. When the sprint is closed, /close-sprint writes
     SPRINT-3-CLOSE.md next to this file. -->
**Branch:** `ga-telegram-bridge/sprint-3-trend` task branches → `master`
**Goal:** The owner's daily message shows, under the 28-day visitors line, a
28-character sparkline of the last four weeks with its lowest and highest day
beside it, so the trend is visible without opening anything; the site
administrator can switch that line off like any other block. The report keeps
arriving at the configured local time after the clocks change in October,
without anyone re-saving the settings. The gate refuses a test that asserts
nothing.

## Fixed decisions
- DECISIONS "A 28-day trend sparkline in the visitors block, drawn with block characters" (2026-09-16): block `trend`, 28 days, min–max scale with the two numbers, `<code>`, one extra `runReport`, ≤2 calls.
- DECISIONS "The daily event is re-anchored after every scheduled run" (2026-09-16).
- DECISIONS "The feature's dev site is the local DDEV install; `dev.dovira.vet` is not part of its flow" (2026-09-16).
- Unchanged from Sprints 1–2: report content and baselines; "Top pages are counted by path and named by their post"; "The date guard runs after the report is built"; "The schedule is registered from the settings alone, and the next run is never stored"; "Only the schedule retries…"; message format (HTML, blocks as checkboxes); zero runtime dependencies.
- A PNG chart, an external chart renderer and a zero-based scale are rejected alternatives of the sparkline decision — an implementation finding that argues for one of them stops the step and goes to DECISIONS.

## Steps

### [x] Step 1 — Trend data: block `trend`, the daily-visitors request and `Report::visitors_by_day`
- **Tasks:**
  - `Settings`: add `trend` to `BLOCKS` (after `devices`), default on, label and description in the blocks section of screen `Settings`; confirm that an install whose stored `blocks` array predates the key gets `trend => true` through the defaults merge, not `false`.
  - `ReportBuilder::requests()`: when `trend` is on, add a seventh request — dimension `date`, metric `activeUsers`, date range `28daysAgo`–`yesterday`, `orderBys` on the `date` dimension ascending, `limit` 28 — and keep the chunking at 5 per call (the sprint's invariant: still ≤2 `batchRunReports` calls; a switched-off `trend` issues no request). Parse it into a 28-element list of integers keyed by `Y-m-d`, filling any day the response omits with 0.
  - `Report`: new `?array $visitors_by_day` (`null` when the block is off; ordered oldest → newest); `to_report()` fills it.
  - Re-record the live fixture for call 2 (now two reports) as `batch-run-reports-daily-call-2.json`, property id scrubbed as before; keep the old shape available for the "trend off" test.
  - `phpunit.xml.dist`: set `failOnRisky="true"` (SPRINT-2-CLOSE → Deferred); fix or delete any test that then fails.
- **Tests:** requests composition with `trend` on/off (7 vs 6 requests, still 2 calls; 5+2 split); parsing with a full 28-row response, with missing days (gaps filled with 0), with an empty response (28 zeros); the defaults merge for a stored `blocks` array without `trend`; the whole suite green under `failOnRisky`.
- **Verification (manual):** screen `Settings` shows the sixth checkbox, on, on the local install whose settings were saved before this step; `wp eval` of `ReportBuilder::build()` against the live property prints 28 dated integers whose sum is in the same range as the 28-day visitors figure (`activeUsers` over a period is not the sum of daily active users — they differ, and the step's report says by how much; only the order and the day keys must match GA4's "Users by day" view).
- **Docs to update:** `docs/DATA-MODEL.md` (`gatb_settings.blocks` gains `trend`); `docs/features/ga-telegram-bridge/FEATURE.md` → Interfaces (`gatb_report_data` payload gains `visitors_by_day` — "touches shared surface", named in the plan); `docs/TESTING.md` (the `failOnRisky` rule replaces the LEARNINGS note).
- **Depends on:** —

### [x] Step 2 — The sparkline in the message
- **Tasks:**
  - New pure helper `Sparkline` (`src/Sparkline.php`): `render( int[] $series ): string` maps each value onto `▁▂▃▄▅▆▇█` between the series' min and max — the min gets `▁`, the max `█`, the rest by linear interpolation rounded to the nearest level; a series whose min equals its max returns 28 × `▄`; an empty or `null` series returns `''`.
  - `MessageRenderer::visitors()`: when `visitors_by_day` is not `null`, append one line `<code>{sparkline}</code> {min}–{max}` right after "За 28 днів", numbers through `number()`, no heading, no blank line; the two numbers are the series' real min and max (`number_format_i18n`). Screen `Settings` → *Preview* shows it without a code change beyond the renderer.
  - One new translatable string at most (the `{min}–{max}` format with its translators comment); regenerate `.pot`, update the `uk` `.po`/`.mo`.
- **Tests:** `SparklineTest` on fixed series: monotonic rise, flat, a single spike, all zeros, values that round to the same level, exactly 28 characters out for 28 in; `MessageRendererTest` snapshots: trend on (line present, inside `<code>`, HTML-escaped digits only), trend off (no line, no blank line left behind), trend on with a flat week; the failure notice unchanged.
- **Verification (manual):** screen `Settings` → *Preview* shows the line under "За 28 днів" in the local install; *Send now* delivers it to the test chat and, opened in a phone Telegram client, the 28 characters sit on one line in a monospace run with the two numbers after them (a screenshot of the chat — not of screen `Settings`, which prints secrets — goes into the verification guide); untick `trend`, save, *Preview* again — the line is gone and the block reads as before.
- **Docs to update:** `docs/features/ga-telegram-bridge/FEATURE.md` → UI (already carries the line; adjust only if a string changed); `readme.txt` (what the plugin sends: the trend line; changelog 0.2.0).
- **Depends on:** Step 1

### [x] Step 3 — Re-anchor the daily event after every scheduled run
- **Tasks:**
  - `Scheduler::run_daily()`: after `Runner::run( 'cron', … )` returns — whatever the outcome, and before any retry is booked by `Runner` — call `reschedule()`, so the recurring `gatb_daily_report` is cleared and registered again for the next occurrence of the configured `HH:MM` in `wp_timezone()`; retries (`gatb_retry_report`) are not touched by this.
  - Make sure re-anchoring inside the running cron callback does not re-fire the event in the same request (WP-Cron has already popped the event; registering a new one ≥ 23 h ahead is safe — assert it in a test with a fixed clock).
  - Bump the plugin version to 0.2.0 in the header (Sprint 3 ships a visible change in the message); `Plugin::version()` reads it, nothing else changes.
- **Tests:** a fixed-clock series of daily runs across the Europe/Kyiv autumn change (2026-10-25) and the spring change: every next run lands on the configured local time, not 86 400 s after the previous one; a failed run still re-anchors; a manual *Send now* does not re-anchor (it is not the schedule); `wp_unschedule_hook` + `wp_schedule_event` are called exactly once per scheduled run.
- **Verification (manual):** on the local install set the send time two minutes ahead, trigger WP-Cron: the report goes out and `wp cron event list` shows exactly one `gatb_daily_report` due at the configured time tomorrow (not "now + 24 h"); screen `Settings` → Schedule prints the same next run.
- **Docs to update:** `docs/ARCHITECTURE.md` → Data flows ("Daily GA report": the re-anchor after the run); `docs/features/ga-telegram-bridge/FEATURE.md` → Invariants (one line: the schedule is re-anchored after every scheduled run); `docs/LEARNINGS.md` (the DST entry gets its resolution); `readme.txt` changelog.
- **Depends on:** Step 1 (shares the branch model and the version bump order; no code dependency)

## Definition of Done
- [ ] Every step closed via /close-step (report + verification guide + worklog) — evidence: three `### [x]` in this file, three guides under `verification/`, three WORKLOG entries
- [ ] `bin/check.sh` green — evidence: exit 0 on `master` after the last merge, with `failOnRisky` in effect
- [ ] Docs match reality (DATA-MODEL, ARCHITECTURE, DECISIONS current) — evidence: `gatb_settings.blocks` lists `trend`; the "Daily GA report" flow mentions the re-anchor; the three 2026-09-16 DECISIONS entries have no contradicting text left in FEATURE.md or the sprint files
- [ ] Merged to `master`; `master` merged into `kyiv`; both productions deployed by hand — evidence: the developer confirms both deploys, and the newest run-log row on each install shows `Розклад · Надіслано` for a morning after the deploy with the trend line visible in the owner's chat
- [ ] The October clock change passes without a shifted report — evidence: the run-log rows of 2026-10-25/26 on either production install carry the configured time (this item is ticked by `/close-sprint`'s second run after the developer confirms it; if the sprint closes before that date, it is confirmed at the next sprint boundary and noted in SPRINT-3-CLOSE)

## Out of scope
- A PNG line chart via `sendPhoto` — rejected alternative (DECISIONS 2026-09-16); a new decision if the owner asks for it after seeing the sparkline
- Translating GA's own labels (`Organic Search`, `mobile`…) — deferred again (SPRINT-2-CLOSE → Deferred), not planned
- Rotating the local install's key and bot token and clearing the stored copies — operational, the developer's task, not a step
- `Requires at least` / packaging for other sites, `docs/DESIGN.md` screen registry, the theme's own Telegram bot — see Risks / notes
- Key events, weekly digest, several properties/chats — not planned (FEATURE.md → Roadmap)

## Risks / notes
- **Sprint 2 close agenda, settled here:** Deferred "DST drift" → Step 3; Deferred `failOnRisky` → Step 1; Contradiction 4 (`dev`) → DECISIONS 2026-09-16. **Still open, carried:** GA labels in English (Deferred); the secret rotation (Deferred); Contradiction 1 (`docs/DESIGN.md` → Screens lists neither plugin screen while DECISIONS "No UI design phase…" forbids DESIGN.md changes — needs a one-line decision either way); Contradiction 2 (`Requires at least: 7.1` vs "any WordPress site" — packaging); Contradiction 3 (wording of Sprint 2 Step 4 — historical, no action); Contradiction 5 (`master` tracks `origin/main` while DECISIONS calls `origin/main` unused — a git fact that needs a DECISIONS entry or a remote rename; raise with the developer before the boundary).
- `activeUsers` summed over 28 single days is not the 28-day `activeUsers` figure (a user active on three days counts three times in the sum, once in the period) — the sparkline shows the shape of daily traffic; the numbers next to it are daily min and max, never a total. Step 1's verification says so explicitly to avoid a false "mismatch" finding.
- `FEATURE.md` is at 93 lines against the template's ≤80: the ad-hoc's UI text grew it. Not this sprint's task; a later re-planning may move the message template into its own reference under `docs/features/ga-telegram-bridge/`.
- The first morning after the deploy is the only real check of how `<code>` block characters render in the owner's Telegram client (iOS/Android/desktop differ in monospace fonts); Step 2's phone screenshot covers one client.
