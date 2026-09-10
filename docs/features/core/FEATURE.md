# Feature — core

<!-- Lightweight ARCHITECTURE + DATA-MODEL for one feature. Lives at
     docs/features/core/FEATURE.md next to its sprints/. Root
     ARCHITECTURE.md holds only one row + a link here. Keep ≤80 lines.
     Adoption 2026-09-08: `core` is the existing theme registered as-is.
     It has NO sprints and never will — new work is a new feature. -->

## Purpose & scope
Everything the Dovira site does today: the public site (pages from ACF
blocks, services with per-city prices, team, vacancies, search), the three
form-to-record pipelines with Telegram notifications, the support role, the
Polylang uk/ru setup, the Kharkiv/Kyiv site switch, and the `wp dovira`
translation CLI. OUT of scope: any new behaviour — it is planned as a
separate feature that consumes the interfaces below; fixes to `core` go
through `/adhoc`.

## Fit into the host
- **Code location:** `wp-content/themes/dovira/` (the whole theme)
- **Host area:** theme `dovira` — obeys its `CLAUDE.md` isolation rules
- **Entry point:** `functions.php` (requires every `inc/*.php`; a new feature adds one `require_once TEMPLATE_DIR . '/inc/features/{name}/bootstrap.php';` line)
- **Shared code it depends on:** — (it *is* the shared code; see Interfaces)

## Data
Owns every table row described in `docs/DATA-MODEL.md`: post types
`service`, `employee`, `vacancy`, `conversation`, `application`,
`questionary`; taxonomy `service-city`; options page `acf-options-settings`
(`options_*`, `options_ru_*`); options `telegram_bot_chats`,
`telegram_webhook_data`; role `customer_support_specialist`; postmeta
`processing_date`, `responsible_persons`, `key_words_services`.
Ownership rule: another feature reads this data through WordPress/ACF
functions but never writes to it except through the hooks and functions
listed under Interfaces; a new field on one of these post types is a `core`
change ("touches shared code") recorded in DATA-MODEL.md.

## Invariants
- Root `CLAUDE.md` Domain invariants 1–8 all belong to `core`.
- Field groups and blocks exist only as PHP under `inc/acf/`; the block set
  editors see is `dovira_allowed_block_types()`.
- `service-city` term ids are stable identifiers (price field names).
- The record is saved before Telegram is called; Telegram is fire-and-forget.
- Built assets in `assets/` and `vendor/` are committed; a deploy is a file sync.

## Interfaces
Surface other features may rely on (changing it = "touches shared surface"):
- **PHP helpers:** `dovira_get_acf_field()`, `dovira_translate_string()`,
  `starter_theme_vite_asset()`, `dovira_hex_is_light()`, constants
  `TEMPLATE_DIR`, `TEMPLATE_DIR_URI`, `ASSETS_DIR_URI`, `VITE_SERVER`.
- **Hooks the theme defines:** none custom; it hooks core/plugin actions
  (`acf/init`, `init`, `wpcf7_submit`, `wpcf7_before_send_mail`,
  `rest_api_init`, `save_post_{type}`, `allowed_block_types_all`).
- **REST (`dovira/v1`):** `POST questionary/save`, `POST telegram/handle-updates`,
  `GET telegram/send-test-message`, `GET telegram/reset-telegram-log`.
- **WP-CLI:** `wp dovira translate`, `translate-options`,
  `translations-export`, `translations-import`.
- **Blocks:** 21 `acf/*` blocks (`docs/DESIGN.md` → Components); the shared
  `general` tab fields every block carries.
- **Templates & parts:** `header.php` (`mode => simple` variant),
  `footer.php`, `template-parts/{header,footer}/*`, `option-status.php`,
  `seo-description.php`; page template "Sub-Service".
- **Nav menus:** `primary`, `footer_col_1`, `footer_col_2`.
- **Front-end:** `source/scripts/app.js` module list; CSS tokens in
  `source/styles/colors.css`; component classes in `docs/DESIGN.md`.
- **Polylang strings:** group `dovira` (`inc/utils/polylang-string-translations.php`)
  and `Dovira: Cities`.

## UI
- **Screens:** the project-wide list in `docs/DESIGN.md` → Screens — all
  belong to `core`; there is no design export, the template file is the ref.
- **Reuses:** every component in `docs/DESIGN.md` → Components
- **Introduces:** — (all of them are `core`'s)

## Roadmap
<!-- Adoption: `core` has no sprints. New work never goes into `core`. -->
- No sprints. Next work = a new feature via Feature-mode discovery
  (`docs/features/{name}/`), whose Sprint 1 Step 1 is a delta-audit of this
  file plus the theme's shared code and creates the check command.
