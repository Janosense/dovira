# Feature — search-stats

<!-- playbook: v1.21. Lightweight ARCHITECTURE + DATA-MODEL for one
     feature. Lives at docs/features/search-stats/FEATURE.md next to its
     sprints/. Root ARCHITECTURE.md holds only one row + a link here.
     Keep ≤80 lines. -->

## Purpose & scope
Records what visitors search for on the site itself — the header search
(`?s=`), the filter of the `services` block and the price-list filter on a
service page — on each city install, and adds three blocks with the five most
frequent queries of yesterday and of the last 28 days to the daily Telegram
report `ga-telegram-bridge` already sends, so the owner sees what people look
for and what they do not find. OUT of scope: a wp-admin screen or export of
the queries, clicks on results, changes to how any search works (relevance,
suggestions), a separate message or chat, sending anything to Google Analytics,
counting visitors without JavaScript.

## Fit into the host
- **Code location:** `wp-content/themes/dovira/inc/features/search-stats/` (+ `source/scripts/features/search-stats/`, `tests/Unit/SearchStats/`)
- **Host area:** theme `dovira` — obeys its `CLAUDE.md` isolation rules
- **Entry point:** `inc/features/search-stats/bootstrap.php`, required by one line in `functions.php`
- **Shared code it depends on:** `search.php` (prints the query and its results count as data attributes), `source/scripts/modules/services-search.js` and `source/scripts/modules/init-service-price-lists.js` (call the recorder), `source/scripts/app.js` (imports the module), `dovira\` REST registration in `inc/rest-api.php`; plugin `ga-telegram-bridge` ≥ 0.3.0 for the filter `gatb_extra_blocks` (absent plugin = nothing is rendered, recording continues)

## Data
Owns the table `{$wpdb->prefix}dovira_search_queries` (`id`, `level` ∈ `site`
| `services` | `service`, `query_text` normalized, `context_id` post id or 0,
`results` count for `site` else `NULL`, `created_at` UTC; details in
`docs/DATA-MODEL.md`), the option `dovira_search_stats_db_version` (schema
version for `dbDelta`), the daily cron hook `dovira_search_stats_purge` and
the filter `dovira_search_stats_retention_days` (default 90, floor 29). No
other feature writes to this table; nothing removes it — a theme has no
uninstall, so dropping it is a documented manual step.

## Invariants
- The REST route validates everything before writing and writes at most one row per request (root invariant 8); an invalid request leaves no trace.
- Normalization happens in one place, on the server (`mb_strtolower`, trim, whitespace collapsed); only the normalized text is stored and printed.
- One search = one row: the browser sends a value once it has rested for the pause or the field lost focus, at least 3 characters, never the same value twice from the same input in a page view; `search.php` sends its query once per results page.
- No personal data: no IP, user agent, cookie or user id is stored.
- Public pages never call Telegram: the theme only hands HTML to the plugin's filter, inside the plugin's own run.
- A purge is the only delete and runs from cron, never from a public request.
- Rendering reads the table with two queries per level (yesterday, 28 days), top-5 each; nothing derived is stored.

## Interfaces
- **REST:** `POST dovira/v1/search-stats/record` — public; JSON `{level, query, context_id, results}`; `204` on success, `400` on refusal (WordPress's error body `{code, message, data: {status}}`), `500` when the row could not be written (DECISIONS "Every search is recorded from the browser through one public REST route").
- **Hook consumed:** `gatb_extra_blocks` (plugin `ga-telegram-bridge`) — the theme returns the plugin's list plus its three blocks (DECISIONS "A theme feature that records site search, plugged into the daily report through a new plugin filter").
- **Filter exposed:** `dovira_search_stats_retention_days` (int).
- **JS:** `recordSearch(level, query, contextId, results)` from `source/scripts/features/search-stats/record.js`; the two filters and `search.php` are its only callers — `search.php` through `recordSiteSearch()`, which `app.js` calls once on load and which reads the results section's `data-search-stats-query` / `data-search-stats-results`.

## UI
- **Screens:** none of its own — the three blocks inside the plugin's message (no design export; DECISIONS "No UI design phase; the three sections are fixed as a template in FEATURE.md"):
  ```
  🔎 <b>Пошук по сайту</b>
  Вчора:
  1. {query} — {count}[ · нічого не знайдено]
  За 28 днів:
  1. {query} — {count}[ · нічого не знайдено]

  🗂 <b>Пошук у переліку послуг</b>
  Вчора:
  1. {query} — {page title} — {count}
  За 28 днів:
  —

  💊 <b>Пошук у послугах</b>
  Вчора:
  1. {query} — {service title} — {count}
  За 28 днів:
  1. {query} — {service title} — {count}
  ```
  Up to five rows per list, ordered by count then by the most recent search; the marker when `MAX(results) = 0` over the period; `{page title}` / `{service title}` = the Ukrainian source post of `context_id` (`(видалено)` when gone); a query longer than 40 characters is cut with `…`; every value HTML-escaped; an empty list prints `—`; a block with two empty lists is not contributed; the blocks sit after the plugin's own blocks and before the GA link, one blank line between blocks (DECISIONS "Three blocks, each with a yesterday list and a 28-day list").
- **Reuses:** —
- **Introduces:** —

## Roadmap
- Sprint 1 — every search on both sites lands in the table, so rows accumulate on production before the first report (`sprints/SPRINT-1.md`)
- Sprint 2 — the morning report on both installs carries the three search blocks (`sprints/SPRINT-2.md`)
- Later (not planned): a wp-admin list of queries, clicks on results, a weekly view
