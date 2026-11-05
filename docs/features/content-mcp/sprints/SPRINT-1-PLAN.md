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
