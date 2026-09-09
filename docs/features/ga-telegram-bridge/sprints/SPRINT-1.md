# SPRINT 1 — Report on demand (from 2026-09-08)

<!-- Written by discovery (Phase C) together with every other sprint of the
     plan — never by Claude Code. Rewritten by a re-planning chat only while
     no step is closed; afterwards steps may only be appended. When the last
     step closes, /close-step writes SPRINT-1-CLOSE.md next to this file. -->
**Branch:** `ga-telegram-bridge/sprint-1-report-on-demand` → `master`
**Goal:** On the dev site an administrator activates the plugin, pastes the
service-account JSON, property id, bot token and chat id, sees "Check GA" name
the property and "Check Telegram" deliver a test message, previews the report
text in the admin and, on "Send now", receives the complete daily report in
Telegram exactly as the template in `FEATURE.md` → UI. Nothing runs on a
schedule yet. The project has a check command that gates every commit.

## Fixed decisions
- Standalone plugin in `wp-content/plugins/ga-telegram-bridge/`, no theme coupling — DECISIONS "A standalone plugin in a new code area"
- Report content, `activeUsers`, baselines (avg of previous 7 days; previous 28 days), `pageTitle` with `pagePath` fallback — DECISIONS "Report content and comparison baselines"
- Service account + own minimal client (`openssl_sign` JWT, `wp_remote_post`, `batchRunReports`), zero runtime deps — DECISIONS "Google access via a service account, own minimal API client"
- PSR-4 `src/`, own autoloader, three options, constant overrides for secrets — DECISIONS "Plugin structure, storage and secrets"
- HTML parse mode, template fixed in FEATURE.md, five block checkboxes — DECISIONS "No UI design phase; message format and configurable blocks"
- PHPUnit + Brain\Monkey, PHPCS WPCS, PHPStan; `bin/check.sh` — DECISIONS "Testing tooling and the project check command"

## Steps

### [x] Step 1 — Delta-audit, plugin skeleton, check command
- **Tasks:**
  - Delta-audit: read `docs/features/core/FEATURE.md`, the theme `CLAUDE.md` and `docs/ARCHITECTURE.md` → Integrations; confirm the plugin touches no theme code or data and note the theme's existing Telegram bot as an unrelated integration (same Telegram API, separate token setting).
  - Create `wp-content/plugins/ga-telegram-bridge/`: `ga-telegram-bridge.php` (header: name "Google Analytics → Telegram bridge", text domain `ga-telegram-bridge`, `Requires PHP: 8.1`, `Requires at least` = a WordPress version verified against the local install), `src/Plugin.php` with `boot()`, `spl_autoload_register` autoloader for namespace `GaTelegramBridge` → `src/`, activation hook that aborts with a message when `openssl` is missing, `.gitignore` (`vendor/`), `readme.txt` stub, `languages/`.
  - `composer.json` (dev only): phpunit/phpunit, brain/monkey, squizlabs/php_codesniffer + wp-coding-standards/wpcs + dealerdirect/phpcodesniffer-composer-installer, phpstan/phpstan + szepeviktor/phpstan-wordpress; `phpcs.xml.dist`, `phpstan.neon.dist` (level agreed in the step plan), `phpunit.xml.dist`, `tests/bootstrap.php` (Brain\Monkey, no WP load). Every package needs the user's approval in the step plan (core rule 4) — exact versions recorded in TECH-STACK.md → Approved dependencies log.
  - Create the check command `bin/check.sh` at the repo root: `composer install` if needed, then PHPCS, PHPStan, PHPUnit in the plugin, then `php -l` over `wp-content/themes/dovira/**/*.php` (excluding `vendor/`, `node_modules/`), exit non-zero on the first failure.
- **Tests:** a smoke test proving the test harness runs (`Plugin::boot()` registers its hooks via Brain\Monkey expectations).
- **Verification (manual):** `bin/check.sh` exits 0 on a clean tree and non-zero when a deliberate PHPCS violation is introduced; the plugin activates on the local site with no notices and shows no menu yet; activation with `openssl` disabled (simulate via the step plan's approach) shows the abort message.
- **Docs to update:** `docs/TECH-STACK.md` → Check command (name `bin/check.sh`), Stack table (plugin row, tooling rows marked `unverified — pinned at bootstrap` until installed), Approved dependencies log; root `CLAUDE.md` → Commands (check command line); `docs/ARCHITECTURE.md` → Feature map + Modules (plugin row) + Integrations (GA4 Data API row, Telegram row for the plugin); `docs/TESTING.md` → How to run.
- **Depends on:** —

### [x] Step 2 — Spike: service account → GA4 Data API (timeboxed, throwaway)
- **Tasks:**
  - Timebox: one session. In a scratch file under `wp-content/plugins/ga-telegram-bridge/spike/` (never committed to `master` — deleted at close), build the JWT (header `RS256`, claims `iss`=client_email, `scope`=`https://www.googleapis.com/auth/analytics.readonly`, `aud`=`https://oauth2.googleapis.com/token`, `iat`, `exp`=iat+3600), sign with `openssl_sign(OPENSSL_ALGO_SHA256)`, exchange it (`grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer`) and call `properties/{id}:runReport` for `activeUsers`, date range `yesterday`, run via `ddev wp eval-file`.
  - Use the Dovira Kharkiv property with a service account the user creates and grants Viewer; the key JSON is read from a local file outside the repo, never committed.
  - Exit criteria (written into the step's close report): access token obtained; `activeUsers` for yesterday equals the GA4 UI value; recorded: the `private_key` newline handling, clock-skew tolerance, the exact error bodies for 401/403 (wrong key, no Viewer access) and 400 (bad property id), the HTTP status for a quota error, response shape of `batchRunReports` with 4 date ranges (`dateRange` dimension values `date_range_0..3`).
- **Tests:** — (spike code is throwaway; findings become fixtures in Step 4)
- **Verification (manual):** the user runs the eval-file command and compares the number with the GA4 Reports → Reports snapshot for yesterday.
- **Docs to update:** `docs/LEARNINGS.md` (the pitfalls found); `docs/DECISIONS.md` only if a finding contradicts the client decision (e.g. `openssl` cannot sign the key on the target host) — raise before deviating.
- **Depends on:** Step 1

### [x] Step 3 — Settings and the admin page
- **Tasks:**
  - `Settings` class: option `gatb_settings` (autoload `no`), defaults, typed getters, sanitize callback (property id digits only; JSON must parse and contain `client_email`, `private_key`, `token_uri`; chat id `-?\d+`; time `HH:MM`; max attempts 1–10; blocks array of the five keys, `visitors` forced on), constant overrides `GATB_GA_SERVICE_ACCOUNT_JSON` / `GATB_TELEGRAM_BOT_TOKEN` (getter returns the constant, field rendered read-only with "set in configuration").
  - `Admin` class: Settings API page under Settings → "GA → Telegram", capability `manage_options`, nonces, admin notices for validation errors, all strings through `__()` with text domain `ga-telegram-bridge`.
- **Tests:** sanitize callback on valid/invalid inputs; constant override wins over the option; `visitors` cannot be disabled.
- **Verification (manual):** screen `Settings`: saving a malformed JSON or a chat id with letters shows an error and keeps the previous value; defining the two constants in `wp-config.php` turns the fields read-only; saved values survive reload.
- **Docs to update:** `docs/DATA-MODEL.md` (section "Plugin ga-telegram-bridge": `gatb_settings` keys and types); `readme.txt` (settings description).
- **Depends on:** Step 1

### [x] Step 4 — GoogleAuth, GaClient and "Check GA"
- **Tasks:**
  - `GoogleAuth`: JWT building/signing from the spike findings, token exchange via `wp_remote_post`, cache in transient `gatb_google_access_token` for `expires_in - 60`, typed exception `GoogleAuthException` with a user-readable reason (never the key).
  - `GaClient`: `batchRunReports(array $requests)` and `getPropertyName()` (Admin API `GET https://analyticsadmin.googleapis.com/v1beta/properties/{id}` with the same token — verify the endpoint and scope in the step plan; if it needs the Admin scope, fall back to a `runReport` with `limit 1` as the connectivity check and show the property id), HTTP timeouts, 5xx and quota errors mapped to `GaClientException`.
  - "Check GA" button on screen `Settings` (admin-post action + nonce): shows the property name or the mapped error.
- **Tests:** JWT header/claims/signature verified with `openssl_verify` against the fixture key; token caching; response parsing on recorded fixtures (`tests/fixtures/ga/*.json` from the spike); each error mapping.
- **Verification (manual):** screen `Settings`: "Check GA" with the real key shows the property; with a key lacking Viewer access shows the access error; the transient exists afterwards and holds no key material.
- **Docs to update:** `docs/ARCHITECTURE.md` → Integrations (GA4 row: auth, failure behaviour); `docs/TESTING.md` (fixtures rule).
- **Depends on:** Step 2, Step 3

### [ ] Step 5 — TelegramClient and "Check Telegram"
- **Tasks:**
  - `TelegramClient::sendMessage(string $chatId, string $html)` via `wp_remote_post` to `https://api.telegram.org/bot{token}/sendMessage` with `parse_mode=HTML`, `disable_web_page_preview=true`; map 400 (bad chat id / unparsable HTML), 401 (bad token), 403 (bot blocked / not in channel), 429 (`retry_after`) to `TelegramException`; the token is never part of any exception message or log line.
  - "Check Telegram" button on screen `Settings`: sends a fixed test message.
- **Tests:** request shape (URL, body, parse mode); each error mapping; the token is absent from exception messages.
- **Verification (manual):** screen `Settings`: the test message arrives in the configured chat (channel with the bot as admin, and a private chat); a wrong chat id shows the mapped error.
- **Docs to update:** `docs/ARCHITECTURE.md` → Integrations (Telegram row for the plugin).
- **Depends on:** Step 3

### [ ] Step 6 — ReportBuilder
- **Tasks:**
  - `Report` value object (immutable: date, visitors yesterday / 7-day avg / 28 d / previous 28 d, top pages ×2, channels, cities, devices — each block nullable when disabled).
  - `ReportBuilder::build(Settings)`: request set — users: metric `activeUsers`, date ranges `yesterday`, `8daysAgo`–`2daysAgo`, `28daysAgo`–`yesterday`, `56daysAgo`–`29daysAgo`; pages ×2: dimensions `pagePath`,`pageTitle`, metric `screenPageViews`, order by views desc, limit 5, ranges `yesterday` and `28daysAgo`–`yesterday`; channels `sessionDefaultChannelGroup` / `sessions`; cities `city` / `activeUsers` (limit 5, "(not set)" dropped); devices `deviceCategory` / `activeUsers`; packed into ≤2 `batchRunReports` calls, skipping disabled blocks.
  - Maths in a pure `Dynamics` helper: 7-day average = sum/7 (not per available day), percentage change rounded to whole %, `null` when the baseline is 0; shares as whole % of the block total; title fallback to path when `pageTitle` is empty or "(not set)".
- **Tests:** request composition per enabled-block set (2 calls max, none for disabled blocks); parsing of the four date ranges from the `dateRange` dimension; dynamics maths including zero baselines and empty rows (a new site with no data); share rounding sums.
- **Verification (manual):** `wp eval` printing the `Report` for the dev property (step plan defines the snippet); numbers match the GA4 UI for the same relative ranges.
- **Docs to update:** `docs/DOMAIN.md` (glossary: daily report, block, dynamics — if wording changes); `docs/TESTING.md` (what is never mocked: the maths and the parsers).
- **Depends on:** Step 4

### [ ] Step 7 — MessageRenderer and Preview
- **Tasks:**
  - `MessageRenderer::render(Report, Settings)` producing exactly the template of `FEATURE.md` → UI: header with `wp_parse_url(home_url(), PHP_URL_HOST)` and `wp_date('j F (l)')`-style localized date, ▲/▼ or `—`, `number_format_i18n`, `esc_html` on every GA-provided string, blocks in fixed order, disabled blocks omitted; `renderFailure(date)`; filter `gatb_message_html`; filter `gatb_report_data` applied before rendering.
  - i18n: source strings in English (WordPress convention), all through `__()`; generate `languages/ga-telegram-bridge.pot` (`wp i18n make-pot`) and ship the `uk_UA` `.po/.mo` (`wp i18n make-mo`); the template in FEATURE.md shows the uk rendering, the English strings must map to it line by line.
  - "Preview" button on screen `Settings`: builds and renders without sending, shows the HTML as text.
- **Tests:** snapshot tests of the rendered message for a full report, a report with disabled blocks, empty data, zero baselines, and a title containing `<`, `&`; the failure notice.
- **Verification (manual):** screen `Settings`: "Preview" shows the uk text identical in structure to the template; switching site language to English shows the en text.
- **Docs to update:** `docs/features/ga-telegram-bridge/FEATURE.md` → UI (if a string had to change, the template is updated in the same commit).
- **Depends on:** Step 6

### [ ] Step 8 — "Send now" and RunLog
- **Tasks:**
  - `RunLog`: option `gatb_log` capped at 30 entries `{time, trigger: cron|retry|manual, date, status: sent|failed, attempt, message}`; `gatb_state` (`last_report_date`, `attempt`); a `Runner::run(trigger, bypassDateGuard)` that chains build → render → send → log → state, with the date guard in place but bypassed for manual runs.
  - "Send now" button on screen `Settings`; screen `Run log` table under the settings form.
- **Tests:** a successful run writes log + state; a failed send writes a failed entry and leaves `last_report_date` untouched; the log cap; the date guard blocks a second non-manual run for the same date.
- **Verification (manual):** screen `Settings`: "Send now" delivers the full report to the chat; screen `Run log` shows the entry; a second "Send now" sends again (manual bypass) and logs `manual`.
- **Docs to update:** `docs/DATA-MODEL.md` (`gatb_state`, `gatb_log`); `docs/ARCHITECTURE.md` → Data flows ("Daily GA report" flow, manual part).
- **Depends on:** Step 5, Step 7

## Definition of Done
- [ ] Every step closed via /close-step (report + verification guide + worklog)
- [ ] `bin/check.sh` green on the sprint branch
- [ ] Docs match reality (DATA-MODEL, ARCHITECTURE, DECISIONS, TECH-STACK current)
- [ ] Merged to `master`; deployed to dev by the sprint-boundary push to `dev`; plugin activated and configured on dev with the constants in its `wp-config.php`; "Send now" delivers the report there
- [ ] Spike code removed; no secret or key file in the repo (`git log -p` checked for the JSON key)

## Out of scope
- Scheduling, retries, failure notice, next-run display → Sprint 2
- `uninstall.php`, deactivation cleanup, readme for other sites → Sprint 2
- Production installs (Kharkiv, Kyiv) → Sprint 2 Definition of Done
- Key events, weekly digest, several properties/chats, packaging → not planned (FEATURE.md → Roadmap)

## Risks / notes
- Day 1: the user creates the Google Cloud project + service account, enables the Google Analytics Data API, grants the service-account e-mail Viewer on both GA4 properties, and creates/reuses a Telegram bot and its target chat (bot must be an admin of a channel). Without these, Steps 2, 4, 5 cannot be verified.
- GA4 yesterday data may still be processing early in the morning; the report reflects GA's numbers at send time — accepted for v1, mentioned in the readme.
- `openssl` and outbound HTTPS to `googleapis.com` / `api.telegram.org` are host requirements; the FTP-deployed dev host must allow them — check on Step 4.
