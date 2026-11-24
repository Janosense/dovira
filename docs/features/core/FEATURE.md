# Feature — core

<!-- playbook: v3.0.4. State document, read whole at the start of every step
     of the feature. Shape, triggers and what does not belong here:
     docs/CONTRACTS.md → `### docs/features/{feature}/FEATURE.md`. -->

## Scope
Everything the Dovira theme did before the playbook: the public site built
from ACF blocks, services with per-city prices, the team, vacancies and
search; the three form-to-record pipelines with their Telegram
notifications; the support role; the Ukrainian/Russian Polylang set-up; the
Kharkiv/Kyiv switch; the `wp dovira` translation commands. New behaviour is
a new feature that uses `core`; a fix to `core` goes through `/adhoc`.

## Fit
- **Code path:** `wp-content/themes/dovira/` (the theme as-is; new features: `inc/features/{name}/`)
- **Entry point:** `functions.php`, which requires every `inc/*.php` and one `bootstrap.php` per theme feature
- **Depends on:** — (it is the shared code)
- **Exposes:** the content model and ACF fields (`DATA-MODEL.md`); the helpers `dovira_get_acf_field()`, `dovira_translate_string()`, `starter_theme_vite_asset()`, `dovira_hex_is_light()` and the constants `TEMPLATE_DIR`, `TEMPLATE_DIR_URI`, `ASSETS_DIR_URI`, `VITE_SERVER`; the `acf/*` blocks with their shared `general` fields and `dovira_allowed_block_types()`; templates and parts (`header.php` with its `simple` mode, `footer.php`, `template-parts/**`, page template «Sub-Service»); the header city switcher's `data-city` links; the nav menus `primary`, `footer_col_1`, `footer_col_2`; `source/scripts/app.js`, `source/styles/app.css` and the tokens; the Polylang string groups `dovira` and «Dovira: Cities»; REST registration in `inc/rest-api.php`; the Telegram send `dovira_telegram_notify()`

## Structure
See `ARCHITECTURE.md` → Modules — every row there whose Used by names `core`.

## Data
- post types `service`, `employee`, `vacancy`, `conversation`, `application`, `questionary`
- taxonomy `service-city`
- options page `acf-options-settings` (`options_*`, `options_ru_*`)
- options `telegram_bot_chats`, `telegram_webhook_data`
- role `customer_support_specialist`

## UI
| Screen | Route | In code | Artboard |
|---|---|---|---|
| Home | `/` | `index.php` + blocks | — |
| Page | any `page` | `index.php` + blocks | — |
| Service | `/services/{slug}/` | `single-service.php` | — |
| Sub-service | `/services/{parent}/{slug}/` | `templates/sub-service.php` | — |
| Team member | `/team/{slug}/` | `single-employee.php` | — |
| Vacancy | `/vacancies/{slug}/` | `single-vacancy.php` | — |
| Blog article | `/news/{slug}/` (ru `/ru/news/{slug}/`) | `single.php` | — |
| Search results | `/?s={query}` | `search.php` | — |
| Not found | any unknown address | `404.php` | — |
| Application (staff print view) | `/applications/{slug}/` | `single-application.php` | — |
| Questionnaire (staff print view) | the `questionary` permalink | `single-questionary.php` | — |
| Admin lists: Conversations, Applications, Questionnaires | `wp-admin/edit.php?post_type={type}` | `inc/utils/{conversation,application,questionary}.php` | — |

## Roadmap
- No sprints: `core` is the adopted theme, and new work is a new feature.
