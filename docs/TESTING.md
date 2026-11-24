# Testing — Dovira

<!-- playbook: v3.0.4. State document, read whole at "Tests to write" and
     before writing a test; a sentence or row that stops being true is
     edited or removed. Shape, triggers and what does not belong here:
     docs/CONTRACTS.md → `### docs/TESTING.md`. -->

## Layers
| Layer | Where | Proves | Cost of one test |
|---|---|---|---|
| Plugin unit | `wp-content/plugins/ga-telegram-bridge/tests/Unit/` | a rule of a plugin class — JWT, response parsing, report maths, message rendering, settings validation, scheduling — with WordPress functions and the network stubbed, no WordPress and no database | — |
| Theme unit | `wp-content/themes/dovira/tests/Unit/{Feature}/` | a rule of a theme feature's class or of `core`'s shared code — normalization, REST validation, the SQL a class builds and the shaping of its rows, rendering, a connector tool's preview and write — with `$wpdb` a test double, no WordPress and no database | — |
| Theme JS unit | `wp-content/themes/dovira/tests/js/{feature}/` | a rule of a pure JS module, in Vitest's `node` environment with no DOM | — |
| Page, DOM glue, real database, a live service | the step's verification guide | what only a browser, MariaDB or the service shows | a guide item |

The full run's time on the development machine: —.

## Run
- Everything: `bin/check.sh` from the repo root, on the host (`TECH-STACK.md` → Check command).
- The plugin alone: `cd wp-content/plugins/ga-telegram-bridge && composer test` (`composer lint`, `composer analyse` for PHPCS and PHPStan); one test: `composer test -- --filter {TestName}`.
- The theme alone: `cd wp-content/themes/dovira/tests && composer test`, one test with `-- --filter {TestName}`; its JS: `cd wp-content/themes/dovira && npm test`, one file with `npx vitest run tests/js/{feature}/{file}`.
- There is no test database: no suite boots WordPress or connects to MariaDB.

## Rules
- A theme test never uses a database: SQL is proven by asserting the query the class builds and by shaping fixed `$wpdb` rows, and what the query does in MariaDB is a guide item.
- The code under test is loaded with `require_once` by its path in the theme, as the theme loads it; never by a PSR-4 entry in `tests/composer.json` and never through a feature's `bootstrap.php`, which registers hooks.
- WordPress's own classes (`WP_Error`, `WP_HTTP_Response`, `WP_REST_Response`, `WP_REST_Request`, `WP_REST_Controller`) come from the committed `wp-includes/` through `tests/bootstrap.php`, never rewritten in `tests/`, so a REST test builds a real request.
- A class whose file needs `ABSPATH` or loads a library (`WP_Http`) stays out of the suites, and what it does is a guide item (for example a malformed JSON body refused before the route runs).
- Only WordPress globals, the network boundary and `$wpdb` are stubbed; the class under test, its parsers and its renderer always run for real.
- A Brain\Monkey expectation such as `Functions\expect( … )->never()` records what happened and is followed by an assertion, because a test without one fails the run.
- A `function_exists()` branch is tested through an injected check, never by leaving the function unstubbed: Brain\Monkey keeps every stubbed function declared for the rest of the run.
- Brain\Monkey's `esc_html` stub double-encodes, so a unit test cannot tell `esc_html()` from the escaping a Telegram message needs; the guide proves the message.
- In the plugin, a test class that defines a real secret constant (`GATB_GA_SERVICE_ACCOUNT_JSON`, `GATB_TELEGRAM_BOT_TOKEN`) belongs to the last suite of `phpunit.xml.dist`, since a constant cannot be undefined.
- In the theme, a test class that defines `DOVIRA_TELEGRAM_BOT_TOKEN` or `DOVIRA_TELEGRAM_JOIN_PASSWORD` runs in separate processes (`#[RunTestsInSeparateProcesses]`, `#[PreserveGlobalState( false )]`), and `SecretsUnsetTest` holds that the suite's own process never defines them.
- A test of code that calls `error_log()` declares `$this->expectErrorLog()`.
- A message is asserted whole, built through the real `ReportBuilder` from the recorded payloads, with `wp_date()` and `number_format_i18n()` stubbed in English; the Ukrainian rendering is a guide item.
- The clock a report test pins (`NOON` in `ReportBuilderTest`, `MessageRendererTest`, `RunnerTest`) is noon UTC of the day `batch-run-reports-daily-call-2.json` was recorded, and it moves with any re-recording of the daily calls.
- A fixture is added in the commit of the parser that reads it, and no test or fixture holds a real token, key, property id, service-account address or chat id.

## Fakes & helpers
- `tests/fixtures/ga/*.json`, `tests/fixtures/telegram/*.json` (plugin) — Google and Telegram responses in the envelope `{status, body, ms}`, rebuilt into a `wp_remote_post` response; a plain `*.json` is recorded against the live API, a `*.written.json` is built from the provider's documentation.
- `batch-run-reports-daily-call-1.json`, `batch-run-reports-daily-call-2.json` (plugin) — the two `batchRunReports` calls of a real daily report; `…-call-2-no-trend.json` is the call without the trend report.
- `TestKey` (plugin `tests/TestKey.php`) — a throwaway 2048-bit RSA pair generated per run, so `openssl_sign` / `openssl_verify` run for real.
- `SitePosts` (plugin `tests/SitePosts.php`) — stubs `url_to_postid()`, `get_permalink()`, `get_post_field()` with what the Kharkiv site answered for the recorded paths.
- `TestCase` (plugin and theme) — sets up and tears down Brain\Monkey.
- `WpdbDouble` (theme `tests/WpdbDouble.php`) — `$GLOBALS['wpdb']` for a test: records `prepare()`, `query()`, `insert()`, `get_results()`, answers `get_results()` from a queue of rows and `insert()` from `$insert_result`; extended when a class needs another method.
- Theme fixtures, when one is captured, go into `tests/fixtures/{feature}/`; rows a test shapes are written inside the test, next to the query they answer.
- There are no factories: a test builds its posts, rows and requests inline.
