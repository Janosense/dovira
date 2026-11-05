# Verification — content-mcp, Sprint 2, Step 1

Checks that, in claude.ai on `dev.dovira.vet`, a person can create a blog
post, a page from the theme's blocks, a service with its price list, an
employee and a vacancy. Each is created in the status they asked for, and
only after a preview and their consent. A new blog category or tag is created
first, under the same rule.

Branch: `content-mcp/sprint-2-create`. Use the connectors from Sprint 1:
`Dovira dev (editor)` and `Dovira dev (admin)`. Unless an item says otherwise,
use the **editor** connector and start a **new chat** for each numbered item.
Allow the tool calls when claude.ai asks.

Write down the id of everything you create. Item 11 removes it all again.

---

1. **Put the task branch on dev.**
   ```
   git push origin content-mcp/sprint-2-create:dev
   ```
   - It is a fast-forward of what Sprint 1 Step 4 pushed.
   - Wait for «FTP deploy on push» to finish green.
   - Note how many files `ls /home/dovirave/dovira-mcp-dumps/dev` shows.
2. **Twelve tools.** The tool list in claude.ai shows the nine of Sprint 1
   plus `dovira-create-post`, `dovira-create-term` and `dovira-update-term`.
   Reconnect the connector if it still shows nine.
3. **A draft blog post with a new category and tag.** «Створи чернетку новини
   про акцію на вакцинацію, рубрика Акції (нова), тег вакцинація».
   - **First the terms:**
     - The agent calls `dovira-create-term` for the category «Акції». The
       preview shows `taxonomy: category`, `name: Акції`. Agree.
     - The same for the tag «вакцинація», unless it already exists. In that
       case the tool answers «… уже є (id N) — використайте його.», and the
       agent uses it.
   - **Then the post:**
     - The `dovira-create-post` preview lists `post_type: post`, the title,
       `status: draft`, `terms.category` with «Акції», `terms.post_tag`, and
       the text as an `acf/rich-text` block. Every line is new: `from` is
       empty.
     - Agree.
   - **In wp-admin → Posts:** the post is a **draft** in Ukrainian (the
     Polylang column shows the flag), with the category «Акції» and the tag.
   - The dump folder has **one** new file: a new chat means a new session.
4. **The status is never assumed.** In a new chat: «Створи сторінку "Тест
   конектора" з блоком hero із заголовком "Привіт"», without saying whether
   to publish it.
   - The agent must **ask** which status (draft or published) before any
     preview.
   - If it calls the tool without one anyway, the tool refuses: `status` is
     required. Answer «чернетка» and decline the preview («Ні»). Nothing is
     created.
5. **A published page from the theme's blocks.** «Створи сторінку "Тест
   конектора" з hero (заголовок "Привіт від конектора") і текстовим блоком
   (кілька речень), опублікуй».
   - The preview lists `status: publish`, and `blocks[0]` `acf/hero` and
     `blocks[1]` `acf/rich-text` with their fields. Agree.
   - The page's link (`https://dev.dovira.vet/test-konektora/` or the slug
     the agent shows) displays the hero with the heading and the text block.
     Both blocks are visible, not just present in the editor.
   - In wp-admin, the page opens in the block editor with both blocks and
     their fields filled.
6. **A service: refused without an image, then created with prices.**
   - «Додай послугу "Тестова послуга" з двома рядками прайсу: Огляд — 450 грн,
     Аналіз — 320 грн, опублікуй», without an image.
     - The tool refuses with «Не заповнено обов’язкові поля: image.» The
       agent tells you the image is needed.
   - «Візьми будь-яке зображення з медіатеки».
     - The agent finds one (`list-media`) and previews `acf.image`, plus
       `acf.prices` with the two rows and their amounts. Agree.
   - On the service's page, the Харків tab shows «Огляд — 450 грн» and
     «Аналіз — 320 грн».
   - In wp-admin, each price row has «Is price general?» on and only Харків
     ticked.
   - «Знайди в прайсі "Огляд"» lists the new row with 450.
7. **An employee.** «Додай співробітника "Лікар Тест", посада Терапевт, фото
   будь-яке з медіатеки, опублікуй».
   - Preview (`acf.photo`, `acf.position`), consent.
   - The employee is in wp-admin → Employees and appears where the site
     lists the team.
   - Asking without a photo gets «Не заповнено обов’язкові поля: photo.»
8. **A vacancy as a draft.** «Додай вакансію "Тестова вакансія" з коротким
   описом, чернеткою».
   - Preview (`acf.description`, `status: draft`), consent.
   - The vacancy is a draft in wp-admin and is not on the site.
9. **What cannot be created** (one chat):
   - «Створи місто Одеса для послуг». The tool refuses: `taxonomy` must be
     `category` or `post_tag`. Cities are never created.
   - «Створи рубрику Акції» again. The tool answers that it already exists,
     with its id.
   - «Створи звернення від клієнта». The agent has no tool for it, or the
     tool refuses: «Звернення, заявки й анкети через конектор не
     створюються.»
   - «Створи новину російською». Refused: a new post is Ukrainian, and the
     Russian copy is a later tool.
   - No dump was written in this chat.
10. **Editing a category.** «Додай опис до рубрики Акції: "Знижки й
    пропозиції клініки"».
    - `dovira-update-term` previews `description: "" → "Знижки…"`, consent.
    - wp-admin → Posts → Categories shows the description.
11. **Clean up** (admin connector or wp-admin).
    - Move to the trash and then delete permanently in wp-admin: the post
      from item 3, the page from 5, the service from 6, the employee from 7
      and the vacancy from 8.
    - Delete the category «Акції» and the tag, if they were created here.
12. **The gate.** On the branch, `bin/check.sh` exits 0 (`==> check: all
    green`).

Each chat that created or changed something (items 3, 5, 6, 7, 8 and 10)
added exactly one dump file. Items 4 and 9 added none.
