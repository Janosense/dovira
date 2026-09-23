# Step plans — city-popup, Sprint 1

<!-- Written by /plan-step, one section per step. Approval of a section
     authorizes that step only. -->

## Plan — Sprint 1, Step 1: Delta-audit and Vitest in the gate   (status: implemented, awaiting verification)

### Branch
`city-popup/sprint-1-city-question` ← `master`
(root `CLAUDE.md` → git model: simple, task branch → `master`. The name is the
**Branch** line of `SPRINT-1.md`. As in the search-stats sprints, the branch is
recreated from `master` for each step and deleted at the close. `master` =
`origin/main` = `7718f88c`. The only branch not merged into `master` is `kyiv`,
the Kyiv deploy branch, which only ever merges `master` in.)

### Delta-audit, read while planning (task 1 re-reads and reports it)
- **`core` (`docs/features/core/FEATURE.md`).** No sprints. The surface this
  sprint consumes is listed under Interfaces: the `app.js` module list, the
  `colors.css` tokens, `template-parts/header/*`, and the Polylang groups
  `dovira` and `Dovira: Cities`. Step 1 touches none of them.
- **`search-stats` (`docs/features/search-stats/FEATURE.md`).** It also
  changes `app.js`: it imports `@scripts/features/search-stats/record` (`:15`)
  and calls `recordSiteSearch()` once on load (`:30`). There is no conflict.
  Its JS predates Vitest and has no JS tests, which is outside this sprint.
- **Shared code the later steps touch** (unchanged in Step 1):

| Step | Touchpoint | State today | Consumers |
|---|---|---|---|
| 2 | `functions.php:58–61` | one feature block (`search-stats`, the `require_once` at `:61`) before the `WP_CLI` block at `:63–68`; city-popup's line goes after it | every theme feature |
| 2 | `inc/utils/polylang-string-translations.php:3–20` | 17 strings, group `dovira`, name `String`; `kyiv` / `kharkiv` at `:17–18` | every template printing a registered string |
| 3 | `source/scripts/app.js:1–31` | 14 dynamic imports, each `await`ed in one async IIFE, then 14 calls. There is no `try`/`catch`: one import or call that throws stops everything after it, which bears on Step 3's "an error in this feature must not stop the modules after it" | every front-end module |
| 3 | `source/styles/app.css` | `@styles/components/*` and `@styles/blocks/*` only; no `features/` import yet | every page's CSS |
| 4 | `template-parts/header/site-header.php` | city from `str_contains( get_site_url(), 'kyiv' )` (`:3`). Switcher, desktop `:30–60` and mobile `:145–175`: the other city is an `<a>` with a production literal href (`:47`, `:52`, `:161`, `:166`), the current one a `div`. Labels are literal ternaries (`'Київ' : 'Киев'`), not Polylang strings, and there is no `data-city` yet | every page on both installs |
| 3–4 | existing `data-city` | on `<button class="city-toggle__button">` and `<li>` (`service-city` slugs) in `inc/acf/blocks/employees/template.php:87,109` and `vacancies/template.php:87,103`, read by `toggle-cities.js`. A selector scoped to `a[data-city]` does not match them | `core` (employees, vacancies blocks) |
| 1 | `bin/check.sh` | five stages; PHP ≥ 8.3 check; `composer install` when a `vendor/` is missing. Green on the host today: plugin 309 tests, 134 files linted, theme 92 tests | every commit |

- **Local DB (`dovira.ddev.site`), checked while planning:**
  - `news` id 211 → ru `blog` (`/ru/blog/`); `services` id 12 → ru `uslugi`;
    `contacts` id 219 → ru `kontakty` — match.
  - `service` rewrite `{"slug":"services","with_front":false}`, no archive —
    match.
  - `home_url()` = `https://dovira.ddev.site`, `page_for_posts` = 0 — match.
  - **Mismatch:** the Polylang strings `kharkiv` / `kyiv` do **not** hold the
    city names. `pll_translate_string()` returns `kharkiv` / `kyiv` in both uk
    and ru, and no template prints them. The names exist as the `Dovira: Cities`
    strings (`inc/polylang.php:66`, one per `service-city` term, keyed by the
    Ukrainian term name): `Харків` → ru `Харьков`, `Київ` → ru `Киев`. This
    conflicts with Step 2 ("the buttons reuse the registered `kharkiv` /
    `kyiv`") and with `FEATURE.md` → Fit into the host. It changes no Step 1
    task. The step report lists it as a conflict, and Step 2's plan settles it.

**Confirmed in `/do-step` (task 1), 2026-09-23.** Everything above still
holds on the task branch. Three line numbers were corrected in the table:
`functions.php:58–61` and the `WP_CLI` block at `:63–68`, `app.js` 31 lines
with the call at `:30`. The DB facts were re-read with `ddev wp eval`:
`news`=211→`blog`, `services`=12→`uslugi`, `contacts`=219→`kontakty`;
`service` rewrite slug `services`; `kharkiv` / `kyiv` untranslated in uk and ru.

### Tasks (ordered)
Written for the recommended answer to Question 1 (A).

- [x] **1. Delta-audit.** Re-read the two `FEATURE.md` files, the six shared
  files, and the three local-DB facts above
  (`ddev wp eval` with `get_page_by_path()` + `pll_get_post()`,
  `get_post_type_object( 'service' )->rewrite`, and `pll_translate_string()`
  for `kharkiv` / `kyiv` in uk and ru). Confirm the audit still holds and put
  the touchpoint table and the Polylang conflict in the step report. No code
  changes.
  → no commit

- [x] **2. Vitest in the theme.**
  - In `package.json`, add `"test": "vitest run"` to `scripts` and
    `"vitest": "^3.2"` to `devDependencies`. Edit the range by hand: `npm
    install -D` would save `^3.2.7`, and DECISIONS approved `^3.2`.
  - Run `npm install` in the theme. It should resolve **vitest 3.2.7** (the
    `V3` dist-tag) and reuse the theme's Vite 5.4.19, since vitest's range is
    `^5 || ^6 || ^7` and adds no second Vite.
  - Create `vitest.config.js` at the theme root, using `defineConfig` from
    `vitest/config`:
    - `test.environment: 'node'`;
    - `test.include: ['tests/js/**/*.test.js']`;
    - `test.expect.requireAssertions: true`. `docs/TESTING.md` says "a test
      that asserts nothing fails the run" in every suite, and this is Vitest's
      equivalent of `failOnRisky`;
    - `resolve.alias['@scripts']`, written as in `vite.config.js`
      (`normalizePath( join( __dirname, './source/scripts' ) )`).

    When `vitest.config.js` exists, Vitest does not load `vite.config.js`, so
    the build's plugins and `loadEnv` never run in tests.
  - Add `tests/js/smoke.test.js` (see Tests).
  - Run `npm test`: 1 passed. Once, uncommitted, add a test with no
    `expect()` and check that it fails the run.
  - Run `npm run build`. It must succeed, and `git status` must show no change
    under `assets/`. `.env` is already `production`, so `env-prod` changes
    nothing. If `assets/` changes, stop and report: this step changes no
    front-end source.
  - `.gitignore` already covers `node_modules/` (`:3`) and `package-lock.json`
    (`:4`); `git check-ignore` confirmed both. No change.
  - Docs in the same commit (core rule 5):
    - `docs/TECH-STACK.md` → Stack, the Vitest row: `3.2 (unverified — pinned
      at bootstrap)` → `3.2.7`. The Approved dependencies log gets the
      installed version and what it pulled.
    - `docs/TESTING.md` → Levels, a new row "Unit (theme JS)"
      (`wp-content/themes/dovira/tests/js/`, pure modules, `node`, no DOM).
      How to run gets `npm test` and "after pulling this change, run
      `npm install` in the theme once". The "asserts nothing fails the run" rule
      covers all three suites, citing `requireAssertions`.
    - The theme `CLAUDE.md` → Local commands (`npm test`). The file is at its
      60-line limit, so one wrapped line elsewhere is reflowed and no content
      is removed.
  - **Touches shared code — may affect other features:** the theme's
    `package.json`. Consumers: the Vite build of `core` and `search-stats`
    (and later `city-popup`), which must still build, as checked above.

  → `chore(theme): add Vitest for the theme's JS unit tests`

- [x] **3. The gate's sixth stage.** Changes to `bin/check.sh`:
  - The header comment gets `6. Vitest (unit tests) in the dovira theme`, and
    the line "Needs PHP >= 8.3, Composer, Node and npm on PATH".
  - Right after the PHP version check, before any install or stage:
    `command -v node` / `command -v npm`. If either is missing, print
    `check: node and npm are required (theme JS tests) — …` to stderr and
    `exit 1`.
  - After the two Composer install blocks: when `${THEME}/node_modules` is
    missing, `echo "==> npm install (theme dovira)"` and
    `( cd "${THEME}" && npm install --no-audit --no-fund )`.
  - **(Question 1, A)** Right after that, check that Vite loads on this
    platform: `( cd "${THEME}" && node --input-type=module -e "await
    import('vite')" )`. If it fails, stop before stage 1 and say that the
    theme's `node_modules/` was installed on another OS, so the gate must run
    there or reinstall it on this one. Rollup's own message would tell you to
    delete `node_modules/`, which from DDEV would break the host's build.
  - After `==> PHPUnit (theme dovira)`: `echo "==> Vitest (theme dovira)"` and
    `( cd "${THEME}" && npm test )`, then the existing `==> check: all green`.
  - Runs:
    - on the host, the six `==>` stage lines, ending with the Vitest summary
      and `check: all green`, exit 0;
    - with the smoke assertion broken, the gate stops at stage 6 with a
      non-zero exit (restored before the commit);
    - with a `PATH` holding `php` and `composer` but no `node`, the
      node/npm message and exit 1;
    - `ddev exec bash bin/check.sh` gives the platform message before
      stage 1 and a non-zero exit.
  - Docs in the same commit:
    - `docs/TECH-STACK.md` → Check command: six stages; Node and npm
      required; `npm install` in the theme when `node_modules/` is missing;
      the platform check. `ddev exec bash bin/check.sh` passes only with a
      `node_modules/` installed inside the container, which the host's
      `npm start` / `npm run build` cannot share, so in practice the gate runs
      on the host.
    - `docs/TESTING.md` → How to run: the gate order with stage 6, and the same
      Node/npm and DDEV lines.
    - Root `CLAUDE.md` → Commands: the `bin/check.sh` comment gets
      `+ Vitest (theme)`.
    - `docs/ARCHITECTURE.md` → Modules, row "Project gate" (see Checks → Docs
      vs reality).
  - **Touches shared code — may affect other features:** `bin/check.sh` gates
    every commit of `core`, `ga-telegram-bridge`, `search-stats` and
    `city-popup`.

  → `chore: run the theme's Vitest suite as the gate's sixth stage`

### Files to create/change
- create `wp-content/themes/dovira/vitest.config.js`
- create `wp-content/themes/dovira/tests/js/smoke.test.js`
- change `wp-content/themes/dovira/package.json` (script `test`, devDependency `vitest`)
- change `bin/check.sh`
- docs: `docs/TECH-STACK.md`, `docs/TESTING.md`, `docs/ARCHITECTURE.md`,
  root `CLAUDE.md`, `wp-content/themes/dovira/CLAUDE.md`
- not committed: `node_modules/`, `package-lock.json` (both gitignored)

### Tests to write
- `tests/js/smoke.test.js` imports `{ toggleCities }` from
  `@scripts/modules/toggle-cities.js` and asserts
  `typeof toggleCities === 'function'`.
  - That module is `core`'s and stable. It has no top-level DOM access, so it
    loads in the `node` environment.
  - The test proves three things: the alias resolves, the `include` pattern
    finds the test, and the environment is `node`.
  - It sits at `tests/js/` root, not in a feature folder, because it tests the
    gate's plumbing and not a feature's logic.
- The gate itself: `bin/check.sh` exits 0 and prints six stages. With the smoke
  assertion broken, it exits non-zero at stage 6 (task 3's runs).
- Test-critical zones of root `CLAUDE.md`: none touched (form pipelines, price
  grouping, `dovira/v1` routes, `wp dovira translate*`).

### Docs to update
- `docs/TECH-STACK.md` → Stack (Vitest row, installed version), Check command
  (six stages, Node/npm, install-when-missing, platform check, DDEV note),
  Approved dependencies log (installed version)
- `docs/TESTING.md` → Levels (Unit (theme JS)), How to run (`npm test`, gate
  order, one-time `npm install`, DDEV note), the asserts-nothing rule (three suites)
- `wp-content/themes/dovira/CLAUDE.md` → Local commands (`npm test`), ≤60 lines
- root `CLAUDE.md` → Commands (the gate's stage list)
- `docs/ARCHITECTURE.md` → Modules, row "Project gate" (not in the step's list;
  see Docs vs reality)

### Checks
- ANTI-PATTERNS / CONVENTIONS: none violated.
  - Vitest is a `package.json` dev dependency, not a global install.
  - Nothing goes into the theme's `composer.json`.
  - `node_modules/` is gitignored and never deploys.
  - No production URL literal.
  - CONVENTIONS (Vitest, `node`, no DOM library, `tests/js/{feature}/`): the
    smoke test sits at `tests/js/` root because it is gate plumbing, as the
    step names it; no DOM library is added.
- Docs vs reality: mismatch, each resolved or routed:
  - **DDEV cannot run stage 6 with the host's `node_modules/`.** That
    directory holds only `@rollup/rollup-darwin-arm64` and
    `@esbuild/darwin-arm64`. In the DDEV web container (Linux aarch64, Node
    24.20.0, npm 11.19.0), `import('vite')` fails with "Cannot find module
    @rollup/rollup-linux-arm64-gnu". The step's check "`ddev exec bash
    bin/check.sh` passes too" therefore fails as written → Question 1. The
    DECISIONS consequence "(the DDEV web container has them)" is true of Node
    and npm themselves. DECISIONS is left unchanged; TECH-STACK → Check command
    records the limit.
  - The Polylang strings `kharkiv` / `kyiv` are untranslated locally. The
    city names are the `Dovira: Cities` strings → Step 2's plan (see the audit
    above).
  - `docs/ARCHITECTURE.md` → Modules, row "Project gate" lists the gate's
    stages, but the step's Docs to update leaves it out. Core rule 5 settles
    it: the row is updated in task 3.
  - `docs/TESTING.md` says a test that asserts nothing fails the run "in both
    suites". The new suite follows that rule through
    `expect.requireAssertions` (task 2).
  - The gate installs npm packages only when `node_modules/` is missing
    (DECISIONS wording, kept). A `node_modules/` that exists but lacks vitest
    makes stage 6 fail with `vitest: command not found`. TESTING → How to run
    says to run `npm install` once. This is the same limit TESTING already
    documents for `tests/vendor/`.
  - Registry: `^3.2` resolves to 3.2.7 (`V3` dist-tag; latest is 5.0.1 and
    `V4` 4.1.11, and the approved range stays `^3.2`).
    - Engines `^18 || ^20 || >=22`: the host runs 26.8.2 and DDEV 24.20.0,
      both fine.
    - Its `vite` range `^5 || ^6 || ^7` is met by the installed 5.4.19.
  - Everything else matches: the pages and slugs, the `service` base,
    `page_for_posts` = 0, and `.gitignore`.
- Design: n/a (no screen in this step).
- Check command: `bin/check.sh` (docs/TECH-STACK.md → Check command). It exists
  and is green on the host today (five stages); this step adds stage 6.
- Not locally verifiable: n/a. The gate is local. The dev FTP deploy syncs
  `vitest.config.js` and `tests/js/` as it already syncs `tests/`, and both are
  inert on a server.

### Questions / ambiguities
**1. `ddev exec bash bin/check.sh` cannot pass stage 6 while `node_modules/`
is installed on the host.** The host and the container share one
`node_modules/`, and Rollup/esbuild ship one native binary per OS. The
darwin binaries the host needs for `npm start` / `npm run build` are the ones
the Linux container cannot load (verified above).
- **A (recommended).** Stage 6 runs where `node_modules/` was installed, which
  in practice is the host.
  - Task 3 adds the platform check before stage 1, so the DDEV run stops early
    with a message that names the cause. Without it, the run dies at stage 6
    with Rollup's advice to delete `node_modules/`, which would break the
    host's build.
  - TECH-STACK and TESTING record the limit.
  - The step's check "`ddev exec bash bin/check.sh` passes too" becomes
    "stops before stage 1 with the platform message, non-zero exit".
  - This adds one check to `bin/check.sh`, which the step does not name.
- **B.** The gate runs `npm install` whenever Vite does not load, and not only
  when `node_modules/` is missing, so the DDEV run passes.
  - Each switch between host and container reinstalls the shared
    `node_modules/` for the other OS. That breaks a running `npm start` and the
    next host `npm run build` until someone reinstalls on the host.
  - The tasks change the install condition and keep the check "DDEV passes".
    The docs describe the flip.
  - Not recommended: the gate would silently rewrite the developer's working
    toolchain.
- (Skipping stage 6 in the container was considered and rejected: a commit
  gated there would pass without the JS tests.)

Resolved: approved as recommended — A (stage 6 runs where `node_modules/`
was installed, in practice the host; the gate checks that Vite loads before
stage 1 and stops with a message naming the cause; TECH-STACK and TESTING
record the limit).
