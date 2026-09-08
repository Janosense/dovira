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

Configuration, scheduling and the run log are added in the following releases.

== Changelog ==

= 0.1.0 =
* Plugin skeleton: autoloader, activation requirements check, translations.
