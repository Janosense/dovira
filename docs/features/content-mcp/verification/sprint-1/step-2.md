# Verification — content-mcp, Sprint 1, Step 2

Checks that, in claude.ai on `dev.dovira.vet`, the agent can describe the site,
list and open any post with its ACF fields, blocks, terms and Russian copy, and
that an editor cannot read the clinic's form records while an administrator
can.

Branch: `content-mcp/sprint-1-read-and-price`. Use the two connectors from Step
1's verification: `Dovira dev (editor)` and `Dovira dev (admin)`. Start a **new
chat** for each item, with only the named connector on.

The names below exist in the local database. If dev's content differs, pick
the nearest equivalent and note it.

---

1. **Put the task branch on dev.**
   ```
   git push origin content-mcp/sprint-1-read-and-price:dev
   ```
   - It is a fast-forward of what Step 1 pushed.
   - Wait for «FTP deploy on push» to finish green.
   - The plugin stays active; nothing to activate.
2. **Five tools.** Open either connector's tool list in claude.ai.
   - Expected exactly: `dovira-describe-site`, `dovira-list-posts`,
     `dovira-get-post`, `dovira-list-terms`, `dovira-list-media`.
   - Nothing else, and no `mcp-adapter-*`.
3. **What is on the site** (editor). «Що є на сайті? Які типи записів і які
   поля в послуги?»
   - The agent calls `dovira-describe-site` and names the types: послуги,
     лікарі, вакансії, сторінки, блог, плюс звернення / заявки / анкети as
     read-only.
   - For the service it names `image`, `description`, `prices` (рядки: назва,
     опис, ціна, міста, «загальна ціна»), `key_words`, `seo_description`.
   - It must **not** mention per-city price columns (`city_prices`,
     `price_city_…`).
4. **A service with its prices** (editor). «Покажи послугу Приймальне
   відділення з цінами».
   - The agent finds it (`list-posts`, then `get-post`) and lists the price
     rows with amounts, e.g. «Прийом, огляд, консультація — 550 грн».
   - It names the Russian copy's id and gives the page link.
   - Open that link in a browser and compare 3 rows: each amount equals what
     the page shows for Харків.
   - A row the page shows as «Дізнатись ціну: …» (a phone) must come back as
     having **no price**, never a number.
5. **A page by its blocks** (editor). «Відкрий сторінку Про нас і перелічи її
   блоки».
   - The agent answers with the block `acf/about` and its fields by name:
     heading «Про нас», the `years` list starting 2014 «Початок шляху».
6. **A blog post** (editor). «Покажи останню новину блогу: заголовок, рубрику і
   текст».
   - The agent opens a post. It names its category (e.g. «Без категорії») and
     quotes the text from the `acf/rich-text` block's `text` field, not raw
     `<!-- wp:acf/… -->` markup.
7. **Vacancies** (editor). «Які є вакансії?»
   - The agent calls `list-posts` with type `vacancy` and lists them (locally:
     «Прибиральниця»).
8. **Blog categories** (editor). «Які є рубрики блогу?»
   - The agent calls `list-terms` on `category` and lists them with their
     language (uk «Без категорії», ru «Uncategorized» when asked for all
     languages).
9. **Media** (editor). «Покажи 3 останні зображення з медіатеки».
   - Three images with title, URL and size.
10. **The editor cannot read form records** (negative check, editor). «Покажи
    останні звернення з сайту».
    - The agent reports that it is not allowed (permission error).
    - No name, phone or message of a conversation appears.
    - Ask once more for «анкети донорів» and «заявки на вакансії»: the same
      refusal.
11. **The administrator can** (admin connector). «Покажи 2 останні звернення».
    - Two conversations with name, phone and message.
    - They have no language and no page link.
12. **Limits** (either connector, negative). «Покажи 200 послуг одним
    списком».
    - The agent may first get an error for asking more than 50 per page.
    - It then pages with at most 50 per call. No single call returns more than
      50 items.
13. **The gate.** On the task branch, on the host: `bash bin/check.sh` → `==>
    check: all green`, and the theme PHPUnit stage shows **245 tests**.
