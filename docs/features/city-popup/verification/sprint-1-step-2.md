# Verification — city-popup, Sprint 1, Step 2

**Feature bootstrap and the dialog on blog pages** · written by `/do-step` on 2026-09-23
Branch under test: `city-popup/sprint-1-city-question` (not merged yet)

What this step promises:
- The feature is loaded by one line in `functions.php`.
- On a blog page it prints one **closed** `<dialog>` in the footer: the
  `news` page, an article, or their Russian versions. The dialog carries:
  - the question and the two city buttons;
  - the ×;
  - as data attributes: the current city, both installs' origins, the cookie
    domain, the days and the target URLs.
- On any other page nothing is printed.
- Nothing visible changes anywhere. Nothing opens the dialog until Step 3.
- The four strings are Polylang strings. The two new ones have ru values
  locally, and the city names reuse the ru values that already exist
  (plan Question 1, answer B).

Run the commands from the repo root (`/Users/tymofii/Projects/php/dovira`).
The site is `https://dovira.ddev.site`.

## 1. Check out the branch and start the site
```bash
git switch city-popup/sprint-1-city-question
git log --oneline -5
ddev start
```
Expected: the log shows these commits, newest first:
- `docs(step): verification guide for sprint 1 step 2`
- `feat(city-popup): load the feature and print the dialog on blog pages`
- `feat(city-popup): the city question dialog and its strings`
- `feat(city-popup): find the blog page and the target URLs in the current language`
- `feat(city-popup): derive the city, both origins and the cookie domain from the site URL`

No front-end file changed, so there is no `npm run build` and `assets/` is
untouched.

## 2. The gate passes
```bash
bash bin/check.sh; echo "exit=$?"
```
Expected:
- the six stages of Step 1;
- `141 files checked` under `php -l` (134 + 4 feature files + 3 tests);
- `OK (123 tests, 320 assertions)` under `PHPUnit (theme dovira)`. Of those,
  31 are new: `SitesTest` 7, `PagesTest` 13, `DialogTest` 11;
- `==> check: all green` and `exit=0`.

## 3. The Ukrainian blog page and an article carry the dialog
```bash
for u in https://dovira.ddev.site/news/ https://dovira.ddev.site/news/koly-tvaryni-potriben-dermatolog/; do
  echo "== $u"; curl -sk "$u" | awk '/<dialog/,/<\/dialog>/'
done
```
Expected, for **each** of the two pages, exactly one block:
- `data-current-city="kharkiv"`;
- `data-kharkiv-origin="https://dovira.ddev.site"` and
  `data-kyiv-origin="https://kyiv.dovira.ddev.site"`;
- `data-cookie-domain="dovira.ddev.site"` and `data-days="90"`;
- `data-targets` holds, as `&quot;`-escaped JSON:
  - `services` → `https://dovira.ddev.site/services/`;
  - `contacts` → `https://dovira.ddev.site/contacts/`;
  - `service` → `https://dovira.ddev.site/services/`;
- the heading `Яке місто вас цікавить?`;
- the buttons `Харків` (with `autofocus`), then `Київ`;
- the × button with `aria-label="Закрити"`;
- **no** `open` attribute on `<dialog …>`.

In the browser, *View Page Source* on `https://dovira.ddev.site/news/` shows
the same block near the end of `<body>`, before the footer `<script>` tags.

## 4. The Russian blog page and a Russian article carry the ru version
```bash
for u in https://dovira.ddev.site/ru/blog/ https://dovira.ddev.site/ru/news/kogda-zhivotnomu-nuzhen-dermatolog/; do
  echo "== $u"; curl -sk "$u" | awk '/<dialog/,/<\/dialog>/'
done
```
Expected for each page:
- the same origins, cookie domain and days as in §3;
- `data-targets` holds:
  - `services` → `https://dovira.ddev.site/ru/uslugi/`;
  - `contacts` → `https://dovira.ddev.site/ru/kontakty/`;
  - `service` → `https://dovira.ddev.site/ru/services/`;
- the heading `Какой город вас интересует?`;
- the buttons `Харьков`, then `Киев`;
- `aria-label="Закрыть"`.

## 5. Negative check: no dialog anywhere else
```bash
for u in https://dovira.ddev.site/ https://dovira.ddev.site/services/ https://dovira.ddev.site/services/reception-department/ https://dovira.ddev.site/contacts/ https://dovira.ddev.site/ru/; do
  printf "%-60s %s\n" "$u" "$(curl -sk "$u" | grep -c '<dialog')"
done
```
Expected: `0` on every line: the home page, the services list, a service,
contacts and the Russian home page.

## 6. Nothing visible changes
Open these in the browser at desktop width, then at 375 px (devtools device
toolbar):
- `https://dovira.ddev.site/news/`
- `https://dovira.ddev.site/news/koly-tvaryni-potriben-dermatolog/`
- `https://dovira.ddev.site/ru/blog/`

Expected:
- each page looks as it did on `master`, with no text or buttons from the
  question anywhere;
- the header links «Послуги» / «Контакти» work as plain links;
- the console shows no new error.

In devtools → Elements, the `<dialog class="city-popup" …>` is there. Its
Computed `display` is `none`, because the browser hides a `<dialog>` without
`open`.

## 7. The strings in Languages → Translations
In wp-admin, go to Languages → Translations. Search `міст`, then `Закрити`,
`Харків` and `Київ`. Expected:

| String (uk key) | Group shown | Русский |
|---|---|---|
| Яке місто вас цікавить? | dovira | Какой город вас интересует? |
| Закрити | dovira | Закрыть |
| Харків | Dovira: Cities | Харьков |
| Київ | dovira | Киев |

Polylang lists each source text once. When two registrations share a text,
the later one names the row, and the translation is shared.
- The `service-city` term «Харків» (the only term on this install) is
  registered on `init`, after the theme's list, so «Харків» shows under
  Dovira: Cities.
- No term is named «Київ» here, so «Київ» shows under the theme's `dovira`
  group. Its ru value `Киев` was already stored.

The same read from the shell:
```bash
ddev wp eval 'foreach ( [ "Яке місто вас цікавить?", "Закрити", "Харків", "Київ" ] as $k ) { echo "$k → " . pll_translate_string( $k, "ru" ) . "\n"; }'
```

## 8. The days filter, including a value below 1
```bash
ddev wp eval '
$dialog = new dovira\CityPopup\Dialog( new dovira\CityPopup\Sites( home_url() ), new dovira\CityPopup\Pages() );
echo "default: " . $dialog->days() . "\n";
add_filter( "dovira_city_popup_days", fn() => 30, 20 ); echo "filtered to 30: " . $dialog->days() . "\n";
add_filter( "dovira_city_popup_days", fn() => 0, 30 );  echo "filtered to 0: " . $dialog->days() . "\n";
'
grep -rn "90" wp-content/themes/dovira/inc/features/city-popup/
```
Expected:
- `default: 90`, `filtered to 30: 30`, `filtered to 0: 90`;
- the `grep` finds exactly one line:
  `Dialog.php:…: public const DEFAULT_DAYS = 90;`.

## Not verified here
- The ru values of `Яке місто вас цікавить?` and `Закрити` on each production.
  After the developer's next hand deploy from `master` (Kharkiv) and the merge
  into `kyiv` (Kyiv), type `Какой город вас интересует?` and `Закрыть` in
  Languages → Translations on each install. That deploy is the check.
  - Until then those two strings show in Ukrainian on `/ru/` pages. The city
    names do not need this step.
  - On Kyiv, which has no blog, the dialog is never printed.
- The dialog opening, the choice and the cookies are Step 3.
