# Domain — Dovira

<!-- playbook: v3.0.4. State document: the clinic's world in its own words,
     present tense; a rule that changes is rewritten. Read Glossary and
     Lifecycles whole, Rules by area. CLAUDE.md → Domain invariants copies a
     selection of Rules verbatim: change a rule here first, then there, in
     the same commit. Shape, triggers and what does not belong here:
     docs/CONTRACTS.md → `### docs/DOMAIN.md`. -->

## Glossary
| Term | Meaning | In code |
|---|---|---|
| Клініка «Довіра» (Dovira) | The veterinary clinic: one brand, two independent locations. | — |
| Філія / місто (city) | A clinic location with its own site, prices, team, vacancies and contacts: Харків (`dovira.vet`) and Київ (`kyiv.dovira.vet`). | `service-city` |
| Послуга (service) | A category of veterinary work shown as one page with a price list. | `service` |
| Підпослуга (sub-service) | A service page without a price list, usually a child of a service. | template `templates/sub-service.php` |
| Прайс / вартість (price list) | The priced items of a service, each priced for the cities it is offered in. | `service` → `prices` |
| Загальна ціна (general price) | One price valid in every city the item is offered in. | `is_price_general` |
| Ціна по місту (city price) | A price of one item in one city, different between cities. | `city_prices` |
| Команда / співробітник (employee) | A vet or staff member of a city shown on the team page. | `employee` |
| Вакансія (vacancy) | A job offer of a city, open or closed, with a fixed salary, a "from" salary or a range. | `vacancy` |
| Заявка (application) | A candidate's response to a vacancy, with contacts and a CV. | `application` |
| Звернення (conversation) | A message from the contact form or the franchise form, handled by support. | `conversation` |
| Франшиза (franchise) | A prospective partner's request to open a Dovira location, recorded as a conversation titled «Франшиза: …». | `conversation` (form 1430) |
| Анкета донора / Банк крові (blood-donor questionnaire) | A pet owner's offer of their dog or cat as a blood donor, with the owner, the pet, the blood group and health answers. | `questionary` |
| Опрацьовано (processed) | A conversation or application that support has handled, with the time it was processed. | `is_processed`, `processing_date` |
| Відповідальні (responsible persons) | Every staff member who opened a record, collected automatically. | `responsible_persons` |
| Спеціаліст підтримки (customer support specialist) | The staff role that works only with conversations, applications and questionnaires. | `customer_support_specialist` |
| Налаштування → Контакти (contacts) | Each city's address, phones, clinic and pet-shop schedules, map, e-mail and Instagram, and the global social links. | options page `acf-options-settings` |
| Мова (language) | Ukrainian is the original; Russian pages are its translations. | Polylang |
| Блог (blog) | The clinic's articles, published only on the Kharkiv site at `/news/` (ru `/ru/blog/`), read from all of Ukraine. | `post`, page `news` |
| Щоденний звіт (daily report) | One Telegram message per day per city site that tells the owner how many people visited, what they read, where they came from, from which cities, on which devices and what they searched for. | plugin `ga-telegram-bridge` |
| Відвідувачі (visitors) | Distinct people who engaged with the site in a period — GA4 "active users", the "Users" figure of the GA4 app. | `activeUsers` |
| Динаміка (dynamics) | The change in % of yesterday against the average of the previous 7 days, and of the last 28 days against the previous 28. | `Dynamics` |
| Блок звіту (report block) | One section of the daily report: visitors, top pages yesterday, top pages over 28 days, traffic sources, cities, devices, and the blocks other parts of the site add. | `gatb_settings.blocks` |
| Тренд (trend) | The shape of daily visitors over the last 28 days, drawn as 28 block characters scaled between the quietest and the busiest day, with those two numbers beside it. | `Sparkline` |
| Одержувач звіту (report recipient) | The one Telegram chat — a channel, a group topic or a person — a site's report goes to. | `telegram_chat_id` |
| Пошук по сайту (site search) | The three ways a visitor searches a city site: the header search, the filter of the services list, and the filter of a service's price list. | `search-stats` |
| Рівень пошуку (search level) | Which of the three searches a query came from: `site`, `services` or `service`. | `level` |
| Пошуковий запит (search query) | What a visitor looked for once they stopped typing, normalized so that case and spacing do not make two queries. | `query_text` |
| Нічого не знайдено (nothing found) | A header-search query whose results page showed no results every time it was searched in the period. | `TopQuery::$nothing_found` |
| Питання міста (city question) | The window «Яке місто вас цікавить?» with «Харків» and «Київ» that a blog reader sees when they tap a link to the services list, a service or the contacts page. | `city-popup` |
| Обране місто (chosen city) | The city a visitor picked in the city question or in the header city switcher, remembered on their device for both city sites. | cookie `dovira_city` |
| Конектор (content connector) | The way the owner, the content manager and the developer manage a city site's content from Claude in their own words, each as their own site user. | `content-mcp` |
| Перегляд змін (preview) | What the connector shows before it writes: which post, in which language, which field, from what to what. | `Diff` |
| Сеанс конектора (connector session) | One connection of one person's Claude to one city site. | `Mcp-Session-Id` |
| Дамп (session dump) | A full copy of the site's database the connector saves before a session's first change, so the developer can undo the session by hand. | `Dump` |

## Lifecycles

### application
| State | How it is entered | What is allowed in it |
|---|---|---|
| `new` | a candidate submits the vacancy form | support reads it and moves it on |
| `in_processing` | support sets it | contacting the candidate |
| `interview_scheduled` | support sets it | the interview |
| `rejected` | support sets it | nothing further |
| `closed` | support sets it | nothing further |

### conversation
| State | How it is entered | What is allowed in it |
|---|---|---|
| new (`is_processed` = 0) | a visitor submits the contact or franchise form | support handles it |
| processed (`is_processed` = 1) | support, or Dovira Bridge, marks it | it may be marked new again |

### report day
| State | How it is entered | What is allowed in it |
|---|---|---|
| unsent | the day ends in the property's calendar | the schedule tries it, then retries hourly while attempts remain |
| sent | Telegram accepts the day's report | only the admin's *Send now* sends it again |
| given up | its last attempt fails and the failure notice goes to the chat | nothing — the next day starts afresh |

## Rules

### Cities and content
- Each city site serves its own city: what an address shows never depends on who opens it or what they chose before.
- A service, an employee and a vacancy belong to one or more cities, and a city site shows only its own.
- Staff build pages only from the clinic's own blocks; a blog article may also use WordPress's standard blocks.
- Ukrainian is the source of every text; a Russian page says what the Ukrainian page says and is not indexed by search engines.
- City names are one list shared by both languages; only their spelling is translated.

### Prices
- A priced item has either one general price or one price per city, never both.
- A price row is shown only in the cities ticked for it.
- A city without a price for an item shows that city's phone from the contacts instead of an amount, never a blank or an invented price.
- Staff change prices in the admin and a change takes effect on publish.

### Forms and records
- A form submission is saved as a record before any Telegram message is sent, and a Telegram failure never loses it.
- A form submission missing a required answer is refused and leaves no record.
- Only an open vacancy accepts applications.
- A blood-donor questionnaire requires the owner's name and phone, the animal, its sex, age and weight, the vaccination date and every yes/no health answer; e-mail and the pet's name are optional.
- Records are created only by visitors' forms, never by staff or the connector.

### Daily report
- A daily report covers one calendar day of the site's own GA4 property and is delivered at most once per day, except when an administrator sends it by hand.
- A day whose report cannot be built or delivered is tried again a configured number of times and then announced as missing to the same chat, never skipped silently and never filled with guesses.
- The report's periods follow Google Analytics' calendar for the property, and the delivery time follows the site's own clock.
- A reported day is never revised, so a morning report is a provisional reading that Google may later show larger.
- The visitors block is always in the report; the others are switched on and off by the site's administrator.
- A report never loses one of its own blocks to Telegram's length limit; blocks added by other parts of the site are dropped from the last one back instead.
- The trend shows the shape of the four weeks, never a total, because daily visitors do not add up to the 28-day figure.

### Site search
- A search counts only when a person made it in a browser; a crawler fetching a results page counts for nothing.
- A search is one row that keeps no personal data — no address, device, cookie or user — and is forgotten after the retention period, never sooner than 29 days.
- A filter query needs at least three letters and is the pair of its text and the page it was typed on.
- Spellings the database treats as one letter («ґ» and «г», «ё» and «е») are one query in the report.
- The report shows, per level, the five most frequent queries of yesterday and of the last 28 days, and a level nobody searched adds no block.
- Only the header search can show "nothing found", and one search in the period that found something clears it.

### City question
- A blog reader is asked their city only when they tap a link to the services list, a service or the contacts page on a blog page — never when a page opens, never on another page.
- The question is asked at most once every 90 days per device; closing it without choosing means "stay on this site" until the browser is closed.
- The chosen city decides where those taps lead: the same page, in the same language, on the chosen city's site.
- A service the chosen city's site does not have leads to that site's services list.
- The header city switcher changes the chosen city.

### Content connector
- The connector writes only what the person saw in a preview and agreed to, in every language.
- Before a session's first change the connector saves a dump of the database, and if it cannot, it changes nothing.
- A dump is never overwritten and is kept for 90 days.
- Prices are the same in every language: a price is changed in the Ukrainian copy and follows into the Russian copy by itself.
- Russian wording is changed on the Russian copy.
- A service created through the connector gets one price per row for this site's city; prices for the other city are added in the admin.
- A Russian copy is made through the connector only from a Ukrainian post that has none, and whatever the agent leaves untranslated stays Ukrainian on the copy until it is translated.
- Through the connector administrators and editors edit the same content, and only administrators delete, only to the trash.
- Through the connector records are read, never changed, and site settings, users and city terms are not touched.

## Roles
| Role | Can do |
|---|---|
| Visitor (pet owner) | browse services and prices, the team and vacancies of a city; submit the contact, franchise, vacancy and blood-donor forms; switch language and city |
| Editor / administrator (clinic staff) | build pages from blocks; edit services and prices, team, vacancies, contacts and translations |
| Customer support specialist | see and process conversations, applications and questionnaires, and nothing else in the admin |
| Staff in the Telegram group | receive a message for every new record in the group topic the site is configured with |
| Developer (Syndicode) | code, deploys, the translation commands |
| Connector user (owner — administrator, content manager — editor, developer — administrator) | manage content from Claude as their own site user; administrators also move posts to the trash |
| Dovira Bridge (Dovira Pro) | read conversations and mark them processed or new, as a user with the support capabilities |
| Owner in Telegram (report recipient) | receive the daily report of each city site |
