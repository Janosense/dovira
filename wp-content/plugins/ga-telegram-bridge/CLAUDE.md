# ga-telegram-bridge plugin — code-area conventions

<!-- Code-area CLAUDE.md: ONE per code directory (an app, package, or
     service; in WP — a theme or plugin), even when
     several features live inside it. Delta only, ≤60 lines — everything
     shared (core rules, step protocol, git model) stays in the ROOT CLAUDE.md.
     Claude Code loads this file automatically when working inside this
     directory. Feature docs do NOT live here — they are in
     docs/features/{feature}/ (see the Features table in root CLAUDE.md). -->

The "Google Analytics → Telegram bridge" plugin — a standalone WordPress
plugin (feature `ga-telegram-bridge`) that must run on any site: it never
references the `dovira` theme, its functions, options or data.

## Feature isolation (if this area hosts several features)
- One feature today. If another ever lands here, it gets `src/Features/{Name}/`
  with its own bootstrap and one registration line in `src/Plugin.php`.
- `ga-telegram-bridge.php` holds only the header, the autoloader and `Plugin::boot()`.
- A feature never writes to data owned by another feature (see each FEATURE.md → Data).

## Area conventions
- PHP ≥ 8.1, `declare(strict_types=1)`, PSR-4 under `src/` in namespace
  `GaTelegramBridge`, one class per file, loaded by the plugin's own
  `spl_autoload_register` — no Composer autoloader at runtime, zero runtime
  dependencies; `composer.json` is dev tooling only and `vendor/` is gitignored.
- Prefix everything WordPress-visible with `gatb_` (options, transients,
  cron hooks, filters, admin-post actions, settings ids); text domain
  `ga-telegram-bridge`; source strings in English, the `uk` translation in
  `languages/` (the locale is `uk`, not `uk_UA` — a `uk_UA` file never loads).
  A new or changed string means regenerating the `.pot` and the `.mo` below.
- WordPress Coding Standards (PHPCS `phpcs.xml.dist`), PHPStan
  (`phpstan.neon.dist`) — both gate every commit through root `bin/check.sh`.
- Network only through `wp_remote_post`/`wp_remote_get` with explicit timeouts,
  only from `GoogleAuth`, `GaClient`, `TelegramClient`; never from a public
  request — cron callbacks and admin-post handlers are the only callers.
- Secrets (`service_account_json`, `telegram_bot_token`) are read only through
  `Settings` getters (constants `GATB_GA_SERVICE_ACCOUNT_JSON`,
  `GATB_TELEGRAM_BOT_TOKEN` win over the option) and never appear in logs,
  exceptions, notices, fixtures or tests.
- Admin: Settings API, capability `manage_options`, nonce on every action,
  `esc_html`/`esc_attr` on output, `sanitize_*` on input. No custom CSS/JS
  unless a step plan approves it. The screen never calls `settings_errors()`:
  wp-admin prints the notices of every screen under Settings itself. The version
  it shows is read from the plugin header (`Plugin::version()`), never copied into
  a constant beside it, and every link into `readme.txt` goes through
  `Admin::readme_link()`, which builds that URL in one place.
- Data: only the options `gatb_settings`, `gatb_state`, `gatb_log`, the
  transient `gatb_google_access_token` and the cron hooks `gatb_daily_report`,
  `gatb_retry_report` — all removed by `uninstall.php`. No custom tables.
  `uninstall.php` runs with the plugin unloaded (core `define()`s
  `WP_UNINSTALL_PLUGIN` and includes it), so it has no autoloader and spells its
  names out; `UninstallTest` holds every literal against the class constant.
- Cron: `Scheduler` is the only class that schedules or clears events; every
  run passes through `Runner` and its date guard. A settings save is caught on
  **both** `update_option_gatb_settings` and `add_option_gatb_settings`: while
  the stored row still equals the registered default, core writes it through
  `add_option()`. Only a `cron` or `retry` run books another attempt; `manual`
  never does. `gatb_retry_report` carries its day as an argument, so it is
  registered with one accepted argument and removed with `wp_unschedule_hook()`
  — `wp_clear_scheduled_hook()` matches only events registered with none.
- Tests: `tests/Unit/` with PHPUnit + Brain\Monkey, fixtures in
  `tests/fixtures/`, rules in `docs/TESTING.md`.

## Local commands
```bash
cd wp-content/plugins/ga-telegram-bridge && composer install     # dev tooling only
cd wp-content/plugins/ga-telegram-bridge && composer test        # PHPUnit (script defined in Sprint 1 Step 1)
bin/check.sh                                                     # full gate from the repo root
ddev wp plugin activate ga-telegram-bridge
ddev wp cron event list                                          # see gatb_* events

# Translations, after adding or changing a string:
ddev exec wp i18n make-pot wp-content/plugins/ga-telegram-bridge \
  wp-content/plugins/ga-telegram-bridge/languages/ga-telegram-bridge.pot \
  --domain=ga-telegram-bridge --exclude=vendor,tests,spike,.phpunit.cache
# DDEV syncs container writes to the host a moment later: read the .pot back and
# check its entry count changed before rebuilding the .po from it.
# translate the new entries in languages/ga-telegram-bridge-uk.po, then:
ddev exec wp i18n make-mo \
  wp-content/plugins/ga-telegram-bridge/languages/ga-telegram-bridge-uk.po \
  wp-content/plugins/ga-telegram-bridge/languages/
```
