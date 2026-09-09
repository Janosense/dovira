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

## Fixtures
- Google responses live in `tests/fixtures/ga/*.json` (Data API and the token
  endpoint, one directory), Telegram responses in `tests/fixtures/telegram/`.
- Every file keeps the envelope the Sprint 1 spike recorded —
  `{"status": …, "body": …, "ms": …}` — so a test rebuilds a `wp_remote_post`
  response from it instead of hand-writing one.
- **Recorded vs. written.** A plain `*.json` is a real response captured against
  the live API. One that could not be captured is named `*.written.json` and is
  built from Google's documentation — today `token-success.written.json` (the
  spike deliberately never printed a real access token) and
  `error-quota-exceeded.written.json` (one PHP process cannot exhaust the quota;
  see LEARNINGS "Sprint 1 spike findings"). The distinction matters: a written
  fixture proves only that our parser handles the shape we believe in.
- A recorded fixture is committed only after the property id, the
  service-account address and any token are checked for and removed. The six
  Sprint 1 dumps contained none of them and are committed unchanged.
- A throwaway RSA key pair for JWT tests is generated in the test bootstrap
  (`tests/TestKey.php`, one 2048-bit pair per run, ~30 ms) — never a real
  service-account key in the repo.

## Never mocked
- The report maths (`Dynamics`), the response parsers and `MessageRenderer`
  run on real recorded payloads — a test that stubs them tests nothing.
- `openssl_sign`/`openssl_verify` — the signature is verified for real against
  the test key.
- Only the network boundary (`wp_remote_post`/`wp_remote_get`) and WordPress
  globals are stubbed.

## Rules
- A new fixture is added in the same commit as the parser that reads it.
- No test may contain a real token, key or chat id; the check command's
  PHPCS config may add a sniff for `private_key` literals if this is ever
  violated (see LEARNINGS.md).
