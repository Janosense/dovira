# Verification — city-popup, Sprint 1, Step 4

**The switcher saves the choice; a missing service falls back to the list** ·
written by `/do-step` on 2026-09-24
Branch under test: `city-popup/sprint-1-city-question` (not merged yet)

What this step promises:
- The header city switcher saves the city it switches to. On every page, a
  plain click on «Київ» (on Kharkiv), in the desktop header or in the mobile
  menu, saves `dovira_city=kyiv` for 90 days. The browser then goes to
  `https://kyiv.dovira.vet` as before. The switcher looks the same, and its
  links are unchanged.
- After that, a blog link to Services or Contacts goes straight to the Kyiv
  site with no question.
- A move to the other site that lands on a service that site does not have
  (a 404 under `/services/` or `/ru/services/` with `city-popup=1`) is sent
  with a 302 to that site's services list, in the same language.
- Every other 404 stays a 404. WordPress's own redirects for a renamed
  service or a longer slug still come first (plan Question 1, A).

Pages used below:
- home: `https://dovira.ddev.site/`
- article uk: `https://dovira.ddev.site/news/koly-tvaryni-potriben-dermatolog/`

**To start from nothing** in any item:
- devtools → Application → Cookies → `https://dovira.ddev.site`, and delete
  `dovira_city` and `dovira_city_dismissed`;
- or use a new Incognito window.

## 1. Check out the branch and open the site
```bash
git switch city-popup/sprint-1-city-question
git log --oneline -5
ddev start
grep '^WP_ENVIRONMENT_TYPE' wp-content/themes/dovira/.env
```
Expected:
- The log shows these commits, newest first, above
  `merge: sprint 1 step 3 — The city question in the browser`:
  - `docs(step): verification guide for sprint 1 step 4`
  - `feat(city-popup): a missing service with the marker falls back to the services list`
  - `feat(theme): the header city switcher names its city`
  - `refactor(city-popup): decide the data-city cookie in logic.js`
- `.env` says `WP_ENVIRONMENT_TYPE=production`, so the site serves the
  committed `assets/`.

## 2. The gate passes
```bash
bash bin/check.sh; echo "exit=$?"
```
Expected:
- the six stages;
- `OK (145 tests, 360 assertions)` under `PHPUnit (theme dovira)`: 18 more,
  all in `FallbackTest`;
- under `Vitest (theme dovira)`: `✓ tests/js/city-popup/logic.test.js (58 tests)`
  and `Tests  59 passed (59)`: 7 more, for `namedCityCookie`;
- `==> check: all green` and `exit=0`.

## 3. The switcher's links carry the city, and nothing else changed
```bash
curl -sk https://dovira.ddev.site/ | grep -o '<a href="https://kyiv.dovira.vet" data-city="kyiv"' | wc -l
curl -sk https://dovira.ddev.site/ | grep -c '<a [^>]*data-city="kharkiv"'
```
Expected:
- `2`: the desktop and the mobile link to Kyiv, with their href unchanged;
- `0`: Kharkiv's own city is the active `div`, not a link. The
  `data-city="kharkiv"` links are printed only on the Kyiv site.
- In the browser, the header on the home page and on the article looks the
  same as on `master`: «Київ» as a link, «Харків» active with its pin.

## 4. The desktop switcher saves Kyiv
1. Start from nothing, at desktop width, and open the home page.
2. Click «Київ» in the header.
3. The browser goes to `https://kyiv.dovira.vet`, the production Kyiv site, as
   before.
4. Go back to `https://dovira.ddev.site/` (Back, or type the address).
5. devtools → Application → Cookies → `https://dovira.ddev.site`.

Expected:
- `dovira_city` = `kyiv`, Domain `dovira.ddev.site`, Path `/`, expiring in
  about 90 days;
- no `dovira_city_dismissed`.

## 5. A saved Kyiv skips the question
With the cookie from item 4, open the article uk and click «Послуги» in the
header.

Expected:
- No dialog opens.
- The browser goes straight to `https://kyiv.dovira.ddev.site/services/?city-popup=1`.
  That host does not resolve locally, so the browser shows its "can't reach
  this site" page with that address in the address bar.

## 6. The mobile menu's switcher saves Kyiv
1. Start from nothing, and set devtools' device toolbar to 375 px wide.
2. Open the home page, tap the hamburger, then tap «Київ» in the menu.
3. Come back to `https://dovira.ddev.site/` and look at the cookies as in
   item 4.

Expected: `dovira_city` = `kyiv`, about 90 days, as in item 4.

## 7. What must not save a city
Start from nothing, at desktop width, on the home page.
- Click the active «Харків» in the header: nothing happens and no cookie
  appears. It is a `div`, not a link.
- Ctrl-click (Cmd-click on a Mac) «Київ»: the Kyiv site opens in a new tab,
  and this tab still has no `dovira_city`. The browser keeps modifier clicks
  (FEATURE.md → Invariants).
- On the article uk, the question still opens on «Послуги» (Step 3), because
  nothing was saved. Press Esc to close it.

## 8. A marked missing service falls back to the services list
```bash
for u in '/services/no-such-service/?city-popup=1' '/services/surgery/no-such/?city-popup=1' '/ru/services/no-such-service/?city-popup=1'; do
  printf '%s  ' "$u"; curl -sk -o /dev/null -w '%{http_code} %{redirect_url}\n' "https://dovira.ddev.site$u"
done
```
Expected:
```
/services/no-such-service/?city-popup=1  302 https://dovira.ddev.site/services/
/services/surgery/no-such/?city-popup=1  302 https://dovira.ddev.site/services/
/ru/services/no-such-service/?city-popup=1  302 https://dovira.ddev.site/ru/uslugi/
```
In the browser, `https://dovira.ddev.site/services/no-such-service/?city-popup=1`
lands on «Послуги» at `https://dovira.ddev.site/services/`, and
`https://dovira.ddev.site/ru/services/no-such-service/?city-popup=1` lands on
the Russian list at `/ru/uslugi/`. Neither address carries the marker.

## 9. Every other 404 stays a 404
```bash
for u in '/services/no-such-service/' '/ru/services/no-such-service/' '/services/no-such-service/?city-popup=0' '/no-such-page/?city-popup=1' '/news/no-such/?city-popup=1'; do
  printf '%s  ' "$u"; curl -sk -o /dev/null -w '%{http_code}\n' "https://dovira.ddev.site$u"
done
```
Expected: `404` on every line. That covers no marker, a marker other than
`1`, and a marked address outside the service base.

## 10. WordPress's own redirects come first, and real pages are untouched
```bash
for u in '/services/diagnost/?city-popup=1' '/services/diagnostics/?city-popup=1' '/services/?city-popup=1'; do
  printf '%s  ' "$u"; curl -sk -o /dev/null -w '%{http_code} %{redirect_url}\n' "https://dovira.ddev.site$u"
done
```
Expected:
```
/services/diagnost/?city-popup=1  301 https://dovira.ddev.site/services/diagnostics/?city-popup=1
/services/diagnostics/?city-popup=1  200
/services/?city-popup=1  200
```
The first line is WordPress guessing the longer slug, as it does for every
visitor (plan Question 1, A). The fallback leaves it alone.

## Not verified here
The Kyiv side needs the next hand deploy from `master` and the merge of
`master` into `kyiv`:
- on a `dovira.vet` article, «Київ» in the question leads to
  `kyiv.dovira.vet/services/`;
- the Kyiv switcher's «Харків» changes the saved city;
- `kyiv.dovira.vet/services/no-such-service/?city-popup=1` lands on the Kyiv
  services list.
