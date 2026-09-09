# Verification — ga-telegram-bridge, Sprint 1, Step 3

**Settings and the admin page** · written at close on 2026-09-09
Branch merged: `ga-telegram-bridge/sprint-1-report-on-demand` → `master`

What this step promises: the plugin has a settings screen. Everything the report will
need can be entered there and survives a reload; a value that is wrong is refused on its
own without losing what was there before; the two secrets can come from `wp-config.php`
instead, and then the screen neither accepts nor shows them.

Nothing calls Google or Telegram yet — the *Check GA*, *Check Telegram*, *Preview* and
*Send now* buttons arrive in Steps 4, 5, 7 and 8. The screen is only credentials and
switches for now.

Run the shell commands from the repo root. The screen is at
**<https://dovira.ddev.site/wp-admin/options-general.php?page=gatb-settings>** —
in wp-admin it is **Settings → GA → Telegram**.

## 1. The screen is there

```bash
ddev wp plugin activate ga-telegram-bridge   # already active if Step 1 was verified
```

Open the URL above. Expected: a page titled *Google Analytics → Telegram* with four
sections and a *Save Changes* button.

| Section | Fields |
|---|---|
| Google Analytics | Property id, Service-account key (a large text box) |
| Telegram | Bot token, Chat id |
| Schedule | Send time (a time picker), Maximum attempts (a number, 1–10) |
| Report blocks | Visitors, Top pages, Traffic sources, Cities, Devices |

The *Visitors* checkbox must be **ticked and greyed out**, labelled "(always sent)".
The Schedule section must say that nothing runs on a schedule yet — that is Sprint 2.

## 2. What you type is kept

Fill in, and press *Save Changes*:

- Property id: `533779496`
- Chat id: `-1001234567890`
- Send time: `07:15`
- Maximum attempts: `4`
- Untick *Traffic sources* and *Cities*

Expected: "Settings saved." at the top. **Reload the page** (F5): every value is still
there, and the two boxes you unticked are still unticked.

Confirm from the database that nothing was mangled on the way:

```bash
ddev wp option get gatb_settings --format=json
```

Expected: `"property_id":"533779496"`, `"send_time":"07:15"`, `"max_attempts":4` (a
number, not a string) and `"channels":false,"cities":false` inside `blocks`.

## 3. Negative check — a wrong value is refused and loses nothing

With the values from §2 saved, now type nonsense into three fields at once:

- Property id: `properties/533779496` (the resource name, not the id)
- Chat id: `@dovira` (a channel username, not an id)
- Service-account key: `paste it here`

Press *Save Changes*. Expected: **three red notices** above the form, one per field,
each naming the field and the expected format, for example

> The GA4 property id must consist of digits only (for example 123456789). The previous
> value was kept.

and the fields show **`533779496`, `-1001234567890` and the empty key box again** — the
previous values, not what you typed.

Now change *Send time* to `08:45` **in the same submission as a bad chat id** (type
`12a`). Expected: one notice about the chat id, and *Send time* nevertheless saved as
`08:45`. A single bad field must never block the rest of the form.

## 4. Negative check — the visitors block cannot be switched off

Untick every block you can and save. Expected: *Visitors* stays ticked (its checkbox is
disabled in the browser). Then try to switch it off behind the screen's back:

```bash
ddev wp eval 'GaTelegramBridge\Settings::register(); update_option( "gatb_settings", array( "blocks" => array( "visitors" => "0", "pages" => "0" ) ) ); echo wp_json_encode( GaTelegramBridge\Settings::blocks() ) . "\n";'
```

Expected: `{"visitors":true,"pages":false,"channels":false,"cities":false,"devices":false}`
— the block that carries the visitor numbers is on whatever is written to the option.

## 5. Negative check — a secret set in the configuration is never shown

`wp-config.php` is **gitignored** in this repo, so this check cannot end up in a commit.
Open it and add these two lines above `require_once ABSPATH . '/wp-settings.php';`:

```php
define( 'GATB_GA_SERVICE_ACCOUNT_JSON', '{"client_email":"from@config.iam.gserviceaccount.com","private_key":"-----BEGIN PRIVATE KEY-----","token_uri":"https://oauth2.googleapis.com/token"}' );
define( 'GATB_TELEGRAM_BOT_TOKEN', '111:FROM-A-CONSTANT' );
```

Reload the screen. Expected for *Service-account key* and *Bot token*:

- the field is **empty and read-only** (typing into it does nothing);
- under it, in bold: *Set in configuration (wp-config.php); the field is ignored.*
  followed by the constant's name in code style;
- **view the page source** (⌥⌘U) and search for `FROM-A-CONSTANT` and `PRIVATE KEY`:
  neither may appear anywhere on the page. This is the check that matters most — the
  value lives in the configuration and must never be rendered back into HTML.

The plugin still reads it, from the constant:

```bash
ddev wp eval 'echo GaTelegramBridge\Settings::telegram_bot_token() . "\n";'
```

Expected: `111:FROM-A-CONSTANT`.

Now paste something into the read-only field via the browser console (or simply save the
form) — the stored option must not change:

```bash
ddev wp option get gatb_settings --format=json | grep -o '"telegram_bot_token":"[^"]*"'
```

Expected: `"telegram_bot_token":""` — a submission for a field that comes from a constant
is ignored.

**Remove the two lines from `wp-config.php` when you are done**, reload, and check that
both fields are editable again and the notes are gone.

## 6. Only administrators reach the screen

Open the screen URL in a private window (logged out). Expected: the WordPress login page,
not the settings form.

And directly, for a logged-in subscriber:

```bash
ddev wp eval 'require_once ABSPATH . "wp-admin/includes/template.php"; $id = username_exists( "gatb-check-subscriber" ); if ( ! $id ) { $id = wp_insert_user( array( "user_login" => "gatb-check-subscriber", "user_pass" => wp_generate_password(), "role" => "subscriber" ) ); } wp_set_current_user( $id ); ob_start(); GaTelegramBridge\Admin::render_page(); $out = ob_get_clean(); echo "subscriber sees " . strlen( $out ) . " bytes\n"; require_once ABSPATH . "wp-admin/includes/user.php"; wp_delete_user( $id );'
```

Expected: `subscriber sees 0 bytes`. (The command removes the temporary user again.)

## 7. The option does not slow every page down

```bash
ddev wp db query "SELECT option_name, autoload FROM wp_options WHERE option_name='gatb_settings'"
```

Expected: `autoload` is `off`. The settings are read only in wp-admin and in the future
cron run, so they must never be loaded on a visitor's page view.

If the row is missing entirely, the plugin was activated before this step; deactivate and
activate it again — the option is created by the activation hook.

## 8. The gate is green

```bash
bash bin/check.sh
```

Expected: `==> check: all green`, with `OK (62 tests, 151 assertions)` from PHPUnit.
PHPCS prints `5 / 5 (100%)` — that is its parallel progress counting batches, not files;
all ten plugin files are checked (`vendor/bin/phpcs -q --report=json` lists them).

## Put the site back

```bash
ddev wp option delete gatb_settings
ddev wp eval 'add_option( "gatb_settings", GaTelegramBridge\Settings::defaults(), "", false );'
```

Leaves the plugin active with the defaults, ready for Step 4's *Check GA*.
