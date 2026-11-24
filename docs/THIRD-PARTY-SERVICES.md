# Third-party services — Dovira

<!-- playbook: v3.0.4. State document: one block per service the code calls;
     a block is replaced, never appended to. Shape, triggers and what does
     not belong here: docs/CONTRACTS.md → `### docs/THIRD-PARTY-SERVICES.md`. -->

## Rules
- Secrets live in each install's own `wp-config.php` as constants, except `ANTHROPIC_API_KEY`, which is in the theme `.env`; neither file is committed, and the theme's `.env.example` lists the `.env` names with empty values.
- The plugin `ga-telegram-bridge` also accepts its two secrets on its settings screen, and a defined constant overrides the stored value (both Dovira installs use the constants).
- Tests never reach a service: the plugin's and the theme's suites stub `wp_remote_post` / `wp_remote_get` with recorded or written responses, and a test that needs a secret constant defines a dummy in its own process (`TESTING.md` → Rules).
- No health command covers the services; each block's Check is a manual action on the install.
- Set-up steps for a person — the hosting, dashboards, the per-install checklists — are in `docs/ops/` (`content-mcp-production-checklist.md`, `ga-telegram-bridge-production-checklist.md`).

## Services

### Telegram Bot API — the theme's notification bot
- **`.env` names** (constants in `wp-config.php`):

  | Name | Role |
  |---|---|
  | `DOVIRA_TELEGRAM_BOT_TOKEN` | calls the API; unset → no notification is sent |
  | `DOVIRA_TELEGRAM_JOIN_PASSWORD` | the password a chat types to subscribe; unset never matches |
  | `DOVIRA_TELEGRAM_CHAT_ID` | the one group every notification also goes to (`/^-?\d+$/`, a group is `-100…`) |
  | `DOVIRA_TELEGRAM_THREAD_ID` | optional topic of that group (`/^[1-9]\d*$/`); unset = the group itself |
- **Webhook address:** `dovira/v1/telegram/handle-updates` (no webhook is set at Telegram, so it receives nothing).
- **Assumed configuration:** the bot is a member of the group `DOVIRA_TELEGRAM_CHAT_ID` names and may post in its topic.
- **Locally:** DDEV's `wp-config.php` defines the constants by hand; without the token the forms save their records and only `error_log` notes the missing constant.
- **Check:** submit the contact form on the install — the record appears under Conversations and the message arrives in the group's topic.

### Telegram Bot API — the report bot of `ga-telegram-bridge`
- **`.env` names** (constant in `wp-config.php`):

  | Name | Role |
  |---|---|
  | `GATB_TELEGRAM_BOT_TOKEN` | calls the API; its own bot, not the theme's |
- **Webhook address:** none.
- **Assumed configuration:** the bot may post in the chat (and the thread) saved on Settings → «GA → Telegram».
- **Locally:** the settings screen of the DDEV site takes a test bot's token and chat.
- **Check:** Settings → «GA → Telegram» → *Check Telegram* — the test message arrives in the chat and the screen shows success.

### Google Analytics 4 Data API
- **`.env` names** (constant in `wp-config.php`):

  | Name | Role |
  |---|---|
  | `GATB_GA_SERVICE_ACCOUNT_JSON` | the service account's key; signs the JWT exchanged for an access token |
- **Webhook address:** none.
- **Assumed configuration:** the Data API is enabled for the key's Google Cloud project, and the service account has Viewer access to the install's GA4 property (Kharkiv: web stream `G-HKYZFG0E2W`; Kyiv: `G-Q597WTF16L`).
- **Locally:** the DDEV site uses a property the key can read, or the *Preview* is skipped.
- **Check:** Settings → «GA → Telegram» → *Check GA* — the screen names the property id and its reporting time zone.

### Anthropic Messages API
- **`.env` names:**

  | Name | Role |
  |---|---|
  | `ANTHROPIC_API_KEY` | calls the API, read by the SDK in `wp dovira translate*` |
- **Webhook address:** none.
- **Assumed configuration:** the key's workspace may use the model the command defaults to (`claude-haiku-4-5`).
- **Locally:** the key goes into the theme `.env` of the machine that runs the command.
- **Check:** `ddev wp dovira translate --post-type=page --dry-run` lists what would be translated without an error.
