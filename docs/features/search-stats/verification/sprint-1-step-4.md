# Verification — search-stats, Sprint 1, Step 4

**Recording from the browser on all three levels** · written at close on 2026-09-23
Branch merged: `search-stats/sprint-1-recording` → `master`

What this step promises:
- **Header search.** The results page sends its query once, with how many
  results it lists.
- **The two filters.** The `services` block filter and a service's price-list
  filter send a value once it has rested for 1500 ms or the field loses focus.
  The value needs at least 3 characters, and an input never sends the value it
  sent last.
- **Context.** Each filter's row carries the id of the page or service it was
  typed in.
- **Only real visitors are counted.** A crawler, `curl` or any fetch that
  runs no JavaScript leaves no row. An empty search sends nothing.
- **The filtering itself is unchanged.**

Run the shell commands from the repo root (`/Users/tymofii/Projects/php/dovira`)
and the browser steps in Chrome with DevTools open. Every expected output below
was observed on 2026-09-23 on the local install while this guide was written.

The local ids used are:
- page `12`, `Послуги` (`/services/`), which carries the `services` block;
- its Russian translation `1630` (`/ru/uslugi/`);
- the service `18`, `Приймальне відділення` (`/services/reception-department/`).

## 1. The gate is green
```bash
bash bin/check.sh; echo "exit=$?"
```
Expected:
- `124 files checked`;
- `OK (63 tests, 168 assertions)` for the theme;
- `==> check: all green` and `exit=0`.

## 2. The committed bundle is the source, built
```bash
(cd wp-content/themes/dovira && npm run build > /dev/null && echo built)
git status --short -- wp-content/themes/dovira/assets
```
Expected: `built`, and then **nothing** from `git status`. A fresh build
reproduces `assets/` byte for byte, so the committed files are exactly what
`source/` produces.

`npm run build` also sets `WP_ENVIRONMENT_TYPE=production` in the theme
`.env`. The local site then serves `assets/`, which is what the browser steps
below need.

## 3. Note where the table starts
```bash
ddev wp db query "SELECT COALESCE(MAX(id),0) AS max_id, COUNT(*) AS n FROM wp_dovira_search_queries"
```
Write down `max_id`, which step 10 uses. Locally it was `0` and `0`.

## 4. The pages print what the browser module reads
```bash
curl -skL https://dovira.ddev.site/services/ | tr -s ' \t\n' ' ' | grep -o '<input[^>]*search-input[^>]*>'
curl -skL https://dovira.ddev.site/ru/uslugi/ | tr -s ' \t\n' ' ' | grep -o '<input[^>]*search-input[^>]*>'
curl -skL https://dovira.ddev.site/services/reception-department/ | tr -s ' \t\n' ' ' | grep -o '<input[^>]*search-input[^>]*>'
curl -skL 'https://dovira.ddev.site/?s=%d0%b2%d0%b0%d0%ba%d1%86%d0%b8%d0%bd%d0%b0%d1%86%d1%96%d1%8f' | tr -s ' \t\n' ' ' | grep -o '<div class="section section--mb-standard" data-search-stats[^>]*>'
curl -skL 'https://dovira.ddev.site/?s=%22%3E%3Cb%3Ex' | tr -s ' \t\n' ' ' | grep -o 'data-search-stats-query="[^"]*"'
```
Expected, in order:
- `… id="services-search-input" … data-search-stats-context="12">`;
- the same input with `data-search-stats-context="1630"`. The Russian page
  carries its own id, and Sprint 2 maps it to the Ukrainian source when it
  renders;
- `… id="service-search-input" … data-search-stats-context="18">`;
- `data-search-stats-query="вакцинація" data-search-stats-results="7"`.
  Locally, `7` is what the page lists: 1 price row, 2 services, 1 news item
  and 3 pages;
- `data-search-stats-query="&quot;&gt;&lt;b&gt;x"`. The query is escaped in
  the attribute.

Use lowercase percent-encoding in the search URLs, as above. The theme
answers an uppercase `%D0…` URL with a 301 to its lowercase form.

## 5. A fetch that runs no JavaScript records nothing
**Negative check.** Step 4 fetched two results pages and three filter pages
with `curl`. Then:
```bash
ddev wp db query "SELECT COUNT(*) AS n FROM wp_dovira_search_queries"
```
Expected: `n` is still the count from step 3 (`0` locally). This is what a
crawler or prefetcher leaves: nothing.

## 6. The header search sends its query once
In Chrome:
1. Open `https://dovira.ddev.site/`.
2. Open DevTools → Network, and filter by `record`.
3. Type `вакцинація` into the header search field (`Пошук...`) and press
   Enter.

Expected:
- The results page opens.
- **One** `POST https://dovira.ddev.site/wp-json/dovira/v1/search-stats/record`,
  with status `204`.
- In the DevTools console,
  `performance.getEntriesByType('resource').filter(e => e.name.includes('search-stats')).map(e => e.initiatorType)`
  prints `["beacon"]`: Chrome sent it with `navigator.sendBeacon`.

**Negative check, empty search:**
1. Open `https://dovira.ddev.site/?s=`.
2. Wait a few seconds.
3. Run the same console line.

Expected: `[]`. The page prints an empty query (and `765` results, because an
empty string matches every price row), and the module sends nothing.

## 7. The services list filter sends after the pause, and on blur
1. Open `https://dovira.ddev.site/services/` with the Network filter
   `record`.
2. Click into `Швидкий пошук по послугах:` and type `вакцин` without pausing.
   - While typing: **no** request.
   - About 1.5 s after the last letter: **one** `POST …/record`, `204`.
3. Type one more letter, `а`, making `вакцина`, and press Tab at once.
   - A second `POST` fires immediately, because the field lost focus.
   - Wait 3 s: **no** third request. Leaving the field cancelled the pause
     that was running.

The list itself still filters as you type, exactly as before this step.

## 8. A service's price-list filter never repeats the value it sent last
1. Open `https://dovira.ddev.site/services/reception-department/` with the
   Network filter `record`.
2. Click into `Пошук по послугах:`, type `кіт` and wait 3 s: **one** request.
   - All 16 price rows are hidden, since none contains `кіт`: the filter still
     filters.
3. Delete the three letters, type `кіт` again and wait 3 s.
   - **Negative check:** no second request.
4. Click `Скинути`, the reset icon on a narrow window, and wait 3 s.
   - The field is empty, and all 16 rows are shown again.
   - **Negative check:** still no second request. Clicking reset takes focus
     off the field while it still holds `кіт`, which was sent last, so
     nothing is resent.

## 9. The rows
```bash
ddev wp db query "SELECT level, query_text, context_id, results FROM wp_dovira_search_queries WHERE id > 0 ORDER BY id"   # 0 = max_id from step 3
```
Expected: exactly four rows, one per request seen in steps 6–8:
```
site      вакцинація  0   7
services  вакцин      12  NULL
services  вакцина     12  NULL
service   кіт         18  NULL
```
- The header search stores the page's count and context `0`.
- The filters store their page or service and `NULL` results.
- The text is stored normalized.

## 10. Clean up
```bash
ddev wp db query "DELETE FROM wp_dovira_search_queries WHERE id > 0"   # 0 = max_id from step 3
ddev wp db query "SELECT COUNT(*) AS n FROM wp_dovira_search_queries"
```
Expected: `Rows affected: 4`, then `n` / `0`, or whatever step 3 showed.

## Not locally verifiable
- **Recording on both productions.** After the hand deploy of `master` to
  `dovira.vet` and of `kyiv` (with `master` merged in) to `kyiv.dovira.vet`,
  repeat steps 6–8 once on each install.
  - The rows must carry that install's own table prefix and post ids.
  - Kyiv's rows must never appear in Kharkiv's table.
  - Read them over SSH on each install:
    ```bash
    wp db query "SELECT level, query_text, context_id, results, created_at FROM $(wp db prefix)dovira_search_queries ORDER BY id DESC LIMIT 5"
    ```
  - This also proves that Step 3's route answers there. A missing table
    shows up as the route's `500`; see the Step 2 and Step 3 guides.
- **Delete the test rows afterwards** on each install, so they do not reach
  the first report.
