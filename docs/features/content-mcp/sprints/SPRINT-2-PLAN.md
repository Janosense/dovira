# SPRINT 2 — PLAN (content-mcp)

<!-- Written by /plan-step, one section per step; executed by /do-step,
     closed by /close-step. -->

## Plan — Sprint 2, Step 1: Creating posts of every writable type, and blog terms   (status: closed)

### Branch
`content-mcp/sprint-2-create` ← `master`, created from `master` = `133d9c56`
(Sprint 1 complete). The only branch not merged into `master` is `kyiv`, the
deploy branch.

### Tasks (ordered)
Written for the recommended answer to Question 1 (A).

- [x] **1. `PostWriter::create()`: a new post planned and then inserted.**
  Commit: `feat(content-mcp): PostWriter plans and inserts a new post`.
  - The update path's checks are reused for a post that does not exist yet.
    The planners take the target and its language explicitly, and a new post
    is a stub (`ID 0`, its type, `post_status 'auto-draft'`, empty content).
  - `Diff::add()` accepts `?int`: a new post's lines carry `target.id: null`.
  - **Input:**
    - `post_type`: one of `Scope::CONTENT`. `post` only where the install has
      a blog: the Ukrainian page `news` exists (the slug city-popup uses for
      the blog; `page_for_posts` is 0 here). Otherwise refused, e.g. on Kyiv.
    - `lang`: `uk`, or absent. Anything else is refused and points to
      `create-translation` (Step 2).
    - `title` (required).
    - `status` (required, no default): `publish|draft|pending|private`.
    - `slug`, `parent`, `menu_order`, `terms`, `acf`, `blocks`: the update
      path's shapes and rules (allowed blocks per type, existing terms only,
      the type's own fields).
  - **Required ACF fields** of the type (`required` in the type's field
    groups: `service.image`, `employee.photo`, `vacancy.description`) must be
    present and non-empty in `acf`. Otherwise everything is refused before
    any insert, with the missing fields named (root invariant 8).
  - **`acf.prices` of a service** (Question 1, A): rows in the reader's
    shape `{title, description, price, linked_page?}`.
    - Each is written as a general row: `is_price_general = 1`, `price`, and
      `cities` = this install's own city (`city_{term}`, with the term from
      `CityPrice::term_id()`).
    - No city term means a refusal.
    - `city_prices` is never written.
  - **The preview** lists every value that will be written: `type`, `lang`,
    `title`, `status`, `slug` as stored, terms, each ACF field, each block,
    all `from: null`.
  - **`apply`:**
    1. `wp_insert_post( wp_slash( [ … 'post_content' => serialized blocks ] ), true )`
       as the connected user;
    2. `pll_set_post_language( $id, 'uk' )`;
    3. `update_field` per field, through the field key;
    4. `wp_set_object_terms`;
    5. returns the new id. A failure after the insert names the created id.
- [x] **2. `dovira/create-post`.** Commit: `feat(content-mcp): create-post
  behind preview, token and dump`.
  - `CreatePost extends WriteAbility`.
  - **Input:** the above plus `confirmation`; `required: [post_type, title,
    status]`.
  - **Permission:** the type is in `Scope::CONTENT` and the user has the
    type's `create_posts`, plus `publish_posts` for `publish` / `private`.
  - **Result:** `{written: [new id], dump, warnings}`.
  - `Catalogue::TOOLS`, `Abilities`, `bootstrap.php`.
- [x] **3. `TermWriter`, `dovira/create-term`, `dovira/update-term`, and
  the instructions.** Commit: `feat(content-mcp): create-term and
  update-term for blog categories and tags`.
  - **`TermWriter::create( $taxonomy, $fields )`:**
    - `taxonomy` is `category` or `post_tag` only; the schema enum refuses
      the rest, `service-city` included.
    - `name` is required; `slug` and `description` are optional; `parent` is
      for `category` only, and must be an existing Ukrainian category.
    - A name that already exists in Ukrainian under the same parent is
      refused with its id.
    - **`apply`:** `wp_insert_term()`, then `pll_set_term_language( $id,
      'uk' )` when Polylang translates the taxonomy. Locally it translates
      both, and the step report records dev's setting. The term is not
      translated: there is no Russian copy of it.
  - **`TermWriter::update( $id, $fields )`:** a term of `category` /
    `post_tag` only; `name`, `slug`, `description`, `parent` (category).
    Applied with `wp_update_term()`. The diff target is the term's id and
    language.
  - **Permission:**
    - `create-term` needs the taxonomy's `manage_terms`.
    - `update-term` needs `edit_terms`.
    - On this install these are `manage_categories` and the tag caps that map
      to it.
  - **Both** extend `WriteAbility`, so every term write is previewed, needs
    the token and takes the session's dump.
  - **`Server::INSTRUCTIONS`:** create with `dovira-create-post`, always in
    the status the person names; a new category or tag first through
    `dovira-create-term`, then used by name or id.
  - **Live check in DDEV, not part of the gate**, as in Sprint 1, with
    temporary users and a temporary `DOVIRA_MCP_DUMP_DIR` path inside the
    container:
    - `create-term` «Акції» + a new tag, then a draft blog post with both;
    - a published page with `acf/hero` + `acf/rich-text`, rendered;
    - a service without `image` refused with `image` named, then with an
      image id and two price rows, shown on its page in the Харків tab;
    - an employee with a photo;
    - a vacancy;
    - `post` refused when the `news` page is absent (simulated);
    - each chat leaves one dump;
    - everything created is deleted afterwards.

### Files to create/change
All under `wp-content/themes/dovira/` unless named otherwise. No shared code is
changed.
- `inc/features/content-mcp/CreatePost.php`, `TermWriter.php`,
  `CreateTerm.php`, `UpdateTerm.php` (new)
- `inc/features/content-mcp/PostWriter.php`, `Diff.php`, `bootstrap.php`,
  `Catalogue.php`, `Abilities.php`, `Server.php` (changed)
- `tests/Unit/ContentMcp/PostWriterTest.php`, `DiffTest.php`,
  `ServerTest.php`, `ReadAbilitiesTest.php` (changed);
  `CreatePostTest.php`, `TermWriterTest.php` (new)
- `docs/features/content-mcp/FEATURE.md`

### Tests to write
- **`PostWriterTest` (create):**
  - required fields refused with their names (`service` without `image`);
  - `post` refused without the `news` page;
  - status required, with no default;
  - `lang` other than `uk` refused;
  - a type out of scope or a record refused;
  - the written values equal the preview: the insert args, the ACF writes,
    the terms;
  - `pll_set_post_language( id, 'uk' )`;
  - service price rows written as general rows of this city, and refused
    without the city term;
  - the update path's tests stay green.
- **`DiffTest`:** a `null` target id.
- **`CreatePostTest`:**
  - permission: `create_posts` of the type, and `publish` / `private` need
    `publish_posts`;
  - preview without a token, write with it, another payload's token
    refused;
  - the result carries the new id.
- **`TermWriterTest`:**
  - the taxonomy allow-list (`service-city` refused);
  - `parent` only for categories, and only an existing Ukrainian one;
  - a duplicate name refused;
  - the Ukrainian language set when the taxonomy is translated;
  - an update of a term outside the two refused;
  - permissions (`manage_terms`, `edit_terms`).
- **`ServerTest`:** twelve tools; the instructions name the three new tools.

### Docs to update
- `docs/features/content-mcp/FEATURE.md`:
  - **Interfaces:** `create-post`, `create-term`, `update-term`; a new
    post's diff target `id: null`; the price rows of a new service.
  - **Invariants:** the price-row line amended per Question 1 (A).
- `docs/DOMAIN.md`: no change. The glossary already defines «Перегляд змін
  (preview)», which was the step's condition.

### Checks
- **ANTI-PATTERNS / CONVENTIONS:** none violated.
  - ACF goes through `update_field()` and block data through ACF's capture.
  - The abilities extend `WriteAbility` and read the session through
    `Session::id()`.
  - `meta.mcp.public: false`.
  - No field group is created (root invariant 6).
  - `service-city` is never created or written as a term (invariant 2).
    Only a new service row's `cities` ticks this city, by its existing term.
  - No URL literal. No new dependency.
- **Docs vs reality:**
  - **`blocks | content_html`** (step text, and the sprint's second Fixed
    decision) is settled by DECISIONS "Every post's content is read as
    blocks; there is no `content_html`", which amends the catalogue entry
    those lines cite. So `blocks` only, as in `update-post`.
  - **"a new category" in the verification:** the step's create path takes
    terms with "the same validation as the update path", which is existing
    terms only. So a new term is a `create-term` call first, with its own
    preview, then the post uses it. Not created inline.
  - **"required ACF fields … (from `Schema`)":** read from the type's field
    groups, the same definitions `Schema` and the update path use.
  - **The blog page:** `page_for_posts` is 0, and the blog is the page
    `news` (city-popup `Pages::BLOG_SLUG`). The writer looks it up by slug
    itself, not through city-popup's class (feature isolation).
  - **Kyiv:** no `news` page is expected, so `post` is refused there by
    design (FEATURE: «a Kyiv blog» is out of scope).
  - **Carried from Sprint 1's last entry** (none changes a task here):
    - the Step 3 dump numbers are unreported;
    - the local `wp-config.php` points `DOVIRA_MCP_DUMP_DIR` at dev's path,
      so the live check uses a container folder as in Sprint 1 Step 4;
    - `master` is not on `origin/main`;
    - the production prerequisites wait for Step 3.
- **Design:** n/a.
- **Check command:** `bin/check.sh`, green on `master` `133d9c56`.
- **Not locally verifiable:** n/a. The DDEV live check covers it, and the
  guide repeats it on dev.

### Questions / ambiguities
1. **The price list of a new service.** The sprint asks for "a service with
   its price list", and the verification for «… з двома рядками прайсу».
   But FEATURE.md → Invariants says "price rows are written only by
   `update-price-row`, which never changes `is_price_general` or the `cities`
   checklist". And `update-price-row` can only change a row that already
   exists. A new row also needs `cities` ticked, or the site does not show it
   (root invariant 3).
   - **A (recommended):** `create-post` accepts `acf.prices` for a **new**
     service, as rows in the reader's shape `{title, description, price,
     linked_page?}`.
     - Each row is written as a general row (`is_price_general = 1`) ticked
       for this install's city only.
     - The FEATURE invariant becomes: "existing price rows are changed only
       by `update-price-row` …; a new service's rows are written by
       `create-post` as general rows of this city".
     - Later rows of an existing service are added in wp-admin, as today.
     - Tasks as written.
   - **B:** a new service is created without prices (`acf.prices` refused).
     The person adds the rows in wp-admin and then changes amounts through
     `update-price-row`. Task 1 drops the price-row writing and its tests,
     and the verification's «з двома рядками прайсу» is done in wp-admin.
   - Resolved: approved as recommended — A: `create-post` writes a new service's rows as general rows ticked for this install's city; the FEATURE invariant is amended accordingly.

### Step report (`/do-step`, 2026-11-05)
- **Commits** on `content-mcp/sprint-2-create`:
  - `ddf2d073` — `PostWriter::create()` and the price-row invariant;
  - `347f1ad5` — `create-post`;
  - `40a42012` — the term tools, the instructions, and a new block's
    defaults;
  - the guide commit.
- **Gate:** theme PHPUnit 373 → 417.
- **Live check on DDEV over MCP**, with a temporary administrator and the
  dump folder created inside the container only (the local `wp-config.php`
  points `DOVIRA_MCP_DUMP_DIR` at dev's path):
  - 12 tools.
  - **Terms:**
    - `create-term` category «Акції (проба)» and a tag, both `uk`;
    - a duplicate refused with its id;
    - `service-city` refused by the schema enum.
  - **A draft blog post** with that category and tag, as an `acf/rich-text`
    block, `uk`.
  - A missing `status` is refused by the schema.
  - **A published page** with `acf/hero` + `acf/rich-text`, rendered (after
    the fix below).
  - **A service:**
    - without `image`, refused: «Не заповнено обов’язкові поля: image.»;
    - with image 1759 and two rows, shown on its page;
    - `find-price-rows` gives 450 / 320 with `per_city: false`.
  - **An employee** with a photo, **a vacancy** as a draft, and
    `update-term` description.
  - One dump per session (0.45 s, 29.7 MB).
  - Everything created, the user, the transients and the folder were removed
    afterwards.
- **Fixed during the live check** (inside task 3, the code it builds on):
  - **The fault:** a new `acf/*` block written with only its named fields
    rendered nothing. ACF applies a field's `default_value` only to a field
    the block data names (`_is_visible` → key). So templates gated on
    `is_visible` (`hero`, `rich-text`, …) read it as empty.
  - **The fix:** a new block now gets every unnamed field's non-empty
    default, as the block editor stores them on insert. An existing block
    still keeps its stored values.
  - The same code serves `update-post`, so a block added to an existing
    post by Sprint 1 Step 4's tool would have been hidden the same way.
    This fixes it there too. Sprint 1 Step 4's live check only edited
    existing blocks.
  - Test: `PostWriterTest::test_a_new_block_gets_the_defaults…`.
- **Polylang on the local install:** `category` and `post_tag` are both
  translated, so a new term is set to `uk`. Dev's setting is the same
  database lineage, and the guide's item 3 shows the term's language in
  wp-admin.
- **`post` without a blog** is covered by unit tests only. Renaming the local
  `news` page for a live check was not worth the risk.
- **Mid-step decisions:** none.

## Plan — Sprint 2, Step 2: The Russian copy, and images   (status: approved, in progress)
### Branch
`content-mcp/sprint-2-translate-media` ← `master`, created from `master` =
`3e430c54` (Step 1 closed and merged). The only branch not merged into
`master` is `kyiv`, the deploy branch.

### Tasks (ordered)
- [x] **1. `Translator` and `dovira/create-translation`.** Commit:
  `feat(content-mcp): create-translation makes the Russian copy of a Ukrainian post`.
  - **`PostWriter::plan_new()`:** the middle of `create()` is extracted, and
    `create()` calls it. It plans a post that does not exist yet:
    - a stub post of the type, with the content the plan starts from: empty
      for a new post, the source's `post_content` for a copy;
    - `post_fields`, `terms`, `acf_fields` and `blocks` in creating mode, in
      the language it is given;
    - it returns `{postarr, terms, fields}`.
    - For a copy, the blocks' lines are the resulting content re-read
      through `PostReader::blocks()`: `blocks[i]` with `from: null`, every
      block. They are not per-field changes against the source.
  - **`Translator`** (`dovira\ContentMcp\Translator`) plans the Russian copy
    of a post.
    - **Input:**
      - `id`: the source;
      - `title` (required: the Russian title the agent writes);
      - `status` (required, no default, `PostWriter::STATUSES`);
      - optional `slug`, `parent`, `menu_order`, `terms`, `acf`, `blocks`,
        in the `update-post` shapes, as the translated values.
    - **Refused before anything else:**
      - a source that is missing, a record, out of scope, or in the trash
        (`UpdatePost::content_post()`);
      - a source that is not `uk`: `dovira_not_source`, naming the
        Ukrainian id when there is one;
      - a source that already has a `ru` copy: `dovira_translation_exists`,
        with the copy's id and «use `update-post` on it»;
      - no Polylang: `dovira_polylang_missing`.
    - **What the copy carries from the source, overlaid by the payload:**
      - **`menu_order`**: the source's.
      - **`parent`**: the parent's own `ru` translation (`pll_get_post`).
        If there is none, `0` and a warning. A payload `parent` must be a
        `ru` post of the same type.
      - **`slug`**: the payload's, or `sanitize_title( title )` under the
        `ru` locale. Cyr2lat's `ctl_locale` filter is set for that one call,
        as `PostCopier::get_slug()` does. The few lines are repeated here:
        `inc/cli/` is shared code and is not touched.
      - **`terms`**: every source term of the scoped taxonomies.
        - `category` / `post_tag` map to their `ru` term (`pll_get_term`).
          A term without one is left out, with a warning naming it (the
          connector creates only `uk` terms).
        - `service-city` is copied by id.
        - A payload taxonomy replaces that taxonomy's set; names are looked
          up in `ru`.
      - **`acf`**: every field of the type's groups that has a value.
        - A field the payload does not name goes over **raw**
          (`get_field( key, source, false )` → `update_field( key, raw, copy )`),
          never re-converted, as the block-write rule in TECH-STACK requires.
        - A named field goes through `FieldInput::keyed()`.
        - **`service.prices`** is copied raw: `price`, `is_price_general`,
          `cities`, `city_prices`, `linked_page`. A payload `acf.prices` is a
          list with exactly the source's row count. Each row
          `{title?, description?}` replaces only that row's text sub-fields.
          A row's `price`, if given, must equal the source row's price in
          this city; otherwise `dovira_price_in_source`. Prices are the same
          in every language (DECISIONS 2026-11-05).
        - Relational values (`post_object`, `relationship`, `linked_page`)
          go over as the source's ids, as `wp dovira translate` copies them.
          The agent may name Russian ids instead.
      - **`blocks`**: without a payload `blocks`, the source's `post_content`
        is copied verbatim. With one, the copy's content is planned like an
        `update-post` of the source's content: named fields replace, and
        unnamed fields of the same block at the same index stay raw.
      - **The page template** (`get_page_template_slug()`, e.g.
        `templates/sub-service.php`), **the featured image** (`_thumbnail_id`,
        only for the types that support one, `page` and `post` — the live
        probe showed WordPress ignores it on a service) and **the date** go
        over as `PostCopier` sets them.
        - The date is `post_date` / `post_date_gmt`, only when the source
          has a GMT date, so a blog archive orders the copy with its source.
        - They are passed as `page_template` / `_thumbnail_id` /
          `post_date` / `post_date_gmt` / `edit_date` of `wp_insert_post`,
          not as raw meta writes.
    - **The preview** lists every value of the copy: target
      `{id: null, lang: "ru"}`, `from: null`.
      - Lines: `post_type`, `title`, `status`, `slug`, `parent`,
        `menu_order`, `template`, `featured_image`, `date`, `terms.*`, every
        `acf.*` that has a value (prices shown in this city, as `get-post`
        shows them), and every `blocks[i]`.
      - One warning lists the text values carried over in Ukrainian (the ACF
        text fields and the blocks the payload did not name), so the person
        sees what stays untranslated.
    - **`apply`:**
      1. `wp_insert_post( wp_slash( … ), true )` as the connected user;
      2. `pll_set_post_language( copy, 'ru' )`;
      3. `pll_save_post_translations( source's translations + ['ru' => copy] )`,
         the calls `PostCopier` makes;
      4. `update_field` per field;
      5. `wp_set_object_terms` per taxonomy;
      6. returns the copy's id. A failure after the insert names the
         created id.
  - **`CreateTranslation extends WriteAbility`**, ability
    `dovira/create-translation`:
    - **Input:** the above plus `confirmation`; `required: [id, title, status]`.
    - **Permission:** `edit_post` of the source and the type's
      `create_posts`, plus `publish_posts` for `publish` / `private` (as
      `create-post`). A missing source passes, so the agent hears «not
      found».
    - **Write:** `{written: [copy id], dump, warnings}`.
    - Annotations and `meta` from `WriteAbility::meta()`.
  - **Registration:**
    - `Catalogue::TOOLS` and `Abilities::definitions()` get it after
      `create-post`; `bootstrap.php` requires it.
    - `Server::INSTRUCTIONS`: a Ukrainian post without a Russian copy gets
      one through `dovira-create-translation`, with the Russian title and
      every text written by the agent (what it leaves out stays in
      Ukrainian), only after the person agrees; an existing copy is changed
      with `dovira-update-post`.
  - **Live check:** probed on DDEV over MCP, as in Step 1. A temporary dump
    folder is made in the container only (local `DOVIRA_MCP_DUMP_DIR` points
    at dev's path) and removed afterwards; probe posts and users are deleted.
- [ ] **2. `MediaWriter` and `dovira/upload-media`.** Commit:
  `feat(content-mcp): upload-media from a URL or base64 behind preview, token and dump`.
  - **`MediaWriter`** plans an upload.
    - **Input:**
      - exactly one of `url` or `base64` (a `data:image/…;base64,` prefix is
        stripped); otherwise `dovira_media_source_invalid`;
      - optional `filename`, `alt`, `title`, `attach_to` (post id).
    - **Accepted type:** a raster image. The type that `wp_get_image_mime()`
      reads from the bytes must be one of the `image/*` types
      `get_allowed_mime_types()` allows the user. SVG is never accepted:
      `wp_get_image_mime()` cannot read it.
    - **Size cap:** `apply_filters( 'dovira_mcp_upload_max_bytes',
      10 * MB_IN_BYTES )`.
    - **Preview checks for `url`.** Nothing is downloaded at preview.
      - Only `http` / `https` that `wp_http_validate_url()` accepts (it also
        refuses local and private hosts): `dovira_media_url_invalid`.
      - `wp_safe_remote_head()`:
        - a `Content-Type` outside the image types →
          `dovira_media_type_invalid` (a `.exe` stops here);
        - a `Content-Length` over the cap → `dovira_media_too_large` (a
          20 MB image stops here).
        - With no type in the HEAD answer, the URL's extension decides
          (`wp_check_filetype()` against the image types).
    - **Preview checks for `base64`.**
      - The decoded size is estimated from the length before decoding; over
        the cap → `dovira_media_too_large`.
      - Strict `base64_decode( …, true )`; the type is read from the bytes
        (`getimagesizefromstring()`).
    - **`attach_to`:** a content post (`UpdatePost::content_post()`).
    - **The preview** has target `{id: null, lang: null}`; media are not
      translated (Polylang `media_support` is off). Its lines are:
      - `source`: the URL, or `base64, N KB`; the bytes never go into the
        diff or the confirmation transient;
      - `filename`, `title`, `alt`, `mime` (when known);
      - `attach_to` → `{id, title}`.
    - **`apply`** loads `wp-admin/includes/{file,media,image}.php`, which a
      REST request does not load, and then:
      - **`url`:** `wp_safe_remote_get( url, [stream → temp file,
        limit_response_size → cap + 1] )`. This is the request
        `download_url()` makes, with the cap that `download_url()` has no
        argument for. A file over the cap, or bytes that are not an
        accepted image, is deleted and refused.
      - **`base64`:** written to `wp_tempnam()`.
      - The filename's extension is set from the type read from the bytes.
      - Then `media_handle_sideload( file, attach_to ?: 0, title )`, the
        call `media_sideload_image()` makes, as the connected user.
      - `alt` → `update_post_meta( id, '_wp_attachment_image_alt',
        sanitize_text_field( alt ) )`. This is core attachment meta, not an
        ACF value.
      - The temp file is removed on every path.
    - **Returns** `{id, url, alt, mime, width, height}`, the `list-media`
      item's fields.
  - **`WriteAbility::run()`:** keys that `write()` returns besides
    `written` / `warnings` are added to the result. `upload-media`'s write is
    `{written: [id], dump, warnings, media: {id, url, alt, mime, width,
    height}}`, and its output schema adds `media`.
  - **`UploadMedia extends WriteAbility`**, ability `dovira/upload-media`:
    - **Permission:** `upload_files`, plus `edit_post` of `attach_to` when
      it exists.
    - **Input:** `url` (format uri), `base64`, `filename`, `alt`, `title`,
      `attach_to` (integer ≥ 1), `confirmation`.
  - **Registration:**
    - `Catalogue::TOOLS` / `Abilities` get it after `create-translation`:
      fourteen tools. `bootstrap.php` requires it.
    - `Server::INSTRUCTIONS`: an image is uploaded with
      `dovira-upload-media` (a URL, or base64 if the client has the bytes),
      then set in the post with `dovira-update-post` / `dovira-create-post`
      (`acf.photo`, `acf.image` = the new id). These are two writes, each
      previewed.
  - **Live check:** on DDEV over MCP:
    - an image URL (preview, then upload);
    - a small base64 PNG;
    - a `.exe` URL and an oversized `Content-Length`, both refused at
      preview.
    - The probe attachments are deleted afterwards.

### Files to create/change
All under `wp-content/themes/dovira/` unless named otherwise. No shared code is
changed.
- `inc/features/content-mcp/Translator.php`, `CreateTranslation.php`,
  `MediaWriter.php`, `UploadMedia.php` (new)
- `inc/features/content-mcp/PostWriter.php`, `WriteAbility.php`,
  `bootstrap.php`, `Catalogue.php`, `Abilities.php`, `Server.php` (changed)
- `tests/Unit/ContentMcp/TranslatorTest.php`, `MediaWriterTest.php` (new);
  `PostWriterTest.php`, `WriteAbilityTest.php`, `ServerTest.php`,
  `ReadAbilitiesTest.php` (changed)
- `docs/features/content-mcp/FEATURE.md`, `docs/ARCHITECTURE.md`,
  `docs/DATA-MODEL.md`

### Tests to write
- **`TranslatorTest`:**
  - **What the copy carries:**
    - an ACF field the payload does not name is written with the source's
      raw value;
    - the content is copied verbatim without `blocks`;
    - template, featured image and date go over.
  - **What the payload overlays:**
    - the title, an ACF field, and one field of a block, while that block's
      other fields stay raw;
    - price rows' texts, with prices and `cities` / `is_price_general` /
      `city_prices` kept;
    - a row count different from the source's is refused;
    - a different `price` gives `dovira_price_in_source`.
  - **Polylang linking:** `pll_set_post_language( copy, 'ru' )`, and
    `pll_save_post_translations` with the source's translations plus `ru`.
  - **Refusals:**
    - an existing copy is refused with its id;
    - a `ru` source is refused;
    - title and status are required;
    - a record or a trashed source is refused.
  - **Parent and terms:**
    - the parent resolves to its translation; with none, the parent is `0`
      and there is a warning;
    - terms map to `ru`; a term without one is left out with a warning;
    - `service-city` is copied by id.
  - **The preview:** every value with `target {id: null, lang: ru}`, and the
    carried-text warning.
  - **The written values equal the preview:** the insert args, the ACF
    writes, the terms.
  - **`CreateTranslation`:**
    - permission: `edit_post` of the source, `create_posts`, and
      `publish_posts` for `publish`;
    - a preview without a token writes nothing;
    - a token gives dump, then insert, and the result carries the copy's id;
    - another payload's token is refused.
- **`MediaWriterTest`** (network stubbed):
  - **Scheme:** `ftp:` / `file:` and a URL `wp_http_validate_url()` refuses
    are refused.
  - **Type:**
    - a HEAD `application/octet-stream` (a `.exe`) is refused, and so is an
      `.exe` extension without a type;
    - SVG is refused;
    - no GET happens at preview.
  - **Size:**
    - a `Content-Length` over the cap is refused at preview;
    - a downloaded file over the cap is refused at write and its temp file
      deleted;
    - the filter changes the cap.
  - **base64:**
    - a `data:` prefix is stripped;
    - invalid base64 is refused;
    - an oversized payload is refused before decoding;
    - the bytes are not in the diff.
  - **Writing:**
    - `alt` is written as `_wp_attachment_image_alt`;
    - `media_handle_sideload` gets `attach_to` only when asked (`0`
      otherwise);
    - the result is `{id, url, alt, mime, width, height}`.
  - **`UploadMedia`:** permission `upload_files` plus `edit_post` of
    `attach_to`; a preview without a token writes nothing; the token write
    carries `media`.
- **`PostWriterTest`:** `create()` stays green through `plan_new()`.
- **`WriteAbilityTest`:** extra keys of `write()` reach the result; `written`,
  `dump` and `warnings` are not overridden.
- **`ServerTest`:** fourteen tools in catalogue order; the instructions name
  `create-translation` and `upload-media`.
- **`ReadAbilitiesTest`:** the readonly loop excludes the two new writes.

### Docs to update
- `docs/features/content-mcp/FEATURE.md`:
  - **Interfaces:**
    - `create-translation` and `upload-media`: input, permission, preview,
      result, errors;
    - `upload-media`'s `media` result key;
    - the «come in Sprint 2 Step 2» remark goes;
    - the server instructions summary;
    - Filters: `dovira_mcp_upload_max_bytes`.
  - **Invariants:** a Russian copy is created only for a Ukrainian post
    without one; its prices are the source's; a carried value is written raw.
- `docs/ARCHITECTURE.md` → Data flows: the Russian copy (`create-translation`:
  insert as the connected user → `ru` → `pll_save_post_translations`) and the
  upload (HEAD at preview; the capped download and `media_handle_sideload`
  only after consent), inside the existing connector-write flow.
- `docs/DATA-MODEL.md` → Writing posts:
  - the copy: its fields, raw ACF, template, thumbnail and date, and the
    Polylang link;
  - attachments: author = the connected user, `_wp_attachment_image_alt`,
    `post_parent` = `attach_to`, no language.

### Checks
- **ANTI-PATTERNS / CONVENTIONS:** none violated.
  - **ACF:** values go through `update_field()`; carried values go over raw
    by field key, and block data through ACF's capture. Neither is
    re-converted from the reader's shape.
  - **Write abilities:** both extend `WriteAbility`, read the session only
    through `Session::id()`, and carry `meta.mcp.public: false`.
  - **`service-city`:** copied by id, never written as a term (invariant 2).
    No field group is created (invariant 6). A copy is always `ru` of an
    existing `uk` post (invariant 7).
  - **Postmeta written directly:** only the attachment's alt (core meta, not
    ACF). The template and featured image go through `wp_insert_post` args.
  - **Polylang checks** are injected (`?bool $polylang`), not tested through
    `function_exists()`.
  - No URL literal. No new dependency.
- **Docs vs reality:**
  - **`blocks / content_html`** (step text): settled by DECISIONS "Every
    post's content is read as blocks; there is no `content_html`", so the
    copy takes `blocks` only.
  - **`media_sideload_image()` / `download_url()`** (step text): neither
    takes a size cap, so a lying server could stream past 10 MB. The plan
    uses the calls they are built on: `wp_safe_remote_get()` with
    `limit_response_size`, and `media_handle_sideload()`. It is the same
    core path, with the cap the step requires.
  - **"a pasted image"** (verification): the model in claude.ai sees a
    pasted image but cannot write its bytes into a tool call.
    - `base64` is still built (step text), unit-tested and probed locally
      over MCP with a small PNG.
    - The guide's claude.ai check uses a URL, which the step's «a pasted
      image **or** a URL» allows.
  - **"the Sprint 1 service"** (verification): Step 1's guide deleted its
    test service at cleanup. The guide creates a Ukrainian test service first
    (`create-post`), then its copy. Locally only 1 of 37 Ukrainian services
    and 1 of 13 posts lack a Russian copy, and no employee has one (0 of 56).
  - **Polylang:** `sync` is empty and `media_support` is off. The copy sets
    its own terms, template and thumbnail (as `PostCopier` does), and an
    attachment gets no language.
  - **Title and status of the copy** are required. A copy with the
    Ukrainian title is not a translation, and a status default is refused by
    DECISIONS 2026-11-05.
  - **Carried from Step 1's WORKLOG entry** (none changes a task here):
    - the Sprint 1 Step 3 dump numbers are unreported;
    - the local `DOVIRA_MCP_DUMP_DIR` points at dev's path;
    - `master` is not on `origin/main`;
    - the production prerequisites belong to Step 3.
- **Design:** n/a (no UI; the surface is the tool catalogue, DECISIONS
  "No UI design phase").
- **Check command:** `bin/check.sh` (docs/TECH-STACK.md → Check command).
- **Not locally verifiable:** n/a. Everything runs on DDEV. claude.ai on
  `dev.dovira.vet` is the guide's own check.

### Questions / ambiguities
none
