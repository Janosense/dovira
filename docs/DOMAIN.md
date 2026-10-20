# Domain — Dovira

<!-- The world the code models, in the customer's own words. Adoption: the
     terms are taken from the code as-is (2026-09-08); the rules are what the
     code enforces — the clinic confirms them. Rules here are laws of the
     domain, not choices — a choice belongs in DECISIONS.md. -->

## Glossary
| Term (the customer's word) | Meaning |
|---|---|
| Клініка «Довіра» (Dovira) | The veterinary clinic; one brand, two independent locations (філії) |
| Філія / місто (city) | A clinic location: Харків (`dovira.vet`) and Київ (`kyiv.dovira.vet`). Each has its own site, prices, team, vacancies and contacts |
| Послуга (service) | A category of veterinary work (e.g. хірургія, вакцинація) presented as one page with a price list |
| Прайс / вартість (price list) | The list of priced items inside a service; every item is priced per city, or once for all cities |
| Загальна ціна (general price) | One price valid in every city the item is offered in |
| Ціна по місту (city price) | A price that differs between Kharkiv and Kyiv |
| Підпослуга (sub-service) | A service page without its own price table (the "Sub-Service" template), usually a child of a service |
| Команда / співробітник (employee) | A vet or staff member shown on the team page, belonging to a city |
| Вакансія (vacancy) | An open or closed job offer of a city, with a salary shown as a fixed amount, "from", or a range |
| Заявка (application) | A candidate's response to a vacancy: name, contacts, CV; moves through statuses |
| Звернення (conversation) | A message from the contact form or the franchise form; handled by support |
| Франшиза (franchise) | A prospective partner asking to open a Dovira location — its form creates a conversation prefixed "Франшиза:" |
| Анкета донора / Банк крові (blood-donor questionnaire) | A pet owner offering their dog or cat as a blood donor; owner, pet, blood group, health answers |
| Опрацьовано (processed) | A conversation or application that support has handled; the processing time is recorded |
| Відповідальні (responsible persons) | Every staff member who opened a record — accumulated automatically |
| Спеціаліст підтримки (customer support specialist) | The admin role that sees only conversations/applications/questionnaires |
| Налаштування → Контакти (settings / contacts) | Per-city address, phones, schedule (clinic and pet shop), map, e-mail, Instagram; and the global social links |
| Мова (language) | Ukrainian is the original; Russian pages are translations of it |
| Щоденний звіт (daily report) | One Telegram message per day per city site, sent by the `ga-telegram-bridge` plugin, that tells the owner how many people visited yesterday and over the last 4 weeks, which pages they read, where they came from, from which cities and on which devices. Below those, the site adds up to three blocks of what visitors searched for — «Пошук по сайту» (the header search, with the queries that found nothing marked), «Пошук у переліку послуг» and «Пошук у послугах» — each with yesterday's and the last 28 days' five most frequent queries; a level nobody searched adds no block |
| Відвідувачі (visitors) | Distinct people who engaged with the site in a period — GA4 "active users", the same number the owner sees as "Users" in the GA4 app |
| Динаміка (dynamics) | The change in % of yesterday against the average of the previous 7 days, and of the last 28 days against the previous 28 days; ▲ up, ▼ down, — when there is nothing to compare with |
| Блок звіту (report block) | One section of the daily report: visitors, top pages, traffic sources, cities, devices, trend; the owner's site administrator switches blocks on and off, visitors is always on. Trend is the one block that is not a section of its own — it is a line inside the visitors block. Other parts of the same site may add blocks of their own after the plugin's blocks and before the closing link; the settings screen does not switch those |
| Тренд (trend) | The shape of daily visitors over the last 28 days, drawn as 28 block characters (`▁▂▃▄▅▆▇█`) scaled between the period's quietest and busiest day, with those two numbers beside it. It shows the shape of the four weeks, never a total: 28 daily figures of active users do not add up to the 28-day figure, because someone who came on three days counts three times in the sum and once in the period |
| Одержувач звіту (report recipient) | The one Telegram chat — a channel or a person — that a site's report goes to |
| Пошук по сайту (site search) | The three ways a visitor searches on a city site: the header form that opens a results page (global), the filter of the services list on a page that carries the «Послуги» block, and the filter of the price list inside one service page |
| Пошуковий запит (search query) | What a visitor actually looked for: the text once they stopped typing (or left the field), at least three letters for the two filters, normalized so that «Вакцинація», «вакцинація » and «вакцинація» are one query. For the two filters a query is the pair (text, where it was typed). The report also counts as one query the spellings the database treats as the same letter — «ґ» and «г», «ё» and «е» — and prints one of them |
| Рівень пошуку (search level) | Which of the three searches a query came from — `site`, `services` (the list) or `service` (inside a service); the daily report shows each level in its own block |
| Нічого не знайдено (nothing found) | A site-search query whose results page showed no results every time it was searched in the period (the largest results count of the period is 0; one search that found something clears it) — the sign that people look for something the site does not offer or does not name that way. Only the header search has it: the two filters record no results count |
| Блог (blog) | The clinic's articles, published only on the Kharkiv site: the page «Блог» (`/news/`, ru `/ru/blog/`) and every article (`/news/{slug}/`, ru `/ru/news/{slug}/`). Search brings readers to it from all of Ukraine, not only from Kharkiv |
| Питання міста (city question) | The small window «Яке місто вас цікавить?» with the buttons «Харків» and «Київ» that a blog reader sees when they tap a link to the services list, to a service or to the contacts page while no city is remembered for them. Russian: «Какой город вас интересует?», «Харьков», «Киев» |
| Обране місто (chosen city) | The city a visitor picked in the city question or in the header city switcher. It is remembered on their device for 90 days and is known on both city sites |

## Rules & invariants
- Every service, employee and vacancy belongs to exactly one or more cities; a
  visitor on the Kharkiv site sees Kharkiv content only, and vice versa.
- A priced item has either one general price or one price per city — never
  both; an item listed for a city without a price shows "call us" with that
  city's phone instead of an amount.
- Prices are edited by clinic staff in the admin, never by a developer, and
  take effect immediately on publish.
- Nothing a visitor submits is lost: the record is saved first; the Telegram
  message to staff is a courtesy notification.
- Applications go `new → in processing → interview scheduled → rejected |
  closed`; a conversation is simply new or processed.
- Only open vacancies accept applications from the site (the vacancy page
  carries the form with the vacancy id).
- A blood-donor questionnaire requires owner name and phone, animal, sex, age,
  weight, vaccination date and the yes/no health answers; e-mail and pet name
  are optional.
- Ukrainian is the source of truth for content; a Russian page is never
  edited into something the Ukrainian page does not say. Russian pages are not
  indexed by search engines.
- City names are shared between languages (one list of cities), only their
  spelling is translated.
- A daily report covers one calendar day of the site's own GA4 property and
  is delivered at most once per day; a day whose report could not be built is
  tried again a configured number of times and then announced as missing to the
  same chat, never silently skipped and never filled with guesses.
- The report's periods follow Google Analytics' calendar for the property;
  the delivery time follows the site's own clock. A day is reported once and
  never revised, and Google is often still processing it — so a morning report
  is a provisional reading of yesterday, and the same day can show larger
  figures in Google later.
- Searches are counted only when a person made them in a browser: a crawler
  fetching the results page counts for nothing. A search is one row, keeps no
  personal data (no address, device or cookie), and is forgotten after the
  retention period; the report shows the five most frequent queries of
  yesterday and of the last 28 days per level, and a day with no searches adds
  nothing to the message.
- A blog reader is asked their city only when they tap a link to the services
  list, to a service or to the contacts page on a blog page — never when a page
  opens, never on another page — and at most once every 90 days per device.
  Closing the question without choosing means "stay on this site" until the
  browser is closed.
- The chosen city decides where those taps lead: the same page, in the same
  language, on the chosen city's site; a service the chosen city's site does not
  have leads to that site's services list. The header city switcher changes the
  chosen city.
- Every address shows its own city to everyone, search engines included: the
  chosen city never changes what a page shows, only where a tap on the blog
  leads.

## Roles
| Role | Can do |
|---|---|
| Visitor (pet owner) | browse services and prices per city, team, vacancies; submit contact, franchise, vacancy and blood-donor forms; switch language and city |
| Editor / administrator (clinic staff) | build pages from blocks, edit services and prices, team, vacancies, contacts/settings, translations |
| Customer support specialist | see and process conversations, applications and questionnaires; nothing else in the admin |
| Staff in the Telegram bot | receive a message for every new record after joining the bot with the password |
| Developer (Syndicode) | code, deploys, translation CLI |
| Owner in Telegram (report recipient) | receives the daily report of each city site; reads, does not configure |

## Explicitly out of scope (v1)
- Online booking, patient records, payments — the site informs and collects
  requests; it is not a clinic management system.
- The former learning/testing module (sign-in, sign-up, tests, chapters,
  "learning plan") — its remnants in the theme are not part of the product
  (see DECISIONS.md open questions).
