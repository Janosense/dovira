# Verification — search-stats, Sprint 1, Step 1

**Delta-audit and the theme's test suite in the gate** · written at close on 2026-09-23
Branch merged: `search-stats/sprint-1-recording` → `master`

What this step promises:
- The theme has a unit-test suite of its own, and `bin/check.sh` runs it as a
  fifth stage.
- A test that asserts nothing fails the gate.
- Installing that suite never touches the theme's committed `vendor/`, which
  production runs on (DECISIONS "The theme's test tooling is its own Composer
  project in `tests/`").
- Nothing on the site changes: no PHP the site loads, no template and no
  asset was modified.

Run everything from the repo root (`/Users/tymofii/Projects/php/dovira`).

## 1. The gate installs the theme's tooling itself and prints five stages
Start from what a clean checkout has: no `tests/vendor/` and no lock.
```bash
rm -rf wp-content/themes/dovira/tests/vendor wp-content/themes/dovira/tests/composer.lock
bash bin/check.sh; echo "exit=$?"
```
Expected, in this order:
1. `==> composer install (theme dovira tests)`, reporting `Package operations: 29 installs`.
2. `==> PHPCS (ga-telegram-bridge)`.
3. `==> PHPStan (ga-telegram-bridge)`.
4. `==> PHPUnit (ga-telegram-bridge)`, then `OK (297 tests, 1069 assertions)`.
5. `==> php -l (theme dovira)`, then `111 files checked` (108 before this step,
   plus the three new PHP files in `tests/`).
6. `==> PHPUnit (theme dovira)`, then `OK (1 test, 1 assertion)`.

The run ends with `==> check: all green` and `exit=0`. The last lines are the
theme suite's `Time: …, Memory: …`, a blank line, `OK (1 test, 1 assertion)`,
then `==> check: all green`.

## 2. The same gate inside the container (PHP 8.3)
```bash
ddev exec bash bin/check.sh; echo "exit=$?"
```
Expected: the same five stages, ending with `OK (1 test, 1 assertion)`,
`==> check: all green` and `exit=0`.

This time there is no `composer install` line, because it reuses the
`tests/vendor/` that §1 installed on the host. That works because
`tests/composer.json` pins `config.platform.php` to `8.3`.

## 3. Negative check: a test that asserts nothing fails the gate
Remove the smoke test's only assertion:
```bash
perl -0pi -e 's/\n\t\t\$this->assertSame\([^\n]*\n//' wp-content/themes/dovira/tests/Unit/BootstrapTest.php
git diff --stat   # 1 file changed, 2 deletions(-)
bash bin/check.sh; echo "exit=$?"
```
Expected under `==> PHPUnit (theme dovira)`:
- `There was 1 risky test:`
- `This test did not perform any assertions`
- `OK, but there were issues!`
- `Tests: 1, Assertions: 0, Risky: 1.`

`==> check: all green` must **not** print, and `exit=1`.

The Brain\Monkey stub alone does not count as an assertion. That is why the
test uses `Functions\when()` and not `expect()`.

Restore the file and confirm green again:
```bash
git checkout -- wp-content/themes/dovira/tests/Unit/BootstrapTest.php
bash bin/check.sh; echo "exit=$?"   # exit=0
```

## 4. Negative check: the theme's committed `vendor/` is untouched
```bash
git status --short wp-content/themes/dovira/vendor
grep -c "mockery\|brain/monkey\|phpunit\|deep-copy" wp-content/themes/dovira/vendor/composer/autoload_files.php
git ls-files wp-content/themes/dovira/tests
git status --short --ignored wp-content/themes/dovira/tests
```
Expected:
- The first command prints nothing.
- The `grep` prints `0`: no dev package reached the autoloader that
  `functions.php` loads on every request.
- `git ls-files` lists exactly five files: `TestCase.php`,
  `Unit/BootstrapTest.php`, `bootstrap.php`, `composer.json`,
  `phpunit.xml.dist`.
- The last command shows three `!!` (ignored) entries: `tests/.phpunit.cache/`,
  `tests/composer.lock`, `tests/vendor/`.

## 5. The theme suite runs on its own
```bash
cd wp-content/themes/dovira/tests && composer test; cd -
```
Expected: `OK (1 test, 1 assertion)`. This is the command the theme
`CLAUDE.md` → Local commands names.

## 6. The site is unaffected
```bash
curl -sk -o /dev/null -w '%{http_code}\n' https://dovira.ddev.site/
curl -sk -o /dev/null -w '%{http_code}\n' 'https://dovira.ddev.site/?s=test'
```
Expected: `200` and `200`. Nothing the site loads changed, and this confirms
the theme's autoloader still loads.

## 7. The delta-audit
Open `docs/features/search-stats/sprints/SPRINT-1-PLAN.md` → Step 1 → *Confirmed
in `/do-step`*. The table lists the touchpoints of Steps 2–4 (file:line, change,
consumers), and two notes for Step 4 follow it:
- the price-list filter is wired only when toggles and lists match;
- the results count `search.php` shows is the displayed subset plus the price
  rows, not the raw union of its queries.

One finding was deliberately left out of the sprint: `search.php:78` echoes the
query unescaped. `?s=<b>x</b>` renders bold. That is for `/adhoc`.

## Not locally verifiable
The hand deploy must leave `wp-content/themes/dovira/tests/vendor/` behind,
exactly as it leaves the plugin's dev `vendor/` behind. This is first checked
at the sprint-boundary deploy: list `wp-content/themes/dovira/tests/` on each
server, and expect the five committed files and **no** `vendor/`.
