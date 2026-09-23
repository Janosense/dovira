# SPRINT 2 — The morning report carries the three search blocks (after Sprint 1)

<!-- playbook: v1.21. Written by discovery (Phase C) together with every
     other sprint of the plan — never by Claude Code. Rewritten by a
     re-planning chat only while no step is closed; afterwards steps may
     only be appended. This file
     describes the sprint and nothing else: goal, fixed decisions, steps.
     The only thing written here during the sprint is the tick in a step's
     heading, by /close-step. -->
**Branch:** `search-stats/sprint-2-report` task branches → `master` (then `master` → `kyiv`)
**Goal:** After the developer's hand deploy of the theme and plugin 0.3.0 to
both productions, the owner's daily message on each install ends — after the
plugin's own blocks and before the "Детальніше в Google Analytics" link — with
up to three blocks, exactly as `FEATURE.md` → UI shows them: what people
searched for on the site yesterday and over the last 28 days, which of those
queries found nothing, what they typed into the services list and inside
which service they searched for what. A morning with no searches shows the
message the plugin sent before this feature existed. *Preview* and *Send now*
on the plugin's Settings screen show the same blocks the schedule sends.

## Fixed decisions
- DECISIONS "A theme feature that records site search, plugged into the daily report through a new plugin filter" (2026-09-22): `gatb_extra_blocks` in `MessageRenderer`, after the plugin's blocks, before the link; plugin 0.3.0; the plugin never references the theme.
- DECISIONS "The plugin drops extra blocks that would push the message past Telegram's limit" (2026-09-23): measured on the text Telegram counts, from the end, noted in the run-log detail.
- DECISIONS "Three blocks, each with a yesterday list and a 28-day list" (2026-09-22): headings, row formats, ordering, the `MAX(results) = 0` marker, 40-character cut, `—` for an empty list, an empty block is not contributed, days in the site's zone.
- DECISIONS "No UI design phase; the three sections are fixed as a template in FEATURE.md" (2026-09-22).
- DECISIONS "The theme gets PHPUnit + Brain\Monkey, run by the check command" (2026-09-22): SQL is tested by the query the code builds and by shaped `$wpdb` results, never against a database.
- Plugin `CLAUDE.md`: a new string means regenerating the `.pot` and the `uk` `.mo`; `readme.txt` ASCII only; the version is read from the plugin header.

## Steps
<!-- Ordered by dependency. One step = one /plan-step → /do-step → /close-step
     cycle and must fit one working session. Every step has all five
     subsections, even if a subsection is "—". The checkbox in the heading is
     ticked by /close-step. -->

### [x] Step 1 — Plugin 0.3.0: the `gatb_extra_blocks` filter and the length guard
- **Tasks:**
  - `MessageRenderer::render()` applies `apply_filters( 'gatb_extra_blocks', array(), Report $report )` after the plugin's own blocks (**touches shared surface:** the plugin's public filters; consumers: this feature); a non-array return is ignored, non-string and empty entries are dropped, each remaining block is printed as one more block — one blank line before it — and the GA link stays last.
  - Length guard: while the message's text as Telegram counts it (tags stripped, entities decoded) exceeds `TelegramClient::MAX_TEXT_LENGTH` (4096), the last extra block is dropped; when any was dropped, the run-log detail line says how many. The plugin's own blocks are never dropped.
  - Version 0.3.0 in the header and `Stable tag`; readme changelog and a FAQ entry "Where do the extra blocks come from?" in ASCII; the `.pot`/`.po`/`.mo` regenerated for the new log string.
- **Tests:** `MessageRendererTest` — blocks printed in order, before the link, one blank line between them, empty and non-string entries dropped, a non-array return ignored, HTML passed through untouched (the theme escapes its own values); the guard drops from the end, never a plugin block, and reports the count; `PluginTest` version 0.3.0.
- **Verification (manual):** on the local install, with a throwaway `add_filter( 'gatb_extra_blocks', … )` in a mu-plugin returning two blocks, *Preview* on the plugin's Settings screen shows them between the devices block and the link; a block of 5000 characters is absent and the run log's detail line names one dropped block. Both productions receive 0.3.0 with the next hand deploy (files only, no settings change).
- **Docs to update:** `docs/features/ga-telegram-bridge/FEATURE.md` → Interfaces (the filter) and Invariants (the guard); `docs/ARCHITECTURE.md` → Modules row of the plugin (`MessageRenderer` applies three filters); `docs/DOMAIN.md` → "report block" gains the sentence that other parts of the site may add blocks.
- **Depends on:** —

### [x] Step 2 — Aggregation: top-5 per level for yesterday and for 28 days
- **Tasks:**
  - `Periods` helper: yesterday and the last 28 days as `[from, to)` UTC bounds computed from a clock and `wp_timezone()` (the clock is a parameter, as in the plugin's classes).
  - `Repository::top()` — one query per (level, period): `SELECT query_text, context_id, COUNT(*) AS n, MAX(results) AS max_results, MAX(created_at) AS last_at … GROUP BY query_text, context_id ORDER BY n DESC, last_at DESC LIMIT 5`; rows returned as a small value object (query, context id, count, nothing-found flag).
  - `Stats::build( clock )` — the six lists in a `SearchStats` value object; no caching, no stored derived value.
- **Tests:** `PeriodsTest` (fixed clock, Europe/Kyiv, both sides of midnight and both DST nights of 2026); `RepositoryTopTest` (the SQL string for each level and period; shaped `$wpdb->get_results()` rows mapped to the value object, the nothing-found flag only when `max_results` is 0, `NULL` for JS levels never flags); `StatsTest` (six lists, empty table gives six empty lists).
- **Verification (manual):** on the local install, seed rows across yesterday and 30 days ago with `ddev wp db query`, then `ddev wp eval` `Stats::build()`: the 30-day-old row is in no list, ties order by the newer search, a query searched twice with results 3 then 0 is not flagged, one searched 0 and 0 is.
- **Docs to update:** `docs/DATA-MODEL.md` → the table's section names the two reading queries and the keys they use; `docs/DOMAIN.md` → "nothing found" confirmed against the code.
- **Depends on:** — (Sprint 1 closed)

### [ ] Step 3 — The three blocks in the message
- **Tasks:**
  - `Renderer::blocks( SearchStats $stats ): array` — the template of `FEATURE.md` → UI: three blocks, `Вчора:` / `За 28 днів:` lists, row formats per level, the marker, the 40-character cut with `…`, `esc_html` on every value, `—` for an empty list, no block when both lists are empty; context titles through `pll_get_post( $id, pll_default_language() )` when Polylang is active, the post itself otherwise, `(видалено)` for a missing post.
  - `bootstrap.php` adds `add_filter( 'gatb_extra_blocks', … )` that appends the renderer's blocks to the list it receives, guarded by `function_exists`/class checks so an install without the plugin loads nothing extra.
- **Tests:** `RendererTest` against the template: a full three-block snapshot; each empty case; escaping of `<`, `&`, `"` in a query and in a title; the cut at 40 characters on a multibyte query; the deleted-post title; ordering preserved from the value object. `BootstrapFilterTest`: the plugin's list comes back with the three blocks appended, untouched when there is nothing to add.
- **Verification (manual):** on the local install with the seeded rows of Step 2, *Preview* on the plugin's Settings screen shows the three blocks between the devices block and the GA link, exactly as in `FEATURE.md` → UI; empty the table — the preview is the plugin's message alone; *Send now* delivers the same to the test chat. After the hand deploy of `master`/`kyiv` to both productions, the next morning's report on each install shows its own queries (Kyiv's rows never appear in Kharkiv's message).
- **Docs to update:** `docs/ARCHITECTURE.md` → Data flows (new flow "Daily report → search blocks"); `docs/features/search-stats/FEATURE.md` → UI confirmed against the rendered message; `docs/DOMAIN.md` → glossary of the daily report names the three search blocks.
- **Depends on:** Step 1, Step 2
