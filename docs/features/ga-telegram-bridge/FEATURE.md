# Feature — ga-telegram-bridge

<!-- playbook: v3.0.4. State document, read whole at the start of every step
     of the feature. Shape, triggers and what does not belong here:
     docs/CONTRACTS.md → `### docs/features/{feature}/FEATURE.md`. -->

## Scope
"Google Analytics → Telegram bridge": a standalone WordPress plugin that,
once a day at a configured time, reads one GA4 property through the Data API
and sends a short report to one Telegram chat, so a business owner who never
opens GA sees whether people come to the site and from where. It works on any
WordPress site; on Dovira each city install runs it with its own property and
chat.

## Fit
- **Code path:** `wp-content/plugins/ga-telegram-bridge/` (its own `CLAUDE.md`)
- **Entry point:** `ga-telegram-bridge.php` (header, autoloader, `Plugin::boot()`), activated in wp-admin; the theme has no registration line
- **Depends on:** — (WordPress core only: HTTP API, Settings API, WP-Cron, i18n, transients; PHP OpenSSL, checked on activation)
- **Exposes:** the filters `gatb_report_data` (`Report $report` → `Report`; another return is ignored), `gatb_extra_blocks` (`array $blocks, Report $report` → a list of HTML strings in Telegram's parse mode, printed after the plugin's blocks and before the GA link, each escaped by its caller; non-strings and empty strings dropped; never applied to the failure notice) and `gatb_message_html` (`string $html, ?Report $report` → the final HTML, `null` report for the failure notice), all applied in `MessageRenderer`; the cron hooks `gatb_daily_report` (no arguments) and `gatb_retry_report` (the day, `Y-m-d`)

## Structure
| Module | Where | Responsibility |
|---|---|---|
| `Plugin` | `src/Plugin.php` | boot, activation guard, default option, hooks |
| `Settings` | `src/Settings.php` | the option `gatb_settings`: defaults, validation, typed getters, constant overrides |
| `Admin` | `src/Admin.php` | the settings screen, its buttons behind admin-post nonces, the run log |
| `GoogleAuth`, `GaClient` | `src/` | the service-account JWT, the access token, Data API calls |
| `TelegramClient` | `src/TelegramClient.php` | one HTML message to one chat |
| `ReportBuilder`, `Report`, `Dynamics` | `src/` | the day's reports in at most two calls, read into one value object |
| `MessageRenderer`, `Sparkline` | `src/` | the report as HTML, the trend line, the failure notice, the filters |
| `Runner` | `src/Runner.php` | the one path of a report: build, date guard, render, send, log |
| `RunLog` | `src/RunLog.php` | the options `gatb_state` and `gatb_log` |
| `Scheduler` | `src/Scheduler.php` | the only class that touches cron |
| `uninstall.php` | plugin root | removes the options, the transient and both events on deletion |

## Data
- options `gatb_settings`, `gatb_state`, `gatb_log`
- transient `gatb_google_access_token`
- cron events `gatb_daily_report`, `gatb_retry_report`

## UI
| Screen | Route | In code | Artboard |
|---|---|---|---|
| Settings | wp-admin → Settings → «GA → Telegram» (`manage_options`) | `Admin` | none — stock wp-admin |
| Run log | the same page, under the buttons | `Admin` | none — stock wp-admin |
| Daily report | the Telegram message | `MessageRenderer` | none — the template below |

The Settings screen holds the credentials, the recipient (chat and optional
thread), the schedule with the next run from `wp_next_scheduled()` and a
warning when WP-Cron has not run for 24 hours, the blocks, and the buttons
*Check GA*, *Check Telegram*, *Preview*, *Send now*; each section links to
its part of `readme.txt`. The Run log shows the plugin's version once, then
the runs newest first: time, trigger, the report's day, sent or failed,
attempt, one line of detail.

The message (HTML parse mode; blocks 2–6 optional, one blank line between
printed blocks):
```
📊 <b>{site_host} — {report_date, "9 Вересня (Вівторок)"}</b>

👥 <b>Відвідувачі</b>
Вчора: {n} ({▲|▼} {pct}% до середнього за 7 днів)
За 28 днів: {n} ({▲|▼} {pct}% до попередніх 28)
[<code>{28 × ▁▂▃▄▅▆▇█}</code> {min}–{max}]

[📄 <b>Топ‑5 сторінок вчора</b>  1. <a href="{home origin}{path}">{post title}</a> — {views} …]

[📅 <b>Топ‑5 сторінок за 28 днів</b>  1. <a href="{home origin}{path}">{post title}</a> — {views} …]

[🧭 <b>Джерела за 28 днів</b>  {channel} {pct}% · …]

[📍 <b>Міста за 28 днів</b>  {city} {pct}%, one per line]

[📱 <b>Пристрої за 28 днів</b>  {device} {pct}%, one per line]

[{blocks from gatb_extra_blocks, in order}]

🔗 <a href="https://analytics.google.com/analytics/web/#/p{property_id}/reports/intelligenthome">Детальніше в Google Analytics</a>
```
A page is named by the post at its path, or by the path, and GA's `(not set)`
is printed without a link; `—` replaces a percentage whose baseline is 0; a
flat trend is 28 × `▄`; a block with no rows is left out like a switched-off
one. The failure notice: `⚠️ <b>{site_host}</b> — звіт за {report_date} не
сформовано. Деталі в журналі плагіна.` Source strings are English (text domain
`ga-telegram-bridge`) with the `uk` translation in `languages/`.

## Roadmap
- Sprint 1 — the full report reaches Telegram from the admin's "Send now" on the dev site (`sprints/SPRINT-1.md`)
- Sprint 2 — the report goes out by itself every day on both production installs, with retries and a run log (`sprints/SPRINT-2.md`)
- Sprint 3 — the visitors block shows the 28-day trend, and the daily event keeps its time across a clock change (`sprints/SPRINT-3.md`)
