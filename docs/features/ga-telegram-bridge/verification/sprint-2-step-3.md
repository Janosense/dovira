# Verification — ga-telegram-bridge, Sprint 2, Step 3

**Lifecycle, readme, translations** · written at close on 2026-09-10
Branch merged: `ga-telegram-bridge/sprint-2-runs-by-itself` → `master`

What this step promises: switching the plugin off costs nothing, deleting it
leaves nothing, and someone who has never seen this plugin can set it up on
another site from `readme.txt` alone.

The screen is
**<https://dovira.ddev.site/wp-admin/options-general.php?page=gatb-settings>**;
shell commands are run from the repo root.

> **§3 deletes the plugin's data.** On this install that data includes the
> service-account key and the bot token, which exist nowhere else. §2 copies all
> three rows into one temporary row first and §4 puts them back — do not skip
> either. Nothing is ever printed: `wp option get gatb_settings` would put the key
> and the token into your terminal history (`LEARNINGS.md`).

## 1. Negative check — the file cannot be fired by a stranger

`uninstall.php` sits in a publicly readable directory, so the first question is
what happens when someone simply asks for it:

```bash
curl -sk -o /dev/null -w "status %{http_code}, %{size_download} bytes\n" \
  https://dovira.ddev.site/wp-content/plugins/ga-telegram-bridge/uninstall.php
ddev wp eval 'foreach ( array( "gatb_settings", "gatb_state", "gatb_log" ) as $k ) { echo $k, ": ", ( false === get_option( $k, false ) ? "GONE" : "present" ), "\n"; }'
```

Expected: `status 200, 0 bytes` — an empty page, no error, no output — and all
three rows still **present**. The file does nothing at all unless WordPress
itself defines `WP_UNINSTALL_PLUGIN` before including it, which only the deletion
of the plugin does. This is the one check no unit test can make: an `exit` inside
the test suite would end the run.

## 2. Negative check — switching the plugin off is not the same as deleting it

Back the three rows up first, all in one row, without printing any of them:

```bash
ddev wp eval '
update_option( "gatb_step3_backup", array(
  "settings" => get_option( "gatb_settings" ),
  "state"    => get_option( "gatb_state" ),
  "log"      => get_option( "gatb_log" ),
), false );
$b = get_option( "gatb_step3_backup" );
echo "backed up: settings ", count( (array) $b["settings"] ), " keys, state ", count( (array) $b["state"] ), " keys, log ", count( (array) $b["log"] ), " rows\n";'
```

Then switch the plugin off and look:

```bash
ddev wp plugin deactivate ga-telegram-bridge
ddev wp eval '
foreach ( array( "gatb_settings", "gatb_state", "gatb_log" ) as $k ) { echo $k, ": ", ( false === get_option( $k, false ) ? "GONE" : "present" ), "\n"; }
$c = _get_cron_array(); $n = 0; foreach ( $c as $hooks ) { foreach ( array_keys( $hooks ) as $h ) { if ( 0 === strpos( $h, "gatb_" ) ) { ++$n; } } }
echo "gatb cron events: ", $n, "\n";'
```

Expected: all three rows **present**, `gatb cron events: 0`. Deactivation takes
the schedule and nothing else — an administrator who switches the plugin off for
an afternoon does not lose the credentials they typed in, the log, or the record
of which days have been sent.

## 3. Deleting the plugin leaves nothing behind

```bash
ddev wp plugin uninstall ga-telegram-bridge --deactivate --skip-delete
```

`--skip-delete` runs exactly what wp-admin's *Delete* runs — core includes
`uninstall.php` the same way — but keeps the files, which this repository
versions. Then:

```bash
ddev wp eval '
foreach ( array( "gatb_settings", "gatb_state", "gatb_log", "gatb_step3_backup" ) as $k ) { echo $k, ": ", ( false === get_option( $k, false ) ? "GONE" : "present" ), "\n"; }
echo "transient: ", ( false === get_transient( "gatb_google_access_token" ) ? "GONE" : "present" ), "\n";
$c = _get_cron_array(); $n = 0; foreach ( $c as $hooks ) { foreach ( array_keys( $hooks ) as $h ) { if ( 0 === strpos( $h, "gatb_" ) ) { ++$n; } } }
echo "gatb cron events: ", $n, "\n";
global $wpdb; echo "gatb rows in wp_options: ", (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE \"gatb!_%\" ESCAPE \"!\"" ), "\n";'
```

Expected: the three options **GONE**, the transient **GONE**,
`gatb cron events: 0`, and `gatb rows in wp_options: 1` — that one row being
`gatb_step3_backup`, which §2 made and the plugin does not own. That is the
negative check of this section, and a better one than an empty count: it shows
the file deleting exactly its own keys and walking past a row that merely looks
like one of them.

## 4. Put the site back

```bash
ddev wp plugin activate ga-telegram-bridge
ddev wp eval '
$b = get_option( "gatb_step3_backup" );
update_option( "gatb_settings", $b["settings"], false );
update_option( "gatb_state", $b["state"], false );
update_option( "gatb_log", $b["log"], false );
delete_option( "gatb_step3_backup" );
echo "property: ", GaTelegramBridge\Settings::property_id(), ", attempts: ", GaTelegramBridge\Settings::max_attempts(), ", send time: ", GaTelegramBridge\Settings::send_time(), "\n";
echo "state: ", wp_json_encode( GaTelegramBridge\RunLog::state() ), "\n";
echo "log rows: ", count( GaTelegramBridge\RunLog::entries() ), "\n";
echo "backup row: ", ( false === get_option( "gatb_step3_backup", false ) ? "GONE" : "STILL THERE" ), "\n";
echo "next run: ", wp_date( "Y-m-d H:i", (int) GaTelegramBridge\Scheduler::next_run() ), "\n";'
```

Expected: the property id, the attempts and the send time are the ones §2 backed
up, the state and the log are back with the same number of rows, the backup row
is **GONE**, and the next run is at the configured time — writing the settings
registers the event again. Open the screen and check the run log looks as it did.

## 5. What only a stranger can check: the readme

Everything above proves the lifecycle. What it cannot prove is the part of this
step that matters most on another site — that
`wp-content/plugins/ga-telegram-bridge/readme.txt` is enough to set the plugin up
from nothing. That check is:

1. On an install with no `gatb_` rows (the state §3 leaves, before §4), open the
   readme and follow **Installation** from step 1, without looking at any other
   file and without reusing what you already know: a Google Cloud project, the
   Data API enabled, a service account with a JSON key, Viewer on the property,
   the numeric property id; a bot from BotFather, a chat, the chat id.
2. Fill the settings screen, press *Check GA*, then *Check Telegram*, then
   *Preview*, then *Send now*.
3. It passes if *Send now* writes a **Надіслано** row and the message arrives —
   and it fails if any step of the readme left you guessing. A readme is only
   proven by someone who does not already know the answers, which is why this
   guide asks you to run it rather than reporting it as done.

Anything missing is a `/fix-step` on this step, not a note for later: the readme
is this step's deliverable.

## 6. The translations did not move

```bash
grep -c '^msgid' wp-content/plugins/ga-telegram-bridge/languages/ga-telegram-bridge.pot
msgfmt --statistics -o /dev/null wp-content/plugins/ga-telegram-bridge/languages/ga-telegram-bridge-uk.po
```

Expected: `122`, and `114 translated messages, 7 untranslated messages`. This
step added no translatable string — `uninstall.php` has no user-facing text and
`readme.txt` is not scanned — so the `.pot` was regenerated only to prove that,
and discarded when nothing but its creation date had changed. The seven empty
entries are the plugin name, the author, the author URI and the labels *GA →
Telegram*, *Google Analytics*, *Telegram* and *Google Analytics → Telegram*:
brand names whose Ukrainian is the same text, where gettext falls back to the
source, so the screen is already fully Ukrainian.

## 7. The gate is green

```bash
bash bin/check.sh
```

Expected: `==> check: all green`, with `OK (260 tests, 827 assertions)`. Five of
those tests are `UninstallTest`, which runs `uninstall.php` the way core runs it
and holds every name the file spells out against the class constant it must
equal — so renaming an option without touching `uninstall.php` fails here rather
than on somebody's site.
