# Verification — ga-telegram-bridge, Sprint 2, Step 1

**Scheduler** · written at close on 2026-09-09
Branch merged: `ga-telegram-bridge/sprint-2-runs-by-itself` → `master`

What this step promises: the report no longer needs anybody to press a button. The plugin
works out when the next run falls from the time in the settings, registers it with WordPress,
says on the screen when it is due, and warns when the site has switched WP-Cron off and put
nothing in its place.

**No bot and no Google key are needed for this guide.** Nothing is configured on the dev
site, so the scheduled run ends in a `Помилка` row instead of a delivered report — which
still proves everything this step built: that it fired, that it fired *as the schedule*, and
that a failure is written down. A run that ends in `Надіслано` needs the property id, the
service-account key, the bot token and the chat id typed in first (§7).

The screen is
**<https://dovira.ddev.site/wp-admin/options-general.php?page=gatb-settings>**; shell
commands are run from the repo root.

## 0. Where things stand before anything is touched

```bash
ddev wp eval 'echo "send time: " . GaTelegramBridge\Settings::send_time() . ", scheduled: " . var_export( wp_next_scheduled( "gatb_daily_report" ), true ) . ", runs: " . count( GaTelegramBridge\RunLog::entries() ) . "\n";'
```

Expected: `send time: 09:00, scheduled: false, runs: 0` — a site that has never saved these
settings has no schedule. Open the screen: under **Розклад** it says

> Звіт ще не заплановано. Збережіть ці налаштування один раз — і він буде запланований.

That sentence is the whole of the first check: an install that was activated but never saved
is told what to do about it, instead of being shown an empty line.

## 1. Save the settings, and the schedule appears

In **Час надсилання** set a time a few minutes ahead of the site's clock (the site's own time
is `ddev wp eval 'echo wp_date("H:i") . "\n";'`), then press **Зберегти зміни**.

Expected: **exactly one** notice — *Налаштування збережено.* — and under **Розклад**, in
place of the sentence above:

> Наступний запуск: {the date and time you set}

printed in the site's time zone, the same way the run log prints its times. And:

```bash
ddev wp cron event list --fields=hook,next_run_relative,recurrence | grep gatb
```

Expected: one line — `gatb_daily_report`, a few minutes away, `1 day`. **One**, not two: the
save clears the previous event before registering the new one, so ten saves leave one event.

Save a second time with a different time and run the command again to see that.

> This is the check the step was rewritten for. Core hands a settings write to
> `add_option()` while the stored row still equals its registered default — which is exactly
> the state of a fresh install — and the plugin listens for both that and the ordinary
> update. If this step ever shows `scheduled: false` after a save, that is the bug come back.

## 2. The run really is the report

```bash
ddev wp cron event run gatb_daily_report
```

Expected: `Executed the cron event 'gatb_daily_report' in …s.` Then the log:

```bash
ddev wp eval 'foreach ( GaTelegramBridge\RunLog::entries() as $e ) { echo wp_date( "Y-m-d H:i", $e["time"] ) . "  " . $e["trigger"] . "  " . $e["date"] . "  " . $e["status"] . "  #" . $e["attempt"] . "  " . $e["message"] . "\n"; } echo "state: " . json_encode( GaTelegramBridge\RunLog::state() ) . "\n";'
```

Expected: one row that reads `cron  2026-09-08  failed  #1  Ідентифікатор ресурсу GA4 ще не
вказано.` — the run happened, it knows it was the schedule that asked (the screen writes that
column as **Розклад**), and it says why it could not build the report. Reload the screen to
see the same row in **Журнал запусків**.

`state:` must show **`"last_report_date":""`**. That is the negative check of this section: a
failed run never marks a day as sent. `last_cron_hit` is now set — §4 uses it.

## 3. Forcing a run moves the next one

Look at **Розклад** again: the next run is now 24 hours after the moment you forced it, not
the time you saved. That is WordPress, not the plugin — a recurring event that is run before
it was due is rescheduled from the moment it ran. Press **Зберегти зміни** once to put the
time back, and check the line again.

## 4. Negative check — the warning, and when it must not appear

Nothing above warned about anything, because WP-Cron is doing its job on this site. Now take
it away. In `wp-config.php` (it is not in the repository), above the "That's all" line, add:

```php
define( 'DISABLE_WP_CRON', true );
```

and forget the last visit to `wp-cron.php`:

```bash
ddev wp eval 'GaTelegramBridge\RunLog::mark_cron_hit( 0 );'
```

Reload the screen. Expected: under **Розклад**, one yellow box:

> WP-Cron вимкнено у wp-config.php (DISABLE_WP_CRON), і за останні 24 години ніхто не
> викликав https://dovira.ddev.site/wp-cron.php — тож звіт не надішле ніщо. Налаштуйте
> системний cron, який звертається до цієї адреси, наприклад кожні 15 хвилин.

with this site's own address in it. The next run is still printed above it: the event exists,
it is just that nothing will run it.

Now the two states in which the warning must **not** appear:

```bash
ddev wp eval 'GaTelegramBridge\RunLog::mark_cron_hit();'
```

Reload: the box is gone — something is calling `wp-cron.php`, which is all the plugin asked
for. Then remove the `define(…)` line from `wp-config.php` and reload once more: still gone,
and it must stay gone however long ago the last visit was, because with WP-Cron switched on a
long silence only means nothing was due.

## 5. Negative check — switching the plugin off leaves no timer behind

```bash
ddev wp plugin deactivate ga-telegram-bridge
ddev wp cron event list --fields=hook | grep gatb
```

Expected: the `grep` finds **nothing** — neither `gatb_daily_report` nor `gatb_retry_report`
(the second is the retry event Step 2 will schedule; it is cleared here already). A
deactivated plugin whose event stayed behind would fire into an unloaded plugin every day.

```bash
ddev wp plugin activate ga-telegram-bridge
ddev wp eval 'echo "scheduled again: " . wp_date( "Y-m-d H:i", (int) wp_next_scheduled( "gatb_daily_report" ) ) . ", settings kept: " . GaTelegramBridge\Settings::send_time() . ", runs kept: " . count( GaTelegramBridge\RunLog::entries() ) . "\n";'
```

Expected: the event is back at the stored send time, and the settings and the log survived
being switched off — deactivation takes away the schedule and nothing else. (Deleting the
plugin is what removes its data; that is `uninstall.php`, Step 3.)

## 6. Put the site back

```bash
ddev wp eval '
delete_option( "gatb_settings" );
add_option( "gatb_settings", GaTelegramBridge\Settings::defaults(), "", false );
delete_option( "gatb_log" );
delete_option( "gatb_state" );
delete_transient( "gatb_google_access_token" );
GaTelegramBridge\Scheduler::clear();
'
ddev wp eval 'echo "runs: " . count( GaTelegramBridge\RunLog::entries() ) . ", state: " . json_encode( GaTelegramBridge\RunLog::state() ) . ", send time: " . GaTelegramBridge\Settings::send_time() . ", scheduled: " . var_export( wp_next_scheduled( "gatb_daily_report" ), true ) . "\n";'
```

Expected: `runs: 0, state: {"last_report_date":"","attempt":0,"last_cron_hit":0}, send time:
09:00, scheduled: false`. The order matters: writing the settings back registers the event
again — that is §1 working — so `Scheduler::clear()` comes last.

## 7. What this guide cannot show

A run that ends in **Надіслано**. It needs a configured install: the property id and the
service-account key of a GA4 property, a bot token and a chat id. With those in place the
same forced run in §2 delivers the report to the chat and writes a `Надіслано` row, and a
second run for the same day writes nothing at all — the date guard, which no state on this
site can demonstrate because no day has ever been delivered here. The report going out **by
itself**, at the hour, on the production installs is Step 4 and the sprint boundary.

Two things the tests carry instead, since neither can be waited for here: what the next run
is on the two mornings a year the clock jumps (both transitions are asserted against a fixed
clock in `SchedulerTest`, in `Europe/Kiev`, including a send time that does not exist on the
day the hour is skipped), and that the thirty-first run drops the oldest.

## 8. The gate is green

```bash
bash bin/check.sh
```

Expected: `==> check: all green`, with `OK (238 tests, 748 assertions)`.
