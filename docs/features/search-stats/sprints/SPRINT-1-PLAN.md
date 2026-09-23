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
