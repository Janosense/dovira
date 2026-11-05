# SPRINT 2 — PLAN (content-mcp)

<!-- Written by /plan-step, one section per step; executed by /do-step,
     closed by /close-step. -->

## Plan — Sprint 2, Step 1: Creating posts of every writable type, and blog terms   (status: implemented, awaiting verification)

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
