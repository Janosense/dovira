# Step plans — ga-telegram-bridge, Sprint 1

<!-- Written by /plan-step, one section per step. Approval of a section
     authorizes that step only. -->

## Plan — Sprint 1, Step 1: Delta-audit, plugin skeleton, check command   (status: closed)

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

---

## Plan — Sprint 1, Step 2: Spike — service account → GA4 Data API (timeboxed, throwaway)   (status: closed)

### Branch
`ga-telegram-bridge/sprint-1-report-on-demand` ← `master`
(the sprint's branch from `SPRINT-1.md`; it was merged and deleted at the close of Step 1,
so `/do-step` recreates it from `master`.)

### Prerequisites — the step cannot be verified without these
The sprint's Risks section calls this "Day 1" work and it is **yours**, not the step's:
1. A Google Cloud project with the **Google Analytics Data API** enabled.
2. A **service account** in that project with a downloaded **JSON key**.
3. That service account's e-mail granted **Viewer** on the Kharkiv GA4 property.
4. The key file placed at `wp-content/plugins/ga-telegram-bridge/spike/service-account.json`
   and the Kharkiv **property id** (9 digits, GA4 Admin → Property details) to hand.

Without 1–4 `/do-step` can write the probe but cannot observe a single exit criterion; it
would stop after task 1 and report. Task 1 commits the ignore rules before anything else, so
the key is out of git's reach from the first moment — but do not `git add` that path yourself.

### Timebox
One session, as the step fixes. A probe that cannot be made to fire inside the timebox is
recorded as *not observed* with the documented behaviour next to it — never faked, never
extended into a second session.

### Tasks (ordered)
- [x] **Task 1 — a spike sandbox git and the gate both ignore.** Add `spike/` to
  `wp-content/plugins/ga-telegram-bridge/.gitignore`, add `<exclude-pattern>/spike/*</exclude-pattern>`
  to `phpcs.xml.dist`, then create the untracked `spike/` directory with its own belt-and-braces
  `.gitignore` (`*`). The PHPCS exclusion is not optional: `phpcs.xml.dist` scans `<file>.</file>`
  with only `/vendor/*` excluded, so an unpolished throwaway probe would turn `bin/check.sh` red
  (Step 1 proved the scan reaches new files — the deliberate `src/TempViolation.php` was caught there).
  → commit `chore(ga-telegram-bridge): keep the Sprint 1 spike out of git and out of the gate`
- [x] **Task 2 — the probe and the happy path.** Write `spike/ga-probe.php` (untracked) and run
  `happy`: build the RS256 JWT, sign it with `openssl_sign( …, OPENSSL_ALGO_SHA256 )`, exchange it
  at `https://oauth2.googleapis.com/token`, then `POST …/properties/{id}:runReport` for
  `activeUsers` over `yesterday`. Print the number, the token's `expires_in`, and the shape of
  what came back. Never print the key, the token or any part of either — lengths and prefixes only.
  → no commit (`spike/` is gitignored by design); evidence = the probe's output in the close report
- [x] **Task 3 — the remaining observations.** Run the other probes and keep their raw JSON under
  `spike/dumps/` for Step 4's fixtures: `batch` (`batchRunReports` with the four users date ranges
  of DECISIONS "Report content and comparison baselines" — `yesterday`, `8daysAgo`–`2daysAgo`,
  `28daysAgo`–`yesterday`, `56daysAgo`–`29daysAgo`), `errors` (bad property id; a property the
  service account was never granted; a garbage bearer token on the Data API; a tampered JWT
  signature at the token endpoint), `skew` (`iat` +300 s and −3600 s), `quota` (a rapid sequential
  burst — best effort, see Timebox).
  → no commit; evidence = the dumps and the close report
- [x] **Task 4 — record the findings.** Write the pitfalls and exact error shapes into
  `docs/LEARNINGS.md`; touch `docs/DECISIONS.md` only if a finding contradicts a fixed decision —
  and then raise it before deviating, never in silence.
  → commit `docs(ga-telegram-bridge): record the GA4 service-account spike findings`

Commits use explicit pathspecs after `git add`, per the LEARNINGS entry of Step 1; the
`templates/*` deletions staged in your working tree stay out of this step's commits too.

### Files to create/change
**Tracked (2 commits total)**
- `wp-content/plugins/ga-telegram-bridge/.gitignore` — `+ spike/`
- `wp-content/plugins/ga-telegram-bridge/phpcs.xml.dist` — `+ <exclude-pattern>/spike/*</exclude-pattern>`
- `docs/LEARNINGS.md` — the findings entry (task 4)
- `docs/DECISIONS.md` — only on a contradiction, raised first
- `docs/features/ga-telegram-bridge/sprints/SPRINT-1-PLAN.md` — checkboxes

**Untracked, deleted at close**
- `spike/.gitignore` (`*`), `spike/service-account.json` (yours, never committed),
  `spike/config.local.php` (returns `key_path`, `property_id`, and the id used for the
  no-access probe), `spike/ga-probe.php`, `spike/dumps/*.json`.

`spike/ga-probe.php` takes the probe name as a positional argument — `wp eval-file <file> [<arg>…]`
puts them in `$args` (verified against `ddev wp help eval-file`) — so one file serves all five probes:
```bash
ddev wp eval-file wp-content/plugins/ga-telegram-bridge/spike/ga-probe.php happy
ddev wp eval-file wp-content/plugins/ga-telegram-bridge/spike/ga-probe.php batch
ddev wp eval-file wp-content/plugins/ga-telegram-bridge/spike/ga-probe.php errors
ddev wp eval-file wp-content/plugins/ga-telegram-bridge/spike/ga-probe.php skew
ddev wp eval-file wp-content/plugins/ga-telegram-bridge/spike/ga-probe.php quota
```
It calls Google through `wp_remote_post` with explicit timeouts — the same door `GoogleAuth` and
`GaClient` will use in Step 4, so what the spike learns about timeouts, redirects and body shapes
transfers instead of being an artefact of `curl`.

### What each probe must answer (the step's exit criteria)
| Probe | Recorded |
|---|---|
| `happy` | access token obtained; `activeUsers` for yesterday; how `private_key` survives `json_decode` → `openssl_pkey_get_private()` (the newline question); `expires_in` |
| `batch` | the `batchRunReports` response shape with four date ranges: whether the `dateRange` dimension must be requested explicitly, the `date_range_0..3` values, and the order of `reports[]` |
| `errors` | exact HTTP status + `error.status` / `error.message` body for: bad property id (expected 400), a property the account cannot read (expected 403 `PERMISSION_DENIED`), an invalid bearer token (expected 401), a tampered JWT at the token endpoint (Google answers the token endpoint with 400 `invalid_grant`, not 401 — the step's "401" is the label, the observation is what counts) |
| `skew` | how much clock skew Google tolerates on `iat` / `exp` |
| `quota` | the status of a quota error — a single PHP process makes sequential calls and will most likely *not* reach the concurrency quota, so the honest outcome is "not observed; Google documents HTTP 429 `RESOURCE_EXHAUSTED`", recorded as such |

### Tests to write
None — the step says so explicitly, and the plugin gains no committed code here. The spike is
throwaway; its recorded responses become `tests/fixtures/ga/*.json` in Step 4 (`docs/TESTING.md`
→ Fixtures), which is why task 3 keeps the raw dumps instead of only printing them. `bin/check.sh`
must still exit 0 on every commit of this step — task 1 is what makes that true while `spike/` exists.

### Docs to update
- `docs/LEARNINGS.md` — the GA4 pitfalls and the exact error shapes (task 4).
- `docs/DECISIONS.md` — only if a finding contradicts a fixed decision of the sprint (e.g. OpenSSL
  cannot sign the key on the target host); raise it before deviating.

### Checks
- **ANTI-PATTERNS:** none violated. No global tool install (nothing is installed at all); no
  runtime-loaded code in `temp-data/`, `reports/` or `_to_delete/`; no theme file, block, field
  group, post type or CF7 id touched; no per-city value on `master`; and core rule 7 is held by
  keeping the key out of git and out of every printed line.
- **Docs vs reality:**
  - The step says the key JSON is read "from a local file **outside the repo**". It cannot be:
    `ddev wp eval-file` runs inside the web container, which mounts only the project — I verified
    `/Users` does not exist in there and `df` shows just `/var/www`. Resolution taken: the key
    lives at `spike/service-account.json`, **inside the project but outside git** (`spike/` in the
    plugin's `.gitignore` plus a `*` `.gitignore` inside the directory). The intent — never
    committed, never deployed — holds, and the sprint's Definition of Done check (`git log -p` for
    the JSON key) stays clean. The alternatives lose more than they gain: running the probe on host
    PHP drops the WordPress HTTP API the real client will use, and mounting an extra host volume
    means committing DDEV config for a throwaway.
  - `docs/LEARNINGS.md` describes itself in its header comment as a log of *process* defects, while
    this step routes *technical* GA pitfalls there; root `CLAUDE.md` describes the file more broadly
    ("when something went wrong before — check if it's a known failure mode"). Following the step:
    the findings go there as a clearly labelled technical entry.
  - The spike must **survive** `/do-step` — your manual verification is running it yourself. Deleting
    `spike/` is a closing action, and before deleting it `/close-step` moves `spike/dumps/` to
    `~/dovira-gatb-spike/` (outside the repo), because Step 4's fixtures are made from those dumps.
  - Nothing else: `bin/check.sh` exists and exits 0 on `master`, the plugin is at the state Step 1
    left it, and no doc claims a GA or Telegram call already works (both integration rows in
    ARCHITECTURE are marked *wired up in Steps 4–6*).
- **Design:** n/a — no screen; the step adds no UI.
- **Check command:** `bin/check.sh` (docs/TECH-STACK.md → Check command) — exists, green on `master`.
- **Not locally verifiable:** every exit criterion of this step. There is no offline substitute for
  a real Google service account, a real property and real traffic data — the one real run is yours:
  `ddev wp eval-file …/ga-probe.php happy` against the Kharkiv property, with the number compared to
  GA4 → Reports for the same day. The quota status may legitimately end as *documented, not observed*.

### Questions / ambiguities
none

## Plan — Sprint 1, Step 3: Settings and the admin page   (status: closed)

### Branch
`ga-telegram-bridge/sprint-1-report-on-demand` ← `master`
(git model in root `CLAUDE.md` is *simple*: task branch → `master`; `SPRINT-1.md` → Branch
names this same branch. It was deleted at the close of Step 2 — `/do-step` re-creates it from
the current `master`, which carries Steps 1 and 2.)

### Tasks (ordered)

- [x] **1. `Settings`: the option, its defaults, validation and typed getters** (+ its tests and
  the DATA-MODEL section, same commit) → `feat(ga-telegram-bridge): store and validate the plugin settings`

  `src/Settings.php`, a final class of static methods (no state, no WordPress in the pure parts):

  - `OPTION = 'gatb_settings'`; `defaults()` returns the whole shape below.
  - `register()` — `register_setting( 'gatb_settings', 'gatb_settings', [ 'type' => 'array',
    'sanitize_callback' => [ self::class, 'sanitize' ], 'default' => self::defaults(),
    'show_in_rest' => false ] )`. Hooked on `admin_init` from `Plugin::boot()`.
  - **Option shape** (one array, non-autoloaded):

    | Key | Type | Default | Rule |
    |---|---|---|---|
    | `property_id` | string | `''` | trimmed; digits only (`/^\d+$/`) |
    | `service_account_json` | string | `''` | trimmed; must `json_decode` to an object and hold non-empty `client_email`, `private_key`, `token_uri` |
    | `telegram_bot_token` | string | `''` | trimmed; no format rule (Telegram's own check is Step 5's "Check Telegram") |
    | `telegram_chat_id` | string | `''` | trimmed; `/^-?\d+$/` |
    | `send_time` | string | `'09:00'` | `/^([01]\d|2[0-3]):[0-5]\d$/` |
    | `max_attempts` | int | `3` | integer 1–10 |
    | `blocks` | array<string,bool> | all five `true` | exactly the keys `visitors`, `pages`, `channels`, `cities`, `devices`; unknown keys dropped; `visitors` always `true` |

    Names `service_account_json` and `telegram_bot_token` are fixed by the plugin's `CLAUDE.md`;
    `telegram_chat_id` follows them. The five block keys are the five blocks of DECISIONS "No UI
    design phase…" — the two page tables of the FEATURE.md template are one block, `pages`.
    `send_time` and `max_attempts` are **stored and validated here, consumed in Sprint 2** —
    the step text names both sanitizers, nothing schedules or retries in Sprint 1.
  - **Validation rule (one decision, made here):** an *empty* value is always accepted and stored
    as `''` — an unconfigured install must be able to save a half-filled form. A *non-empty*
    value that breaks its rule is rejected **per field**: that field keeps the value currently
    stored, every other field of the same submit is saved, and an error is registered. This is
    what the step's manual verification asks for ("shows an error and keeps the previous value").
  - **Pure core:** `sanitize_settings( array $raw, array $current, array $locked ): array` returning
    `[ 'values' => …, 'errors' => [ field => message ] ]` — no WordPress calls, so every rule above
    is unit-tested directly. `$locked` = keys whose constant is defined; their submitted value is
    ignored and the stored one kept, with no error.
  - **WordPress face:** `sanitize( mixed $raw ): array` collects `$current` (`self::all()`) and
    `$locked`, calls the pure core and turns each error into `add_settings_error( 'gatb_settings',
    "gatb_{$field}", $message, 'error' )`. Error messages name the field and the expected format and
    **never echo the submitted value** (the JSON key is one of them) — plugin `CLAUDE.md`, core rule 7.
  - **Getters:** `all()`, `property_id()`, `service_account_json()`, `telegram_bot_token()`,
    `telegram_chat_id()`, `send_time()`, `max_attempts()`, `blocks()`, `is_block_enabled( string $key )`,
    `is_secret_locked( string $key )`. `all()` merges the stored option over `defaults()` (the nested
    `blocks` merged on its own, `visitors` forced `true`), so a partially written option can never
    produce a missing key or a wrong type at level 8.
  - **Constant overrides:** the two secret getters go through one pure seam,
    `secret( string $constant_name, string $stored ): string` → `defined()` ? `constant()` : stored,
    with `GATB_GA_SERVICE_ACCOUNT_JSON` for `service_account_json` and `GATB_TELEGRAM_BOT_TOKEN` for
    `telegram_bot_token` (DECISIONS "Plugin structure, storage and secrets"). The seam takes the
    constant *name* so both branches are testable without defining the real constants in the suite.
  - `src/Plugin.php`: `boot()` also registers `admin_init` → `Settings::register`; `activate()`, after
    the OpenSSL guard, calls `add_option( 'gatb_settings', Settings::defaults(), '', false )` — the
    only way to guarantee **autoload `no`** (the step's wording; `update_option` from `options.php`
    would create it autoloaded on a fresh install).

- [x] **2. `Admin`: the screen `Settings` under Settings → "GA → Telegram"** (+ its tests and the
  `readme.txt` settings description, same commit) → `feat(ga-telegram-bridge): add the GA → Telegram settings page`

  `src/Admin.php`, final class of static methods; `src/Plugin.php` registers `admin_menu` →
  `Admin::add_page` and `admin_init` → `Admin::add_fields`.

  - `add_page()` — `add_options_page( 'GA → Telegram', 'GA → Telegram', 'manage_options',
    'gatb-settings', [ self::class, 'render_page' ] )`, both titles through `__()`.
  - `add_fields()` — four sections on page `gatb-settings`: **Google Analytics** (`property_id`,
    `service_account_json`), **Telegram** (`telegram_bot_token`, `telegram_chat_id`), **Schedule**
    (`send_time`, `max_attempts`; section description says the values are stored now and start
    working when the daily schedule ships), **Report blocks** (the five checkboxes).
  - `render_page()` — `current_user_can( 'manage_options' )` guard, `settings_errors( 'gatb_settings' )`,
    `<form method="post" action="options.php">` with `settings_fields( 'gatb_settings' )` (this is
    the nonce + capability check `options.php` verifies), `do_settings_sections( 'gatb-settings' )`,
    `submit_button()`. Stock wp-admin markup, no CSS or JS (plugin `CLAUDE.md`).
  - Field renderers, one per type, every value escaped (`esc_attr`, `esc_textarea`, `esc_html`):
    - text / number / time inputs named `gatb_settings[{key}]`, `type="time"` for `send_time`,
      `type="number" min="1" max="10"` for `max_attempts`;
    - the two **secret** fields (JSON in a `<textarea rows="8">`, token in a text input) render the
      stored value when it is editable, and when the matching constant is defined render **empty**,
      `readonly`, with the description "Set in configuration (`wp-config.php`); the field is ignored."
      — the constant's value is never printed into the page;
    - the five block checkboxes render checked from the stored state; `visitors` renders
      `checked disabled` with the note that the visitors block is always sent (a disabled checkbox
      submits nothing, and `sanitize` forces it back to `true` — both halves of "cannot be disabled").
  - All strings through `__()` / `esc_html__()` with text domain `ga-telegram-bridge`; English
    source strings, the `uk_UA` translation ships in Step 7 with the `.pot`.

### Files to create/change
- `wp-content/plugins/ga-telegram-bridge/src/Settings.php` — new (task 1)
- `wp-content/plugins/ga-telegram-bridge/src/Admin.php` — new (task 2)
- `wp-content/plugins/ga-telegram-bridge/src/Plugin.php` — `boot()` gains three hooks (task 1: `admin_init` → `Settings::register`; task 2: `admin_menu`, `admin_init` for `Admin`), `activate()` creates the option
- `wp-content/plugins/ga-telegram-bridge/tests/Unit/SettingsTest.php` — new (task 1)
- `wp-content/plugins/ga-telegram-bridge/tests/Unit/SettingsSecretConstantsTest.php` — new (task 1)
- `wp-content/plugins/ga-telegram-bridge/tests/Unit/AdminTest.php` — new (task 2)
- `wp-content/plugins/ga-telegram-bridge/tests/Unit/PluginTest.php` — extended (tasks 1 and 2)
- `docs/DATA-MODEL.md` — new section (task 1)
- `wp-content/plugins/ga-telegram-bridge/readme.txt` — Description gains the configuration paragraph (task 2)

### Tests to write
`SettingsTest` (pure, no WordPress state):
- `defaults()`: five blocks all on, `max_attempts` 3, `send_time` `09:00`, the four credentials empty.
- a fully valid submit is stored with the right types (`max_attempts` an `int`, the rest strings).
- property id `abc` / `53a` → error on `property_id`, the previously stored id survives, everything
  else in the same submit is saved.
- service-account JSON: malformed → error; valid JSON without `private_key` → error; in both cases the
  stored JSON survives and **the error message contains no fragment of the submitted value**.
- chat id `@dovira` and `12a` → error, previous kept; `-1001234567890` and `123456` accepted.
- `send_time` `24:00`, `9:00`, `noon` → error, previous kept; `00:00` and `23:59` accepted.
- `max_attempts` `0`, `11`, `abc` → error, previous kept; `1` and `10` accepted.
- every field empty → no errors, all stored as `''` (a fresh install can save).
- blocks: an unchecked box (absent from the input) becomes `false`; an unknown key is dropped;
  `visitors` is `true` even when absent from the input **and** when the stored option says `false`.
- a key in `$locked` keeps the stored value and produces no error, whatever was submitted.
- `secret( 'GATB_NOT_DEFINED_IN_TESTS', 'from-option' )` returns the option value.
- `blocks()` / `is_block_enabled()` on a stored option missing keys → defaults fill in.

`SettingsSecretConstantsTest` — the one class that defines `GATB_GA_SERVICE_ACCOUNT_JSON` and
`GATB_TELEGRAM_BOT_TOKEN` (constants cannot be undefined; no other test asserts their absent branch
on the real names): both getters return the constant over a stored option, and `is_secret_locked()`
reports both keys locked.

`AdminTest` (Brain\Monkey stubs `add_options_page`, `esc_*`, `__`, `checked`, `disabled`, output buffered):
- `add_page()` registers the page with capability `manage_options` and slug `gatb-settings`.
- the JSON field with the constant defined: `readonly` present, "Set in configuration" present, and
  the constant's value **absent** from the HTML; without the constant: the stored JSON present and
  no `readonly` — the two states the admin actually sees.
- the `visitors` checkbox renders `checked` and `disabled`; `cities` renders enabled, `checked` when
  the stored option has it on and unchecked when off.
- `render_page()` emits `settings_fields( 'gatb_settings' )` (nonce) and `settings_errors( 'gatb_settings' )`.

`PluginTest` (extended): `boot()` registers `admin_init` → `Settings::register`, `admin_menu` →
`Admin::add_page` and `admin_init` → `Admin::add_fields` alongside the existing `init` hook;
`activate()` creates `gatb_settings` with the defaults and autoload `false`.

Test-critical zones of the profile (form pipelines, price grouping, REST, translate commands) are
not touched by this step; the plugin's own rule — every piece of pure logic is unit-tested — is what
applies, and `sanitize_settings` is that logic.

### Docs to update
- `docs/DATA-MODEL.md` — new section "Plugin `ga-telegram-bridge` (`wp_options`)" before `## Relations`:
  the `gatb_settings` key/type/default/rule table above, autoload `no`, the two constant overrides,
  and a line that `gatb_state`, `gatb_log` and the token transient arrive in Step 8 (committed with task 1).
- `wp-content/plugins/ga-telegram-bridge/readme.txt` — the Description's closing line
  ("Configuration, scheduling and the run log are added in the following releases") is replaced by a
  paragraph describing the settings screen: where it lives, what is entered, and that the two secrets
  may instead be defined in `wp-config.php`. No version bump and no changelog entry — the header is
  still `0.1.0` and release numbering is a Sprint 2 packaging question (DECISIONS "The check command
  runs on PHP 8.3+…" → Consequences).
- Not updated, checked: `FEATURE.md` → Data already lists these `gatb_settings` keys and points at
  DATA-MODEL; `ARCHITECTURE.md` gains no module, endpoint, flow or integration (the plugin row and
  both integration rows exist since Step 1); `DECISIONS.md` — the step reopens nothing;
  `TECH-STACK.md` — no dependency; `DOMAIN.md` — no new domain term (the settings are plumbing);
  `TESTING.md` — the fixtures rule is unchanged, this step records no GA payloads; `DESIGN.md` — see below.

### Checks
- **ANTI-PATTERNS:** none violated. The list is about the theme (ACF field groups, block directories,
  `service-city` term ids, CF7 ids, `assets/`, `mu-plugins/`, post-type files, per-city ids) and about
  tooling ("no global installs"); this step adds two PSR-4 classes inside the plugin and no dependency.
  The one item that could touch it — "never hardcode business values" (core rule 6) — is respected:
  every value the business may change (which blocks are sent, when, how many attempts) is an option,
  not a constant.
- **Docs vs reality:** match, with three observations that change no task here:
  1. `SPRINT-1.md` writes later methods in camelCase (`sendMessage`, `batchRunReports`,
     `getPropertyName`). WPCS as configured rejects that — verified: PHPCS reports
     `WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid` on a `sendMessage` probe. Since
     WPCS gates every commit (plugin `CLAUDE.md`), Steps 4–6 will write `send_message`,
     `batch_run_reports`, `get_property_name`; class names stay PascalCase. Resolution taken:
     the gate wins over the sprint's prose; no doc change, it is naming shorthand, not a contract.
  2. `FEATURE.md` → Data lists `gatb_state` as `last_report_date`, `attempt`, `next_run`, while
     DECISIONS "Plugin structure…" and `SPRINT-1.md` Step 8 list only the first two. Neither is
     written in this step (Step 8 / Sprint 2 owns `gatb_state`) — left for the step that creates it.
  3. Step 2's `spike/` and the service-account key are still on disk, untracked and excluded from
     PHPCS; removing them is a Definition-of-Done item at the sprint boundary, not a task here.
- **Design:** n/a — DECISIONS "No UI design phase; message format and configurable blocks": stock
  wp-admin components, no `docs/DESIGN.md` entry and no `design/` folder for this feature. The screen
  is named `Settings` as `FEATURE.md` → UI names it; this step builds its *unconfigured* and
  *secrets set in configuration* states, while the *check ok/error* states arrive with the buttons in
  Steps 4, 5, 7 and 8.
- **Check command:** `bin/check.sh` (docs/TECH-STACK.md → Check command) — exists and is green on
  `master` right now (PHPCS 5 files, PHPStan level 8 no errors, PHPUnit 3 tests/4 assertions,
  `php -l` over 108 theme files).
- **Not locally verifiable:** n/a. Everything this step builds is verifiable on the DDEV site:
  `wp-config.php` is gitignored here (`.gitignore:13`), so defining the two constants for the
  read-only check cannot be committed by accident, and it is removed again after the check.

### Questions / ambiguities
none
