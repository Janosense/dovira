# SPRINT 2 — Create content: pages, blog posts, translations, media (after Sprint 1)

<!-- playbook: v1.25. Written by discovery (Phase C) together with every
     other sprint of the plan — never by Claude Code. Rewritten by a
     re-planning chat only while no step is closed; afterwards steps may
     only be appended. This file
     describes the sprint and nothing else: goal, fixed decisions, steps.
     The only thing written here during the sprint is the tick in a step's
     heading, by /close-step. -->
**Branch:** `content-mcp/sprint-2-create` task branches → `master` (then `master` → `kyiv`)
**Goal:** On `dev.dovira.vet`, and on both productions after the developer's hand deploy and the checklist, a person in claude.ai creates a new page built from the theme's blocks, a blog post with a category and a tag that may be new, a service with its price list and image, an employee with a photo, a vacancy — each in the status they asked for, never a default — and then its Russian copy, linked in Polylang so the language switcher finds it; an image is uploaded from a URL or from the chat and attached where it belongs. Every creation goes through the preview and the session's dump like every write of Sprint 1. The production checklist names everything an install needs before the first connector session, and both productions run the connector for the three people.

## Fixed decisions
- Every entry listed under Fixed decisions of `sprints/SPRINT-1.md` (2026-11-05), unchanged.
- DECISIONS "The tool catalogue speaks ACF field names and block names, prices have their own tools, and a price change mirrors into the Russian copy by row index" (2026-11-05): `create-post` takes the same `acf` / `blocks` / `content_html` shapes `update-post` takes; no default status; the Russian text comes from the agent, never from a server-side API.
- DECISIONS "The connector is verified on `dev.dovira.vet`; the adapter plugin is committed with the other plugins" (2026-11-05): production deploys are the user's hand deploys from `master` and `kyiv`; a step only writes the checklist they follow.
- Root `CLAUDE.md` domain invariant 2 (`service-city` never written or created), 6 (no field group is created — a new post uses the existing groups), 7 (Ukrainian is the source, `/ru/` is `noindex` — a Russian copy is always a translation of an existing Ukrainian post).

## Steps
<!-- Ordered by dependency. One step = one /plan-step → /do-step → /close-step
     cycle and must fit one working session. Every step has all five
     subsections, even if a subsection is "—". The checkbox in the heading is
     ticked by /close-step. -->

### [x] Step 1 — Creating posts of every writable type, and blog terms
- **Tasks:**
  - `PostWriter::create()`: `post_type` (one of `service`, `employee`, `vacancy`, `page`, `post`; `post` only where the install has a blog — the `news` page exists, else refused), `lang` (`uk`; `ru` only through `create-translation`), `title`, `status` (required — `draft`, `pending`, `publish`, `private`; no default), `slug`, `parent`, `menu_order`, `terms`, `acf`, `blocks` | `content_html` — the same shapes and validation as the update path, plus the required ACF fields of the type (from `Schema`) checked before anything is inserted (root invariant 8): a `service` without `image` is refused with the field named. `wp_insert_post()` as the connected user, `pll_set_post_language()`, then the ACF fields, then the terms; the preview lists every value that will be written.
  - `TermWriter`: `create` / `update` for `category` and `post_tag` only (`name`, `slug`, `description`, `parent` for categories); any other taxonomy is refused by schema. Terms are not translated by this feature (the blog's taxonomies follow Polylang's own setting — the step records which in the report).
  - Abilities `dovira/create-post` (preview/write; `permission_callback`: `create_posts` of the type's capability object, `publish_posts` when `status` is `publish`), `dovira/create-term` and `dovira/update-term` (preview/write; `manage_categories`).
- **Tests:** `PostWriterTest` (create: required fields refused with names; `post` refused without a blog page; status required; `lang` other than `uk` refused; the written values equal the preview), `TermWriterTest` (taxonomy allow-list; parent only for categories), permission callbacks (`publish` needs `publish_posts`).
- **Verification (manual):** In claude.ai on `dev.dovira.vet`: «створи чернетку новини про акцію на вакцинацію, рубрика Акції (нова), тег вакцинація» → a preview with the title, the HTML, the new term and the status `draft`, consent, the post is in wp-admin as a draft with the term; «створи сторінку Банк крові з hero і текстовим блоком, опублікуй» → the page renders the two blocks; «додай послугу Х з двома рядками прайсу» without an image → the refusal names `image`; the same with an image id → the service with its prices; «додай співробітника …» → the employee on the team page. Each in a fresh chat leaves one new dump.
- **Docs to update:** `docs/features/content-mcp/FEATURE.md` → Interfaces; `docs/DOMAIN.md` (glossary: the connector's "preview", if not yet there).
- **Depends on:** Sprint 1 closed (`master`)

### [x] Step 2 — The Russian copy, and images
- **Tasks:**
  - `Translator` (`dovira\ContentMcp\Translator`): `create-translation` for a Ukrainian post that has no Russian copy — copies the source (`title`, `status` as given, `acf`, `blocks` / `content_html`, terms, parent resolved to the parent's own translation when it exists), overlays the translated values the payload carries, `pll_set_post_language( …, 'ru' )`, `pll_save_post_translations()` — the same calls `inc/cli/` makes; fields the payload does not translate stay as the source's (prices identical by rule). A post that already has a copy is refused with the copy's id (use `update-post` on it). The preview lists every value of the copy, translated or carried over.
  - `MediaWriter`: `upload-media` with either `url` (sideloaded with `media_sideload_image()` / `download_url()` — only `http(s)`, only image mime types, size cap filterable `dovira_mcp_upload_max_bytes`, default 10 MB) or `base64` (same checks, decoded to a temp file), `filename`, `alt`, `title`, optional `attach_to` (post id) — returns `{id, url, alt, mime, width, height}`. The preview states the source and the target; the upload itself happens only on the confirmed call.
  - Abilities `dovira/create-translation` (preview/write; `edit_post` of the source and `create_posts`), `dovira/upload-media` (preview/write; `upload_files`; `edit_post` of `attach_to`).
- **Tests:** `TranslatorTest` (the copy carries untranslated fields, overlays translated ones, links both languages, refuses an existing copy, parent resolved to its translation), `MediaWriterTest` (scheme and mime allow-lists, size cap, base64 decoding, `alt` written as `_wp_attachment_image_alt`, attach only when asked). Network calls stubbed.
- **Verification (manual):** In claude.ai on `dev.dovira.vet`: for the Sprint 1 service «зроби російську копію» → the agent proposes the Russian title and texts, the preview lists them with the prices carried over, consent, the Russian page exists and the language switcher of the Ukrainian one links to it; asking again → the refusal with the copy's id; «заміни фото співробітника Х на це» with a pasted image or a URL → the preview, consent, the image is in the media library with its alt and shown on the employee's card; a `.exe` URL or a 20 MB image is refused before anything is downloaded.
- **Docs to update:** `docs/features/content-mcp/FEATURE.md` → Interfaces, Invariants; `docs/ARCHITECTURE.md` → Data flows (the Russian copy); `docs/DATA-MODEL.md` (attachments written by the connector carry the connected user).
- **Depends on:** Step 1

### [ ] Step 3 — Production checklist and handover
- **Tasks:**
  - `docs/features/content-mcp/PRODUCTION-CHECKLIST.md` (started in Sprint 1 Step 1): for each install — the plugin activated; `DOVIRA_MCP_DUMP_DIR` defined in `wp-config.php` and the folder created, writable, outside the webroot (or the fallback's 403 checked); an application password per person (owner, content manager, developer) created in their own WP profile, named `claude`; the connector added in each person's own claude.ai with the install's URL and the Basic header; the first session's smoke test (`describe-site`, one price preview without consent, no dump written); the retention cron present (`wp cron event list`); how the developer restores a dump (`wp db import` from the `.sql.gz`) and how a person is removed (revoke the password). No secret or password value is written into the repository.
  - `describe-site` final pass: the instructions text names every tool and the protocol; the server returns the catalogue in the order a chat uses it.
  - `docs/ARCHITECTURE.md` → Environments & deploy: the connector per install (two URLs, two sets of passwords, one code); → Integrations: the MCP Adapter row and the claude.ai client; `docs/TECH-STACK.md` row verified on both productions after the user's deploys.
  - Result-size guard: every list ability caps `per_page` at 50 and `get-post` truncates no field but reports `content_html` length; documented in FEATURE.md → Interfaces.
- **Tests:** The existing suites pass; `describe-site`'s instructions text names every registered ability (a test over the catalogue).
- **Verification (manual):** The user follows the checklist on `dovira.vet` after the hand deploy from `master`, then on `kyiv.dovira.vet` after merging `master` into `kyiv` and deploying: each of the three people connects from their own claude.ai, `describe-site` names the right city, a price preview appears and no dump is written until a consent; one confirmed small change on each production leaves one dump in that install's folder; the editor cannot trash, the administrators can. The checklist is complete when both productions pass it; the step's own verification on `dev.dovira.vet` is the same list.
- **Docs to update:** `PRODUCTION-CHECKLIST.md`; `docs/ARCHITECTURE.md`; `docs/TECH-STACK.md`; `docs/features/content-mcp/FEATURE.md` → Roadmap (Sprint 2 done); `docs/DOMAIN.md` → Roles (the three connector users).
- **Depends on:** Step 2
