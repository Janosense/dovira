# Verification — ga-telegram-bridge, Sprint 2, Step 2

**Retries and the failure notice** · written at close on 2026-09-09
Branch merged: `ga-telegram-bridge/sprint-2-runs-by-itself` → `master`

What this step promises: a day that could not be reported is not lost quietly. The scheduled
run tries again an hour later, as many times as **Максимум спроб** allows, and the last
attempt tells the chat that the day could not be reported before letting it go. A report sent
by hand is never retried, and a retry that wakes up too late reports the day it was booked
for instead of quietly sending another one.

The screen is
**<https://dovira.ddev.site/wp-admin/options-general.php?page=gatb-settings>**; shell commands
are run from the repo root.

**Two paths.** §1–§3 need a configured install (property id, service-account key, bot token,
chat id) — the dev site has been one since the Sprint 1 boundary, and then the notice really
arrives in the chat. On an install with nothing configured every run fails one step earlier,
at the missing property id, which proves the same chain except the arrival: the last row then
says the chat could not be told either, which is the other half of §3.

> **Never run `wp option get gatb_settings`.** That row holds the service-account key and the
> bot token; printing it puts both into your terminal history. Every command below reads the
> fields it needs by name and writes them one at a time.

## 0. Where things stand before anything is touched

```bash
ddev wp eval 'echo "property: " . GaTelegramBridge\Settings::property_id() . ", attempts: " . GaTelegramBridge\Settings::max_attempts() . ", chat set: " . ( "" !== GaTelegramBridge\Settings::telegram_chat_id() ? "yes" : "no" ) . ", state: " . json_encode( GaTelegramBridge\RunLog::state() ) . "\n";'
ddev wp cron event list --fields=hook,next_run_relative,recurrence,args | grep gatb
```

Write the property id down — §7 puts it back. Expected: one `gatb_daily_report` line, `1 day`,
with empty `args`, and no `gatb_retry_report` (nothing has failed).

## 1. Give the day two attempts, and take Google away

```bash
ddev wp option patch update gatb_settings max_attempts 2
ddev wp option patch update gatb_settings property_id 999999999
```

Reload the screen. Under **Розклад**, **Максимум спроб** now reads 2 and says what the number
means:

> Скільки спроб дається на один день, перш ніж у чат надійде повідомлення, що звіт не
> сформовано. Кожна наступна спроба — за годину після попередньої. Від 1 до 10.

Two, not three, is deliberate: it makes the whole chain reachable in two commands. The
property id is now one this site's service account cannot read, which is the failure a real
morning would meet if the property were mistyped or access were withdrawn.

## 2. The first attempt fails and books the next one

```bash
ddev wp cron event run gatb_daily_report
ddev wp cron event list --fields=hook,next_run_relative,args | grep gatb
```

Expected: two events now — the daily one, and

```
gatb_retry_report   59 minutes 59 seconds   ["2026-09-08"]
```

The date in the arguments is the day the report was about, and it travels with the event on
purpose: in an hour "yesterday" may be another day, and the attempt is about the day it was
booked for.

```bash
ddev wp eval '$e = GaTelegramBridge\RunLog::entries(); echo $e[0]["trigger"] . "  " . $e[0]["date"] . "  " . $e[0]["status"] . "  #" . $e[0]["attempt"] . "  " . $e[0]["message"] . "\n"; echo "state: " . json_encode( GaTelegramBridge\RunLog::state() ) . "\n";'
```

Expected: one new row, `cron  2026-09-08  failed  #1`, with the sentence naming the service
account and the property. On the screen it is the top row of **Журнал запусків**: **Розклад**,
**Помилка**, **Спроба 1**. `state` shows `"attempt":1`, and `last_report_date` is whatever it
was — a failure never marks a day as delivered.

Nothing has been sent yet. That is the point of the first attempt: the owner is not told about
a hiccup that the next attempt may fix.

## 3. The last attempt tells the chat and lets the day go

```bash
ddev wp cron event run gatb_retry_report
ddev wp cron event list --fields=hook,next_run_relative,args | grep gatb
```

Expected: **only** `gatb_daily_report` is left. No third attempt is booked, because the day
was given two and has used both.

**Open Telegram.** The configured chat has received:

> ⚠️ **dovira.ddev.site** — звіт за 8 Вересня (Понеділок) не сформовано. Деталі в журналі
> плагіна.

```bash
ddev wp eval '$e = GaTelegramBridge\RunLog::entries(); echo $e[0]["trigger"] . "  " . $e[0]["date"] . "  " . $e[0]["status"] . "  #" . $e[0]["attempt"] . "  " . $e[0]["message"] . "\n"; echo "state: " . json_encode( GaTelegramBridge\RunLog::state() ) . "\n";'
```

Expected: `retry  2026-09-08  failed  #2` — the same day as the first attempt, not today —
and a message that ends

> … Спроб не залишилось, тому в чат надіслано повідомлення, що звіт за цей день не сформовано.

`state` shows **`"attempt":0`**: the counter is back where a fresh day starts, so tomorrow gets
its own two attempts. `last_report_date` is still untouched — the day stays unsent, it is only
no longer being tried.

On the screen the row reads **Повтор · Помилка · Спроба 2**, with the whole sentence in
**Деталі**.

If this install has no bot token or the chat id is wrong, that sentence reads instead
*Спроб не залишилось, і повідомити чат теж не вдалося: …* with Telegram's own reason after it.
That is the same branch seen from the other side: a notice that cannot be delivered is written
into the run's own row and never thrown away — and it is still one row per run, not two.

## 4. Negative check — *Надіслати зараз* is never retried

Press **Надіслати зараз** on the screen (the property id is still broken, so it fails), then:

```bash
ddev wp cron event list --fields=hook,args | grep gatb
ddev wp eval '$e = GaTelegramBridge\RunLog::entries(); echo $e[0]["trigger"] . "  " . $e[0]["status"] . "  #" . $e[0]["attempt"] . "\n"; echo "state: " . json_encode( GaTelegramBridge\RunLog::state() ) . "\n";'
```

Expected: still **no** `gatb_retry_report`, and no second notice in the chat. The new row is
`manual  failed  #1` — the attempt is counted, because the day really did fail again, but
nothing is booked and nobody is messaged. A person is at the screen reading the reason and can
press the button again; a retry booked behind their back would wake up an hour later for a day
they may have already sent by hand.

## 5. Negative check — switching the plugin off takes the retry with it

Book one and leave it pending:

```bash
ddev wp eval 'GaTelegramBridge\Scheduler::schedule_retry( "2026-09-08" );'
ddev wp cron event list --fields=hook,args | grep gatb_retry
ddev wp plugin deactivate ga-telegram-bridge
ddev wp cron event list --fields=hook | grep gatb
```

Expected: the retry appears with its date, and after the deactivation the `grep` finds
**nothing** — neither hook. This is the check the step was written around: an event that
carries arguments is not removed by the function that matches events *without* them, so a
pending retry would otherwise have survived being switched off and fired into an unloaded
plugin.

```bash
ddev wp plugin activate ga-telegram-bridge
```

## 6. What this guide does not make you wait an hour for

Two things, both asserted in `tests/Unit/` against a fixed clock:

- **A retry that wakes up after midnight.** The day it was booked for can no longer be read —
  every range this plugin asks Google for is relative, so the property decides what "yesterday"
  means — and the run then reports *that* day as failed, sends the notice for it and books
  nothing, instead of sending another day's numbers under its date. To see it by hand on a
  configured install (**it posts a real message to the chat**, about a date from years ago):
  `ddev wp eval 'GaTelegramBridge\Scheduler::run_retry( "2020-01-01" );'` — the new row reads
  `retry  2020-01-01  failed` and says the property now calls another day yesterday.
- **Telegram's flood limit.** A 429 that names a wait of at most 30 seconds is waited out once
  and the message posted again; a longer one, a second refusal, or a 429 that names no wait is
  the failure it looks like and is left to the hourly retry. One bot sending one message a day
  cannot provoke a real 429, so the fixture is written from Telegram's documentation.

## 7. Put the site back

```bash
ddev wp option patch update gatb_settings property_id THE_ID_FROM_STEP_0
ddev wp option patch update gatb_settings max_attempts 3
ddev wp eval 'GaTelegramBridge\RunLog::reset_attempt();'
ddev wp eval 'echo "property: " . GaTelegramBridge\Settings::property_id() . ", attempts: " . GaTelegramBridge\Settings::max_attempts() . ", next run: " . wp_date( "Y-m-d H:i", (int) GaTelegramBridge\Scheduler::next_run() ) . ", state: " . json_encode( GaTelegramBridge\RunLog::state() ) . "\n";'
ddev wp cron event list --fields=hook,next_run_relative,args | grep gatb
```

Expected: the property id and 3 attempts are back, `"attempt":0`, one `gatb_daily_report` and
no retry, and the next run at the configured send time — writing the settings re-registers the
event, which also undoes the shift that forcing a run in §2 caused (`LEARNINGS.md`, "Running a
cron event by hand moved the daily slot"). The counter is reset by hand because §4 left the
day counted as failed once, and tomorrow's real run should start with all its attempts.

The failed rows stay in **Журнал запусків**. They are real runs and the log is not rewritten;
they drop off by themselves after thirty more.

## 8. The gate is green

```bash
bash bin/check.sh
```

Expected: `==> check: all green`, with `OK (255 tests, 815 assertions)`.
