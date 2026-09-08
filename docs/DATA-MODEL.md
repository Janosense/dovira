# Data model — Dovira

<!-- This file MUST match the actual schema at all times. A schema change
     without updating this file in the same commit = unfinished task
     (CLAUDE.md core rule 8). Adoption: documented from the code as-is, 2026-09-08. -->

## Conventions
- Standard WordPress tables (`wp_` prefix; `wp_posts`, `wp_postmeta`,
  `wp_terms`/`wp_term_taxonomy`/`wp_term_relationships`, `wp_options`,
  `wp_users`/`wp_usermeta`) plus plugin tables (Polylang, Yoast
  `wp_yoast_indexable`, CF7). **No custom tables, no migrations mechanism**:
  schema = registered post types, taxonomies and ACF field groups in PHP.
- Every domain entity is a post type; every attribute is an ACF field stored
  in `wp_postmeta` (ACF convention: `{name}` value row + `_{name}` field-key
  row). Field names below are ACF names; repeater rows are
  `{repeater}_{i}_{subfield}` in postmeta.
- ACF options page fields live in `wp_options` as `options_{name}` (uk, default
  language) and `options_{lang}_{name}` (other languages; `inc/polylang.php`).
- Ids: WordPress auto-increment. Timestamps: `post_date`; custom
  `processing_date` is a Unix timestamp **+10800 s** (UTC+3 offset applied in
  code). Deletion: WordPress trash; no soft-delete flags of our own.
- Each city install (Kharkiv, Kyiv) has its own database; nothing is shared
  between them at the DB level.
- Languages: Polylang `language` / `post_translations` taxonomies link uk↔ru
  posts; `service` and `vacancy` are translatable (plus core `page`, `post`);
  `employee`, `conversation`, `application`, `questionary` are not.

## Tables
### Post type `service` (public, hierarchical, slug `services/`, supports title, editor, page-attributes)
A clinic service (e.g. "Хірургія") with its price list. Field group in `inc/acf/fields/post-type-service.php`, not shown on the `templates/sub-service.php` template.
| Field | Type | Notes |
|---|---|---|
| `image` | image | required |
| `description` | wysiwyg | intro under the H1 |
| `prices` | repeater (table) | one row per priced item |
| `prices_*_title`, `prices_*_description` | textarea | row text |
| `prices_*_is_price_general` | true/false | default 1: one `price` for all ticked cities; 0: per-city prices |
| `prices_*_price` | number | shown when `is_price_general = 1` |
| `prices_*_city_prices_price_city_{term_id}` | number (group) | **one subfield per `service-city` term, generated at runtime from the terms** |
| `prices_*_cities` | checkbox, values `city_{term_id}` | which city tabs the row appears in |
| `key_words` | textarea | editor-entered search keywords |
| `seo_description` | wysiwyg | rendered by `template-parts/seo-description.php` |
| `key_words_services` | plain postmeta (not ACF) | concatenated row titles, written on `save_post_service` for search |
| taxonomy `service-city` | — | scopes the service to a city |

### Post type `employee` (public, slug `team/`, supports title; `capability_type` page)
| Field | Type | Notes |
|---|---|---|
| `photo` | image | |
| `position` | text | |
| `quote` | textarea | |
| `social_links` | group: `facebook`, `instagram`, `linkedin` | urls |
| `is_contact_cta_enabled` | true/false | |
| `title`, `description` | text, textarea | contact CTA block |
| `facts` | repeater: `title`, `fact` | |
| taxonomy `service-city` | — | |

### Post type `vacancy` (public, hierarchical, slug `vacancies/`, supports title, page-attributes; Polylang-translatable)
| Field | Type | Notes |
|---|---|---|
| `is_open` | true/false | only open vacancies appear in the application filter |
| `description` | wysiwyg | |
| `salary_type` | select: `static` / `from` / `range` | |
| `static_salary`, `salary_from`, `salary_to` | number | which apply depends on `salary_type` |
| `contacts` | repeater: `phone` | |
| `seo_description` | wysiwyg | |
| taxonomy `service-city` | — | |

### Post type `conversation` (private, not queryable, supports title; `capability_type` conversation)
A contact-form or franchise-form submission. Created by `dovira_wpcf7_submit_action`.
| Field | Type | Notes |
|---|---|---|
| `is_processed` | true/false | admin list "Нове / Опрацьовано" |
| `note` | textarea | admin note |
| `name`, `pet_name`, `phone`, `email`, `message` | text/email/textarea | from CF7 `your-*` fields; `pet_name` only from form 6 |
| `processing_date` | plain postmeta, int | set when `is_processed` flips to 1, reset to 0 otherwise |
| `responsible_persons` | plain postmeta, array `{user_id: {name, email}}` | every admin who opened the editor (`the_editor_content` filter) |
| `post_title` | — | `"{name} | {phone}"`, prefixed `"Франшиза: "` for form 1430 |

### Post type `application` (public but `show_in_rest` false, hierarchical, slug `applications/`; `capability_type` conversation)
A vacancy application. Created by `dovira_wpcf7_before_send_mail` (CF7 form 1399).
| Field | Type | Notes |
|---|---|---|
| `status` | select: `new` / `in_processing` / `interview_scheduled` / `rejected` / `closed` | admin list badge + filter |
| `note` | textarea | |
| `first_name`, `last_name`, `phone`, `email` | text/email | |
| `file` | file (attachment id) | CV sideloaded into the media library, attached to this post |
| `file_error` | textarea | sideload error message if upload failed |
| `vacancy` | post_object → `vacancy` id | admin filter `vacancy_id` |
| `processing_date`, `responsible_persons` | plain postmeta | as for `conversation` |

### Post type `questionary` (public, `show_in_rest` true, supports title; `capability_type` conversation)
A blood-donor questionnaire. Created by `QuestionaryController::save_questionary`.
| Field | Type | Notes |
|---|---|---|
| `admin_note` | textarea | |
| `name`, `phone`, `city`, `email`, `pet_name`, `pet_type`, `pet_old`, `pet_weight`, `vaccination_date` | text/email | free text; `city` is ucfirst'ed request value, not a term |
| `animal` | radio: `dog` / `cat` | |
| `pet_sex` | radio: `female` / `male` | |
| `blood_group` | select | choices in `fields/post-type-questionary.php` (`all-dont-know`, `cat-a`, `cat-b`, `cat-ab`, `dog-dea-1-plus`, `dog-dea-1-minus`); the admin filter in `utils/questionary.php` lists a different, older set (`cat-AB`, `dog-dea-1.1` …) |
| `street`, `cat_contact`, `castration`, `donor_before`, `blood_take`, `chronic_diseases`, `pills` | true/false | from `yes`/`no` request values |
| `post_title` | — | `"{name} - {pet-name}"` |

### Taxonomy `service-city` (hierarchical, public, REST, admin column) — on `service`, `employee`, `vacancy`
Cities the clinic operates in. **Not Polylang-translatable** (invariant 2); term names are registered as Polylang strings `service-city-{term_id}` (group "Dovira: Cities"). Term ids are referenced by Service price field names.

### Options page `acf-options-settings` ("Settings", under Appearance) — `wp_options`
| Field | Type | Notes |
|---|---|---|
| `contacts_main_phone`, `contacts_telegram`, `contacts_viber` | text | |
| `contacts_cities` | repeater: `city`, `phones` (repeater: `number`), `address`, `note`, `schedule`, `schedule_shop`, `map_iframe`, `email`, `instagram_link` | header phones, footer, JSON-LD departments, fallback phone on price lists |
| `social_links` | repeater: `url`, `is_icon_image_file`, `icon_image`, `icon_svg_tag` | |

### Other `wp_options` rows written by the theme
| Option | Type | Notes |
|---|---|---|
| `telegram_bot_chats` | array `{chat_id: chat_id}` | chats that receive notifications; added by password, removed by `/reset` |
| `telegram_webhook_data` | array of raw Telegram updates | append-only log; cleared by `GET dovira/v1/telegram/reset-telegram-log` |

### Users
Roles: WordPress defaults + `customer_support_specialist` (caps `edit_conversations` … `read_conversation`, see `inc/custom-roles/`). Legacy: a `student` role (cap `take_course`) is added on theme activation and `user_study_state` usermeta is written by `dovira_update_user_study_state()` — remnants of a removed LMS, not used by any live page.

## Relations
```
service-city ──< service            application >── vacancy (post_object)
service-city ──< employee           application >── attachment (file / CV)
service-city ──< vacancy            service.prices[*].cities ──> service-city term ids
uk post <── Polylang post_translations ──> ru post   (page, post, service, vacancy)
```
`conversation` and `questionary` have no relations to other posts.

## Invariants
- A `service` price row is visible in a city tab only if `cities` contains
  that city's `city_{term_id}`; a per-city price lives in
  `city_prices_price_city_{term_id}` of the same row.
- Deleting or re-creating a `service-city` term orphans every stored
  `price_city_{old_id}` value — term ids are stable identifiers.
- `conversation`, `application`, `questionary` rows are created only by the
  form/REST handlers, never by editors; `post_author` is user 1 for CF7-created
  records.
- `processing_date` is derived from a status change and never entered by hand.
- `options_ru_*` rows exist only for the ru language and are produced by
  `wp dovira translate-options` or by saving the options page while viewing ru.
