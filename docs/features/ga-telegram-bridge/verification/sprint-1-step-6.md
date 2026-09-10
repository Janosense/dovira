# Verification — ga-telegram-bridge, Sprint 1, Step 6

**ReportBuilder** · written at close on 2026-09-09
Branch merged: `ga-telegram-bridge/sprint-1-report-on-demand` → `master`

What this step promises: the plugin can turn the configured GA4 property into the
numbers the daily message is made of — visitors and their two comparisons, top
pages twice, traffic sources, cities and devices — asking Google for no more than
it needs and never more than twice.

Nothing is rendered and nothing is sent: this step produces data, not a message.
The message is Step 7 and sending it is Step 8, so everything below is read from a
`wp eval` rather than from a screen.

Shell commands are run from the repo root. No Telegram bot is needed.

## 0. Configure the property on the settings screen

Open **<https://dovira.ddev.site/wp-admin/options-general.php?page=gatb-settings>**
and fill in the *Google Analytics* section the way any administrator would:

* **Property id** — `533779496`
* **Service-account key** — the contents of
  `wp-content/plugins/ga-telegram-bridge/spike/service-acount.json` (the key from
  the Sprint 1 spike; the filename really is missing a "c")

Save, then press **Check GA** to confirm the connection before reading anything
into the report. It should answer *"Google answered for property 533779496. Its
reporting time zone is Europe/Kiev"*.

Both values go into the database, which is where the plugin keeps them. `*.sql` is
gitignored, so a dump can never carry them into the repository. §7 clears them
again when you are done.

If you would rather verify the way the two production installs are configured,
define the key in `wp-config.php` instead — `define( 'GATB_GA_SERVICE_ACCOUNT_JSON',
file_get_contents( __DIR__ . '/wp-content/plugins/ga-telegram-bridge/spike/service-acount.json' ) );`
— and the *Service-account key* field turns read-only and says *Set in
configuration*. Everything below works the same either way.

## 1. The whole report, from the real property

```bash
ddev wp eval 'echo json_encode( GaTelegramBridge\ReportBuilder::build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";'
```

Expected: one JSON object beginning like this (the numbers move every day; the
shape does not) —

```json
{
    "date": "2026-09-08",
    "time_zone": "Europe/Kiev",
    "visitors_yesterday": 69,
    "visitors_average_7_days": 56.285714285714285,
    "visitors_28_days": 1560,
    "visitors_previous_28_days": 1577,
    "visitors_change_vs_average": 23,
    "visitors_change_28_days": -1,
    "pages_yesterday": [ … five entries, each with title, path and views … ],
    …
}
```

**This is the number to check against Google.** Open GA4 → Reports → Reports
snapshot for the same property, set the date range to yesterday, and compare
`visitors_yesterday` with the **Users** figure. They must be the same number: the
report asks for `activeUsers`, which is what the GA4 app labels "Users"
(DECISIONS "Report content and comparison baselines").

`visitors_change_vs_average` is yesterday against the average of the seven days
before it — 69 against 56.3 is +23 %. That average divides by **7** even if some of
those days had no visitors at all, which is the point: a quiet week should make
yesterday look busy.

## 2. The shares, in a form you can read

```bash
ddev wp eval '$r = GaTelegramBridge\ReportBuilder::build(); foreach ( array( "channels", "cities", "devices" ) as $b ) { echo strtoupper( $b ) . ": "; foreach ( $r->$b as $row ) { echo $row["label"] . " " . $row["share"] . "% (" . $row["value"] . ")  "; } echo "\n"; }'
```

Expected, in the shape of:

```
CHANNELS: Organic Search 70% (1757)  Direct 16% (399)  Referral 7% (173)  …
CITIES: Kharkiv 52% (623)  Kyiv 30% (360)  Dnipro 11% (136)  Lviv 7% (78)
DEVICES: mobile 80% (1248)  desktop 19% (304)  tablet 1% (10)
```

Two things worth noticing rather than glossing over. The percentages of a block
**need not add up to exactly 100** — each row is rounded on its own, so three equal
thirds print as 33, 33 and 33. And the cities list has **four** rows although the
request asks for five: see the next check.

## 3. Negative check — a city GA cannot place is dropped, not shown as "(not set)"

```bash
ddev wp eval '$r = GaTelegramBridge\ReportBuilder::build(); echo implode( ", ", array_column( $r->cities, "label" ) ) . "\n"; echo ( in_array( "(not set)", array_column( $r->cities, "label" ), true ) ? "(not set) IS BEING SHOWN — STOP\n" : "no (not set) row (good)\n" ); echo "share of the first city: " . $r->cities[0]["share"] . "%\n";'
```

Expected: `no (not set) row (good)`. GA really does return such a row for this
property — around 114 users of about 1311 in the recorded month — and it is dropped
**before** the shares are worked out, so the remaining percentages are shares of the
cities actually listed (623 of 1197, not of 1311). A share of a total that includes a
row you removed would be wrong in a way nobody would ever notice.

## 4. Negative check — the four periods are not read in row order

This is the one that would go wrong silently, so it is worth seeing for yourself.
Ask Google directly and look at the order the rows arrive in:

```bash
ddev wp eval '$body = GaTelegramBridge\GaClient::batch_run_reports( array( GaTelegramBridge\ReportBuilder::requests( array( "visitors" => true ) )["visitors"] ) ); foreach ( $body[0]["rows"] as $row ) { echo $row["dimensionValues"][0]["value"] . " = " . $row["metricValues"][0]["value"] . "\n"; }'
```

Expected: the rows come back **ordered by their number, largest first** — something
like

```
date_range_3 = 1577
date_range_2 = 1560
date_range_1 = 394
date_range_0 = 69
```

`date_range_0` is yesterday and it is *last*, because GA orders rows by the metric
and not by the order the ranges were asked for. Compare the numbers with §1:
`visitors_yesterday` must equal the `date_range_0` line (69 here), **not** the first
one. If a future change ever reads these rows by position, the report will announce
the previous 28 days as yesterday and look entirely plausible while doing it.

## 5. Negative check — a switched-off block asks Google for nothing

On the settings screen, in **Report blocks**, untick *Top pages* and save. Then:

```bash
ddev wp eval '$r = GaTelegramBridge\ReportBuilder::build(); echo "pages_yesterday: " . var_export( $r->pages_yesterday, true ) . "\n"; echo "cities still there: " . count( $r->cities ) . " rows\n";'
```

Expected: `pages_yesterday: NULL` — switched off, so the block is absent from the
report entirely, as opposed to `array ()`, which would mean switched on with nothing
to report. Tick *Top pages* back on and save.

The requests behind that can be seen without touching Google at all:

```bash
ddev wp eval '$off = array( "visitors" => true, "pages" => false, "channels" => false, "cities" => false, "devices" => false ); $r = GaTelegramBridge\ReportBuilder::requests( $off ); echo count( $r ) . " request(s): " . implode( ", ", array_keys( $r ) ) . "\n"; echo ( false === strpos( json_encode( $r ), "pagePath" ) && false === strpos( json_encode( $r ), "deviceCategory" ) ? "no disabled block is mentioned (good)\n" : "A DISABLED BLOCK IS STILL ASKED FOR — STOP\n" ); $all = GaTelegramBridge\ReportBuilder::requests( array_fill_keys( GaTelegramBridge\Settings::BLOCKS, true ) ); echo count( $all ) . " requests with everything on, in " . count( array_chunk( $all, 5 ) ) . " call(s)\n";'
```

Expected:

```
1 request(s): visitors
no disabled block is mentioned (good)
6 requests with everything on, in 2 call(s)
```

Six reports cannot be one call: the Data API accepts at most five requests per
`batchRunReports`. That is where the plugin's "never more than two calls a run"
comes from — it is a limit, not a preference.

## 6. Negative check — the day is the property's day, not the server's

```bash
ddev wp eval 'echo "Kyiv:   " . GaTelegramBridge\ReportBuilder::report_date( "Europe/Kiev", 1757536200 ) . " / " . GaTelegramBridge\ReportBuilder::report_date( "Europe/Kiev", 1757539800 ) . "\n"; echo "UTC:    " . GaTelegramBridge\ReportBuilder::report_date( "UTC", 1757539800 ) . "\n";'
```

Expected:

```
Kyiv:   2025-09-09 / 2025-09-10
UTC:    2025-09-09
```

The two Kyiv answers are one hour apart and land on different days, because the
second one is past midnight in Kyiv. The UTC line shows what a server-clock report
would have said at that same instant — a day behind. Which day "yesterday" means is
the property's business, and the plugin reads the time zone out of Google's own
answer to be sure of it.

## 7. Put the site back

```bash
ddev wp eval 'delete_option( "gatb_settings" ); add_option( "gatb_settings", GaTelegramBridge\Settings::defaults(), "", false );'
```

That clears the property id and the key from the database along with everything
else. If you used the `wp-config.php` constant instead of the form, delete that line
too.

## 8. The gate is green

```bash
bash bin/check.sh
```

Expected: `==> check: all green`, with `OK (163 tests, 532 assertions)`.

Among them, one test walks **all sixteen** on/off combinations of the four optional
blocks and checks both halves of the promise: never a third call to Google, and the
report carrying exactly the blocks that were switched on.
