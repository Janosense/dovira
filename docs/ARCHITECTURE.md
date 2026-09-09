# Architecture — Dovira

<!-- Describes what exists. Adoption mode filled it from the codebase as-is (2026-09-08). -->

## Overview
A single WordPress install per city (Kharkiv `dovira.vet`, Kyiv `kyiv.dovira.vet`)
running the same custom classic theme `wp-content/themes/dovira`. Everything
custom is in the theme: post types, taxonomy, ACF field groups and blocks (PHP
via ACF Builder), page templates, a REST namespace `dovira/v1`, WP-CLI
commands, a custom role, Polylang glue. The only custom plugin is
`ga-telegram-bridge` (its own code area, no theme coupling); there are no
mu-plugins. Content is bilingual (uk source, ru translation) via Polylang.

```
 Browser ──► nginx/PHP-FPM ──► WordPress core 7.1
                                  │
                                  ├─ theme dovira (functions.php bootstrap)
                                  │    ├─ inc/register-post-types.php  → inc/post-types/*.php   (6 CPTs)
                                  │    ├─ inc/register-taxonomies.php  → inc/taxonomies/city.php (service-city)
                                  │    ├─ inc/acf.php                  → inc/acf/{fields,blocks,option-pages}
                                  │    ├─ inc/utils.php                → CF7 hooks, admin columns/filters, Vite manifest
                                  │    ├─ inc/rest-api.php             → dovira/v1 (Telegram, Questionary)
                                  │    ├─ inc/custom-roles.php         → customer_support_specialist
                                  │    ├─ inc/polylang.php             → translatable CPTs, per-language ACF options
                                  │    └─ inc/cli.php (WP_CLI only)    → wp dovira translate*, translations-*
                                  │
                                  ├─ plugins: ACF Pro, Polylang, Contact Form 7, Yoast SEO, Cyr2Lat,
                                  │           Simple Custom Post Order, Enable Media Replace, Akismet, Loco Translate
                                  ├─ plugin ga-telegram-bridge (own code area, feature ga-telegram-bridge)
                                  │    └─ ga-telegram-bridge.php → src/ (PSR-4, own autoloader)
                                  └─ MariaDB
 External: Telegram Bot API (notifications + webhook), Anthropic Messages API (CLI translation),
           Google Fonts, GTM/GA4
```

## Feature map
| Feature | Code area | Docs |
|---|---|---|
| `core` | the theme as-is, `wp-content/themes/dovira/` | `docs/features/core/FEATURE.md` |
| `ga-telegram-bridge` | plugin `wp-content/plugins/ga-telegram-bridge/` (own `CLAUDE.md`) | `docs/features/ga-telegram-bridge/FEATURE.md` |

A new feature inside the theme goes into
`wp-content/themes/dovira/inc/features/{name}/` with one registration line in
`functions.php` (see the theme's `CLAUDE.md`); a feature that must run on any
WordPress site goes into its own plugin, as `ga-telegram-bridge` does.

## Modules
| Module | Location | Responsibility / public surface | Must never |
|---|---|---|---|
| Bootstrap | `functions.php` | Loads Composer autoload, `.env` (phpdotenv), defines `TEMPLATE_DIR`, `VITE_SERVER`, `WP_ENVIRONMENT_TYPE`, `ASSETS_DIR_URI`; requires every `inc/*.php` | contain logic beyond requires and constants |
| Theme setup & assets | `inc/template-functions.php` | `after_setup_theme` supports, nav menus (`primary`, `footer_col_1`, `footer_col_2`), Vite asset enqueue via manifest (`starter_theme_vite_asset`), HMR in development, `defer` on scripts, head cleanup, block allow-list (`dovira_allowed_block_types`), JSON-LD (`VeterinaryCare`, `BreadcrumbList`), lowercase-URL 301, `responsible_persons` tracking on edit, CF7 shortcode attr `vacancy`, query vars `status`/`vacancy`, submenu toggle button, Polylang switcher labels | — |
| Content model | `inc/post-types/*.php`, `inc/taxonomies/city.php` | 6 CPTs (`service`, `employee`, `vacancy`, `conversation`, `application`, `questionary`), taxonomy `service-city` on service/employee/vacancy. See DATA-MODEL.md | register anything outside these glob-loaded files |
| ACF definitions | `inc/acf/fields/*.php`, `inc/acf/option-pages/settings.php`, `inc/acf/blocks/{name}/{block.json,fields.php,template.php}` | All field groups in PHP (StoutLogic ACF Builder); 21 blocks auto-registered by directory scan on `acf/init`; options page "Settings" (`acf-options-settings`) | define fields in the ACF admin UI |
| Templates | `header.php`, `footer.php`, `index.php`, `single*.php`, `search.php`, `404.php`, `templates/*.php`, `template-parts/**` | Classic PHP templates; `single-service.php` builds per-city price tabs; `templates/sub-service.php` ("Sub-Service") is a service page template without the price group | — |
| Forms → records | `inc/utils/conversation.php`, `inc/utils/application.php`, `inc/rest-api/QuestionaryController.php` | CF7 form 6 (contact) & 1430 (franchise) → `conversation`; CF7 form 1399 (vacancy) → `application` + CV sideload; REST `POST dovira/v1/questionary/save` → `questionary`. Each then notifies Telegram | send Telegram before the post is saved |
| Admin lists | `inc/utils/{conversation,application,questionary}.php` | Custom columns and filter dropdowns for the three record CPTs; `processing_date` set on status change | — |
| Telegram bot | `inc/rest-api/TelegramController.php` | Webhook `POST dovira/v1/telegram/handle-updates` (join by password → chat id stored in option `telegram_bot_chats`; `/reset`), `GET …/send-test-message`, `GET …/reset-telegram-log` — all `permission_callback: __return_true` | — |
| Role | `inc/custom-roles/customer-support-specialist.php` | Role `customer_support_specialist` with `*_conversation(s)` caps (shared by `conversation`, `application`, `questionary`), trimmed admin menu/bar, login redirect to Conversations | — |
| Polylang glue | `inc/polylang.php`, `inc/utils/polylang-string-translations.php` | `service`/`vacancy` translatable; ACF options per language via `acf/settings/current_language`; `service-city` term names + UI strings registered as Polylang strings; `dovira_translate_string()` | make `service-city` translatable |
| CLI translation | `inc/cli/*.php` | `wp dovira translate`, `translate-options`, `translations-export`, `translations-import`: extract strings (title, ACF block data, core block HTML, Yoast meta) → Anthropic Messages API (default `claude-haiku-4-5`, JSON schema, chunked 100 items / 8000 chars) → copy post + Polylang link; bundles keyed by source post id + fingerprint | run outside WP-CLI |
| Front-end build | `vite.config.js`, `postcss.config.cjs`, `source/**` → `assets/**` (committed) | Entries `main` (`source/main.js` → `scripts/app.js` + `styles/app.css`) and `admin`; ES modules loaded via dynamic `import()`; Swiper, Fancybox, iMask; admin CSS prefixed `.acf-block-preview` for editor iframe previews | edit `assets/` by hand |
| GA → Telegram bridge (plugin) | `wp-content/plugins/ga-telegram-bridge/` | Standalone plugin, feature `ga-telegram-bridge`: PSR-4 `src/` under namespace `GaTelegramBridge` loaded by its own autoloader, `gatb_` prefix, text domain `ga-telegram-bridge`, zero runtime dependencies, OpenSSL required (checked on activation). In as of Sprint 2 Step 1: `Plugin` (bootstrap, activation guard, option creation, the schedule registered on activation and cleared on deactivation), `Settings` (the option `gatb_settings` — defaults, validation, typed getters, constant overrides for the two secrets), `Admin` (screen Settings → "GA → Telegram", Settings API, `manage_options`, the *Check GA*, *Check Telegram*, *Preview* and *Send now* buttons behind admin-post nonces, the run log under them and the next run in the Schedule section; each result travels back in WordPress's own notice transient, and the preview is lifted out of it on `all_admin_notices` so the screen prints the whole message in a block of its own — the notices themselves are printed by wp-admin, which requires `options-head.php` for every screen under Settings), `GoogleAuth` + `GaClient` (sign in to Google, read the Data API), `TelegramClient` (post one HTML message to one chat), `ReportBuilder` + `Report` + `Dynamics` (compose the day's six GA4 reports into at most two `batchRunReports` calls and read them back as one value object, shares and changes already computed) and `MessageRenderer` (that value object as the HTML message of FEATURE.md → UI, in the site's language, plus the failure notice; the filters `gatb_report_data` and `gatb_message_html` are applied here). Sending the message and the schedule are in as well: `Runner` (the one path a report takes — build, date guard, render, send, log), `RunLog` (the options `gatb_log` and `gatb_state`) and `Scheduler` (the recurring `gatb_daily_report` event, computed from the configured send time in the site zone, re-registered on every settings save and cleared on deactivation — the only class that touches cron). The retries and the failure notice follow in Sprint 2 Step 2 | reference the theme, its functions, options or data; ship its dev `vendor/` (gitignored) |
| Project gate (not a feature) | `bin/check.sh` | PHPCS + PHPStan + PHPUnit in the plugin, `php -l` over the theme; run before every commit (docs/TECH-STACK.md → Check command) | be replaced by an ad-hoc shell chain |
| Ops tooling (not a feature) | `temp-data/` | Yoast SEO import script + data (`yoast-import.php`, run via `wp eval-file`), translation bundles, SEO CSVs; `reports/` (repo root) holds Kyiv/Kharkiv service diff CSVs | be loaded by the theme at runtime |

Legacy remnants present in the theme but not wired to any live page: `templates/{sign-in,sign-up,profile,testing}.php`, `dovira_get_user_study_state()` / `chapter` / `question` helpers, `student` role on theme switch, `source/scripts/modules/{authentication,reset-progress}.js`, `learning-plan` styles/HTML in `temp-data/`. Documented as-is; see DECISIONS.md open questions.

## Data flows
**Service price page (FIXED — do not deviate; CLAUDE.md invariants 2–3).**
`single-service.php` loads all `service-city` terms → reads ACF repeater `prices` →
groups rows by the term ids ticked in each row's `cities` → renders one tab per
city (`dovira_translate_string(term name)`), client-side search
(`init-service-price-lists.js`, `services-search.js`); a row without a price for
the city renders the city's first phone from options `contacts_cities`.
`save_post_service` stores a search index `key_words_services` from the row titles.

**Contact / franchise form (FIXED; invariant 1).** CF7 `wpcf7_submit` with
`status === mail_sent` (or any status in DDEV) → `wp_insert_post('conversation')`
→ `update_field()` for name, pet_name (form 6 only), phone, email, message →
`wp_remote_get` Telegram `sendMessage` to every chat id in option `telegram_bot_chats`.

**Vacancy application (FIXED; invariant 1).** CF7 form 1399 on `wpcf7_before_send_mail`
→ `application` post (`status = new`, `vacancy` = post id from the shortcode attr)
→ CV `media_handle_sideload` → Telegram.

**Blood-donor questionnaire (FIXED; invariants 1, 8).** Front-end
`questionary-form-handler.js` → `POST /wp-json/dovira/v1/questionary/save` →
server-side required-field check (13 fields) → `questionary` post + ACF fields
(`city` ucfirst'ed) → Telegram → `{success, message, post_id}`.

**Telegram join.** Bot webhook → `handle-updates` appends the raw update to option
`telegram_webhook_data`; `/start|/join` asks for a password; the password message
stores the chat id in `telegram_bot_chats`; `/reset` removes it.

**Daily GA report (plugin `ga-telegram-bridge`).** One path, one class:
`Runner::run( $trigger, $bypass_date_guard )`, with two callers. The recurring
WP-Cron event `gatb_daily_report` — registered by `Scheduler` at the next
occurrence of the configured send time in the site's zone (`wp_timezone()`),
re-registered on every settings save, cleared on deactivation — passes `cron`
and keeps the guard; the admin-post action `gatb_send_now` behind the *Send now*
button (nonce, `manage_options`) passes `manual` and bypasses it on purpose. The
screen reads the next run from `wp_next_scheduled()`, which is the only copy of
it, and warns when `DISABLE_WP_CRON` is set and nothing has called `wp-cron.php`
for a day (`gatb_state.last_cron_hit`).
`ReportBuilder::build()` (≤ 2 `batchRunReports` calls, only the enabled blocks)
→ `Report` → **date guard**: the day is the property's own (`Report::$time_zone`),
and a run that is not manual stops here when `gatb_state.last_report_date`
already holds it — nothing sent, nothing logged → `MessageRenderer::render()`
→ `TelegramClient::send_message()` to the one configured chat → `gatb_log`
(30 entries) and, only on delivery, `gatb_state.last_report_date`. A failure at
either host is logged with its mapped reason, raises `gatb_state.attempt` and
leaves the day unsent; no secret is ever written. While that day has attempts
left (`gatb_settings.max_attempts`), a run started by the schedule books the
single event `gatb_retry_report` an hour out, carrying the day it is for — a
run started by *Send now* books nothing, because a person is at the screen.
Screen `Run log` under the settings form prints the entries newest first.

**Translation (CLI).** `wp dovira translate --post-type=X --lang-from=uk --lang-to=ru`
→ `ContentExtractor` + `MetaExtractor` → `AnthropicTranslator` → `PostCopier`
creates the ru post, copies terms/thumbnail, links via `pll_save_post_translations`.
`translations-export` writes a JSON bundle; `translations-import` recreates the
same result on another environment without the API.

**Kyiv vs Kharkiv.** `template-parts/header/site-header.php` sets
`$current_site_id` from `str_contains(get_site_url(), 'kyiv')` and renders the
cross-site switcher; content differs per install (separate DBs), code is shared.

## Integrations
| Service | Used for | Auth / credentials | Failure behaviour |
|---|---|---|---|
| Telegram Bot API (theme, feature `core`) | notifications after every form record; bot webhook | Bot token is a **string literal in code** (`TelegramController::$access_token` and three `inc/utils/*` call sites); join password is a literal in `handle_updates` | `wp_remote_get` result ignored — the record is already saved; no retry |
| Telegram Bot API (plugin `ga-telegram-bridge`) | the daily GA report to one configured chat (`TelegramClient::send_message()` → `POST api.telegram.org/bot{token}/sendMessage`, HTML parse mode, link previews off, 15 s timeout); one fixed test message behind the *Check Telegram* button on screen `Settings` (admin-post action `gatb_check_telegram`, nonce, `manage_options`) | Its **own** bot token and chat id in `gatb_settings`, overridable by `GATB_TELEGRAM_BOT_TOKEN`; shares nothing with the theme's bot (separate token, separate chat registry) | one mapped `TelegramException` per case (401 the token was revoked, 404 the token is malformed, 400 `chat not found`, 400 unreadable HTML, 403 the bot may not post there, 429 flood control naming `retry_after`, 5xx, and a host that cannot reach Telegram at all). A flood limit is the one refusal answered inside the same run: a `retry_after` of at most 30 seconds is waited out once and the message posted again — longer than that is left to the hourly retry, because every send happens inside a WP-Cron or an admin request. The bot token travels in the request URL, so every value entering a message is scrubbed of it first — Telegram never echoes it, but a transport error can quote the URL back. Every run is recorded in `gatb_log` with its mapped reason; the retries follow in Sprint 2 |
| Google Analytics 4 Data API (plugin `ga-telegram-bridge`) | reads one GA4 property (`GaClient::batch_run_reports()`; `check_connection()` behind the *Check GA* button) | Service-account JSON in `gatb_settings` (or `GATB_GA_SERVICE_ACCOUNT_JSON`) → RS256 JWT signed with `openssl_sign` (`GoogleAuth`, scope `analytics.readonly`) → token exchanged at `oauth2.googleapis.com/token` (15 s) and cached in the transient `gatb_google_access_token` for `expires_in - 60`; reports posted to `analyticsdata.googleapis.com/v1beta` (20 s). The **Admin API is deliberately not used** — it is a second API each install would have to enable, so the check names the property id with the reporting time zone from the report's own `metadata`, not the property's display name | one mapped `GoogleAuthException` / `GaClientException` per case (bad property id, no Viewer access — the message names the service account —, refused token → the cached token is dropped, quota, 5xx, host cannot reach Google); no message or log line ever carries the key, the JWT or the token. `ReportBuilder` composes the day's reports into **at most two** `batchRunReports` calls (the API takes five requests per call and a full report is six), asks only for the blocks that are switched on, and keys the visitors rows by the `dateRange` dimension because GA returns them ordered by metric |
| Anthropic Messages API | `wp dovira translate*` (CLI only) | `ANTHROPIC_API_KEY` in the theme `.env` (phpdotenv), read by `anthropic-ai/sdk` | command errors out via `WP_CLI::error`; chunk retry for strings whose markup changed |
| Contact Form 7 | contact (id 6), franchise (1430), vacancy (1399) forms | — (form ids hardcoded in hooks) | mail failure → no `conversation` (except in DDEV) |
| Google Tag Manager / GA4 | analytics; ids differ per install (`master`: GTM-53M4V7M5 + G-HKYZFG0E2W; `kyiv`: G-Q597WTF16L only) | inline in `header.php` | — |
| Google Fonts | Inter, Oswald, Raleway (`header.php`); Open Sans self-hosted | — | — |
| Yoast SEO | meta; one-off import via `temp-data/yoast-import.php` | — | see `temp-data/README.md` |

## Environments & deploy
| Env | URL | Source | How |
|---|---|---|---|
| Local | `https://dovira.ddev.site` | working tree | DDEV (`.ddev/config.yaml`: wordpress, PHP 8.3, nginx-fpm, MariaDB 10.11); `wp-config.php` is DDEV-generated and gitignored; DB snapshot `mysql.sql` at repo root; theme `.env` sets `WP_ENVIRONMENT_TYPE` (development → assets from Vite :3000, otherwise from `assets/` manifest) |
| Dev | `dev.dovira.vet` | branch `dev` | **push-to-deploy**: `.github/workflows/dev.yml` — SamKirkland/FTP-Deploy-Action syncs the whole repo over FTP on push to `dev` (secrets `REMOTE_HOST_DEV`, `FTP_USER_DEV`, `FTP_PASSWORD_DEV`, `FTP_PATH_DEV`) |
| Production Kharkiv | `dovira.vet` | branch `master` | **manual** deploy by the developer |
| Production Kyiv | `kyiv.dovira.vet` | branch `kyiv` (= `master` merged in + Kyiv analytics ids in `header.php`) | **manual** deploy by the developer; after each `master` release, merge `master` into `kyiv` and deploy |

Built assets (`assets/`) and Composer `vendor/` are committed — a deploy is a
file sync, no build step on the server. `origin/main` exists on GitHub but is
not part of this flow.
