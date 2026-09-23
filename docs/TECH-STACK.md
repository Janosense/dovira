# Tech stack — Dovira

## Stack
<!-- Adoption: versions below are read from the installed code (plugin headers,
     composer.lock, package-lock.json) on 2026-09-08 — verified, not pinned. -->
| Layer | Choice | Version | Why (link DECISIONS.md if non-obvious) |
|---|---|---|---|
| Platform | WordPress core (classic theme, no block theme) | 7.1 | client-editable site; the whole repo is the WP root |
| Language / runtime | PHP (DDEV image; production per host) | 8.3 (local) | theme uses PHP 8 syntax (`mixed`, union types, `str_contains`) |
| DB | MariaDB (DDEV) | 10.11 (local) | WP default; snapshot `mysql.sql` committed at repo root |
| Web server | nginx-fpm (DDEV) | — | — |
| Theme | `dovira` — custom classic theme from the Syndicode starter | 1.1.2026 | all the site's own code lives here (feature `core`) |
| Custom plugin | `ga-telegram-bridge` — standalone plugin in its own code area, zero runtime dependencies, own `CLAUDE.md` | 0.1.0 | daily GA4 → Telegram report; must run on any WP site — DECISIONS "A standalone plugin in a new code area" |
| Fields & blocks | ACF Pro + StoutLogic ACF Builder | 6.8.8 / 1.12.0 | field groups and 21 blocks defined in PHP, version-controlled (invariant 6) |
| i18n | Polylang | 3.8.4 | uk/ru content; ACF options per language via `inc/polylang.php` |
| Forms | Contact Form 7 | 6.1.6 | contact / franchise / vacancy forms → CPT records via hooks |
| SEO | Yoast SEO | 27.7 | meta + `wp_yoast_indexable`; one-off import tooling in `temp-data/` |
| Other plugins | Cyr2Lat 7.0.2 (Latin slugs), Simple Custom Post Order 2.7.2, Enable Media Replace 4.1.9, Akismet 5.7, Loco Translate 2.8.5 | — | — |
| PHP deps (theme `composer.json`) | vlucas/phpdotenv 5.6.3, anthropic-ai/sdk 0.25.0, guzzlehttp/guzzle 7.11.0; dev: roave/security-advisories | — | `.env` loading; CLI translation; `vendor/` is committed |
| Front-end build | Vite + PostCSS (preset-env stage 1, assets, prefix-selector, replace) + LightningCSS | 5.4.19 / 8.4.49 / 1.28.2 | `source/` → hashed `assets/` with manifest; `assets/` is committed |
| Front-end libs | Swiper 11.2.1, Fancybox (`@fancyapps/ui`) 5.0.36, iMask 7.6.1; vanilla ES modules, no framework | — | dynamic `import()` per module in `scripts/app.js` |
| Fonts | Google Fonts (Inter, Oswald, Raleway) + self-hosted Open Sans | — | — |
| Testing & QA | **Plugin:** PHPUnit + Brain\Monkey (unit tests, no WP bootstrap), PHPCS + WPCS, PHPStan + phpstan-wordpress, all dev-only in the plugin's `composer.json`, whose `vendor/` is gitignored. **Theme:** PHPUnit + Brain\Monkey only, in the separate dev-only Composer project `wp-content/themes/dovira/tests/`, whose `vendor/` is gitignored and whose lock is uncommitted, never in the theme's own `composer.json` | plugin 12.5.34 + 2.7.0 / 3.13.6 + 3.4.1 / 2.2.13 + 2.0.4; theme 12.5.35 + 2.7.0 | DECISIONS "Testing tooling and the project check command", "The theme gets PHPUnit + Brain\Monkey, run by the check command", "The theme's test tooling is its own Composer project in `tests/`"; no PHPCS/PHPStan for the theme and no JS tests exist |
| Local env | DDEV | — | `.ddev/config.yaml`; `wp-config.php` DDEV-generated |
| CI/CD | GitHub Actions → FTP (dev only) | FTP-Deploy-Action 4.3.5 | production deploys are manual (ARCHITECTURE.md → Environments) |
| CLI | WP-CLI (`wp dovira …` commands in `inc/cli/`) | — | translation and bundle transfer |

## Check command
`bin/check.sh` (repo root, committed and executable) is the gate every commit
passes. It runs five stages in order and stops at the first failure:
1. PHPCS (WordPress Coding Standards), inside
   `wp-content/plugins/ga-telegram-bridge/`.
2. PHPStan (level 8, analysing against PHP 8.1), same place.
3. PHPUnit, same place.
4. `php -l` over every theme PHP file outside `vendor/` and `node_modules/`
   (111 files today; the theme's `tests/*.php` are included and its
   `tests/vendor/` is not).
5. PHPUnit in the theme's `tests/` (DECISIONS "The theme gets PHPUnit +
   Brain\Monkey, run by the check command").

It refuses to start below PHP 8.3. It runs `composer install` in the plugin
when the plugin's `vendor/` is missing, and in `wp-content/themes/dovira/tests/`
when `tests/vendor/` is missing. The theme's own committed `vendor/` is never
installed or touched by the gate (DECISIONS "The theme's test tooling is its
own Composer project in `tests/`"). Both suites set `failOnRisky`, so a test
that asserts nothing fails the gate.

PHPStan needs two settings of its own, both learned from the gate
crashing on code that has no errors: it is capped at **two parallel workers** in
`phpstan.neon.dist`, because its pool otherwise scales with the CPU count and
every worker loads the WordPress stubs (on a 14-core machine that alone
exhausted the default), and it is run with **`--memory-limit=1G`**, because the
stubs push the analysis to ~730M peak — measured, and the same with or without
`tests/` in the paths, so the cost is the stubs and not the plugin's own files.
The limit is a CLI flag in `bin/check.sh` and in the plugin's `composer analyse`
script; PHPStan has no memory setting in its config file. The host PHP (8.5) and the DDEV web container (8.3) both qualify
— `ddev exec bash bin/check.sh` works. The theme's `npm run build` is NOT part
of the gate (`assets/` are committed; rebuild only when the front end
changes) — DECISIONS "Testing tooling and the project check command".

## ANTI-PATTERNS (mandatory reading before writing code)
<!-- Only code that steps keep writing (queries, migrations, tests,
     components, UI strings, calls to integrations) where the default on
     this stack is one way and this project requires another. One line
     each: "Do not X — Y", plus the DECISIONS entry behind it. -->
- Do not create or edit ACF field groups in the admin UI — every group is a
  PHP `FieldsBuilder` in `inc/acf/`; the UI would create a DB copy that the
  code cannot see and that a deploy cannot carry.
- Do not add a block outside `inc/acf/blocks/{name}/` with the trio
  `block.json` + `fields.php` + `template.php` — the directory scan in
  `inc/acf.php` is the only registration; also add the block name to
  `dovira_allowed_block_types()` or editors will not see it.
- Do not make `service-city` Polylang-translatable and do not rename its terms
  by id — Service price field names (`price_city_{id}`, `city_{id}`) and stored
  postmeta derive from term ids. Translate names as Polylang strings.
- Do not send the Telegram notification before `wp_insert_post` succeeds, and
  do not let a Telegram failure change the HTTP/mail result — the record is
  the source of truth, the notification is best-effort.
- Do not decide "which city site is this" anywhere except from the site URL
  (`site-header.php`) — there is no option, constant or env flag for it.
- Do not hand-edit `assets/` or `assets/.vite/manifest.json` — run
  `npm run build`; the manifest resolver `starter_theme_vite_asset()` maps
  `source/*` entries to hashed files.
- Do not enqueue front-end assets by hand-written paths — go through
  `starter_theme_vite_asset()`; in development the Vite dev server serves them.
- Do not put runtime-loaded code into `temp-data/`, `reports/` or `_to_delete/` —
  they are ops artifacts; the theme never requires them.
- Do not register a post type / taxonomy outside `inc/post-types/*.php` /
  `inc/taxonomies/*.php` — they are glob-loaded; an inline `register_post_type`
  in `functions.php` breaks the one-file-per-type convention.
- Do not use `wp-content/mu-plugins/` — it is gitignored (starter-theme legacy)
  and would not deploy.
- Do not put per-city analytics ids or any Kyiv-only change on `master` —
  `kyiv` carries them and only ever merges `master` in.
- Do not add new hardcoded CF7 form ids without recording them in
  ARCHITECTURE.md → Integrations; the three existing ids (6, 1430, 1399) are
  environment-specific already.
- Do not add a check/lint/test tool as a global install — it must be a
  committed script or `composer.json`/`package.json` dev dependency.
- Do not add a dev or tooling package to the theme's own `composer.json`.
  - The theme's `vendor/` is committed and `functions.php` loads its autoloader
    on every request.
  - A dev package's autoload `files` entry would therefore either ship the
    tool to every server, or take every page down when the entry is committed
    without the package.
  - The theme's test tooling lives in `tests/composer.json` (DECISIONS "The
    theme's test tooling is its own Composer project in `tests/`").
- Do not record a search server-side in `search.php`. The browser module
  (`source/scripts/features/search-stats/record.js`) is the one recorder: a
  results page fetched by a crawler or a prefetcher runs no JavaScript and so
  leaves no row, which a PHP recorder could only approximate with a user-agent
  list (DECISIONS "Every search is recorded from the browser through one
  public REST route").
- Do not escape a value for a Telegram HTML message with `esc_html()`.
  - WordPress keeps an entity it recognises (`&nbsp;`, `&laquo;`).
  - Telegram refuses every named entity except `&lt;`, `&gt;`, `&amp;` and
    `&quot;`, so one such value makes the whole message unsendable.
  - Escape with double encoding,
    `htmlspecialchars( $v, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8', true )`,
    and decode a stored WordPress title first (DECISIONS "Values in a
    Telegram message are escaped with double encoding, never with
    `esc_html()`").
  - Brain\Monkey's `esc_html` stub double-encodes, so a unit test cannot see
    the difference.

## CONVENTIONS (mandatory reading before writing code)
<!-- Project-wide rules for code that later steps must follow and would
     otherwise write differently, where the stack has no default or leaves
     the choice open (a stack default that leads the other way → ANTI-PATTERNS).
     One line each, present tense: the rule — why, in a few words — and
     where it was set (`DECISIONS {date} — {title}`, or
     `{feature} Sprint {N} Step {M}`). The line is the record: a rule no
     alternatives were weighed for needs no DECISIONS entry. Current state,
     not a log: a line that stops being true is edited or removed.
     Not here: a one-time choice, a detail of one step or screen, a
     data-model or domain rule, scope — they live in their own docs; a rule
     of one code area → that area's CLAUDE.md → Area conventions.
     `none yet` while there are none. -->
none yet

## Dependency policy
New dependencies (runtime AND dev/tooling) only after explicit user approval —
see CLAUDE.md core rule 1. Record approved additions here with one line of
justification.

## Approved dependencies log
| Date | Package | Why |
|---|---|---|
| (pre-adoption) | everything in the Stack table | inherited as-is at adoption, 2026-09-08 |
| 2026-09-08 | phpunit/phpunit `^12.5` (12.5.34) | test runner for the plugin. Not `^13`: it requires PHP ≥ 8.4.1 and would not run in DDEV (PHP 8.3) |
| 2026-09-08 | brain/monkey `^2.7` (2.7.0) | stubs WordPress functions so unit tests need no WP bootstrap and no database |
| 2026-09-08 | squizlabs/php_codesniffer `^3.13` (3.13.6) | linter. Not `^4`: WPCS 3.4.1 requires PHPCS `^3.13.5` |
| 2026-09-08 | wp-coding-standards/wpcs `^3.4` (3.4.1) | WordPress Coding Standards ruleset for the plugin |
| 2026-09-08 | dealerdirect/phpcodesniffer-composer-installer `^1.2` (1.2.1) | registers the WPCS standard with PHPCS on install |
| 2026-09-08 | phpstan/phpstan `^2.2` (2.2.13) | static analysis — the plugin runs on unknown hosts |
| 2026-09-08 | szepeviktor/phpstan-wordpress `^2.0` (2.0.4) | WordPress stubs for PHPStan (pulls php-stubs/wordpress-stubs 7.1.0, matching the installed core) |
| 2026-09-22 | phpunit/phpunit `^12.5` (12.5.35) — **theme** | test runner for the theme's unit suite; same pin and reason as the plugin's row above |
| 2026-09-22 | brain/monkey `^2.7` (2.7.0) — **theme** | stubs WordPress functions for the theme's suite (pulls mockery 1.6.15, antecedent/patchwork 2.2.3, hamcrest v3.0.0) |

The first seven are **dev-only**, live in `wp-content/plugins/ga-telegram-bridge/composer.json`,
and never reach a server: the plugin's `vendor/` is gitignored and it has zero runtime
dependencies. Approved in the Sprint 1 Step 1 plan.

The two **theme** rows are dev-only too. They live in
`wp-content/themes/dovira/tests/composer.json` with `config.platform.php = 8.3`,
**not** in the theme's own `composer.json`, whose `vendor/` is committed.
- `tests/vendor/` is gitignored and never deployed, and `tests/composer.lock`
  is not committed.
- The versions above are what `composer install` locked on 2026-09-23 (29
  packages).
- Approved in DECISIONS "The theme gets PHPUnit + Brain\Monkey, run by the
  check command". Their place is set by DECISIONS "The theme's test tooling is
  its own Composer project in `tests/`".
