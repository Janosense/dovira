# Verification — content-mcp, Sprint 2, Step 2

This guide checks two things in claude.ai on `dev.dovira.vet`:

- **Russian copies.** A person can make the Russian copy of a Ukrainian post
  that has none. The agent writes the Russian texts, prices go over
  unchanged, and the language switcher of the Ukrainian page leads to the
  copy.
- **Images.** An image from a URL lands in the media library with its alt
  text and is then set in a post. A non-image or a huge file is refused
  before anything is downloaded.

Every write goes through the preview and your consent, as before.

**Branch:** `content-mcp/sprint-2-translate-media`.

**Connectors:** use the ones from Sprint 1, `Dovira dev (editor)` and
`Dovira dev (admin)`. Unless an item says otherwise:

- use the **editor** connector;
- start a **new chat** for each numbered item;
- allow the tool calls when claude.ai asks.

Write down the id of everything you create. Item 9 removes it all again.

---

1. **Put the task branch on dev.**
   ```
   git push origin content-mcp/sprint-2-translate-media:dev
   ```
   - This is a fast-forward of what Step 1 pushed.
   - Wait for «FTP deploy on push» to finish green.
   - Note how many files `ls /home/dovirave/dovira-mcp-dumps/dev` shows.
2. **Fourteen tools.**
   - The tool list in claude.ai shows the twelve tools of Step 1 plus
     `dovira-create-translation` and `dovira-upload-media`.
   - If it still shows twelve, reconnect the connector.
3. **A Ukrainian service to translate.** Dev may have no Ukrainian service
   without a Russian copy, so create one.
   - Ask: «Додай послугу "Тестова послуга" з описом у два речення і двома
     рядками прайсу: Огляд — 450 грн, Аналіз — 320 грн; зображення будь-яке з
     медіатеки; опублікуй».
   - Check the preview and agree (as in Step 1, item 6).
   - Note the service's id and the image's id.
4. **Its Russian copy.** In a new chat, ask: «Зроби російську копію послуги
   "Тестова послуга"».
   - **Before any preview:** the agent asks which status, or you name one.
     Answer «опублікуй».
   - **The preview** (`dovira-create-translation`) shows:
     - target `lang: ru`, with `id` empty;
     - a Russian `title`, e.g. «Тестовая услуга»;
     - `status: publish` and a Russian `slug`;
     - `acf.image` with the same image;
     - the description in Russian;
     - `acf.prices` with the Russian row titles (e.g. «Осмотр», «Анализ») and
       the amounts **450** and **320**, unchanged.
   - **Warnings:** if the agent left any text untranslated, a warning lists
     it («Перенесено з української без перекладу: …»). If it lists
     something you want in Russian, ask the agent to translate it: you get a
     new preview.
   - **Agree.**
     - The result names the new id.
     - The dump folder has **one** more file than before.
   - **In wp-admin → Services**, switch the language filter to «Русский». The
     copy is there, and the Polylang column links it to the Ukrainian
     service.
   - **In the copy's «Prices»:**
     - each row has the same «Is price general?» setting and the same cities
       as the Ukrainian row;
     - only the titles and descriptions are Russian.
   - **On the site:**
     - Open the Ukrainian service's page and switch the language to RU in the
       header. It opens the Russian page (`https://dev.dovira.vet/ru/…`), not
       the Russian home page.
     - The Russian page's Харків tab shows «Осмотр — 450 грн» and
       «Анализ — 320 грн».
5. **A second copy is refused.** In the same chat, ask: «Зроби російську
   копію ще раз».
   - The tool refuses with «Російська копія вже є (id …) — змінюйте її через
     dovira-update-post.», where the id is the copy's.
   - Nothing is created, and the dump folder doesn't change.
   - Also ask: «Зроби російську копію російської сторінки "О нас"».
     - It is refused: only the Ukrainian version is translated, and the
       answer names the Ukrainian page's id.
6. **An image from a URL, set on an employee.**
   - In a new chat, ask: «Заміни фото співробітника [будь-який співробітник]
     на це: https://upload.wikimedia.org/wikipedia/commons/3/3a/Cat03.jpg».
     First note the employee's current photo id; the agent shows it with
     `get-post`.
   - **First preview** (`dovira-upload-media`):
     - `source` is the URL;
     - `filename` is `cat03.jpg`;
     - `mime` is `image/jpeg`;
     - an `alt` the agent proposes, e.g. «Кіт»;
     - possibly `attach_to` with the employee.
     - **Before answering**, check that the image is not yet in Media →
       Library.
   - **Agree.** The result has `media` with an id, a URL ending in
     `cat03.jpg`, a width and a height.
   - **Second preview** (`dovira-update-post`): `acf.photo` from the old
     image to the new id. Agree.
   - **In wp-admin → Media:**
     - the cat is in the library, its author is the editor user, and its
       «Alternative text» is the alt from the preview;
     - the employee's photo is the cat, also where the site lists the team.
   - The dump folder has **one** more file: both writes were in this chat.
   - **Put it back:** ask «Поверни попереднє фото (id …)», with a preview, and
     agree.
7. **What must not be downloaded.** Do this in a new chat; nothing in it is
   confirmed.
   - Ask: «Завантаж у медіатеку https://the.earth.li/~sgtatham/putty/latest/w64/putty.exe».
     - It is refused at once with «За адресою не зображення дозволеного типу
       (application/x-msdos-program)…».
   - Ask: «Завантаж у медіатеку
     https://upload.wikimedia.org/wikipedia/commons/e/ea/Van_Gogh_-_Starry_Night_-_Google_Art_Project.jpg».
     - It is refused **within a second or two** with «Зображення завелике:
       663.9 MB, можна до 10.0 MB…». That speed shows nothing was
       downloaded.
   - Ask: «Завантаж http://127.0.0.1/a.png».
     - It is refused: only a public http(s) address.
   - Media → Library has no new file, and the dump folder has no new file
     from this chat.
8. **A pasted image** (expected limitation, not a failure). Paste a picture
   into a chat and ask to upload it.
   - claude.ai cannot hand the image's bytes to a tool, so the agent should
     say so and ask for a URL.
   - The `base64` input was checked locally over MCP with a small PNG.
   - Note what the agent answered.
9. **Clean up** (admin connector or wp-admin).
   - Move to the trash and then delete permanently:
     - the Ukrainian test service from item 3;
     - its Russian copy from item 4.
   - Delete the cat image from Media → Library, after the employee's photo
     is back (item 6).
10. **The gate.** On the branch, `bin/check.sh` exits 0 (`==> check: all
    green`).

**Dump count:** each chat that created or changed something added exactly one
dump file: items 3, 4 and 6, three files in all. Items 5 and 7 added none.
