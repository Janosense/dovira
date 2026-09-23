# How to check: the search blocks in the morning report

**search-stats · Sprint 2, Step 3 — The three blocks in the message** · rewritten for manual testing on 2026-09-23
Merged into `master` · the last step of Sprint 2

## What changed, in plain words
The daily Telegram report the owner receives each morning now ends with up to
three new sections, placed just before the "Детальніше в Google Analytics"
link:

| Section | What it lists |
|---|---|
| 🔎 **Пошук по сайту** | what people typed into the search box in the site header |
| 🗂 **Пошук у переліку послуг** | what people typed into the filter on the services list page |
| 💊 **Пошук у послугах** | what people typed into the price-list filter on a service's own page |

Each section has two short lists:
- **Вчора:** yesterday;
- **За 28 днів:** the last four weeks.

Each list holds up to five rows, most frequent first. A header search whose
every attempt found nothing is marked **· нічого не знайдено**. A list with
nothing in it shows **—**, and a section nobody used at all is left out.

You test it the way a visitor and the owner would: make some searches on the
local site, then look at the report in wp-admin (and, if you want, in
Telegram).

Every expected result below was seen on the local site on 2026-09-23 while this
guide was written. Only the device percentages (`mobile 81%` …) change from
day to day.

## Before you start
You need:
- a terminal in the project folder `/Users/tymofii/Projects/php/dovira`, with
  the local site running (`ddev start`);
- the local wp-admin, logged in as an administrator:
  https://dovira.ddev.site/wp-admin;
- only for step 9: the Telegram chat the local site sends its report to.

> ⚠️ **The report's settings page shows secrets at its top.** On
> Settings → GA → Telegram, the first fields contain the Google key and the
> Telegram bot token. Never screenshot, copy or share that page as a whole.
> When a step sends you there, use the browser's search (⌘F) to jump straight
> to the part you need.

**Optional, for developers.** The automated checks still pass:
```bash
bash bin/check.sh; echo "exit=$?"
```
It ends with `==> check: all green` and `exit=0`: 309 plugin tests and 92
theme tests.

---

## Part A — Search like a visitor

### 1. Note where your test starts
The search log may already hold other people's searches. Write down the last
entry's number, so that later steps touch only your own searches:
```bash
ddev wp db query "SELECT COALESCE(MAX(id), 0) FROM wp_dovira_search_queries" --skip-column-names
```
You see one number, for example `56`. **Write it down.** Below, it is called
**N**; replace `N` with your number wherever it appears.

### 2. Search from the header search box
Open each address in the browser, in this order. Each time you open the page,
wait until it has finished loading (about 2 seconds): that moment counts as
one search.

| Open | How many times | What the page shows |
|---|---|---|
| https://dovira.ddev.site/?s=вакцинація | **2** (open it, then reload once) | a list of results |
| https://dovira.ddev.site/?s=жирафи | **2** | no results |
| https://dovira.ddev.site/?s=дуже довгий запит про вакцинацію котів і собак | 1 | no results |
| https://dovira.ddev.site/?s=%3Cb%3E | 1 | no results (this is the search text `<b>`) |

On the last page the heading "Результати пошуку для запиту:" looks empty. That
is a known, separate problem of the results page (`search.php` prints the
query unescaped) and not part of this step.

### 3. Search in the services list
1. Open https://dovira.ddev.site/services/.
2. Click the field **"Швидкий пошук по послугах:"** above the service cards.
3. Type `кастрація` and wait **3 seconds**. The cards filter as you type, as
   before.
4. Select everything in the field, type `&copy; <i>` exactly like that, and
   wait **3 seconds**.

### 4. Search in the services list of the Russian site
1. Open https://dovira.ddev.site/ru/uslugi/ (the page is titled «Услуги»).
2. Click the same search field, type `стерилизация`, and wait **3 seconds**.

### 5. Search inside one service
1. Open https://dovira.ddev.site/services/reception-department/ («Приймальне
   відділення»).
2. Click the search field of the price list, type `огляд`, and wait
   **3 seconds**.

### 6. Check that all 10 searches were counted
```bash
ddev wp db query "SELECT COUNT(*) FROM wp_dovira_search_queries WHERE id > N" --skip-column-names
```
Expected: `10`. If the number is lower, a filter search probably did not wait
long enough: a filter counts a search only after the typing pauses for
1.5 seconds, or when you click away.

### 7. Pretend the searches happened yesterday
The report only ever shows **yesterday** and the four weeks before today:
searches made today wait for tomorrow's report. So that you don't have to
wait, move your 10 searches back by one day:
```bash
ddev wp db query "UPDATE wp_dovira_search_queries SET created_at = created_at - INTERVAL 1 DAY WHERE id > N"
```
Expected: `Success: Query succeeded. Rows affected: 10`.

---

## Part B — What the report shows

### 8. Look at the report in *Preview*
This builds the report and shows it; nothing is sent.
1. Open https://dovira.ddev.site/wp-admin/options-general.php?page=gatb-settings.
2. Scroll down to the section **"З’єднання"** and click **Попередній перегляд**.
3. The page reloads and jumps back to the top, where the secrets are. Don't
   scroll up: press ⌘F and search for **`Повідомлення в тому вигляді`**.
4. Below that sentence is a grey box with the report as its raw text. Tags such
   as `<b>` are shown as text here; in Telegram they become bold.

**Expected.** The box ends like this (the device percentages vary):
```
📱 <b>Пристрої за 28 днів</b>
mobile 81%
desktop 19%
tablet 0%

🔎 <b>Пошук по сайту</b>
Вчора:
1. жирафи — 2 · нічого не знайдено
2. вакцинація — 2
3. <b> — 1 · нічого не знайдено
4. дуже довгий запит про вакцинацію котів і… — 1 · нічого не знайдено
За 28 днів:
1. жирафи — 2 · нічого не знайдено
2. вакцинація — 2
3. <b> — 1 · нічого не знайдено
4. дуже довгий запит про вакцинацію котів і… — 1 · нічого не знайдено

🗂 <b>Пошук у переліку послуг</b>
Вчора:
1. стерилизация — Послуги — 1
2. &copy; <i> — Послуги — 1
3. кастрація — Послуги — 1
За 28 днів:
1. стерилизация — Послуги — 1
2. &copy; <i> — Послуги — 1
3. кастрація — Послуги — 1

💊 <b>Пошук у послугах</b>
Вчора:
1. огляд — Приймальне відділення — 1
За 28 днів:
1. огляд — Приймальне відділення — 1

🔗 <a href="https://analytics.google.com/analytics/web/#/p533779496/reports/intelligenthome">Детальніше в Google Analytics</a>
```

**Check each of these:**
- [ ] The three new sections come **after** the devices section and **before**
      the Google Analytics link, one empty line apart.
- [ ] **Counting.** «вакцинація» and «жирафи» show **2**, because each page
      was opened twice.
- [ ] **Ties.** «жирафи» comes before «вакцинація». Both were searched
      twice, and the one searched more recently goes first.
- [ ] **Nothing found.** «жирафи» carries **· нічого не знайдено**, because
      its page found nothing. «вакцинація» does not, because its page found
      results.
- [ ] **Long queries are shortened.** The long query is cut after 40
      characters and ends with **…**.
- [ ] **The Russian page is named in Ukrainian.** «стерилизация», typed on
      the Russian «Услуги» page, is shown under the Ukrainian page name
      **Послуги**.
- [ ] **Each service is named.** «огляд» shows the service it was typed in:
      **Приймальне відділення**.
- [ ] **Odd text stays plain text.** `<b>` and `&copy; <i>` appear exactly
      as they were typed. They are not turned into bold, italics or a ©
      sign.

### 9. Optional: send it to Telegram
⚠️ This sends a real message to the chat the local site is configured with.

1. On the same page, click **Надіслати зараз**.
2. ⌘F for **Журнал запусків** to find the run log.

**Expected:**
- [ ] The newest row of **Журнал запусків** reads `Звіт за {yesterday's date}
      надіслано в чат {chat number}.`, and nothing more. No sentence about
      blocks being left out: the message is far below Telegram's size limit.
- [ ] In Telegram, the message arrives and ends with the three sections, then
      the Google Analytics link.
- [ ] The section headings are **bold**. The rows show `<b>` and
      `&copy; <i>` as plain text, just as typed.

---

## Part C — What must NOT happen

### 10. Today's searches do not appear before tomorrow
1. Open https://dovira.ddev.site/?s=сьогодні once and let it load.
2. Repeat step 8 (*Preview*).

**Expected:** the word «сьогодні» appears **nowhere** in the report. It
belongs to today, and today is reported tomorrow.

### 11. No searches, no sections
Remove every search you made, including the one from step 10:
```bash
ddev wp db query "DELETE FROM wp_dovira_search_queries WHERE id > N"
```
Expected: `Rows affected: 11`.

Repeat step 8 (*Preview*). **Expected:** the box ends with the devices section,
one empty line, then the Google Analytics link. There are **no** «Пошук»
headings and no empty sections: the report looks exactly as it did before this
feature.

If the site had real searches from other people in the last four weeks, their
sections remain. That is correct: only your own were removed.

### 12. Without the report plugin, the site adds nothing
This is a technical check. It starts WordPress once without the
"GA → Telegram" plugin, without deactivating it:
```bash
ddev wp eval --skip-plugins=ga-telegram-bridge 'echo false !== has_filter( "gatb_extra_blocks", [ "dovira\\SearchStats\\Renderer", "add_to" ] ) ? "hooked" : "not hooked", " renderer loaded=", class_exists( "dovira\\SearchStats\\Renderer", false ) ? "yes" : "no", "\n";'
```
**Expected:** `not hooked renderer loaded=no`. Without the plugin, the theme
neither hooks into the report nor loads the code that writes the sections.

---

## Part D — On the live sites, after the hand deploy
This part can only be checked after the developer's deploy:
- `master` → Kharkiv (dovira.vet).
- `master` merged into `kyiv` → Kyiv (kyiv.dovira.vet).
- Files only, no settings change.

On **each** site:
1. **The plugin version.** In wp-admin → Settings → GA → Telegram, ⌘F for
   **Версія плагіна**. It reads `0.3.0`. Do not look at the top of the page.
2. **Searches are recorded.** Make one search of each kind, as in steps 2–5,
   using that site's own pages. Afterwards, the developer checks that the log
   holds them, then deletes them (Sprint 1's check, deferred to this deploy).
3. **The next morning's report** ends with that site's own search sections,
   before the Google Analytics link. The Kyiv report never shows Kharkiv's
   searches, and the reverse holds too.
4. **A quiet day.** On a morning after a day nobody searched, the report looks
   exactly as it did before this feature.
