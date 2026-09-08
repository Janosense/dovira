# Tech stack — Dovira

## Stack
<!-- Adoption: versions below are read from the installed code (plugin headers,
     composer.lock, package-lock.json) on 2026-09-08 — verified, not pinned. -->
| Layer | Choice | Version | Why (link DECISIONS.md if non-obvious) |
|---|---|---|---|
| Platform | WordPress core (classic theme, no block theme) | 7.1 | client-editable site; the whole repo is the WP root |
| Language / runtime | PHP (DDEV image; production per host) | 8.3 (local) | theme uses PHP 8 syntax (`mixed`, union types, `str_contains`) |
| DB | MariaDB (DDEV) | 10.11 (local) | WP default; snapshot `mysql.sql` committed at repo root |
| Web server | nginx-fpm (DDEV) | — | — |
| Theme | `dovira` — custom classic theme from the Syndicode starter | 1.1.2026 | all custom code lives here; no custom plugins |
| Fields & blocks | ACF Pro + StoutLogic ACF Builder | 6.8.8 / 1.12.0 | field groups and 21 blocks defined in PHP, version-controlled (invariant 6) |
| i18n | Polylang | 3.8.4 | uk/ru content; ACF options per language via `inc/polylang.php` |
| Forms | Contact Form 7 | 6.1.6 | contact / franchise / vacancy forms → CPT records via hooks |
| SEO | Yoast SEO | 27.7 | meta + `wp_yoast_indexable`; one-off import tooling in `temp-data/` |
| Other plugins | Cyr2Lat 7.0.2 (Latin slugs), Simple Custom Post Order 2.7.2, Enable Media Replace 4.1.9, Akismet 5.7, Loco Translate 2.8.5 | — | — |
| PHP deps (theme `composer.json`) | vlucas/phpdotenv 5.6.3, anthropic-ai/sdk 0.25.0, guzzlehttp/guzzle 7.11.0; dev: roave/security-advisories | — | `.env` loading; CLI translation; `vendor/` is committed |
| Front-end build | Vite + PostCSS (preset-env stage 1, assets, prefix-selector, replace) + LightningCSS | 5.4.19 / 8.4.49 / 1.28.2 | `source/` → hashed `assets/` with manifest; `assets/` is committed |
| Front-end libs | Swiper 11.2.1, Fancybox (`@fancyapps/ui`) 5.0.36, iMask 7.6.1; vanilla ES modules, no framework | — | dynamic `import()` per module in `scripts/app.js` |
| Fonts | Google Fonts (Inter, Oswald, Raleway) + self-hosted Open Sans | — | — |
| Testing | **none** — no PHPUnit, no JS tests, no linter, no static analysis configured | — | see Check command |
| Local env | DDEV | — | `.ddev/config.yaml`; `wp-config.php` DDEV-generated |
| CI/CD | GitHub Actions → FTP (dev only) | FTP-Deploy-Action 4.3.5 | production deploys are manual (ARCHITECTURE.md → Environments) |
| CLI | WP-CLI (`wp dovira …` commands in `inc/cli/`) | — | translation and bundle transfer |

## Check command
`none yet` — the project ships no test, lint, static-analysis or build gate.
Created in the first code step of the next feature (its Sprint 1 Step 1 has
the task "create the check command"): one committed script that at minimum
runs `npm run build` in the theme and a PHP lint/static-analysis pass, exits
non-zero on the first failure, and is recorded here.

## ANTI-PATTERNS (mandatory reading before writing code)
<!-- Derived from what the code actually does and avoids (Adoption). Grows via LEARNINGS.md. -->
- Do not create or edit ACF field groups in the admin UI — every group is a
  PHP `FieldsBuilder` in `inc/acf/`; the UI would create a DB copy that the
  code cannot see and that a deploy cannot carry.
- Do not add a block outside `inc/acf/blocks/{name}/` with the trio
  `block.json` + `fields.php` + `template.php` — the directory scan in
  `inc/acf.php` is the only registration; also add the block name to
  `dovira_allowed_block_types()` or editors will not see it.
- Do not make `service-city` Polylang-translatable and do not rename its terms
  by id — Service price field names (`price_city_{id}`, `city_{id}`) and stored
  postmeta derive from term ids. Translate names as Polylang strings.
- Do not send the Telegram notification before `wp_insert_post` succeeds, and
  do not let a Telegram failure change the HTTP/mail result — the record is
  the source of truth, the notification is best-effort.
- Do not decide "which city site is this" anywhere except from the site URL
  (`site-header.php`) — there is no option, constant or env flag for it.
- Do not hand-edit `assets/` or `assets/.vite/manifest.json` — run
  `npm run build`; the manifest resolver `starter_theme_vite_asset()` maps
  `source/*` entries to hashed files.
- Do not enqueue front-end assets by hand-written paths — go through
  `starter_theme_vite_asset()`; in development the Vite dev server serves them.
- Do not put runtime-loaded code into `temp-data/`, `reports/` or `_to_delete/` —
  they are ops artifacts; the theme never requires them.
- Do not register a post type / taxonomy outside `inc/post-types/*.php` /
  `inc/taxonomies/*.php` — they are glob-loaded; an inline `register_post_type`
  in `functions.php` breaks the one-file-per-type convention.
- Do not use `wp-content/mu-plugins/` — it is gitignored (starter-theme legacy)
  and would not deploy.
- Do not put per-city analytics ids or any Kyiv-only change on `master` —
  `kyiv` carries them and only ever merges `master` in.
- Do not add new hardcoded CF7 form ids without recording them in
  ARCHITECTURE.md → Integrations; the three existing ids (6, 1430, 1399) are
  environment-specific already.
- Do not add a check/lint/test tool as a global install — it must be a
  committed script or `composer.json`/`package.json` dev dependency.

## Dependency policy
New dependencies (runtime AND dev/tooling) only after explicit user approval —
see CLAUDE.md core rule 4. Record approved additions here with one line of
justification.

## Approved dependencies log
| Date | Package | Why |
|---|---|---|
| (pre-adoption) | everything in the Stack table | inherited as-is at adoption, 2026-09-08 |
