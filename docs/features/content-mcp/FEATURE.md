# Feature — content-mcp

<!-- playbook: v3.0.4. State document, read whole at the start of every step
     of the feature. Shape, triggers and what does not belong here:
     docs/CONTRACTS.md → `### docs/features/{feature}/FEATURE.md`. -->

## Scope
An MCP connector to each city install through which the owner, the content
manager and the developer manage the site's content from Claude (claude.ai,
Claude Desktop) in their own words: read every post type, change prices, add
and edit pages, blog posts (Kharkiv only), services, employees and vacancies
with their Russian copies, upload images, and create and edit blog categories
and tags. Every person connects as their own WordPress user; every write is
previewed and needs that person's agreement, and the session's first write
is preceded by a database dump.

## Fit
- **Code path:** `wp-content/themes/dovira/inc/features/content-mcp/` (namespace `dovira\ContentMcp`, tests in `tests/Unit/ContentMcp/`); transport: the third-party plugin `wp-content/plugins/mcp-adapter/`, committed
- **Entry point:** `inc/features/content-mcp/bootstrap.php`, required by one line in `functions.php`; it does nothing unless `wp_register_ability()` and `WP\MCP\Core\McpAdapter` exist, then registers the category `dovira` (`wp_abilities_api_categories_init`), the abilities (`wp_abilities_api_init`) and the server (`mcp_adapter_init`)
- **Depends on:** the post types and taxonomies of `inc/post-types/` and `inc/taxonomies/`; the ACF field groups and block definitions of `inc/acf/**` and `dovira_allowed_block_types()`, read only; Polylang (`pll_get_post`, `pll_set_post_language`, `pll_save_post_translations`, `pll_get_term`); Cyr2Lat's Russian table for slugs; the `wp-config.php` constant `DOVIRA_MCP_DUMP_DIR`
- **Exposes:** the MCP server `https://{host}/wp-json/mcp/dovira` and the abilities `dovira/*` (also through `wp-abilities/v1`), listed in `Catalogue::TOOLS` in the order a chat uses them, with the instructions `Server::INSTRUCTIONS` (`API.md` → Exposed); the filters `dovira_mcp_dump_retention_days` (default 90, at least 1) and `dovira_mcp_upload_max_bytes` (default 10 MB, at least 1)

## Structure
| Module | Where | Responsibility |
|---|---|---|
| `Catalogue`, `Abilities`, `Server`, `Session` | `inc/features/content-mcp/` | the tool list, registration, the server and its instructions, the `Mcp-Session-Id` of the request |
| `Scope`, `Schema`, `DescribeSite` | `inc/features/content-mcp/` | what the connected user may read and write; the content model as `describe-site` shows it |
| `ReadTools`, `PostReader`, `FieldValues`, `CityPrice` and the read abilities (`ListPosts`, `GetPost`, `FindPriceRows`, `ListTerms`, `ListMedia`) | `inc/features/content-mcp/` | reading posts, ACF by name, blocks, prices in this city, terms and media |
| `WriteAbility`, `Confirmation`, `Diff`, `Dump` | `inc/features/content-mcp/` | the write protocol: preview, token, session dump, write |
| `PostWriter`, `FieldInput`, `PriceRows`, `Translator`, `TermWriter`, `MediaWriter` and the write abilities (`UpdatePriceRow`, `UpdatePost`, `CreateTerm`, `UpdateTerm`, `UploadMedia`, `CreatePost`, `CreateTranslation`, `TrashPost`) | `inc/features/content-mcp/` | each kind of write through WordPress's own functions |

## Data
- transients `dovira_mcp_confirm_{hash}`, `dovira_mcp_dumped_{session}`
- dump files `dovira-{host}-{YYYY-MM-DD}-{HHMMSS}.sql.gz` in `DOVIRA_MCP_DUMP_DIR` (fallback `wp-content/backup-db/` with the `.htaccess` and `index.php` it writes there)
- cron event `dovira_mcp_dump_retention`

## Roadmap
- Sprint 1 — read everything and change a price from Claude: the connector answers claude.ai, shows every post type, changes a price row after a preview with the session's dump taken first, and edits and trashes existing posts (`sprints/SPRINT-1.md`)
- Sprint 2 — create content: pages, blog posts, services, employees and vacancies, their Russian copies, images, blog terms, and the production checklist for both installs (`sprints/SPRINT-2.md`)
