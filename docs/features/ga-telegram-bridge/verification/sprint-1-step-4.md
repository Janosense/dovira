# Verification — ga-telegram-bridge, Sprint 1, Step 4

**GoogleAuth, GaClient and "Check GA"** · written at close on 2026-09-09
Branch merged: `ga-telegram-bridge/sprint-1-report-on-demand` → `master`

What this step promises: the plugin can sign in to Google with the service-account key
and read the configured property, and the settings screen has a button that proves it.
Every way it can fail says what to fix, in one sentence, without ever showing the key,
the JWT or the access token.

Still nothing goes to Telegram, and no report is composed yet — those are Steps 5 to 8.

The screen is at **<https://dovira.ddev.site/wp-admin/options-general.php?page=gatb-settings>**.
Shell commands are run from the repo root.

## 0. Give the site the key, without putting it in the database

`wp-config.php` is gitignored here, and a key pasted into the settings form would end up
in the database and from there in a `mysql.sql` dump. So for this check, add the constant
instead — above `require_once ABSPATH . '/wp-settings.php';`:

```php
define( 'GATB_GA_SERVICE_ACCOUNT_JSON', file_get_contents( __DIR__ . '/wp-content/plugins/ga-telegram-bridge/spike/service-acount.json' ) );
```

(That path is the key from the Sprint 1 spike; the filename really is missing a "c".)
Then open the screen: the *Service-account key* box must be **empty, read-only** and say
*Set in configuration (wp-config.php)*. Enter `533779496` as the property id and save.

**Remove that line again when you are finished** — §7.

## 1. Check GA answers for the real property

Press **Check GA** in the *Connection* section, below the settings form.

Expected: the page comes back with a green notice —

> Google answered for property 533779496. Its reporting time zone is Europe/Kiev — that
> is the day the report calls "yesterday".

`Europe/Kiev` is the property's own reporting time zone, read from the response. It is
what decides which calendar day the daily report covers, so it is worth recognising here
rather than assuming the server's clock decides it.

## 2. Negative check — the cached token exists and is only a token

```bash
ddev wp eval 'echo strlen( (string) get_transient( "gatb_google_access_token" ) ) . " chars, starts \"" . substr( (string) get_transient( "gatb_google_access_token" ), 0, 4 ) . "\"\n";'
```

Expected: about **1024 chars, starts "ya29"** — a Google access token. Now the part that
matters:

```bash
ddev wp eval 'echo ( false !== strpos( (string) get_transient( "gatb_google_access_token" ), "PRIVATE KEY" ) ? "KEY MATERIAL IN THE TRANSIENT — STOP\n" : "no key material (good)\n" );'
```

Expected: `no key material (good)`. The cache holds the bearer token and nothing else.
Press **Check GA** again — it answers just as fast and makes no second sign-in, because
the token is reused until a minute before it expires.

## 3. Negative check — a property the account cannot read

Change the property id to `111111111` (a property nobody granted this account) and save,
then press **Check GA**.

Expected: a **red** notice naming the account you would have to grant access to —

> The service account cannot read this property. In Google Analytics, give
> dovira@dovira-471615.iam.gserviceaccount.com at least Viewer access to it — and check
> that the property id is the right one.

The address is an identifier, not a secret: it is exactly the string you paste into GA's
access management. What must **not** appear anywhere in that notice is the key.

Then try `abc` as the property id — it is refused by the settings form itself (Step 3's
validation, digits only), so a request never leaves the site.

## 4. Negative check — a broken key never produces a token

Point the constant at a damaged copy of the key and confirm that no token comes back:

```bash
ddev wp --exec="\$k = json_decode( file_get_contents( '/var/www/html/wp-content/plugins/ga-telegram-bridge/spike/service-acount.json' ), true ); \$k['private_key'] = str_replace( 'A', 'B', \$k['private_key'] ); define( 'GATB_GA_SERVICE_ACCOUNT_JSON', json_encode( \$k ) );" eval 'delete_transient( "gatb_google_access_token" ); $s = GaTelegramBridge\Settings::all(); $s["property_id"] = "533779496"; update_option( "gatb_settings", $s ); try { GaTelegramBridge\GaClient::check_connection(); echo "A BROKEN KEY PRODUCED A RESULT — STOP\n"; } catch ( Throwable $e ) { echo get_class( $e ) . ": " . $e->getMessage() . "\n"; } echo ( false === get_transient( "gatb_google_access_token" ) ? "no token cached (good)\n" : "A TOKEN WAS CACHED — STOP\n" );'
```

Expected: a `GoogleAuthException` saying Google refused the service-account key (either
*Invalid JWT Signature* from Google, or the local "private key could not be read" if the
damage broke the PEM), and `no token cached (good)`. A signature that does not verify
must never yield a token — that is the whole basis of the sign-in.

## 5. Negative check — an unconfigured site sends nothing

```bash
ddev wp eval 'delete_option( "gatb_settings" ); add_option( "gatb_settings", GaTelegramBridge\Settings::defaults(), "", false ); try { GaTelegramBridge\GaClient::check_connection(); echo "A REQUEST WAS MADE WITHOUT A PROPERTY — STOP\n"; } catch ( Throwable $e ) { echo $e->getMessage() . "\n"; }'
```

Expected: `No GA4 property id is configured yet.` — refused locally, with no request to
Google. Pressing **Check GA** on a fresh install shows the same sentence as a red notice.

## 6. Negative check — the button cannot be triggered from outside

Open <https://dovira.ddev.site/wp-admin/admin-post.php?action=gatb_check_ga> directly in
the browser (a GET, no nonce).

Expected: WordPress's own *"The link you followed has expired"* screen. The check runs
only from the form on the settings screen, and only for an administrator.

## 7. Put the site back

```bash
ddev wp eval 'delete_option( "gatb_settings" ); add_option( "gatb_settings", GaTelegramBridge\Settings::defaults(), "", false ); delete_transient( "gatb_google_access_token" );'
```

and delete the `define( 'GATB_GA_SERVICE_ACCOUNT_JSON', … )` line from `wp-config.php`.

## 8. The gate is green

```bash
bash bin/check.sh
```

Expected: `==> check: all green`, with `OK (101 tests, 264 assertions)`.

If PHPStan ever crashes here with *"reached configured PHP memory limit: 512M ... while
running parallel worker"*, that is not a memory problem and not a problem with the code:
re-run `vendor/bin/phpstan analyse --debug` inside the plugin (single-threaded) to see the
real result first. See LEARNINGS, 2026-09-09.
