=== Google Analytics -> Telegram bridge ===
Contributors: dovira
Tags: analytics, google analytics, ga4, telegram, reports
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sends a short daily Google Analytics 4 report to one Telegram chat.

== Description ==

The plugin reads one GA4 property through the Google Analytics Data API once a
day and sends a short report to a single Telegram chat, so that someone who never
opens Google Analytics still sees whether people come to the site and from where.

The message is one screenful:

* **Visitors** -- yesterday against the average of the seven days before it, and
  the last 28 days against the 28 before those. Always sent.
* **Top 5 pages** yesterday, and over 28 days, each named by the title of its
  post on the site (or by its path, when no post lives there).
* **Traffic sources**, **cities** and **devices** over 28 days, as shares.

Every block except visitors can be switched off, and a block with nothing in it
is left out rather than sent empty.

What it needs: a GA4 property you can grant read access to, a Telegram bot, one
chat, WordPress 7.1 or newer, PHP 8.1 or newer with the OpenSSL extension (the
plugin refuses to activate without it), and outbound HTTPS from the site to
`oauth2.googleapis.com`, `analyticsdata.googleapis.com` and `api.telegram.org`.

What it does not do: it talks to Google and to Telegram and to nobody else, it
never makes a request from a visitor's page view, it creates no database tables,
and it never shows or logs the service-account key or the bot token.

== Installation ==

Setting this up takes about fifteen minutes, most of it in Google Cloud. Do the
two accounts first and the WordPress screen last.

= 1. Google: let this site read your property =

1. Open the [Google Cloud console](https://console.cloud.google.com/) and pick a
   project, or create one. Any project will do; it is only a container for the
   credential.
2. **APIs & Services -> Library**, search for **Google Analytics Data API**,
   press *Enable*. That is the only Google API this plugin uses -- the Analytics
   Admin API is deliberately not needed, so there is nothing else to switch on.
3. **APIs & Services -> Credentials -> Create credentials -> Service account**.
   Give it a name; it needs no project role at all.
4. Open the service account, **Keys -> Add key -> Create new key -> JSON**. The
   file downloads once. Keep it somewhere safe -- the plugin needs its whole
   contents, and Google will not show it again.
5. Copy the service account's e-mail address. It looks like
   `something@your-project.iam.gserviceaccount.com`.
6. In **Google Analytics -> Admin -> Property access management**, add that
   address with the **Viewer** role on the property you want reported.
7. In **Admin -> Property settings**, copy the **property id**: a number such as
   `533779496`. It is *not* the measurement id `G-XXXXXXXXXX`, which this plugin
   cannot use.

= 2. Telegram: a bot and a chat =

1. Write to [@BotFather](https://t.me/BotFather), send `/newbot`, answer its
   questions, and keep the token it gives you. It looks like
   `123456789:AAExampleExampleExampleExampleExample`.
2. Decide who receives the report:
   * **a person** -- that person has to write to the bot once, or Telegram will
     not let the bot message them;
   * **a group or channel** -- add the bot to it *as an administrator*, or it may
     not post there.
3. Find the numeric chat id: post any message into that chat, then open
   `https://api.telegram.org/botYOUR_TOKEN/getUpdates` in a browser, with your own
   token in place of `YOUR_TOKEN`, and read `"chat":{"id":...}`. A person's id is
   positive; a group or channel id begins with a minus, usually `-100...`.
   That URL contains your token -- do not paste it into a chat, an issue or a
   screenshot.

= 3. The settings screen =

Install and activate the plugin, then open **Settings -> GA -> Telegram**
(administrators only) and fill in the property id, the whole key file, the bot
token and the chat id. Set the time the report should go out at, and how many
attempts a day is given. Save.

A field that is filled in wrongly is refused on its own: it keeps its previous
value, the rest of the form is saved, and the reason appears above the form.

Under the form, **Connection** has four buttons, and this is the order to press
them in:

1. *Check GA* asks Google whether this site can read the configured property, and
   reports the property's reporting time zone -- the zone that decides which day
   the report calls "yesterday". It sends nothing to Telegram.
2. *Check Telegram* really posts a short test message into the chat, which is the
   only way to prove the bot may write there. Open the chat and look.
3. *Preview* reads yesterday from Google, builds the report and prints the message
   under the buttons exactly as Telegram would receive it. It sends nothing and
   stores nothing.
4. *Send now* builds the same report and sends it, even if today's report has
   already gone out.

No button ever shows the key or the token.

**Run log**, under those buttons, lists the last 30 runs, newest first: when it
ran, what started it, which day the report was about, whether it was sent or
failed, which attempt it was, and one line of detail.

= 4. Keep the two secrets out of the database (recommended) =

Anything stored in the settings travels in every database dump and backup. Both
secrets can instead be defined in `wp-config.php`:

`define( 'GATB_GA_SERVICE_ACCOUNT_JSON', '{ ... the whole key file ... }' );`
`define( 'GATB_TELEGRAM_BOT_TOKEN', '123456789:AA...' );`

Where a constant is defined, the matching field is shown read-only, anything
typed into it is ignored, and the value never reaches the database.

= 5. Make sure something runs WordPress's scheduler =

The report goes out by itself once a day at the time set under **Schedule**, and
WordPress runs it through WP-Cron, which fires on site traffic. A site nobody
visits in the morning gets its report late.

Where `DISABLE_WP_CRON` is defined in `wp-config.php`, WordPress runs nothing by
itself and a system cron has to call `wp-cron.php` instead. On the server:

`*/15 * * * * wget -q -O - https://example.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1`

Use the site's own address, and an interval finer than the delay you are willing
to accept on the send time. The settings screen says so itself when
`DISABLE_WP_CRON` is set and nothing has called `wp-cron.php` for a day.

== Frequently Asked Questions ==

= Which day does the report cover, and how fresh are the numbers? =

Yesterday -- in the **property's** own reporting time zone, not the site's. Every
range the plugin asks for is relative (`yesterday`, `28daysAgo`), so Google
decides where the day starts; *Check GA* names that time zone. The send time, by
contrast, follows the site's clock.

Google is often still processing that day when the report goes out. Google's own
documentation says data processing "can take 24-48 hours" and that reports may
change during it, with a standard property's daily data usually complete some
hours into the following afternoon. This plugin sends a day once and never
revises it, so an early-morning report is a provisional reading: the same day can
show higher figures in Google later on. If that matters more than having the
number at breakfast, set the send time to the late afternoon.

= The report did not arrive this morning. What now? =

Look at **Run log** on the settings screen. If a run is there and failed, the row
says why. If there is no run at all, nothing triggered it: check the **Next run**
line under Schedule, and whether anything is calling `wp-cron.php` (step 5 of
Installation).

A scheduled report that Google or Telegram refuses is tried again an hour later,
as many times as **Maximum attempts** allows. After the last attempt a short
notice goes to the same chat saying that the day could not be reported, and the
reason stays in the run log. A day is never sent twice, and a report sent by hand
with *Send now* is not retried -- the reason is on the screen, and the button is
there to press again.

= Can it read two properties, or send to two chats? =

No. One property, one chat, one message a day. Two sites mean two installs, each
with its own property and its own chat.

= Where are the key and the token kept? =

In the options table, unless they are defined as constants in `wp-config.php`
(step 4 of Installation), which is the better place. Neither is ever printed on a
screen, written to the run log or included in an error message: the bot token
travels in the request URL, so even a transport error that quotes that URL back
has the token removed from it first.

= What happens when I deactivate the plugin? And when I delete it? =

Deactivating takes away only the schedule: the settings, the run log and the
record of what has been sent all survive, and activating again resumes where it
left off. Deleting the plugin removes everything it owns -- the three options, the
cached Google token and both scheduled events -- and leaves no `gatb_` row behind.

= Does anything go anywhere else? =

No. The site talks to `oauth2.googleapis.com` and `analyticsdata.googleapis.com`
to read the report, and to `api.telegram.org` to send it. There is no third-party
service, no telemetry, and no request made from a visitor's page view.

== Changelog ==

= 0.1.0 =
* The daily report -- visitors, top pages, traffic sources, cities and devices --
  read from one GA4 property through the Data API and sent to one Telegram chat.
* Settings screen with per-field validation, the two secrets overridable by
  `wp-config.php` constants, and the buttons *Check GA*, *Check Telegram*,
  *Preview* and *Send now*.
* A daily schedule at a configured local time, retries an hour apart up to a
  configured number of attempts, and a notice to the chat when a day is given up.
* Run log of the last 30 runs, and a guard that never sends a day twice.
* Ukrainian translation; everything the plugin owns is removed when it is deleted.
