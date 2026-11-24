# Verification — ga-telegram-bridge, Sprint 1, Step 8

**"Send now" and RunLog** · written at close on 2026-09-09
Branch merged: `ga-telegram-bridge/sprint-1-report-on-demand` → `master`

What this step promises: the report the plugin has been able to build since Step 6 and
to write out since Step 7 now really reaches Telegram when an administrator asks for it,
and every attempt — delivered or refused — is written down where it can be read.

**This is the one step that needs a Telegram bot.** Create it with
[@BotFather](https://t.me/BotFather), put it into the chat that is to receive the report
(a channel needs the bot as an **administrator**; a private chat needs you to write to the
bot once first), and have the token and the chat id to hand. Without them §1 and §2 cannot
be run, and the sprint's Definition of Done stays open — everything else below still works,
including the negative check in §4, which is what the dev site was left proving.

Everything happens on
**<https://dovira.ddev.site/wp-admin/options-general.php?page=gatb-settings>** unless a
shell command is given; those are run from the repo root.

## 0. Configure both sides, and count the notices

In *Google Analytics*: **Ідентифікатор ресурсу** `533779496`, **Ключ сервісного акаунта**
the contents of `wp-content/plugins/ga-telegram-bridge/spike/service-acount.json`. In
*Telegram*: **Токен бота** and **Ідентифікатор чату** from BotFather and your chat.

Save, then press **Перевірити GA** and **Перевірити Telegram**.

Expected: each answers with **exactly one** notice — the GA one naming the property and
`Europe/Kiev`, the Telegram one naming the chat, and the test message arriving in it. Two
identical notices would mean the screen has taken back the `settings_errors()` call Step 7
removed.

Below the buttons, **Журнал запусків** says *"Ще нічого не надсилалося."* — the log is empty
on a site that has never run.

## 1. Send the report

Press **Надіслати зараз**.

Expected, in this order:

1. **The report arrives in the chat**, as one message: the header with the site and the day,
   *Відвідувачі*, the two page blocks, sources, cities, devices. It is the message §1 of the
   Step 7 guide showed in the preview — the same text, now delivered.
2. On the screen, **exactly one** green notice: *"Звіт за 2026-09-08 надіслано в чат
   -100…"*.
3. **Журнал запусків** has gained one row:

   | Час | Запуск | Звіт за | Результат | Спроба | Деталі |
   |---|---|---|---|---|---|
   | today's time | Надіслати зараз | 2026-09-08 | Надіслано | 1 | Звіт за 2026-09-08 надіслано… |

The **Звіт за** column is the **property's** day, not the server's. That is the day the
plugin will refuse to send twice by itself — §3.

## 2. Press it again

Press **Надіслати зараз** a second time.

Expected: the message arrives again, and the log has **two** `Надіслано` rows for the same
day. That is deliberate: the date guard exists for the schedule, not for the person pressing
the button, and the log says `Надіслати зараз` so the history shows who asked.

## 3. The guard the button bypasses

```bash
ddev wp eval 'echo "state: " . json_encode( GaTelegramBridge\RunLog::state() ) . "\n";'
```

Expected: `state: {"last_report_date":"2026-09-08","attempt":0}` — the day that was
delivered, and a counter back at zero. From Sprint 2 the scheduled run compares its report's
day with exactly this value and sends nothing when they match; nothing on a schedule runs
yet.

## 4. Negative check — a refusal is recorded, and the day is not marked as sent

Change **Ідентифікатор чату** to `-1009999999999` (a channel that does not exist), save, and
press **Надіслати зараз**.

Expected: **one** red notice — *"Telegram не знаходить цей чат…"* — and one new row in the
log, `Помилка`, `Спроба 2`, with that reason in **Деталі**. Then:

```bash
ddev wp eval 'echo "state: " . json_encode( GaTelegramBridge\RunLog::state() ) . "\n";'
```

Expected: `last_report_date` is **still** `2026-09-08` from §1 — a failure never overwrites
the day that really went out — and `attempt` has gone up. That counter and that untouched
date are what Sprint 2's retry will read; if a failure marked the day as sent, the report
would simply be lost. Put the real chat id back and save.

## 5. Negative check — no secret is ever written to the database

```bash
ddev wp eval '$raw = (string) json_encode( array( get_option( "gatb_log" ), get_option( "gatb_state" ) ) ); $token = GaTelegramBridge\Settings::telegram_bot_token(); echo ( "" !== $token && false !== strpos( $raw, $token ) ) ? "THE TOKEN IS IN THE DATABASE — STOP\n" : "no token in the log or the state (good)\n";'
```

Expected: `no token in the log or the state (good)`. The bot token travels inside the request
URL, so a transport error can quote it back; every message that reaches the log has been
scrubbed of it first. The same holds for the service-account key, which no error message ever
carries.

## 6. The log, read from the shell

```bash
ddev wp eval 'foreach ( GaTelegramBridge\RunLog::entries() as $e ) { echo wp_date( "Y-m-d H:i", $e["time"] ) . "  " . str_pad( $e["trigger"], 7 ) . " " . $e["date"] . "  " . str_pad( $e["status"], 6 ) . " #" . $e["attempt"] . "  " . $e["message"] . "\n"; }'
```

Expected: the same rows the screen shows, newest first — for example

```
2026-09-09 16:32  manual  2026-09-08  failed #1  Токен бота Telegram ще не вказано.
```

The list stops at 30 runs; the 31st drops the oldest, which the test suite asserts on the
boundary rather than by pressing the button thirty-one times.

## 7. Negative check — a run with nothing configured says so, and still records it

This is what the dev site was left proving, and it needs no bot. Clear the **Токен бота**
and save, then press **Надіслати зараз**.

Expected: **one** red notice, *"Токен бота Telegram ще не вказано."*, and a `Помилка` row
whose **Звіт за** column still holds the property's day — the report was built from Google
before Telegram refused it, so a missing token costs a read and nothing else. Nothing is
sent, and no attempt is silently swallowed.

## 8. Put the site back

```bash
ddev wp eval 'delete_option( "gatb_settings" ); add_option( "gatb_settings", GaTelegramBridge\Settings::defaults(), "", false ); delete_option( "gatb_log" ); delete_option( "gatb_state" ); delete_transient( "gatb_google_access_token" );'
```

Expected afterwards:

```bash
ddev wp eval 'echo "runs: " . count( GaTelegramBridge\RunLog::entries() ) . ", state: " . json_encode( GaTelegramBridge\RunLog::state() ) . ", property: " . var_export( GaTelegramBridge\Settings::property_id(), true ) . "\n";'
```

→ `runs: 0, state: {"last_report_date":"","attempt":0}, property: ''` — the settings, the key,
the log and the state are gone. (`uninstall.php`, which does this when the plugin is deleted,
is Sprint 2.)

## 9. The gate is green

```bash
bash bin/check.sh
```

Expected: `==> check: all green`, with `OK (215 tests, 687 assertions)`.

Among them: a run that reaches Telegram writes one `sent` entry and remembers the day; a
refused send leaves that day unsent; a report that cannot be read never reaches Telegram at
all; the date guard stops a second `cron` run for the same day and a `manual` one sends
anyway; and the log holds thirty runs, not thirty-one.
