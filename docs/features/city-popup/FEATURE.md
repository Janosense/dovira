# Feature — city-popup

<!-- playbook: v3.0.4. State document, read whole at the start of every step
     of the feature. Shape, triggers and what does not belong here:
     docs/CONTRACTS.md → `### docs/features/{feature}/FEATURE.md`. -->

## Scope
The blog lives only on the Kharkiv site and draws readers from all of
Ukraine. When a blog reader taps a link to the services list, a service or
the contacts page, the feature asks once which city they mean, remembers the
answer for 90 days on both city sites, and takes the reader to that page on
the chosen city's site in the same language; the header city switcher
changes the remembered city. It never changes what an address shows.

## Fit
- **Code path:** `wp-content/themes/dovira/inc/features/city-popup/` (JS in `source/scripts/features/city-popup/`, styles in `source/styles/features/city-popup/`, tests in `tests/Unit/CityPopup/` and `tests/js/city-popup/`)
- **Entry point:** `inc/features/city-popup/bootstrap.php`, required by one line in `functions.php`
- **Depends on:** `template-parts/header/site-header.php` (the switcher's links carry `data-city`); `source/scripts/app.js` and `source/styles/app.css` (import the module and its styles); `inc/utils/polylang-string-translations.php` (its four strings, keyed in Ukrainian, the city names reusing the «Dovira: Cities» translations); `.button` and the `colors.css` tokens; Polylang (`pll_get_post()`, `pll_current_language()`, `pll__()`); the Ukrainian pages `news`, `services`, `contacts` and the `service` rewrite base; `bin/check.sh` and the theme's `package.json` (Vitest)
- **Exposes:** the filter `dovira_city_popup_days` (int days, default 90, below 1 falls back); the query marker `city-popup=1`, understood on a 404 under the service base; `data-city="kharkiv|kyiv"` on any link saves that city on click

## Structure
| Module | Where | Responsibility |
|---|---|---|
| `Sites` | `inc/features/city-popup/Sites.php` | pure: the city, both installs' origins and the cookie domain from the site URL |
| `Pages` | `inc/features/city-popup/Pages.php` | whether a page is a blog page; the target URLs in the current language |
| `Dialog` | `inc/features/city-popup/Dialog.php` | prints the config element on every page and the closed dialog on blog pages |
| `Fallback` | `inc/features/city-popup/Fallback.php` | the 302 from a marked 404 under the service base to the services list |
| `logic.js` | `source/scripts/features/city-popup/logic.js` | pure: every decision of a click |
| `city-popup.js` | `source/scripts/features/city-popup/city-popup.js` | DOM glue: the delegated listener, the dialog, the cookies |

## Data
- cookie `dovira_city` (`kharkiv` / `kyiv`, 90 days) and session cookie `dovira_city_dismissed`, on the Kharkiv host's domain — in the visitor's browser; no table, option or post meta

## UI
| Screen | Route | In code | Artboard |
|---|---|---|---|
| City question | a blog page (`/news/`, an article; ru `/ru/blog/`, `/ru/news/…`), after a plain tap on a target link with no city saved and no dismissal | `Dialog.php`, `city-popup.js`, `city-popup.css` | none — the template below |

The template: a modal `<dialog>` centred on a dimmed backdrop, white, fitting
a 320 px screen; the title, then «Харків» and «Київ» (side by side, stacked
below 768 px), and × in the top-right corner. Strings: uk «Яке місто вас
цікавить?», «Харків», «Київ», × labelled «Закрити»; ru «Какой город вас
интересует?», «Харьков», «Киев», «Закрыть». The one visible state is open
with focus on «Харків»; every other outcome navigates away.

## Roadmap
- Sprint 1 — a blog reader is asked the city once and lands on the chosen city's page, in the same language; the header switcher changes the choice, and a service the chosen site lacks leads to its services list (`sprints/SPRINT-1.md`)
