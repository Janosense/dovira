# Verification — ga-telegram-bridge, Sprint 3, Step 3

**Re-anchor the daily event after every scheduled run** · written at close on 2026-09-16
Branch merged: `ga-telegram-bridge/sprint-3-trend` → `master`

What this step promises: the report keeps its time of day. WordPress reschedules a recurring
event by adding 86 400 seconds, which is not a day on the two nights a year the clock moves —
so until now the report drifted by an hour every autumn and spring and stayed there until
somebody saved the settings. Each scheduled run now registers the next one from the
configured local time instead. It also ships the plugin as **0.2.0**.

The screen is
**<https://dovira.ddev.site/wp-admin/options-general.php?page=gatb-settings>**; shell
commands are run from the repo root.

> **Do not take a whole-page screenshot of this screen** (LEARNINGS 2026-09-10) — it prints
> the stored key and bot token back into their fields.

## 1. Where things stand

```bash
ddev wp cron event list --fields=hook,next_run_gmt,recurrence | grep -E 'hook|gatb'
```

Expected: **exactly one** `gatb_daily_report`, recurrence `1 day`, due at the configured send
time. Note that `next_run_gmt` is GMT — 06:00 GMT is 09:00 in Kyiv. Two rows for that hook
would be a defect; one is the invariant.

The screen says the same thing under **Розклад → Наступний запуск**, in site-local time, and
above the run log it now reads **Версія плагіна 0.2.0**.

## 2. The re-anchor, without sending anything

This is the whole step in one command, and it delivers no message — provided the day has
already gone out, which the date guard then stops the run on. Check first:

```bash
ddev wp eval 'echo "last report sent: " . GaTelegramBridge\RunLog::last_report_date() . "\n";'
```

If that prints **yesterday's** date, the run below will stop at the guard and send nothing.
(If it does not, either skip to §3 or expect a real message.)

```bash
ddev wp eval '
$drifted = ( new DateTimeImmutable( "tomorrow 08:00", wp_timezone() ) )->getTimestamp();
wp_unschedule_hook( "gatb_daily_report" );
wp_schedule_event( $drifted, "daily", "gatb_daily_report" );
echo "1. drift simulated, next run: " . wp_date( "Y-m-d H:i", (int) wp_next_scheduled( "gatb_daily_report" ) ) . "\n";
$before = count( GaTelegramBridge\RunLog::entries() );
GaTelegramBridge\Scheduler::run_daily();
echo "2. log rows " . $before . " -> " . count( GaTelegramBridge\RunLog::entries() ) . "\n";
echo "3. next run now:      " . wp_date( "Y-m-d H:i", (int) wp_next_scheduled( "gatb_daily_report" ) ) . "\n";
$events = 0;
foreach ( (array) _get_cron_array() as $slot ) { if ( isset( $slot["gatb_daily_report"] ) ) { $events += count( $slot["gatb_daily_report"] ); } }
echo "4. events registered: " . $events . "\n";'
```

Expected, and what it printed at the close:

```
1. drift simulated, next run: 2026-09-17 08:00
2. log rows 7 -> 7
3. next run now:      2026-09-17 09:00
4. events registered: 1
```

Line 3 is the step: an event sitting an hour early — exactly what a clock change leaves
behind — put back on the configured time by one run. Line 2 says nothing was sent or logged,
line 4 that the run replaced the event rather than adding a second one.

## 3. A real scheduled run keeps the time too

Set **Час надсилання** two minutes ahead of the site's clock
(`ddev wp eval 'echo wp_date("H:i") . "\n";'`) and save. Then wait for it, or push it:

```bash
ddev wp cron event run gatb_daily_report
ddev wp cron event list --fields=hook,next_run_gmt,recurrence | grep -E 'hook|gatb'
```

Expected: the report **arrives in the chat**, and the next `gatb_daily_report` is due at the
**same time tomorrow** — not 24 hours after the moment you ran it. That last part is what
Sprint 2 could not do: running an event by hand used to move the slot for good
(LEARNINGS 2026-09-09, now resolved).

Put **Час надсилання** back to 09:00 and save.

## 4. Negative check — pressing a button does not move the schedule

Note the next run, press **Надіслати зараз**, and look again.

```bash
ddev wp cron event list --fields=hook,next_run_gmt | grep gatb_daily_report
```

Expected: **unchanged**. *Send now* is a run, but it is not the schedule; only the scheduled
run re-anchors. A retry is the same — it is a single event with its own day and never touches
the recurring one.

## 5. Negative check — the retry is not swept away

The re-anchor clears `gatb_daily_report` and nothing else. If a failed run has booked one, a
`gatb_retry_report` row must survive the re-anchor that follows it in the same run:

```bash
ddev wp cron event list --fields=hook,next_run_gmt | grep gatb
```

Expected after a failed scheduled run: **both** hooks listed. `SchedulerTest` holds this too,
because clearing the wrong one would cancel the day's last chance.

## 6. What this step does not prove

- **The clock change itself.** The arithmetic is proven by a fixed-clock test over the real
  nights — 2026-10-25 is 25 hours long, 2027-03-28 is 23 — and the re-anchor is proven above,
  but only the morning of **26 October 2026** on a production install proves it where it
  matters. That is the sprint's own Definition of Done, confirmed after the deploy.
- **Both productions.** They run Sprint 2's code until the sprint-boundary deploy.
