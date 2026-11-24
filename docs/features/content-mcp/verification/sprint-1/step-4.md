# Verification — content-mcp, Sprint 1, Step 4

Checks that, in claude.ai on `dev.dovira.vet`, an existing post can be changed
after a preview and your consent: an ACF value, a title in one or both
languages, a field of a block, a category. It also checks that only an
administrator can move a post to the trash, and that the post can be restored
from there.

Branch: `content-mcp/sprint-1-read-and-price`. Use the connectors from Step 1:
`Dovira dev (editor)` and `Dovira dev (admin)`. Unless an item says otherwise,
use the **editor** connector and start a **new chat** for each numbered item.
Allow the tool calls when claude.ai asks.

The names below exist in the local database; dev's content may differ. Note
what you change and put it back as each item says.

---

1. **Put the task branch on dev.**
   ```
   git push origin content-mcp/sprint-1-read-and-price:dev
   ```
   - It is a fast-forward of what Step 3 pushed.
   - Wait for «FTP deploy on push» to finish green.
   - `DOVIRA_MCP_DUMP_DIR` stays as set in Step 3.
   - Before you start, note how many files
     `ls /home/dovirave/dovira-mcp-dumps/dev` shows.
2. **Nine tools.** The connector's tool list in claude.ai shows the seven of
   Step 3 plus `dovira-update-post` and `dovira-trash-post`. Reconnect the
   connector if it still shows seven.
3. **Close a vacancy** (editor). «Переведи вакансію Прибиральниця в закриту».
   - The agent opens the vacancy and calls `dovira-update-post` without
     confirmation.
   - The preview shows `acf.is_open: true → false` for the Ukrainian vacancy
     only.
   - Answer «Так». The result names the vacancy's id and a **new** dump file.
     The dump folder has **one** file more than in item 1.
   - Open the vacancy's page on `https://dev.dovira.vet`: the vacancy block
     (title, cities, the application form) is gone. The vacancies list block
     no longer lists it.
   - In the **same chat**: «Відкрий її знову». You get a preview
     `false → true`. After your consent the result says `"dump": "existing"`,
     and the page shows the vacancy again.
4. **A page title, then its Russian copy** (editor). «Зміни заголовок сторінки
   Про нас на "Про клініку Dovira"».
   - The preview has **one** line: `title`, for the Ukrainian page only.
   - Before answering, ask «А російську копію теж?». The new preview has
     **two** lines: the Ukrainian title and the Russian page's title (the
     agent writes the Russian wording, e.g. «О клинике Dovira»). Agree.
   - Both titles change in wp-admin → Pages and on `/about/` and the
     `/ru/` page.
   - Then ask to put both titles back («Про нас» / «О нас»), with a preview,
     and agree.
5. **A field of a block** (editor). «На сторінці Франшиза зміни заголовок у
   блоці hero на "Франшиза Dovira — тест"».
   - The agent opens the page (`get-post`) and calls `update-post` with the
     `acf/hero` block naming only that field.
   - The preview has **one** line: `blocks[i].acf/hero.{field}` with the old
     and the new text, nothing else.
   - After consent, `https://dev.dovira.vet/franchise/` shows the new
     heading, and every other block and field on the page looks as before.
   - In wp-admin, open the page: the hero block opens in the editor with its
     fields intact.
   - Put the heading back the same way.
6. **A category on a blog post** (editor).
   - «Додай до новини "Коли тварині потрібен дерматолог?" рубрику Акції».
     - If dev has no category «Акції», the agent must say it doesn't exist and
       that it cannot create one: creating categories comes in Sprint 2. No
       preview is offered.
     - Then ask it to add an **existing** category (ask «Які є рубрики?»
       first).
   - The preview shows `terms.category` with the current list and the list
     with the new category. After consent, the post page shows the category.
   - Put the category list back as it was.
7. **What cannot be changed** (editor), all in one chat:
   - «Зміни ціну на екстрений прийом через update-post». The tool refuses:
     «Ціни послуги змінює лише dovira-update-price-row…». The agent may then
     offer the price tool; answer «Ні».
   - «Зміни заголовок останнього звернення». As the editor the record cannot
     even be read. Repeat this one line on the **admin** connector: the tool
     refuses with «Звернення, заявки й анкети через конектор лише
     читаються.»
   - «Додай на сторінку Про нас звичайний абзац тексту (core/paragraph)». The
     tool refuses: the block is not available for this post, and the list of
     the theme's blocks is named.
   - Nothing was written: the dump folder has no new file from this chat.
8. **The editor cannot trash** (editor). «Видали вакансію Прибиральниця».
   - The agent calls `dovira-trash-post` and reports that you are not
     allowed (permission denied).
   - The vacancy is still published.
9. **The administrator trashes, to the trash** (admin). «Видали вакансію
   Прибиральниця».
   - The preview shows `status: publish → trash` for the Ukrainian vacancy,
     plus a warning that its Russian copy (id …) stays.
   - Agree. The vacancy is in wp-admin → Vacancies → Trash, its page is gone
     from the site, and the Russian copy is still published.
   - The dump folder has one new file for this chat.
   - **Restore** it in wp-admin (Trash → Restore). If it comes back as a
     draft, publish it. The page is back.
10. **The gate.** On the branch, `bin/check.sh` exits 0 (`==> check: all
    green`).

Each chat that wrote something (items 3, 4, 5, 6 and 9) added exactly one dump
file. At the end, `ls /home/dovirave/dovira-mcp-dumps/dev` shows five more
files than in item 1.
