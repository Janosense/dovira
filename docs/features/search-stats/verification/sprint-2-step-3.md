# Verification — search-stats, Sprint 2, Step 3

**The three blocks in the message** · written at close on 2026-09-23
Branch merged: `search-stats/sprint-2-report` → `master` · the last step of Sprint 2

What this step promises:
- **The morning message ends with up to three search blocks.** They sit
  after the plugin's own blocks and before the "Детальніше в Google Analytics"
  link, exactly as `docs/features/search-stats/FEATURE.md` → UI shows them:
  - «Пошук по сайту», with "· нічого не знайдено" on queries that never found
    anything;
  - «Пошук у переліку послуг»;
  - «Пошук у послугах».
- **Each block has two lists.** Each has a `Вчора:` and a `За 28 днів:` list
  of up to five rows. An empty list prints `—`, and a level nobody searched
  adds no block.
- **Queries are cut and escaped.**
  - A query over 40 characters keeps 40 and gets `…`.
  - Page and service names are the Ukrainian post's, or `(видалено)`.
  - Every value is escaped so that no search text, however odd, can make
    Telegram refuse the message.
- **No searches, no change.** A day with no searches sends the plugin's
  message exactly as before.
- **Every run carries the same blocks.** *Preview*, *Send now*, the schedule
  and a retry all carry the same blocks.
- **Without the plugin, nothing is hooked or loaded.**

Run the commands from the repo root (`/Users/tymofii/Projects/php/dovira`).
Unless a section says otherwise, every expected output was observed on
2026-09-23 on the local install while this guide was written. The seeded rows
were then deleted.

> **Credentials on screen `Settings`.** The top of Settings → GA → Telegram
> prints the stored service-account JSON and the bot token back into their
> fields. Never screenshot or copy the whole page. After pressing a button,
> jump to the preview with the browser's find (⌘F) for
> `Повідомлення в тому вигляді` (LEARNINGS 2026-09-10 and 2026-09-23).

## 1. The gate is green
```bash
bash bin/check.sh; echo "exit=$?"
```
Expected:
- `OK (309 tests, 1103 assertions)` for the plugin;
- `134 files checked`;
- `OK (92 tests, 229 assertions)` for the theme (83 before this step);
- `==> check: all green` and `exit=0`.

## 2. The step's own tests
```bash
(cd wp-content/themes/dovira/tests && vendor/bin/phpunit --testdox --filter '/(RendererTest|BootstrapFilterTest)::/')
```
Expected: `OK (9 tests, 17 assertions)`. Among them:
- *Queries and titles are escaped for telegram*: a query `&nbsp; &copy;`
  prints `&amp;nbsp; &amp;copy;`, where `esc_html()` would have left them.
- *The list is untouched when there is nothing to add*.

## 3. The theme hooks the report only when the plugin is there
```bash
ddev wp plugin list --name=ga-telegram-bridge --fields=name,status,version
ddev wp eval 'echo false !== has_filter( "gatb_extra_blocks", [ "dovira\\SearchStats\\Renderer", "add_to" ] ) ? "hooked" : "not hooked", "\n";'
ddev wp eval --skip-plugins=ga-telegram-bridge 'echo false !== has_filter( "gatb_extra_blocks", [ "dovira\\SearchStats\\Renderer", "add_to" ] ) ? "hooked" : "not hooked", " renderer loaded=", class_exists( "dovira\\SearchStats\\Renderer", false ) ? "yes" : "no", "\n";'
```
Expected:
- `ga-telegram-bridge	active	0.3.0`;
- `hooked`;
- `not hooked renderer loaded=no`. This is the **negative check**: WordPress
  loaded without the plugin, and the theme hooks nothing and loads no
  renderer. The plugin's activation is not touched.

## 4. Seed searches
```bash
ddev wp db query "SELECT COUNT(*) FROM wp_dovira_search_queries" --skip-column-names
```
Expected: `0`. Other rows would share the top five.

The seed is written for the local install's zone, `+03:00` (Step 2's guide
§3). `@t` is today's local midnight in UTC, computed by the database, and
every text starts with `zz-verify`.
```bash
ddev wp db query "SET @t = CONVERT_TZ( DATE( CONVERT_TZ( UTC_TIMESTAMP(), '+00:00', '+03:00' ) ), '+03:00', '+00:00' );
INSERT INTO wp_dovira_search_queries (level, query_text, context_id, results, created_at) VALUES
('site', 'zz-verify вакцинація', 0, 5, @t - INTERVAL 20 HOUR),
('site', 'zz-verify вакцинація', 0, 5, @t - INTERVAL 19 HOUR),
('site', 'zz-verify вакцинація', 0, 5, @t - INTERVAL 18 HOUR),
('site', 'zz-verify рентген', 0, 3, @t - INTERVAL 17 HOUR),
('site', 'zz-verify рентген', 0, 0, @t - INTERVAL 16 HOUR),
('site', 'zz-verify груминг', 0, 0, @t - INTERVAL 15 HOUR),
('site', 'zz-verify груминг', 0, 0, @t - INTERVAL 14 HOUR),
('services', 'zz-verify кастрація', 1630, NULL, @t - INTERVAL 13 HOUR),
('service', 'zz-verify узі', 18, NULL, @t - INTERVAL 12 HOUR),
('service', 'zz-verify зникла послуга', 999999, NULL, @t - INTERVAL 11 HOUR),
('site', 'zz-verify &nbsp; <b> &copy;', 0, 2, @t - INTERVAL 10 HOUR),
('site', 'zz-verify дуже довгий запит про вакцинацію котів і собак', 0, 1, @t - INTERVAL 9 HOUR);"
```
Expected: `Rows affected: 12`.

The less obvious rows:
- **`кастрація`** was typed on the **Russian** «Услуги» page (1630). It must
  be named by the Ukrainian post, `Послуги`.
- **`зникла послуга`** belongs to post 999999, which does not exist.
- **The `&nbsp; <b> &copy;` row** is a search text that `esc_html()` would
  have let through to Telegram.
- **The long query** is 55 characters.

## 5. The message, from the shell
This reads GA and sends nothing:
```bash
ddev wp eval '$m = GaTelegramBridge\MessageRenderer::compose( GaTelegramBridge\ReportBuilder::build() ); echo "dropped=", $m["dropped"], "\n", implode( "\n", array_slice( explode( "\n", $m["html"] ), -31 ) ), "\n";'
```
Expected (the device shares are that day's):
```
dropped=0
tablet 0%

🔎 <b>Пошук по сайту</b>
Вчора:
1. zz-verify вакцинація — 3
2. zz-verify груминг — 2 · нічого не знайдено
3. zz-verify рентген — 2
4. zz-verify дуже довгий запит про вакцинац… — 1
5. zz-verify &amp;nbsp; &lt;b&gt; &amp;copy; — 1
За 28 днів:
1. zz-verify вакцинація — 3
2. zz-verify груминг — 2 · нічого не знайдено
3. zz-verify рентген — 2
4. zz-verify дуже довгий запит про вакцинац… — 1
5. zz-verify &amp;nbsp; &lt;b&gt; &amp;copy; — 1

🗂 <b>Пошук у переліку послуг</b>
Вчора:
1. zz-verify кастрація — Послуги — 1
За 28 днів:
1. zz-verify кастрація — Послуги — 1

💊 <b>Пошук у послугах</b>
Вчора:
1. zz-verify зникла послуга — (видалено) — 1
2. zz-verify узі — Приймальне відділення — 1
За 28 днів:
1. zz-verify зникла послуга — (видалено) — 1
2. zz-verify узі — Приймальне відділення — 1

🔗 <a href="https://analytics.google.com/analytics/web/#/p533779496/reports/intelligenthome">Детальніше в Google Analytics</a>
```
Read it against `FEATURE.md` → UI:
- one blank line between the blocks;
- the long query cut at exactly 40 characters (`zz-verify дуже довгий
  запит про вакцинац`) before `…`;
- equal counts ordered by the newer search (`довгий` before `&nbsp;`,
  `зникла` before `узі`).

**Negative checks:**
- `рентген` carries no marker.
- No raw `&nbsp;` or `<b>` from a query reaches the message.

## 6. The same on screen, in *Preview*
1. Open
   `https://dovira.ddev.site/wp-admin/options-general.php?page=gatb-settings`.
2. Press **Попередній перегляд**.
3. When the page returns, ⌘F for `Повідомлення в тому вигляді`.

The `<pre>` under it ends with the same three blocks as §5, between
`📱 <b>Пристрої за 28 днів</b>` and the `🔗` line.

The screen shows that row as `5. zz-verify &nbsp; <b> &copy; — 1`, the plain
text Telegram will show. The preview prints the message through
`esc_html()`, which does not re-escape the message's own `&amp;…` and
`&lt;…`, so they display decoded; the Step 1 guide saw the same with `&amp;`.
The message itself is §5.

*Observed:* on 2026-09-23 the preview was checked by reading the `<pre>`
element only, on a seed without the long and the deleted-post rows:
- the blocks were in the order devices → site → services list → service →
  link;
- there were two markers;
- both titles were printed.

## 7. *Send now* (sends a message)
*Not observed while this guide was written.* It posts to the chat the local
install is configured with.

With the §4 rows still in place, press **Надіслати зараз**. Expected:
- In Telegram, the message ends with the three blocks under bold headings,
  then the link. The query row reads `zz-verify &nbsp; <b> &copy;` as plain
  text, and the message was not refused.
- The notice and the newest row of "Журнал запусків" read
  `Звіт за {yesterday} надіслано в чат {chat id}.`, with **no** sentence
  about left-out blocks: the message is far below 4 096 characters.

## 8. No searches: the plugin's message alone
```bash
ddev wp db query "DELETE FROM wp_dovira_search_queries WHERE query_text LIKE 'zz-verify%'"
ddev wp db query "SELECT COUNT(*) FROM wp_dovira_search_queries" --skip-column-names
ddev wp eval '$m = GaTelegramBridge\MessageRenderer::compose( GaTelegramBridge\ReportBuilder::build() ); echo "dropped=", $m["dropped"], " search blocks=", substr_count( $m["html"], "Пошук" ), "\n", implode( "\n", array_slice( explode( "\n", $m["html"] ), -3 ) ), "\n";'
```
Expected:
- `Rows affected: 12`, then `0`;
- `dropped=0 search blocks=0`, then the last device row, a blank line and
  the `🔗` link.

This is the **negative check**: an empty table adds nothing, not even an
empty heading. *Preview* now ends with the devices block and the link.

## Not locally verifiable: the sprint-boundary hand deploy
Sprint 2 reaches the owner only with the developer's hand deploy:
- `master` goes to Kharkiv as files.
- `master` is merged into `kyiv`, and `kyiv` goes to Kyiv.
- Both carry the theme and plugin 0.3.0, with no settings change.

On **each** install:
1. The line under "Журнал запусків" reads `Версія плагіна 0.3.0`.
2. **The Sprint 1 checks.** Both were deferred to this deploy.
   - The first request creates `{prefix}dovira_search_queries`. The database
     user needs `CREATE`.
   - One real search of each kind leaves one row. Delete those test rows
     afterwards.
3. **The next morning's report** ends with that install's own search blocks,
   before the GA link. Kyiv's queries never appear in Kharkiv's message, and
   the reverse holds too.
4. **A morning with no searches** on an install sends the plugin's message
   alone.
