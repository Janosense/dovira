# Architecture — Dovira

<!-- playbook: v3.0.4. State document: a section is read whole and replaced;
     no provenance, no future tense. Shape, triggers and what does not
     belong here: docs/CONTRACTS.md → `### docs/ARCHITECTURE.md`. -->

## Overview
One WordPress install per city — Kharkiv `dovira.vet`, Kyiv `kyiv.dovira.vet`,
each with its own database — runs the same repository: the custom classic
theme `wp-content/themes/dovira` (feature `core` and the theme features under
`inc/features/`), the standalone plugin `ga-telegram-bridge`, the single-file
plugin `dovira-bridge-conversations`, the third-party plugin `mcp-adapter`
(transport of `content-mcp`) and stock plugins (ACF Pro, Polylang, Contact
Form 7, Yoast SEO, Cyr2Lat, Simple Custom Post Order, Enable Media Replace,
Akismet, Loco Translate); content is Ukrainian with Russian Polylang
translations. Constraints every step respects:
- Which city an install serves is decided once, from the site URL (`kyiv` in `get_site_url()` / `home_url()`), never from an option, constant, env flag or URL literal.
- The two installs share code and nothing else: no database, option or request crosses between them, except a visitor's own navigation and the cookies `city-popup` writes on the domain both share.
- Built front-end assets (`assets/`) and the theme's Composer `vendor/` are committed, so a deploy is a file sync with no build step on the server.
- Public pages never call Google or Telegram synchronously on a visitor's behalf except the form notification, which runs after the record is saved.
- `wp-content/mu-plugins/` is not used: it is gitignored and would not deploy.

## Modules
| Module | Where | Responsibility | Must never | Used by |
|---|---|---|---|---|
| Public pages | `header.php`, `footer.php`, `index.php`, `single*.php`, `search.php`, `404.php`, `templates/*.php`, `template-parts/**` | classic PHP templates of every public screen | branch on the city other than through the site URL | core, search-stats, city-popup |
| Back office | wp-admin: post-type lists and columns in `inc/utils/{conversation,application,questionary}.php`, options page «Settings», role `customer_support_specialist` (`inc/custom-roles/`) | staff edit content and process records | define ACF field groups in the admin UI | core |
| REST `dovira/v1` | controllers in `inc/rest-api/` and the features, registered in `inc/rest-api.php` | public JSON endpoints the theme's own pages call, and the Telegram webhook | write before every required field is validated | core, search-stats |
| MCP server `mcp/dovira` | `inc/features/content-mcp/` on plugin `mcp-adapter` | the content connector's tools, as the connected user | expose an ability that is not `dovira/*` | content-mcp |
| REST `dovira-bridge/v1` | plugin `wp-content/plugins/dovira-bridge-conversations/` (one file) | lets the outside app Dovira Bridge read conversations and set their processed status, as a user with `edit_others_conversations` | store data of its own | — (outside caller) |
| WP-CLI `wp dovira …` | `inc/cli.php`, `inc/cli/` | AI translation uk → ru and translation bundles | run outside WP-CLI | core |
| Bootstrap | `functions.php` | Composer autoload, theme `.env`, constants (`TEMPLATE_DIR`, `TEMPLATE_DIR_URI`, `ASSETS_DIR_URI`, `VITE_SERVER`), every `inc/*.php`, one `require_once` per theme feature's `bootstrap.php` | contain feature logic | every theme feature |
| Content model | `inc/post-types/*.php`, `inc/taxonomies/*.php` (glob-loaded, one file each) | six post types and the taxonomy `service-city` | be registered anywhere else | core, content-mcp, search-stats |
| ACF definitions | `inc/acf/fields/`, `inc/acf/option-pages/`, `inc/acf/blocks/{name}/` | every field group in PHP (ACF Builder); `acf/*` blocks registered by directory scan; the block allow-list `dovira_allowed_block_types()` | be created in the ACF admin UI | core, content-mcp |
| Form pipelines | `inc/utils/conversation.php`, `inc/utils/application.php`, `inc/rest-api/QuestionaryController.php`, `inc/utils/telegram.php` | a submission becomes a post, then a Telegram notification (Flows) | call Telegram before the post is saved | core |
| Polylang glue | `inc/polylang.php`, `inc/utils/polylang-string-translations.php` | translatable post types, ACF options per language, theme strings (`dovira_translate_string()`) | make `service-city` translatable | every theme feature |
| Front-end build | `vite.config.js`, `postcss.config.cjs`, `source/**` → `assets/**` | ES modules imported by `source/scripts/app.js`, one CSS file per component or block, resolved through `starter_theme_vite_asset()` | be edited in `assets/` by hand | every theme feature |
| Check script | `bin/check.sh` | the commit gate (`TECH-STACK.md` → Check command) | be replaced by an ad-hoc shell chain | every feature |
| Ops tooling | `temp-data/` (read `temp-data/README.md` before touching Yoast meta or the SEO import), `reports/` | one-off imports and exports | be loaded by the theme at runtime | — |

**Dependency rules**
- A theme feature lives in `inc/features/{name}/` with one `bootstrap.php`, and `functions.php` holds exactly one line per feature.
- A theme feature reads `core` through WordPress, ACF and Polylang functions and changes shared code only as a plan task marked "touches shared code" (theme `CLAUDE.md` → Feature isolation).
- A feature never writes another feature's data; `content-mcp` writes `core`'s posts, terms and attachments only through the functions wp-admin uses, as the connected user.
- The plugin `ga-telegram-bridge` never references the theme, and the theme reaches it only through the plugin's filters (`gatb_report_data`, `gatb_extra_blocks`, `gatb_message_html`), only when `GaTelegramBridge\Plugin` is loaded.
- A new theme feature copies the layout of `inc/features/search-stats/`: classes in namespace `dovira\{Feature}` required by its `bootstrap.php`, JS in `source/scripts/features/{name}/`, styles in `source/styles/features/{name}/`, tests in `tests/Unit/{Feature}/` and `tests/js/{name}/`; a feature that must run on any WordPress site is a plugin of its own instead.

## Platform
| Concern | Mechanism | Where configured | Rule for new code |
|---|---|---|---|
| Content storage | post types + ACF fields in `wp_postmeta`, options page in `wp_options` (`options_*`, `options_ru_*`) | `inc/post-types/`, `inc/acf/` | read and write ACF values with `get_field()` / `update_field()` by name |
| Own tables | `dbDelta()` from a schema version kept in an option | `inc/features/search-stats/Schema.php` | a new table copies `Schema` (version option, `after_switch_theme` + `init`) |
| Scheduled work | WP-Cron events, registered on `init` when absent | the owning feature or plugin (Jobs) | never runs from a public request; registers itself when missing |
| Short-lived state | transients | the owning feature | never holds a secret except the Google access token |
| Settings | ACF options page «Settings» (per language); a plugin's own option row through the Settings API | `inc/acf/option-pages/settings.php`; plugin `Settings` | a business value is a setting or a filter, never a constant |
| Secrets | theme `.env` (phpdotenv) for `ANTHROPIC_API_KEY`; `wp-config.php` constants for the Telegram bots, GA key and dump folder | each install's own files, never committed | read through the one accessor that owns the secret |
| Authentication | WordPress users and roles; application passwords for outside callers (`mcp/dovira`, `dovira-bridge/v1`) | wp-admin → Users | every REST route sets an explicit `permission_callback` |
| Translations | Polylang: posts linked uk ↔ ru, theme strings in Polylang → Strings | `inc/polylang.php`, `inc/utils/polylang-string-translations.php` | every visible string is a Polylang string |
| Front-end assets | Vite manifest → hashed files in `assets/`, dev server in development | `vite.config.js`, theme `.env` `WP_ENVIRONMENT_TYPE` | enqueue through `starter_theme_vite_asset()` |
| Media | the WordPress media library | — | add files with `media_handle_sideload()` |
| Error reporting | PHP `error_log`; the plugin's run log `gatb_log` | host PHP settings | never log a secret value |

## Flows

### Service price page
- **Started by:** a visit to a `service` page (`single-service.php`).
1. The template loads every `service-city` term and the service's ACF repeater `prices`.
2. Each row is grouped under the city terms ticked in its `cities` checklist, one tab per city, the tab named by the term's Polylang string.
3. A row prints its general `price`, or its `city_prices.price_city_{term_id}` for that tab's city.
4. A row with no price for the city prints that city's first phone from the options repeater `contacts_cities` instead of an amount.
5. On save, `save_post_service` stores the row titles in the postmeta `key_words_services` for search.
- **State kept in:** the service's ACF fields; the term ids in the field names.
- `FIXED`

### Form submission → record → Telegram
- **Started by:** Contact Form 7 form 6 (contact) or 1430 (franchise) on `wpcf7_submit`, form 1399 (vacancy) on `wpcf7_before_send_mail`, or `POST dovira/v1/questionary/save`.
1. A contact or franchise submission whose status is `mail_sent` (any status in DDEV) becomes a `conversation` post, the franchise one titled with the prefix «Франшиза: ».
2. A vacancy submission becomes an `application` post with status `new` and the vacancy id from the shortcode attribute, and its CV is added with `media_handle_sideload()`.
3. A questionnaire request is checked for its 13 required fields before anything is written, then becomes a `questionary` post and answers `{success, message, post_id}`.
4. The post's ACF fields are written with `update_field()`.
5. Only after that the message is built — a conversation's names the city from the site URL and links to the install's own admin list — and `dovira_telegram_notify()` sends it.
6. `dovira_telegram_notify()` sends to every chat in the option `telegram_bot_chats`, then once to `DOVIRA_TELEGRAM_CHAT_ID` with `message_thread_id` = `DOVIRA_TELEGRAM_THREAD_ID` when set, a subscribed chat that is that chat getting it only in the thread.
7. The send's result is ignored: without the bot token nothing is sent and one `error_log` line names the constant, and the record stays saved.
- **State kept in:** the `conversation`, `application`, `questionary` posts.
- `FIXED`

### Telegram join (dormant)
- **Started by:** a Telegram update posted to `dovira/v1/telegram/handle-updates` — none arrives, because the bot has no webhook set.
1. The raw update is appended to the option `telegram_webhook_data`.
2. `/start` or `/join` asks for the password, the password message stores the chat id in `telegram_bot_chats`, `/reset` removes it.
- **State kept in:** `telegram_bot_chats`, `telegram_webhook_data`.

### Site search → statistics row
- **Started by:** a results page of the header search, or a pause in typing in the filter of the `services` block or of a service's price list.
1. `search.php` counts what it lists (`ResultsCount::shown()`) and prints the query and the count as `data-search-stats-*` attributes.
2. On load, `app.js` calls `recordSiteSearch()`, which sends a non-empty query once through `recordSearch()`.
3. Each filter input carries `data-search-stats-context` (the hosting page or the service), and `debouncedRecorder()` sends its value after 1500 ms of rest or on blur, at least 3 characters once trimmed, never the value that input sent last.
4. `recordSearch()` posts JSON with `navigator.sendBeacon`, falling back to `fetch(keepalive)`, to `POST dovira/v1/search-stats/record` and never reads the answer.
5. The route validates the whole request, normalizes the text once (`Normalizer`) and inserts one row with `Repository::insert()`, or writes nothing.
- **State kept in:** the table `{prefix}dovira_search_queries`.
- `FIXED`

### Blog click → city question
- **Started by:** a click on a page that carries the config element `data-city-popup-config`, which `Dialog` prints in `wp_footer` on every page.
1. `city-popup.js`, imported last by `app.js` inside its own `try`/`catch`, adds one delegated `click` listener and takes every decision from the pure `logic.js`.
2. The listener leaves alone a click already cancelled, a click with a modifier or a non-main button, and a click on a link that opens another window.
3. A plain click on a link with `data-city="kharkiv|kyiv"` writes `dovira_city` for the configured days and the browser follows the link — this is the header switcher.
4. On a blog page (the `news` page in either language, or a single `post`), where `Dialog` also prints the closed `<dialog data-city-popup>` with the city, both origins and the target URLs (`services`, `contacts`, the `service` base) from `home_url()` and Polylang, a plain click on a target on the current host is decided from the cookies.
5. `dovira_city` of this install or only `dovira_city_dismissed` → the link is followed; `dovira_city` of the other city → its version on the other install (same path, query and fragment plus `city-popup=1`); neither → `showModal()` with focus on «Харків».
6. A city button writes `dovira_city` and goes to the link on that city's install; ×, Esc or a click outside the dialog's box writes the session cookie `dovira_city_dismissed` and follows the link; the dialog is closed before every navigation.
7. On the receiving install, `Fallback::redirect()` on `template_redirect` answers a 404 under its language's service base whose query has `city-popup=1` with a 302 to that language's services page, without the marker, after core's `wp_old_slug_redirect` and `redirect_canonical`.
- **State kept in:** the visitor's cookies `dovira_city` and `dovira_city_dismissed` on the Kharkiv host's domain; no PHP reads them.
- `FIXED`

### Connector write → dump → write
- **Started by:** a write tool of the `mcp/dovira` server called by a person's Claude, authenticated as that person's WordPress user with an application password.
1. Called without `confirmation`, the ability validates the payload, computes the diff (target post and language, field, from, to, warnings) and stores a confirmation token bound to the user, the `Mcp-Session-Id` and the payload's hash for 15 minutes, issuing none for an empty diff.
2. The person's Claude shows the diff and calls again with the same payload and the token only after the person agrees.
3. The token is verified and consumed, and `Dump::ensure()` dumps the whole database to a new timestamped `.sql.gz` in `DOVIRA_MCP_DUMP_DIR` when the session has no dump yet, a failed dump aborting the write.
4. The write runs as the connected user through WordPress's own functions (`wp_insert_post`, `wp_update_post`, `update_field`, `wp_set_object_terms`, `wp_trash_post`, `media_handle_sideload`, Polylang's linking) and returns `{written, dump, warnings}`.
5. Reads issue no token and take no dump.
- **State kept in:** the transients `dovira_mcp_confirm_{hash}` and `dovira_mcp_dumped_{session}`; the dump files.
- `FIXED`

### Daily GA report
- **Started by:** the WP-Cron event `gatb_daily_report` (trigger `cron`), the single event `gatb_retry_report` (trigger `retry`), or the admin's *Send now* (trigger `manual`).
1. `Runner::run()` is the one path: `ReportBuilder::build()` reads the enabled blocks in at most two `batchRunReports` calls into one `Report`.
2. A run that is not manual stops, sending and logging nothing, when the report's day — the property's own — equals `gatb_state.last_report_date`.
3. `MessageRenderer::compose()` renders the plugin's blocks, then those `gatb_extra_blocks` adds, dropping added blocks from the last one back while the text is longer than 4 096 characters.
4. `TelegramClient::send_message()` posts it to the one configured chat (and thread), and a delivery writes `gatb_state.last_report_date`.
5. Every run writes one `gatb_log` row; a failure raises `gatb_state.attempt`, and a scheduled run with attempts left books `gatb_retry_report` an hour out carrying its day, the last attempt sending the failure notice instead.
6. A scheduled run ends by re-registering `gatb_daily_report` at the next configured local time.
- **State kept in:** the options `gatb_state` and `gatb_log`; the next run only in `wp_next_scheduled()`.
- `FIXED`

### Daily report → search blocks
- **Started by:** every run of the daily GA report — schedule, retry, *Preview* and *Send now* alike.
1. `MessageRenderer::compose()` applies `gatb_extra_blocks`, which the theme hooks in `inc/features/search-stats/bootstrap.php` with `Renderer::add_to()`.
2. `Stats::build()` runs six queries on the install's own table — each level, yesterday and the 28 days that end with it, in the site's zone, top five each.
3. `Renderer::blocks()` turns them into up to three blocks, appended to the plugin's list; a day with no searches adds nothing.
- **State kept in:** nothing; every report queries again.

### AI translation (CLI)
- **Started by:** `wp dovira translate --post-type=X --lang-from=uk --lang-to=ru`.
1. `ContentExtractor` and `MetaExtractor` extract the title, ACF block data, core block HTML and Yoast meta.
2. `AnthropicTranslator` translates them in chunks with the Anthropic Messages API.
3. `PostCopier` creates the Russian post, copies terms and thumbnail and links it with `pll_save_post_translations()`.
4. `translations-export` writes the result as a JSON bundle keyed by source post id and fingerprint; `translations-import` recreates it on another environment without the API.
- **State kept in:** the Russian posts; the bundles in `temp-data/`.

## Jobs
| Job | Schedule | What it does | Module |
|---|---|---|---|
| `gatb_daily_report` | daily at the configured local send time, re-anchored after each run | builds and sends the daily GA report | plugin `ga-telegram-bridge` (`Scheduler`, `Runner`) |
| `gatb_retry_report` | single, an hour after a failed scheduled run, carrying the day | retries that day's report | plugin `ga-telegram-bridge` |
| `dovira_search_stats_purge` | daily | deletes search rows older than the retention | `inc/features/search-stats/` (`Purge`) |
| `dovira_mcp_dump_retention` | daily | deletes dump files older than the retention | `inc/features/content-mcp/` (`Dump`) |

## Integrations
| Service | Used for | Module that talks to it | Auth model | On failure | Docs |
|---|---|---|---|---|---|
| Telegram Bot API (theme bot) | a notification after every form record | `inc/utils/telegram.php` | bot token constant `DOVIRA_TELEGRAM_BOT_TOKEN` | result ignored, no retry; token unset → no request and one `error_log` line | https://core.telegram.org/bots/api |
| Telegram Bot API (report bot) | the daily report, the failure notice, the test message | plugin `TelegramClient` | its own bot token (`gatb_settings` or `GATB_TELEGRAM_BOT_TOKEN`) | one mapped `TelegramException` per refusal, logged; a `retry_after` up to 30 s is waited out once; the hourly retry repeats the rest | https://core.telegram.org/bots/api |
| Google Analytics 4 Data API | the report's figures | plugin `GoogleAuth`, `GaClient` | service-account JWT (RS256, `analytics.readonly`) → access token cached in a transient | one mapped exception per refusal, logged; a refused token is dropped; the day is retried like a Telegram failure | https://developers.google.com/analytics/devguides/reporting/data/v1/rest |
| Google OAuth 2.0 token endpoint | the access token for the Data API | plugin `GoogleAuth` | the signed JWT | mapped `GoogleAuthException` | https://developers.google.com/identity/protocols/oauth2/service-account |
| Anthropic Messages API | `wp dovira translate*` | `inc/cli/` (`AnthropicTranslator`, SDK `anthropic-ai/sdk`) | `ANTHROPIC_API_KEY` in the theme `.env` | the command stops with `WP_CLI::error`; chunks whose markup changed are retried | https://docs.claude.com/en/api/messages |
| MCP clients (claude.ai, Claude Desktop) | call the content connector from Anthropic's cloud (user agent `Claude-User`) | plugin `mcp-adapter` + `inc/features/content-mcp/` | the person's own application password as a fixed `authorization: Basic` header | 401 without a valid password; the host's bot protection answers 403/405 before WordPress unless `Claude-User` is allowed | https://modelcontextprotocol.io/specification/2025-11-25 |
| Dovira Bridge (Dovira Pro) | reads conversations and sets their processed status | plugin `dovira-bridge-conversations` | an application password of a user with `edit_others_conversations` | 401 / 403 from the route's permission check | — |
| Google Tag Manager / GA4 tag | site analytics, ids per install | `header.php` (inline) | — | — | https://developers.google.com/tag-platform |
| Google Fonts | Inter, Oswald, Raleway | `header.php` | — | the system font | https://developers.google.com/fonts/docs/css2 |

## Environments & deploy
| Environment | Tracks | Runs as | Deploy |
|---|---|---|---|
| Local | the working tree | DDEV `https://dovira.ddev.site`: PHP 8.3, nginx-fpm, MariaDB 10.11; `wp-config.php` generated by DDEV; database snapshot `mysql.sql`; theme `.env` `WP_ENVIRONMENT_TYPE=development` serves assets from Vite on :3000 | `ddev start`, `ddev import-db --file=mysql.sql` |
| Dev | `dev` | `dev.dovira.vet` on the shared host (webroot `/home/dovirave/dovira.vet/dev`); the connector's steps are verified here | push to `dev` → GitHub Actions `.github/workflows/dev.yml` syncs the repo over FTP |
| Production Kharkiv | `master` (published as `origin/main`) | `dovira.vet` | by hand, a file sync; plugins activated by hand |
| Production Kyiv | `kyiv` (= `master` merged in + the Kyiv analytics ids in `header.php`; published as `origin/kyiv`) | `kyiv.dovira.vet` | by hand after merging `master` into `kyiv` |

Each install's `wp-config.php` (gitignored, never committed) carries its own
`DOVIRA_TELEGRAM_BOT_TOKEN`, `DOVIRA_TELEGRAM_JOIN_PASSWORD`,
`DOVIRA_TELEGRAM_CHAT_ID`, `DOVIRA_TELEGRAM_THREAD_ID`,
`GATB_GA_SERVICE_ACCOUNT_JSON`, `GATB_TELEGRAM_BOT_TOKEN` and
`DOVIRA_MCP_DUMP_DIR`, set before the code that reads them is deployed; the
dump folder of every install sits directly under `/home/dovirave/`, outside
every webroot. Per-install set-up is in `docs/ops/` (THIRD-PARTY-SERVICES.md
names the files).
