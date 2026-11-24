# Verification — city-popup, Sprint 1, Step 1

**Delta-audit and Vitest in the gate** · written by `/do-step` on 2026-09-23
Branch under test: `city-popup/sprint-1-city-question` (not merged yet)

What this step promises:
- The theme has a Vitest suite for its JavaScript (`npm test`, tests in
  `tests/js/`, `node` environment). `bin/check.sh` runs it as a sixth stage.
- A JS test that asserts nothing fails the run, as in the PHPUnit suites.
- The gate says clearly why it cannot run when:
  - `node` or `npm` is missing;
  - the theme's `node_modules/` belongs to another OS (the DDEV container
    seeing the host's install).

  In the second case, it stops before stage 1 and never touches
  `node_modules/`.
- The production build is unaffected: `npm run build` succeeds and `assets/`
  does not change.
- Nothing on the site changes. No PHP the site loads, no template, no source
  script or style and no asset was modified.

Run everything from the repo root (`/Users/tymofii/Projects/php/dovira`)
unless an item says otherwise.

## 1. Check out the task branch and install
```bash
git switch city-popup/sprint-1-city-question
git log --oneline -4
(cd wp-content/themes/dovira && npm install && npm ls vitest)
```
Expected:
- The log shows `docs(step): verification guide for sprint 1 step 1`,
  `chore: run the theme's Vitest suite as the gate's sixth stage` and
  `chore(theme): add Vitest for the theme's JS unit tests` on top of
  `7718f88c`.
- `npm install` reports `up to date`. npm 11 also warns that three existing
  packages (`core-js-pure`, `esbuild`, `fsevents`) have install scripts not
  covered by `allowScripts`. That warning predates this step and is harmless.
- `npm ls vitest` shows `vitest@3.2.7` with `vite@5.4.19 deduped`, so there is
  no second copy of Vite.

## 2. The gate on the host prints six stages and passes
```bash
bash bin/check.sh; echo "exit=$?"
```
Expected, in this order:
1. `==> PHPCS (ga-telegram-bridge)`
2. `==> PHPStan (ga-telegram-bridge)`
3. `==> PHPUnit (ga-telegram-bridge)`, then `OK (309 tests, 1103 assertions)`
4. `==> php -l (theme dovira)`, then `134 files checked` (unchanged, because
   this step adds no PHP file)
5. `==> PHPUnit (theme dovira)`, then `OK (92 tests, 229 assertions)`
6. `==> Vitest (theme dovira)`, then `> vitest run`, ` RUN  v3.2.7 …`,
   `✓ tests/js/smoke.test.js (1 test)`, `Test Files  1 passed (1)`,
   `Tests  1 passed (1)`

The run ends with `==> check: all green` and `exit=0`.

The `Browserslist: browsers data (caniuse-lite) is 21 months old` notice under
stage 6 comes from the theme's PostCSS config, which Vite loads. `npm run
build` prints it too, and it changes nothing.

## 3. Negative check: a failing JS test stops the gate at stage 6
```bash
sed -i '' "s/toBe('function')/toBe('string')/" wp-content/themes/dovira/tests/js/smoke.test.js
bash bin/check.sh; echo "exit=$?"
git checkout -- wp-content/themes/dovira/tests/js/smoke.test.js
```
Expected:
- Stages 1–5 pass as in §2.
- Under `==> Vitest (theme dovira)`: `AssertionError: expected 'function' to
  be 'string'` and `Test Files  1 failed (1)`.
- There is **no** `==> check: all green`, and the result is `exit=1`.
- After the `git checkout`, `git status` is clean again.

## 4. Negative check: a JS test that asserts nothing fails the run
```bash
printf "import { it } from 'vitest'\nit('asserts nothing', () => {})\n" > wp-content/themes/dovira/tests/js/no-assert.test.js
(cd wp-content/themes/dovira && npm test); echo "exit=$?"
rm wp-content/themes/dovira/tests/js/no-assert.test.js
```
Expected:
- `× asserts nothing` with `Error: expected any number of assertion, but got none`.
- `Test Files  1 failed | 1 passed (2)` and `exit=1`.

That error comes from `expect.requireAssertions` in
`wp-content/themes/dovira/vitest.config.js`.

## 5. Negative check: DDEV stops before stage 1 and leaves `node_modules/` alone
```bash
ddev exec bash bin/check.sh; echo "exit=$?"
ls wp-content/themes/dovira/node_modules/@rollup wp-content/themes/dovira/node_modules/@esbuild
```
Expected:
- Before any `==>` stage line:
  ```
  check: Vite does not load here (Linux aarch64) — the theme's node_modules/ was installed on another OS, or is incomplete.
         Run the gate where node_modules/ was installed (the host), or run npm install in the theme on this system.
         Do not delete node_modules/ from the DDEV container: the host's npm start / npm run build need it.
  ```
- DDEV adds `Failed to execute command 'bash bin/check.sh': exit status 1`, and
  the result is `exit=1`. There is **no** `==> PHPCS` line.
- `ls` still shows `rollup-darwin-arm64` and `darwin-arm64`: the host's install
  is untouched.

This is the resolved Question 1 of the plan (answer A). The gate runs on the
host, and `docs/TECH-STACK.md` → Check command says so.

## 6. Negative check: no Node on PATH
```bash
mkdir -p /tmp/dovira-nonode
ln -sf "$(command -v php)" /tmp/dovira-nonode/php
ln -sf "$(command -v composer)" /tmp/dovira-nonode/composer
env PATH="/tmp/dovira-nonode:/usr/bin:/bin" bash bin/check.sh; echo "exit=$?"
rm -rf /tmp/dovira-nonode
```
Expected, as the only output:
`check: node and npm are required (the theme's JS tests, stage 6) — install Node.js, which brings npm, and put both on PATH.`
followed by `exit=1`. No stage runs.

## 7. The gate installs `node_modules/` itself when it is missing
This moves the install aside and restores it at the end.
```bash
mv wp-content/themes/dovira/node_modules wp-content/themes/dovira/node_modules.before-check
bash bin/check.sh; echo "exit=$?"
```
Expected:
- `==> npm install (theme dovira)` comes before `==> PHPCS
  (ga-telegram-bridge)`, and npm reports `added 203 packages` (the count
  on 2026-09-23, from the local `package-lock.json`).
- Then the six stages of §2, `==> check: all green` and `exit=0`.

Afterwards, keep the fresh install:
```bash
rm -rf wp-content/themes/dovira/node_modules.before-check
```
If the install failed (no network, for example), put the old one back instead:
```bash
rm -rf wp-content/themes/dovira/node_modules
mv wp-content/themes/dovira/node_modules.before-check wp-content/themes/dovira/node_modules
```

## 8. The production build is unaffected
```bash
(cd wp-content/themes/dovira && npm run build); echo "exit=$?"
git status --short
```
Expected:
- `✓ built in …`, `[vite-plugin-static-copy] Copied 22 items.`, and `exit=0`.
- `git status --short` prints nothing: `assets/` was rebuilt byte for byte.

`npm run build` sets `WP_ENVIRONMENT_TYPE=production` in the theme's `.env`.
It was already `production` on this install. If you were running `npm start`,
run it again afterwards.

## 9. The site is unchanged
Open `https://dovira.ddev.site/` and `https://dovira.ddev.site/news/`.
- Both pages look and behave as before, and the browser console shows no new
  error.
- `git diff master --stat -- wp-content/themes/dovira` lists only
  `CLAUDE.md`, `package.json`, `tests/js/smoke.test.js` and
  `vitest.config.js`. None of them is loaded by WordPress or by a page.

## 10. The audit's one conflict (input for Step 2)
```bash
ddev wp eval 'foreach ( [ "kharkiv", "kyiv", "Харків", "Київ" ] as $k ) { echo "$k: uk=" . pll_translate_string( $k, "uk" ) . " ru=" . pll_translate_string( $k, "ru" ) . "\n"; }'
```
Expected:
```
kharkiv: uk=kharkiv ru=kharkiv
kyiv: uk=kyiv ru=kyiv
Харків: uk=Харків ru=Харьков
Київ: uk=Київ ru=Киев
```
- The registered strings `kharkiv` / `kyiv` hold no city names. The names live
  in the `Dovira: Cities` strings.
- Step 2 says its buttons reuse `kharkiv` / `kyiv`, so Step 2's plan has to
  settle this. See `SPRINT-1-PLAN.md` → Step 1 → Delta-audit.
- Nothing in this step depends on it.
