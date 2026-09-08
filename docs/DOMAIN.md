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
| Щоденний звіт (daily report) | One Telegram message per day per city site, sent by the `ga-telegram-bridge` plugin, that tells the owner how many people visited yesterday and over the last 4 weeks, which pages they read, where they came from, from which cities and on which devices |
| Відвідувачі (visitors) | Distinct people who engaged with the site in a period — GA4 "active users", the same number the owner sees as "Users" in the GA4 app |
| Динаміка (dynamics) | The change in % of yesterday against the average of the previous 7 days, and of the last 28 days against the previous 28 days; ▲ up, ▼ down, — when there is nothing to compare with |
| Блок звіту (report block) | One section of the daily report: visitors, top pages, traffic sources, cities, devices; the owner's site administrator switches blocks on and off, visitors is always on |
| Одержувач звіту (report recipient) | The one Telegram chat — a channel or a person — that a site's report goes to |

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
  announced as missing, never silently skipped and never filled with guesses.
- The report's periods follow Google Analytics' calendar for the property;
  the delivery time follows the site's own clock.

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
