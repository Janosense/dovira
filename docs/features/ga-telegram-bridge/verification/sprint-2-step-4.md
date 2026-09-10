# Verification — ga-telegram-bridge, Sprint 2, Step 4

**Production readiness on both Dovira installs** · written at close on 2026-09-10
Branch merged: `ga-telegram-bridge/sprint-2-runs-by-itself` → `master`

What this step promises: the settings screen says which build it is running and
puts the instructions one click away, and there is a written procedure —
`docs/features/ga-telegram-bridge/PRODUCTION-CHECKLIST.md` — for setting an
install up and checking it after a deploy. The screen half is checked here. The
procedure's own sections B–E belong to the two production installs and are the
sprint boundary's, not this session's (§6).

The screen is
**<https://dovira.ddev.site/wp-admin/options-general.php?page=gatb-settings>**;
shell commands are run from the repo root.

> **The dev install changed during this step**, on purpose: the service-account
> key and the bot token are now `define()`d in DDEV's `wp-config.php` as well as
> stored in `gatb_settings`. That was the rehearsal of the checklist's riskiest
> line (§4). Nothing was printed to do it and nothing was deleted, so the option
> still holds both as a fallback.

## 1. The screen names the build and points at the readme

Open the screen and read it top to bottom. It must show:

- Under **Google Analytics**, after the section text, the link
  *Як створити сервісний акаунт і ключ до нього: readme.txt, розділ Installation,
  крок 1.*
- Under **Telegram**, *Як створити бота і дізнатися ідентифікатор чату: …, крок 2.*
- Under **Розклад**, after *Наступний запуск*, *Що має запускати WP-Cron і який
  рядок додати в crontab: …, крок 5.*
- Under **Журнал запусків**, above the table, two lines: **Версія плагіна 0.1.0**
  and *Сьогодні вранці нічого не надійшло? readme.txt, розділ Frequently Asked
  Questions.*

Click one of the links: it opens `readme.txt` in a tab of its own, so a
half-filled form is never lost.

The version is **one line above the table, not a column in it**, whatever the
number of runs. That is the negative check of this section: the run log records
what a run did, and what a run was made by is not something it stores. To see it
against a full table without waiting for thirty runs:

```bash
ddev wp --user=1 eval '
GaTelegramBridge\Plugin::load_textdomain();
ob_start(); GaTelegramBridge\Admin::render_log_section( GaTelegramBridge\RunLog::entries() ); $log = ob_get_clean();
echo "version lines: ", substr_count( $log, "Версія плагіна" ), ", table rows: ", substr_count( $log, "<tr>" ) - 1, "\n";'
```

Expected: `version lines: 1, table rows: 5`.

## 2. What the links open is readable

```bash
curl -sk -o /tmp/gatb-readme.txt -w "status %{http_code}, %{content_type}, %{size_download} bytes\n" \
  https://dovira.ddev.site/wp-content/plugins/ga-telegram-bridge/readme.txt
LC_ALL=C grep -c '[^ -~\t]' /tmp/gatb-readme.txt; rm -f /tmp/gatb-readme.txt
```

Expected: `status 200, text/plain, 10086 bytes` and a count of **0**.

The zero is the point. This server sends `text/plain` with no charset, so the
browser guesses the encoding — and it guessed a Cyrillic codepage, which turned
the file's arrows, em dashes and ellipses into mojibake when the links were first
clicked. `readme.txt` is ASCII now (`->`, `--`, `...`), which every guess renders
alike; the plugin's `CLAUDE.md` says so, so the next typographic dash does not
quietly come back. Open the file in the browser too — the title must read
`=== Google Analytics -> Telegram bridge ===` and nothing on the page may look
like `в†'`.

## 3. Negative check — no secret reaches the screen

Both secrets are configured two ways over now, which makes this the moment to
prove the screen still shows neither:

```bash
ddev wp --user=1 eval '
GaTelegramBridge\Plugin::load_textdomain();
GaTelegramBridge\Settings::register();
GaTelegramBridge\Admin::add_fields();
ob_start(); GaTelegramBridge\Admin::render_page(); $page = ob_get_clean();
echo "page: ", strlen( $page ), " bytes\n";
echo "the key anywhere in it: ", ( false === strpos( $page, GaTelegramBridge\Settings::service_account_json() ) ? "no" : "YES - STOP" ), "\n";
echo "the token anywhere in it: ", ( false === strpos( $page, GaTelegramBridge\Settings::telegram_bot_token() ) ? "no" : "YES - STOP" ), "\n";
echo "constant names shown instead: ", substr_count( $page, "GATB_GA_SERVICE_ACCOUNT_JSON" ) + substr_count( $page, "GATB_TELEGRAM_BOT_TOKEN" ), "\n";
echo "version line: ", substr_count( $page, "Версія плагіна" ), ", readme links: ", substr_count( $page, "readme.txt\" target=\"_blank\"" ), "\n";'
```

Expected: `the key anywhere in it: no`, `the token anywhere in it: no`,
`constant names shown instead: 2`, `version line: 1, readme links: 4`. The
command compares the page against the real values and prints only whether it
found them — it never prints either (`docs/LEARNINGS.md`, "A whole options row
was printed to read two of its fields").

On the screen itself the two secret fields are empty, greyed out and followed by
**Задано в конфігурації (wp-config.php); поле ігнорується** with the constant's
name beside it.

## 4. The constants, on a real install

This is the checklist's step B2 rehearsed, and the reason the step could be
closed without a production install:

```bash
ddev wp eval '
echo "constants defined: json ", ( defined( "GATB_GA_SERVICE_ACCOUNT_JSON" ) ? "yes" : "no" ), ", token ", ( defined( "GATB_TELEGRAM_BOT_TOKEN" ) ? "yes" : "no" ), "\n";
echo "fields locked on the screen: key ", ( GaTelegramBridge\Settings::is_secret_locked( "service_account_json" ) ? "yes" : "no" ), ", token ", ( GaTelegramBridge\Settings::is_secret_locked( "telegram_bot_token" ) ? "yes" : "no" ), "\n";
$stored = get_option( "gatb_settings" );
echo "constant matches the stored copy: key ", ( hash( "sha256", (string) GaTelegramBridge\Settings::service_account_json() ) === hash( "sha256", (string) $stored["service_account_json"] ) ? "same value" : "DIFFERENT" ), ", token ", ( hash( "sha256", (string) GaTelegramBridge\Settings::telegram_bot_token() ) === hash( "sha256", (string) $stored["telegram_bot_token"] ) ? "same value" : "DIFFERENT" ), "\n";'
```

Expected: `yes` four times and `same value` twice — the two hashes are compared
so that nothing has to be printed to know they agree.

Then press **Перевірити GA** and **Перевірити Telegram** on the screen. They must
still pass with the secrets coming from `wp-config.php`:

> Google відповів для ресурсу 533779496. Його часовий пояс звітності —
> Europe/Kiev; саме за ним визначається день, який звіт називає «вчора».

> Telegram прийняв тестове повідомлення для чату … Відкрийте цей чат, щоб його
> побачити.

Open the chat and see the test message arrive. That pair of answers is the whole
of the checklist's section C: reaching Google proves outbound HTTPS to
`oauth2.googleapis.com` and `analyticsdata.googleapis.com` **and** that OpenSSL
signed the request, and reaching Telegram proves `api.telegram.org`.

**To undo it** (the site keeps working either way, because the option still holds
both values): delete the two `define(…)` lines from `wp-config.php` above the
"stop editing" line, reload the screen, and the fields are editable again.

## 5. The gate holds the two version numbers together

The number on the screen comes from the plugin header, and `readme.txt` states it
a second time as `Stable tag`. A release that changes one and forgets the other
fails here rather than on somebody's site:

```bash
cd wp-content/plugins/ga-telegram-bridge
sed -i '' 's/^Stable tag: 0.1.0$/Stable tag: 0.1.1/' readme.txt
vendor/bin/phpunit --no-coverage --filter agree_on_the_version
git checkout readme.txt
vendor/bin/phpunit --no-coverage --filter version
```

Expected: the first run **fails** —

```
the plugin header and readme.txt must name the same version
Failed asserting that two strings are identical.
-'0.1.0'
+'0.1.1'
```

— and the second, after the file is put back, is `OK (3 tests, 7 assertions)`.
Check `git status` afterwards: `readme.txt` must be unchanged.

## 6. What only the deploy can check: the checklist itself

`docs/features/ga-telegram-bridge/PRODUCTION-CHECKLIST.md` is this step's other
deliverable. Sections **A** (what to have ready) can be worked through at any
time; **B**–**E** need the installs and are the sprint boundary's:

1. Deploy `master` to Kharkiv and `kyiv` to Kyiv by hand, as the git model says.
2. Work through B–D on each install in turn: the two constants in that install's
   `wp-config.php`, activate, save, *Перевірити GA*, *Перевірити Telegram* into
   **that city's** chat, *Попередній перегляд*, *Надіслати зараз*.
3. Then E: on two consecutive mornings the newest run log row on each install
   reads **Розклад · Надіслано · Спроба 1** and the owner confirms the messages.

That third item is the sprint's Definition of Done, and it is the one thing
neither this guide nor any test can stand in for: two real mornings on two real
hosts. Anything that fails there is a `/fix-step` on this step.

## 7. The gate is green

```bash
bash bin/check.sh
```

Expected: `==> check: all green`, with `OK (265 tests, 843 assertions)`. Five of
those are new: three assert what the screen shows (the version line, that it is
printed once for any number of rows, and that all four sections carry the readme
link), and two hold the version itself — one that it is read from the header
rather than written into the markup, one that the header and the readme agree.
