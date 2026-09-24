# Step plans — city-popup, Sprint 1

<!-- Written by /plan-step, one section per step. Approval of a section
     authorizes that step only. -->

## Plan — Sprint 1, Step 1: Delta-audit and Vitest in the gate   (status: closed)

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

## Plan — Sprint 1, Step 2: Feature bootstrap and the dialog on blog pages   (status: closed)

### Branch
`city-popup/sprint-1-city-question` ← `master`
(root `CLAUDE.md` → git model: simple. The branch is created again from
`master`, which carries Step 1 (`e006de92`), and deleted at the close.)

### Tasks (ordered)
Written for the recommended answer to Question 1 (B). Under answer A, task 3
changes as that question describes.

- [x] **1. `Sites`: the city, both origins and the cookie domain from the site URL.**
  - `inc/features/city-popup/Sites.php` defines `final class Sites` in namespace
    `dovira\CityPopup`.
    - It is pure: PHP's `parse_url()`, no WordPress function.
    - It is built from the URL it is given: `new Sites( home_url() )` at the
      call site.
    - Constants: `KHARKIV = 'kharkiv'`, `KYIV = 'kyiv'`.
  - Methods:
    - `city()`: `KYIV` when the URL contains `kyiv`, otherwise `KHARKIV`. This
      is the switcher's own test (DECISIONS "The other install's host and the
      cookie domain are derived from the site URL").
    - `kharkiv_origin()`: scheme + the host without a leading `kyiv.` + the
      port, if any.
    - `kyiv_origin()`: scheme + `kyiv.` + the Kharkiv host + the port.
    - `cookie_domain()`: the Kharkiv host, with no scheme and no port.
    - The host is lowercased, and the URL's path never reaches an origin.
  - Test: `tests/Unit/CityPopup/SitesTest.php` (see Tests).
  - The class is not loaded by WordPress yet (task 4), so this commit changes
    nothing on the site.

  → `feat(city-popup): derive the city, both origins and the cookie domain from the site URL`

- [x] **2. `Pages`: the blog and the target URLs.**
  - `inc/features/city-popup/Pages.php` defines `final class Pages` in
    namespace `dovira\CityPopup`.
    - Constants: `BLOG_SLUG = 'news'`, `SERVICES_SLUG = 'services'`,
      `CONTACTS_SLUG = 'contacts'`.
    - The constructor is `__construct( ?bool $polylang = null )`, with `null`
      meaning `function_exists( 'pll_get_post' )`.
    - Tests pass `false` for "without Polylang". Brain\Monkey declares a stubbed
      function for the rest of the PHPUnit run (`FunctionStub::__construct`
      `eval`s it), so `function_exists()` cannot be false in a later test. This
      is the one way to test that case in-process.
  - A page "in the current language" is `get_page_by_path( $slug )` and then:
    - with Polylang, `pll_get_post( $id )` (current language), where `0` or
      `false` means none;
    - without Polylang, the page itself.
  - `is_blog()`:
    - `is_singular( 'post' )`; or
    - `is_page( $id )` for the blog page in the current language.
    - A missing page or translation is not blog. The code never calls
      `is_page()` with an empty id, which WordPress treats as "any page".
  - `targets()` returns `array<string, string>`:
    - `services` and `contacts`: `get_permalink()` of the page in the current
      language;
    - `service`: the base URL of the `service` type in the current language.
      That is the slug `get_post_type_object( 'service' )->rewrite['slug']`
      appended to `pll_home_url()` (or `home_url( '/' )` without Polylang),
      with a trailing slash. Locally that gives `https://dovira.ddev.site/services/`
      and `https://dovira.ddev.site/ru/services/`.
    - A missing page, translation, type or rewrite slug leaves its key out.
    - Keys rather than a list, so the printed JSON says what each URL is. The
      JS in Step 3 reads the values.
  - Test: `tests/Unit/CityPopup/PagesTest.php`.

  → `feat(city-popup): find the blog page and the target URLs in the current language`

- [x] **3. The dialog's strings and `Dialog`.**
  - `inc/utils/polylang-string-translations.php` gets four entries appended to
    `$strings`, each with its Ukrainian text as the key:
    - `'Яке місто вас цікавить?'` and `'Закрити'`, as the step says;
    - `'Харків'` and `'Київ'` (Question 1, B).

    An install that has not translated them yet shows Ukrainian.
  - `inc/features/city-popup/Dialog.php` defines `final class Dialog` in
    namespace `dovira\CityPopup`:
    - `public const DEFAULT_DAYS = 90;` is the only `90` in the feature.
    - `__construct( Sites $sites, Pages $pages )`.
    - `days(): int` is `(int) apply_filters( 'dovira_city_popup_days',
      self::DEFAULT_DAYS )`. A value below 1 gives `DEFAULT_DAYS`.
    - `render(): void` prints nothing unless `$pages->is_blog()`. Otherwise it
      prints one `<dialog>`, described below.
    - `static print_on_blog(): void` is the `wp_footer` callback:
      `( new self( new Sites( home_url() ), new Pages() ) )->render()`.
  - The `<dialog>` (the markup is internal to the feature, FEATURE.md →
    Interfaces). Strings go through `dovira_translate_string()` (core's
    null-safe `pll__()`), text through `esc_html()`, and every attribute
    through `esc_attr()`.
    ```html
    <dialog class="city-popup" aria-labelledby="city-popup-title" data-city-popup
            data-current-city="kharkiv"
            data-kharkiv-origin="https://dovira.ddev.site" data-kyiv-origin="https://kyiv.dovira.ddev.site"
            data-cookie-domain="dovira.ddev.site" data-days="90"
            data-targets="{…wp_json_encode( targets, JSON_UNESCAPED_SLASHES )…}">
      <h2 class="city-popup__title" id="city-popup-title">Яке місто вас цікавить?</h2>
      <div class="city-popup__cities">
        <button type="button" class="button button--black city-popup__city" data-city-popup-choice="kharkiv" autofocus>Харків</button>
        <button type="button" class="button button--black city-popup__city" data-city-popup-choice="kyiv">Київ</button>
      </div>
      <button type="button" class="city-popup__close" data-city-popup-close aria-label="Закрити"><span aria-hidden="true">×</span></button>
    </dialog>
    ```
    - It has no `open` attribute and no styles. The browser's own `dialog`
      rule keeps it hidden, and no theme CSS targets `dialog` (checked).
    - The city buttons use `data-city-popup-choice`, not `data-city`, which
      Step 3 gives to links (`a[data-city]`). The `city-toggle` buttons of the
      employees and vacancies blocks already carry `data-city`.
    - `autofocus` on «Харків» is FEATURE.md → UI's state "open, with focus on
      the first city button". The × comes after the buttons in the DOM, and
      Step 3 places it top-right.
    - `.button` / `--black` are FEATURE.md → UI's reused component; Step 3
      styles the rest.
  - Local ru values, for Languages → Translations, go in with `ddev wp eval`
    through Polylang's `PLL_MO` (`import_from_db()` / `add_entry()` /
    `export_to_db()`), as `inc/cli/TranslateOptionsCommand.php` writes string
    translations:
    - `Яке місто вас цікавить?` → `Какой город вас интересует?`;
    - `Закрити` → `Закрыть`.

    `Харків` → `Харьков` and `Київ` → `Киев` already exist, shared with the
    `Dovira: Cities` strings. This is local DB content, not a file, so it is
    not committed.
  - Test: `tests/Unit/CityPopup/DialogTest.php`.
  - Docs in the same commit: `docs/features/city-popup/FEATURE.md` → Fit into
    the host, the strings line (Question 1, B).
  - **Touches shared code — may affect other features:**
    `inc/utils/polylang-string-translations.php`. Consumers: every template
    that prints a registered string (`core`). Entries are only appended, so the
    existing registrations stay as they are.

  → `feat(city-popup): the city question dialog and its strings`

- [x] **4. Wire the feature.**
  - `inc/features/city-popup/bootstrap.php` requires `Sites.php`, `Pages.php`
    and `Dialog.php` and adds `add_action( 'wp_footer', [ Dialog::class,
    'print_on_blog' ] )`.
  - `functions.php` gets one block after the `search-stats` one (`:58–61`) and
    before the `WP_CLI` block, in the same comment style:
    `/** Feature: city-popup */` and
    `require_once TEMPLATE_DIR . '/inc/features/city-popup/bootstrap.php';`.
  - Check locally with curl against `dovira.ddev.site`:
    - `/news/` and one article carry one `<dialog`, whose origins, cookie
      domain and targets are the Step's values;
    - `/ru/blog/` and one ru article carry the ru strings and `/ru/uslugi/`,
      `/ru/kontakty/`, `/ru/services/`;
    - `/`, `/services/` and one service carry none.
  - Docs in the same commit: `docs/ARCHITECTURE.md` → Modules, a new row
    "City popup (theme feature)" (`Sites`, `Pages`, `Dialog`, the hook, "must
    never": read `dovira_city*` cookies in PHP, print a URL literal, print the
    dialog off the blog). The Bootstrap row becomes "(today `search-stats`,
    `city-popup`)".
  - **Touches shared code — may affect other features:** `functions.php`.
    Consumers: every theme feature (`core`, `search-stats`). One
    `require_once` is added, and it hooks only `wp_footer`.

  → `feat(city-popup): load the feature and print the dialog on blog pages`

### Files to create/change
- create `wp-content/themes/dovira/inc/features/city-popup/{bootstrap,Sites,Pages,Dialog}.php`
- create `wp-content/themes/dovira/tests/Unit/CityPopup/{Sites,Pages,Dialog}Test.php`
- change `wp-content/themes/dovira/functions.php` (shared)
- change `wp-content/themes/dovira/inc/utils/polylang-string-translations.php` (shared)
- docs: `docs/ARCHITECTURE.md`, `docs/features/city-popup/FEATURE.md`
- local DB only: the ru values of the two new strings

### Tests to write
Every test requires the class under test by its path
(`dirname( __DIR__, 3 ) . '/inc/features/city-popup/…'`) and never
`bootstrap.php` (docs/TESTING.md → Rules).
- **`SitesTest`** has one data provider row per URL, and each row asserts the
  city, both origins and the cookie domain:
  - `https://dovira.vet` → kharkiv, `https://dovira.vet`,
    `https://kyiv.dovira.vet`, `dovira.vet`;
  - `https://kyiv.dovira.vet` → kyiv, with the same two origins and domain;
  - `https://dev.dovira.vet` → kharkiv, `https://kyiv.dev.dovira.vet`,
    `dev.dovira.vet`;
  - `https://dovira.ddev.site` → kharkiv, `https://kyiv.dovira.ddev.site`,
    `dovira.ddev.site`;
  - `https://kyiv.dovira.ddev.site/some/path/` → kyiv, with no path in either
    origin;
  - `https://dovira.ddev.site:8443` → the port stays in both origins and is not
    in the cookie domain;
  - `http://Dovira.DDEV.site` → the scheme is kept and the host lowercased.
- **`PagesTest`** uses Brain\Monkey stubs: `get_page_by_path`, `pll_get_post`
  (uk → ru map), `is_page`, `is_singular`, `get_permalink`,
  `get_post_type_object`, `pll_home_url`, `home_url`.
  - `is_blog()`:
    - the `news` page on a uk request and its ru translation on a ru request →
      true;
    - a single `post` → true;
    - the front page and a `service` → false;
    - the `news` page missing → false, and `is_page()` is never called with an
      empty id;
    - without Polylang (`new Pages( false )`), the `news` page itself → true.
  - `targets()`:
    - uk → `/services/`, `/contacts/`, `/services/`;
    - ru → `/ru/uslugi/`, `/ru/kontakty/`, `/ru/services/`;
    - the contacts page missing → no `contacts` key;
    - a page with no translation in the current language → its key left out;
    - no `service` type → no `service` key;
    - without Polylang → the pages themselves and `home_url( '/' )` +
      `services/`.
- **`DialogTest`** covers `render()` through `Sites` built from a URL and a
  `Pages` stub state:
  - off the blog → empty output;
  - on the blog, exactly one `<dialog`, with no `open` attribute (hidden until
    Step 3 opens it);
  - the title, «Харків» before «Київ», `autofocus` on «Харків» only, and the ×
    button with `aria-label`;
  - strings pass through `dovira_translate_string()`, and a ru map gives the ru
    strings;
  - attributes are escaped. A permalink stub with `&` and `"` comes back intact
    after the attribute is HTML-decoded, and `data-targets` `json_decode`s to
    the `targets()` array;
  - the days filter: `expectApplied( 'dovira_city_popup_days' )` with `90`.
    Returned `30` → `data-days="30"`; `0`, `-5` and `'abc'` → `90`; `'45'` →
    `45`;
  - `print_on_blog()` with `home_url()` stubbed to `https://kyiv.dovira.vet` →
    `data-current-city="kyiv"`.
- Test-critical zones of root `CLAUDE.md`: none touched.
- Gate after the step: `php -l` 134 → 141 files, and the theme suite gains the
  three classes. Vitest is unchanged (Step 3 brings the JS).

### Docs to update
- `docs/ARCHITECTURE.md` → Modules: the feature's row and the Bootstrap row (task 4)
- `docs/features/city-popup/FEATURE.md` → Fit into the host, strings line (task 3, Question 1 B)

### Checks
- ANTI-PATTERNS / CONVENTIONS: none violated.
  - "Decide which city site this is only from the site URL": `Sites` decides
    from `home_url()`, as DECISIONS "The other install's host and the cookie
    domain are derived from the site URL" sets ("one PHP class computes
    them"), and no template branches on it.
  - There is no URL literal: the origins are derived.
  - No PHP reads `dovira_city*`: nothing reads cookies at all.
  - No block, no field group, no JS.
  - The theme `CLAUDE.md` rules hold: `dovira\` namespace; one registration
    line in `functions.php`; strings through the registered list.
- Docs vs reality: mismatch.
  - **The Polylang strings `kharkiv` / `kyiv` hold no city names**
    (`pll_translate_string()` returns the key in uk and ru; Step 1 audit), but
    the step and FEATURE.md reuse them → Question 1.
  - Root invariant 4 names `get_site_url()`, while DECISIONS and the step name
    `home_url()`. DECISIONS takes precedence, and both give
    `https://dovira.ddev.site` locally.
  - The step says "`targets()` … the `service` base URL"; FEATURE.md says
    "the rewrite base of the `service` post type". Reality:
    `rewrite.slug = services`, `has_archive = false`, and
    `get_post_type_archive_link( 'service' )` is `false`. The base is
    therefore built from the slug and `pll_home_url()` (task 2), which
    matches the step's `/ru/services/`.
  - `pll_home_url()` gives `https://dovira.ddev.site/` (uk, default language
    hidden) and `…/ru/`. There are 11 uk and 11 ru posts, and ru articles are
    at `/ru/news/{slug}/`. Everything else matches the Step 1 audit.
- Design: matches «City question» in FEATURE.md → UI for its markup. There is
  no design export (DECISIONS "No UI design phase"). The strings are
  FEATURE.md's word for word, and the layout, backdrop and visibility are
  Step 3.
- Check command: `bin/check.sh` (docs/TECH-STACK.md → Check command). Six
  stages, green on `master` after Step 1.
- Not locally verifiable: the ru values of the two new strings on each
  production. They are typed in Languages → Translations after the
  developer's next hand deploy from `master` (Kharkiv) and the merge into
  `kyiv` (Kyiv), and verified by that deploy.

### Questions / ambiguities
**1. The city buttons: reuse `kharkiv` / `kyiv`, or register `Харків` / `Київ`?**
The step (and FEATURE.md → Fit into the host) says the buttons "reuse the
registered `kharkiv` / `kyiv`" strings. That assumed they hold the city names,
which Step 1 was to confirm. They do not: both return `kharkiv` / `kyiv` in uk
and ru, and nothing prints them. The names exist only as the `Dovira: Cities`
strings, keyed by the Ukrainian term names (`Харків` → ru `Харьков`, `Київ` →
ru `Киев`).
- **A.** Keep `pll__( 'kharkiv' )` / `pll__( 'kyiv' )`.
  - Task 3 also writes their uk values (`Харків`, `Київ`) and ru values
    (`Харьков`, `Киев`) locally.
  - Each production needs four more values typed by hand after its deploy.
  - Until then, the buttons read "kharkiv" / "kyiv" in Latin letters. That is
    the failure the step itself avoids for the title and the × by keying them
    in Ukrainian.
  - FEATURE.md stays as it is.
- **B (recommended).** Register `'Харків'` and `'Київ'` in the theme's
  `$strings` list, keyed in Ukrainian like the title and the ×.
  - They are already translated on every install whose city names are, since
    Polylang keeps one translation per source text and they share it with the
    `Dovira: Cities` strings.
  - An untranslated install shows Ukrainian.
  - Registering them in the theme's list keeps them in Languages →
    Translations even if an editor renames a `service-city` term.
  - `kharkiv` / `kyiv` stay registered and unused. Removing them is not this
    step's.
  - FEATURE.md → Fit into the host changes its strings line (task 3).

Resolved: approved as recommended — B (register `'Харків'` / `'Київ'` in the
theme's `$strings` list, keyed in Ukrainian; their ru values are shared with
the `Dovira: Cities` strings; FEATURE.md → Fit into the host changes its
strings line).

## Plan — Sprint 1, Step 3: The city question in the browser   (status: closed)

### Branch
`city-popup/sprint-1-city-question` ← `master`
(root `CLAUDE.md` → git model: simple. The branch is created again from
`master`, which carries Steps 1–2 (`f8543bce`), and deleted at the close.)

### Tasks (ordered)
Written for the recommended answer to Question 1 (A). Under answer B, task 2
drops out and task 3 changes as that question describes.

- [x] **1. The pure module `source/scripts/features/city-popup/logic.js`.** It
  has no DOM access, and its named exports are in the style of the theme's
  modules (arrow functions, one `export {…}`). Constants: `CITY_COOKIE =
  'dovira_city'`, `DISMISS_COOKIE = 'dovira_city_dismissed'`, `MARKER =
  'city-popup'`, and `DAY_SECONDS = 86400`.
  - `isTarget( href, currentOrigin, targets )` (`targets` is the list of
    target URLs):
    - `href` resolves against `currentOrigin` (`new URL`); an invalid URL →
      `false`;
    - the origin must equal `currentOrigin`. `tel:`, `mailto:` and another
      host → `false`;
    - its path, with a trailing slash added when missing, starts with the
      slash-ended path of a target on the same origin.

    The added slash makes `/contacts` count like `/contacts/`, the address
    WordPress redirects it to, so an author's link without the slash is asked
    about too.
  - `isPlainClick( click, linkTarget )`: `button === 0`, none of
    `ctrlKey` / `metaKey` / `shiftKey` / `altKey`, and `linkTarget` empty or
    `_self`.
  - `crossUrl( href, origin )`:
    - it is `origin` + the path + the query + the fragment of `href`;
    - `city-popup=1` is appended as `?…` or `&…`, with the existing query
      kept byte for byte. It is not rebuilt through `URLSearchParams`, which
      would re-encode it;
    - a query that already has `city-popup=1` is left as it is.
  - `decide( cookies, currentCity )`:
    - a valid saved city → `go-current` when it is `currentCity`, else
      `go-city`;
    - otherwise `dismissed === '1'` → `go-current`;
    - otherwise → `ask`. An unknown saved value counts as none.
  - Cookies:
    - `cityCookie( city, days, domain, secure )` →
      `dovira_city={city}; Max-Age={days×86400}; Domain={domain}; Path=/;
      SameSite=Lax`, plus `; Secure` when `secure`;
    - `dismissCookie( domain, secure )` → the same with `dovira_city_dismissed=1`
      and no `Max-Age`;
    - `readCookies( string )` → `{name: value}`, splitting on `;`, trimming,
      keeping any `=` inside a value; `''` → `{}`;
    - `cityFromAttr( value )` → `'kharkiv'` | `'kyiv'` | `null`.
  - `isOutside( rect, x, y )`: whether a click point lies outside the dialog's
    box. A click on the backdrop and a click on the dialog's own padding both
    have the `<dialog>` as their target, so "a click on the backdrop" needs
    the point. This is the step's backdrop task, kept pure so Vitest covers
    it.
  - Test: `tests/js/city-popup/logic.test.js` (see Tests).
  - Nothing imports the module yet, so this changes nothing on the site.

  → `feat(city-popup): the pure decisions of the city question`

- [x] **2. (Question 1, A) The cookie settings on every page.**
  - `Dialog` (`inc/features/city-popup/Dialog.php`) gets `render_config()`.
    On any page it prints
    `<div hidden data-city-popup-config data-cookie-domain="…" data-days="…"></div>`
    (escaped; `days()` as today).
  - The static `print_config()` is hooked on `wp_footer` in `bootstrap.php`
    next to `print_on_blog()`.
  - The blog `<dialog>` drops `data-cookie-domain` and `data-days`, so each
    value is printed once. It keeps the city, both origins and the targets.
  - `DialogTest` changes with it:
    - the "one closed dialog with its data" test no longer expects those two
      attributes;
    - the days data provider asserts `data-days` on the config element;
    - new tests: the config is printed off the blog too, with escaped values
      and nothing else; and `print_config()` reads `home_url()`.
  - Docs in the same commit: `docs/ARCHITECTURE.md` → Modules, the City popup
    row (the config element).
  - Not shared code: only the feature's files change.

  → `feat(city-popup): print the cookie settings on every page`

- [x] **3. The DOM glue `source/scripts/features/city-popup/city-popup.js` and its import.**
  - `initCityPopup()` does nothing without `[data-city-popup-config]`.
    Otherwise it reads:
    - the domain and the days from that element;
    - `secure` from `location.protocol === 'https:'`;
    - when `dialog[data-city-popup]` exists: the current city, both origins
      and `Object.values( JSON.parse( targets ) )`.
  - One delegated `click` listener on `document`:
    - The link is `event.target.closest( 'a[href]' )`. The listener returns
      when there is none, when `event.defaultPrevented` (another handler
      owns the click), or when `!isPlainClick( event, link.target )`. That is
      FEATURE.md's invariant: the feature does not touch a modified click,
      and that includes the switcher.
    - `a[data-city]` with `cityFromAttr()` valid → write `cityCookie()` and
      return. The browser follows the link, on every page.
    - With no dialog, or when `!isTarget( link.href, the current city's
      origin, targets )` → return.
    - `decide( readCookies( document.cookie ), currentCity )`:
      - `go-current` → return, and the browser follows the link as it is;
      - `go-city` → `preventDefault()` and
        `location.assign( crossUrl( link.href, origin of the saved city ) )`;
      - `ask` → `preventDefault()`, remember the link's `href`, and
        `dialog.showModal()`. Focus lands on «Харків» through `autofocus`.
  - Dialog handlers:
    - a `[data-city-popup-choice]` button writes `cityCookie( choice )`, then
      goes to the remembered `href` as it is (chosen = current) or to its
      `crossUrl()` (other city);
    - the `[data-city-popup-close]` × → dismiss;
    - `cancel` (Esc) → dismiss;
    - a `click` whose target is the `<dialog>` and whose point is outside
      its box → dismiss.
    - Dismiss writes `dismissCookie()` and follows the remembered `href` as
      it is.
    - Every path calls `dialog.close()` before `location.assign()`, so Back
      (bfcache) does not bring the page back with the question open.
  - `source/scripts/app.js`, after `recordSiteSearch();`, gets the feature's
    own block inside the IIFE:
    `try { const {initCityPopup} = await import('@scripts/features/city-popup/city-popup'); initCityPopup(); } catch (error) { console.error(error); }`.
    - Step 1's audit found that one throw in `app.js` stops everything after
      it, so a failure here is caught and stops nothing.
    - It sits last, so it never delays the modules above.
  - Docs in the same commit, `docs/ARCHITECTURE.md`:
    - Modules: the City popup row gets its JS part;
    - Data flows: "Blog click → city question" as built. That covers the
      config element, the modifier rule for `a[data-city]`, the
      `defaultPrevented` guard, the backdrop point, and closing before
      navigating.
  - **Touches shared code — may affect other features:**
    `source/scripts/app.js`. Consumers: every front-end module (`core`, and
    `search-stats`' `recordSiteSearch`). The block is appended, and the
    existing imports and calls are unchanged.

  → `feat(city-popup): open the city question on a blog click and save the choice`

- [x] **4. Screen «City question» in `source/styles/features/city-popup/city-popup.css`.**
  - Mobile first, from the `colors.css` tokens and `.button`:
    - `.city-popup`:
      - `width: calc(100% - 32px)` (the 16 px gutter), `max-width: 400px`;
      - white (`--color-white`), text `--color-black`, `border: none`, radius
        5 px like `.button`;
      - padding with room for the ×, `position: relative` for it.

      The UA's modal `dialog` rules keep it centred.
    - `.city-popup::backdrop`: `rgba(27, 27, 27, 0.6)`, which is
      `--color-black` at 60 %. It is written as a value with a comment because
      `::backdrop` does not see `:root` custom properties in browsers before
      2024.
    - `.city-popup__title`: Inter, 400, centred, at the heading scale's h4
      size (23.6 px). The theme's global `h2` rule would otherwise make it
      39.1 px.
    - `.city-popup__cities`: a column with a 12 px gap; a row from
      `@media (min-width: 768px)`, the theme's first breakpoint. Two
      `.button` (min-width 152 px) plus the gap do not fit a 375 px dialog.
    - `.city-popup__city`: full width in the column, `flex: 1` in the row.
    - `.city-popup__close`: absolute top-right, a 32 px hit area, no border
      or background, `--color-gray-dark`; `--color-black` on hover within
      `@media (hover: hover)`, as the theme does.
  - `source/styles/app.css` gets `/* Features */` and
    `@import "@styles/features/city-popup/city-popup.css";` after the blocks.
  - Docs in the same commit, `docs/DESIGN.md`:
    - Components: a row "City question dialog (`.city-popup`)", with states
      closed / open (modal, backdrop), stacked / side by side, in
      `features/city-popup/city-popup.css` and `Dialog.php`;
    - Screens: «City question», feature `city-popup`, entry "a click on a
      target link on a blog page", design ref FEATURE.md → UI, states uk / ru
      and 375 px / desktop.
  - **Touches shared code — may affect other features:**
    `source/styles/app.css`. Consumers: every page's CSS (`core`). One import
    is appended; the selectors are all `.city-popup*`.

  → `feat(city-popup): style the city question dialog`

- [x] **5. Build.**
  - `npm run build` in the theme. `.env` is already `production`.
  - Commit `assets/`, with the new hashed `main` JS/CSS, the new chunk(s) and
    `.vite/manifest.json`.
  - Before the commit, check locally: on a local article (with the built
    assets), «Послуги» opens the dialog.

  → `chore(theme): rebuild assets with the city question`

### Files to create/change
- create `wp-content/themes/dovira/source/scripts/features/city-popup/{logic,city-popup}.js`
- create `wp-content/themes/dovira/source/styles/features/city-popup/city-popup.css`
- create `wp-content/themes/dovira/tests/js/city-popup/logic.test.js`
- change `wp-content/themes/dovira/inc/features/city-popup/{Dialog,bootstrap}.php`, `tests/Unit/CityPopup/DialogTest.php` (task 2)
- change `wp-content/themes/dovira/source/scripts/app.js`, `source/styles/app.css` (shared)
- rebuild `wp-content/themes/dovira/assets/**` (committed)
- docs: `docs/ARCHITECTURE.md`, `docs/DESIGN.md`

### Tests to write
- **`tests/js/city-popup/logic.test.js`** (Vitest, `node`, imports
  `@scripts/features/city-popup/logic.js`):
  - `isTarget`:
    - the uk list (`/services/`, `/contacts/`, the service base `/services/`)
      and the ru list (`/ru/uslugi/`, `/ru/kontakty/`, `/ru/services/`);
    - targets: the services list, a service (`/services/diagnostics/`), a
      sub-service (`/services/surgery/sterilization/`), contacts, and
      `/contacts` without a slash;
    - not targets: `/news/`, `/about/`, `/services-old/`, the same path on
      another host, `tel:+380…`, `mailto:…`, a bare `#hash`, an invalid href;
    - a relative `/contacts/` is resolved against the current origin.
  - `isPlainClick`:
    - a plain primary click → true;
    - each of ctrl / meta / shift / alt → false;
    - `button: 1` (middle) → false;
    - `target="_blank"` → false; `_self` and `''` → true.
  - `crossUrl`:
    - path, query and fragment kept (`/services/x/?a=1#prices`);
    - the marker added once;
    - an existing query kept exactly (`?a=1&b=%20x`);
    - no query → `?city-popup=1`.
  - `decide`:
    - a saved `kyiv` on Kharkiv → `go-city`;
    - a saved `kharkiv` on Kharkiv → `go-current`;
    - a dismissal only → `go-current`;
    - saved and dismissed → the saved city wins;
    - nothing → `ask`;
    - an unknown value (`dovira_city=odesa`) → `ask`.
  - Cookie strings:
    - `Max-Age` = days × 86400 (90 → 7776000, 30 → 2592000); `Domain`,
      `Path=/` and `SameSite=Lax` are present;
    - `Secure` only when `secure`;
    - the session cookie has no `Max-Age`;
    - `readCookies`: several cookies, spaces, a value holding `=`, `''`;
    - `cityFromAttr` accepts only `kharkiv` / `kyiv`: `Kyiv`, `''`,
      `undefined` and `kharkiv,kyiv` → `null`.
  - `isOutside`: a point inside the box, on its edge, and outside on each side.
- **`DialogTest`** (task 2):
  - the config element on and off the blog, with escaped values, and hidden;
  - `data-days` from the filter moves onto it (same provider: 30, 0, −5,
    'abc', '45');
  - the dialog no longer carries the two attributes;
  - `print_config()` on a Kyiv URL → `data-cookie-domain="dovira.vet"`.
- **The state the user sees after the interaction:** `decide()` tests pin what
  a click does (asks, goes to the city, or follows the link). The DOM result,
  the dialog open with focus on «Харків», is checked by hand. TECH-STACK →
  CONVENTIONS: no DOM library in Vitest, and the glue stays thin.
- Test-critical zones of root `CLAUDE.md`: none touched.

### Docs to update
- `docs/ARCHITECTURE.md` → Modules (the City popup row: config element in task 2, JS part in task 3) and Data flows ("Blog click → city question", as built, task 3)
- `docs/DESIGN.md` → Components (City question dialog) and Screens («City question», feature `city-popup`) (task 4)

### Checks
- ANTI-PATTERNS / CONVENTIONS: none violated.
  - The choice lives only in the two cookies, which JS writes and PHP never
    reads.
  - There is no URL literal: the origins and targets come from the markup
    and `location.protocol` gives `secure`.
  - `assets/` comes from `npm run build`, and the entry goes through
    `app.js`, which is already enqueued by `starter_theme_vite_asset()`.
  - CONVENTIONS: the pure logic is in `logic.js`, tested in
    `tests/js/city-popup/`, and the DOM glue is thin and checked by hand.
  - The theme `CLAUDE.md` rules hold: an ES module imported from `app.js`,
    one CSS file imported by `app.css` under `source/styles/features/{name}/`,
    tokens from `colors.css`, and no new visible string.
- Docs vs reality: mismatch, each resolved or routed:
  - **The cookie domain and days exist only on blog pages**, but `a[data-city]`
    must save "on every page". DECISIONS "The question is a native
    `<dialog>` printed only on blog pages…" says "pages other than the blog
    carry no dialog and no click listener", while DECISIONS "The header
    switcher saves the city it switches to" says the JS writes the cookie
    "on every page" → Question 1.
  - A click on the dialog's padding targets the `<dialog>` like a backdrop
    click does → `isOutside()` in `logic.js` (task 1).
  - The theme styles every `h2` at 39.1 px (`heading.css`) → the title sets
    its own size (task 4).
  - `::backdrop` does not inherit `:root` custom properties in pre-2024
    browsers → the backdrop colour is `--color-black`'s value at 60 %, with a
    comment (task 4).
  - `app.js` isolates no module (Step 1 audit) → the feature's block has its
    own `try` / `catch` (task 3).
  - Checked on `/news/`: the header's «Послуги» / «Контакти» are absolute
    links to the current host (`https://dovira.ddev.site/services/`,
    `/contacts/`). They match the printed targets.
  - `toggle-submenu.js` prevents default only on its toggle button, not on
    menu links. The `defaultPrevented` guard covers any handler that does.
- Design: this builds screen «City question» from FEATURE.md → UI. There is
  no design export (DECISIONS "No UI design phase"). No new token: the
  backdrop is `--color-black` at 60 % as a value. No new strings.
- Check command: `bin/check.sh` (docs/TECH-STACK.md → Check command). Six
  stages, green on `master` after Step 2.
- Not locally verifiable:
  - the move to the other install (`kyiv.dovira.ddev.site` does not resolve
    locally) → verified by the next hand deploy from `master` and the merge
    into `kyiv`, as the step says;
  - the ru strings on production depend on the Step 2 values typed after
    that deploy.

### Questions / ambiguities
**1. How do pages outside the blog learn the cookie domain and the days?**
The step wants `a[data-city]` to write `dovira_city` "on every page" (Step 4
puts `data-city` on the header switcher, which is on every page). The only
place those two values are printed is the blog `<dialog>` (Step 2). A PHP
source is needed: DECISIONS derives the domain in PHP from `home_url()` and
the days from the filter, and the JS knows no URL. Two DECISIONS entries also
disagree about a listener off the blog.
- **A (recommended).** Task 2 prints a hidden
  `<div data-city-popup-config data-cookie-domain data-days>` on every page
  from `Dialog`, hooked on `wp_footer`, and moves those two attributes off
  the dialog.
  - The glue attaches its one listener wherever the config element is. The
    question part runs only where the dialog is.
  - The "no click listener" wording in the dialog DECISIONS entry gives way to
    the switcher entry. `/close-step` records that in DECISIONS.
  - Cost: one PHP task and changes to a closed step's class and test
    (`Dialog`, `DialogTest`), inside the feature.
- **B.** Step 3 keeps to its file list: `a[data-city]` saves only where the
  dialog is (blog pages).
  - Step 4, whose Verification needs "on any page", then adds the config
    element in its own plan.
  - The step text's "on every page" is not met in Step 3.
  - Task 2 drops out, and task 3 reads the domain and days from the dialog.

Resolved: approved as recommended — A (a hidden `data-city-popup-config`
element with the cookie domain and the days on every page, printed by
`Dialog` on `wp_footer`; the two attributes move off the dialog; the switcher
DECISIONS entry prevails over the dialog entry's "no click listener", recorded
at the close).
