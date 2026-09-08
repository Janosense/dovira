# Step plans — ga-telegram-bridge, Sprint 1

<!-- Written by /plan-step, one section per step. Approval of a section
     authorizes that step only. -->

## Plan — Sprint 1, Step 1: Delta-audit, plugin skeleton, check command   (status: implemented, awaiting close)

### Branch
`ga-telegram-bridge/sprint-1-report-on-demand` ← `master`
(git model in root `CLAUDE.md` is *simple*: task branch → `master`; `SPRINT-1.md` → Branch
names this same branch, so the sprint branch is the task branch for every step of Sprint 1.)

### Delta-audit (task 1, no code)
Read: `docs/features/core/FEATURE.md`, `wp-content/themes/dovira/CLAUDE.md`,
`docs/ARCHITECTURE.md` → Modules / Integrations / Environments. Result:

- The plugin touches **no** `core` code and **no** `core` data. It adds no line to
  `functions.php` (the theme's feature-registration convention does not apply — the plugin
  is activated in wp-admin), reads none of `core`'s helpers (`dovira_*`), post types,
  options (`options_*`, `telegram_bot_chats`, `telegram_webhook_data`) or Polylang strings.
- The theme's existing Telegram bot (`inc/rest-api/TelegramController.php`,
  `inc/utils/{conversation,application}.php`, `QuestionaryController.php`) is an
  **unrelated integration** that happens to use the same vendor API: its own bot token
  (string literal in theme code), its own chat registry (`telegram_bot_chats`), its own
  message texts. The plugin gets a separate bot token (`gatb_settings` /
  `GATB_TELEGRAM_BOT_TOKEN`) and a single configured chat id; neither reads the other's
  data. `ARCHITECTURE.md` → Integrations therefore gets a **second** Telegram row rather
  than an edit to the existing one.
- Shared code touched: none. `bin/check.sh` is *new* project-level tooling at the repo root
  that both features will gate through; it only reads theme files (`php -l`).
- Deploy shape is unchanged: `wp-content/plugins/*` is committed (ACF Pro, Polylang, … all
  are), so the plugin ships by the same file sync; its `vendor/` (dev tooling only) is
  gitignored and never deployed.

### Tasks (ordered)
- [x] **Task 1 — delta-audit + plugin skeleton.** Create
  `wp-content/plugins/ga-telegram-bridge/ga-telegram-bridge.php` (header: *Google Analytics
  → Telegram bridge*, `Version: 0.1.0`, `Requires at least: 7.1`, `Requires PHP: 8.1`,
  `Text Domain: ga-telegram-bridge`, `Domain Path: /languages`, GPL-2.0-or-later; `ABSPATH`
  guard; `define( 'GATB_PLUGIN_FILE', __FILE__ )`; `spl_autoload_register` for
  `GaTelegramBridge\` → `src/`; `register_activation_hook`; `Plugin::boot()`), `src/Plugin.php`,
  `.gitignore`, `readme.txt` stub, `languages/.gitkeep`, and commit the area `CLAUDE.md`
  that discovery already left on disk untracked (as-is, no edits).
  → commit `feat(ga-telegram-bridge): add plugin skeleton with autoloader and activation guard`
- [x] **Task 2 — dev tooling + smoke tests.** `composer.json` (dev-only requires, `autoload-dev`
  PSR-4 for `src/` and `tests/`, scripts `test` / `lint` / `lint:fix` / `analyse`,
  `allow-plugins` for the PHPCS installer), committed `composer.lock`, `phpcs.xml.dist`,
  `phpstan.neon.dist`, `phpunit.xml.dist`, `tests/bootstrap.php`, `tests/TestCase.php`,
  `tests/Unit/PluginTest.php`. Run `composer install` (installs the approved dev packages —
  see *Dependencies*), then PHPCS/PHPStan/PHPUnit green; any style or type fixes to the
  task-1 files belong to this commit (the tools do not exist before it).
  → commit `chore(ga-telegram-bridge): add PHPUnit, PHPCS and PHPStan dev tooling`
- [x] **Task 3 — the check command.** `bin/check.sh` at the repo root (executable, `set -euo
  pipefail`): PHP ≥ 8.3 guard → `composer install` in the plugin when `vendor/` is missing →
  PHPCS → PHPStan → PHPUnit → `php -l` over every theme `*.php` outside `vendor/` and
  `node_modules/` (108 files today), exiting non-zero on the first failure and printing the
  file that failed.
  → commit `chore: add bin/check.sh check command`
- [x] **Task 4 — docs.** The four documents under *Docs to update*, with the versions actually
  locked by `composer.lock` and the audit findings above.
  → commit `docs: record the ga-telegram-bridge plugin, its tooling and the check command`

Only the paths named below are staged; the untracked ops artifacts already in the working
tree (`_to_delete/`, `mysql.sql`, `backup-before-yoast-2026-08-24.sql`, `reports/`) are left
untouched.

### Dependencies to approve (core rule 4)
Dev-only, in the plugin's `composer.json`; nothing is added to the theme and the plugin keeps
**zero runtime dependencies**. Versions verified against packagist and resolved end-to-end
with `composer update --dry-run` on 2026-09-08 (host PHP 8.5.4 / Composer 2.9.5):

| Package | Constraint | Resolves to | Why |
|---|---|---|---|
| phpunit/phpunit | `^12.5` | 12.5.34 | test runner. **Not `^13`**: 13.x requires PHP ≥ 8.4.1 and would not run inside DDEV (PHP 8.3); 12.5 requires ≥ 8.3 and runs both on the host and in DDEV |
| brain/monkey | `^2.7` | 2.7.0 (+ mockery 1.6.15, antecedent/patchwork 2.2.3, hamcrest 3.0.0) | stubs WordPress functions without bootstrapping WordPress |
| squizlabs/php_codesniffer | `^3.13` | 3.13.6 | linter. **Not `^4`**: WPCS 3.4.1 requires PHPCS `^3.13.5` |
| wp-coding-standards/wpcs | `^3.4` | 3.4.1 (+ phpcsutils 1.2.3, phpcsextra 1.5.1) | WordPress Coding Standards |
| dealerdirect/phpcodesniffer-composer-installer | `^1.2` | v1.2.1 | registers the WPCS standard with PHPCS |
| phpstan/phpstan | `^2.2` | 2.2.13 | static analysis |
| szepeviktor/phpstan-wordpress | `^2.0` | v2.0.4 (+ php-stubs/wordpress-stubs v7.1.0) | WordPress stubs for PHPStan; the stubs match the installed WP 7.1 |

### Files to create/change
**New — `wp-content/plugins/ga-telegram-bridge/`**
- `ga-telegram-bridge.php` — header, `ABSPATH` guard, `GATB_PLUGIN_FILE`, autoloader,
  `register_activation_hook( __FILE__, [ Plugin::class, 'activate' ] )`, `Plugin::boot()`.
- `src/Plugin.php` — `boot()` registers exactly one hook for now: `init` →
  `load_textdomain()` (`load_plugin_textdomain( 'ga-telegram-bridge', false, … '/languages' )`);
  `activate()` (WordPress passes `$network_wide` as the first argument, so it takes no
  argument of ours) calls the pure
  `activation_blocked_message( bool $has_openssl ): ?string` with
  `extension_loaded( 'openssl' )` and, when it returns a message, `deactivate_plugins()` +
  `wp_die( …, '', [ 'back_link' => true ] )`. Splitting the guard this way is what makes
  both the unit test and the manual check below possible on a host that always has openssl.
  No menu, no settings, no network calls — those are Steps 3–5.
- `.gitignore` — `vendor/`, `.phpunit.cache/`, `.phpcs.cache`.
- `readme.txt` — stub (name, short description, `Requires at least: 7.1`, `Requires PHP: 8.1`,
  License). The full readme for other sites is Sprint 2 (Out of scope).
- `languages/.gitkeep` — the directory the `Domain Path` header points at; `.pot`/`.po`/`.mo`
  arrive in Step 7.
- `CLAUDE.md` — already written by discovery, currently untracked; committed unchanged.
- `composer.json`, `composer.lock` — dev tooling only. The lock **is** committed (the theme
  ignores its lock, but here the lock is what makes `bin/check.sh` reproducible; `vendor/`
  stays ignored as the area `CLAUDE.md` requires).
- `phpcs.xml.dist` — ruleset `WordPress` over the plugin, `vendor/` excluded, with:
  `WordPress.Files.FileName` excluded (PSR-4 wants `Plugin.php`, the sniff wants
  `class-plugin.php` — PSR-4 is fixed by DECISIONS "Plugin structure, storage and secrets"),
  `minimum_wp_version` 7.1, `PrefixAllGlobals` prefixes `gatb` / `GaTelegramBridge`,
  `WordPress.WP.I18n` text domain `ga-telegram-bridge`.
- `phpstan.neon.dist` — includes `szepeviktor/phpstan-wordpress`, `level: 8`,
  `phpVersion: 80100` (analyse against the minimum supported PHP, not the host's 8.5),
  paths `ga-telegram-bridge.php`, `src`, `tests`. Level 8 is the plan's answer to the
  sprint's "level agreed in the step plan": it covers nullables and union types on
  greenfield `strict_types` code without forcing `mixed` gymnastics around
  `get_option()`/`wp_remote_post()` return values.
- `phpunit.xml.dist` — bootstrap `tests/bootstrap.php`, suite `Unit` → `tests/Unit`,
  `failOnWarning`/`failOnNotice`/`failOnDeprecation` true, cache in `.phpunit.cache`.
- `tests/bootstrap.php` — Composer autoloader + `GATB_PLUGIN_FILE` defined to a dummy path
  (the plugin file itself is never loaded in tests: its `ABSPATH` guard would exit).
- `tests/TestCase.php` — base case doing `Monkey\setUp()` / `Monkey\tearDown()`.
- `tests/Unit/PluginTest.php` — the smoke test (see *Tests to write*).

**New — repo root**
- `bin/check.sh` (mode 755).

**Changed — docs only** (task 4): `docs/TECH-STACK.md`, root `CLAUDE.md`,
`docs/ARCHITECTURE.md`, `docs/TESTING.md`.

**Not created here** (Sprint 1 Out of scope / later steps): `uninstall.php`, `Settings`,
`Admin`, any cron, any `wp_remote_*` call, the `spike/` directory (Step 2).

### Tests to write
`tests/Unit/PluginTest.php` (PHPUnit + Brain\Monkey, no WordPress loaded):
- `boot()` adds exactly one `init` action for the textdomain loader
  (`Actions\expectAdded( 'init' )->once()`) — this is the smoke test the step asks for: it
  proves the harness, the autoloader and the WP-function stubs all work.
- `activation_blocked_message( true )` returns `null`.
- `activation_blocked_message( false )` returns a non-empty, translated message naming
  OpenSSL (`__()` stubbed with `Functions\when( '__' )->returnArg()`).

Nothing else is testable in this step: the step adds no form pipeline, no REST route, no
price grouping and no CLI command, so none of the profile's test-critical zones is entered.

### Docs to update
- `docs/TECH-STACK.md` — **Check command** section rewritten as *exists* (`bin/check.sh`,
  what it runs, PHP ≥ 8.3); Stack table: the `Testing` row replaced by the real tooling with
  locked versions, a new row for the plugin code area; **Approved dependencies log**: one
  row per package above with its locked version and reason.
- root `CLAUDE.md` → Commands — the `# check command: none yet …` line replaced by
  `bin/check.sh` with a one-line description.
- `docs/ARCHITECTURE.md` — Overview diagram: the plugin next to the third-party plugins;
  Feature map: the second feature and its code area; Modules: a row for the plugin (location,
  responsibility, "must never: reference the theme, its functions, options or data");
  Integrations: a **GA4 Data API** row (service account → JWT → `batchRunReports`) and a
  **second Telegram row** for the plugin's own bot token and single chat id — both marked
  *wired up in Sprint 1 Steps 4–5* so the file keeps describing what exists.
- `docs/TESTING.md` → How to run — drop "until then this section is aspirational" and name
  the composer script names fixed in task 2.

### Checks
- **ANTI-PATTERNS:** none violated. The relevant ones and how the step stays on the right
  side: tooling is a committed script plus `composer.json` dev dependencies, never a global
  install; the plugin is a normal plugin, not `mu-plugins/` (gitignored, would not deploy);
  no runtime code is placed in `temp-data/`, `reports/` or `_to_delete/`; no theme file,
  ACF group, block, post type, taxonomy, CF7 id or `assets/` artifact is touched, so the
  theme-scoped anti-patterns cannot be reached; nothing city-specific goes near `master`.
- **Docs vs reality:**
  - `wp-content/plugins/ga-telegram-bridge/CLAUDE.md` exists on disk but is **untracked** —
    discovery wrote it; task 1 commits it unchanged.
  - `docs/WORKLOG.md` has no entries, `SPRINT-1-PLAN.md` did not exist, `SPRINT-1.md` Step 1
    is unticked and has no dependencies → nothing in flight, Step 1 is the project's first
    step. Verified.
  - WordPress **7.1** confirmed (`wp-includes/version.php`, `ddev wp core version`), so
    `Requires at least: 7.1` is a value verified against the install as the step asks — and
    since the whole WP root is this repo, all three environments run that same core.
  - All 108 theme PHP files pass `php -l` on the host PHP 8.5.4 today, so task 3 lands green.
  - PHP versions differ by where the gate runs: host 8.5.4, DDEV web 8.3.31 — the dependency
    constraints above are chosen so `bin/check.sh` runs under both.
  - Outside this step, no task: `docs/TECH-STACK.md` says the DB snapshot `mysql.sql` is
    "committed at repo root", but it is untracked (as are `reports/`,
    `backup-before-yoast-2026-08-24.sql`, `_to_delete/`). Worth an `/adhoc` later; it changes
    nothing here.
- **Design:** n/a — the step builds no screen (`docs/DESIGN.md` covers the theme's front end;
  FEATURE.md → UI records stock wp-admin components and applies from Step 3 on).
- **Check command:** `bin/check.sh` (docs/TECH-STACK.md → Check command) — **missing today,
  created in task 3**. Tasks 1–2 are verified by `composer test` / `lint` / `analyse` inside
  the plugin; from task 3 on, every commit of this feature goes through `bin/check.sh`.
- **Not locally verifiable:** the activation abort on a host **without** openssl — openssl is
  compiled into the PHP binary both on the host and in the DDEV image (`php -n -m` still
  lists it), so the extension cannot be switched off locally. It is covered by the unit test
  and by printing the guard's message with
  `ddev wp eval 'echo GaTelegramBridge\Plugin::activation_blocked_message( false );'`; the
  one real run is an activation on an openssl-less host, which no environment of this
  project provides. Everything else in the step's manual verification is locally runnable.

### Questions / ambiguities
none
