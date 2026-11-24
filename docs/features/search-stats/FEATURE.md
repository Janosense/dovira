# Feature — search-stats

<!-- playbook: v3.0.4. State document, read whole at the start of every step
     of the feature. Shape, triggers and what does not belong here:
     docs/CONTRACTS.md → `### docs/features/{feature}/FEATURE.md`. -->

## Scope
Records what visitors search for on each city site — the header search, the
filter of the services list and the filter of a service's price list — and
adds up to three blocks with the five most frequent queries of yesterday and
of the last 28 days to the daily Telegram report of `ga-telegram-bridge`, so
the owner sees what people look for and what they do not find.

## Fit
- **Code path:** `wp-content/themes/dovira/inc/features/search-stats/` (JS in `source/scripts/features/search-stats/`, tests in `tests/Unit/SearchStats/`)
- **Entry point:** `inc/features/search-stats/bootstrap.php`, required by one line in `functions.php`
- **Depends on:** `search.php` (prints the query and its results count as data attributes); `source/scripts/modules/services-search.js` and `init-service-price-lists.js` (hand their inputs to the recorder); `inc/acf/blocks/services/template.php` and `single-service.php` (print `data-search-stats-context`); `source/scripts/app.js` (calls `recordSiteSearch()`); `inc/rest-api.php` (registers the controller); plugin `ga-telegram-bridge` ≥ 0.3.0 for the filter `gatb_extra_blocks` — without it nothing is rendered and recording continues
- **Exposes:** the route `POST dovira/v1/search-stats/record` (`API.md`); the filter `dovira_search_stats_retention_days` (int, default 90, at least 29); `recordSearch( level, query, contextId, results )` in `record.js`

## Structure
| Module | Where | Responsibility |
|---|---|---|
| `Schema` | `inc/features/search-stats/Schema.php` | creates and upgrades the table with `dbDelta()` |
| `Purge` | `inc/features/search-stats/Purge.php` | the daily deletion of old rows |
| `Normalizer` | `inc/features/search-stats/Normalizer.php` | the one normalization of a query |
| `RecordController`, `Repository` | `inc/features/search-stats/` | validation and the one insert; the report's reads |
| `ResultsCount` | `inc/features/search-stats/ResultsCount.php` | counts what `search.php` lists |
| `Periods`, `Stats`, `SearchStats`, `TopQuery` | `inc/features/search-stats/` | yesterday and 28 days in the site's zone; the six top-five lists |
| `Renderer` | `inc/features/search-stats/Renderer.php` | the blocks of the message; the `gatb_extra_blocks` callback |
| `record.js` | `source/scripts/features/search-stats/record.js` | the browser recorder |

## Data
- table `{prefix}dovira_search_queries`
- option `dovira_search_stats_db_version`
- cron event `dovira_search_stats_purge`

## UI
| Screen | Route | In code | Artboard |
|---|---|---|---|
| Search blocks of the daily report | the Telegram message of `ga-telegram-bridge`, after its own blocks and before the GA link | `Renderer::blocks()` | none — the template below |

The template, one blank line between blocks:
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
Up to five rows per list, by count, then by the latest search; an empty list
prints `—`, and a level with two empty lists adds no block; a title is the
Ukrainian source post of `context_id`, or `(видалено)`; a query longer than
40 characters keeps its first 40 and gets `…`; `{count}` is plain digits.

## Roadmap
- Sprint 1 — every search on both sites lands in the table, so rows accumulate on production before the first report (`sprints/SPRINT-1.md`)
- Sprint 2 — the morning report on both installs carries the three search blocks (`sprints/SPRINT-2.md`)
