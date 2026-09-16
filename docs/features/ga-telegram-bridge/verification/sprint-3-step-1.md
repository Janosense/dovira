# Verification — ga-telegram-bridge, Sprint 3, Step 1

**Trend data: block `trend`, the daily-visitors request and `Report::visitors_by_day`** · written at close on 2026-09-16
Branch merged: `ga-telegram-bridge/sprint-3-trend` → `master`

What this step promises: the report now **reads** the shape of the last four weeks — one
figure of active users per day for 28 days — and the site administrator can switch that
reading off like any other block. It promises nothing about the message yet: the trend line
appears in Step 2, and this guide checks explicitly that the message has **not** changed.

The screen is
**<https://dovira.ddev.site/wp-admin/options-general.php?page=gatb-settings>**; shell
commands are run from the repo root. §3 reads the live GA property, so it needs the install
configured (it is).

> **Do not take a whole-page screenshot of this screen.** It prints the stored
> service-account key and bot token back into their fields (LEARNINGS 2026-09-10). If you
> want a picture, capture the *Блоки звіту* fieldset alone.

## 1. The stored settings predate the block — and the block is still on

The point of the step: an install configured in Sprint 2 knows nothing about `trend`, and
must not be given it switched off.

```bash
ddev wp eval 'echo implode( ", ", array_keys( (array) ( get_option( "gatb_settings" )["blocks"] ?? array() ) ) ) . "\n";'
```

Expected: **`visitors, pages, channels, cities, devices`** — five keys, written before this
step existed. (If you have saved the settings since the deploy it will already say six; then
this check has nothing left to prove and §2 is where to look.)

```bash
ddev wp eval 'var_export( GaTelegramBridge\Settings::blocks() ); echo "\n";'
```

Expected: **six** keys, and `'trend' => true` — the stored row says nothing about `trend`, so
it takes its default, which is on.

## 2. The sixth checkbox is on the screen

Open the screen and find **Блоки звіту**. Expected, in this order:

| | |
|---|---|
| Відвідувачі | ticked, greyed out, *(завжди надсилається)* |
| Топ сторінок · Джерела трафіку · Міста · Пристрої | as you have them set |
| **Тренд** | **ticked**, changeable, followed by *Спарклайн за 28 днів у блоці «Відвідувачі».* |

The description is the reason the label alone is not enough: every other block is a section of
its own, this one is a line inside another block.

## 3. The data: 28 dated figures, oldest first

```bash
ddev wp eval '$r = GaTelegramBridge\ReportBuilder::build(); foreach ( $r->visitors_by_day as $day => $n ) { echo $day . "  " . $n . "\n"; } echo "--\nsum of the 28 days: " . array_sum( $r->visitors_by_day ) . "\n28-day activeUsers: " . $r->visitors_28_days . "\nreport is about:    " . $r->date . "\n";'
```

Every command in this guide was run on the local install at the close; §3 printed
`days: 28`, `first: 2026-08-19 = 62`, `last: 2026-09-15 = 76`, `min/max: 43 / 77`.

Expected:

1. **exactly 28 lines**, dates ascending, the last one equal to `report is about` — yesterday
   in the property's own time zone, not the server's;
2. every value a whole number, most of them in the 40–80 range on this property;
3. **the sum is larger than the 28-day figure** — on this install when the step was closed,
   1761 against 1520, about 16% more. That is correct and not a bug: someone who visited on three days is
   three active users in the sum and one over the period. The sparkline shows the *shape* of
   the four weeks; the two numbers beside it in Step 2 are the quietest and busiest day,
   never a total.

Cross-check against GA4 if you want: Reports → **Users by day** over the last 28 days should
draw the same shape, day for day. The daily numbers should match; the period total will not
equal their sum there either.

## 4. Two calls, seven reports — the invariant still holds

```bash
ddev wp eval '$q = GaTelegramBridge\ReportBuilder::requests( GaTelegramBridge\Settings::blocks() ); echo count( $q ) . " requests: " . implode( ", ", array_keys( $q ) ) . "\n"; echo "calls: " . count( array_chunk( $q, 5, true ) ) . "\n";'
```

Expected: **7 requests** — `visitors, pages_yesterday, pages_28_days, channels, cities,
devices, trend` — in **2 calls**. Never three: the Data API takes five requests per call, and
FEATURE.md's invariant is at most two calls per run.

## 5. Negative check — switching the block off asks Google nothing

Untick **Тренд**, press **Зберегти зміни**, then:

```bash
ddev wp eval '$q = GaTelegramBridge\ReportBuilder::requests( GaTelegramBridge\Settings::blocks() ); echo count( $q ) . " requests, date dimension asked for: " . ( false !== strpos( json_encode( $q ), "\"name\":\"date\"" ) ? "YES" : "no" ) . "\n"; echo "series: " . var_export( GaTelegramBridge\ReportBuilder::build()->visitors_by_day, true ) . "\n";'
```

Expected: **6 requests**, `date dimension asked for: no`, and `series: NULL`. A switched-off
block must issue no request at all — not an empty one, not a placeholder — and `NULL` is how
the report says "switched off", which is a different thing from "on, and GA had nothing".

Tick **Тренд** again and save, so the install is left as the step intends.

## 6. Negative check — the message has not changed

This step reads the data; it does not print it. Press **Попередній перегляд** and read the
**Відвідувачі** block.

Expected: exactly two lines, *Вчора:* and *За 28 днів:*, the same as before this step — **no
sparkline, no extra line, no blank line** where one might go. If a row of block characters
appears here, something from Step 2 has leaked into Step 1.

## 7. What this step does not prove

- **How the sparkline looks** — there is none yet; Step 2 draws it and checks it in a real
  phone client.
- **The figures on production.** Kharkiv and Kyiv are deployed by hand at the sprint
  boundary; until then only this install has read a trend.
