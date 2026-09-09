=== Google Analytics → Telegram bridge ===
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
day and sends a short report — visitors, top pages, traffic sources, cities and
devices — to a single Telegram chat, so that someone who never opens Google
Analytics still sees whether people come to the site and from where.

Requirements: the PHP OpenSSL extension (checked on activation) and outbound
HTTPS access to googleapis.com and api.telegram.org.

== Configuration ==

Settings > GA → Telegram (administrators only) holds everything the plugin needs:

* **Property id** — the numeric id of the GA4 property, from Admin > Property
  settings. Not the measurement id.
* **Service-account key** — the whole JSON key file of a Google Cloud service
  account whose e-mail address has Viewer on that property.
* **Bot token** — the token BotFather issued for the bot.
* **Chat id** — a number; a person's id is positive, a group or channel id
  starts with a minus. To post into a channel, add the bot to it as an
  administrator.
* **Schedule** — the site-local time the daily report goes out at, and how many
  attempts a day is given: a failed one is tried again an hour later, and after
  the last attempt the chat is told that the day could not be reported. The
  section also says when the next run is due.
* **Report blocks** — which parts the message contains. Visitors is always sent.

A field that is filled in wrongly is refused on its own: it keeps its previous
value, the rest of the form is saved, and the reason appears above the form.

The two secrets may instead be defined in `wp-config.php`, which keeps them out
of the database and out of any database dump:

`define( 'GATB_GA_SERVICE_ACCOUNT_JSON', '{ ... the key file ... }' );`
`define( 'GATB_TELEGRAM_BOT_TOKEN', '123456789:AA...' );`

Where a constant is defined, the matching field is shown read-only and anything
typed into it is ignored.

Under the settings form, **Connection** holds four buttons. *Check GA* asks
Google whether this site can read the configured property and reports the
property's reporting time zone — the time zone that decides which day the report
calls "yesterday". *Check Telegram* really posts a short test message into the
configured chat, which is the only way to prove the bot may write there; open
the chat to see it arrive. *Preview* reads yesterday from Google, builds the
report and prints the message underneath the buttons exactly as Telegram would
receive it, tags and all — it sends nothing and stores nothing. *Send now*
builds the same report and sends it, even if today's report has already gone
out. No button ever shows the key or the token.

**Run log** under those buttons lists the last 30 runs, newest first: when it
ran, what started it, which day the report was about, whether it was sent or
failed, which attempt it was and one line of detail. A failed run leaves the day
unsent, so nothing is lost.

The report also goes out by itself, once a day at the time set under Schedule.
WordPress runs it through WP-Cron, which fires on site traffic, so a very quiet
morning can delay it. Where `DISABLE_WP_CRON` is defined in `wp-config.php`, a
system cron has to call `wp-cron.php` instead — the settings screen says so when
nothing has called it for a day.

A scheduled report that Google or Telegram refuses is tried again an hour later,
as many times as **Maximum attempts** allows; after the last one a short notice
goes to the same chat saying that the day could not be reported, and the reason
stays in the run log. A day is never sent twice, and a report sent by hand with
*Send now* is not retried — the reason is on the screen, and the button is
there to press again.

== Changelog ==

= 0.1.0 =
* Plugin skeleton: autoloader, activation requirements check, translations.
