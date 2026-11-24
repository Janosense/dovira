# Tech stack — Dovira

<!-- playbook: v3.0.4. State document, read whole before writing code; a
     sentence or row that stops being true is edited or removed. Exact
     versions are the lockfiles and plugin headers. Shape, triggers and what
     does not belong here: docs/CONTRACTS.md → `### docs/TECH-STACK.md`. -->

## Stack
| Layer | Choice | Major version |
|---|---|---|
| Platform | WordPress core, classic theme (the repo is the WordPress root) | 7 |
| Language | PHP (8.3 in DDEV; the check script needs ≥ 8.3) | 8 |
| Database | MariaDB (DDEV 10.11) | 10 |
| Theme | `dovira`, custom classic theme (Syndicode starter) | — |
| Fields & blocks | ACF Pro + StoutLogic ACF Builder | 6 / 1 |
| Languages | Polylang | 3 |
| Forms | Contact Form 7 | 6 |
| SEO | Yoast SEO | 28 |
| MCP transport | `mcp-adapter` (WordPress MCP Adapter), committed; the Abilities API of core | 0.7 |
| Environment loading | vlucas/phpdotenv (theme `composer.json`, `vendor/` committed) | 5 |
| AI translation | anthropic-ai/sdk + guzzlehttp/guzzle | 0.25 / 7 |
| Front-end build | Vite + PostCSS + LightningCSS | 5 / 8 / 1 |
| Front-end libraries | Swiper, Fancybox (`@fancyapps/ui`), iMask; vanilla ES modules | 11 / 5 / 7 |
| PHP tests | PHPUnit + Brain\Monkey — the plugin's `composer.json` and the theme's separate `tests/composer.json`, both dev-only | 12 / 2 |
| JS tests | Vitest, `node` environment, no DOM library (theme `devDependencies`) | 3 |
| Analysis & formatting (plugin) | PHPStan + szepeviktor/phpstan-wordpress; PHPCS + WordPress Coding Standards | 2 / 3 |

## Check command
`bin/check.sh` from the repo root gates every commit and stops at the first
failure: PHPCS, PHPStan (level 8, two workers, `--memory-limit=1G`) and
PHPUnit in `wp-content/plugins/ga-telegram-bridge/`, then `php -l` over the
theme, PHPUnit in the theme's `tests/`, and Vitest in the theme (`npm test`).
It refuses to start below PHP 8.3 or without `node` and `npm`, installs a
missing plugin `vendor/`, theme `tests/vendor/` or theme `node_modules/`, and
first checks that Vite loads on this OS. It runs on the host, because
`node_modules/` is shared with the DDEV container and holds native packages
for the host's OS only. Both PHPUnit configurations set `failOnRisky` and
Vitest sets `expect.requireAssertions`, so a test that asserts nothing fails
the gate. A task's commit passes the same script; `npm run build` is not part
of it and is run when a task changes `source/`.

## ANTI-PATTERNS
- Do not create or edit an ACF field group in the admin UI — write a `FieldsBuilder` in `inc/acf/`, because a group made in the UI lives only in one database and no deploy carries it.
- Do not register a block outside `inc/acf/blocks/{name}/` (or a feature's `blocks/{name}/`) with its trio `block.json` + `fields.php` + `template.php` — the directory scan in `inc/acf.php` is the only registration, and the name must also be added to `dovira_allowed_block_types()`.
- Do not register a post type or a taxonomy inline — one file each in `inc/post-types/` or `inc/taxonomies/`, which are glob-loaded.
- Do not make `service-city` Polylang-translatable or re-create its terms — price field names and stored postmeta derive from its term ids, and its names are Polylang strings.
- Do not write an ACF value with `update_post_meta()` or `$wpdb`, nor read one by its stored key — use `update_field()` / `get_field()` by name, which keep the `_{name}` key rows and a repeater's row count (`PostWriter`).
- Do not read or write an ACF block's `attrs.data` by its flattened keys — go through `acf_setup_meta()` and `get_field_objects()` / ACF's save capture (`PostReader::block_fields()`, `PostWriter::acf_block()`), keep unchanged fields unformatted (`get_fields( $id, false )`), and give a new block every unnamed field's `default_value`.
- Do not call Telegram before `wp_insert_post()` has succeeded, nor let its result change the form's answer — the record is the source of truth (`dovira_telegram_notify()`).
- Do not decide the city from anything but the site URL — no option, constant or env flag exists for it.
- Do not write `https://dovira.vet` or `https://kyiv.dovira.vet` into new code — derive the other install's host from the own one (the Kharkiv host is the own host without `kyiv.`, the Kyiv host is `kyiv.` + it; `dovira\CityPopup\Sites`).
- Do not keep the visitor's chosen city anywhere but the cookies `dovira_city` / `dovira_city_dismissed` on the Kharkiv host's domain, nor read them in PHP or vary a page, a redirect or a cache key by them (`source/scripts/features/city-popup/logic.js`).
- Do not record a search from PHP — the browser module `source/scripts/features/search-stats/record.js` is the one recorder, so a crawler that runs no JavaScript leaves no row.
- Do not escape a value of a Telegram HTML message with `esc_html()`, which keeps named entities Telegram refuses — decode a stored title, then use `htmlspecialchars( $v, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8', true )`.
- Do not print a number in a Telegram message with `number_format_i18n()` — its Ukrainian thousands separator is the entity `&nbsp;`, so print plain digits.
- Do not treat `event.target === dialog` as a click on a `<dialog>`'s backdrop — also test that the point lies outside `getBoundingClientRect()` (`isOutside()` in `logic.js`).
- Do not colour `::backdrop` with a `:root` custom property — older browsers do not pass custom properties into it, so write the value.
- Do not expose a core, adapter or other plugin's ability on the `dovira` MCP server, nor a `dovira/*` ability on the adapter's default server — every `dovira/*` ability carries `meta.mcp.public: false` and `meta.show_in_rest: true` and never `meta.public: true` (`DescribeSite::args()`, `Catalogue::TOOLS`).
- Do not gate a read of `conversation`, `application` or `questionary` with `read_post` — core maps it to plain `read` for a published post, so gate with `edit_others_conversations` (`Scope::can_read_type()`).
- Do not hand-edit `assets/` or `assets/.vite/manifest.json` — run `npm run build`.
- Do not enqueue a front-end asset by a written path — go through `starter_theme_vite_asset()`.
- Do not put runtime code into `temp-data/`, `reports/` or `_to_delete/` — they are ops artifacts the theme never loads.
- Do not use `wp-content/mu-plugins/` — it is gitignored and would not deploy.
- Do not put a per-city analytics id or any Kyiv-only change on `master` — `kyiv` carries them and only merges `master` in.
- Do not add a Contact Form 7 form id to a hook without naming it in `ARCHITECTURE.md` → Flows — the three ids (6, 1430, 1399) are environment-specific.
- Do not add a dev or tooling package to the theme's own `composer.json`, whose committed `vendor/` loads on every request — it goes into `tests/composer.json` or the theme's `package.json` `devDependencies`.
- Do not install a check, lint or test tool globally — it is a committed script or a dev dependency.

## CONVENTIONS
- A theme feature's JavaScript logic is pure modules with no DOM access, unit-tested with Vitest in `tests/js/{feature}/`, and the DOM glue that calls them stays thin and is verified by hand (DECISIONS 2026-09-23 — The theme gets Vitest for the unit tests of its JavaScript).
- Every `dovira/*` write ability extends `WriteAbility`: it implements `preview()` → `Diff` and `write()`, leaves the token, the session's dump and the result's keys to `run()`, and reads the session only through `Session::id()`.
