# Feature — content-mcp

<!-- playbook: v1.25. Lightweight ARCHITECTURE + DATA-MODEL for one
     feature. Lives at docs/features/content-mcp/FEATURE.md next to its
     sprints/. Root ARCHITECTURE.md holds only one row + a link here.
     Keep ≤80 lines. -->

## Purpose & scope
An MCP connector to each city install (Kharkiv `dovira.vet`, Kyiv `kyiv.dovira.vet` — two connections, two databases) through which the owner, the content manager and the developer manage the site's content from Claude (claude.ai web connector, Claude Desktop) in natural language: read every post type, change prices, add pages and blog posts (Kharkiv only), upload images, create and edit blog categories and tags. Every connector user is a real WP user connecting with their own application password; administrators and editors have the same content scope, and only administrators may delete (to the WordPress trash). Every write, in every language, is previewed and happens only with a confirmation token the preview issued; inside the first confirmed write of a session the connector dumps the database into a web-closed folder — a new timestamped file every time. The Russian copy of a changed Ukrainian post is updated in the same session under the same rule.
OUT of scope: site settings (WP options, the ACF options page «Налаштування»), users, plugins, themes; writing to `conversation`, `application`, `questionary` (read-only — status stays with the admin and Dovira Bridge); permanent deletion; roll-back as a connector action; one connector for both cities; a Kyiv blog; translating on the server.

## Fit into the host
- **Code location:** `wp-content/themes/dovira/inc/features/content-mcp/` (namespace `dovira\ContentMcp`; tests in `tests/Unit/ContentMcp/`); the plugin `wp-content/plugins/mcp-adapter/` (third-party, committed) is its transport
- **Host area:** theme `dovira` — obeys its `CLAUDE.md` isolation rules
- **Entry point:** `inc/features/content-mcp/bootstrap.php`, required by one plain line in `functions.php`; it returns at once (the site loads as before) unless `wp_register_ability()` and the adapter's `WP\MCP\Core\McpAdapter` exist, then registers the ability category `dovira` on `wp_abilities_api_categories_init` (core refuses an ability whose category is not registered first), the abilities on `wp_abilities_api_init` and the server on `mcp_adapter_init` (the adapter fires it on `rest_api_init` priority 15, on `init` priority 20 under WP-CLI). The names the server lists and the names registered are one constant, `Catalogue::TOOLS`; every ability carries `meta.mcp.public: false`, so the adapter's default server neither discovers nor executes it, and `meta.show_in_rest: true` for REST `wp-abilities/v1`
- **Session:** the adapter gives an ability only its input, so `Session` reads the `Mcp-Session-Id` header from the REST request of `/mcp/dovira` (`rest_request_before_callbacks`); any other request has the session `''`
- **Shared code it depends on:** the registered post types (`inc/post-types/*.php`) and taxonomy; the ACF local field groups and block definitions (`inc/acf/**`) it introspects — read only, never changed by this feature; Polylang (`pll_get_post`, `pll_set_post_language`, `pll_save_post_translations`); `wp-config.php` constant `DOVIRA_MCP_DUMP_DIR`

## Data
Owns no table and no post type. Owns: the transients `dovira_mcp_confirm_{hash}` (a pending confirmation: user, session, payload hash; 15 min) and `dovira_mcp_dumped_{session}` (24 h); the dump files `dovira-{host}-{YYYY-MM-DD}-{HHMMSS}.sql.gz` in `DOVIRA_MCP_DUMP_DIR` (fallback `wp-content/backup-db/`, with the `.htaccess` and `index.php` it writes there); the daily cron hook `dovira_mcp_dump_retention` and the filter `dovira_mcp_dump_retention_days` (default 90); the server's sessions are the adapter's (user meta). It writes posts, postmeta (through ACF), terms and attachments that belong to `core` — as the connected user, through the same functions the admin uses (details in `docs/DATA-MODEL.md` → Feature `content-mcp`).

## Invariants
- No write without a valid confirmation token for this payload, user and session; a read never issues one.
- No write before the session's dump exists; a failed dump aborts the write with an error. A dump file is never overwritten or renamed.
- The server exposes only `dovira/*` abilities; no write ability exists for `conversation`, `application`, `questionary`; `service-city` terms and the options page are never written; `trash-post` requires `manage_options`.
- ACF values go through `update_field()`, never raw postmeta; price rows are written only by `update-price-row`, which never changes `is_price_general` or the `cities` checklist.
- The Russian copy is written only when the person confirmed its diff; a price mirrors only into a copy with the same number of rows, otherwise the result carries a warning.
- No URL literal of either install; the city comes from the site URL (root invariant 4). No secret in code: authentication is WordPress's own.

## Interfaces
- **MCP server** `https://{host}/wp-json/mcp/dovira` (Streamable HTTP, the adapter's sessions; the adapter's gate is `current_user_can( 'read' )`, so no valid application password = 401); `instructions` (the adapter has no instructions argument and sends the server description as `instructions`, so the text is `Server::INSTRUCTIONS`, the description; MCP tool names are the ability names with `/` → `-`, e.g. `dovira-describe-site`): call `describe-site` first, preview every write, show the diff, write only after consent, Ukrainian is the source and the Russian copy follows.
- **Abilities** (also REST `wp-abilities/v1/abilities/dovira/*` and `wp mcp-adapter serve --server=dovira`): read — `describe-site`, `list-posts`, `get-post`, `find-price-rows`, `list-terms`, `list-media`; write — `update-price-row`, `update-post`, `create-post`, `create-translation`, `upload-media`, `create-term`, `update-term`, `trash-post`.
- **Read access:**
  - Content types (`service`, `employee`, `vacancy`, `page`, `post`) need the type's `edit_posts`, so editors and administrators.
  - Records (`conversation`, `application`, `questionary`) need `edit_others_conversations`, so administrators and the support role, never an editor. It is not `read_post`: core maps that to `read` for a published post.
  - `list-terms` needs the taxonomy's `assign_terms`; `list-media` needs `upload_files`; `find-price-rows` needs `service`'s `edit_posts`.
  - A missing id gets `dovira_post_not_found`; a type outside the scope is refused.
  - Every list is `{items, total, page, per_page}`, with `per_page` ≤ 50 (the schema refuses more).
- **Result shapes:**
  - A post = `{id, type, status, lang, translations:{uk,ru}, title, slug, parent, link, menu_order, modified, terms:{taxonomy:[{id,name}]}, acf:{…by field name; repeater → [{…}], group → {…}, image → {id,url,alt}, file → {id,url,title}, post_object → {id,title}, relationship → [{id,title}]}, blocks:[…]}`.
    - `blocks` is the content of **every** type (blog posts, services and employees are built from `acf/*` blocks too). An `acf/*` block is `{block, fields}`, read by name through ACF's block meta. Any other block is `{block, html}`, its serialized markup. Freeform text is `core/freeform`. There is no `content_html`.
    - A `service`'s `acf.prices[].price` is the price in this install's city (`CityPrice`). It is the general price, or the row's `city_prices.price_city_{term}` for the install's `service-city` term, found by the slug of the URL's city. `null` means no price (the site shows the phone). `city_prices` is not shown.
    - Records and untranslated types have `lang: null` and `translations: {}`. Non-viewable types have `link: null`.
  - A `list-posts` item = `{id, type, status, lang, translations, title, modified, link}`.
  - A term = `{id, name, slug, parent, count, lang}`.
  - A media item = `{id, title, url, alt, mime, width, height, date}`.
  - `describe-site` = `{city, languages, user:{login, roles, can_delete}, post_types:[{name, label, hierarchical, translated, read_only, taxonomies, fields}], blocks:[{name, title, fields}], taxonomies:[{name, label, object_types, translated, read_only, terms (≤50), terms_total}]}`. A field there is `{name, label, type, required, choices?, sub_fields?}`, without `message`/`tab`/`accordion`.
  - A price row (`find-price-rows`: rows of the Ukrainian services whose `title` or `description` contains `text`, any case, as a paginated list) = `{service:{id,title}, row_index (from 0), title, description, price (this city's, `null` = none), per_city}`.
  - **Writes** (`WriteAbility`): the same call twice. Without `confirmation` = a preview `{diff:[{target:{id,lang}, field, from, to}], warnings:[…], confirmation}` and nothing written; an empty diff gives `confirmation: null`. With the token = a write `{written:[…ids], dump, warnings}`, where `dump` is the new file's name or `"existing"`. A token of another payload, user or session, used or older than 15 minutes = `dovira_confirmation_invalid`; a failed dump = `dovira_dump_failed`; both write nothing. Write abilities are annotated `readonly: false, destructive: true, idempotent: false`.
  - `update-price-row` = `{service_id, row_index, title?, description?, price? (> 0), confirmation?}`, permission `edit_post` of the service. `price` goes into the row's own column in this city (`price`, or `city_prices.price_city_{own term}`; a per-city row without that term = `dovira_city_term_missing`). It is changed in the Ukrainian copy only (`dovira_price_in_source` on a Russian one) and follows into the Russian copy's row of the same index; different row counts, or a copy the user cannot edit = a warning and Ukrainian only. `title` / `description` change the targeted copy only: the Russian text is a call on the Russian copy's id. The repeater is written whole through `update_field()`; `is_price_general`, `cities` and the other rows go back as read (ACF stores empty sub-fields of every row, as a save in wp-admin does).
  - `update-post` = `{id, title?, status?, slug?, parent?, menu_order?, terms?, acf?, blocks?, translation?: {id, …the same}, confirmation?}`, permission `edit_post` of every named post; only `service`, `employee`, `vacancy`, `page`, `post` (`dovira_read_only_type` for records). Only the named fields change (`PostWriter`):
    - `status` ∈ `publish|draft|pending|private` (`publish`/`private` need the type's `publish_posts`); `slug` is previewed as stored; `parent` only for hierarchical types; a trashed post is refused.
    - `terms` = `{taxonomy: [id | name]}`, the taxonomy's whole new set; existing terms only, a name looked up in the post's language, `service-city` by id; nothing is created.
    - `acf` = the post's own fields by name in the `get-post` shape, through `update_field()`; a repeater is replaced whole; `service.prices` is refused (`update-price-row`).
    - `blocks` = the whole new content in the `get-post` shape, within the blocks the editor allows on that post (`dovira_allowed_block_types()`: theme `acf/*`; core blocks too on `post`). An `acf/*` block's named fields replace those fields, the unnamed ones of the same block at the same index stay as stored; ACF flattens the data (`acf_setup_meta()` capture). A `{block, html}` must parse as exactly that block.
    - The translation is written only when named in `translation` (its id must be the post's Polylang translation); the preview lists both.
  - Changing these shapes = touches this feature's interface.
- **Filters/hooks:** `dovira_mcp_dump_retention_days`; cron `dovira_mcp_dump_retention`.

## Roadmap
- Sprint 1 — Read everything and change a price from Claude: the connector is reachable from claude.ai on `dev.dovira.vet`, lists and shows every post type with structured ACF, blocks and translations, and changes a price row after a preview, with the session's dump taken first; existing posts are edited and trashed (administrators) under the same protocol (`sprints/SPRINT-1.md`)
- Sprint 2 — Create content: pages from blocks, blog posts, services, employees and vacancies with ACF; Russian copies linked through Polylang; images uploaded and attached; blog terms created and edited; the production checklist for both installs (`sprints/SPRINT-2.md`)
