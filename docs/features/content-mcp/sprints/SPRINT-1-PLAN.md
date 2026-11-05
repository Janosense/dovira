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

## Plan — Sprint 1, Step 3: The write protocol: confirmation tokens, the session dump, and the price change   (status: closed)

### Branch
`content-mcp/sprint-1-read-and-price` ← `master`. The branch is recreated from
`master` = `c478d90b` (Step 2 merged). The only branch not merged into
`master` is `kyiv`, the deploy branch.

### Tasks (ordered)
- [x] **1. `Session`, `Confirmation`, `Diff`.** Commit:
  `feat(content-mcp): confirmation tokens bound to user, session and payload`.
  - `Session` (`dovira\ContentMcp\Session`) gives the current MCP session id:
    - The adapter does not pass `Mcp-Session-Id` to an ability (see Checks →
      Docs vs reality).
    - So `Session::capture()` hooks `rest_request_before_callbacks` (filter,
      returns the response unchanged). It keeps the request's
      `mcp-session-id` header only when the route is `/mcp/dovira`, where the
      adapter has already checked the session against the user's own sessions.
    - `Session::id()` returns `''` for every other request: REST
      `wp-abilities/v1`, WP-CLI, and the stateless MCP revision 2026-07-28.
  - `Confirmation` (`dovira\ContentMcp\Confirmation`):
    - **`issue( $user_id, $session_id, array $payload, array $diff )`** returns
      a random token. It stores the transient `dovira_mcp_confirm_{hash}` for
      15 minutes.
      - `hash` = SHA-256 of (user id, session id, canonical JSON of the
        payload). The canonical JSON is recursively key-sorted, with no
        `confirmation`.
      - The value is `{user_id, session, payload_hash, token_hash, diff, issued_at}`.
        The token itself is never stored, only its SHA-256.
      - A second preview of the same payload replaces the first one's token.
    - **`verify( $token, $user_id, $session_id, $payload )`** computes the same
      key and compares `token_hash` with `hash_equals`.
      - It deletes the transient before returning true, so a token writes once.
      - A token of another payload, user or session finds no transient, or a
        different hash, so it gets false.
  - `Diff` (`dovira\ContentMcp\Diff`) is the one preview format:
    - `add( $id, ?$lang, $field, $from, $to )` makes a
      `{target:{id, lang}, field, from, to}` line. A line where from equals to
      is dropped.
    - `warn( $text )` adds a warning.
    - `is_empty()`; `to_array()` gives `{diff, warnings}`.
  - `bootstrap.php`: requires the three classes, adds the
    `rest_request_before_callbacks` filter.
- [x] **2. `Dump` and its retention.** Commit:
  `feat(content-mcp): the session dump and its 90-day retention`.
  - `Dump` (`dovira\ContentMcp\Dump`), constructor
    `( ?object $wpdb = null, ?string $dir = null, ?string $fallback = null, ?string $host = null )`.
    - `null` means: global `$wpdb`; `DOVIRA_MCP_DUMP_DIR` when defined;
      `WP_CONTENT_DIR . '/backup-db'`; the host of `home_url()`.
    - Tests inject a fake `$wpdb` and temp dirs.
  - **`ensure( string $session_id ): string`.**
    - With a non-empty session and the transient `dovira_mcp_dumped_{session}`
      present, it returns `"existing"`.
    - Otherwise it writes `dovira-{host}-{Y-m-d}-{His}.sql.gz`, in site time
      (`wp_date`). An existing name gets `-2`, `-3`, … and nothing is ever
      overwritten.
    - Contents: a header comment (host, date); `SET NAMES utf8mb4`; then per
      table from `SHOW TABLES`: `DROP TABLE IF EXISTS`, `SHOW CREATE TABLE`,
      and `INSERT`s of 500 rows. That is the spike's proven loop, values
      escaped with `$wpdb->_real_escape`.
    - The file ends with a footer comment: seconds and peak memory. That is
      how the dump's duration in a real web request on dev is read
      (`zcat … | tail -2`), the open item of Step 1.
    - Then `chmod 0600`, and the transient (24 h) when there is a session. It
      returns the file name.
    - With an empty session it always dumps and sets no transient.
  - **The folder.**
    - With `DOVIRA_MCP_DUMP_DIR` it is created with `wp_mkdir_p` if missing and
      used as is.
    - In the fallback it is created, then `.htaccess` (`Require all denied`)
      and an empty `index.php` are written when absent.
    - An unwritable folder throws.
  - **Any failure throws `\RuntimeException`:** a query with `last_error`, an
    empty `SHOW CREATE TABLE`, `gzopen` / `gzwrite` failing. The half-written
    file is closed and deleted first. The caller writes nothing.
  - **Retention.**
    - `Dump::schedule()` on `init`: `wp_schedule_event( time(), 'daily',
      'dovira_mcp_dump_retention' )` when `wp_next_scheduled` is false.
    - `Dump::retain()` on that event deletes, in the folder in use, only files
      matching `dovira-*-YYYY-MM-DD-HHMMSS[-N].sql.gz` whose mtime is older
      than `max( 1, (int) apply_filters( 'dovira_mcp_dump_retention_days', 90 ) )`
      days.
  - `bootstrap.php`: requires `Dump`, adds both hooks.
- [x] **3. `PriceRows` and `dovira/find-price-rows`.** Commit:
  `feat(content-mcp): find-price-rows`.
  - `PriceRows` (`dovira\ContentMcp\PriceRows`), constructor
    `( ?bool $polylang = null, ?callable $services = null )`, injected as in
    Step 2.
  - **`find( $text )`** reads the `prices` rows (`get_field( 'prices', $id )`)
    of every `service` in Ukrainian (`lang => 'uk'` with Polylang; all
    services without it), any status but trash.
    - It matches rows whose `title` or `description` contains `$text`,
      case-insensitively (`mb_stripos`).
    - Each match is a row of FEATURE.md: `{service:{id,title}, row_index,
      title, description, price, per_city}`.
      - `row_index` counts from 0.
      - `per_city` = not `is_price_general`.
      - `price` comes from `CityPrice::resolve()` with the install's own term
        (Step 2's rule), so an empty column is `null`, never a number.
  - **`update( $service_id, $row_index, $changes )`** plans the change and
    returns a `Diff` plus the writes.
    - **The ACF write.** It reads the repeater raw
      (`get_field( 'prices', $id, false )`, rows keyed by field key) and
      changes only the targeted value. The keys come from the field object's
      `sub_fields`. Then `update_field( 'prices', $rows, $id )`.
      `is_price_general`, `cities`, `linked_page` and every other row go back
      as they were read. TECH-STACK ANTI-PATTERNS: no raw postmeta.
    - **The price** goes into that row's own resolved column: `price` when the
      row is general, otherwise `city_prices.price_city_{own term}`. A
      per-city row on an install without its own term is an error
      (`dovira_city_term_missing`), never a guess.
    - **The Russian copy.** When the service is the Ukrainian copy and its
      Russian copy (`pll_get_post( $id, 'ru' )`) has the same number of rows,
      a `price` change is applied to the Russian row of the same index, in
      that row's own resolved column, with its own diff line. A different row
      count gives a warning, and only the Ukrainian copy is written. If the
      user cannot `edit_post` the copy, that also gives a warning.
    - **`title` / `description`** are written to the targeted copy only. Text
      is per language (see Checks → Docs vs reality). The Russian text is a
      second call that targets the Russian copy's id.
    - **A `price` on a Russian copy** is refused (`dovira_price_in_source`):
      the price is changed in the Ukrainian copy and follows into the Russian
      one (root invariant 7).
  - Ability `dovira/find-price-rows`: input `{text (required, minLength 1),
    page, per_page}`; output `{items, total, page, per_page}`, ≤ 50 per page
    (FEATURE.md: every list). Permission: `Scope::can_read_type( 'service' )`.
    Meta: `ReadTools::meta()`.
  - `Catalogue::TOOLS` and `Abilities::definitions()` gain it; `bootstrap.php`
    requires the class.
- [x] **4. `WriteAbility` and `dovira/update-price-row`; the server
  instructions.** Commit: `feat(content-mcp): update-price-row behind
  preview, token and dump`.
  - `WriteAbility` (`dovira\ContentMcp\WriteAbility`, abstract):
    - **Input:** each write ability's input schema plus `confirmation`
      (string, optional).
    - **Meta:** annotations `readonly: false`, `destructive: true` (a value is
      overwritten), `idempotent: false`; `show_in_rest: true`;
      `mcp.public: false`.
    - **`run( $input )`:**
      1. payload = input without `confirmation`;
      2. `preview( $payload )` (abstract) validates and gives a `Diff`, or a
         `WP_Error`. Nothing is issued on an error (root invariant 8);
      3. without `confirmation`: an empty diff returns
         `{diff:[], warnings:[…, «нічого не змінюється»], confirmation:null}`.
         Otherwise `{diff, warnings, confirmation: Confirmation::issue(…)}`;
      4. with `confirmation`, a failed `verify()` returns
         `dovira_confirmation_invalid` («підтвердження не дійсне для цих змін —
         покажіть новий перегляд»), and nothing is written;
      5. `Dump::ensure( Session::id() )`. A throw becomes `dovira_dump_failed`
         with the reason, and nothing is written;
      6. `write( $payload )` (abstract) returns
         `{written:[ids], dump: file name | "existing", warnings}`.
  - `UpdatePriceRow` (`dovira/update-price-row`) extends it:
    - **Input:** `{service_id, row_index, title?, description?, price?
      (number > 0), confirmation?}`, at least one of the three.
    - **Permission:** a missing post passes, so execute says
      `dovira_post_not_found`. Otherwise it needs `current_user_can(
      'edit_post', $id )`, and the post must be a `service`.
    - `preview` / `write` = `PriceRows::update()`.
  - `Catalogue::TOOLS` / `Abilities::definitions()` / `bootstrap.php` gain it.
  - `Server::INSTRUCTIONS` changes to say:
    - `describe-site` first;
    - for a price, `find-price-rows`, then `update-price-row`;
    - every write is called first without `confirmation`, the diff is shown,
      and the write is repeated with the token only after consent;
    - a changed request means a new preview;
    - the price follows into the Russian copy by itself, while Russian text is
      written as a separate change of the Russian copy;
    - Ukrainian is the source.
  - **Live check in DDEV, not part of the gate**, as in Step 2. A temporary
    administrator and application password, removed afterwards, drive MCP
    `tools/call` over `curl` on `https://dovira.ddev.site/wp-json/mcp/dovira`:
    - a preview, then a write of one general row and one per-city row of a
      local service;
    - `get-post` shows the new prices in both copies;
    - one dump file for two writes in one session;
    - the repeater still opens in wp-admin (its `_prices_*` key rows intact).

### Files to create/change
All under `wp-content/themes/dovira/` unless named otherwise. No shared code is
touched: `functions.php`, `inc/acf/**` and the templates are only read.
- `inc/features/content-mcp/Session.php`, `Confirmation.php`, `Diff.php`,
  `Dump.php`, `PriceRows.php`, `FindPriceRows.php`, `WriteAbility.php`,
  `UpdatePriceRow.php` (new)
- `inc/features/content-mcp/bootstrap.php`, `Catalogue.php`, `Abilities.php`,
  `Server.php` (changed)
- `tests/Unit/ContentMcp/SessionTest.php`, `ConfirmationTest.php`,
  `DiffTest.php`, `DumpTest.php`, `PriceRowsTest.php`, `WriteAbilityTest.php`
  (new); `ServerTest.php` (changed: seven tools, the new instructions)
- `docs/features/content-mcp/FEATURE.md`, `docs/DATA-MODEL.md`,
  `docs/ARCHITECTURE.md`, `docs/features/content-mcp/PRODUCTION-CHECKLIST.md`

### Tests to write
PHPUnit + Brain\Monkey, no WordPress. The per-city price of a Service is a
test-critical zone (root `CLAUDE.md`).
- **`SessionTest`:**
  - the header of a `/mcp/dovira` request (a real `WP_REST_Request`) is kept;
  - another route's header is ignored;
  - no header gives `''`;
  - the response passes through unchanged.
- **`ConfirmationTest`:**
  - a token verifies for the same user, session and payload, regardless of
    key order;
  - a second `verify` of it is false (single use: the transient is deleted);
  - another user, another session, another payload, or an unknown token is
    false;
  - an expired transient is false;
  - `set_transient` gets 900 s, and the stored value has no plain token.
- **`DiffTest`:** the line shape; an unchanged value gives no line; warnings.
- **`DumpTest`** (fake `$wpdb`, temp dirs):
  - the name pattern, and `-2` on a collision;
  - the SQL: `CREATE`, `INSERT`s batched at 500, escaped values, the footer;
  - mode `0600`;
  - `.htaccess` + `index.php` written in the fallback only;
  - `"existing"` for a dumped session;
  - an empty session dumps every time and sets no transient;
  - a table whose query fails throws and leaves no file;
  - an unwritable folder throws;
  - retention deletes only matching files older than the filtered days, never
    other files, and treats a filter value below 1 as 1;
  - `schedule()` schedules once.
- **`PriceRowsTest`:**
  - search by title and by description, case-insensitive, Ukrainian copies
    only;
  - `price` from the general or the own-city column, and `null` for an empty
    column;
  - `update` writes the general `price` or the own city column through
    `update_field`, with `is_price_general` and `cities` unchanged;
  - the price is mirrored into the Russian row of the same index;
  - the warning and Ukrainian-only write on a different row count;
  - the title is not mirrored;
  - a price on a Russian copy is refused;
  - a per-city row without the own term is refused;
  - a row index out of range is refused.
- **`WriteAbilityTest`** (through `UpdatePriceRow`):
  - a call without a token writes nothing and returns
    `{diff, warnings, confirmation}`;
  - a token of another payload (550 → 560) is refused, writes nothing and
    takes no dump;
  - a valid token dumps, then writes, in that order;
  - a failed dump writes nothing;
  - an empty diff issues no token;
  - the permission needs `edit_post`, and a non-service is refused;
  - a missing id is `dovira_post_not_found`.
- **`ServerTest`:**
  - the catalogue has seven tools, all `dovira/*`, none on the default
    server;
  - `update-price-row` is annotated not readonly;
  - the instructions name both price tools.
- Form-to-record pipelines are not touched.

### Docs to update
- **`docs/features/content-mcp/FEATURE.md`:**
  - **Interfaces:**
    - the preview `{diff, warnings, confirmation}` (an empty diff gives
      `confirmation: null`);
    - the write `{written, dump, warnings}`, where `dump` is the file name or
      `"existing"`;
    - `find-price-rows` is a list `{items, total, page, per_page}` of price
      rows;
    - `update-price-row` input; a price mirrors, text does not; a price on a
      Russian copy is refused;
    - the errors `dovira_confirmation_invalid` and `dovira_dump_failed`.
  - **Fit:** the session id is read from the `/mcp/dovira` REST request.
- **`docs/DATA-MODEL.md` → Feature `content-mcp`:**
  - the confirm transient's value holds `token_hash`, never the token;
  - Sessions: how the id is read, and that 2026-07-28 has none;
  - the dump's footer comment.
- **`docs/ARCHITECTURE.md`:**
  - Data flows "Connector write → dump → write": the session id source and the
    empty-diff case;
  - Environments: unchanged unless a fact differs (it already names
    `DOVIRA_MCP_DUMP_DIR`, the fallback and the event).
- **`PRODUCTION-CHECKLIST.md`:**
  - A2 "used from Sprint 1 Step 3 on" becomes "used by every write";
  - B gets a row for the web-request dump on dev, filled at `/close-step`
    from the guide's reported footer numbers;
  - C's retention line stays.

### Checks
- **ANTI-PATTERNS / CONVENTIONS:** none violated.
  - ACF is written through `update_field()` on the repeater, never postmeta.
  - Both new abilities carry `mcp.public: false` and are listed through
    `Catalogue::TOOLS`.
  - No `function_exists()` branch is tested by leaving a function unstubbed:
    Polylang is injected.
  - The city comes from the site URL (`DescribeSite::city()` →
    `CityPrice::term_id()`).
  - No URL literal of an install.
  - No dev package is added.
  - `service-city` is never written.
- **Docs vs reality:**
  - **"The session id … the adapter passes":** it doesn't. Adapter 0.7.1
    hands the id only to its own transport and observability. An ability gets
    only its input, and `mcp_adapter_pre_tool_call` carries no session.
    Resolution: `Session` reads the header from the WP REST request of
    `/mcp/dovira` (task 1).
    - Under protocol 2025-11-25 (and the legacy 2025-06-18 / 2024-11-05 it
      maps there), the adapter validates the header before every tool call.
      claude.ai connects with `initialize`, which negotiates one of those, so
      its chats have a session.
    - Revision 2026-07-28 is sessionless, so it gets `''` and dumps on every
      write, as DECISIONS says for calls without a session.
  - **"mirrors the same change into the Russian copy's row":** resolved by
    DECISIONS "The tool catalogue speaks ACF field names…": prices are the
    same in every language, and the Russian text is written by the agent in
    the chat. So only `price` mirrors. Mirroring `title` / `description`
    would put Ukrainian text into the Russian copy (root invariant 7).
  - **FEATURE.md `dump:{file|"existing"}`:** implemented as a string, either
    the file name or `"existing"`, which is what `Dump::ensure()` returns.
    FEATURE.md is updated to say so.
  - **DATA-MODEL's confirm transient value** gains `token_hash`. The key is
    derived from (user, session, payload), so the random token is the secret
    and is stored only hashed.
  - **The fallback `.htaccess` on dev:** dev answers through nginx (`server:
    nginx`; `/.htaccess` → 403, `/wp-content/backup-db/` → 404 today). Whether
    Apache rules apply there is unknown. The guide's item with the constant
    removed shows it, and mode `0600` already keeps the file from a web server
    running as another user. The checklist rule stays: set
    `DOVIRA_MCP_DUMP_DIR` on every install.
  - **Out of this step:**
    - `_to_delete/content-mcp-dump-spike.php` says "delete after Step 3 ships
      `Dump`". The step does not name it, so it stays: `/adhoc`.
    - Kyiv's `service-city` slug `kyiv` (WORKLOG open item) affects
      per-city writes there exactly as reads; a missing term is refused with
      `dovira_city_term_missing`.
- **Design:** n/a (DECISIONS "No UI design phase").
- **Check command:** `bin/check.sh` (docs/TECH-STACK.md → Check command). Green
  on `master` `c478d90b` before planning.
- **Not locally verifiable:**
  - The real dump in a web request on `dev.dovira.vet`, which settles the
    host's web `memory_limit` / `max_execution_time`;
  - the fallback folder's 403 behind dev's nginx;
  - the retention event on dev's WP-Cron.

  All three are verified by the step's guide on dev after your push to `dev`.
  The DDEV live check covers the rest.

### Questions / ambiguities
none

### Step report (`/do-step`, 2026-11-05)
- **Commits** on `content-mcp/sprint-1-read-and-price`:
  - `38a06ce7` — tokens, `Session`, `Diff`;
  - `12999701` — `Dump` and retention;
  - `27daf6cc` — `PriceRows` and `find-price-rows`;
  - `fa994949` — `WriteAbility` and `update-price-row`;
  - the guide commit.
- **Gate:** theme PHPUnit 245 → 311.
- **Live check on DDEV**, over MCP with `curl`, protocol 2025-11-25, with a
  temporary administrator and application password (removed afterwards):
  - `tools/list` returns the seven tools.
  - **First session:**
    - The Ендоскопія row 0 (general) preview 2800 → 1234 has lines for uk 60
      and ru 1646.
    - A 1235 call with that token is refused.
    - The write gives `{written:[60,1646], dump:"dovira-dovira.ddev.site-…-181150.sql.gz"}`.
    - Приймальне відділення row 0 (per-city, Kharkiv column) 550 → 777 writes
      to 18 and 1641 with `dump:"existing"`.
    - A price on 1641 is refused.
    - `get-post` shows 777 in both copies.
  - **Second session:** the restore took a new dump, then `"existing"`. A
    no-op preview returns `confirmation: null`.
  - **The local dump:** 19 tables, 0.25 s, 29.6 MB peak, mode `0600`. The
    fallback folder got `.htaccess` and `index.php`.
  - **Postmeta against the pre-write dump:** no value changed, no key was
    removed. ACF added empty rows for sub-fields that had never been stored
    (`prices_{i}_price` / `_linked_page` = `''`, +4 per row on Ендоскопія).
    That is what any save of the repeater in wp-admin does, and the
    templates read `''` as they read a missing value.
  - **Cleanup:** the local dumps, the probe transients and the temporary user
    are removed. The retention event is scheduled (`1 day`).
- **Implementation choices inside the plan:**
  - `gzopen` has no exclusive-create mode, so `Dump` reserves the name with
    `fopen( $path, 'xb' )` before gzipping into it.
  - `UpdatePriceRow` keeps the plan computed by its preview within the call,
    so a write applies exactly what the same request validated.
  - `dump` in a write result is the file name or `"existing"`.
- **Mid-step decisions:** none.

## Plan — Sprint 1, Step 4: Editing an existing post, and trashing one as an administrator   (status: closed)

### Branch
`content-mcp/sprint-1-read-and-price` ← `master`. The branch is recreated from
`master` = `9b2f4201` (Step 3 merged). The only branch not merged into
`master` is `kyiv`, the deploy branch. This is the sprint's last step: its
`/close-step` completes Sprint 1.

### Tasks (ordered)
- [x] **1. `FieldInput`: ACF values from the reader's shape back to what ACF
  stores.** Commit: `feat(content-mcp): FieldInput, the inverse of FieldValues`.
  - `FieldInput::value( $field, $value )` is the inverse of
    `FieldValues::value()`, by field type:
    - image, file: `{id,…}` or an id → the id;
    - gallery: a list of those → ids;
    - post_object: `{id,title}` or an id → the id; a list when `multiple`;
    - relationship: a list → ids;
    - taxonomy: `[{id,name}]` or ids → ids;
    - repeater: rows by sub-field name, each converted recursively. The value
      replaces the whole repeater;
    - group: an object by sub-field name, converted recursively;
    - any other type passes through.
  - `FieldInput::keyed( $fields, $values )` turns `{name: value}` into
    `{field_key: value}` for the top level, unknown names refused. ACF's
    repeater accepts sub-fields by name (`update_row`), so only the top level
    needs keys.
  - Tests: `FieldInputTest`. Every case of `FieldValuesTest` round-trips:
    `FieldInput::value( FieldValues::value( x ) )` gives the stored form.
- [x] **2. `PostWriter`: a partial change set of one post, planned and
  then applied.** Commit: `feat(content-mcp): PostWriter plans and applies a
  partial post change`.
  - `PostWriter::plan( $post, $changes )` returns
    `{diff: Diff, apply: callable}` or a `WP_Error`. Nothing is written
    until `apply()`. As in `PriceRows`, the preview's diff is exactly what is
    written.
  - **`title`, `status`, `slug`, `parent`, `menu_order`:**
    - **`status`** is one of `publish`, `draft`, `pending`, `private`. `trash`
      is refused, pointing to `trash-post`. `future` is refused because there
      is no date input. `publish` and `private` need the type's
      `publish_posts`.
    - **`slug`** is shown as it will be stored (`wp_unique_post_slug(
      sanitize_title() )`).
    - **`parent`** only for hierarchical types. It must be an existing post
      of the same type, and not the post itself or one of its descendants.
    - A trashed post is refused (`dovira_post_in_trash`).
  - **`terms`:** `{taxonomy: [ids or names]}`, for the taxonomies of
    `Scope::TAXONOMIES` that the type has.
    - The list is the taxonomy's whole new set
      (`wp_set_object_terms( …, false )`). «Додай рубрику» means the current
      terms plus one; the preview shows both lists.
    - `category` / `post_tag` take existing terms by id or by name, the name
      looked up in the post's language.
    - `service-city` takes ids only.
    - An unknown term, a term in another language, or a name for
      `service-city` is refused. Nothing is created: `create-term` is Sprint
      2 (FEATURE.md → Roadmap).
  - **`acf`:** `{field_name: value}`, the post's own fields only. Those are
    the fields of the field groups located on the type
    (`acf_get_field_groups( ['post_type' => …] )` → `acf_get_fields()`).
    - Unknown names are refused.
    - **`service.prices` is refused** and points to `update-price-row`
      (FEATURE.md invariant: price rows are written only there).
    - Each top-level field is written with `update_field( $key,
      FieldInput::value(…), $id )`.
    - The diff line is `acf.{name}`: from the reader's value, to the new one.
  - **`blocks`:** the post's whole new content as the reader gives it
    (`[{block, fields} | {block, html}]`). Each block is checked against the
    blocks the editor allows on that post, via the shared
    `dovira_allowed_block_types()` (read, not changed): only the theme's
    `acf/*` blocks, except on `post`, where core blocks are allowed too (root
    invariant 5). Any other block is refused.
    - **An `acf/*` block** gets `fields` by name. When the current content
      has the same block at the same index, fields not named keep their
      current values, so «зміни заголовок у hero» cannot wipe the block's
      other fields.
    - The data is built **by ACF itself**:
      1. `FieldInput::keyed( acf_get_block_fields( $block ), $fields )`;
      2. `acf_setup_meta( $keyed, $block_id, true )`, which simulates a save
         and returns the flattened `name` / `_name` → key data;
      3. `acf_reset_meta( $block_id )`.
      
      It goes into `attrs.data`, keeping the block's other attrs (`mode`,
      `align`, …) when the same block is at that index. TECH-STACK
      ANTI-PATTERNS: block data goes through ACF only.
    - **`{block, html}`** must parse (`parse_blocks`) as exactly one block
      of that name, otherwise it is refused. It is written back as parsed.
      `core/freeform` is the raw HTML.
    - Then `serialize_blocks()`, `wp_slash()`, `wp_update_post()`, so
      WordPress applies its own `content_save_pre` (kses for users without
      `unfiltered_html`), as in the editor.
    - The diff gives one line per changed block field
      (`blocks[2].acf/hero.heading`), or `blocks[i]` for a block added,
      removed or replaced.
  - **Post fields** go through one `wp_update_post( wp_slash( […] ), true )`,
    which applies `post_name` uniqueness and the post's own hooks. **Terms**
    go through `wp_set_object_terms`. **ACF** goes through `update_field`.
    A `WP_Error` from any of them is returned.
- [x] **3. `dovira/update-post`.** Commit: `feat(content-mcp): update-post
  behind preview, token and dump`.
  - `UpdatePost extends WriteAbility`.
  - **Input:** `{id, title?, status?, slug?, parent?, menu_order?, terms?,
    acf?, blocks?, translation?: {id, …the same fields}, confirmation?}`.
    `translation` must be the Polylang translation of `id`
    (`pll_get_post_translations`); any other id is refused. The Russian copy
    is written only when it is named there (sprint step; FEATURE.md
    invariant), and the preview lists both targets.
  - **Permission:** `edit_post` of `id` and of `translation.id`; a missing
    post passes, so execute says `dovira_post_not_found`. A type outside
    `Scope::CONTENT` is refused in execute: records get
    `dovira_read_only_type` («звернення, заявки й анкети лише читаються»),
    so no write ability exists for them.
  - `preview` plans every target with `PostWriter`; `write` applies them,
    Ukrainian first. Result `{written, dump, warnings}`.
  - `Catalogue::TOOLS`, `Abilities`, `bootstrap.php`.
- [x] **4. `dovira/trash-post`, and the server instructions.** Commit:
  `feat(content-mcp): trash-post for administrators`.
  - `TrashPost extends WriteAbility`.
    - **Input:** `{id, translation_id?, confirmation?}`.
    - **Permission:** `manage_options` **and** `delete_post` of each id.
      `DescribeSite`'s `can_delete` already reports the first.
    - **Refused in execute:** records (`dovira_read_only_type`) and a post
      already in the trash.
    - **Preview:** a line `{target:{id, lang}, field: 'status', from: …, to:
      'trash'}` per post, plus a warning naming the Russian copy when it
      exists and is not named («російську копію (id N) не буде видалено»).
    - **Write:** `wp_trash_post()` per post, never `wp_delete_post()`. A
      `false` / `null` result is an error.
  - **`Server::INSTRUCTIONS`** gains:
    - edit with `dovira-update-post`, passing only what changes (a block's
      unnamed fields keep their values; a repeater value replaces the whole
      repeater);
    - the Russian copy is changed only when the person agrees, named as
      `translation`;
    - prices only through `update-price-row`;
    - deleting is `dovira-trash-post`, administrators only, to the trash.
  - `Catalogue::TOOLS`, `Abilities`, `bootstrap.php`.
  - **Live check in DDEV, not part of the gate**, as in Step 3: a temporary
    administrator and a temporary editor, removed afterwards, over `curl`.
    - `update-post` on a vacancy: `is_open`, then back.
    - A page title, uk then uk + ru.
    - A block field of a page, read back by `get-post` and rendered on the
      page (round trip of `acf_setup_meta` capture → `PostReader`), then the
      content set back.
    - A term on a blog post, then back.
    - `trash-post` refused for the editor; as the administrator, preview,
      write, and restore from the trash.
    - The postmeta and `post_content` compared with the session's dump, as in
      Step 3.

### Files to create/change
All under `wp-content/themes/dovira/` unless named otherwise. **No shared code is
changed**: `dovira_allowed_block_types()` (`inc/template-functions.php`) and
the field groups are only called or read.
- `inc/features/content-mcp/FieldInput.php`, `PostWriter.php`, `UpdatePost.php`,
  `TrashPost.php` (new)
- `inc/features/content-mcp/bootstrap.php`, `Catalogue.php`, `Abilities.php`,
  `Server.php` (changed)
- `tests/Unit/ContentMcp/FieldInputTest.php`, `PostWriterTest.php`,
  `PostAbilitiesTest.php` (new); `ServerTest.php`, `ReadAbilitiesTest.php`
  (changed: nine tools; the readonly loop skips the write abilities)
- `docs/features/content-mcp/FEATURE.md`, `docs/DECISIONS.md` (correction
  below), `docs/DOMAIN.md` only if a rule's wording changes

### Tests to write
PHPUnit + Brain\Monkey, no WordPress.
- **`FieldInputTest`:** each type's inverse; the round trip with
  `FieldValues`; nested repeater and group; unknown names refused by
  `keyed()`.
- **`PostWriterTest`:**
  - partial updates: only the named fields reach `wp_update_post`;
  - status rules: trash and future refused, `publish` without
    `publish_posts` refused;
  - slug shown as stored;
  - `parent` rules;
  - terms:
    - by name in the post's language and by id;
    - an unknown term refused, and no term created;
    - a name for `service-city` refused;
  - `acf`:
    - a repeater value replaces the whole repeater (`update_field` gets the
      full converted rows);
    - an image `{id,url,alt}` is written as its id;
    - unknown fields refused;
    - `service.prices` refused;
  - `blocks`:
    - names → keys through `acf_get_block_fields`, and the data stored in
      `attrs.data` is what `acf_setup_meta` returned;
    - unnamed fields of the same block keep their values;
    - the block's other attrs kept;
    - a block outside the allowed list refused (`acf/foo`, `core/paragraph`
      on a page), while `core/paragraph` is allowed on a post;
    - `{block, html}` that does not parse as that one block refused;
    - the reader reads back what was written (stubbed `parse_blocks` /
      `serialize_blocks` round trip);
  - `wp_slash` applied to the content;
  - the diff lists exactly the values passed to the writes.
- **`PostAbilitiesTest`:**
  - `update-post` refuses a record type and an unknown translation id;
  - `update-post` writes nothing without a token or with another payload's
    token;
  - the Russian copy is untouched unless named, and both are written when it
    is named;
  - `trash-post` is refused for an editor (no `manage_options`), for a record
    type and for a trashed post;
  - `trash-post` calls `wp_trash_post`, never `wp_delete_post`;
  - the Russian copy warning;
  - both abilities are annotated as writes.
- **`ServerTest`:** nine tools; the instructions name `update-post` and
  `trash-post`.
- Form-to-record pipelines are not touched. Records get no write ability, and
  the tests above assert the refusal.

### Docs to update
- **`docs/features/content-mcp/FEATURE.md` → Interfaces:**
  - the `update-post` and `trash-post` input;
  - the term and block rules;
  - `translation`;
  - the new error codes.
- **`docs/DECISIONS.md`:** correct one sentence in the consequences of
  "Only a row's price follows into the Russian copy…" (written at Step 3's
  close). "Step 4's `update-post` follows the same split: values the copies
  share follow" becomes: "`update-post` writes a Russian copy only when the
  payload names it as `translation`; nothing follows on its own."
- **`docs/DOMAIN.md`:** only if the delete or edit wording changes. Expected:
  no change.

### Checks
- **ANTI-PATTERNS / CONVENTIONS:** none violated.
  - ACF values go through `update_field()`, and block data through
    `acf_setup_meta()`, never postmeta or a hand-written key mapper.
  - Both abilities extend `WriteAbility` and read the session through
    `Session::id()`.
  - `meta.mcp.public: false`; the tools are listed through
    `Catalogue::TOOLS`.
  - The records gate is not `read_post`.
  - `service-city` terms are never created or renamed.
  - Polylang is injected.
  - No URL literal. No new dependency.
- **Docs vs reality:**
  - **`content_html` for a `post`** (step text) conflicts with DECISIONS
    "Every post's content is read as blocks; there is no `content_html`".
    Its consequences already say Step 4's writer takes `blocks` and that the
    `content_html` task is to be re-read here. DECISIONS wins: `blocks` for
    every type, with the editor's allowed blocks per type.
    - The step's "`content_html` sanitized with `wp_kses_post`" becomes
      WordPress's own `content_save_pre` on `wp_update_post`, the same as the
      editor. A `{block, html}` must parse as exactly its block.
  - **"`blocks` for a `page`"**, by the same entry: `blocks` for every type
    in scope.
  - **"a block name outside the theme's blocks is refused":** the theme
    already decides which blocks each post may use,
    `dovira_allowed_block_types()` (root invariant 5). Pages, services and
    the rest get the theme's `acf/*` only; blog posts get core blocks too,
    which two of them use today. The writer uses that function, so a blog
    post's core blocks survive an edit.
  - **The DECISIONS consequence written at Step 3's close** ("values the
    copies share follow") went further than the sprint. For posts no value is
    shared the way a price is, and the step says the copy is touched only
    when named. Corrected under Docs to update.
  - **`service.prices` through `update-post`:** FEATURE.md invariant says
    price rows are written only by `update-price-row`, so it is refused.
  - **Terms by name:** only existing terms; creating one is Sprint 2. The
    verification's «додай рубрику Акції» needs the category to exist on dev
    (it doesn't locally); the guide names an existing category.
  - **Single site:** editors have `unfiltered_html` by default, so kses
    changes nothing for them, as in the editor.
- **Design:** n/a (DECISIONS "No UI design phase").
- **Check command:** `bin/check.sh` (docs/TECH-STACK.md → Check command), green
  on `master` `9b2f4201`.
- **Not locally verifiable:** n/a. The DDEV live check covers the ACF block
  round trip, and the guide runs everything again on dev in claude.ai.

### Questions / ambiguities
none

### Step report (`/do-step`, 2026-11-05)
- **Commits** on `content-mcp/sprint-1-read-and-price`:
  - `87867265` — `FieldInput`;
  - `f647692c` — `PostWriter`;
  - `8326fb73` — `update-post`;
  - `6635d8d3` — `trash-post` and the instructions;
  - the guide commit.
- **Gate:** theme PHPUnit 311 → 373.
- **ACF block write, spiked on DDEV before the tests** (page 176, `acf/about`):
  - `acf_setup_meta()` with field-key values captures ACF's own save and
    returns the flattened `name` / `_name` data. `get-post` reads the edited
    field back.
  - The first version filled unnamed fields from the reader's formatted
    values, which rewrote a wysiwyg with `<p>` and a true_false `'1'` as
    `true`. Unnamed fields are now taken unformatted
    (`get_fields( $block_id, false )`). After that only the edited key
    changed (113 of 114 identical).
  - The fields are written in the block's field order, so an edit that is set
    back leaves `post_content` byte-identical to the original (checked by
    md5).
- **Live check on DDEV over MCP**, with a temporary administrator and editor
  and a temporary uk category «Акції», all removed afterwards with the
  probe's revisions:
  - nine tools;
  - vacancy 1535 `is_open` false → true;
  - titles of 176 + its translation 1631 in one preview;
  - the `acf/about` heading, rendered on `/about/` and set back;
  - a category added to post 1498 and removed;
  - refusals: `core/paragraph` on a page, `service.prices`, a
    `conversation`, `trash-post` for the editor;
  - the administrator's trash of 1535, with the warning about 1677, then
    restored;
  - one dump per session (0.42–0.44 s, ~30 MB).
  - The fingerprint (titles, status, slug, content md5, postmeta md5,
    categories) of the five posts is identical to before.
- **Local configuration found during the check:** your local `wp-config.php`
  now defines `DOVIRA_MCP_DUMP_DIR` as dev's path
  `/home/dovirave/dovira-mcp-dumps/dev`, which doesn't exist in DDEV.
  - The first run's writes all failed with `dovira_dump_failed`, and nothing
    was written: the invariant, live.
  - For the probe the folder was created inside the container only, then
    removed. Your `wp-config.php` was not touched.
  - Locally, writes need that line removed or pointed at a container path.
- **Implementation choices inside the plan:**
  - `UpdatePost::content_post()` is shared with `TrashPost`.
  - A failure partway through a two-post write names what was already
    written.
- **Mid-step decisions:** none.
