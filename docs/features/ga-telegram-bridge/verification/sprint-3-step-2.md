# Verification — ga-telegram-bridge, Sprint 3, Step 2

**The sparkline in the message** · written at close on 2026-09-16
Branch merged: `ga-telegram-bridge/sprint-3-trend` → `master`

What this step promises: the 28 daily figures Step 1 reads are now **drawn** — one line
inside the visitors block, 28 block characters from the oldest day on the left to yesterday
on the right, with the quietest and busiest day printed beside them. Switching the block off
takes the line away and leaves nothing where it stood.

The screen is
**<https://dovira.ddev.site/wp-admin/options-general.php?page=gatb-settings>**; shell
commands are run from the repo root.

> **Do not take a whole-page screenshot of this screen.** It prints the stored
> service-account key and bot token back into their fields (LEARNINGS 2026-09-10). The
> screenshot this guide asks for is of the **Telegram chat**, never of the screen.

## 1. The line is in the message

```bash
ddev wp eval 'echo GaTelegramBridge\MessageRenderer::render( GaTelegramBridge\ReportBuilder::build() ) . "\n";' | head -6
```

Expected — the visitors block of four lines, the fourth being the trend:

```
📊 <b>dovira.ddev.site — 15 Вересня (Вівторок)</b>

👥 <b>Відвідувачі</b>
Вчора: 76 (▲ 29% до середнього за 7 днів)
За 28 днів: 1 520 (▼ 8% до попередніх 28)
<code>▅▄▁▃▂▆▇▆▂▅▇▅██▆▄▅▂▁█▆▇▆▄▄▇▄█</code> 43–77
```

That is the output of the day this step was closed; yours will have moved on. What must hold
whatever the numbers are:

1. the line sits **directly under** *За 28 днів* — no heading, and no blank line between them;
2. it is wrapped in `<code>…</code>` and nothing else;
3. exactly **28 characters** inside the tags, only from `▁▂▃▄▅▆▇█`;
4. the two numbers after it are the **lowest and highest day** of those 28 — not a total, and
   not the same as the 28-day figure on the line above. Read them off the list from
   §3 of the Step 1 guide if you want to check them by hand.

## 2. The screen shows the same thing

Press **Попередній перегляд**. The preview block prints the whole message; the visitors block
must read exactly as above, with the row of blocks under the two figures.

This is the check that matters most, because the preview is what the administrator sees
before anything is sent — and the step changed no code in the preview path. If the line shows
up in §1 but not here, something is wrong with the preview, not with the renderer.

## 3. It survives the trip to Telegram — the part only a real client can answer

Press **Надіслати зараз**, then open the chat **on a phone**.

Expected: the 28 characters sit on **one line**, in a monospace run, with the two numbers
directly after them. They must not wrap onto a second line, and they must not be drawn with
gaps between them — the whole point of `<code>` is that every character takes the same width,
so the row reads as a shape.

**Put a screenshot of the chat here** (not of the settings screen). Different clients pick
different monospace fonts, so this check answers for one of them; iOS, Android and desktop
are only fully answered by the owner's own client after the deploy.

## 4. Negative check — switching the block off leaves nothing behind

Untick **Тренд**, press **Зберегти зміни**, then press **Попередній перегляд** again.

Expected: the visitors block is back to **three lines** — heading, *Вчора*, *За 28 днів* —
and the next block starts after exactly one blank line. There must be **no** empty line where
the trend was, and no stray `<code>` anywhere in the message.

```bash
ddev wp eval '$m = GaTelegramBridge\MessageRenderer::render( GaTelegramBridge\ReportBuilder::build() ); echo ( false === strpos( $m, "<code>" ) ? "no trend line" : "TREND STILL THERE" ) . "\n" . ( false === strpos( $m, "\n\n\n" ) ? "no double blank line" : "BLANK LINE LEFT BEHIND" ) . "\n";'
```

Expected: `no trend line` and `no double blank line`.

Tick **Тренд** again and save, so the install is left as the sprint intends.

## 5. Negative check — a flat period is not an empty line

You cannot make the property flat to try this, so it is worth knowing what to expect if it
ever happens: a period where every day is equal — including a site nobody visited at all —
prints **28 × `▄`**, a row at mid height, and the two numbers are the same figure twice. It
does **not** print an empty line, and it does **not** print 28 × `▁`. The scale runs between
the period's own ends, so a flat period has no low point to mark; `SparklineTest` holds both
cases.

## 6. What this step does not prove

- **The October clock change** — Step 3, and then the sprint's Definition of Done.
- **The owner's own client.** The first scheduled morning after the sprint-boundary deploy is
  the only check of how the row draws where it is actually read.
- **The plugin version.** The changelog now describes `0.2.0` while the header still says
  `0.1.0`; Step 3 bumps it.
