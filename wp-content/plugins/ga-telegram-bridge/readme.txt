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
* **Schedule** — send time and how often a failed report is retried. Stored
  now; nothing runs on a schedule until the next release.
* **Report blocks** — which parts the message contains. Visitors is always sent.

A field that is filled in wrongly is refused on its own: it keeps its previous
value, the rest of the form is saved, and the reason appears above the form.

The two secrets may instead be defined in `wp-config.php`, which keeps them out
of the database and out of any database dump:

`define( 'GATB_GA_SERVICE_ACCOUNT_JSON', '{ ... the key file ... }' );`
`define( 'GATB_TELEGRAM_BOT_TOKEN', '123456789:AA...' );`

Where a constant is defined, the matching field is shown read-only and anything
typed into it is ignored.

Under the settings form, **Connection** holds two buttons. *Check GA* asks Google
whether this site can read the configured property and reports the property's
reporting time zone — the time zone that decides which day the report calls
"yesterday". *Check Telegram* really posts a short test message into the
configured chat, which is the only way to prove the bot may write there; open
the chat to see it arrive. Neither button ever shows the key or the token, and
neither is needed again once both answer.

Previewing the report and sending it are added in the following releases.

== Changelog ==

= 0.1.0 =
* Plugin skeleton: autoloader, activation requirements check, translations.
