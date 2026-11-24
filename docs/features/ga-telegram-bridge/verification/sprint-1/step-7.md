# Verification — ga-telegram-bridge, Sprint 1, Step 7

**MessageRenderer and Preview** · written at close on 2026-09-09
Branch merged: `ga-telegram-bridge/sprint-1-report-on-demand` → `master`

What this step promises: the numbers Step 6 produced become the message of
`FEATURE.md` → UI — in the site's own language — and an administrator can read that
message on the settings screen before anything has ever been sent.

Nothing is sent here. No Telegram bot is needed, and none is configured: sending is
Step 8. Everything below happens on
**<https://dovira.ddev.site/wp-admin/options-general.php?page=gatb-settings>**
except the last two sections, which are run from the repo root.

## 0. Configure the property, and count the notices

Fill in the *Google Analytics* section the way any administrator would:

* **Ідентифікатор ресурсу** — `533779496`
* **Ключ сервісного акаунта** — the contents of
  `wp-content/plugins/ga-telegram-bridge/spike/service-acount.json` (the key from the
  Sprint 1 spike; the filename really is missing a "c")

Save, then press **Перевірити GA**.

Expected: **exactly one** green notice — *"Google відповів для ресурсу 533779496. Його
часовий пояс звітності — Europe/Kiev; саме за ним визначається день, який звіт називає
«вчора»."*

The count is the check. Until this step the same notice appeared **twice**: wp-admin
prints the notices of every screen under Settings by itself (`admin-header.php` →
`options-head.php` → `settings_errors()`), and the screen printed them a second time.
Two identical boxes means the screen has taken that call back.

Both values go into the database, which is where the plugin keeps them. `*.sql` is
gitignored, so a dump cannot carry them into the repository. §7 clears them again.

## 1. The message, as Telegram will receive it

Press **Попередній перегляд**.

Expected: below the three buttons, a heading **Попередній перегляд**, the line *"Повідомлення
в тому вигляді, у якому його отримає Telegram, разом із тегами. Нічого не надіслано."* and
the message itself — tags shown as text, one line per line:

```
📊 <b>dovira.ddev.site — 8 Вересня (Вівторок)</b>
<b>Відвідувачі</b>
Вчора: 69 (▲ 23% до середнього за 7 днів)
За 28 днів: 1 560 (▼ 1% до попередніх 28)
<b>Топ‑5 сторінок вчора</b>
1. Ветеринарна клініка у Харкові– Лікування собак та кішок — 73
…
<b>Джерела за 28 днів</b>
Organic Search 70% · Direct 16% · Referral 7% · …
<b>Міста за 28 днів</b>
Kharkiv 52% · Kyiv 30% · Dnipro 11% · Lviv 7%
<b>Пристрої за 28 днів</b>
mobile 80% · desktop 19% · tablet 1%
```

The numbers move every day; the shape does not. Compare it line for line with the
template in `docs/features/ga-telegram-bridge/FEATURE.md` → UI — that template is what
the renderer is written against.

**And there must be no notice at the top of the page.** The whole message printed as one
bold paragraph above the form, with its line breaks gone, is the failure this step was
reopened for: a message belongs in a block, not in a notice.

Two things worth noticing rather than glossing over. The date says **Вересня** and
**Вівторок**, capitalised: WordPress declines the month into the genitive by itself and
writes both words that way in its Ukrainian translation. And the cities and channels keep
the names Google gives them — `Kharkiv`, `Organic Search` — because they are data, not
our text.

## 2. Negative check — the thousands separator is a character, not an entity

In Ukrainian WordPress hands out `1&nbsp;560` for the number 1560: the separator is a
**named HTML entity**, not a space. Telegram's HTML mode understands only four named
entities — `&lt;`, `&gt;`, `&amp;` and `&quot;` — so an undecoded `&nbsp;` would either be
printed literally in the report or make the whole message unparseable.

Read the *За 28 днів* line: it must say `1 560`, and the text `&nbsp;` must appear nowhere
in the block. In the browser console, on the same page:

```js
document.querySelector( 'pre' ).textContent.includes( '&nbsp;' )   // false
document.querySelector( 'pre' ).textContent.includes( ' ' )   // true
```

## 3. Negative check — the preview sends nothing

Look at the *Telegram* section while the preview is on screen: **Токен бота** and
**Ідентифікатор чату** are empty. There is no bot, no token and no chat on this site, and
the message still appeared — so it cannot have been sent anywhere. That is the whole point
of the button: it runs the same two calls to Google and the same renderer the daily report
will, and then stops.

## 4. A switched-off block leaves no trace

In **Блоки звіту**, untick *Топ сторінок* and save. Press **Попередній перегляд** again.

Expected: **both** page blocks are gone — *Топ‑5 сторінок вчора* and *Топ‑5 сторінок за 28
днів* — and every other block is exactly where it was:

```
📊 <b>dovira.ddev.site — 8 Вересня (Вівторок)</b>
<b>Відвідувачі</b>
Вчора: 69 (▲ 23% до середнього за 7 днів)
За 28 днів: 1 560 (▼ 1% до попередніх 28)
<b>Джерела за 28 днів</b>
…
```

A block that is switched on but has nothing to report is left out in the same way — the
`Report` keeps the two states apart, the message has no place for a heading with nothing
under it. Tick *Топ сторінок* back on and save.

## 5. Negative check — a refused save still says so, once

Type `abc123` into **Ідентифікатор ресурсу** and save.

Expected: **exactly one** red notice — *"Ідентифікатор ресурсу GA4 має складатися лише з
цифр (наприклад, 123456789). Збережено попереднє значення."* — and the field holding
`533779496` again. The validation of Step 3 is unchanged; what changed is that its notice,
like every other, is now printed once.

## 6. The language is the site's, not the plugin's

```bash
ddev wp eval 'echo "\n"; $r = new GaTelegramBridge\Report( "2026-09-08", "Europe/Kiev", 69, 56.28, 1560, 1577, 23, -1, null, null, null, null, array( array( "label" => "mobile", "value" => 1248, "share" => 80 ) ) ); echo GaTelegramBridge\MessageRenderer::render( $r ) . "\n\n"; switch_to_locale( "en_US" ); echo GaTelegramBridge\MessageRenderer::render( $r ) . "\n\n"; echo GaTelegramBridge\MessageRenderer::render_failure( "2026-09-08" ) . "\n";'
```

Expected: the same report twice, and then the failure notice —

```
📊 <b>dovira.ddev.site — 8 Вересня (Вівторок)</b>
<b>Відвідувачі</b>
Вчора: 69 (▲ 23% до середнього за 7 днів)
За 28 днів: 1 560 (▼ 1% до попередніх 28)
…
📊 <b>dovira.ddev.site — 8 September (Tuesday)</b>
<b>Visitors</b>
Yesterday: 69 (▲ 23% to the 7-day average)
Last 28 days: 1,560 (▼ 1% to the previous 28)
…
⚠️ <b>dovira.ddev.site</b> — the report for 8 September (Tuesday) was not built. The details are in the plugin's run log.
```

English is the source language of the plugin; the Ukrainian above it comes from
`languages/ga-telegram-bridge-uk.mo`. Notice what follows the language: `1 560` becomes
`1,560` and `8 Вересня (Вівторок)` becomes `8 September (Tuesday)` — the report is written
the way the reader's language writes numbers and dates, not the way PHP does. The failure
notice is the second template of the same renderer; Sprint 2 is what sends it.

## 7. Put the site back

```bash
ddev wp eval 'delete_option( "gatb_settings" ); add_option( "gatb_settings", GaTelegramBridge\Settings::defaults(), "", false ); delete_transient( "gatb_google_access_token" );'
```

That clears the property id and the key from the database along with everything else, and
puts every report block back on.

## 8. The gate is green

```bash
bash bin/check.sh
```

Expected: `==> check: all green`, with `OK (187 tests, 585 assertions)`.

Among them, twelve assert whole messages built from the responses the live property really
gave — including a page title carrying `<b>` and `&`, which arrive at Telegram as `&lt;b&gt;`
and `&amp;`, and the Ukrainian separator arriving as a character.
