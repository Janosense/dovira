# Decisions log — Dovira

<!-- One decision log per project, across ALL features and code areas.
     Append-only; a reversed decision gets a NEW entry that links the old one,
     the old entry is never edited. Read this file before proposing any architecture or
     tooling change — it may already be decided. -->

Entry format:

## {{YYYY-MM-DD}} — {{Short title}}
- **Context:** {{what forced the decision}}
- **Decision:** {{what was decided, one sentence, imperative}}
- **Alternatives rejected:** {{and the one-line reason each lost}}
- **Consequences:** {{what this commits us to; what becomes an anti-pattern}}

---

## 2026-09-08 — Adopt the playbook (v1.14) into the existing codebase
- **Context:** The site has been live and developed without a written record; new work should go through the playbook's discovery → step cycle.
- **Decision:** Register the whole `dovira` theme as feature `core` with no sprints; every new piece of work is a new feature under `inc/features/{name}/` planned in Feature mode.
- **Alternatives rejected:** splitting the existing theme into several features retroactively — no value without a refactor, and Adoption changes no code.
- **Consequences:** `core` never receives sprints; the theme gets a code-area `CLAUDE.md`; the first feature's Sprint 1 Step 1 creates the check command.

## 2026-09-08 — Verification profile and deploy model
- **Context:** Adoption needs the profile slots of root `CLAUDE.md`; the repo shows a `dev` FTP workflow but nothing for production.
- **Decision:** Profile is developer-reviewed; deploys are manual from `master` (Kharkiv) and `kyiv` (Kyiv), with `kyiv` a deployment branch that only merges `master` in; git model is simple (task branch → `master`).
- **Alternatives rejected:** chained sprint branches — overhead for a one-developer site; treating `kyiv` as a long-lived feature branch — it must never diverge beyond analytics ids.
- **Consequences:** no step may deploy; a sprint's Definition of Done includes "merge to `master`, merge `master` into `kyiv`, deploy both by hand"; `origin/main` is unused.

## 2026-09-08 — Document the UI from code (DESIGN.md) instead of deleting the skeleton
- **Context:** The design exists only as the client's Figma; the theme has a clear token/component structure in `source/styles/`.
- **Decision:** Keep `docs/DESIGN.md`, filled from code, with the code as the source of truth.
- **Alternatives rejected:** deleting the skeleton — future UI features would have no shared vocabulary for screens and components.
- **Consequences:** a UI feature's design brief starts from DESIGN.md → Tokens/Components; new components are added there in the same step.

## 2026-09-08 — [ga-telegram-bridge] A standalone plugin in a new code area
- **Context:** The daily Google Analytics → Telegram report must work on any WordPress site, not only Dovira; the theme is Dovira-specific and its Telegram code is a `core` shared surface with open questions (token in code, public routes).
- **Decision:** Build `ga-telegram-bridge` as a self-contained plugin in the new code area `wp-content/plugins/ga-telegram-bridge/` (own `CLAUDE.md`), inside this repo; it never depends on the theme and never touches the theme's Telegram code.
- **Alternatives rejected:** a feature module inside the theme (`inc/features/`) — ties the report to Dovira and to the theme's hardcoded bot; a separate git repository from day one — Init mode overhead for a plugin whose only v1 customer is this repo's two installs; it can be extracted later.
- **Consequences:** the plugin has its own conventions (namespace `GaTelegramBridge`, prefix `gatb_`, text domain `ga-telegram-bridge`) and its own tooling; the Features table gets a second row, so `/plan-step` requires the feature name from now on; done for v1 = the report runs on `dovira.vet` and `kyiv.dovira.vet`, each install reporting its own GA4 property.

## 2026-09-08 — [ga-telegram-bridge] Report content and comparison baselines
- **Context:** The recipient is a business owner who does not open GA; the message must answer "are people coming and from where" and stay short enough to be read every day.
- **Decision:** One daily message per install with: `activeUsers` yesterday vs the average of the previous 7 days; `activeUsers` for the last 28 days vs the previous 28 days; top-5 pages (by `screenPageViews`, labelled by `pageTitle`, `pagePath` as fallback) for yesterday and for 28 days; `sessionDefaultChannelGroup` shares, top cities and `deviceCategory` shares for 28 days. All date ranges are relative (`yesterday`, `NdaysAgo`) and therefore resolved in the GA4 property's reporting time zone.
- **Alternatives rejected:** `totalUsers` — the GA4 UI headline "Users" is `activeUsers`, the owner would see a different number than in the app; comparing yesterday with the day before — weekday vs weekend gives false dynamics; same weekday last week — a single day is noisy for a small site; key events / conversions in v1 — event names differ per site and need a configurable list, deferred; a weekly digest as a second template in v1 — doubles templates and schedules, deferred.
- **Consequences:** `ReportBuilder` issues 6 reports in 2 `batchRunReports` calls (users with 4 date ranges; pages ×2; channels; cities; devices); the report is a pure data structure the renderer formats; adding key events later is a new block, not a rewrite.

## 2026-09-08 — [ga-telegram-bridge] Google access via a service account, own minimal API client
- **Context:** Server-side reading of the GA4 Data API needs credentials without a browser; the plugin must install on arbitrary hosts without Composer.
- **Decision:** Authenticate with a service-account JSON key (admin creates it in their Google Cloud, grants it Viewer on the property, pastes the JSON into the plugin): sign a RS256 JWT with `openssl_sign`, exchange it at `https://oauth2.googleapis.com/token` (`grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer`, scope `https://www.googleapis.com/auth/analytics.readonly`), cache the access token in a transient for its `expires_in`, and call `POST https://analyticsdata.googleapis.com/v1beta/properties/{id}:batchRunReports` through `wp_remote_post`.
- **Alternatives rejected:** Google PHP SDK (`google/analytics-data`, `google/auth`) — tens of MB of vendor to commit or build, and `guzzle`/`google/*` version clashes with other plugins on a host site; OAuth "Sign in with Google" — needs an OAuth client, consent screen verification and refresh-token handling for a one-time server credential; key via env/constant only — a non-developer could not configure the plugin.
- **Consequences:** zero runtime Composer dependencies (`vendor/` of the plugin is gitignored, dev tools only); the `openssl` PHP extension is a requirement checked at activation; `GoogleAuth` and `GaClient` are the only classes that know Google URLs; the JWT signing and the response parsing are unit-tested without network.

## 2026-09-08 — [ga-telegram-bridge] Plugin structure, storage and secrets
- **Context:** The plugin must be readable by a coding agent step by step and safe to install next to any theme.
- **Decision:** PSR-4 classes under `src/` (namespace `GaTelegramBridge`) loaded by the plugin's own `spl_autoload_register` autoloader; modules `Settings`, `GoogleAuth`, `GaClient`, `ReportBuilder`, `MessageRenderer`, `TelegramClient`, `Scheduler`, `RunLog`, `Admin`. Storage is three non-autoloaded options: `gatb_settings` (one array), `gatb_state` (`last_report_date`, `attempt`), `gatb_log` (last 30 runs). The service-account JSON and the bot token live in `gatb_settings` but are overridden by the constants `GATB_GA_SERVICE_ACCOUNT_JSON` and `GATB_TELEGRAM_BOT_TOKEN` when defined in `wp-config.php` (the admin fields then show "set in configuration" and are read-only). Secrets are never written to the log or to error messages.
- **Alternatives rejected:** custom DB tables — three small records do not justify schema management and uninstall complexity; secrets only in options — on Dovira they would end up in the committed `mysql.sql` snapshots; Composer autoloading at runtime — requires `vendor/` on every host.
- **Consequences:** `uninstall.php` deletes the three options and the cron events; DATA-MODEL.md gets the three options; Dovira installs define both constants in `wp-config.php` (gitignored), which satisfies core rule 7; a settings save re-validates and re-schedules.

## 2026-09-08 — [ga-telegram-bridge] Scheduling, retries and idempotency on WP-Cron
- **Context:** The report must go out once a day at a configured local time; WP-Cron fires only on traffic and jobs can run twice or late.
- **Decision:** One daily `wp_schedule_event` (`gatb_daily_report`) computed from the configured time in the site time zone and re-registered on every settings save; on a GA or Telegram failure the run schedules a `wp_schedule_single_event` retry one hour later, up to a configurable maximum of 3 attempts, then sends a short "report for {date} could not be generated" message to the same chat and records the reason in `gatb_log`. A run first compares the target date with `gatb_state.last_report_date` and exits when the report for that date was already sent, so duplicate cron firings and "Send now" never duplicate a message. Exact timing is out of scope: the readme recommends `DISABLE_WP_CRON` + a system cron calling `wp-cron.php`.
- **Alternatives rejected:** a REST/WP-CLI trigger for an external cron as the primary mechanism — makes hosting setup a requirement for every site; silent retries without a failure notice — the owner would not know a day is missing; no idempotency guard — WP-Cron double runs are a documented reality.
- **Consequences:** `Scheduler` is the only module that touches cron; "Send now" in the admin bypasses the date guard explicitly (it is a manual, logged action); the failure notice is a second, minimal template of `MessageRenderer`.

## 2026-09-08 — [ga-telegram-bridge] Testing tooling and the project check command
- **Context:** The project has no tests, linter or static analysis (TECH-STACK.md → Check command); this is the first feature, so its Sprint 1 Step 1 must create the gate.
- **Decision:** Dev dependencies in the plugin's `composer.json` only: PHPUnit with Brain\Monkey (unit tests that stub WordPress functions, no WordPress bootstrap), PHP_CodeSniffer with WordPress Coding Standards, PHPStan with `szepeviktor/phpstan-wordpress`. The check command is `bin/check.sh` at the repo root: it runs PHPCS, PHPStan and PHPUnit inside the plugin and `php -l` over the theme's PHP files, exiting non-zero on the first failure.
- **Alternatives rejected:** the WordPress test suite (`WP_UnitTestCase`, wp-env) — needs Docker and a database in CI for logic that is mostly pure (JWT, parsing, rendering, dynamics maths); skipping PHPStan — the plugin runs on unknown hosts, typing errors must not reach them; including the theme's `npm run build` in the gate — `assets/` are committed and rebuilt only when the front end changes.
- **Consequences:** TECH-STACK.md → Check command and root `CLAUDE.md` → Commands name `bin/check.sh`; `TESTING.md` is created for the project (unit vs integration, how to run, what is never mocked: the maths and the renderer run on recorded GA responses stored as fixtures); every later step commits through the gate; the theme gains only `php -l`, not tests.

## 2026-09-08 — [ga-telegram-bridge] No UI design phase; message format and configurable blocks
- **Context:** The design question of Phase B (Claude Design / in chat / not at all). The feature's only screens are a wp-admin settings page and the Telegram message itself.
- **Decision:** No design is made: the admin page uses the Settings API and stock wp-admin styling; the Telegram message is sent with `parse_mode=HTML` — a bold header (site host + report date), bold block titles, ▲/▼ with a percentage for dynamics, every dynamic value HTML-escaped — and its exact template is fixed in `FEATURE.md` → UI. The five blocks (visitors, top pages, channels, cities, devices) are checkboxes in the settings, all on by default; the visitors block cannot be disabled.
- **Alternatives rejected:** Claude Design or in-chat mockups — nothing to design beyond stock admin components; MarkdownV2 — page titles with `.`, `-`, `(` need per-character escaping and break silently; plain text — hard to scan; a fixed block set — the composition of the report is a business value (core rule 6), not a constant.
- **Consequences:** no `docs/DESIGN.md` changes and no `design/` folder for this feature; the message template in `FEATURE.md` is the reference the renderer tests assert against; a disabled block is skipped in both the GA requests and the rendering.

## 2026-09-08 — [ga-telegram-bridge] The check command runs on PHP 8.3+, tooling pinned to what 8.3 accepts
- **Context:** Sprint 1 Step 1 installed the tooling fixed by DECISIONS "Testing tooling and the project check command". The gate can be run from two places with different PHP versions — the developer's host (PHP 8.5.4) and the DDEV web container (PHP 8.3.31, where WP-CLI verification happens) — while the plugin itself must run on PHP 8.1.
- **Decision:** `bin/check.sh` requires PHP ≥ 8.3 and works from either place; the plugin's dev tooling is pinned to versions that install under 8.3 — PHPUnit `^12.5` (13.x requires PHP ≥ 8.4.1) and PHPCS `^3.13` (WPCS 3.4 does not support PHPCS 4) — with `config.platform.php = "8.3"` and a **committed `composer.lock`** so both places resolve identically; PHPStan runs at level 8 with `phpVersion: 80100`, analysing against the plugin's minimum PHP, not the host's.
- **Alternatives rejected:** the newest majors (PHPUnit 13, PHPCS 4) — the gate would then run only on the host; no committed lock (the theme's convention) — the two PHP versions could resolve different tool versions and disagree about what "green" means; analysing at the host's PHP version — would hide PHP 8.1 incompatibilities until a production host found them.
- **Consequences:** moving past PHPUnit 12 or PHPCS 3 requires raising DDEV's PHP first; the plugin header's `Requires at least: 7.1` is the WordPress version this repo actually ships (the whole WP root is versioned here), and lowering it for other sites is a Sprint 2 packaging question.

## 2026-09-09 — [ga-telegram-bridge] "Check GA" proves the connection through the Data API, not the Admin API
- **Context:** Sprint 1 Step 4 asked for `getPropertyName()` against the Admin API (`GET analyticsadmin.googleapis.com/v1beta/properties/{id}`), so the check could show the property's display name, with the instruction to verify endpoint and scope while planning and to fall back to a `runReport` with `limit 1` if the Admin scope was needed. Both halves were verified with a read-only probe using the real service-account key: the scope is fine (`properties.get` accepts `analytics.readonly`, which the plugin already requests), but the **Google Analytics Admin API is a separate service and is not enabled** in the Cloud project — it answers `403 PERMISSION_DENIED`, `reason: SERVICE_DISABLED`.
- **Decision:** The plugin talks to one Google API only. `GaClient::check_connection()` runs one `runReport` with `limit 1` against the Data API and reports the property id together with the reporting time zone and currency taken from the response's own `metadata` (`Europe/Kiev` / `USD` for the Kharkiv property). There is no `get_property_name()` and no Admin API call anywhere.
- **Alternatives rejected:** enabling the Admin API and showing the display name — a second API every install of this plugin would have to switch on, a second host in the integration surface, and one more error to map for the sites that skip it, in exchange for a nicer label; trying the Admin API with a runtime fallback — about twice the code for the same answer (core rule 5).
- **Consequences:** setting the plugin up needs exactly one API enabled (Data API) plus Viewer on the property; the check names an id rather than a name, but it also names the reporting time zone, which is what decides the day the report calls "yesterday" (FEATURE.md invariant); showing the display name later is a small change to one method if the Admin API is ever enabled.

## 2026-09-09 — [ga-telegram-bridge] The date guard runs after the report is built, not before
- **Context:** DECISIONS "Scheduling, retries and idempotency on WP-Cron" says a run "first compares the target date with `gatb_state.last_report_date` and exits when the report for that date was already sent". Sprint 1 Step 8 had to build that guard, and the day a report is about is fixed elsewhere as the **property's** day: FEATURE.md's invariant resolves every range in the property's reporting time zone, and the plugin learns that zone only from the `metadata.timeZone` of Google's own answer (Step 2 spike).
- **Decision:** `Runner::run()` builds the report first and compares `Report::$date` with `gatb_state.last_report_date` immediately afterwards; on a match it returns without rendering, sending or logging anything. `gatb_state.last_report_date` therefore always holds a day in the property's calendar.
- **Alternatives rejected:** guarding before the build against "yesterday in the site time zone" (what `SPRINT-2.md` → Step 1 currently describes) — the site's zone and the property's can differ, so the guard would compare two different calendars and would eventually either send a day twice or skip one; caching the property's time zone in `gatb_state` so the date can be computed before the call — a second copy of a value that arrives with every response, and one more thing to invalidate when the property changes.
- **Consequences:** a duplicate cron firing costs one pair of `batchRunReports` calls and sends nothing — about 2 of the 200 000 daily tokens the spike measured — which is the price of not keeping that second copy; `docs/DATA-MODEL.md` records `last_report_date` as the property's day; and SPRINT-2 Step 1's wording is now a contradiction, listed in `SPRINT-1-CLOSE.md` for the retro to settle before that step is planned.

---

## Open questions from the adoption audit (not decisions — to be settled in a Feature-mode discovery)
1. **Telegram bot token and join password are string literals in code**
   (`inc/rest-api/TelegramController.php`, `inc/utils/{conversation,application}.php`,
   `inc/rest-api/QuestionaryController.php`) — conflicts with core rule 7
   (secrets via environment config). Candidate: move to the theme `.env`
   and rotate the token.
2. **Three `dovira/v1/telegram/*` routes are public** (`permission_callback:
   __return_true`), including `send-test-message` (hardcoded personal chat id
   and unrelated text) and `reset-telegram-log` — a cleanup/lock-down candidate.
3. **Legacy LMS remnants** (`templates/{sign-in,sign-up,profile,testing}.php`,
   `student` role, `user_study_state`, `chapter`/`question` helpers,
   `authentication.js`, `reset-progress.js`, `learning-plan` styles): remove or keep?
4. **CF7 form ids (6, 1430, 1399) and admin URLs (`dovira.vet`, `dev.dovira.vet`)
   are hardcoded** — they differ per environment/city install; a settings
   option would make Kyiv and dev behave the same as Kharkiv.
5. **`blood_group` choices differ** between the field definition
   (`fields/post-type-questionary.php`) and the admin filter
   (`utils/questionary.php`) — one list should win.
6. **`processing_date` adds a fixed +10800 s** instead of using WordPress
   timezone functions.
7. **No check command, tests, linter or static analysis** — the first
   feature's Sprint 1 Step 1 creates the gate (TECH-STACK.md → Check command).
8. **`wp-content/themes/dovira/temp-data/` and `reports/`** hold one-off ops
   data (SEO import, translation bundles, city diffs) inside the deployable
   tree; keep, move, or gitignore?
