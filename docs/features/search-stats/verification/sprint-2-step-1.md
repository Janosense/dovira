# Verification — search-stats, Sprint 2, Step 1

**Plugin 0.3.0: the `gatb_extra_blocks` filter and the length guard** · written at close on 2026-09-23
Branch merged: `search-stats/sprint-2-report` → `master`

What this step promises. All of it is in the plugin `ga-telegram-bridge`; no
theme file changed.
- **Other code can add blocks.** A callback on `gatb_extra_blocks` returns
  HTML strings. They are printed after the plugin's own blocks and before the
  closing Google Analytics link, one blank line before each, in the order
  given, exactly as written.
  - Entries that are empty, whitespace-only or not strings leave no trace.
  - A return that is not an array is ignored.
  - The failure notice is never offered the filter.
- **The length guard.** While the message's text as Telegram counts it is
  longer than 4 096, the last added block is dropped. The text is counted with
  tags removed, entities decoded, and in UTF-16 units, so an emoji counts
  twice. The plugin's own blocks are never dropped.
- **The run log says so.** A run that lost blocks says how many in its detail
  line, whether it was sent or refused. The sentence is plural-aware in
  Ukrainian.
- **Nothing hooked, nothing changes.** Without a callback the message is the
  one 0.2.0 sent.
- **Version 0.3.0** is in the header and in `Stable tag`. The readme gains a
  changelog and a FAQ entry, in ASCII only.

Run the shell commands from the repo root
(`/Users/tymofii/Projects/php/dovira`). Unless a section says otherwise, every
expected output below was observed on 2026-09-23 on the local install while
this guide was written.

> **Credentials on screen `Settings`.** The top of Settings → GA → Telegram
> prints the stored service-account JSON and the bot token back into their
> fields. Never screenshot or copy the whole page, and never run
> `wp option get gatb_settings`. After pressing a button the page comes back
> scrolled to the top. Jump straight to the preview with the browser's find
> (⌘F) for `Повідомлення в тому вигляді` instead of scrolling up to look
> (LEARNINGS 2026-09-10 and 2026-09-23).

## 1. The gate is green
```bash
bash bin/check.sh; echo "exit=$?"
```
Expected:
- `OK (309 tests, 1103 assertions)` for the plugin (297 before this step);
- `124 files checked`;
- `OK (63 tests, 168 assertions)` for the theme;
- `==> check: all green` and `exit=0`.

## 2. The guard's own tests, by name
```bash
(cd wp-content/plugins/ga-telegram-bridge && vendor/bin/phpunit --testdox --filter '/(MessageRendererTest|RunnerTest)::test_(.*extra_block|the_filter_is_given|empty_and_non_string|a_filter_that_returns_something|the_length_is|the_plugins_own|a_run_with_nothing|a_refused_run_still)/')
```
Expected: `OK (12 tests, 34 assertions)`. Nine are in Message Renderer and
three in Runner, all ticked. Among them:
- `An extra block that fits exactly is kept`: 3 338 characters on top of the
  recorded day fit exactly, and 3 339 do not.
- `The length is counted as telegram counts it`: 1 669 emoji fit and 1 670 do
  not.
- `The plugins own blocks are never dropped`: this is the **negative check**
  that no block of the plugin's own is ever removed, even from a message that
  is over the limit on its own.
- `The failure notice is given no extra blocks`: the second **negative
  check**.

## 3. Version 0.3.0, and an ASCII readme
```bash
ddev wp plugin list --name=ga-telegram-bridge --fields=name,status,version
grep -n '^Stable tag' wp-content/plugins/ga-telegram-bridge/readme.txt
python3 -c "b=open('wp-content/plugins/ga-telegram-bridge/readme.txt','rb').read(); print('ascii-only' if all(c<128 for c in b) else 'NON-ASCII')"
```
Expected:
- `ga-telegram-bridge	active	0.3.0`;
- `7:Stable tag: 0.3.0`;
- `ascii-only`.

Open `https://dovira.ddev.site/wp-content/plugins/ga-telegram-bridge/readme.txt`.
The FAQ has "Where do the extra blocks come from?" and the changelog opens
with `= 0.3.0 =` in three bullets: the filter, the guard, and the closing
link. It reads without mojibake.

In wp-admin, the line under "Журнал запусків" reads `Версія плагіна 0.3.0`.

## 4. A throwaway probe on the filter
The probe goes in `wp-content/mu-plugins/`, which is gitignored and does not
exist in the repo. It is deleted in §8.
```bash
mkdir -p wp-content/mu-plugins && cat > wp-content/mu-plugins/gatb-extra-blocks-probe.php <<'EOF'
<?php
// Throwaway probe for Sprint 2 Step 1 verification: never committed, deleted after the check.
add_filter(
	'gatb_extra_blocks',
	static function ( $blocks ) {
		$blocks[] = "🧪 <b>Probe one</b>\nfirst line &amp; more";
		$blocks[] = '🧪 <b>Probe two</b>';
		if ( file_exists( __DIR__ . '/gatb-probe-long.flag' ) ) {
			$blocks[] = str_repeat( 'x', 5000 );
		}
		return $blocks;
	}
);
EOF
git status --short
```
Expected: `git status --short` prints nothing.

## 5. Two short blocks: between the devices block and the link
First the live message, from the shell. This reads GA and sends nothing:
```bash
ddev wp eval '$m = GaTelegramBridge\MessageRenderer::compose( GaTelegramBridge\ReportBuilder::build() ); echo "dropped=", $m["dropped"], "\n", implode( "\n", array_slice( explode( "\n", $m["html"] ), -9 ) ), "\n";'
```
Expected (the device shares are that day's):
```
dropped=0
desktop 19%
tablet 0%

🧪 <b>Probe one</b>
first line &amp; more

🧪 <b>Probe two</b>

🔗 <a href="https://analytics.google.com/analytics/web/#/p533779496/reports/intelligenthome">Детальніше в Google Analytics</a>
```
The `&amp;` stays as the probe wrote it: the plugin does not re-escape an
added block.

Then on the screen:
1. Open
   `https://dovira.ddev.site/wp-admin/options-general.php?page=gatb-settings`.
2. Press **Попередній перегляд** under "З’єднання".
3. When the page returns, ⌘F for `Повідомлення в тому вигляді`.

In the `<pre>` under it:
- the `📱 <b>Пристрої за 28 днів</b>` block comes first;
- then, after one blank line each, the two probe blocks;
- then one blank line and the `🔗` link, which is the last line.

The probe's first block shows as `first line & more`. The preview is escaped
with `esc_html()`, which does not re-escape an entity that is already there.
That is how the screen has always displayed `&amp;`; what is sent keeps
`&amp;`, as the shell output above shows.

## 6. A block of 5 000 characters is left out
```bash
touch wp-content/mu-plugins/gatb-probe-long.flag
ddev wp eval '$m = GaTelegramBridge\MessageRenderer::compose( GaTelegramBridge\ReportBuilder::build() ); echo "dropped=", $m["dropped"], " has_x=", ( false !== strpos( $m["html"], "xxxxx" ) ? "yes" : "no" ), "\n";'
```
Expected: `dropped=1 has_x=no`.

On the screen, press **Попередній перегляд** again and ⌘F as in §5. The
preview is the same as in §5: the two probe blocks, then the link, with no
run of `x`. This is the **negative check**: a block that would push the
message past Telegram's limit is not there.

## 7. The run log names the dropped block (sends a message)
*Not observed while this guide was written.* **Надіслати зараз** posts
the report to the chat the local install is configured with, the same chat
as its earlier *Send now* rows.

Keep the flag from §6 and press **Надіслати зараз**. Expected:
- The notice and the newest row of "Журнал запусків" (trigger
  `Надіслати зараз`, result `Надіслано`) read:
  `Звіт за {yesterday} надіслано в чат {chat id}. 1 додатковий блок не ввійшов: з ним повідомлення було б довшим, ніж приймає Telegram.`
- In Telegram, the message ends with the devices block, the two probe blocks
  and the link, and contains no run of `x`.

The other two Ukrainian forms were checked from the shell:
```bash
ddev wp eval 'switch_to_locale( "uk" ); load_plugin_textdomain( "ga-telegram-bridge", false, "ga-telegram-bridge/languages" ); foreach ( array( 1, 2, 5 ) as $n ) { printf( _n( "%d extra block was left out: with it, the message would have been longer than Telegram accepts.", "%d extra blocks were left out: with them, the message would have been longer than Telegram accepts.", $n, "ga-telegram-bridge" ) . "\n", $n ); }'
```
Expected:
```
1 додатковий блок не ввійшов: з ним повідомлення було б довшим, ніж приймає Telegram.
2 додаткові блоки не ввійшли: з ними повідомлення було б довшим, ніж приймає Telegram.
5 додаткових блоків не ввійшло: з ними повідомлення було б довшим, ніж приймає Telegram.
```

## 8. Remove the probe: the message is the plugin's own again
```bash
rm wp-content/mu-plugins/gatb-extra-blocks-probe.php wp-content/mu-plugins/gatb-probe-long.flag && rmdir wp-content/mu-plugins
git status --short
ddev wp eval '$m = GaTelegramBridge\MessageRenderer::compose( GaTelegramBridge\ReportBuilder::build() ); echo "dropped=", $m["dropped"], "\n", implode( "\n", array_slice( explode( "\n", $m["html"] ), -3 ) ), "\n";'
```
Expected:
- `git status --short` prints nothing;
- `dropped=0`, and the last three lines are the last device row, a blank
  line and the `🔗` link, with no probe left.

## Not locally verifiable
Both productions on 0.3.0. This is checked at the sprint-boundary hand deploy:
`master` → Kharkiv and `kyiv` (after `master` is merged in) → Kyiv, files
only, no settings change.
- On each install the line under "Журнал запусків" reads
  `Версія плагіна 0.3.0`.
- The next morning's report arrives as before. Nothing hooks the filter on
  production until Step 3's theme code ships with it.
