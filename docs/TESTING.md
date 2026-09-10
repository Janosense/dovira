# Testing — Dovira

<!-- Created by the ga-telegram-bridge discovery (2026-09-08) per DECISIONS
     "Testing tooling and the project check command". The theme has no tests
     (see TECH-STACK.md); this file governs the plugin and any future feature. -->

## Profile
Developer-reviewed (root `CLAUDE.md`): the user reads code and diffs; tests
are still mandatory in the test-critical zones and for every piece of pure
logic in a plugin.

## Levels
| Level | What | Where | Runs WordPress? |
|---|---|---|---|
| Unit | pure logic: JWT building, response parsing, report maths, message rendering, sanitizers, scheduling maths, state transitions | `wp-content/plugins/ga-telegram-bridge/tests/Unit/` | No — Brain\Monkey stubs `__()`, `esc_html()`, `get_option()`, `wp_remote_post()` etc. |
| Integration | none automated in v1; the manual verification guides written by `/close-step` (`docs/features/{feature}/verification/`) are the regression suite | — | — |

## How to run
- Everything that gates a commit: `bin/check.sh` from the repo root (PHPCS →
  PHPStan → PHPUnit in the plugin → `php -l` over the theme). Needs PHP ≥ 8.3
  and Composer on PATH; `ddev exec bash bin/check.sh` works too.
- Plugin only: `cd wp-content/plugins/ga-telegram-bridge && composer install`,
  then `composer test` (PHPUnit), `composer lint` / `composer lint:fix` (PHPCS /
  PHPCBF) and `composer analyse` (PHPStan).
- **Suite order is fixed, not alphabetical.** `phpunit.xml.dist` declares two
  suites so that `SettingsSecretConstantsTest` — the one class that defines the
  real `GATB_GA_SERVICE_ACCOUNT_JSON` and `GATB_TELEGRAM_BOT_TOKEN` — runs last.
  A PHP constant cannot be undefined, so once that class has run, every later
  test sees a secret it cannot control: `Settings::telegram_bot_token()` returns
  the constant no matter what `get_option()` is stubbed to. Any test that needs
  an unset or a chosen secret must therefore be in the first suite. Adding a
  test class needs nothing; adding a second class that defines **secret**
  constants means moving it into the last suite too. `UninstallTest` defines
  `WP_UNINSTALL_PLUGIN`, which nothing else reads, and stays in the first suite.

## Fixtures
- Google responses live in `tests/fixtures/ga/*.json` (Data API and the token
  endpoint, one directory), Telegram responses in `tests/fixtures/telegram/`.
- Every file keeps the envelope the Sprint 1 spike recorded —
  `{"status": …, "body": …, "ms": …}` — so a test rebuilds a `wp_remote_post`
  response from it instead of hand-writing one.
- **Recorded vs. written.** A plain `*.json` is a real response captured against
  the live API. One that could not be captured is named `*.written.json` and is
  built from the vendor's documentation. The distinction matters: a written
  fixture proves only that our parser handles the shape we believe in, so a
  written case still needs a real run somewhere — the step's manual verification
  guide, if nothing else.
  - Google (`tests/fixtures/ga/`): written are `token-success.written.json` (the
    spike deliberately never printed a real access token) and
    `error-quota-exceeded.written.json` (one PHP process cannot exhaust the
    quota; see LEARNINGS "Sprint 1 spike findings"). The other six are recorded.
  - Telegram (`tests/fixtures/telegram/`): recorded are `error-unauthorized.json`
    (401) and `error-not-found.json` (404) — a wrong or malformed token needs no
    credential to provoke, so both were captured against the live API. The other
    five (the successful send, `chat not found`, `can't parse entities`,
    `Forbidden`, `Too Many Requests`) all need a working bot token and are
    written from Telegram's Bot API documentation.
- A recorded fixture is committed only after the property id, the
  service-account address and any token are checked for and removed. The six
  Sprint 1 Google dumps contained none of them and are committed unchanged; the
  two Telegram recordings are constant refusals with no request data in them.
- A throwaway RSA key pair for JWT tests is generated in the test bootstrap
  (`tests/TestKey.php`, one 2048-bit pair per run, ~30 ms) — never a real
  service-account key in the repo.

## Never mocked
- The report maths (`Dynamics`), the response parsers and `MessageRenderer`
  run on real recorded payloads — a test that stubs them tests nothing.
  For the daily report those payloads are
  `batch-run-reports-daily-call-{1,2}.json`: the two `batchRunReports` calls
  `ReportBuilder` composes, recorded against the live property. They are worth
  reading before changing the parser, because the real data carries the awkward
  cases — the visitors rows come back ordered by metric (`date_range_3` first,
  `date_range_0` last), the cities list contains a real `(not set)` row, and the
  28-day pages list holds the same path twice under two titles. The empty-property
  shape is the one written fixture (`batch-run-reports-no-data.written.json`):
  a property with traffic cannot produce it.
- `MessageRenderer` is asserted as **whole messages**, built from those same
  recorded payloads through the real `ReportBuilder`. Two WordPress functions
  are stubbed inside it — `wp_date()` and `number_format_i18n()` — with what
  WordPress does in **English**, so the expected messages in the test are the
  English source strings; Brain\Monkey's translation stubs return the original
  anyway. The Ukrainian rendering is therefore proven by the step's manual
  verification guide and by nothing else. One test stubs
  `number_format_i18n()` with the Ukrainian thousands separator, which really is
  the string `&nbsp;`, because Telegram accepts only four named entities
  (`&lt;`, `&gt;`, `&amp;`, `&quot;`) and would otherwise print it or refuse the
  message.
- `openssl_sign`/`openssl_verify` — the signature is verified for real against
  the test key.
- Only the network boundary (`wp_remote_post`/`wp_remote_get`) and WordPress
  globals are stubbed.

## Rules
- A new fixture is added in the same commit as the parser that reads it.
- No test may contain a real token, key or chat id; the check command's
  PHPCS config may add a sniff for `private_key` literals if this is ever
  violated (see LEARNINGS.md).
