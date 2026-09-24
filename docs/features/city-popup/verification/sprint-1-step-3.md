# Verification — city-popup, Sprint 1, Step 3

**The city question in the browser** · written by `/do-step` on 2026-09-24
Branch under test: `city-popup/sprint-1-city-question` (not merged yet)

What this step promises:
- On a blog page, a plain click on a link to the services list, a service or
  the contacts page opens screen «City question» (FEATURE.md → UI), unless a
  city is saved or the question was dismissed in this browser session.
- A city button saves `dovira_city` for 90 days and goes to that city's page,
  in the same language.
- ×, Esc and a click on the backdrop save a session-long dismissal and follow
  the link on the current site.
- Clicks with a modifier key or the middle button, pages outside the blog,
  other links, and visitors without JavaScript behave as before.
- On every page, a plain click on a link with `data-city="kharkiv|kyiv"`
  saves that city. Step 4 puts the attribute on the header switcher.
- Every decision is a Vitest case (`tests/js/city-popup/logic.test.js`).

Pages used below:
- article uk: `https://dovira.ddev.site/news/koly-tvaryni-potriben-dermatolog/`
- article ru: `https://dovira.ddev.site/ru/news/kogda-zhivotnomu-nuzhen-dermatolog/`

**To start from nothing** in any item:
- devtools → Application → Cookies → `https://dovira.ddev.site`, and delete
  `dovira_city` and `dovira_city_dismissed`;
- or use a new Incognito window.

## 1. Check out the branch and open the site
```bash
git switch city-popup/sprint-1-city-question
git log --oneline -6
ddev start
grep '^WP_ENVIRONMENT_TYPE' wp-content/themes/dovira/.env
```
Expected:
- The log shows these commits, newest first:
  - `docs(step): verification guide for sprint 1 step 3`
  - `chore(theme): rebuild assets with the city question`
  - `feat(city-popup): style the city question dialog`
  - `feat(city-popup): open the city question on a blog click and save the choice`
  - `feat(city-popup): print the cookie settings on every page`
  - `feat(city-popup): the pure decisions of the city question`
- `.env` says `WP_ENVIRONMENT_TYPE=production`, so the site serves the
  committed `assets/`.

If you run `npm start` instead, Vite serves `source/` and the result is the
same.

## 2. The gate passes
```bash
bash bin/check.sh; echo "exit=$?"
```
Expected:
- the six stages;
- `OK (127 tests, 341 assertions)` under `PHPUnit (theme dovira)`. `DialogTest`
  has 15 tests, 4 more for the config element;
- under `Vitest (theme dovira)`: `✓ tests/js/city-popup/logic.test.js (51 tests)`,
  `Test Files  2 passed (2)`, `Tests  52 passed (52)`;
- `==> check: all green` and `exit=0`.

## 3. The question opens on «Послуги», desktop and uk
Start from nothing, open the uk article at desktop width, and click
**«Послуги»** in the header. Expected, as FEATURE.md → UI describes:
- a white box centred over the page, which is dimmed;
- the title «Яке місто вас цікавить?»;
- the buttons «Харків» then «Київ», side by side;
- a × in the top-right corner;
- the address stays the article's;
- focus is on «Харків». Press Tab and the focus ring moves to «Київ», then to
  ×; it never reaches the page behind the box. After × it may go to the
  browser's own toolbar.

## 4. The same at 375 px, and in Russian
In devtools, switch on the device toolbar and pick a 375 px wide device, or
enter a width of 375. Start from nothing.
- On the uk article, open the header menu and tap «Послуги». The box fits the
  screen with a margin on both sides, and the two buttons are **stacked**,
  each full width.
- On the ru article, tap «Услуги». Expected: «Какой город вас интересует?»,
  «Харьков», «Киев». The × is read as «Закрыть»: inspect the button,
  `aria-label="Закрыть"`.

## 5. Esc: this site, until the browser is closed
Start from nothing, on the uk article (desktop):
1. Click «Послуги», then press **Esc**. You land on
   `https://dovira.ddev.site/services/`.
2. In devtools → Cookies, `dovira_city_dismissed` = `1`, Expires =
   **Session**, and there is no `dovira_city`.
3. Press Back to return to the article. The question is **not** showing.
   Click «Контакти»: you go straight to `/contacts/`, with no question.
4. Quit the browser completely and open the article again. «Послуги» asks
   again.
   - Chrome's *On startup → Continue where you left off* keeps session
     cookies. With that setting on, check this step in a new Incognito window
     instead.

## 6. × and the backdrop behave like Esc; the box itself does not
Start from nothing each time, on the uk article:
- click «Послуги», then **×** → `/services/`, with the dismissal saved as in §5;
- click «Контакти», then click the **dimmed area** outside the box →
  `/contacts/`, with the dismissal saved;
- **negative:** click «Послуги», then click the white area inside the box,
  away from the buttons. Nothing happens and the question stays open.

## 7. «Харків»: saved for 90 days
Start from nothing, on the uk article:
1. Click «Послуги», then **«Харків»**. You land on
   `https://dovira.ddev.site/services/`.
2. devtools → Cookies shows `dovira_city` = `kharkiv`:
   - Domain `dovira.ddev.site` (Chrome may show `.dovira.ddev.site`);
   - Path `/`;
   - Expires about 90 days from now;
   - SameSite `Lax`, Secure ✓.
3. Go back to the article and click «Контакти» or «Послуги». There is no
   question; the browser goes straight there.

## 8. «Київ» and a saved Kyiv: the same page on the Kyiv site
Start from nothing, on the uk article:
1. Click «Послуги», then **«Київ»**. The browser goes to
   `https://kyiv.dovira.ddev.site/services/?city-popup=1`.
   - DDEV's DNS resolves that host, but its certificate does not cover it, so
     Chrome shows **"Your connection is not private"**. The address bar shows
     the URL, and that is the check.
   - Whether the Kyiv site then serves the page is checked by the next deploy
     (see "Not verified here").
2. Go back. `dovira_city` = `kyiv` now. Click «Контакти» and you go to
   `https://kyiv.dovira.ddev.site/contacts/?city-popup=1` with no question.
3. **A service link.** Start from nothing on the uk article and add one in
   the console, then click it:
   ```js
   document.querySelector('h1').insertAdjacentHTML('afterend', '<p><a href="/services/diagnostics/">test service link</a></p>')
   ```
   The question opens. Choose «Київ» →
   `https://kyiv.dovira.ddev.site/services/diagnostics/?city-popup=1`.

   You can also add a real link to `/services/{a service}/` to a local article
   in the editor.
4. **Russian.** Start from nothing on the ru article. «Услуги» → «Киев» →
   `https://kyiv.dovira.ddev.site/ru/uslugi/?city-popup=1`.

## 9. Negative checks: what the feature leaves alone
Start from nothing, on the uk article:
- **Ctrl-click (Cmd-click on macOS)** on «Послуги» opens `/services/` in a
  new tab. There is no question, and no cookie.
- **Middle-click** on «Послуги» does the same.
- «Про нас», «Блог» and a link inside the article text lead where they
  always did, with no question.
- On the **home page** `https://dovira.ddev.site/`, «Послуги» and «Контакти»
  go straight there. On any page outside the blog nothing is ever asked.
- **JavaScript off.** In devtools press ⌘⇧P / Ctrl⇧P, type "Disable
  JavaScript" and reload the article. «Послуги» and «Контакти» are plain
  links, and the question never shows. Run "Enable JavaScript" afterwards.

## 10. The settings are on every page; `data-city` saves on every page
```bash
for u in https://dovira.ddev.site/ https://dovira.ddev.site/services/ https://dovira.ddev.site/news/; do
  printf "%-36s " "$u"; curl -sk "$u" | grep -o '<div hidden data-city-popup-config[^>]*>'
done
```
Expected, on each line:
`<div hidden data-city-popup-config data-cookie-domain="dovira.ddev.site" data-days="90">`.
The `<dialog>` no longer carries `data-cookie-domain` or `data-days`.

In the browser, on the **home page** (no dialog there), start from nothing and
paste in the console:
```js
document.body.insertAdjacentHTML('beforeend', '<a id="probe" href="/contacts/" data-city="kyiv">probe</a>'); document.getElementById('probe').click()
```
The browser goes to `/contacts/`, and devtools → Cookies shows
`dovira_city` = `kyiv`, 90 days. This is the switcher's mechanism; Step 4
puts `data-city` on the switcher itself.

Then delete the cookie, and repeat with `data-city="odesa"`. No `dovira_city`
is written.

## 11. Nothing else changed on the page
On the uk article and the home page, at desktop and at 375 px:
- the page looks as it did on `master` until a question opens;
- the console shows nothing new from this feature. The known
  `ReferenceError: wp is not defined` from `wp-i18n` / Contact Form 7 predates
  this step (WORKLOG `/adhoc` candidate).

## Not verified here
- **The move to the other install.** Whether
  `https://kyiv.dovira.vet/services/?city-popup=1` serves the Kyiv page is
  verified by the next hand deploy from `master` (Kharkiv) and the merge of
  `master` into `kyiv` (Kyiv).
- **The fallback for a missing service** on the receiving site, and the
  header switcher carrying `data-city`, are Step 4.
