# API — Dovira

<!-- playbook: v3.0.4. State document: what the project exposes, the
     webhook it receives, the calls it makes to outside services. A row is
     replaced when the endpoint or call changes. Shape, triggers and what
     does not belong here: docs/CONTRACTS.md → `### docs/API.md`. -->

## Rules
- Every route sets an explicit `permission_callback`, and a public `dovira/v1` route validates every required field before it writes anything (CLAUDE.md → Domain invariants).
- An own endpoint refuses with a `WP_Error` carrying its HTTP status, so the body is WordPress's `{code, message, data: {status}}`.
- `dovira/v1` routes are public (`__return_true`); `mcp/dovira`, `wp-abilities/v1` and `dovira-bridge/v1` authenticate a WordPress user by application password and check that user's capabilities.
- The one incoming webhook (Telegram) appends the raw update before acting on it, and a form's Telegram call never changes the form's answer.
- Tests never call a real service: the network boundary is stubbed with recorded or written responses (`TESTING.md`).

## Exposed
| Method & path | Caller | Request | Response | Errors |
|---|---|---|---|---|
| `POST /wp-json/dovira/v1/questionary/save` | the blood-donor form (`questionary-form-handler.js`) | form fields; required `name`, `phone`, `animal`, `pet-sex`, `pet-old`, `pet-weight`, `vaccination-date`, `street`, `castration`, `donor-before`, `blood-take`, `chronic-diseases`, `pills` | 200 `{success, message, post_id}` | 400 `missing_required_field`; 500 `post_creation_failed` |
| `POST /wp-json/dovira/v1/search-stats/record` | `recordSearch()` in `source/scripts/features/search-stats/record.js` | JSON `{level: site\|services\|service, query, context_id, results}` | 204, no body | 400 on any invalid field; 500 `search_stats_not_recorded` |
| `GET /wp-json/dovira/v1/telegram/send-test-message` | nobody on the site (manual) | — | sends a fixed message to the subscribed chats | — |
| `GET /wp-json/dovira/v1/telegram/reset-telegram-log` | nobody on the site (manual) | — | empties the option `telegram_webhook_data` | — |
| `POST /wp-json/mcp/dovira` | a person's Claude (claude.ai, Claude Desktop) | MCP over Streamable HTTP, header `Mcp-Session-Id`; the tools of `Catalogue::TOOLS` | MCP results; a write's preview `{diff, warnings, confirmation}`, a write `{written, dump, warnings}` | 401 without a valid application password; tool errors `dovira_*` |
| `GET/POST /wp-json/wp-abilities/v1/abilities/dovira/*` | the same abilities through WordPress's Abilities REST API | the ability's input | the ability's output | as `mcp/dovira`; no session, so every write dumps |
| `GET /wp-json/dovira-bridge/v1/describe` | Dovira Bridge | — | the `conversation` type: fields, statuses | 401 not signed in; 403 without `edit_others_conversations` |
| `GET /wp-json/dovira-bridge/v1/conversations` | Dovira Bridge | `modified_after` (ISO 8601 UTC), `page`, `per_page` (≤ 50) | conversations, oldest modification first, with paging | 400 `dovira_bad_date`; 401; 403 |
| `GET /wp-json/dovira-bridge/v1/conversations/{id}` | Dovira Bridge | — | one conversation | 401; 403; 404 |
| `POST /wp-json/dovira-bridge/v1/conversations/{id}/status` | Dovira Bridge | `{field_kind, key, value}` | the updated conversation | 400 `dovira_bad_value`, `dovira_bad_status`; 401; 403; 404 |

## Incoming webhooks

### Telegram (the theme's bot)
- **Path:** `POST /wp-json/dovira/v1/telegram/handle-updates` — no webhook is set at Telegram, so nothing arrives.
- **Verified by:** nothing; a chat joins only by sending `DOVIRA_TELEGRAM_JOIN_PASSWORD`.
- **Stored as:** the raw update appended to the option `telegram_webhook_data`; a joined chat's id in `telegram_bot_chats`.
- **In the request / queued:** everything in the request; nothing is queued.
- **Answer:** 200 after the update is handled.

## Consumed

### Telegram Bot API
- **Base address:** `https://api.telegram.org/bot{token}/`
- **Auth:** the bot token in the path (theme: `DOVIRA_TELEGRAM_BOT_TOKEN`; plugin: its own token) — every text that may echo the URL is scrubbed of it.

| Call | Sent | Answered | Errors |
|---|---|---|---|
| `sendMessage` (theme, `wp_remote_get`) | `chat_id`, `text`, `parse_mode=HTML`, `message_thread_id` for the fixed thread | ignored | ignored |
| `sendMessage` (plugin, `POST`, 15 s) | `chat_id`, `text`, `parse_mode=HTML`, `link_preview_options.is_disabled`, `message_thread_id` when configured | `ok`, `result.message_id` | 400 `chat not found` / `can't parse entities`, 401, 403, 404, 429 with `parameters.retry_after`, 5xx — each a mapped `TelegramException` |

### Google OAuth 2.0 token endpoint
- **Base address:** `https://oauth2.googleapis.com/token`
- **Auth:** a JWT signed RS256 with the service account's private key, scope `https://www.googleapis.com/auth/analytics.readonly`.

| Call | Sent | Answered | Errors |
|---|---|---|---|
| `POST /token` (15 s) | `grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer`, `assertion` | `access_token`, `expires_in` | 400 `invalid_grant`, 401 — a mapped `GoogleAuthException` |

### Google Analytics 4 Data API
- **Base address:** `https://analyticsdata.googleapis.com/v1beta/properties/{property_id}`
- **Auth:** `Authorization: Bearer {access_token}`.

| Call | Sent | Answered | Errors |
|---|---|---|---|
| `:batchRunReports` (20 s) | up to five `requests`, each `dimensions`, `metrics`, relative `dateRanges` (`yesterday`, `NdaysAgo`), `orderBys`, `limit` | `reports[]`: `dimensionHeaders`, `metricHeaders`, `rows[].dimensionValues` / `metricValues`, `metadata.timeZone` | 400, 403 `PERMISSION_DENIED`, 404, 429 `RESOURCE_EXHAUSTED`, 5xx — a mapped `GaClientException`; 401 drops the cached token |
| `:runReport` (*Check GA*) | `activeUsers` for `yesterday`, `limit` 1 | `metadata.timeZone`, `metadata.currencyCode` | as above |

### Anthropic Messages API
- **Base address:** `https://api.anthropic.com/v1/messages`, through `anthropic-ai/sdk`.
- **Auth:** header `x-api-key` = `ANTHROPIC_API_KEY`.

| Call | Sent | Answered | Errors |
|---|---|---|---|
| `messages.create` | `model` (default `claude-haiku-4-5`), the chunk of strings (≤ 100 items / 8 000 characters), a JSON schema for the answer | the translated strings in that schema | SDK exceptions → `WP_CLI::error` |
