# Feature — ga-telegram-bridge

<!-- Lightweight ARCHITECTURE + DATA-MODEL for one feature. Lives at
     docs/features/ga-telegram-bridge/FEATURE.md next to its sprints/. Root
     ARCHITECTURE.md holds only one row + a link here. Keep ≤80 lines. -->

## Purpose & scope
"Google Analytics → Telegram bridge": a standalone WordPress plugin that, once a
day at a configured time, reads one GA4 property through the Data API, builds a
short report and sends it to one Telegram chat (a channel or a person), so a
business owner who never opens GA sees whether people come to the site and from
where. It works on any WordPress site; on Dovira it is installed separately on
each city install. OUT of scope: key events / conversions, a weekly digest,
several properties or chats per install, joining a recipient through a bot
password/webhook, OAuth sign-in, exact external cron, distribution packaging
and wordpress.org, any change to the theme's own Telegram code.

## Fit into the host
- **Code location:** `wp-content/plugins/ga-telegram-bridge/`
- **Host area:** plugin `ga-telegram-bridge` — obeys its own `CLAUDE.md`
- **Entry point:** `ga-telegram-bridge.php` (plugin header, autoloader, `Plugin::boot()`); the theme has no registration line — the plugin is activated in wp-admin
- **Shared code it depends on:** — (WordPress core only: HTTP API, Settings API, WP-Cron, i18n, transients)

## Data
Owns three non-autoloaded `wp_options` rows (details in `docs/DATA-MODEL.md`):
`gatb_settings` (property id, service-account JSON, bot token, chat id, send
time, max attempts, enabled blocks — six: `visitors` always on, `pages`, `channels`, `cities`, `devices`, `trend`), `gatb_state` (`last_report_date`,
`attempt`, `last_cron_hit` — the next run is not stored: `wp_next_scheduled()` is
the one copy of it), `gatb_log` (last 30 runs); one transient
`gatb_google_access_token`; cron hooks `gatb_daily_report` (recurring) and
`gatb_retry_report` (single, carrying the day it is for as its one argument). Constants `GATB_GA_SERVICE_ACCOUNT_JSON` and
`GATB_TELEGRAM_BOT_TOKEN` override the two secrets. No other feature writes
to this data; `uninstall.php` removes all of it when the plugin is deleted, while deactivation takes only the two cron events.

## Invariants
- A report for a given date is sent at most once: every run compares the day of
  the report it has built with `gatb_state.last_report_date` before sending, and
  stops there when they match; only the admin's "Send now" bypasses it.
- A day is attempted at most `max_attempts` times: a scheduled run that fails is
  repeated an hour later while attempts remain, and the last one sends the
  failure notice to the same chat and starts the counter again — the day itself
  stays unsent. A run started by *Send now* is never repeated. A retry cannot
  read a day the property has already left behind: it reports that day as failed
  instead of sending another one under its date.
- Secrets never appear in `gatb_log`, error messages, notices or test output.
- All GA date ranges are relative (`yesterday`, `NdaysAgo`) and thus resolved
  in the property's reporting time zone; the send time is site-local.
- The schedule is re-anchored at the end of every scheduled run: the next `gatb_daily_report` is registered from the configured local time, never by adding a fixed interval, so the report keeps its time of day across a clock change without anyone re-saving the settings. Only the schedule does this — *Send now* and a retry never move it.
- Never more than 2 `batchRunReports` calls per run; a disabled block issues no request.
- Public pages never call Google or Telegram synchronously: all network work
  runs inside the cron callback or an admin-initiated request.
- Zero runtime Composer dependencies; `openssl` is required and checked at activation.

## Interfaces
- **Admin:** Settings → "GA → Telegram" (`manage_options`), screen names in UI below.
- **Cron hooks:** `gatb_daily_report` (no arguments) and `gatb_retry_report` (one argument, the day `Y-m-d` the attempt is for) — the only schedulers are in `Scheduler`, and both are cleared with `wp_unschedule_hook()` so an event carrying arguments goes too.
- **Filters (public surface, stable):** `gatb_report_data` (the normalized `Report` before rendering — `apply_filters( 'gatb_report_data', Report $report )`; a return value that is not a `Report` is ignored and the built one is rendered; a page row's `title` is the title of the post at that path, or the path when no post lives there; `visitors_by_day` carries the trend block's 28 daily figures as `array<string, int>` keyed `Y-m-d`, oldest first, and is `null` when that block is off — added in Sprint 3, so a reader written for Sprint 2 sees one more readonly property and nothing moved) and `gatb_extra_blocks` (since 0.3.0: blocks other code on the site adds to the report — `apply_filters( 'gatb_extra_blocks', array $blocks, Report $report )`, starting from `array()`, with the report `gatb_report_data` handed back; a list of HTML strings in Telegram's parse mode, printed as they come — the caller escapes its own values — each as one more block after the plugin's own blocks and before the GA link, one blank line before it, in the order given; a return that is not an array is ignored, and an entry that is not a string or is empty after `trim()` is dropped; never applied to the failure notice; DECISIONS "A theme feature that records site search, plugged into the daily report through a new plugin filter") and `gatb_message_html` (final HTML before sending — `apply_filters( 'gatb_message_html', string $html, ?Report $report )`, `null` for the failure notice). All three are applied in `MessageRenderer`. Changing their payload = "touches shared surface".
- **CLI / REST:** none in v1.

## UI
- **Screens:** `Settings` (wp-admin page: credentials, recipient, schedule, blocks, buttons *Check GA*, *Check Telegram*, *Preview*, *Send now*; the Schedule section prints when the next run is due — read from `wp_next_scheduled()`, never stored — and warns when `DISABLE_WP_CRON` is set and nothing has called `wp-cron.php` for 24 h; states: unconfigured, secrets set in configuration, check ok/error, nothing scheduled yet; each configuration section links into `readme.txt` — Google and Telegram to the Installation steps that set them up, Schedule to the WP-Cron recipe), `Run log` (table on the same page under the buttons, newest first: time, what started the run, the day the report was about, sent or failed, attempt and one line of detail; above it the version this build declares, read from the plugin header and printed once rather than per row — the log records what a run did, not what it was made by — and a link to the readme's FAQ for a report that did not arrive; states: empty, with errors. Six columns and no **next run** column: the next run is one value about the future and belongs to the Schedule section above, not to a list of past runs). No design export — stock wp-admin components.
- **Reuses:** — (not a theme screen; `docs/DESIGN.md` does not apply)
- **Introduces:** —
- **Message template (HTML parse mode; `{}` = data, `[...]` = block, blocks 2–5 optional; one line per row, one blank line between the blocks that are printed, never a blank line for a block left out):**
  ```
  📊 <b>{site_host} — {report_date, "9 Вересня (Вівторок)"}</b>

  👥 <b>Відвідувачі</b>
  Вчора: {n} ({▲|▼} {pct}% до середнього за 7 днів)
  За 28 днів: {n} ({▲|▼} {pct}% до попередніх 28)
  [<code>{28 × ▁▂▃▄▅▆▇█, oldest day left, yesterday right}</code> {min}–{max}]

  [📄 <b>Топ‑5 сторінок вчора</b>  1. <a href="{home origin}{path}">{post title}</a> — {views} … ]

  [📅 <b>Топ‑5 сторінок за 28 днів</b>  1. <a href="{home origin}{path}">{post title}</a> — {views} … ]

  [🧭 <b>Джерела за 28 днів</b>  {channel} {pct}% · {channel} {pct}% · … ]

  [📍 <b>Міста за 28 днів</b>  {city} {pct}%, one city per line … ]

  [📱 <b>Пристрої за 28 днів</b>  {device} {pct}%, one device per line … ]

  [{blocks from gatb_extra_blocks, in the order given, one blank line before each}]

  🔗 <a href="https://analytics.google.com/analytics/web/#/p{property_id}/reports/intelligenthome">Детальніше в Google Analytics</a>
  ```
  The closing link is always present on a report (never on the failure notice), built from the property id alone (DECISIONS "The message ends with a link into the GA4 property").
  A page is named by the post at its path, or by the path when no post lives there (DECISIONS "Top pages are counted by path and named by their post"), and a path that is not an address — GA's `(not set)` — is printed without a link. The emoji are not part of the translated titles.
  Failure notice: `⚠️ <b>{site_host}</b> — звіт за {report_date} не сформовано. Деталі в журналі плагіна.`
  Source strings are English (text domain `ga-telegram-bridge`); the `uk` translation shipped in `languages/` is what the template above shows; `—` when a baseline is 0.
  The date is `wp_date()` on the property's own day: WordPress declines the month itself and capitalises month and weekday the way its Ukrainian translation writes them, hence "9 Вересня (Вівторок)".
  The trend line (block `trend`, Sprint 3) sits inside the visitors block with no heading and no blank line of its own: 28 characters, one per day from `28daysAgo` to `yesterday`, scaled from the period's minimum to its maximum, then the two numbers; a flat period prints 28 × `▄`; a day GA does not return counts as 0; when `trend` is off, the line is absent (DECISIONS "A 28-day trend sparkline in the visitors block").
  A block with no rows is left out exactly like a switched-off one — the message has no place for a heading with nothing under it, though the `Report` keeps the two states apart (`null` = off, `array()` = on and empty).

## Roadmap
- Sprint 1 — the full report reaches Telegram from the admin's "Send now" on the dev site (`sprints/SPRINT-1.md`)
- Sprint 2 — the report goes out by itself every day on both production installs, with retries and a run log (`sprints/SPRINT-2.md`)
- Sprint 3 — the visitors block shows the 28-day trend as a sparkline, and the daily event no longer drifts across a DST change (`sprints/SPRINT-3.md`)
- Later (not planned): key events block, weekly digest, a PNG chart via `sendPhoto`, GA labels translated, several properties/chats, packaging for other sites
