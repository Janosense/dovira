# Step plans — content-mcp, Sprint 1

<!-- Written by /plan-step, one section per step. Approval of a section
     authorizes that step only. -->

## Plan — Sprint 1, Step 1: Delta-audit, the adapter in the repo, and the spike: a tool in claude.ai and a dump on the host   (status: closed)

### Branch
`content-mcp/sprint-1-read-and-price` ← `master`
- Root `CLAUDE.md` git model: simple, task branch → `master`. The name is the
  **Branch** line of `SPRINT-1.md`.
- Like the city-popup and search-stats sprints, the branch is recreated from
  `master` for each step and deleted at the close.
- Today `master` = `39b6dfff`, one docs commit ahead of `origin/main`
  (`e899201a`).

### Tasks (ordered)
- [x] **1. Delta-audit and baseline gate.** No commit. The findings go into the
  step report.
  - Read the feature docs: `docs/features/{core,search-stats,city-popup,ga-telegram-bridge}/FEATURE.md`.
  - Read the shared code this feature reads:
    - `inc/post-types/*.php`, `inc/taxonomies/city.php`;
    - `inc/acf/fields/*.php`, `inc/acf/blocks/*/fields.php`, `inc/acf.php`;
    - `inc/polylang.php`;
    - `inc/cli/*.php`, for how `PostCopier` copies a post and links its translations;
    - `functions.php`, `bin/check.sh`;
    - the plugin `dovira-bridge-conversations`. Its capability check is the
      precedent for the read-only record types.
  - From `wp-includes/abilities-api.php` (core 7.1.4), record the ability API:
    - the `wp_register_ability( $name, $args )` arguments: `label`,
      `description`, `category` (required), `execute_callback`,
      `permission_callback`, `input_schema`, `output_schema`;
    - the `meta` keys: `annotations` (`readonly` / `destructive` /
      `idempotent`), `public`, `show_in_rest`;
    - the two hooks: abilities are registered on `wp_abilities_api_init`, but a
      category must exist first, registered on `wp_abilities_api_categories_init`.
  - From the installed adapter's `docs/` and `includes/`, record:
    - the `create_server()` arguments: id, route namespace, route, name,
      description, version, transports, error handler, observability, tools,
      resources, prompts, transport permission callback;
    - that `mcp_adapter_init` fires on `rest_api_init` at priority 15, and on
      `init` at priority 20 under WP-CLI;
    - whether a tool listed explicitly on a custom server also needs `meta.mcp.public`;
    - how `HttpTransport` hands the request (headers, `Mcp-Session-Id`) to a
      permission callback;
    - that the `initialize` result's `instructions` is the server description
      (`mcp_adapter_initialize_response` filter).
  - Run `bin/check.sh` on the host as the baseline and record the counts.
- [x] **2. Add the adapter plugin.** Commit:
  `chore(content-mcp): add the mcp-adapter 0.7.1 plugin`.
  **Touches shared code — may affect other features:** the site's plugin set.
  Consumers: every install and every feature. It only adds REST routes under
  `mcp/` and the adapter's default server, and no theme code calls it before
  task 3.
  - Unzip `https://downloads.wordpress.org/plugin/mcp-adapter.0.7.1.zip`
    unchanged into `wp-content/plugins/mcp-adapter/`.
  - Activate it locally with `ddev wp plugin activate mcp-adapter`. This changes
    the local DB only.
  - In the same commit, update `docs/TECH-STACK.md`:
    - the MCP transport row becomes verified: `0.7.1 (verified 2026-11-05 —
      WordPress.org, released 2026-10-16, tested up to 7.1.4; core here 7.1.4)`;
    - the Approved dependencies log row is confirmed as installed.
- [x] **3. Feature bootstrap, the `dovira` server and `dovira/describe-site`, with tests.**
  Commit: `feat(content-mcp): dovira MCP server with describe-site`.
  **Touches shared code — may affect other features:** `functions.php` gets one
  `require_once TEMPLATE_DIR . '/inc/features/content-mcp/bootstrap.php';`
  after city-popup's line, before the `WP_CLI` block. Consumers: every theme
  feature (`core`, `search-stats`, `city-popup`).
  - `bootstrap.php`: requires the classes and attaches hooks only when both
    `function_exists( 'wp_register_ability' )` and
    `class_exists( \WP\MCP\Core\McpAdapter::class )` hold. Without the plugin the
    site loads as before. The `functions.php` line itself stays a plain
    `require_once`, as the theme `CLAUDE.md` requires.
  - Hooks:
    - `wp_abilities_api_categories_init` → `Abilities::register_category()`
      (category `dovira`);
    - `wp_abilities_api_init` → `Abilities::register()`;
    - `mcp_adapter_init` → `Server::register( $adapter )`.
  - `Catalogue` (`dovira\ContentMcp\Catalogue`): `CATEGORY = 'dovira'` and
    `TOOLS = [ 'dovira/describe-site' ]`. This is the one list both the
    registration and the server read.
  - `Abilities`: registers every name in `Catalogue::TOOLS` with:
    - label and description (uk);
    - input and output schema;
    - `permission_callback` = `current_user_can( 'read' )`;
    - `meta.annotations` `{readonly: true, destructive: false, idempotent: true}`
      and `meta.show_in_rest: true`, since FEATURE.md → Interfaces makes
      abilities reachable through REST `wp-abilities/v1`;
    - `meta.mcp.public` too, if task 1 finds a custom server needs it.
  - `DescribeSite`: `execute()` returns
    `{city, languages, user:{login, roles, can_delete}}`:
    - `city` is `kyiv` when `get_site_url()` contains `kyiv`, else `kharkiv`
      (root invariant 4). The check is the same inline one as
      `inc/utils/telegram.php:152`; city-popup's `Sites` is another feature's class.
    - `languages` comes from `pll_languages_list()`. The Polylang check is
      injected: `?bool $polylang = null`, per the TECH-STACK anti-pattern.
    - `user` comes from `wp_get_current_user()`.
    - `can_delete` = `current_user_can( 'manage_options' )`.
  - `Server`: `register( object $adapter )` calls `create_server()` with:
    - id `dovira`, namespace `mcp`, route `dovira`, so the URL is
      `/wp-json/mcp/dovira`;
    - name, version;
    - **the description = the `instructions` text of FEATURE.md → Interfaces**,
      because the adapter returns the description as `instructions`;
    - `[ HttpTransport::class ]`, `ErrorLogMcpErrorHandler::class`, `null`
      observability, `Catalogue::TOOLS`, no resources or prompts;
    - `null` transport permission, which means the adapter's `is_user_logged_in()`.
  - Check locally on DDEV with an application password of a local user:
    `curl -u` to `/wp-json/mcp/dovira`, then `initialize` → `tools/list` →
    `tools/call`. The results go into the report.
  - In the same commit, update `docs/features/content-mcp/FEATURE.md` → Fit into
    the host: the category hook, the description used as instructions, and the
    guard living in `bootstrap.php`.
- [x] **4. Spike: the dump measured on DDEV and on dev.** Commit:
  `chore(content-mcp): dump spike script and host measurements`.
  - `_to_delete/content-mcp-dump-spike.php` at the repo root. The folder is new;
    TECH-STACK names it an ops-artifact folder that the theme never loads.
  - Its first line exits unless `WP_CLI` is defined, so a URL hit on dev runs nothing.
  - The script dumps in PHP only, through `$wpdb`: `SHOW TABLES`,
    `SHOW CREATE TABLE`, `INSERT`s in batches, `gzopen`. It writes to a folder
    given as an argument, outside the webroot.
  - It prints table count, raw and gz size, seconds, `memory_get_peak_usage()`,
    `max_execution_time`, `memory_limit`, and whether `exec` is callable
    (information only).
  - Run it on DDEV for the reference numbers.
  - The user runs it on dev:
    `! ssh … 'wp eval-file …/content-mcp-dump-spike.php <dir>'`, uploading it
    first with scp, because it is not on dev before the push. This was settled
    while planning: SSH and WP-CLI are available on dev.
  - Check from here with `curl -I` that the chosen folder is not served. If only
    `wp-content/backup-db/` is writable, a hand-written `.htaccess` deny there
    must make a direct URL answer 403.
  - Exit criterion: the ~8 MB dump fits in one request with headroom against
    `max_execution_time`, and the folder is closed to the web.
  - Record the numbers and the folder in the step report and in the new
    `docs/features/content-mcp/PRODUCTION-CHECKLIST.md`. Its sections: per-install
    prerequisites (plugin activated, `DOVIRA_MCP_DUMP_DIR`, application passwords,
    the connector header), the spike's measured numbers, and placeholders that
    Sprint 2 Step 3 fills.
  - Also update `docs/ARCHITECTURE.md` → Environments: dev's measured folder and numbers.

### Files to create/change
- `wp-content/plugins/mcp-adapter/**` (new, the WordPress.org 0.7.1 zip unchanged)
- `wp-content/themes/dovira/functions.php` (one line, shared)
- `wp-content/themes/dovira/inc/features/content-mcp/bootstrap.php`, `Catalogue.php`, `Abilities.php`, `DescribeSite.php`, `Server.php` (new)
- `wp-content/themes/dovira/tests/Unit/ContentMcp/ServerTest.php` (new)
- `_to_delete/content-mcp-dump-spike.php` (new, throwaway)
- Docs below

### Tests to write
`tests/Unit/ContentMcp/ServerTest.php` uses PHPUnit and Brain\Monkey. It
stubs `wp_register_ability`, `wp_register_ability_category`,
`current_user_can`, `wp_get_current_user`, `get_site_url`,
`pll_languages_list`, and uses a fake adapter object that records its
`create_server()` arguments.
- Every name `Abilities::register()` registers equals `Catalogue::TOOLS`, and is
  in category `dovira`.
- `Server::register()` passes exactly `Catalogue::TOOLS` as tools, the `mcp` /
  `dovira` route, and a null transport permission (the adapter's
  `is_user_logged_in()`).
- `describe-site`'s `can_delete` is true with `manage_options`, and false for an
  editor (no `manage_options`).
- `city` is `kyiv` on `https://kyiv.dovira.vet`, and `kharkiv` on `dovira.vet`,
  `dev.dovira.vet` and DDEV.
- `languages` is `[]` when Polylang is absent, injected as `false`.
- The permission callback returns `current_user_can( 'read' )`.

No test-critical zone of root `CLAUDE.md` is touched: no form pipeline, no price
grouping, no `dovira/v1` route, no `wp dovira translate*`. The gate's theme
PHPUnit count must rise by the new tests.

### Docs to update
- `docs/TECH-STACK.md`: the adapter row verified with 0.7.1, and the log row
  confirmed (task 2).
- `docs/features/content-mcp/FEATURE.md` → Fit into the host: the category hook
  `wp_abilities_api_categories_init`, the server description as `instructions`,
  and the guard inside `bootstrap.php` (task 3).
- `docs/ARCHITECTURE.md` → Environments: the spike's folder on dev and its
  numbers. That the plugin is activated by hand is already written (task 4).
- `docs/features/content-mcp/PRODUCTION-CHECKLIST.md`: created (task 4).
- `docs/DATA-MODEL.md`: no change, because Step 1 stores nothing.

### Checks
- **ANTI-PATTERNS / CONVENTIONS: none violated.**
  - Only `dovira/*` abilities are on the `dovira` server, and nothing is
    registered on the default server.
  - No ACF write.
  - The city comes from the site URL, and there is no URL literal.
  - The Polylang `function_exists()` check is injected.
  - No tool goes into the theme's `composer.json`.
  - Nothing runtime-loaded goes into `_to_delete/`: the spike is CLI-only and the
    theme never requires it.
  - No `mu-plugins`.
  - The JS convention does not apply, because there is no JS.
- **Docs vs reality:**
  - mismatch: FEATURE.md → Fit names only `wp_abilities_api_init`. Core 7.1.4
    refuses an ability whose category is not registered on
    `wp_abilities_api_categories_init`. Resolved: category `dovira` is
    registered there, and FEATURE.md is updated.
  - mismatch: Step 1 asks for "the `instructions` text" on the server, but the
    adapter's `create_server()` has no instructions argument. Its `initialize`
    returns the server description as `instructions` (adapter trunk; task 1
    confirms this for 0.7.1). Resolved: the instructions text is the server
    description.
  - mismatch: Step 1 says "the registration [in `functions.php`] is guarded by
    `function_exists`/`class_exists`", but the theme `CLAUDE.md` allows exactly
    one `require_once` line there. Resolved: the guard lives at the top of
    `bootstrap.php`.
  - mismatch: the spike script is "kept in `_to_delete/`", which does not exist
    yet. Resolved: it is created at the repo root. `dev`'s FTP sync will carry
    the script, so it exits unless `WP_CLI` is defined.
  - match: `mcp-adapter` 0.7.1 is current on WordPress.org (2026-10-16, tested
    up to 7.1.4, requires WP 6.9 and PHP 7.4). Core here is 7.1.4.
  - note: there is no shared "which city" helper; the city check is inline at
    `inc/utils/telegram.php:152` and `site-header.php:3`. `describe-site` uses
    the same inline check rather than city-popup's `Sites` class.
  - note: `master` is one docs commit ahead of `origin/main`.
- **Design:** n/a. DECISIONS "No UI design phase": the tool catalogue is the surface.
- **Check command:** `bin/check.sh` (docs/TECH-STACK.md → Check command). It
  exists and runs on the host; task 1 records the baseline.
- **Not locally verifiable:**
  - the claude.ai connector (`Authorization: Basic` header, `tools/list`, the
    call as editor and as administrator, 401 without the header). claude.ai
    calls from Anthropic's cloud, so this is checked on `dev.dovira.vet` after
    the user's push to `dev`. The verification guide asks what claude.ai
    detected as authentication, and whether the header had to be typed by hand.
  - the dump on the dev host: verified by the user's one SSH/WP-CLI run inside task 4.

### Questions / ambiguities
none. The one open point, how the spike runs on dev, was settled while
planning: the user runs it over SSH/WP-CLI during `/do-step`.

### Step report (`/do-step`, 2026-11-05)
**Gate.** Baseline green: plugin 317 tests, 147 files linted, theme 166, Vitest 59. After the step it is still green: theme **182** (+16, `ServerTest`) and 153 files linted.

**Delta-audit (task 1).**
- **Ability API in core 7.1.4** (`wp-includes/abilities-api.php:290`):
  - Arguments: `wp_register_ability( $name, { label, description, category, execute_callback, permission_callback, input_schema?, output_schema?, meta?, ability_class? } )`.
  - `meta` holds `annotations` (`readonly` / `destructive` / `idempotent`), `public` (seeds `show_in_rest`) and `show_in_rest`.
  - It may be called only on `wp_abilities_api_init`. The category must be registered first, on `wp_abilities_api_categories_init`.
  - With an empty input schema, core accepts only a `null` input.
- **Adapter 0.7.1:**
  - `create_server( id, route_namespace, route, name, description, version, transports[], error_handler, observability = null, tools = [], resources = [], prompts = [], transport_permission_callback = null )`.
  - `mcp_adapter_init` fires from `McpAdapter::init()`: on `rest_api_init` priority 15, or on `init` priority 20 under WP-CLI.
  - `initialize` returns `instructions` = the server description (filter `mcp_adapter_initialize_response`).
  - Tool names are the ability names with `/` → `-` (`McpNameSanitizer`).
  - The zip has no `docs/` folder; everything here was read from `includes/`.
- **Exposure:**
  - A custom server lists the abilities it is given by name, with no `public` check.
  - `meta.mcp.public` (falling back to `meta.public`) only decides whether the adapter's *default* server can discover and execute an ability.
  - So every `dovira/*` ability carries `meta.mcp.public: false`, which is the TECH-STACK anti-pattern "never on the default server". The plan's conditional "`meta.mcp.public` too, if needed" is resolved as `false`.
  - Checked live: the default server's `execute-ability` answers "not found" for `dovira/describe-site`.
- **Transport gate:**
  - With a null permission callback the gate is `current_user_can( apply_filters( 'mcp_adapter_default_transport_permission_user_capability', 'read' ) )`, not literally `is_user_logged_in()` as the adapter docblock and DECISIONS say. The effect is the same for every role here, all of which have `read`.
  - Anonymous requests get REST `rest_forbidden`, HTTP 401.
- **Sessions:**
  - `initialize` returns the `Mcp-Session-Id` header. Every later call must send it, plus `MCP-Protocol-Version`.
  - The tool-call context (`McpToolCallContext`) does not carry the session id, so Step 3 reads it from the request header. That input is for Step 3; it changes nothing here.
- **Shared code (unchanged, read for Steps 2–4):**
  - `service`, `employee`, `vacancy`: `capability_type` `page`, `show_in_rest` true.
  - `conversation`, `application`, `questionary`: `capability_type` `conversation`. `dovira-bridge-conversations` gates on `edit_others_conversations` (`dovira_bridge_conv_capability()`), which is the precedent for the read-only types.
  - 21 ACF blocks; field groups `inc/acf/fields/post-type-*.php` + `option-page-settings.php`.
  - `PostCopier::copy()` links translations with `pll_set_post_language()` + `pll_save_post_translations()` (`inc/cli/PostCopier.php:102`).
  - `functions.php`: content-mcp's line comes after city-popup's.

**Implementation notes.**
- `describe-site` takes no input, so it has no input schema. The adapter publishes `{type: object, properties: {}}` and turns a client's `{}` into `null`.
- The agent's instructions are `Server::INSTRUCTIONS`, in English (agent-facing); the labels and descriptions of the ability are Ukrainian.
- A failed `create_server()` is logged with `error_log()` and is never fatal.

**Local run on DDEV (task 3).** Done with a temporary editor user and application passwords, both deleted afterwards:
- `initialize` → `serverInfo {Dovira, 0.1.0}` + the instructions; `tools/list` → exactly `dovira-describe-site` with `readOnlyHint` and `idempotentHint`.
- `tools/call` as administrator → `{city: kharkiv, languages: [uk, ru], user: {…, roles: [administrator], can_delete: true}}`; as editor → `can_delete: false`.
- No auth → HTTP 401.
- REST `wp-abilities/v1/abilities/dovira/describe-site/run` works for the editor.
- `wp mcp-adapter list` → `dovira` (1 tool) and the default server (3).

**Spike (task 4).** The numbers are in `PRODUCTION-CHECKLIST.md` → B.
- DDEV: 0.48 s, 44.5 MB peak.
- `dev.dovira.vet`: 18 tables, 7.18 MB → 0.70 MB gz, 0.75 s, 96.5 MB peak, mode 0600, folder `/home/dovirave/dovira-dump-spike` outside the webroot. The user ran it over SSH.
- Exit criteria met.
- Open:
  - The web values of `max_execution_time` and `memory_limit` were not read; the account `php.ini` says 512M. A peak of 96.5 MB is above `WP_MEMORY_LIMIT` 40M, so Step 3's verification of a real dump inside a web request on dev settles this.
  - Dev's webroot is nested inside `/home/dovirave/dovira.vet/`, so dump folders go directly under `/home/dovirave/`.
- The claude.ai half of the spike (header, detected authentication) is the verification guide's items 3–6.

## Plan — Sprint 1, Step 2: Reading: describe the site, list and open any post, terms and media   (status: closed)

### Branch
`content-mcp/sprint-1-read-and-price` ← `master`. The branch is recreated from
`master` = `e5a51f84` (Step 1 merged). The only branch not merged into
`master` is `kyiv`, the deploy branch.

### Tasks (ordered)
Written for the recommended answer to Question 1 (A).

- [x] **1. `Scope` and the full `describe-site`.** Commit:
  `feat(content-mcp): describe-site lists types, fields, blocks and terms`.
  - `Scope` (`dovira\ContentMcp\Scope`):
    - `CONTENT = [service, employee, vacancy, page, post]`,
      `RECORDS = [conversation, application, questionary]`.
    - `can_read_type( $type )`:
      - content type → `current_user_can( $type_obj->cap->edit_posts )`;
      - record type → `current_user_can( $type_obj->cap->edit_others_posts )`,
        which is `edit_others_conversations`. Only the administrator and the
        support role have it.
      - Any type outside the scope → false.
    - The reason it is not `read_post`: see Checks → Docs vs reality.
  - `Schema` (`dovira\ContentMcp\Schema`) builds the description:
    - **Per type:** name, label, hierarchical, whether Polylang translates it,
      taxonomies, and `fields`.
      - `fields` comes from the ACF local field groups whose location names
        that `post_type` (`acf_get_local_field_groups()` → `acf_get_fields()`).
      - A field is `{name, label, type, required, choices?, sub_fields?}`.
      - UI-only types (`message`, `tab`, `accordion`) are dropped.
    - **Per block:** each `acf_get_block_types()` entry
      `{name, title, fields}`, with fields from the group whose location is
      `block == name`.
    - **Taxonomies** `category`, `post_tag`, `service-city`: label, object
      types, translated, and terms `{id, name, slug, lang?}`, capped at 50
      plus `terms_total`.
    - **`service.prices`:** `city_prices` is dropped, and `price` is described
      as «ціна в місті цього сайту; null — ціни немає, сайт показує телефон».
    - The ACF and Polylang `function_exists()` checks are injected.
  - `DescribeSite::execute()` = Step 1's `{city, languages, user}` plus
    `{post_types, blocks, taxonomies}`.
  - The options page group `group_settings` is out of scope and never
    described.
- [x] **2. Reading one post: `PostReader` with ACF, blocks, translations and
  the price in this city.** Commit:
  `feat(content-mcp): PostReader — one post in the connector's shape`.
  - `FieldValues` (`dovira\ContentMcp\FieldValues`) normalizes ACF formatted
    values, by field name, through the field definitions:
    - image → `{id, url, alt}`;
    - file → `{id, url, title}`;
    - gallery → `[{id, url, alt}]`;
    - post_object / relationship / page_link with ids → `{id, title}` or a
      list of them;
    - taxonomy → `[{id, name}]`;
    - repeater → `[{…}]`, group → `{…}`, recursing into both;
    - every other type → as ACF returns it.
    - An image or file given as id, url or array always ends in the same
      shape.
  - `CityPrice` (`dovira\ContentMcp\CityPrice`): `term_id( $city )` finds the
    install's own `service-city` term by slug, with the city from the site
    URL. `resolve( $row, $term_id )` gives:
    - `is_price_general` true → `price`;
    - otherwise → `city_prices['price_city_{term_id}']`;
    - empty or missing → `null`, never a made-up number (root invariant 3).

    This is the site's own rule (`single-service.php:116`, `search.php:110`).
    Step 3's `PriceRows` reuses it.
  - `PostReader` (`dovira\ContentMcp\PostReader`): `read( WP_Post )` → the
    FEATURE.md post shape:
    - `{id, type, status, lang, translations:{uk,ru}, title, slug, parent, link, menu_order, modified, terms:{taxonomy:[{id,name}]}, acf:{…}, blocks:[…]}`.
    - `acf` = `get_fields( $id )` normalized by `FieldValues`. For a `service`,
      every `prices` row has `price` resolved by `CityPrice`, and
      `city_prices` is removed. `is_price_general`, `cities`, `linked_page`
      are kept.
    - `blocks`, for every post with non-empty `post_content` (Question 1):
      `parse_blocks()`, then:
      - an `acf/*` block → `{block, fields}`. The fields come from ACF's own
        block meta (`acf_setup_meta( $data, $block_id, true )` →
        `get_fields( $block_id )` → `acf_reset_meta()`), normalized by
        `FieldValues`. This was checked on page 176 while planning.
      - any other block → `{block, html}`, with `html` = `serialize_block()`
        of it.
      - freeform text → `{block: "core/freeform", html}`.
    - `lang` / `translations` come from `pll_get_post_language()` /
      `pll_get_post_translations()`. Record types are untranslated:
      `lang: null`, `translations: {}`.
    - `link` = `get_permalink()` for a viewable type, otherwise `null`.
- [x] **3. The four read abilities on the server.** Commit:
  `feat(content-mcp): list-posts, get-post, list-terms, list-media`.
  - Each gets the Step 1 pattern: `args()` with an input schema and an output
    schema, `meta.annotations` readonly, `meta.mcp.public: false`,
    `show_in_rest: true`. Each is added to `Catalogue::TOOLS` and
    `Abilities::definitions()`.
  - **`dovira/list-posts`**
    - Input: `type` (required, from `Scope`), `search`, `status`
      (`publish|draft|pending|private|future|any`, default `any`), `lang`
      (`uk|ru|all`, default `uk`; ignored for record types), `terms`
      `{taxonomy: [ids]}`, `page` ≥ 1, `per_page` 1–50 (default 20).
    - Output: `{items:[{id, type, status, lang, translations, title, modified, link}], total, page, per_page}`.
    - Permission: `Scope::can_read_type( type )`.
  - **`dovira/get-post`**
    - Input: `id`.
    - Output: the `PostReader` shape.
    - Permission: the post exists, its type is in scope, and
      `Scope::can_read_type()`.
    - An editor gets a permission error on the three record types, not an
      empty object.
  - **`dovira/list-terms`**
    - Input: `taxonomy` (`category|post_tag|service-city`), `search`, `lang`,
      `page`, `per_page` ≤ 50.
    - Output: `{items:[{id, name, slug, parent, count, lang}], total, …}`.
    - Permission: `current_user_can( $tax->cap->assign_terms )`.
  - **`dovira/list-media`**
    - Input: `search`, `mime` (e.g. `image`), `page`, `per_page` ≤ 50.
    - Output: `{items:[{id, title, url, alt, mime, width, height, date}], total, …}`.
    - Permission: `upload_files`.
  - Pagination and caps: a schema `maximum` of 50 for `per_page`. Execute also
    clamps it, because the REST route validates against the schema too.
  - Run locally on DDEV with temporary application passwords (administrator,
    editor), deleted afterwards:
    - `tools/list` shows 5 tools;
    - `get-post` on a service, on page 176 and on a blog post;
    - the editor's `get-post` / `list-posts` on `conversation` are refused,
      the administrator's are listed.
  - In the same commit, update FEATURE.md → Interfaces (shapes as
    implemented).

### Files to create/change
- New, in `wp-content/themes/dovira/inc/features/content-mcp/`: `Scope.php`,
  `Schema.php`, `FieldValues.php`, `CityPrice.php`, `PostReader.php`,
  `ListPosts.php`, `GetPost.php`, `ListTerms.php`, `ListMedia.php`.
- Changed in the same folder: `DescribeSite.php`, `Catalogue.php`,
  `Abilities.php`, `bootstrap.php` (requires).
- New tests, in `wp-content/themes/dovira/tests/Unit/ContentMcp/`:
  `SchemaTest.php`, `FieldValuesTest.php`, `CityPriceTest.php`,
  `PostReaderTest.php`, `ReadAbilitiesTest.php`.
- Changed test: `ServerTest.php`. The catalogue now has 5 names; the test
  still asserts `TOOLS` equals the registered names.
- Docs below.
- No shared code: `inc/post-types`, `inc/acf/**` and `functions.php` are only
  read.

### Tests to write
PHPUnit with Brain\Monkey. ACF (`acf_get_local_field_groups`,
`acf_get_fields`, `acf_get_block_types`, `get_fields`, `acf_setup_meta`,
`acf_reset_meta`), Polylang and WP functions are stubbed. Every
`function_exists()` check is injected.
- **`SchemaTest`**
  - A fixture of the service, page and block groups produces the
    description.
  - `message` / `tab` fields are dropped.
  - `prices` has no `city_prices`, and `price` carries the "this city"
    description.
  - The options group is absent.
  - Terms are capped at 50, with `terms_total`.
  - Without ACF or Polylang there are no fields / no languages, and no error.
- **`CityPriceTest`** (test-critical zone «per-city price grouping»):
  - a general row gives `price`;
  - a per-city row gives the own city's `price_city_{id}`;
  - an empty or missing column gives `null`;
  - a Kyiv URL picks the `kyiv` term;
  - an install with no term for its city gives `null` for every per-city
    row, and the general rows keep their price.
- **`FieldValuesTest`**
  - Image as id / url / array all give `{id,url,alt}`.
  - post_object gives `{id,title}`.
  - Repeaters and groups are normalized recursively.
  - Unknown types pass through.
- **`PostReaderTest`**
  - The service's `prices` are resolved and `city_prices` is gone.
  - An `acf/*` block's flattened data (`years_0_year` + `_years_0_year`)
    comes out as `fields.years[0].year` through the meta API calls.
  - A core block gives `{block, html}`; a freeform gives `core/freeform`.
  - A blog post made of `acf/rich-text` gives `blocks`.
  - `translations` / `lang` come from Polylang.
  - A record type has `lang: null`; a non-viewable type has `link: null`.
- **`ReadAbilitiesTest`**
  - An editor (no `edit_others_conversations`) is refused `get-post` /
    `list-posts` on each of the three record types; an administrator is not.
  - A type outside the scope is refused.
  - `per_page` 500 is clamped to 50.
  - `list-media` without `upload_files` is refused.
  - Every catalogued ability has an output schema and `readonly: true`.

### Docs to update
- `docs/features/content-mcp/FEATURE.md` → Interfaces: the post shape as
  implemented (`blocks` for every type, `{block, html}` for non-ACF blocks,
  service `prices[].price` resolved, no `content_html`), plus the list shapes
  and permissions.
- `docs/DATA-MODEL.md` → Feature `content-mcp`: one line, "reads `service`
  prices through the same general/per-city rule as `single-service.php`; stores
  nothing". Nothing else, since the reader adds no data.

### Checks
- **ANTI-PATTERNS / CONVENTIONS: none violated.**
  - ACF is read only through `get_fields()` / ACF's block meta, never
    `get_post_meta()` by stored key.
  - `service-city` is read, never written.
  - The city comes from the site URL.
  - `function_exists()` checks are injected.
  - Only `dovira/*` tools are on the server, with `meta.mcp.public: false`.
  - No URL literal, no new dependency.
- **Docs vs reality:**
  - mismatch: Step 2 says the record types' permission is "`read_post` of that
    type".
    - All 91 local records are `publish`, and for a public status core maps
      `read_post` to `$post_type->cap->read` = `read`
      (`wp-includes/capabilities.php:369`). The editor has `read`, so they
      would be **allowed**.
    - Resolved: `edit_others_posts` of the type (`edit_others_conversations`),
      the precedent of `dovira-bridge-conversations`
      (`dovira_bridge_conv_capability()`). It refuses the editor as the step
      requires.
  - mismatch, settled by Question 1:
    - DECISIONS "The tool catalogue speaks ACF field names and block names…"
      and FEATURE.md say a blog post is "core-block HTML" read as
      `content_html`.
    - In the local DB, 23 of 25 posts are built from `acf/*` blocks (mostly
      `acf/rich-text`) and 2 from core blocks.
    - 7 services (`templates/sub-service.php`) and 28 employees also carry
      `acf/*` block content that their templates render with `the_content()`.
  - mismatch: ACF block data is stored flattened, the same way as postmeta
    (`years_0_year` plus `_years_0_year` → field key, a repeater count under
    its name), not as "`field_key → value` and `_name → field_key` pairs".
    - Resolved: read through ACF's own `acf_setup_meta()` / `get_fields()`,
      which returns names and lists (checked on page 176: `years` → 11 rows),
      instead of a hand-written mapper.
    - Step 4's writer will need the reverse, ACF's flattening on save; that is
      for Step 4's plan.
  - match:
    - 1160 of 1444 price rows are per-city (`is_price_general = 0`) and keep
      their amount in `city_prices.price_city_4`, so a raw `price` would be
      empty for 80% of rows.
    - The "price in this city" rule above is the site's own
      (`single-service.php:116`).
  - note: this install has one `service-city` term (`kharkiv`, id 4). The
    Kyiv install's term slugs are unknown. If its own term is not slug `kyiv`,
    `price` is `null` for its per-city rows. Sprint 2 Step 3's production
    checklist checks it.
  - note: 4 services use `templates/sub-service.php`, which the service field
    group excludes. Their `acf` is `{}` and their content is in `blocks`.
  - note: ACF Pro installed is 6.8.10, while TECH-STACK says 6.8.8. That
    changes no task and is left for an `/adhoc`.
- **Design:** n/a. DECISIONS "No UI design phase".
- **Check command:** `bin/check.sh` (docs/TECH-STACK.md → Check command).
  Green on `master` after Step 1: theme 182.
- **Not locally verifiable:** the claude.ai conversation items of the
  verification («що є на сайті?», «покажи послугу Терапія»…). They are
  checked on `dev.dovira.vet` after the user's push to `dev`. Every ability
  is also run locally over HTTP in task 3.

### Questions / ambiguities
1. **How is a post's content read, when blog posts (and some services and
   employees) are built from `acf/*` blocks?** The answer changes the
   `PostReader` output shape, its tests, and FEATURE.md → Interfaces, which
   is the feature's interface.
   - **A (recommended):** every post with content is read as
     `blocks: [...]`, pages and blog posts alike:
     - `acf/*` → `{block, fields}`;
     - any other block → `{block, html}`, its serialized markup;
     - `content_html` is dropped from the shape.

     The agent then sees a blog post's text as named fields. The same shape
     works for the 7 services and 28 employees that carry blocks. Step 4's
     writer gets a shape it can write back. At the close this amends the
     DECISIONS catalogue entry with a new entry.
   - **B:** keep FEATURE.md as written: a `post` gets `content_html` = raw
     `post_content`. For 23 of 25 posts the agent then sees ACF JSON inside
     block comments, and Step 4 would write raw block markup it can easily
     corrupt. Services and employees would get no content at all.
   - **C:** `content_html` = rendered HTML (`apply_filters( 'the_content' )`).
     Readable, but it cannot be written back, and it differs from what the
     editor stores.
   - Resolved: approved as recommended — A: every post with content is read as `blocks` (`acf/*` → `{block, fields}`, any other → `{block, html}`), no `content_html`.

### Step report (`/do-step`, 2026-11-05)
**Gate.** Green after every task. The theme PHPUnit count went 182 → 190 → 225 → **245**, and 168 files are linted.

**Built as planned:**
- `describe-site` describes the content model. Live on DDEV:
  - 8 types, 21 blocks;
  - `category` 2 terms, `post_tag` 0, `service-city` 1;
  - `service.prices` has `price` with the "this city" description and no `city_prices`.
- **Size:** the whole description is ~59 KB, most of it the 21 blocks' fields with their choice lists. It is one call per conversation and is not trimmed in this step.
- `PostReader` and the four read tools match the plan; the shapes are recorded in FEATURE.md → Interfaces.

**Local MCP run on DDEV.** Done with a temporary editor user and application passwords, both deleted afterwards:
- `tools/list` → `describe-site`, `list-posts`, `get-post`, `list-terms`, `list-media`.
- Service 18 «Приймальне відділення»:
  - `lang: uk`, `translations: {uk: 18, ru: 1641}`;
  - `prices[0].price: 550`, read from the Kharkiv column of a per-city row, with no `city_prices`.
- Page 176: `acf/about` with `years[0] = {year: 2014, title: «Початок шляху», …}`.
- Blog post 1498: `terms.category = [Без категорії]`, `blocks = [acf/rich-text]` with named fields.
- `list-terms category`, `list-media mime=image` (394 in the library): items as specified.
- `per_page: 500` → refused by the schema: «input[per_page] повинен бути між 1 і 50».
- Administrator `list-posts conversation` → 89. Editor `list-posts` / `get-post` on conversation 1730 → «Permission denied».
- Editor `get-post 18` → read.
- Missing id → «Запису з id 999999 немає.» Attachment 768 (outside the scope) → refused.

**Notes:**
- Implementation choices:
  - The read abilities share `ReadTools` (meta, paging, list schema).
  - `ListPosts` and `ListMedia` take an injectable query callable, so the query args are unit-tested without `WP_Query`.
  - `Schema::term()` and `DescribeSite` take `object`/injected collaborators, because the test bootstrap loads no `WP_Term` and no core block functions.
- `GetPost::can()` lets a missing id through, so the agent hears "not found" rather than "not allowed".
- `list-terms` returns the slug as WordPress stores it; the Ukrainian category slug is percent-encoded.
- For Step 4: ACF block data is flattened on save. Writing blocks back will go through ACF's own flattening, the reverse of `acf_setup_meta`.
