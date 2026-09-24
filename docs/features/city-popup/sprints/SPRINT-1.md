# SPRINT 1 — A blog reader chooses the city once (from 2026-09-23)

<!-- playbook: v1.25. Written by discovery (Phase C) together with every
     other sprint of the plan — never by Claude Code. Rewritten by a
     re-planning chat only while no step is closed; afterwards steps may
     only be appended. This file
     describes the sprint and nothing else: goal, fixed decisions, steps.
     The only thing written here during the sprint is the tick in a step's
     heading, by /close-step. -->
**Branch:** `city-popup/sprint-1-city-question` task branches → `master` (then `master` → `kyiv`)
**Goal:** On the local install, and on both productions after the developer's
hand deploy, a reader on a blog page (`/news/`, an article, or their Russian
versions) who taps a link to the services list, a service or the contacts page
sees the screen «City question» once. They land on that page on the chosen
city's site, in the same language, and are not asked again for 90 days. A
dismissal keeps them on the current site until the browser is closed. The
header switcher changes the remembered city. A service the chosen site does
not have leads to its services list. Clicks with a modifier key, pages outside
the blog and visitors without JavaScript behave as before, and every address
returns the same HTML to every visitor. The gate runs the theme's JS unit
tests, and every decision the module makes is covered by one.

## Fixed decisions
- DECISIONS "A theme feature that asks a blog reader's city before Services and Contacts" (2026-09-23): the feature lives in the theme under `inc/features/city-popup/`. The shared files it touches are named there, and each change is a plan task marked "touches shared code".
- DECISIONS "The visitor's city choice is a cookie written by JavaScript on the domain both installs share; PHP never reads it" (2026-09-23): `dovira_city` for the filtered number of days (default 90), a session cookie `dovira_city_dismissed`, and no PHP reader.
- DECISIONS "The other install's host and the cookie domain are derived from the site URL" (2026-09-23): no URL literal and no configuration.
- DECISIONS "The blog, services and contacts pages are found by their Ukrainian slugs and Polylang translations" (2026-09-23): the slugs `news`, `services`, `contacts` and the `service` base; PHP prints the targets for the JS.
- DECISIONS "The question is a native `<dialog>` printed only on blog pages; the click handler reads everything from its data attributes" (2026-09-23).
- DECISIONS "A service missing on the chosen install falls back to its services list through a marker in the URL" (2026-09-23): `city-popup=1`, and a 302 only for a 404 under the service base that carries it.
- DECISIONS "The header switcher saves the city it switches to" (2026-09-23): `data-city` on its links, with hrefs unchanged.
- DECISIONS "The theme gets Vitest for the unit tests of its JavaScript, run by the check command" (2026-09-23): the dev dependency is approved, and only pure modules are tested, in the `node` environment.
- DECISIONS "No UI design phase; the dialog is fixed as a template in FEATURE.md" (2026-09-23): screen «City question», `FEATURE.md` → UI.
- Root `CLAUDE.md` domain invariant 4 (the city comes from the site URL), and the theme `CLAUDE.md` (a feature registers through one line in `functions.php`; ES modules are imported from `app.js`; one CSS file per component is imported by `app.css`; every visible string is a Polylang string).

## Steps
<!-- Ordered by dependency. One step = one /plan-step → /do-step → /close-step
     cycle and must fit one working session. Every step has all five
     subsections, even if a subsection is "—". The checkbox in the heading is
     ticked by /close-step. -->

### [x] Step 1 — Delta-audit and Vitest in the gate
- **Tasks:**
  - Delta-audit:
    - Read `docs/features/core/FEATURE.md`, and `docs/features/search-stats/FEATURE.md`, which also changes `app.js`.
    - Read the shared code this feature will touch: `functions.php`, `source/scripts/app.js`, `source/styles/app.css`, `inc/utils/polylang-string-translations.php`, `template-parts/header/site-header.php`, `bin/check.sh`.
    - On the local DB, confirm the following:
      - the pages at `news`, `services` and `contacts` exist, with their Russian translations (`blog`, `uslugi`, `kontakty`);
      - the `service` type's rewrite base is `services`;
      - the Polylang strings `kharkiv` / `kyiv` hold the city names in both languages.
    - List the touchpoints and any conflict in the step report. The audit itself changes no code.
  - Add `vitest` `^3.2` to `devDependencies` of the theme's `package.json`, with a `test` script (`vitest run`). **Touches shared code — may affect other features:** the theme's `package.json`; consumers: the Vite build of every feature, which must still build.
    - `vitest.config.js` at the theme root: environment `node`, include `tests/js/**/*.test.js`, and the `@scripts` alias the tests import through.
    - One smoke test in `tests/js/`.
  - `bin/check.sh` gains a sixth stage, "Vitest (theme dovira)", after the theme's PHPUnit. **Touches shared code:** the project gate; consumers: every commit of every feature.
    - Before any stage, it stops with a clear message when `node` or `npm` is missing.
    - It runs `npm install` in the theme when `node_modules/` is missing, then `npm test`.
  - Check that the theme's `.gitignore` keeps `node_modules/` and `package-lock.json` out (both are listed today).
- **Tests:**
  - The smoke test imports a module through the configured alias and asserts a value.
  - `bin/check.sh` exits 0 and prints six stages.
- **Verification (manual):**
  - `bin/check.sh` from the repo root prints six stages and ends with the Vitest summary and `check: all green`.
  - Break the smoke test's assertion: the gate stops at stage 6 with a non-zero exit.
  - `ddev exec bash bin/check.sh` passes too.
  - `npm run build` in the theme still succeeds.
- **Docs to update:**
  - `docs/TECH-STACK.md` → Stack (the Vitest row: the installed version replaces "unverified"), Check command (six stages; Node and npm required) and Approved dependencies log (the installed version).
  - `docs/TESTING.md` → Levels (Unit (theme JS)) and How to run (`npm test`).
  - The theme `CLAUDE.md` → Local commands (`npm test`).
  - Root `CLAUDE.md` → Commands (the gate's stage list).
- **Depends on:** —

### [x] Step 2 — Feature bootstrap and the dialog on blog pages
- **Tasks:**
  - `inc/features/city-popup/bootstrap.php` requires the feature's classes and registers their hooks. Add one line in `functions.php`. **Touches shared code:** `functions.php`; consumers: every theme feature.
  - `Sites` (`dovira\CityPopup\Sites`), pure, working from the site URL it is given (`home_url()` at the call site). It returns:
    - the current city: `kyiv` when the URL contains `kyiv`, `kharkiv` otherwise;
    - the Kharkiv origin: the host without a leading `kyiv.`;
    - the Kyiv origin: `kyiv.` + the Kharkiv host;
    - the cookie domain: the Kharkiv host.
  - `Pages` (`dovira\CityPopup\Pages`):
    - The three slugs are class constants.
    - `is_blog()` is true for the page at `news` or its Polylang translation in the current language, and for `is_singular( 'post' )`.
    - `targets()` returns the permalinks of the pages at `services` and `contacts` in the current language (`get_page_by_path()` + `pll_get_post()`, or the page itself without Polylang) and the `service` base URL in the current language. A page that does not exist is left out.
  - `Dialog` (`dovira\CityPopup\Dialog`) on `wp_footer`, only on a blog page, prints one `<dialog>`:
    - the title, the «Харків» / «Київ» buttons and the × with its accessible label;
    - as escaped data attributes: the current city, both origins, the cookie domain, and the targets as JSON;
    - `days` = `apply_filters( 'dovira_city_popup_days', Dialog::DEFAULT_DAYS )`, cast to int; a value below 1 falls back to the default, and the number 90 appears once, as that constant.
    - It has no styles yet. A `<dialog>` without `open` is not displayed.
  - Strings, per `FEATURE.md` → UI, in `inc/utils/polylang-string-translations.php`. **Touches shared code:** the Polylang string list; consumers: every template that prints a registered string.
    - The buttons reuse the registered `kharkiv` / `kyiv` strings.
    - The title and the × label are registered with their Ukrainian text as the key, so an install that has not translated them yet still shows Ukrainian.
    - Their Russian values go into Languages → Translations: on the local install now, and on each production after its deploy.
- **Tests:**
  - `SitesTest`: `https://dovira.vet`, `https://kyiv.dovira.vet`, `https://dev.dovira.vet`, `https://dovira.ddev.site`, and a URL with a path or a port. Assert the city, the two origins and the cookie domain.
  - `PagesTest`:
    - blog: the `news` page and its Russian translation are blog pages; a `post` is one; the home page and a service are not;
    - targets in uk and ru, a missing page left out, and the case without Polylang.
  - `DialogTest`: nothing printed off the blog; printed once, with escaped attributes and JSON; the days filter, including a return below 1.
- **Verification (manual):**
  - Local `https://dovira.ddev.site/news/` and one article: the page source ends with the `<dialog>`. Its origins are `https://dovira.ddev.site` and `https://kyiv.dovira.ddev.site`, and its targets are `/services/`, `/contacts/` and the service base.
  - `/ru/blog/` and a Russian article carry the ru strings and `/ru/uslugi/`, `/ru/kontakty/`, `/ru/services/`.
  - The home page, `/services/` and a service carry no dialog.
  - Nothing visible changes on any page.
  - Languages → Translations lists the new strings, filled in for ru.
  - The strings on each production are verified by the next deploy from `main`.
- **Docs to update:** `docs/ARCHITECTURE.md` → Modules (the feature's row) and the Bootstrap row (the features `functions.php` requires).
- **Depends on:** Step 1

### [ ] Step 3 — The city question in the browser
- **Tasks:**
  - Pure module `source/scripts/features/city-popup/logic.js`, with no DOM access:
    - `isTarget( href, currentOrigin, targets )`;
    - `isPlainClick()`: modifiers, button, the link's `target`;
    - `crossUrl( href, origin )`: the other origin with the path, query and fragment kept and `city-popup=1` added once;
    - `decide()` from the cookies → `ask` | `go-current` | `go-city`;
    - the cookie helpers: `cityCookie( city, days, domain, secure )`, `dismissCookie( domain, secure )`, `readCookies( string )`, `cityFromAttr( value )`.
  - DOM glue `source/scripts/features/city-popup/city-popup.js`:
    - Where the dialog exists, one delegated `click` listener on `document` opens it with `showModal()`.
    - A city button writes `dovira_city` and navigates.
    - `cancel` (Esc), the × and a click on the backdrop write `dovira_city_dismissed` and follow the link as it is.
    - On every page, a click on `a[data-city]` writes `dovira_city`. Step 4 puts the attribute on the switcher.
  - Import the module in `source/scripts/app.js`. **Touches shared code:** consumers: every module on every page. An error in this feature must not stop the modules after it.
  - Screen «City question» per `FEATURE.md` → UI, in `source/styles/features/city-popup/city-popup.css`, imported by `source/styles/app.css`. **Touches shared code.** Mobile first, built from the `colors.css` tokens and `.button`, with a dimmed `::backdrop`.
  - `npm run build`, and commit `assets/`.
- **Tests:** Vitest over `logic.js`:
  - `isTarget`: the uk and ru lists; the services list, a service, a sub-service, contacts; `/news/` and `/about/` are not targets; neither is another host, `tel:`, or a bare `#hash`; a relative href is resolved.
  - `isPlainClick`: each modifier, the middle button, `target="_blank"`.
  - `crossUrl`: path, query and fragment kept; the marker added once; an existing query kept.
  - `decide`:
    - a saved `kyiv` on Kharkiv → `go-city` without asking;
    - a saved `kharkiv` on Kharkiv → `go-current`;
    - a dismissal only → `go-current`;
    - saved and dismissed → the saved city wins;
    - nothing → `ask`;
    - an unknown cookie value → `ask`.
  - Cookie strings:
    - `Max-Age` = days × 86400, `Domain`, `Path=/`, `SameSite=Lax`;
    - `Secure` only on https;
    - the session cookie has no `Max-Age`;
    - `cityFromAttr` accepts only `kharkiv` / `kyiv`.
- **Verification (manual):** screen «City question», on a local article, at desktop width and at 375 px:
  - «Послуги» in the header opens the dialog as in `FEATURE.md` → UI, with uk strings. On `/ru/news/…` the strings are ru.
  - Esc → `/services/` on the same host.
    - The next tap on «Контакти» goes straight to `/contacts/`.
    - After the browser is closed and reopened, the dialog shows again.
    - The × and a click on the backdrop behave as Esc.
  - «Харків» → `/services/`.
    - Devtools shows `dovira_city=kharkiv` on `dovira.ddev.site`, expiring in about 90 days.
    - The next tap opens no dialog.
  - With cookies cleared, «Київ» sends the browser to `https://kyiv.dovira.ddev.site/services/?city-popup=1`. That host does not resolve locally; the hop itself is verified by the next deploy from `main`.
    - A link to `/services/diagnostics/` added to a local article maps the same way.
    - So does a ru page.
  - Ctrl/Cmd-click and the middle button open a new tab without the dialog.
  - The home page's menu opens no dialog.
  - With JavaScript off, the links work as before.
- **Docs to update:**
  - `docs/ARCHITECTURE.md` → Modules (the JS part) and Data flows (the flow as built).
  - `docs/DESIGN.md` → Components (City question dialog) and Screens («City question», feature `city-popup`).
- **Depends on:** Step 1, Step 2

### [ ] Step 4 — The switcher saves the choice; a missing service falls back to the list
- **Tasks:**
  - `template-parts/header/site-header.php`: the switcher's links, desktop and mobile, get `data-city="kharkiv"` / `data-city="kyiv"`, and nothing else in the markup changes. **Touches shared code — may affect other features:** the header of every page on both installs; consumers: every page.
  - `Fallback` (`dovira\CityPopup\Fallback`) on `template_redirect`:
    - It acts when all three hold: `is_404()`, the query carries `city-popup=1`, and the request path is under the `service` base of either language.
    - It then calls `wp_safe_redirect()` to the services page of the request's language, with `302`, and exits.
    - In every other case it does nothing.
- **Tests:**
  - `FallbackTest`:
    - a 404 with the marker and a uk service path → `/services/`;
    - the same with a ru path → `/ru/uslugi/`;
    - no marker → nothing;
    - not a 404 → nothing;
    - a 404 with the marker outside the service base → nothing;
    - the services page missing → nothing.
  - Vitest: the switcher's `data-city` value → the cookie string; an unknown value writes nothing.
- **Verification (manual):**
  - Locally, on any page, tap «Київ» in the header, in the desktop header and in the mobile menu. The browser leaves for `https://kyiv.dovira.vet` as before.
  - Back on `dovira.ddev.site`:
    - devtools shows `dovira_city=kyiv`, 90 days;
    - on an article, «Послуги» now goes straight to `https://kyiv.dovira.ddev.site/services/?city-popup=1`, with no dialog.
  - `https://dovira.ddev.site/services/no-such-service/?city-popup=1` → 302 to `/services/`.
  - `/ru/services/no-such-service/?city-popup=1` → 302 to `/ru/uslugi/`.
  - Both of these without the marker → 404.
  - Verified by the next deploy from `main` and the merge of `master` into `kyiv`:
    - on a `dovira.vet` article, «Київ» leads to `kyiv.dovira.vet/services/`;
    - the Kyiv switcher's «Харків» changes the choice;
    - `kyiv.dovira.vet/services/no-such-service/?city-popup=1` lands on the Kyiv services list.
- **Docs to update:**
  - `docs/ARCHITECTURE.md` → Data flows (the fallback and the switcher) and Modules.
  - `docs/features/core/FEATURE.md` → Interfaces (the switcher's `data-city`).
- **Depends on:** Step 2, Step 3
