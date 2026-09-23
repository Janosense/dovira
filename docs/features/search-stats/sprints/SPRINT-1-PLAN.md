# Step plans — search-stats, Sprint 1

<!-- Written by /plan-step, one section per step. Approval of a section
     authorizes that step only. -->

## Plan — Sprint 1, Step 1: Delta-audit and the theme's test suite in the gate   (status: closed)

### Branch
`search-stats/sprint-1-recording` ← `master`
(root `CLAUDE.md` → git model: simple, task branch → `master`. The name is the
**Branch** line of `SPRINT-1.md`. As in the ga-telegram-bridge sprints, the branch
is recreated from `master` for each step and deleted at the close.)

### Delta-audit, read while planning (task 1 re-reads and reports it)
- **`core` (`docs/features/core/FEATURE.md`).** Its invariant "`assets/` and
  `vendor/` are committed; a deploy is a file sync" decides where this step's
  tooling can go. The theme's `vendor/` is 1 904 tracked files, and
  `functions.php:8` loads `vendor/autoload.php` on every request. That is
  Question 1 below. No `core` data, hook or REST route is touched by Step 1.
- **`ga-telegram-bridge` (its `FEATURE.md`).** Installed 0.2.0. The public
  surface is `gatb_report_data` and `gatb_message_html`. `gatb_extra_blocks`
  does not exist yet (grep of `src/` is empty); it is Sprint 2 work. Step 1
  touches nothing in the plugin, and gate stages 1–3 (its PHPCS, PHPStan and
  PHPUnit) stay as they are.
- **Shared theme code the later steps touch.** All of it exists where the docs
  say:
  - `functions.php` has no `inc/features/` line yet (Step 2).
  - `inc/rest-api.php` requires two controllers by path and registers them in
    one `rest_api_init` closure (Step 3 adds the third).
  - `search.php` builds the three result sets DECISIONS names: fuzzy price
    rows `$sub_services`, core `$posts`, and `$posts_by_meta` on
    `key_words`/`key_words_services`, merged into `$search_posts` (Step 4).
  - `services-search.js` hooks `#services-search-input`
    (`inc/acf/blocks/services/template.php:79`).
  - `init-service-price-lists.js` hooks `#service-search-input`
    (`single-service.php:60`), and only when the price toggles and lists
    match in count.
  - `app.js` has 13 dynamic imports.

  No conflict with the sprint. None of these files changes in Step 1.
- **Outside this sprint.** `search.php:78` prints `get_query_var( 's' )`
  unescaped. Locally, `?s=<b>probe</b>` renders as bold markup: a reflected
  XSS in `core`. It belongs to `/adhoc`. Step 4 edits `search.php`, but the
  step protocol keeps this fix out of that step.

**Confirmed in `/do-step` (task 1), 2026-09-23.** Everything above still holds.
Touchpoints for Steps 2–4 (file:line, change, consuming feature):

| Step | Touchpoint | Change | Consumers |
|---|---|---|---|
| 2 | `functions.php:56–60` | one `require_once TEMPLATE_DIR . '/inc/features/search-stats/bootstrap.php';` after the Polylang line and before the `WP_CLI` guard at `:61`, so the feature loads on web and CLI requests alike | every theme feature (`core`, `search-stats`) |
| 3 | `inc/rest-api.php:9–15` | a third `require_once` plus `register_routes()` inside the existing `rest_api_init` closure | `core`'s Telegram and Questionary routes, registered in the same closure |
| 4 | `search.php:20–72` | result sets: `$sub_services` (fuzzy price rows, `:22–38`), global `$posts` (core query, `:21`), `$posts_by_meta` (`:43`), merged into `$search_posts` by type (`:61–72`) | `core` (the header search) |
| 4 | `search.php:77–78` | the heading printing the query, where the data attributes go on the results section | `core` |
| 4 | `inc/acf/blocks/services/template.php:79` | `#services-search-input`, printed only when `$is_search_visible`; needs `data-search-stats-context` | `core` (the `services` block) |
| 4 | `single-service.php:60` | `#service-search-input`; needs `data-search-stats-context` | `core` (service pages) |
| 4 | `source/scripts/modules/services-search.js:7` | the `input` listener that calls the recorder | `core` |
| 4 | `source/scripts/modules/init-service-price-lists.js:10` | the `input` listener inside `serviceSearch()` | `core` |
| 4 | `source/scripts/app.js:1–29` | one more dynamic import and one call | every front-end module |

One Step 4 fact that doesn't change Step 1:
- `serviceSearch()` is wired only when the price toggles and price lists
  match in count and both are non-empty (`init-service-price-lists.js`). The
  recorder is therefore reached only on the pages where the filter itself
  works.
- `search.php` does not print everything it collects. Of `$search_posts` it
  shows `service` with `post_parent === 0`, `post`, `page` and `employee`
  only. "How many results the page showed" (SPRINT-1 Goal) is that shown
  subset plus `$sub_services`, not the raw union of the three sets.
- Step 4's `ResultsCountTest` should pin this.

### Tasks (ordered)
Written for the recommended answer to Question 1 (B). Under answer A the tasks
change as that question describes.

- [x] **1. Delta-audit.** Re-read the two `FEATURE.md` files and the shared code
  listed above. Confirm the audit above still holds. List the touchpoints of
  Steps 2–4 (file, line, what changes, which feature consumes it) in the step
  report. No code change.
  → no commit (findings go in the step report)

- [x] **2. The theme's test suite as its own dev-only Composer project in `tests/`.**
  Create the five files under *Files to create/change*, then add
  `/tests/vendor/` and `.phpunit.cache/` to the theme `.gitignore`.
  `tests/composer.lock` is already ignored by that file's bare `composer.lock`
  (checked with `git check-ignore`). Then:
  - run `cd wp-content/themes/dovira/tests && composer install && composer test`,
    expecting `OK (1 test, 1 assertion)`;
  - run `bin/check.sh`, which should still be green on its four stages, with
    `php -l` now linting 111 files (108 + the three new PHP files; `tests/vendor/`
    is excluded by the existing `*/vendor/*` filter);
  - `git status` must show nothing under `wp-content/themes/dovira/vendor/`.

  Docs in the same commit (core rule 5):
  - `docs/TESTING.md` → header comment, Levels, How to run (theme suite),
    Fixtures, Never mocked, Rules.
  - `docs/TECH-STACK.md` → Stack row "Testing & QA", Approved dependencies log
    (two theme rows with the versions `composer install` actually locked), and
    one ANTI-PATTERNS line.
  - The theme `CLAUDE.md` → Local commands (`composer test`), the never-commit
    list (`tests/vendor/`), and where dev tooling goes. The file stays at 60
    lines or fewer; it is 58 now.
  - `docs/DECISIONS.md` → a new entry that amends the 2026-09-22 clause (see
    Question 1).

  **Touches shared code — may affect other features:** the theme `.gitignore`
  and a new theme-wide `tests/` directory. Consumers: `search-stats` Steps 2–4
  write their tests here, and so will any later theme feature. `core` has no
  runtime consumer, because nothing under `tests/` is loaded by WordPress.
  → `chore(theme): add a PHPUnit + Brain\Monkey test suite under tests/`

- [x] **3. The gate's fifth stage.** Changes to `bin/check.sh`:
  - The header comment gains `5. PHPUnit (unit tests) in the dovira theme's tests/`.
  - A `THEME_TESTS="${THEME}/tests"` variable.
  - After the plugin's install block:
    `if [ ! -d "${THEME_TESTS}/vendor" ]` → `echo "==> composer install (theme dovira tests)"`
    and `( cd "${THEME_TESTS}" && composer install --no-interaction --no-progress )`.
    This is the plugin's install-when-missing, applied to the directory that
    can actually be missing.
  - After `php -l`: `echo "==> PHPUnit (theme dovira)"` and
    `( cd "${THEME_TESTS}" && vendor/bin/phpunit )`, then the existing
    `==> check: all green`.

  Run the gate twice: on the host (PHP 8.5.4), and via
  `ddev exec bash bin/check.sh` (PHP 8.3). TECH-STACK claims both work, and the
  new stage must keep that true. `config.platform.php = "8.3"` in
  `tests/composer.json` is what makes a host-resolved `tests/vendor/` load in
  the container.

  Docs in the same commit:
  - `docs/TECH-STACK.md` → Check command (five stages, and the install in
    `tests/`).
  - `docs/TESTING.md` → How to run (the gate line).
  - Root `CLAUDE.md` → Commands (the `bin/check.sh` comment).
  - `docs/ARCHITECTURE.md` → Modules, row "Project gate".

  **Touches shared code — may affect other features:** `bin/check.sh` gates
  every commit of `core`, `ga-telegram-bridge` and `search-stats`.
  → `chore: run the theme's PHPUnit suite as the gate's fifth stage`

After each commit, read `git show --stat HEAD` against this file list
(LEARNINGS 2026-09-08).

### Files to create/change
**New: `wp-content/themes/dovira/tests/`**
- `composer.json` has no `require` at all. It contains:
  - `require-dev`: `brain/monkey ^2.7`, `phpunit/phpunit ^12.5`;
  - `autoload-dev` PSR-4 `dovira\\Tests\\` → `./` (the theme's `dovira\`
    namespace convention);
  - `config`: `platform.php` `8.3` and `sort-packages`;
  - `scripts`: `"test": "phpunit"`.
- `phpunit.xml.dist` mirrors the plugin's:
  - schema `vendor/phpunit/phpunit/phpunit.xsd`, `bootstrap="bootstrap.php"`,
    `colors`;
  - `failOnWarning`, `failOnNotice`, `failOnDeprecation` and **`failOnRisky`**
    all `true`;
  - `cacheDirectory=".phpunit.cache"`;
  - one suite `Unit` → directory `Unit`.
- `bootstrap.php` does `declare( strict_types=1 )` and
  `require_once __DIR__ . '/vendor/autoload.php'`. That autoloader also loads
  Brain\Monkey's API. No WordPress is loaded and no constants are defined:
  nothing needs one yet, and Step 2 adds what its classes need.
- `TestCase.php`: `dovira\Tests\TestCase`, abstract, with `Monkey\setUp()` in
  `setUp()` and `Monkey\tearDown()` in `tearDown()`, the same as the plugin's.
- `Unit/BootstrapTest.php`: the smoke test (see *Tests to write*).

**Changed**
- `wp-content/themes/dovira/.gitignore` (task 2)
- `bin/check.sh` (task 3)
- `docs/TESTING.md` (tasks 2, 3), `docs/TECH-STACK.md` (tasks 2, 3),
  `wp-content/themes/dovira/CLAUDE.md` (task 2), `docs/DECISIONS.md` (task 2)
- `CLAUDE.md` (root), `docs/ARCHITECTURE.md` (task 3)

**Checked and not changed**
- The theme's `composer.json` and `vendor/`. That is the point of Question 1,
  answer B.
- The root `.gitignore`. Its `wp-content/themes/dovira/composer.lock` covers
  only the theme root; the theme's own `.gitignore` covers `tests/composer.lock`.
- The plugin's `phpcs.xml.dist`, `phpstan.neon.dist` and `phpunit.xml.dist`
  scan the plugin only, so they have nothing to learn.
- The `php -l` stage's `find` needs no change: it already lints `tests/*.php`
  and skips `tests/vendor/`.
- `vite.config.js` and `assets/`: no front-end change, so no `npm run build`.

### Dependencies (approved: DECISIONS 2026-09-22, "The theme gets PHPUnit + Brain\Monkey")
The versions were resolved in the scratchpad with `composer update --dry-run`
on 2026-09-23 (`config.platform.php = 8.3`, host PHP 8.5.4, Composer 2.9.5),
using exactly the `tests/composer.json` above. That is 29 packages in all.

| Package | Constraint | Resolves to |
|---|---|---|
| phpunit/phpunit | `^12.5` | 12.5.35. The newest overall is 13.3.4, which requires PHP ≥ 8.4.1 and would not run in DDEV; the reason is already in TECH-STACK |
| brain/monkey | `^2.7` | 2.7.0, pulling in mockery 1.6.15, antecedent/patchwork 2.2.3 and hamcrest v3.0.0 |

The lock is not committed (DECISIONS 2026-09-22 → Consequences), so TECH-STACK
records whatever task 2's `composer install` actually locks.

### Tests to write
- `BootstrapTest::test_a_wordpress_function_can_be_stubbed`:
  `Functions\when( 'get_site_url' )->justReturn( 'https://dovira.ddev.site' )`,
  then `assertSame( 'https://dovira.ddev.site', get_site_url() )`. No WordPress
  is loaded, so the function exists only because Brain\Monkey stubbed it.
  - The test uses `when()`, not `expect()`: a Brain\Monkey expectation is not a
    PHPUnit assertion (LEARNINGS 2026-09-10). Deleting the `assertSame` must
    therefore leave a test with no assertions, which `failOnRisky` turns into a
    failed gate. That is the step's second manual check.
  - Task 3 tries it once and does not commit it: remove the assertion, see
    `bin/check.sh` exit non-zero with "This test did not perform any
    assertions", then restore the assertion.
- The gate: `bin/check.sh` exits 0 and prints five stages. With
  `tests/vendor/` absent it first prints `==> composer install (theme dovira tests)`.

### Docs to update
- `docs/TECH-STACK.md`
  - Check command: five stages, and the install in the theme's `tests/`.
  - Stack row "Testing & QA (plugin only)" becomes "Testing & QA". Its
    wording "the theme has no tests" is no longer true.
  - Approved dependencies log: two theme rows, with a note under the table
    saying where they live and that `tests/vendor/` is gitignored and never
    deployed.
  - ANTI-PATTERNS gains: *do not add packages to the theme's `composer.json`
    for tooling. Its `vendor/` is committed and loaded on every request, so
    theme dev tools go in `tests/composer.json`.*
- `docs/TESTING.md`
  - Header comment and Levels: a theme unit suite now exists at
    `wp-content/themes/dovira/tests/Unit/`.
  - How to run: the five-stage gate, and `cd wp-content/themes/dovira/tests &&
    composer install && composer test`. `failOnRisky` applies to both suites.
  - Fixtures: the theme's fixtures go in its `tests/fixtures/` (none yet),
    under the same recorded-vs-written rules.
  - Never mocked: the same rules as the plugin's; only WordPress globals and
    `$wpdb` are stubbed.
  - Rules: SQL is tested by asserting the query the code builds and by
    shaping fixed `$wpdb` results, never against a database (DECISIONS
    2026-09-22).
- The theme `CLAUDE.md` → Local commands (`composer test` from `tests/`),
  `tests/vendor/` in the never-commit list, and one clause: `vendor/` **is**
  committed, so dev tooling goes to `tests/composer.json`.
- `docs/DECISIONS.md`: a new entry, "The theme's test tooling is its own
  Composer project in `tests/`, because the theme's `vendor/` is committed".
  - Context: the facts under Question 1.
  - Decision: answer B.
  - Alternatives rejected: answer A, and gitignoring the theme's `vendor/`
    (the deploy is a file sync with no Composer on the server).
  - Consequences: it supersedes only the "as `require-dev` of the theme's
    `composer.json`" clause of the 2026-09-22 entry, and the rest of that
    entry stands. DECISIONS is append-only, so the old entry is not edited.
- These four describe the gate this step changes but are not named by the step
  (core rule 5 decides it):
  - Root `CLAUDE.md` → Commands, the `bin/check.sh` comment.
  - `docs/ARCHITECTURE.md` → Modules, row "Project gate".
  - The TECH-STACK Stack row above.
  - The TESTING header and Levels above.

### Checks
- **ANTI-PATTERNS: none violated.**
  - The test tools are dev dependencies in a committed `composer.json`, not a
    global install.
  - No change to `assets/`, ACF, post types, `mu-plugins/`, `temp-data/`,
    per-city code or CF7 ids.
  - The smoke test's stub URL is a test value, not a city decision.
- **Docs vs reality: mismatch, five items.**
  1. SPRINT-1 Step 1 says "the theme's `.gitignore` keeps `vendor/` … out, per
     its `CLAUDE.md`". It does not, and it must not:
     - `vendor/` is committed (1 904 files) and is what the file-sync deploy
       ships (ARCHITECTURE → Environments & deploy, the `core` invariant,
       TECH-STACK Stack row);
     - the theme `CLAUDE.md` lists `composer.lock`, never `vendor/`.

     Resolution: the runtime `vendor/` stays committed, and only the tooling's
     `tests/vendor/` is ignored (Question 1).
  2. The step says "running `composer install` in the theme when `vendor/` is
     missing, exactly as it does for the plugin". The theme's `vendor/` is
     never missing from a checkout. Resolution: the check is against
     `tests/vendor/` (Question 1).
  3. The step marks the theme `composer.json` "consumers: none at runtime, dev
     tooling only". That is not true of this theme:
     - the committed `vendor/composer/installed.json` says `"dev": true`, so
       `vendor/` is built by a plain `composer install`;
     - with PHPUnit + Brain\Monkey in `require-dev`, that command writes five
       dev-only `files` autoload entries into the tracked autoloader. The
       plugin's install shows which: mockery `helpers.php` and `Mockery.php`,
       deep-copy `deep_copy.php`, brain/monkey `inc/api.php`, phpunit
       `Framework/Assert/Functions.php`;
     - `functions.php:8` requires that autoloader on every request.

     Resolution: Question 1.
  4. Four docs describe the gate but are missing from the step's Docs list:
     root `CLAUDE.md` Commands, the ARCHITECTURE "Project gate" row, the
     TECH-STACK "Testing & QA (plugin only)" row and the TESTING header and
     Levels. Resolution: updated in the same commits (core rule 5).
  5. Outside this step: the reflected XSS at `search.php:78` (see the audit
     above). Resolution: `/adhoc`, not this sprint.
- **Design:** n/a. No screen.
- **Check command:** `bin/check.sh` (docs/TECH-STACK.md → Check command) exists
  and was green on 2026-09-23 at plan time: plugin `OK (297 tests, 1069
  assertions)`, 108 theme files linted, working tree clean afterwards.
- **Not locally verifiable:** that the hand deploy leaves
  `wp-content/themes/dovira/tests/vendor/` behind, just as it must leave the
  plugin's dev `vendor/` behind (ARCHITECTURE → Modules). This is first proven
  at the sprint-boundary deploy, by listing `wp-content/themes/dovira/tests/`
  on each server. The `dev` FTP action syncs the git checkout and never sees
  it. The committed `tests/*.php` files do reach the servers, as the plugin's
  `tests/` already do.

### Questions / ambiguities
**1. Where are the theme's test tools installed, given that the theme's
`vendor/` is committed and loaded on every request?** DECISIONS 2026-09-22 and
the step say "`require-dev` of the theme's `composer.json`". The facts below
were measured while planning.

- **A: as written, in the theme's `composer.json`.**
  - Today a plain `composer install` there fails against the local lock:
    "Required (in require-dev) package "phpunit/phpunit" is not present in the
    lock file".
  - Once the lock is updated, the same command installs 29 dev packages into
    the committed `vendor/` and adds the five `files` entries above to the
    tracked autoloader. What happens next depends on what gets committed:
    - Autoloader committed without the packages: every page on
      `dev.dovira.vet` and on both productions fatals.
    - Both committed: PHPUnit ships to all three servers.
    - Neither committed: `vendor/` is dirty for good, and a working-copy sync
      uploads it anyway.
  - On a clean checkout, which has no lock because the lock is uncommitted,
    the gate's `composer install` would also re-resolve and upgrade nine
    committed runtime packages. For example, guzzle goes 7.11.0 → 7.15.5 and
    phpdotenv 5.6.3 → 5.7.0 (host dry run, 2026-09-23).
  - Under A the plan would need a guard the step does not name:
    `--no-dev` in every documented theme `composer install`, and the gate
    installing into a separate `COMPOSER_VENDOR_DIR` while still sharing the
    uncommitted lock. That would come back as a new question, and a single
    forgotten `--no-dev` followed by a commit takes the sites down.
- **B: a separate dev-only Composer project in `tests/`** (the tasks above).
  - `tests/composer.json` holds the same two packages at the same constraints,
    pinned to platform PHP 8.3.
  - `tests/vendor/` is gitignored and its lock uncommitted.
  - The theme's `composer.json` and `vendor/` are untouched.
  - Everything else the step and the 2026-09-22 entry ask for stays: the
    packages, `failOnRisky`, `tests/Unit/`, the fifth stage after `php -l`,
    install-when-missing, no PHPCS/PHPStan for the theme.
  - Cost: one new DECISIONS entry amending one clause, and one ANTI-PATTERNS
    line.

**Recommendation: B.** It is the only answer where running a normal
`composer install` in the theme cannot change what production runs.

**Resolved: approved as recommended — B.** The theme's test tooling is a
separate dev-only Composer project in `wp-content/themes/dovira/tests/`. The
theme's own `composer.json` and `vendor/` are not touched. (`/do-step`, 2026-09-23.)

## Plan — Sprint 1, Step 2: Feature bootstrap, the table and the purge   (status: closed)

### Branch
`search-stats/sprint-1-recording` ← `master`
(root `CLAUDE.md` → git model: simple, task branch → `master`. The name is the
**Branch** line of `SPRINT-1.md`. The branch is recreated from `master`, which
now carries Step 1, and deleted at the close.)

### Tasks (ordered)
- [x] **1. The feature's bootstrap and the table.**
  - `inc/features/search-stats/bootstrap.php` requires `Schema.php` and
    registers two hooks:
    - `add_action( 'after_switch_theme', [ Schema::class, 'install' ] )`;
    - `add_action( 'init', [ Schema::class, 'maybe_upgrade' ] )`.
  - `functions.php` gains one line, `require_once TEMPLATE_DIR .
    '/inc/features/search-stats/bootstrap.php';`, under a docblock like the
    others. It goes after the Polylang require (`:56`) and before the `WP_CLI`
    guard (`:61`), so the feature loads on web, REST, cron and CLI requests
    alike (Step 1 audit, touchpoint 1).
  - `inc/features/search-stats/Schema.php` holds `dovira\SearchStats\Schema`
    (a `final` class, following the theme's `dovira\` / `dovira\CLI`
    convention; no `declare(strict_types)`, like the rest of the theme's
    classes):
    - `VERSION = 1`, `OPTION = 'dovira_search_stats_db_version'`,
      `TABLE = 'dovira_search_queries'`;
    - `table_name()` returns `$wpdb->prefix . TABLE`;
    - `create_table_sql()` returns the `CREATE TABLE` shown under *Files*,
      written to dbDelta's rules: one column per line, two spaces after
      `PRIMARY KEY`, `KEY` rather than `INDEX`, lowercase types, no
      backticks, and `$wpdb->get_charset_collate()` at the end;
    - `install()` does `require_once ABSPATH . 'wp-admin/includes/upgrade.php'`
      only when `dbDelta` is not defined yet (this is the front end, and
      `upgrade.php` is an admin include). It then calls
      `dbDelta( create_table_sql() )`, and only when `$wpdb->last_error === ''`
      calls `update_option( OPTION, VERSION, true )`. The option is autoloaded
      because `init` reads it on every request;
    - `maybe_upgrade()` calls `install()` only when `(int) get_option( OPTION, 0 ) < VERSION`.
      Every other request costs one read of an autoloaded option, and dbDelta
      never runs on every request.
  - `tests/WpdbDouble.php` is a `dovira\Tests\WpdbDouble` test double for
    `$wpdb`:
    - properties `prefix` and `last_error`;
    - `get_charset_collate()` returns a fixed collate string;
    - `prepare()` records the query and its arguments and returns the
      interpolated string;
    - `query()` records the SQL.

    PSR-4 `dovira\Tests\` → `tests/` from Step 1 autoloads it, so no
    `composer dump-autoload` is needed.
  - `tests/Unit/SearchStats/SchemaTest.php`. The class under test is loaded
    with `require_once` by its path in the theme, not through Composer. The
    theme loads its classes by path, and a new PSR-4 entry in
    `tests/composer.json` would not reach an existing `tests/vendor/`: the gate
    installs only when that directory is missing, so every developer machine
    would need a manual `composer dump-autoload`.

  Docs in the same commit (core rule 5):
  - `docs/DATA-MODEL.md`:
    - Conventions: "No custom tables, no migrations mechanism" becomes one
      custom table, versioned through dbDelta;
    - a new section `## Feature search-stats` holding the table (columns,
      keys, UTC, ownership), the option, and the manual removal (`DROP TABLE`,
      `wp option delete`).
  - `docs/ARCHITECTURE.md` → Modules: a `search-stats` row. The Bootstrap row
    gains "and one `inc/features/{name}/bootstrap.php` per feature".
  - `docs/TESTING.md` → Rules: the code under test is required by path; the
    `$wpdb` double is `tests/WpdbDouble.php`.

  **Touches shared code — may affect other features:** `functions.php`, which
  every theme feature (`core`, `search-stats`) loads on every request.
  → `feat(search-stats): create the search queries table from a schema version`

- [x] **2. The purge.**
  - `inc/features/search-stats/Purge.php` holds `dovira\SearchStats\Purge`:
    - `HOOK = 'dovira_search_stats_purge'`, `FILTER = 'dovira_search_stats_retention_days'`,
      `DEFAULT_RETENTION_DAYS = 90`, `MIN_RETENTION_DAYS = 29`. The number 90
      appears once;
    - `retention_days()` returns
      `max( MIN_RETENTION_DAYS, (int) apply_filters( FILTER, DEFAULT_RETENTION_DAYS ) )`;
    - `schedule()` calls `wp_schedule_event( time(), 'daily', HOOK )` only
      when `wp_next_scheduled( HOOK )` is `false`;
    - `run( ?int $now = null )` executes
      `$wpdb->query( $wpdb->prepare( "DELETE FROM {table} WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', $now - retention_days() * DAY_IN_SECONDS ) ) )`,
      where `$now` defaults to `time()`. The optional clock is the plugin's
      convention; Brain\Monkey cannot stub `time()`.
  - `bootstrap.php` gains two hooks:
    - `add_action( 'init', [ Purge::class, 'schedule' ] )`;
    - `add_action( Purge::HOOK, [ Purge::class, 'run' ], 10, 0 )`.

    `accepted_args` 0 is deliberate. `do_action()` with no arguments passes
    `''` (`wp-includes/plugin.php:516`), which would reach `?int $now` as a
    TypeError. WP-Cron uses `do_action_ref_array( $hook, [] )`
    (`wp-cron.php:191`), and `WP_Hook` calls a 0-argument callback with none
    (`class-wp-hook.php:350`).
  - `tests/bootstrap.php` defines `DAY_IN_SECONDS` (86400, WordPress's own
    value) when it is not defined, because no WordPress is loaded in the
    tests. This is the one constant the feature's classes need; the Step 1
    bootstrap left room for it.
  - `tests/Unit/SearchStats/PurgeTest.php`.

  Docs in the same commit:
  - `docs/DATA-MODEL.md` → the feature section gains the cron hook, the filter
    (default 90, floor 29), "the purge is the only delete", and
    `wp cron event delete dovira_search_stats_purge` in the removal note.
  - `docs/ARCHITECTURE.md` → the feature's Modules row gains the purge.

  **Touches shared code — may affect other features:** `tests/bootstrap.php`,
  the theme test harness, which only `search-stats` consumes today.
  → `feat(search-stats): purge search rows older than the retention period`

### Files to create/change
**New: `wp-content/themes/dovira/inc/features/search-stats/`**
- `bootstrap.php` (task 1; task 2 adds the purge hooks)
- `Schema.php` (task 1). `create_table_sql()` for prefix `wp_`:
  ```sql
  CREATE TABLE wp_dovira_search_queries (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    level varchar(16) NOT NULL,
    query_text varchar(100) NOT NULL,
    context_id bigint(20) unsigned NOT NULL DEFAULT 0,
    results int(10) unsigned NULL,
    created_at datetime NOT NULL,
    PRIMARY KEY  (id),
    KEY level_created_at (level,created_at),
    KEY created_at (created_at)
  ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci
  ```
  The collate string is what the local install's `$wpdb->get_charset_collate()`
  returns (checked 2026-09-23). The integer display widths are the ones
  MariaDB 10.11's `DESCRIBE` prints, so a later dbDelta run finds nothing to
  alter. `level` and `query_text` are `NOT NULL`: the sprint gives their types,
  and the only writer (Step 3's route) always sets both.
- `Purge.php` (task 2)

**New: `wp-content/themes/dovira/tests/`**
- `WpdbDouble.php` (task 1)
- `Unit/SearchStats/SchemaTest.php` (task 1), `Unit/SearchStats/PurgeTest.php` (task 2)

**Changed**
- `wp-content/themes/dovira/functions.php` (task 1, shared)
- `wp-content/themes/dovira/tests/bootstrap.php` (task 2, shared test harness)
- `docs/DATA-MODEL.md`, `docs/ARCHITECTURE.md` (tasks 1 and 2), `docs/TESTING.md` (task 1)

**Checked and not changed**
- `tests/phpunit.xml.dist` scans `Unit/` recursively, so `Unit/SearchStats/`
  is already included.
- `tests/composer.json` needs no change, because the classes under test are
  required by path.
- The gate's `php -l` stage picks up the six new PHP files (111 → 117).
- The theme `CLAUDE.md` already states where a feature lives and that
  `functions.php` gets one line per feature.
- The feature's `FEATURE.md` → Data already names the table, the option, the
  hook and the filter.
- ARCHITECTURE → Feature map already has the row.

### Tests to write
The clock is pinned to `1790164800`, i.e. 2026-09-23 12:00:00 UTC. The cutoffs
below were computed from it with `gmdate()` while planning. `$wpdb` is a
`WpdbDouble` in `$GLOBALS['wpdb']`, set in `setUp()` and removed in
`tearDown()`. Every WordPress call a test cares about is recorded by a
`Functions\when()->alias()` and asserted, never left to an expectation alone
(TESTING.md → How to run).

`SchemaTest`
- `install()` hands `dbDelta` exactly the SQL above for prefix `wp_`, and the
  same SQL with prefix `wpk_` for a second prefix. That proves the table name
  comes from `$wpdb->prefix`, with every column and both keys, and nothing else.
- A successful run (`last_error` stays `''`) writes the option once:
  `update_option( 'dovira_search_stats_db_version', 1, true )`.
- A failed run (the `dbDelta` stub sets `last_error`) writes no option.
- `maybe_upgrade()` with the option absent (`false`) or behind (`'0'`), via a
  data provider, calls `dbDelta` once and writes the option.
- `maybe_upgrade()` with the option current (`'1'`) calls neither `dbDelta`
  nor `update_option`.

`PurgeTest`
- With no filter, `run( 1790164800 )` prepares
  `DELETE FROM wp_dovira_search_queries WHERE created_at < %s` with
  `['2026-06-25 12:00:00']` (90 days), and `query()` receives the prepared
  string. Both are asserted.
- The filter is applied once, with the default `90`.
- A filter returning `5` gives `2026-08-25 12:00:00` (clamped to 29), one
  returning `29` gives the same, and one returning `120` gives
  `2026-05-26 12:00:00` (a data provider).
- `schedule()` with `wp_next_scheduled()` returning `false` calls
  `wp_schedule_event` once with `(int, 'daily', 'dovira_search_stats_purge')`.
  With it returning a timestamp, it calls nothing.

`bin/check.sh` must exit 0 after each task: 297 plugin tests, 117 files linted
after task 2, and the theme suite at 1 + the new tests.

### Docs to update
- `docs/DATA-MODEL.md`
  - Conventions: the custom-table exception.
  - New section `## Feature search-stats` (custom table + `wp_options`):
    - the table: columns, types, nullability, keys, `created_at` in UTC,
      `results` `NULL` except for level `site`;
    - the option: autoloaded, written only after a successful dbDelta;
    - the cron hook and the filter;
    - ownership: no other feature writes to the table;
    - the manual removal: `DROP TABLE {prefix}dovira_search_queries`,
      `wp option delete dovira_search_stats_db_version` and
      `wp cron event delete dovira_search_stats_purge`. A theme has no
      uninstall.

  This is the step's own item. The Conventions line is core rule 5, because
  it becomes false.
- `docs/ARCHITECTURE.md` → Modules:
  - one row for `search-stats`: location, `Schema` and `Purge`, the hooks it
    registers; "must never": write another feature's data, delete from a
    public request;
  - the Bootstrap row's list of requires (rule 5).
- `docs/TESTING.md` → Rules: code under test is required by path; the `$wpdb`
  double. This follows from rule 5, because the step creates the convention.

### Checks
- **ANTI-PATTERNS:** none violated.
  - No post type, taxonomy or ACF group is registered.
  - Nothing goes in `mu-plugins/`, `temp-data/` or `assets/`, and there is no
    city branching.
  - No package is added to the theme's `composer.json`.
  - `functions.php` gets exactly one feature line (theme `CLAUDE.md` →
    Feature isolation).
- **Docs vs reality:** mismatch. Resolved and recorded:
  1. DATA-MODEL → Conventions says "No custom tables, no migrations
     mechanism". This step adds both. It is updated in task 1.
  2. The sprint's Verification runs `ddev wp db query "DESCRIBE …"` first, but
     `wp db` commands do not load WordPress, so `init` has not fired and the
     table does not exist yet on a fresh install. The verification guide first
     loads WordPress once (a page, or `ddev wp option get …`). No task changes.
  3. `after_switch_theme` fires only on a theme switch, from
     `check_theme_switched()` on `init` at priority 99. On the hand deploy
     the theme is already active, so the priority-10 `init` check is what
     creates the table on both productions. After a real switch, dbDelta runs
     twice in that request, and the second run is a no-op.
  4. If a production database user cannot `CREATE TABLE`, dbDelta leaves
     `last_error` set. The option is then not written and `init` retries
     dbDelta on every request until it succeeds. That is what "written after
     a successful run" means, so no task changes; it is listed below as not
     locally verifiable.
- **Design:** n/a. No screen.
- **Check command:** `bin/check.sh` (docs/TECH-STACK.md → Check command) was
  green on `master` after the Step 1 merge: 297 plugin tests, 111 files, 1
  theme test.
- **Not locally verifiable:** that the first request after the hand deploy
  creates `{prefix}dovira_search_queries` on each production install, writes
  `dovira_search_stats_db_version` = 1, and registers
  `dovira_search_stats_purge`. This also proves the database user may create
  tables. It is verified at the sprint-boundary deploy with
  `wp db query "DESCRIBE {prefix}dovira_search_queries"`, `wp option get
  dovira_search_stats_db_version` and `wp cron event list` over SSH on each
  install.

### Questions / ambiguities
none

## Plan — Sprint 1, Step 3: The REST route that records a search   (status: closed)

### Branch
`search-stats/sprint-1-recording` ← `master`
(root `CLAUDE.md` → git model: simple, task branch → `master`. The name is the
**Branch** line of `SPRINT-1.md`. The branch is recreated from `master`, which
now carries Steps 1 and 2, and deleted at the close.)

### Tasks (ordered)
- [x] **1. The normalizer.**
  - `inc/features/search-stats/Normalizer.php` holds
    `dovira\SearchStats\Normalizer`, a `final` class with one static method,
    like `Schema` and `Purge`:
    - `normalize( string $text ): string` does three things. It collapses
      every run of whitespace to one space with
      `preg_replace( '/\s+/u', ' ', $text )`, trims the ends, and lowercases
      with `mb_strtolower()`.
    - With `/u`, `\s` also matches Unicode whitespace such as the
      non-breaking space. This was checked on 2026-09-23 on PHP 8.3.32 in DDEV
      and PHP 8.5.4 on the host.
    - For invalid UTF-8, `preg_replace()` returns `null` and `normalize()`
      returns `''`, which the route refuses as empty. A string from
      `json_decode()` can never be invalid UTF-8, because `json_decode()`
      rejects it, and rejects a lone `\ud800` escape as well. So this branch
      only guards direct callers. It also keeps `trim( null )` from raising a
      deprecation.
    - It does nothing else: no `sanitize_text_field()` and no tag stripping.
      The DECISIONS rule is exactly lowercase, trim and collapse. The text
      reaches SQL only through `$wpdb->insert()`'s formats, and it is escaped
      where Sprint 2 prints it.
  - `bootstrap.php` requires `Normalizer.php` next to `Schema.php` and
    `Purge.php`. No hook is added.
  - `tests/Unit/SearchStats/NormalizerTest.php`.

  Docs in the same commit (core rule 5):
  - `docs/ARCHITECTURE.md`: the feature's Modules row gains `Normalizer`,
    the one place text is normalized.
  - `docs/TESTING.md` → Levels: the theme row drops "Today it holds only the
    bootstrap's smoke test", which has been false since Step 2.
  → `feat(search-stats): normalize search text in one place`

- [x] **2. The route: validation, the insert and the registration.**
  - `inc/features/search-stats/Repository.php` holds
    `dovira\SearchStats\Repository`:
    - `insert( string $level, string $query_text, int $context_id, ?int $results ): bool`
      makes one call,
      `$wpdb->insert( Schema::table_name(), [ 'level' => …, 'query_text' => …, 'context_id' => …, 'results' => …, 'created_at' => current_time( 'mysql', true ) ], [ '%s', '%s', '%d', '%d', '%s' ] )`,
      and returns `false !==` its result.
    - A `null` in `results` is written as SQL `NULL`, whatever its format:
      `wpdb::_insert_replace_helper()` turns a null value into `NULL`
      (`wp-includes/class-wpdb.php:2601–2603`).
  - `inc/features/search-stats/RecordController.php` holds
    `dovira\SearchStats\RecordController extends \WP_REST_Controller`, built
    like `QuestionaryController`:
    - The constructor sets `namespace` to `dovira/v1` and `rest_base` to
      `search-stats`.
    - `register_routes()` calls
      `register_rest_route( 'dovira/v1', '/search-stats/record', [ 'methods' => 'POST', 'callback' => [ $this, 'record' ], 'permission_callback' => '__return_true' ] )`.
      There are no `args`, because WordPress validates `args` against every
      parameter source (query string, form body, JSON), and this route reads
      the JSON body only.
    - `record( WP_REST_Request $request ): WP_REST_Response|WP_Error` reads
      `$request->get_json_params()` and checks the rules below in this order.
      The first rule that fails is returned as
      `WP_Error( code, message, [ 'status' => 400 ] )`, before anything is
      written:

      | Rule | Refused when | Code |
      |---|---|---|
      | body | `get_json_params()` is not an array: the content type is not JSON, the body is empty, or it is a JSON scalar | `search_stats_not_json` |
      | `level` | it is not one of the strings `site`, `services`, `service` (strict comparison) | `search_stats_invalid_level` |
      | `query` | it is not a string, or its normalized form is shorter than 1 character (`site`) or 3 (`services`, `service`), or longer than 100, counted with `mb_strlen()` | `search_stats_invalid_query` |
      | `context_id`, for `services` and `service` | it is not an integer > 0; or `get_post_status()` is not `publish`; or, for `service`, `get_post_type()` is not `service` | `search_stats_invalid_context` |
      | `results` | for `site`: it is absent, not an integer, or negative. For `services` and `service`: the key is present at all, `null` included (the sprint's "refused when present") | `search_stats_invalid_results` |

    - For `site`, `context_id` is ignored whatever it holds, and is stored as
      `0`.
    - `context_id` and `results` must be JSON integers, so `"101"` and `3.5`
      are refused. Step 4's module sends numbers.
    - `context_id` is checked for `> 0` before `get_post_status()` runs,
      because `get_post( 0 )` falls back to the global post
      (`wp-includes/post.php:1139–1140`).
    - The levels and the limits are named constants: `MIN_LENGTH_SITE = 1`,
      `MIN_LENGTH_FILTER = 3`, `MAX_LENGTH = 100`. They are rules fixed by
      DECISIONS and by the column width, not settings.
    - A valid request calls `Repository::insert()` once and gets
      `new WP_REST_Response( null, 204 )`. WordPress sends no body with a 204
      (`class-wp-rest-server.php:541–543`).
    - If the insert fails (it returns `false`, e.g. because the table is
      missing), the answer is
      `WP_Error( 'search_stats_not_recorded', …, [ 'status' => 500 ] )`, not a
      204 that would claim a row. `QuestionaryController` answers a failed
      write with 500 too (`post_creation_failed`).
    - Messages are English, through `__( …, 'dovira' )`, as in
      `QuestionaryController`. Nothing reads them: the browser module ignores
      the response.
    - A malformed JSON body never reaches `record()`. `WP_REST_Server`
      refuses it first, with `rest_invalid_json` and 400
      (`class-wp-rest-request.php:735`, through `has_valid_params()` at
      `:903`).
  - `bootstrap.php` requires `Repository.php` and `RecordController.php`.
  - `inc/rest-api.php` gains `use dovira\SearchStats\RecordController;`, and
    at the end of the existing `rest_api_init` closure,
    `$search_stats = new RecordController(); $search_stats->register_routes();`.
    - It does not require the file itself. The feature's bootstrap does, so
      shared code never reaches into a feature directory.
    - `functions.php` requires `inc/rest-api.php` (`:46`) before the feature
      (`:61`). The closure only runs on `rest_api_init`, after the whole
      theme has loaded.
    - `WP_REST_Controller` is loaded by `wp-settings.php:322`, before any
      theme.
  - Test harness:
    - `tests/bootstrap.php` requires five of WordPress's own classes from the
      committed core, as `dirname( __DIR__, 4 ) . '/wp-includes/…'`:
      `WP_Error`, `WP_HTTP_Response`, `WP_REST_Response`, `WP_REST_Request`
      and `WP_REST_Controller`.
      - None of the five files runs code when it loads.
      - A probe on 2026-09-23 in DDEV (PHP 8.3.32, `E_ALL`) loaded and used
        all five outside WordPress with no notice or deprecation. It needed
        only `absint()` and `do_action()`, which Brain\Monkey defines, and a
        stub of `wp_is_json_media_type()`. A JSON body parsed; a form body and
        an empty body gave `null`; a JSON string came back as a string.
      - The tests therefore exercise WordPress's real request parsing, not a
        rewrite of it.
    - `tests/WpdbDouble.php` gains `insert()`. It records
      `[ table, data, format ]` in `$inserts` and returns `$insert_result`,
      which is `1` unless a test sets it to `false`.
  - `tests/Unit/SearchStats/RecordControllerTest.php`.

  Docs in the same commit:
  - `docs/ARCHITECTURE.md`:
    - The feature's Modules row gains the route, `RecordController`
      (validation; 204, 400 or 500) and `Repository::insert()`. Its "must
      never" gains "write the table from anywhere but the route".
    - The diagram line becomes
      `inc/rest-api.php → dovira/v1 (Telegram, Questionary, search-stats)`.
  - `docs/DATA-MODEL.md`:
    - Relations: `dovira_search_queries.context_id ──> wp_posts.ID`, not
      enforced. It is `0` for `site`, and a post deleted later leaves its id
      behind.
    - The table section: rows are inserted only by the route, one per valid
      request, with `created_at` = `current_time( 'mysql', true )`.
  - `docs/TESTING.md` → Rules:
    - WordPress's REST classes come from the committed core through
      `tests/bootstrap.php` and are never rewritten in `tests/`;
    - `wp_is_json_media_type()` is stubbed like any other WordPress
      function;
    - `WpdbDouble` records `insert()`.
  - `docs/features/search-stats/FEATURE.md` → Interfaces: a refusal's body is
    WordPress's error shape `{code, message, data: {status: 400}}`, and the
    route answers `500` when the row could not be written.

  **Touches shared code — may affect other features:**
  - `inc/rest-api.php`. Its consumers are `core`'s Telegram and Questionary
    routes, registered in the same closure. Their lines are unchanged, and
    the new lines come after them.
  - `tests/bootstrap.php` and `tests/WpdbDouble.php`, the theme's test
    harness. Only `search-stats` uses it today.
  → `feat(search-stats): record a search through POST dovira/v1/search-stats/record`

### Files to create/change
**New: `wp-content/themes/dovira/inc/features/search-stats/`**
- `Normalizer.php` (task 1)
- `Repository.php`, `RecordController.php` (task 2)

**New: `wp-content/themes/dovira/tests/Unit/SearchStats/`**
- `NormalizerTest.php` (task 1), `RecordControllerTest.php` (task 2)

**Changed**
- `wp-content/themes/dovira/inc/features/search-stats/bootstrap.php` (tasks 1 and 2)
- `wp-content/themes/dovira/inc/rest-api.php` (task 2, shared)
- `wp-content/themes/dovira/tests/bootstrap.php`, `tests/WpdbDouble.php` (task 2, shared test harness)
- `docs/ARCHITECTURE.md` and `docs/TESTING.md` (tasks 1 and 2);
  `docs/DATA-MODEL.md` and `docs/features/search-stats/FEATURE.md` (task 2)

**Checked and not changed**
- The gate picks up the new files by itself. `php -l` lints every `*.php`
  outside `vendor/` and `node_modules/` (117 files now, 119 after task 1, 122
  after task 2), and `tests/phpunit.xml.dist` scans `Unit/` recursively.
- `tests/composer.json`: the classes under test are required by path, and so
  are the core classes, from the bootstrap.
- `functions.php`: the feature's line is already there.
- The theme `CLAUDE.md`: every convention it states still holds. The
  controller extends `WP_REST_Controller`, uses `dovira/v1`, is registered in
  `inc/rest-api.php` and has an explicit `permission_callback`.
- `docs/DOMAIN.md`: "search query" (at least 3 letters for the two filters,
  and the normalization example) and "search level" already match.

### Tests to write
Every WordPress call a test cares about is recorded by
`Functions\when()->alias()` and asserted (TESTING.md → How to run). `$wpdb` is
a `WpdbDouble( 'wp_' )`.

`NormalizerTest`: `normalize()` through a data provider:
- uppercase Cyrillic: `ВАКЦИНАЦІЯ` → `вакцинація`;
- Ukrainian letters: `ЇЖАК Ґудзик ЄНОТ` → `їжак ґудзик єнот`;
- Latin: `Rabies Vaccine` → `rabies vaccine`;
- mixed script and digits: `УЗД 3D Кота` → `узд 3d кота`;
- spaces at the edges: `"  кіт  "` → `кіт`;
- tabs and newlines at the edges: `"\t\nкіт\r\n"` → `кіт`;
- inner runs of whitespace: `"стерилізація   \t\n кота"` → `стерилізація кота`;
- a non-breaking space: `"стерилізація\u{00A0}кота"` → `стерилізація кота`;
- whitespace only: `" \t\n "` → `''`.

Plus one test: invalid UTF-8 (`"a\xffb"`) → `''`. That makes 10 tests.

`RecordControllerTest`. Each request is a real `WP_REST_Request`: `POST`,
`Content-Type: application/json`, and a `json_encode`d body. The stubs:
- `get_post_status` and `get_post_type` answer from a map: 101 is a published
  `page`, 202 a published `service`, 303 a `draft` `service`, and any other id
  gives `false`;
- `current_time` returns `2026-09-23 12:00:00` and records its arguments;
- `wp_is_json_media_type` is true for `application/json` only;
- the translation functions come from `Functions\stubTranslationFunctions()`.

The tests:
- `register_routes()` makes one call, asserted whole: `dovira/v1`,
  `/search-stats/record`, `POST`, callback `[ $controller, 'record' ]`,
  `permission_callback` `__return_true`.
- Success, one test per level. The response is a `WP_REST_Response` with
  status 204 and `null` data, and `$wpdb->inserts` holds exactly one row:
  - `site`, `"  Вакцинація  "`, `results` 0, `context_id` 55 →
    `[ 'wp_dovira_search_queries', [ level site, query_text вакцинація, context_id 0, results 0, created_at 2026-09-23 12:00:00 ], [ %s, %s, %d, %d, %s ] ]`,
    and `current_time` was called with `( 'mysql', true )`;
  - `services`, `Вакц`, `context_id` 101 → `results` `null`;
  - `service`, `кіт`, `context_id` 202 → `results` `null`.
- Accepted at the edges (a data provider, one row each):
  - a 1-character `site` query;
  - a 100-character Cyrillic query for `services`. It is 200 bytes; the
    length is counted in characters;
  - `кіт` padded with 150 spaces, accepted as `кіт`, because the length is
    measured after normalization.
- Refusals (a data provider). Each returns a `WP_Error` with the code from the
  table and `status` 400, and `$wpdb->inserts` stays `[]`:
  - `level`: missing; `x`; the integer `1`;
  - `query`: missing; the integer `123`; `""`; `"  \t "` (empty after
    normalization); `ва` for `services` (2 characters); `"  ві  "` for
    `service` (2 characters after normalization); 101 Cyrillic characters for
    `site`;
  - `context_id`: missing (for `services`); `"101"` (a string); `0`; `999`
    (unknown); `303` for `service` (a draft); `101` for `service` (a page, not
    a service);
  - `results`: missing for `site`; `-1`; `"3"`; `3.5`; `0` for `services`;
    `null` for `service`.
- Not a JSON body (a data provider; `search_stats_not_json`, no insert):
  - a form body (`application/x-www-form-urlencoded`,
    `level=site&query=кіт&results=0`);
  - an empty body;
  - the JSON string `"кіт"`.
- A failed insert (`insert_result` set to `false`) returns
  `search_stats_not_recorded` with `status` 500, after exactly one insert
  attempt.

That makes 1 + 3 + 3 + 22 + 3 + 1 = 33 tests.

`bin/check.sh` must exit 0 after each task:
- 297 plugin tests;
- 119 files linted after task 1, then 122;
- the theme suite goes from 15 tests to 25, then 58.

### Docs to update
- `docs/ARCHITECTURE.md`: the feature's Modules row (tasks 1 and 2) and the
  diagram line (task 2). These are the step's own items.
- `docs/DATA-MODEL.md`: Relations, which is the step's own item, and the
  table section's line on who writes rows (rule 5: the table gains its
  writer).
- `docs/TESTING.md`: Levels in task 1, to fix the false line, and Rules in
  task 2, because the harness gains the core classes and `insert()`. Rule 5.
- `docs/features/search-stats/FEATURE.md` → Interfaces (task 2): the error
  body and the 500. Rule 5.

### Checks
- **ANTI-PATTERNS:** none violated.
  - No post type, taxonomy, ACF group or block is registered.
  - Nothing goes in `mu-plugins/`, `temp-data/` or `assets/`, and there is no
    city branching.
  - No package is added. The core classes are the repo's own committed
    WordPress.
  - Nothing calls Telegram.
- **Docs vs reality:** mismatches, each resolved:
  1. DECISIONS "Every search is recorded from the browser through one public
     REST route" → Consequences lists the shared files the feature touches,
     but not `inc/rest-api.php`. The sprint (Step 3 and Fixed decisions) and
     FEATURE.md → Fit into the host both name it, and nothing forbids it. So
     the route is registered there, and the task is marked shared.
  2. FEATURE.md → Interfaces says `400` with `{code, message}`. A `WP_Error`
     is sent as `{code, message, data: {status: 400}}`. That is the shape
     WordPress gives `rest_invalid_json`, and the shape the Questionary route
     gives its refusals. It is kept, and task 2 says so in FEATURE.md along
     with the `500`.
  3. The sprint's test list includes a "non-JSON body".
     - A malformed body sent with a JSON content type is refused by WordPress
       before the callback runs (`rest_invalid_json`).
     - Reproducing that in a unit test needs `WP_Http`, whose file exits when
       `ABSPATH` is undefined and loads the Requests library
       (`class-wp-http.php:11–19`).
     - The unit tests cover every non-JSON body that can reach `record()`: a
       form body, an empty body and a JSON scalar. The malformed body becomes
       a check in the verification guide.
  4. TESTING.md → Levels still says the theme suite "holds only the
     bootstrap's smoke test", which has been false since Step 2. Task 1 fixes
     it.
  5. Carried, not part of this step: `search.php:78` echoes the search query
     unescaped (WORKLOG, Step 1). It goes to `/adhoc`.
- **Design:** n/a. No screen.
- **Check command:** `bin/check.sh` (docs/TECH-STACK.md → Check command) was
  green on `master` at `158d6380` after the Step 2 merge: 297 plugin tests,
  117 files, 15 theme tests.
- **Not locally verifiable:** that the route answers on both production
  installs, through whatever sits in front of `/wp-json/` there, and writes
  into that install's own table. It is verified at the sprint-boundary
  deploy from `master` and `kyiv`: one search per install (Step 4's
  production check), with the row read back over SSH.

### Questions / ambiguities
none

## Plan — Sprint 1, Step 4: Recording from the browser on all three levels   (status: implemented, awaiting close)

### Branch
`search-stats/sprint-1-recording` ← `master`
(root `CLAUDE.md` → git model: simple, task branch → `master`. The name is the
**Branch** line of `SPRINT-1.md`. The branch is recreated from `master`, which
now carries Steps 1–3, and deleted at the close.)

### Tasks (ordered)
Written for the recommended answer to Question 1 (A). Under answer B, task 2
changes as that question describes.

- [x] **1. The header search: results count, data attributes, the module, and `app.js`.**
  - `inc/features/search-stats/ResultsCount.php` holds
    `dovira\SearchStats\ResultsCount`, a `final` class:
    - `shown( array $sub_services, array $search_posts ): int` returns how
      many results `search.php` actually lists: every matched price row
      (`$sub_services`), the services with `post_parent === 0` (the
      template's own strict check, `search.php:138`), and every `post`,
      `page` and `employee` in `$search_posts`.
    - Anything else in `$search_posts` (a child service, or another post
      type the core query or the meta query returned) is collected by the
      page but never printed, so it is not counted. This is the Step 1 audit
      finding.
    - The three listed types are one named constant.
  - `bootstrap.php` requires `ResultsCount.php`.
  - `search.php`. **Touches shared code — may affect other features:** the
    header search of `core`.
    - After the result sets are built (`:72`), it computes
      `$search_stats_results = \dovira\SearchStats\ResultsCount::shown( $sub_services, $search_posts );`.
    - The results section (`:74`, `<div class="section section--mb-standard">`)
      gains
      `data-search-stats-query="<?= esc_attr( $search_query_var ); ?>"` and
      `data-search-stats-results="<?= (int) $search_stats_results; ?>"`.
    - Nothing else in the file changes. The unescaped echo at `:78` stays
      for `/adhoc`; the new attribute is escaped.
  - `source/scripts/features/search-stats/record.js`, a new directory, where
    the theme `CLAUDE.md` puts a feature's scripts:
    - `ENDPOINT = '/wp-json/dovira/v1/search-stats/record'`. It is
      root-relative, as `questionary-form-handler.js` builds its URL.
    - `recordSearch( level, query, contextId, results )`:
      - The body is `JSON.stringify( { level, query, context_id: contextId, results } )`.
        `JSON.stringify` drops `undefined` keys. So a filter sends no
        `results` key at all, which matters because the route refuses one
        even when it is `null`, and the site level sends no `context_id`.
        The numbers go as numbers (Step 3's WORKLOG item).
      - It sends with `navigator.sendBeacon( ENDPOINT, new Blob( [ body ], { type: 'application/json' } ) )`.
        When `sendBeacon` is missing, returns `false` or throws, it falls
        back to
        `fetch( ENDPOINT, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body, keepalive: true } ).catch( () => {} )`.
        The do-step browser run notes which transport Chrome used.
      - Nothing reads the answer, and a failure is silent: recording never
        disturbs the page. The text goes as typed, because normalization is
        the server's (FEATURE.md → Invariants).
    - `recordSiteSearch()` finds `[data-search-stats-query]`. When the
      element is there and the query is not empty once trimmed, it calls
      `recordSearch( 'site', query, undefined, Number( results ) )` once. An
      empty `?s=` sends nothing, since the route would refuse it.
      - This is "`app.js` calls `recordSearch` once when those attributes
        are present", written in `app.js`'s own idiom: `app.js` only imports
        modules and calls their entry functions.
      - It also matches DECISIONS: "the module sends them once on load".
  - `source/scripts/app.js`. **Touches shared code — may affect other
    features:** every front-end module is loaded from it.
    - It gains one dynamic import,
      `const {recordSiteSearch} = await import('@scripts/features/search-stats/record');`,
      and one call, `recordSiteSearch();`, after the existing ones.
    - The existing lines don't change.
  - `npm run build` rebuilds `assets/`.
    - While planning, a build of the unchanged source in a scratch copy
      (Vite 5.4.19, Node 26.8.2) reproduced the committed `assets/` exactly.
    - So the diff contains only the chunks this task changes, a new chunk
      and the manifest.
    - The theme `.env` already has `WP_ENVIRONMENT_TYPE=production`, so the
      local site serves `assets/`.
  - `tests/Unit/SearchStats/ResultsCountTest.php`.
  - Before the commit, the gate runs, and in Chrome on the local install:
    - `https://dovira.ddev.site/?s=вакцинація` makes one request to
      `…/record` with `level: site` and the count the page lists, and writes
      one row;
    - `curl 'https://dovira.ddev.site/?s=test'` writes no row.

  Docs in the same commit:
  - `docs/ARCHITECTURE.md`:
    - the feature's Modules row gains `ResultsCount` and `record.js`;
    - Data flows gains the new flow "Site search → statistics row", with the
      site level;
    - its "must never" gains "record a search from PHP".
  - `docs/TECH-STACK.md` → ANTI-PATTERNS: "do not record a search
    server-side in `search.php`; the browser module is the one recorder".
    The step names this line.
  - `docs/features/search-stats/FEATURE.md` → Interfaces, JS: names
    `recordSiteSearch()`, called once by `app.js`, as the path the results
    page takes into `recordSearch()`. Rule 5.
  → `feat(search-stats): record the header search from its results page`

- [x] **2. The two filters: the pause, the context and the calls.**
  - `record.js` gains `PAUSE_MS = 1500` and `MIN_LENGTH = 3`, both named
    constants, and `debouncedRecorder( input, level, contextId )`:
    - Every `input` event restarts a `PAUSE_MS` timer. `blur` sends at once;
      DECISIONS, FEATURE.md → Invariants and DOMAIN "search query" all say
      "or the field lost focus", even though the step's line doesn't.
    - To send, it takes `value = input.value.trim()`. When
      `value.length >= MIN_LENGTH` and `value !== lastSent`, it sets
      `lastSent = value` and calls
      `recordSearch( level, input.value, contextId )`.
      - `lastSent` lives in the call's closure, so it is per input and per
        page view.
      - It compares with the **last** value sent, as DECISIONS says (see
        Docs vs reality 2).
      - The trim is a gate, not normalization. The text still goes as
        typed.
    - It adds its own listeners, so the filters' own `input` and reset
      handlers are untouched. A reset sets the value in code, which fires no
      event. A timer already running then finds an empty value and sends
      nothing.
  - The context id. **Touches shared code — may affect other features:**
    `core`'s `services` block, on every page that carries it, and the
    service pages.
    - `inc/acf/blocks/services/template.php:79`: `#services-search-input`
      gains `data-search-stats-context="<?= (int) get_the_ID(); ?>"`. That
      is the page hosting the block, because blocks render inside
      `the_content()` in the loop.
    - `single-service.php:60`: `#service-search-input` gains the same, which
      is the service.
    - Nothing else in either template changes.
  - `source/scripts/modules/services-search.js`. **Touches shared code**, for
    the `services` block.
    - `import {debouncedRecorder} from '@scripts/features/search-stats/record';`.
    - Inside `if (services.length)`, the one branch where the filter works,
      it calls
      `debouncedRecorder(input, 'services', Number(input.dataset.searchStatsContext));`.
  - `source/scripts/modules/init-service-price-lists.js`. **Touches shared
    code**, for service pages.
    - The same import.
    - Inside `serviceSearch()`'s `if (input)` it calls
      `debouncedRecorder(input, 'service', Number(input.dataset.searchStatsContext));`.
      `serviceSearch()` is only wired when the price toggles and lists match
      (Step 1 audit), so it records exactly where the filter works.
  - `npm run build` rebuilds `assets/` again.
  - Before the commit, the gate runs, and in Chrome on the local install,
    with the Network tab:
    - On `Послуги` (page 12), typing `вак`, `вакц`, `вакцин` without pausing
      sends one request after the pause, with `level: services` and
      `context_id: 12`.
    - On service 18, typing `кіт`, waiting, deleting it and retyping `кіт`
      sends one request only.
    - Leaving the field (`blur`) before the pause sends at once.

  Docs in the same commit:
  - `docs/ARCHITECTURE.md`: the flow "Site search → statistics row" gains
    the two filters, and the Modules row gains `debouncedRecorder`.
  - `docs/features/search-stats/FEATURE.md`:
    - Invariants: "never the same value twice from the same input" becomes
      "never the value that input sent last", per DECISIONS;
    - Fit into the host → Shared code: adds the two templates
      (`data-search-stats-context`).
    - Rule 5.
  - `docs/DECISIONS.md`: a new entry amending one clause of "Every search is
    recorded from the browser through one public REST route". The
    templates' "(nothing — their input ids stay the hook)" becomes "each
    input gains `data-search-stats-context`" (Question 1, answer A).
  - `docs/DOMAIN.md`: confirmed against the code, no change. "Search query"
    (pause or focus lost, at least three letters for the two filters,
    normalized) and "search level" match.
  → `feat(search-stats): record the two filters' searches after a pause`

### Files to create/change
**New**
- `wp-content/themes/dovira/inc/features/search-stats/ResultsCount.php` (task 1)
- `wp-content/themes/dovira/source/scripts/features/search-stats/record.js` (tasks 1 and 2)
- `wp-content/themes/dovira/tests/Unit/SearchStats/ResultsCountTest.php` (task 1)

**Changed**
- `wp-content/themes/dovira/inc/features/search-stats/bootstrap.php` (task 1)
- Shared (marked in the tasks):
  - `search.php` and `source/scripts/app.js` (task 1);
  - `inc/acf/blocks/services/template.php`, `single-service.php`,
    `source/scripts/modules/services-search.js` and
    `source/scripts/modules/init-service-price-lists.js` (task 2).
- `wp-content/themes/dovira/assets/**`, rebuilt by `npm run build` (tasks 1 and 2).
- `docs/ARCHITECTURE.md` and `docs/features/search-stats/FEATURE.md` (tasks 1
  and 2); `docs/TECH-STACK.md` (task 1); `docs/DECISIONS.md` (task 2).

**Checked and not changed**
- The gate picks up the two new PHP files by itself: 122 files now, 124
  after task 1. The gate does not read JS, so no config learns about it.
- `vite.config.js`: the `@scripts` alias already resolves
  `@scripts/features/…`, and no new entry point is needed. The module is
  imported by `app.js`, and Vite splits it into its own chunk.
- The theme `CLAUDE.md` already says a feature's scripts go under
  `source/scripts/features/{name}/`.
- `header.php` is not touched, so the `master` → `kyiv` merge at the sprint
  boundary meets no conflict (`kyiv` differs from `master` only in
  `header.php`).
- `docs/DOMAIN.md` matches.

### Tests to write
`ResultsCountTest`: `shown()` through a data provider. Results are plain
objects carrying `post_parent`, and nothing WordPress-specific is needed.
- nothing found: `[]` and `[]` give `0`;
- price rows only: three rows give `3`;
- every listed kind: 2 price rows, 1 top-level service, 2 posts, 1 page and
  1 employee give `7`;
- a child service (`post_parent` 5) next to a top-level one gives `1`;
- types the page does not list (`vacancy`, `attachment`) next to one page
  give `1`.

That makes 5 tests.

The JS has no tests: there is no JS runner in the project, and the step says
so. The route's 33 tests cover the server side. The browser runs listed in
each task are the check before each commit, and the verification guide
repeats them.

`bin/check.sh` must exit 0 after each task:
- 297 plugin tests;
- 124 files linted;
- the theme suite at 58 → 63 tests after task 1, unchanged after task 2.

### Docs to update
- `docs/ARCHITECTURE.md` → Data flows, "Site search → statistics row". This
  is the step's own item. The Modules row follows from rule 5.
- `docs/DOMAIN.md`: confirm the terms against the code. This is the step's
  own item; the terms match and there is no change.
- `docs/TECH-STACK.md` → ANTI-PATTERNS: the line the step names.
- `docs/features/search-stats/FEATURE.md` → Interfaces (JS), Invariants (the
  last-value rule), and Fit into the host (the two templates). Rule 5.
- `docs/DECISIONS.md`: the amendment, under answer A.

### Checks
- **ANTI-PATTERNS:** none violated.
  - `assets/` is rebuilt with `npm run build`, never edited by hand.
  - Nothing is enqueued by a hand-written path: the module is reached
    through `app.js` and the Vite manifest.
  - There is no city branching, and nothing goes in `mu-plugins/`.
  - No package is added. `sendBeacon` and `fetch` are the browser's own.
  - The step adds one line to the list.
- **Docs vs reality:** mismatches, each resolved or asked:
  1. DECISIONS "Every search is recorded from the browser through one public
     REST route" → Consequences says `single-service.php` and the `services`
     block template get "nothing — their input ids stay the hook". Step 4
     prints `data-search-stats-context` next to each input, which changes
     both. A DECISIONS entry is not overridden silently, so this is
     **Question 1**.
  2. FEATURE.md → Invariants and the sprint say "never the same value twice
     from the same input in a page view". DECISIONS says "differs from the
     **last** value that input sent in this page view". They differ for
     `кіт`, `пес`, `кіт`: that is two rows by the first rule and three by
     DECISIONS. DECISIONS takes precedence, so the code compares with the
     last value sent and FEATURE.md is reworded in task 2. The sprint's
     check ("type `кіт`, wait, delete, retype `кіт`: one request") holds
     under both.
  3. DECISIONS says the count is "fuzzy price rows + posts by `key_words` /
     `key_words_services` + the core query … computed the way the page
     computes them". The page lists only part of those three sets (Step 1
     audit). The Sprint Goal says "how many results the page showed", so
     `ResultsCount` counts what is listed.
  4. A crawler or prefetcher leaves no row. WordPress's speculative loading
     defaults to `prefetch` with `conservative` eagerness, which never runs
     JS, and with pretty permalinks it excludes every URL with a query
     string (`wp-includes/speculative-loading.php:125`, `:131`, `:217–224`).
     No task.
  5. The step's helper line names the pause, the minimum length and the
     repeat rule, but not `blur`. DECISIONS, FEATURE.md and DOMAIN all say
     "or the field loses focus", so it is included.
  6. Carried, not part of this step: `search.php:78` echoes the query
     unescaped. It stays for `/adhoc`, even though task 1 edits two lines
     above it.
- **Design:** n/a. Nothing visible changes: attributes and a script only.
- **Check command:** `bin/check.sh` (docs/TECH-STACK.md → Check command) was
  green on `master` at `a0ddffb2` after the Step 3 merge: 297 plugin tests,
  122 files, 58 theme tests.
- **Not locally verifiable:** both productions after the hand deploy of
  `master` and `kyiv`.
  - One search per level on each install must leave one row carrying that
    install's prefix and post ids.
  - This also proves Step 3's route answers there.
  - It is the Step's own "Repeat once on `kyiv.dovira.vet`", checked at the
    sprint-boundary deploy, with the rows read over SSH.

### Questions / ambiguities
1. **Where does the browser get the context id?**
   DECISIONS (Consequences) says the two input templates change nothing.
   Step 4 prints `data-search-stats-context` on each input.
   - **A (recommended). Do what Step 4 says.**
     - Each input gets `data-search-stats-context="<?= (int) get_the_ID(); ?>"`
       (task 2).
     - A new DECISIONS entry amends that one clause.
     - The id sits on the input that uses it, and it is the same
       data-attribute mechanism `search.php` uses.
     - Cost: two more shared templates, each one line, marked in task 2.
   - **B. Keep DECISIONS as written.**
     - The templates stay untouched. `record.js` reads the id from
       WordPress's body class: `page-id-12` on the services page and
       `postid-18` on a service, both observed locally.
     - Task 2 loses the two template edits, gains a small
       `contextFromBody()` in `record.js`, and writes no DECISIONS entry.
     - Cost: the recorder then depends on the body class format, which
       plugins can filter, instead of a value the theme prints for this
       purpose.

   Recommendation: A. The step is the more specific, later statement of the
   same design, and the attribute is explicit.

   Resolved: approved as recommended — A. Each input gets
   `data-search-stats-context` (task 2), and a DECISIONS entry amends the
   clause.
