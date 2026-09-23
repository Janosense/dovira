# Feature — city-popup

<!-- playbook: v1.25. Lightweight ARCHITECTURE + DATA-MODEL for one
     feature. Lives at docs/features/city-popup/FEATURE.md next to its
     sprints/. Root ARCHITECTURE.md holds only one row + a link here.
     Keep ≤80 lines. -->

## Purpose & scope
The blog lives only on the Kharkiv site and draws readers from all of Ukraine. When a reader on a blog page taps a link to the services list, a service or the contacts page, the feature asks once which city they mean (Харків / Київ). It remembers the answer for 90 days on both city sites and takes the reader to that page on the chosen city's site, in the same language. The header city switcher changes the remembered city. OUT of scope: a third «Інше місто» button (spec §8, not before an online-consultation page exists); a switcher that leads to the same page instead of the home page; GA events for the choice; any server-side redirect or content change by the remembered city; asking on any page but the blog.

## Fit into the host
- **Code location:** `wp-content/themes/dovira/inc/features/city-popup/` (+ `source/scripts/features/city-popup/`, `source/styles/features/city-popup/`, `tests/Unit/CityPopup/`, `tests/js/city-popup/`)
- **Host area:** theme `dovira` — obeys its `CLAUDE.md` isolation rules
- **Entry point:** `inc/features/city-popup/bootstrap.php`, required by one line in `functions.php`
- **Shared code it depends on:**
  - `template-parts/header/site-header.php`: the switcher's links carry `data-city`.
  - `source/scripts/app.js` and `source/styles/app.css`: they import the module and its styles.
  - `inc/utils/polylang-string-translations.php`: the dialog's four strings, each keyed in Ukrainian (`Яке місто вас цікавить?`, `Закрити`, `Харків`, `Київ`). The two city names share their ru values with the `Dovira: Cities` strings of the same text; the registered `kharkiv` / `kyiv` hold no names and are not used.
  - `.button` and the `colors.css` tokens.
  - Polylang: `pll_get_post()`, `pll_current_language()`, `pll__()`.
  - The pages at the Ukrainian slugs `news`, `services` and `contacts`, and the rewrite base of the `service` post type.
  - `bin/check.sh` and the theme's `package.json`, for Vitest.

## Data
No table, option or post meta. It owns these, in the visitor's browser:
- the cookie `dovira_city` (`kharkiv` | `kyiv`, 90 days);
- the session cookie `dovira_city_dismissed` (`1`).

Both cookies use the Kharkiv host as their domain, `Path=/`, `SameSite=Lax`, and `Secure` on https. Only this feature's JS writes them, and PHP never reads them. It also owns the filter `dovira_city_popup_days` (default 90; a value below 1 falls back to 90) and the query marker `city-popup=1`. No other code writes the two cookies: the header switcher saves a city only through this feature's module.

## Invariants
- The question opens only on a click on a blog page, never when a page loads. Without JS every link is a plain link.
- Every address serves its own city. PHP reads neither cookie, and no page, redirect or cache key depends on them.
- The browser handles a click with Ctrl/Cmd/Shift/Alt, a click with a button other than the main one, and a click on a link that opens another window. The feature does not touch them.
- Moving to the other install keeps the path, query and fragment and adds `city-popup=1`. Only a 404 under the service base that carries the marker is redirected: a 302 to the services list, in the same language. A 404 without the marker stays a 404.
- The current install's city, the other install's host and the cookie domain come from `home_url()` only (root invariant 4), never from a URL literal.
- A saved city wins over a dismissal, and a dismissal lasts until the browser is closed.
- Every visible string is a Polylang string.

## Interfaces
- Filter `dovira_city_popup_days` (int days, default 90).
- The query marker `city-popup=1`: every install understands it on a 404 under the service base.
- `data-city="kharkiv|kyiv"` on a link: the module saves that city on click, on every page. The header switcher uses it, and any link may carry it.
- The `<dialog>` markup and its data attributes are internal. No other feature reads them.

## UI
- **Screens:** «City question». There is no design export (DECISIONS "No UI design phase; the dialog is fixed as a template in FEATURE.md"). The template:
  - A modal `<dialog>` centred over the page on a dimmed `::backdrop`, white, narrow enough for a 320 px screen with its gutter. It holds a title, the two city buttons below it («Харків» first, then «Київ»; side by side, stacked on a narrow screen), and a × in the top-right corner.
  - Strings: uk «Яке місто вас цікавить?», «Харків», «Київ», × labelled «Закрити»; ru «Какой город вас интересует?», «Харьков», «Киев», × labelled «Закрыть».
  - States: open, with focus on the first city button. Chosen, dismissed and a saved city all navigate away, so there is no other visible state.
- **Reuses:** `.button` (`--black`), the `colors.css` tokens, the Inter heading style.
- **Introduces:** the feature component «City question dialog» (`source/styles/features/city-popup/`). Sprint 1 Step 3 adds it to `docs/DESIGN.md` → Components and Screens. No new token.

## Roadmap
- Sprint 1 — A blog reader is asked the city once and lands on the chosen city's page, in the same language. The header switcher changes the choice, and a service the chosen site lacks leads to its services list (`sprints/SPRINT-1.md`)
- Not planned yet (no sprint): the «Інше місто» button leading to an online-consultation page, once that page exists (spec §8).
