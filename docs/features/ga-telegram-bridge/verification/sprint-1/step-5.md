# Verification — ga-telegram-bridge, Sprint 1, Step 5

**TelegramClient and "Check Telegram"** · written at close on 2026-09-09
Branch merged: `ga-telegram-bridge/sprint-1-report-on-demand` → `master`

What this step promises: the plugin can post a message to one Telegram chat, and the
settings screen has a button that proves the bot may write there. Every way it can
fail says what to fix, in one sentence, and the bot token never appears in any of
them — not even when the failure quotes the request URL back.

Still nothing is composed from Google's data: the message here is a fixed test line.
The report itself is Steps 6 and 7, and sending it on a schedule is Sprint 2.

The screen is at **<https://dovira.ddev.site/wp-admin/options-general.php?page=gatb-settings>**.
Shell commands are run from the repo root.

## 0. What you need before starting — a bot and a chat

This is the Day-1 item in `SPRINT-1.md` → Risks, and nothing below works without it.

1. In Telegram, write to **@BotFather**, send `/newbot`, give it a name and a username.
   BotFather answers with a token that looks like `123456789:AAH…`. **Keep it in the
   chat, do not paste it into the settings form** — see step 1 for why.
2. Create the channel (or pick the chat) the report should land in, open its
   *Administrators*, and **add your bot as an administrator**. A bot cannot post into a
   channel it does not administer. If you would rather have the report as a private
   message, just write anything to the bot once — a bot may not open a conversation.
3. Find the chat id. Post any message in the channel, then open this in a browser,
   with your token in place of `<TOKEN>`:

   ```
   https://api.telegram.org/bot<TOKEN>/getUpdates
   ```

   Look for `"chat":{"id":-1001234567890,...}` — that number, minus included, is the
   chat id. A channel or group id starts with `-100`; a person's id is positive.

## 1. Give the site the token, without putting it in the database

`wp-config.php` is gitignored here, and a token pasted into the settings form would end
up in the database and from there in a `mysql.sql` dump. So add the constant instead —
above `require_once ABSPATH . '/wp-settings.php';`:

```php
define( 'GATB_TELEGRAM_BOT_TOKEN', '123456789:AAH…' );
```

**Remove that line again when you are finished** — §8.

Now open the screen. The *Bot token* field must be **empty, read-only** and say
*Set in configuration (wp-config.php)* with `GATB_TELEGRAM_BOT_TOKEN` under it. Enter
the chat id from §0 into *Chat id* and save.

## 2. The screen offers two checks, and says which one sends something

Scroll to **Connection**, below the settings form.

Expected: two buttons, each with its own line of explanation —

> **Check GA** — Asks Google whether this site can read the configured property. Sends
> nothing to Telegram.
>
> **Check Telegram** — Really posts a short test message into the configured chat, so
> you can see it arrive.

That second sentence is the point of the pair: one button reads, the other writes, and
the screen says so before you press it.

## 3. Check Telegram delivers

Press **Check Telegram**.

Expected, in the chat you configured:

> 📊 **dovira.ddev.site** — test message from the Google Analytics → Telegram bridge.
> The daily report will arrive in this chat.

and on the screen, a green notice:

> Telegram accepted a test message for chat -1001234567890. Open that chat to see it.

The site host is bold because the message is sent with Telegram's HTML parse mode — the
same mode the daily report will use, so a bot that takes this line will take the report.

## 4. Negative check — a chat the bot cannot reach

Change *Chat id* to `-1009999999999` (a channel that does not exist) and save, then press
**Check Telegram**.

Expected: a **red** notice —

> Telegram cannot find that chat. Check the chat id — a channel id begins with -100 —
> and make sure the bot has been added to it.

Then try `abc` as the chat id: it is refused by the settings form itself (Step 3's
validation, digits with an optional minus), so no request ever leaves the site.

If you want to see the other half of this pair, remove the bot from the channel's
administrators and press the button again with the real chat id: Telegram answers that
the bot may not post there, and the notice tells you to add it back as an administrator.

## 5. Negative check — a wrong token never reveals itself

Point the constant at a token that belongs to no bot:

```bash
ddev wp --exec="define( 'GATB_TELEGRAM_BOT_TOKEN', '111111111:AAnot-a-real-bot-token' );" eval '$s = GaTelegramBridge\Settings::all(); $s["telegram_chat_id"] = "-1001234567890"; update_option( "gatb_settings", $s ); try { GaTelegramBridge\TelegramClient::send_message( GaTelegramBridge\Settings::telegram_chat_id(), GaTelegramBridge\Admin::test_message() ); echo "A FAKE TOKEN WAS ACCEPTED — STOP\n"; } catch ( Throwable $e ) { echo get_class( $e ) . ": " . $e->getMessage() . "\n"; echo ( false === strpos( $e->getMessage(), "111111111:AA" ) ? "no token in the message (good)\n" : "TOKEN LEAKED — STOP\n" ); }'
```

Expected (this was run before this guide was written):

```
GaTelegramBridge\TelegramException: Telegram did not accept the bot token. Check the
token BotFather issued for this bot — a token is revoked as soon as a new one is
generated.
no token in the message (good)
```

This matters more than it looks. The token is part of the **request URL**, so a network
error that quotes the URL back would otherwise print it into an admin notice and, from
Sprint 2, into the run log. Every value that reaches a message is scrubbed of the token
first; the unit tests hold that line with an error message that deliberately embeds it.

## 6. Negative check — an unconfigured site sends nothing

```bash
ddev wp eval 'delete_option( "gatb_settings" ); add_option( "gatb_settings", GaTelegramBridge\Settings::defaults(), "", false ); try { GaTelegramBridge\TelegramClient::send_message( GaTelegramBridge\Settings::telegram_chat_id(), "x" ); echo "A REQUEST WAS MADE WITHOUT A TOKEN — STOP\n"; } catch ( Throwable $e ) { echo $e->getMessage() . "\n"; }'
```

Expected: `No Telegram bot token is configured yet.` — refused locally, with no request
to Telegram. (Run before writing this guide.) With the token set but no chat id, the
sentence is `No Telegram chat id is configured yet.` Pressing **Check Telegram** on a
fresh install shows the same sentence as a red notice.

## 7. Negative check — the button cannot be triggered from outside

Open <https://dovira.ddev.site/wp-admin/admin-post.php?action=gatb_check_telegram>
directly in the browser (a GET, no nonce).

Expected: WordPress's own *"The link you followed has expired"* screen, and **no message
in your chat**. The check runs only from the form on the settings screen, and only for an
administrator — which matters more here than for *Check GA*, because this one writes.

## 8. Put the site back

```bash
ddev wp eval 'delete_option( "gatb_settings" ); add_option( "gatb_settings", GaTelegramBridge\Settings::defaults(), "", false );'
```

and delete the `define( 'GATB_TELEGRAM_BOT_TOKEN', … )` line from `wp-config.php`.

Keep the token and chat id somewhere safe — Step 8 ("Send now") and Sprint 2 need them.

## 9. The gate is green

```bash
bash bin/check.sh
```

Expected: `==> check: all green`, with `OK (129 tests, 318 assertions)`.

Two notes for when it is not:

- If PHPStan crashes with *"reached configured PHP memory limit"*, check that the
  `--memory-limit=1G` flag is still on the `phpstan analyse` line in `bin/check.sh`. The
  WordPress stubs each worker loads need it; removing `tests/` from the analysis does
  **not** help — measured, it saves 8 MB of 732 MB. See LEARNINGS, 2026-09-09.
- If many tests fail at once with a secret that is "wrong" everywhere, check
  `phpunit.xml.dist`: the class defining the `GATB_*` constants must stay alone in the
  second testsuite, or it poisons every class that runs after it. See
  `docs/TESTING.md` → How to run.
