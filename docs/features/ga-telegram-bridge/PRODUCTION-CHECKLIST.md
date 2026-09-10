# Pre-flight checklist — ga-telegram-bridge on the two Dovira installs

Written for Sprint 2 Step 4. It is worked through **once per install**, and the
two installs are independent: Kharkiv `dovira.vet` (deployed by hand from
`master`) and Kyiv `kyiv.dovira.vet` (from `kyiv`). Each has its own GA4
property, its own chat and its own copy of the settings — nothing about a city
lives in the code, which is why the same plugin serves both.

The deploy itself is the sprint boundary's, not a step's (root `CLAUDE.md` → git
model). This checklist starts the moment the files are on the server.

The screen is **Settings → GA → Telegram**
(`/wp-admin/options-general.php?page=gatb-settings`), and it is in Ukrainian on
both installs. `readme.txt` in the plugin directory explains every field; the
screen links to the relevant part of it from each section.

> **Never print the settings row.** `wp option get gatb_settings` puts the
> service-account key and the bot token into a terminal, a scrollback and a
> shell history at once. Read single fields instead, or just look at the screen.
> Never paste a key, a token or a `getUpdates` URL into a chat, an issue or a
> screenshot.

---

## A. Before the deploy — what to have ready

Nothing here needs the site; all of it is in the Google and Telegram accounts.
Both installs need their own answers.

- [ ] **A1. The numeric property id of each property.** Google Analytics →
      Admin → Property settings. It is a number like `533779496`. The
      measurement id `G-…` is *not* it and the plugin cannot use it — but it is
      what tells the two properties apart: `G-HKYZFG0E2W` is the Kharkiv
      property and `G-Q597WTF16L` is the Kyiv one (`docs/ARCHITECTURE.md` →
      Integrations). Open each property's web data stream and read the
      measurement id there to be sure which id belongs to which city.
- [ ] **A2. A service account with a JSON key**, created once in Google Cloud
      with the **Google Analytics Data API** enabled in that project (readme
      §1). One service account may serve both installs, or each may have its
      own; whichever you choose, the key file is pasted into that install and
      nowhere else.
- [ ] **A3. Viewer on both properties** for that service account's e-mail
      address (`…@….iam.gserviceaccount.com`), in Google Analytics → Admin →
      Property access management. Viewer is enough; the plugin only reads.
- [ ] **A4. One chat per install, and its id.** The Kharkiv report and the Kyiv
      report go to different chats, or the owner cannot tell them apart. A
      channel needs the bot added **as an administrator**; a person has to have
      written to the bot once. The chat id is read as readme §2 describes — a
      person's is positive, a channel's begins with `-100`.
- [ ] **A5. The send time, agreed with the owner**, and the same for the block
      set (visitors, top pages yesterday, top pages 28 days, sources, cities,
      devices). The time is that install's own local time. If the owner asks for
      something other than the default 09:00 and all blocks, write it into
      `docs/DECISIONS.md` — the composition of the report is a business value,
      not a constant (root `CLAUDE.md` → core rule 6).
- [ ] **A6. Each install's WordPress time zone is right** (Settings → General).
      The send time follows the site's clock; which day the report is *about*
      follows the property's, and *Check GA* names that zone in B4.

## B. At the deploy — per install, in this order

- [ ] **B1. The plugin directory is on the server**:
      `wp-content/plugins/ga-telegram-bridge/`. There is nothing to build and
      nothing to install — the plugin has zero runtime dependencies, so the
      files are all of it.
- [ ] **B2. The two secrets go into that install's `wp-config.php`**, above the
      "That's all, stop editing" line:

      define( 'GATB_GA_SERVICE_ACCOUNT_JSON', '{ … the whole key file … }' );
      define( 'GATB_TELEGRAM_BOT_TOKEN', '123456789:AA…' );

      This is where they belong: anything in the settings travels in every
      database dump and backup (`docs/DECISIONS.md` → "Plugin structure, storage
      and secrets"). `wp-config.php` is gitignored on every install and is never
      committed. Mind the quoting: the JSON contains double quotes, so the
      constant is written in single quotes as above.
- [ ] **B3. Activate the plugin.** If the host has no OpenSSL the plugin refuses
      to activate and says so — that is the `openssl` check, and there is nothing
      else to test for it. Ask the host to enable the extension.
- [ ] **B4. Fill in the rest and save**: the property id from A1, the chat id
      from A4, the send time from A5, the blocks, the maximum number of
      attempts. The two secret fields must show as read-only with "set in
      configuration" — that is B2 proving itself. Anything typed into them is
      ignored.
- [ ] **B5. *Перевірити GA*.** It must answer with **that city's** property id
      and its reporting time zone (`Europe/Kiev` for both Dovira properties). A
      refusal names its own reason: a property the service account cannot read
      (A3), a mistyped id, a key Google no longer knows.
- [ ] **B6. *Перевірити Telegram*.** A short test message must arrive **in that
      city's chat**. Open the chat and look — this is the only way to prove the
      bot may write there, and the only way to catch the two installs pointing
      at the same chat.
- [ ] **B7. *Попередній перегляд***, to read the message before anyone else
      does: yesterday's numbers, the blocks the owner asked for, the site host in
      the heading.
- [ ] **B8. *Надіслати зараз***, once, for the first real report. The run log
      row must read **Вручну · Надіслано · Спроба 1**. A day is never sent twice,
      but tomorrow is a different day, so this does not cost the first scheduled
      report.

## C. The host requirements prove themselves in B

No probe file is uploaded to a production server: the plugin's own buttons make
exactly the calls a real run makes.

- **B5 passing** proves outbound HTTPS to `oauth2.googleapis.com` **and**
  `analyticsdata.googleapis.com`, and that OpenSSL signed the request — the
  service-account JWT cannot be built without it.
- **B6 passing** proves outbound HTTPS to `api.telegram.org`.
- **B3 passing** proves the OpenSSL extension on its own.

- [ ] **C1.** If a check says the site cannot reach the service at all, it is the
      host: ask them to allow outbound HTTPS (port 443) to those three names.
      Everything else a check reports is configuration, and the message says
      which.

## D. The schedule

- [ ] **D1. Under Розклад, "Наступний запуск" names the agreed time**, in that
      install's local clock. If it says the report is not scheduled yet, save the
      settings once more — that is what registers the event.
- [ ] **D2. Something runs WP-Cron.** WordPress fires it on site traffic, so a
      quiet morning delays the report. If that install's `wp-config.php` defines
      `DISABLE_WP_CRON`, a system cron must call `wp-cron.php` instead (readme
      §5) — the screen prints a warning when nothing has called it for a day, and
      that warning must be gone the next day.

## E. The two mornings — the sprint's Definition of Done

- [ ] **E1.** On each install, on two consecutive days, the newest run log row
      reads **Розклад · Надіслано · Спроба 1** for the day before, and nobody
      pressed anything.
- [ ] **E2.** The owner confirms both messages arrived, in the right chat, at a
      reasonable hour.
- [ ] **E3.** Anything that failed instead is in the same log with its reason,
      and the chat has the short "звіт не сформовано" notice after the last
      attempt. A failure here is a `/fix-step` on the step it belongs to, not a
      note for later.

## F. Never

- Never run `wp option get gatb_settings`, dump it, or `print_r` anything under
  it — it holds the key and the token (`docs/LEARNINGS.md`, 2026-09-09).
- Never paste a key, a bot token or an `api.telegram.org/bot…/getUpdates` URL
  into a chat, an issue, a commit or a screenshot.
- Never commit a `wp-config.php`, and never put a Kyiv-only value on `master` —
  the two installs differ only in what is typed into them.

---

**What this file holds and what it does not.** It says where every value is
found and where it is typed in; it carries no property id, no chat id and no
credential of its own. The two measurement ids above are already public in the
page source of each site.
