# Data model — Dovira

<!-- playbook: v3.0.4. State document, read by block, never whole; a block is
     replaced when its post type, field group, option or table changes.
     Shape, triggers and what does not belong here: docs/CONTRACTS.md →
     `### docs/DATA-MODEL.md`. -->

## Conventions
- Each install has its own MariaDB database with WordPress's standard tables (prefix `wp_`), the plugins' own (Polylang, Yoast `wp_yoast_indexable`, CF7), and one custom table, `{prefix}dovira_search_queries`; charset and collation are `$wpdb->get_charset_collate()` (`utf8mb4` / `utf8mb4_unicode_520_ci`).
- The schema is code: post types in `inc/post-types/`, the taxonomy in `inc/taxonomies/`, ACF field groups in `inc/acf/`, the custom table in `Schema` with `dbDelta()` — a change to any of these is this document's "migration".
- A domain entity is a post type, and its attributes are ACF fields in `wp_postmeta` (a `{name}` value row plus a `_{name}` field-key row; a repeater's rows are `{repeater}_{i}_{subfield}`).
- A block below is headed by the post type, taxonomy, option, transient or table name, and its column table lists ACF field names, option keys or SQL columns.
- Every post has WordPress's id (auto-increment), `post_date` and `post_modified`, not repeated per block.
- Types in the column tables are ACF field types (`text`, `textarea`, `wysiwyg`, `number`, `true_false`, `select`, `radio`, `checkbox`, `image`, `file`, `link`, `repeater`, `group`, `post_object`), "postmeta" for a plain meta row, or SQL types for the custom table.
- An enumeration is a `select` / `radio` field or a string key, its values listed in Notes.
- Prices are ACF `number` fields in hryvnias, without a currency column.
- Time is WordPress's (`post_date` in the site zone, `post_date_gmt` in UTC); the custom table stores UTC; `processing_date` is a Unix timestamp shifted by +10800 s in code.
- Records (`conversation`, `application`, `questionary`) are facts: they are processed by a status, and removed only to the WordPress trash by an administrator.
- Options of the ACF options page are `options_{name}` for Ukrainian and `options_ru_{name}` for Russian.
- Polylang links translations with its taxonomies `language` and `post_translations`; `service`, `vacancy`, `page`, `post` are translatable, `employee` and the three records are not.

## Tables

### service
- **Purpose:** a clinic service with its price list (post type, public, hierarchical, slug `services/`; a child page on the template «Sub-Service» has no price group).
- **Written by:** wp-admin; `content-mcp` (`PostWriter`, `PriceRows`, `Translator`); `save_post_service` writes `key_words_services`.

| Column | Type | Notes |
|---|---|---|
| `image` | image | required |
| `description` | wysiwyg | |
| `prices` | repeater | |
| `prices_*_title`, `prices_*_description` | textarea | |
| `prices_*_is_price_general` | true_false | default 1 |
| `prices_*_price` | number | used when `is_price_general` = 1 |
| `prices_*_city_prices_price_city_{term_id}` | number (in group `city_prices`) | one subfield per `service-city` term, generated from the terms |
| `prices_*_cities` | checkbox | values `city_{term_id}` |
| `prices_*_linked_page` | link | |
| `key_words` | textarea | |
| `seo_description` | wysiwyg | |
| `key_words_services` | postmeta | generated on save |
| taxonomy `service-city` | — | |

### employee
- **Purpose:** a team member of a city (post type, public, slug `team/`, `capability_type` page).
- **Written by:** wp-admin; `content-mcp` (`PostWriter`).

| Column | Type | Notes |
|---|---|---|
| `photo` | image | required |
| `position` | text | |
| `quote` | textarea | |
| `social_links` | group: `facebook`, `instagram`, `linkedin` | |
| `is_contact_cta_enabled` | true_false | |
| `title`, `description` | text, textarea | |
| `facts` | repeater: `title`, `fact` | |
| taxonomy `service-city` | — | |

### vacancy
- **Purpose:** a job offer of a city (post type, public, hierarchical, slug `vacancies/`).
- **Written by:** wp-admin; `content-mcp` (`PostWriter`, `Translator`).

| Column | Type | Notes |
|---|---|---|
| `is_open` | true_false | default 1 |
| `description` | wysiwyg | required |
| `salary_type` | select | `static` / `from` / `range` |
| `static_salary`, `salary_from`, `salary_to` | number | |
| `contacts` | repeater: `phone` | |
| `seo_description` | wysiwyg | |
| taxonomy `service-city` | — | |

### conversation
- **Purpose:** a contact-form or franchise-form submission (post type, not queryable, `capability_type` conversation).
- **Written by:** `dovira_wpcf7_submit_action()`; staff in wp-admin (`is_processed`, `note`); `dovira-bridge/v1` (processed status); `responsible_persons` by the `the_editor_content` filter.

| Column | Type | Notes |
|---|---|---|
| `post_title` | — | `{name} \| {phone}`, prefix `Франшиза: ` for form 1430 |
| `is_processed` | true_false | |
| `note` | textarea | |
| `name`, `pet_name`, `phone`, `email`, `message` | text / email / textarea | `pet_name` from form 6 only |
| `processing_date` | postmeta, int | set when processed, 0 otherwise |
| `responsible_persons` | postmeta, array `{user_id: {name, email}}` | |

### application
- **Purpose:** a candidate's response to a vacancy (post type, public, hierarchical, slug `applications/`, not in REST, `capability_type` conversation).
- **Written by:** `dovira_wpcf7_before_send_mail()` (form 1399); staff in wp-admin (`status`, `note`).

| Column | Type | Notes |
|---|---|---|
| `status` | select | `new` / `in_processing` / `interview_scheduled` / `rejected` / `closed`; `new` on creation |
| `note` | textarea | |
| `first_name`, `last_name`, `phone`, `email` | text / email | |
| `file` | file | attachment id, attached to this post |
| `file_error` | textarea | |
| `vacancy` | post_object | → `vacancy` |
| `processing_date`, `responsible_persons` | postmeta | as `conversation` |

### questionary
- **Purpose:** a blood-donor questionnaire (post type, public, in REST, `capability_type` conversation).
- **Written by:** `QuestionaryController::save_questionary()`; staff in wp-admin (`admin_note`).

| Column | Type | Notes |
|---|---|---|
| `post_title` | — | `{name} - {pet-name}` |
| `admin_note` | textarea | |
| `name`, `phone`, `city`, `email`, `pet_name`, `pet_type`, `pet_old`, `pet_weight`, `vaccination_date` | text / email | `city` is free text, not a term |
| `animal` | radio | `dog` / `cat` |
| `pet_sex` | radio | `female` / `male` |
| `blood_group` | select | `all-dont-know`, `cat-a`, `cat-b`, `cat-ab`, `dog-dea-1-plus`, `dog-dea-1-minus` |
| `street`, `cat_contact`, `castration`, `donor_before`, `blood_take`, `chronic_diseases`, `pills` | true_false | |

### service-city
- **Purpose:** the cities the clinic works in (taxonomy on `service`, `employee`, `vacancy`; hierarchical, public, in REST).
- **Written by:** wp-admin only; never by `content-mcp`.

| Column | Type | Notes |
|---|---|---|
| term id | — | referenced by `service` field names; never re-created |
| `slug` | — | `kharkiv` / `kyiv` — the install's own term is found by the city of its URL |
| name | — | not Polylang-translatable; a Polylang string `service-city-{term_id}` (group «Dovira: Cities») |

### options page `acf-options-settings`
- **Purpose:** contacts per city and the global social links (ACF options page «Settings», per language).
- **Written by:** wp-admin; `wp dovira translate-options`.

| Column | Type | Notes |
|---|---|---|
| `contacts_main_phone`, `contacts_telegram`, `contacts_viber` | text | |
| `contacts_cities` | repeater: `city`, `phones` (repeater `number`), `address`, `note`, `schedule`, `schedule_shop`, `map_iframe`, `email`, `instagram_link` | |
| `social_links` | repeater: `url`, `is_icon_image_file`, `icon_image`, `icon_svg_tag` | |

### options `telegram_bot_chats`, `telegram_webhook_data`
- **Purpose:** the chats subscribed to form notifications, and the raw updates of the theme bot's webhook.
- **Written by:** `TelegramController::handle_updates()`; `reset-telegram-log` empties `telegram_webhook_data`.

| Column | Type | Notes |
|---|---|---|
| `telegram_bot_chats` | array `{chat_id: chat_id}` | |
| `telegram_webhook_data` | array of updates | append-only |

### users and roles
- **Purpose:** WordPress users; the role `customer_support_specialist` (caps `*_conversation(s)`); application passwords of connector and Dovira Bridge users.
- **Written by:** wp-admin; `inc/custom-roles/`; the `mcp-adapter` sessions in user meta.

| Column | Type | Notes |
|---|---|---|
| role `customer_support_specialist` | — | caps shared by `conversation`, `application`, `questionary` |
| role `student`, usermeta `user_study_state` | — | written by unused legacy code |

### gatb_settings
- **Purpose:** the configuration of `ga-telegram-bridge` (option, autoload `no`).
- **Written by:** `Plugin::activate()` (defaults) and the settings screen through the Settings API (`Settings`).

| Column | Type | Notes |
|---|---|---|
| `property_id` | string | default `''`; digits only |
| `service_account_json` | string | default `''`; overridden by `GATB_GA_SERVICE_ACCOUNT_JSON` |
| `telegram_bot_token` | string | default `''`; overridden by `GATB_TELEGRAM_BOT_TOKEN` |
| `telegram_chat_id` | string | default `''`; `/^-?\d+$/` |
| `telegram_thread_id` | string | default `''`; empty or `/^[1-9]\d*$/` |
| `send_time` | string | default `09:00`; `HH:MM`, site-local |
| `max_attempts` | int | default 3; 1–10 |
| `blocks` | array<string,bool> | keys `visitors` (always true), `pages`, `channels`, `cities`, `devices`, `trend`; default all true |

### gatb_state
- **Purpose:** the plugin's idempotency — which day was delivered, the day's attempts, the last WP-Cron hit (option, autoload `no`).
- **Written by:** `RunLog`, merging into the stored row.

| Column | Type | Notes |
|---|---|---|
| `last_report_date` | string | default `''`; `Y-m-d` of the property's day |
| `attempt` | int | default 0 |
| `last_cron_hit` | int | default 0; Unix time |

### gatb_log
- **Purpose:** the last runs of the report (option, autoload `no`).
- **Written by:** `RunLog::add()`, newest first.

| Column | Type | Notes |
|---|---|---|
| (list) | array | at most 30 entries (`RunLog::KEEP`) |
| `time` | int | Unix time |
| `trigger` | string | `manual` / `cron` / `retry` |
| `date` | string | `Y-m-d` |
| `status` | string | `sent` / `failed` |
| `attempt` | int | |
| `message` | string | never a secret |

### gatb_google_access_token
- **Purpose:** the cached Google access token (transient).
- **Written by:** `GoogleAuth::access_token()`; deleted on a 401.

| Column | Type | Notes |
|---|---|---|
| (value) | string | expires after `expires_in` − 60 s |

### {prefix}dovira_search_queries
- **Purpose:** one row per search a visitor made in a browser.
- **Written by:** `Repository::insert()` behind `POST dovira/v1/search-stats/record`; deleted only by `Purge::run()`.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint(20) unsigned | auto-increment, primary key |
| `level` | varchar(16) | not null; `site` / `services` / `service`; key `level_created_at (level, created_at)` |
| `query_text` | varchar(100) | not null; normalized |
| `context_id` | bigint(20) unsigned | not null, default 0 |
| `results` | int(10) unsigned | null; set for `site` only |
| `created_at` | datetime | not null; UTC; key `created_at` |

### dovira_search_stats_db_version
- **Purpose:** the schema version of `{prefix}dovira_search_queries` (option, autoloaded).
- **Written by:** `Schema::install()` after a `dbDelta()` run with no error.

| Column | Type | Notes |
|---|---|---|
| (value) | int | equals `Schema::VERSION` (1) |

### dovira_mcp_confirm_{hash}
- **Purpose:** one pending write confirmation of the connector (transient, 15 minutes).
- **Written by:** `Confirmation`; deleted when the token is used.

| Column | Type | Notes |
|---|---|---|
| `{hash}` | — | SHA-256 of user id, `Mcp-Session-Id` and the canonical payload without `confirmation` |
| `user_id`, `session`, `payload_hash`, `issued_at` | — | |
| `token_hash` | string | SHA-256 of the token; the token itself is never stored |
| `diff` | array | |

### dovira_mcp_dumped_{session}
- **Purpose:** marks that a connector session has its dump (transient, 24 hours).
- **Written by:** `Dump::ensure()`; never set for an empty session.

| Column | Type | Notes |
|---|---|---|
| (value) | string | the dump file's name |

## Relations without a foreign key
- A `service` price row's `cities` values and its `city_prices.price_city_{term_id}` names point at `service-city` term ids, many to many.
- `service`, `employee` and `vacancy` belong to `service-city` terms through WordPress's term relationships, many to many.
- An `application`'s `vacancy` names one `vacancy` post, and its `file` one attachment, many to one.
- Polylang's `post_translations` links a Ukrainian post to at most one Russian post.
- `{prefix}dovira_search_queries.context_id` names a `wp_posts.ID` (the page hosting the `services` block, or the `service`), 0 for level `site`, checked only when the row is written.
- A `dovira_mcp_dumped_{session}` value names a dump file in `DOVIRA_MCP_DUMP_DIR` (`dovira-{host}-{YYYY-MM-DD}-{HHMMSS}.sql.gz`, never overwritten).

## Invariants across tables
- A price row appears under a city only when its `cities` contains that city's `city_{term_id}`, and its per-city price lives in the same row's `city_prices_price_city_{term_id}`.
- Deleting or re-creating a `service-city` term orphans every stored value whose name carries the old id.
- `conversation`, `application` and `questionary` posts are created only by the form and REST handlers, never by staff or the connector.
- `options_ru_*` rows exist only for Russian and are written by `wp dovira translate-options` or by saving the options page in Russian.
- A Russian `service` has the same price rows, in the same order, as its Ukrainian source; a price is changed in the source and copied by row index.
