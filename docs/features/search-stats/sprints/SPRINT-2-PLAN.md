# Step plans — search-stats, Sprint 2

<!-- Written by /plan-step, one section per step. Approval of a section
     authorizes that step only. -->

## Plan — Sprint 2, Step 1: Plugin 0.3.0: the `gatb_extra_blocks` filter and the length guard   (status: closed)

### Branch
`search-stats/sprint-2-report` ← `master`
(root `CLAUDE.md` → git model: simple, task branch → `master`. The name is the
**Branch** line of `SPRINT-2.md`. As in Sprint 1, the branch is created from
`master` for this step and deleted at the close. `master` → `kyiv` happens at
the sprint boundary, not in this step.)

### What this step touches
All the code of this step is in the plugin `ga-telegram-bridge`, which is its
own code area. It obeys the plugin's `CLAUDE.md`, and that file stays true
after the step, so it is not changed. No theme file changes.

Every code task below **touches shared code — may affect other features**:
- `ga-telegram-bridge` owns the code and its own report goes through it.
- `search-stats` is the consumer, from Step 3.

The step adds the plugin's third public filter. DECISIONS
"A theme feature that records site search, plugged into the daily report
through a new plugin filter" already fixes it as stable public surface. The
plugin still never references the theme.

### Tasks (ordered)
- [x] **1. The `gatb_extra_blocks` filter in `MessageRenderer::render()`.**
  *Touches shared code — may affect other features (`ga-telegram-bridge`, `search-stats`).*
  - `render()` builds the plugin's own blocks as today. It then applies
    `apply_filters( 'gatb_extra_blocks', array(), $report )`, where
    `$report` is the report after `gatb_report_data`.
  - A return that is not an array is ignored.
  - Each entry that is not a string, or is `''` after `trim()`, is dropped.
    A whitespace-only entry counts as empty, because printing it would
    break the "one blank line between blocks" rule of the template.
  - The remaining entries are printed in the order given, each as one more
    block, joined with the same `"\n\n"` as every other block.
  - They go after the plugin's own blocks. The GA link stays last.
  - Their HTML is printed exactly as the caller wrote it. The caller escapes
    its own values.
  - The filter is not applied to `render_failure()`.
  - With nothing hooked, Brain\Monkey's `apply_filters` hands back
    `array()`, so every existing snapshot stays as it is.
  - Docs in the same commit:
    - `docs/features/ga-telegram-bridge/FEATURE.md` → Interfaces: the filter,
      with its signature and rules.
    - The same file → UI message template: one line
      `[{blocks from gatb_extra_blocks, in the order given}]` above the link
      line (core rule 5: the template describes the message this code writes).
    - `docs/ARCHITECTURE.md` → Modules, the plugin row: `MessageRenderer`
      applies three filters.
    - `docs/DOMAIN.md` → "Блок звіту": other parts of the same site may add
      blocks of their own after the plugin's blocks.

  → `feat(gatb): let other code add blocks to the report through gatb_extra_blocks`

- [x] **2. The length guard, and the count in the run log.**
  *Touches shared code — may affect other features (`ga-telegram-bridge`, `search-stats`).*
  - `TelegramClient` gains `public const MAX_TEXT_LENGTH = 4096;` with a
    docblock. The step text names the constant as if it existed. It does
    not; DECISIONS "The plugin drops extra blocks…" → Consequences says it
    is added here. `send_message()` does not enforce it; the renderer does
    (that DECISIONS entry).
  - `MessageRenderer` gains
    `public static function compose( Report $report ): array{html: string, dropped: int}`.
    - It does what `render()` did in task 1.
    - While extra blocks remain **and** the assembled message is longer than
      `TelegramClient::MAX_TEXT_LENGTH`, it drops the last extra block and
      counts it.
    - It then applies `gatb_message_html` as today.
  - `render()` becomes `return self::compose( $report )['html'];`, so
    *Preview* and every existing caller keep their signature.
  - Why a second method: the class holds no state. The count has to travel
    out as a return value, not as a static that `Runner` reads back.
  - How Telegram's length is measured, in a private helper:
    - Tags are removed with `strip_tags()`. It carries a `phpcs:ignore` for
      `WordPress.WP.AlternativeFunctions.strip_tags_strip_tags`, because
      Telegram's parser removes the tags and keeps what is between them;
      `wp_strip_all_tags()` also deletes content and trims.
    - Entities are decoded with
      `html_entity_decode( …, ENT_QUOTES | ENT_HTML5, 'UTF-8' )`, as
      `number()` already does.
    - The result is counted in **UTF-16 code units**: code points (`/./su`)
      plus the code points above U+FFFF (`/[\x{10000}-\x{10FFFF}]/u`), with
      `strlen()` as the fallback when the text is not valid UTF-8. Bytes are
      never fewer than UTF-16 units.
    - Why UTF-16: it is the unit the Bot API measures text in, and it is
      never smaller than the character count. So the guard can only err
      toward dropping a block, never toward a message Telegram refuses.
    - No `mb_*` and no new WordPress function. A render with no extra blocks
      measures nothing at all.
  - The guard measures the message the plugin composed, **before**
    `gatb_message_html`. A `gatb_message_html` filter that lengthens the
    message stays that filter's business, as in 0.2.0: dropping extra blocks
    could not undo what it adds.
  - When the plugin's own blocks alone are over the limit, every extra block
    is dropped and the message goes out whole, as 0.2.0 sends it.
  - `Runner::run()` calls `compose()`. When `dropped > 0`, it appends one
    sentence to the run-log detail line:
    - It is appended to the "sent" line.
    - It is also appended to the Telegram-refusal line passed to `failed()`.
      DECISIONS says "a run whose message lost blocks says how many", not
      only a delivered run.
    - The sentence is one new plural string, with the translator comment
      `%d: how many blocks added by other code were left out of the message`:
      - `'%d extra block was left out: with it, the message would have been longer than Telegram accepts.'`
      - `'%d extra blocks were left out: with them, the message would have been longer than Telegram accepts.'`
  - With nothing dropped, the line is exactly the 0.2.0 sentence.
  - Translations, per the plugin's `CLAUDE.md` → i18n commands. After the
    container-side write, read the `.pot` back on the host and check its
    entry count moved (LEARNINGS "Two files looked unchanged because DDEV
    had not synced them yet").
    - Regenerate the `.pot`.
    - `msgmerge --no-wrap` into `-uk.po`.
    - Translate the three Ukrainian plural forms (`nplurals=3` is already in
      the `.po` header).
    - `make-mo`.
  - Docs in the same commit:
    - `FEATURE.md` (plugin) → Invariants: the guard, in one line.
    - `docs/ARCHITECTURE.md` → Modules, the plugin row: the guard.
    - `docs/ARCHITECTURE.md` → Data flows → "Daily GA report":
      `MessageRenderer::render()` becomes `compose()`, with the extra blocks
      cut from the end to fit, and the log line naming the count (core
      rule 5: the flow names the call that changes).

  → `feat(gatb): drop extra blocks that would push the message past Telegram's limit`

- [x] **3. Release 0.3.0.**
  - `ga-telegram-bridge.php`: `Version: 0.2.0` → `0.3.0`.
  - `readme.txt`: `Stable tag: 0.2.0` → `0.3.0`.
  - `readme.txt` gains a `= 0.3.0 =` changelog section, in ASCII only
    (`->`, `--`, `...`):
    - the `gatb_extra_blocks` filter;
    - the length guard and its run-log line;
    - the closing link into Google Analytics. It reached `master` through
      the 2026-09-16 `/adhoc` with no version bump or changelog line, so
      0.3.0 is the first release that carries it to production (WORKLOG
      2026-09-16 → Open).
  - `readme.txt` gains the FAQ entry `= Where do the extra blocks come from? =`,
    in ASCII only. It says:
    - code on the same site (a theme or another plugin) can add blocks
      through `gatb_extra_blocks`;
    - they are printed after the plugin's own blocks and before the link;
    - they must be Telegram HTML that the caller has escaped;
    - the settings screen does not switch them on or off;
    - when the message would be longer than Telegram's 4096 characters, they
      are left out from the last one back, never the plugin's own blocks, and
      the run log says how many.
  - `Plugin::version()` reads the header, so nothing else moves.

  → `chore(gatb): release 0.3.0`

### Files to create/change
In `wp-content/plugins/ga-telegram-bridge/`:
- `src/MessageRenderer.php` (tasks 1, 2)
- `src/TelegramClient.php` (task 2)
- `src/Runner.php` (task 2)
- `tests/Unit/MessageRendererTest.php` (tasks 1, 2)
- `tests/Unit/RunnerTest.php` (task 2)
- `languages/ga-telegram-bridge.pot`, `languages/ga-telegram-bridge-uk.po`,
  `languages/ga-telegram-bridge-uk.mo` (task 2)
- `ga-telegram-bridge.php`, `readme.txt` (task 3)

Docs:
- `docs/features/ga-telegram-bridge/FEATURE.md` (tasks 1, 2)
- `docs/ARCHITECTURE.md` (tasks 1, 2)
- `docs/DOMAIN.md` (task 1)
- `docs/features/search-stats/sprints/SPRINT-2-PLAN.md` (the task ticks)

Checked and left unchanged:
- Gate configs. No file is added: `phpstan.neon.dist` lists `src` and
  `tests` as directories, PHPCS scans the plugin directory, and the PHPUnit
  suite is `tests/Unit` (LEARNINGS "The plan's file list missed a config the
  gate reads").
- The plugin's `CLAUDE.md`. No convention it states changes. The filter
  prefix `gatb_`, "never references the theme" and the i18n procedure are
  followed, not changed (LEARNINGS "…missed the code area's own CLAUDE.md
  again").

### Tests to write
All in the plugin suite. They are built from the recorded report through the
real `ReportBuilder` (`docs/TESTING.md` → Never mocked). Only
`apply_filters` answers are set, through `Filters\expectApplied`.

**`MessageRendererTest`, task 1:**
- `test_extra_blocks_are_printed_after_the_plugins_own_and_before_the_link`:
  - The filter returns `'<b>A &amp; "B"</b>'` and `'second'`.
  - The message ends with
    `"tablet 1%\n\n<b>A &amp; \"B\"</b>\n\nsecond\n\n🔗 <a href=…>More in Google Analytics</a>"`.
  - This proves the order, the placement before the link, one blank line
    each, and HTML passed through untouched: nothing re-escaped.
- `test_the_filter_is_given_an_empty_list_and_the_report`:
  - `expectApplied( 'gatb_extra_blocks' )->once()->with( array(), $report )`.
- `test_empty_and_non_string_entries_are_dropped`:
  - The filter returns `''`, `"  \n"`, `42`, `null`, `array( 'x' )` and `'kept'`.
  - Exactly one extra block, `kept`, is printed, and the message contains no
    `"\n\n\n"`.
- `test_a_filter_that_returns_something_other_than_a_list_is_ignored`:
  - The same report is rendered once with no filter and once with the filter
    answering `'not a list'`. The two messages are `assertSame`.
- `test_the_failure_notice_is_given_no_extra_blocks`:
  - `expectApplied( 'gatb_extra_blocks' )->never()` around `render_failure()`.

**`MessageRendererTest`, task 2.** The numbers below were measured while
planning, from the snapshot in
`test_a_full_report_is_written_out_block_by_block`:
- That snapshot is 36 lines and 1 371 characters of HTML.
- As Telegram counts it, it is 748 characters. The 8 emoji outside the BMP
  (📊 👥 📄 📅 🧭 📍 📱 🔗) count twice, so it is **756 UTF-16 units**.
- One extra block adds its own length plus one `"\n\n"`. So it fits while it
  is at most 4096 − 756 − 2 = **3 338 units**.

The tests:
- `test_an_extra_block_that_fits_exactly_is_kept`:
  - 3 338 × `x` is kept, `compose()['dropped'] === 0`, and
    `render()` equals `compose()['html']`.
  - 3 339 × `x` is dropped: `dropped === 1`, and the message equals the plain
    recorded snapshot.
- `test_the_length_is_counted_as_telegram_counts_it`:
  - `'<b>' . str_repeat( 'x', 3337 ) . '&amp;</b>'` is 3 338 as text and
    3 349 as HTML, and is kept.
  - 1 669 × `🔎` (3 338 units) is kept.
  - 1 670 × `🔎` (3 340 units, but only 1 670 characters) is dropped.
- `test_extra_blocks_are_dropped_from_the_end`:
  - The blocks are `'first'`, 5 000 × `x`, `'last'`.
  - `last` and then the long block are dropped, `first` is kept, and
    `dropped === 2`.
- `test_the_plugins_own_blocks_are_never_dropped`:
  - `report_with()` a `channels` row whose label is 5 000 characters, and
    one extra block.
  - The extra block is dropped (`dropped === 1`). The message still holds the
    whole label, the visitors block and the link, and is returned over the
    limit.
  - The same report with no extra block gives `dropped === 0`.

**`RunnerTest`, task 2:**
- `test_a_run_with_nothing_dropped_logs_the_sentence_it_always_did`:
  - `assertSame` on
    `'The report for 2026-09-15 was sent to chat -1001234567890.'`.
- `test_a_run_whose_extra_blocks_were_dropped_says_how_many`:
  - The filter returns `'short'` and 5 000 × `x`.
  - The run is `sent`, and the detail line ends with
    `' 1 extra block was left out: with it, the message would have been longer than Telegram accepts.'`
  - The text posted to Telegram contains `short` and not the long block.
- `test_a_refused_run_still_says_how_many_blocks_were_dropped`:
  - The Telegram answer is `error-chat-not-found.written.json`, and two
    blocks are dropped.
  - The run is `failed`, and the detail line holds both the mapped reason
    and `2 extra blocks were left out`.

**`PluginTest`: no new test.** The step's "`PluginTest` version 0.3.0" is the
existing `test_the_header_and_the_readme_agree_on_the_version`, which reads
both shipped files and fails when only one is bumped. Sprint 3's 0.2.0 bump
added no literal either. A literal would be one more copy of the version to
edit with every release. The number itself is checked by the manual
verification: `ddev wp plugin list` and the settings screen show 0.3.0.

Counts, if the tests are written as listed: plugin 297 → 309 (task 1 +5,
task 2 +7). Theme 63 unchanged.

Tests the change could break. This list is the result of a search, not a
complete list, and the gate's first run is the real one (LEARNINGS "The plan
named the tests a change would break by grepping…"):
- None expected. With nothing hooked, the filter returns `array()` and
  nothing is measured.
- The log sentence is unchanged when nothing is dropped. That covers
  `AdminSendNowTest:365` and the `RunnerTest` log assertions.

### Docs to update
- `docs/features/ga-telegram-bridge/FEATURE.md`:
  - Interfaces → Filters: `gatb_extra_blocks` (task 1).
  - UI → message template: the extra-blocks line (task 1).
  - Invariants: the guard (task 2).
- `docs/ARCHITECTURE.md`:
  - Modules, the plugin row: three filters and the guard (tasks 1, 2).
  - Data flows → "Daily GA report": `compose()` and the log line (task 2).
- `docs/DOMAIN.md` → glossary "Блок звіту (report block)": other parts of the
  site may add blocks (task 1).
- `readme.txt` (plugin) → Changelog 0.3.0 and the FAQ entry (task 3).

### Checks
- **ANTI-PATTERNS: none violated.**
  - The step's manual verification puts a throwaway
    `add_filter( 'gatb_extra_blocks', … )` into `wp-content/mu-plugins/`.
    TECH-STACK forbids `mu-plugins/` for code that should ship, because it is
    gitignored (`.gitignore:35`) and would not deploy. This file is a local
    probe that is never committed and is deleted after the check, so the
    rule holds.
  - The plugin's own rules hold as well: no network outside `TelegramClient`,
    no secret in the new log sentence, no theme reference, the `gatb_` prefix.
- **Docs vs reality: mismatches, each resolved here.**
  - *Sprint 1 close marker.* The WORKLOG entry of Sprint 1 Step 4 does not
    end with `Sprint 1 complete…`, and there is no `SPRINT-1-CLOSE.md`.
    - Step 4 was closed before `67b657b4` folded `/close-sprint` into
      `/close-step`.
    - All four Sprint 1 steps are ticked, and all are merged into `master`
      (the last is `7b53f1ff`).
    - What Sprint 1 carries forward is the Open list of that entry: the
      production check at the sprint-boundary deploy, `wp is not defined`,
      and `search.php:78` for `/adhoc`. None of it touches this step.
  - *`main`.* This project's `main` is `master`, per root `CLAUDE.md` → git
    model and DECISIONS "`origin/main` is the deploy remote". There is no
    local `main`. The "Sprint N−1 on `main`" precondition is met on `master`.
  - *`TelegramClient::MAX_TEXT_LENGTH`.* The step text names it as if it
    existed, and it does not. Task 2 adds it, as the DECISIONS entry's
    Consequences say.
  - *Changelog.* The GA link has been on `master` since 2026-09-16 with no
    changelog line, and production still runs 0.2.0 without it. The 0.3.0
    changelog names it (task 3).
  - *"`PluginTest` version 0.3.0".* No literal test is added; the existing
    header/readme agreement test covers it (Tests → PluginTest).
  - *"`MessageRenderer::render()` applies the filter".* The filter is applied
    in `compose()`, which `render()` returns the HTML of. The behaviour the
    step names is unchanged.
  - *Doc updates beyond the step's Docs list.* Two are added under core
    rule 5: the plugin's UI template line, and ARCHITECTURE → Data flows.
- **Rule dependents.** The guard is a new rule on the message. Its readers
  are `Runner`, *Preview*, and `gatb_message_html` subscribers:
  - none loses anything, because with no extra blocks the guard measures
    nothing;
  - no existing test's actor loses an ability.
- **Design: n/a.** There is no screen. The message template in the plugin's
  `FEATURE.md` → UI gains a placeholder line, and the three search blocks'
  own template is Step 3's.
- **Check command:** `bin/check.sh` (`docs/TECH-STACK.md` → Check command). It
  was run green on `master` while planning: 297 plugin tests / 1 069
  assertions, PHPStan level 8 clean, 124 theme files linted, 63 theme tests.
- **Environment, checked while planning** through the typed getters, with no
  value printed:
  - `ddev` is running;
  - the plugin is active at 0.2.0;
  - the property id, service account, bot token and chat id are all set;
  - `wp-content/mu-plugins/` does not exist yet.

  So *Preview* and *Send now* work locally for the manual verification.
  *Send now* posts to the chat the local install is configured with.
- **What the tasks make possible (manual verification):**
  - A throwaway mu-plugin returns two short blocks: *Preview* shows them
    between the devices block and the link.
  - A third block of 5 000 characters is appended: it is absent from
    *Preview*.
  - After *Send now*, the run log's detail line names one dropped block.
  - The settings screen and `ddev wp plugin list` show 0.3.0.
  - Before the step is marked implemented, *Preview* is opened in the
    browser with the probe in place (LEARNINGS "A screen was called finished
    without anyone opening it").
- **Not locally verifiable:** both productions on 0.3.0. This is verified by
  the sprint-boundary hand deploy of `master` → Kharkiv and `kyiv` → Kyiv
  (files only, no settings change): each settings screen shows 0.3.0, and the
  next morning's report arrives.

### Questions / ambiguities
none

## Plan — Sprint 2, Step 2: Aggregation: top-5 per level for yesterday and for 28 days   (status: closed)

### Branch
`search-stats/sprint-2-report` ← `master`
(root `CLAUDE.md` → git model: simple, task branch → `master`. The name is the
**Branch** line of `SPRINT-2.md`. It is created again from `master`, which now
carries Step 1, and deleted at the close.)

### What this step touches
All the code is in the feature's own directory, `inc/features/search-stats/`
of the theme, plus its tests. Nothing calls the new code yet: Step 3 wires it
into `gatb_extra_blocks`. So nothing a visitor or the report sees changes.

One piece of shared code is touched: the theme's test double
`tests/WpdbDouble.php` gains `get_results()` (task 2). It is marked **touches
shared code — may affect other features**. Its only users today are the
`search-stats` tests; `core` has no tests. The change adds a method and two
properties and changes nothing that exists.

New classes, all in namespace `dovira\SearchStats`, one file each:
- `Periods`
- `TopQuery`
- `SearchStats`
- `Stats`

They follow the feature's existing style: `final class`, short arrays, no
`strict_types`, as in `Repository.php` and `Purge.php`. The value objects use
promoted `readonly` properties, which need PHP 8.1. Both productions run
plugin 0.2.0, which requires PHP 8.1, so production is on 8.1 or later.
`readonly` classes (8.2) are not used.

### Tasks (ordered)
- [x] **1. `Periods`: yesterday and the last 28 days as UTC bounds.**
  - `inc/features/search-stats/Periods.php`, `final class Periods`, with
    `public const DAYS = 28;`.
  - `yesterday( int $now ): array{from: string, to: string}` and
    `last_28_days( int $now ): array{from: string, to: string}`.
    - The clock is a parameter, as in the plugin and in `Purge::run()`.
    - The zone is read with `wp_timezone()`.
  - The shared start is the local midnight of `$now`:
    `( new DateTimeImmutable( '@' . $now ) )->setTimezone( wp_timezone() )->setTime( 0, 0 )`.
    - `to` is that midnight.
    - `from` is that midnight `->modify( '-1 day' )` for yesterday, and
      `->modify( '-28 days' )` for the 28-day period.
    - Both are converted to UTC as `Y-m-d H:i:s`, the format of `created_at`.
    - The bounds are half-open: `[from, to)`.
  - So "28 days" is the 28 calendar days that end with yesterday. That is the
    plugin's `28daysAgo`…`yesterday`, and the reason `Purge` keeps at least
    29 days.
  - Calendar arithmetic in the site's zone gives a 23-hour or a 25-hour
    yesterday on a clock-change night by itself. It never adds a fixed number
    of seconds.
  - `bootstrap.php` gains `require_once __DIR__ . '/Periods.php';`.
  - Docs in the same commit: `ARCHITECTURE.md` → Modules, the `search-stats`
    row, gains "In as of Sprint 2 Step 2: `Periods` …".

  → `feat(search-stats): compute yesterday and the last 28 days in the site's zone`

- [x] **2. `Repository::top()` and `TopQuery`.**
  *The `tests/WpdbDouble.php` part touches shared code — may affect other features (theme tests; users today: `search-stats` only).*
  - `TopQuery.php`: `final class TopQuery`, with promoted readonly
    `string $query`, `int $context_id`, `int $count` and
    `bool $nothing_found`.
  - `Repository::top( string $level, string $from, string $to ): array`
    returns `list<TopQuery>`. `Repository` gains `public const TOP = 5;`,
    which DECISIONS fixes as top-5 (a fixed rule, not a business setting).
    It runs one `$wpdb->prepare()` + `get_results()`:
    ```sql
    SELECT query_text, context_id, COUNT(*) AS n, MAX(results) AS max_results, MAX(created_at) AS last_at
    FROM {table}
    WHERE level = %s AND created_at >= %s AND created_at < %s
    GROUP BY query_text, context_id
    ORDER BY n DESC, last_at DESC
    LIMIT %d
    ```
    - `{table}` is `Schema::table_name()`, interpolated as in `Purge`.
    - The arguments are `[ $level, $from, $to, self::TOP ]`.
    - It reads through the key `level_created_at (level, created_at)`: one
      equality, then a range.
  - Mapping. `get_results()` returns `stdClass` rows whose values are strings
    or `NULL`:
    - `query` = `query_text`;
    - `context_id` = `(int)`;
    - `count` = `(int) n`;
    - `nothing_found` = `null !== max_results && 0 === (int) max_results`.
      So a `NULL` (the two filter levels) never flags, and results 3 then 0
      (MAX 3) does not flag.
    - A `null` or empty answer gives `[]`.
  - `tests/WpdbDouble.php` gains:
    - `public array $selected = [];`, every SQL string passed to
      `get_results()`, in order;
    - `public array $results = [];`, a queue of answers, each a list of
      `stdClass` rows or `null`;
    - `get_results( string $query, string $output = 'OBJECT' ): ?array`, which
      records the query and shifts the next answer (`[]` when the queue is
      empty).

    The existing `prepare()` already substitutes `%d` (its `vsprintf`), so it
    does not change.
  - `bootstrap.php` gains `require_once __DIR__ . '/TopQuery.php';`.
  - Docs in the same commit:
    - `DATA-MODEL.md` → the table's section: the reading query, the key it
      uses, and the collation note (Checks → Docs vs reality).
    - `TESTING.md` → Rules, the `WpdbDouble` line: it records
      `get_results()` too and answers from `$results`.
    - `ARCHITECTURE.md`, the `search-stats` row: `Repository::top()` and
      `TopQuery`.

  → `feat(search-stats): read a level's top five queries for a period`

- [x] **3. `Stats::build()` and `SearchStats`.**
  - `SearchStats.php`: `final class SearchStats` with six promoted readonly
    `list<TopQuery>` properties, in this order:
    - `site_yesterday`, `site_28_days`;
    - `services_yesterday`, `services_28_days`;
    - `service_yesterday`, `service_28_days`.
    - It has no methods. Step 3's renderer reads the properties.
  - `Stats.php`: `final class Stats`, `build( ?int $now = null ): SearchStats`.
    - `$now ??= time()`.
    - Each period is computed once.
    - It calls `Repository::top()` six times, in the order of the properties.
    - The levels come from `RecordController::LEVEL_SITE` / `LEVEL_SERVICES` /
      `LEVEL_SERVICE`: one source for the three names, not a second list.
    - No caching, no option, no transient (FEATURE.md → Invariants "nothing
      derived is stored").
  - `bootstrap.php` gains the two `require_once` lines.
    - `RecordController.php` is already required there.
    - With all four new classes loaded, the manual verification's
      `ddev wp eval` can call `Stats::build()`.
  - Docs in the same commit:
    - `DOMAIN.md` → "Нічого не знайдено" confirmed against the code: every
      search of the query in the period found nothing (`MAX(results) = 0`),
      site level only. "Пошуковий запит" gains the collation clause.
    - `ARCHITECTURE.md`, the `search-stats` row: `Stats` and `SearchStats`,
      not called by anything until Step 3.

  → `feat(search-stats): build the report's six top-five lists`

### Files to create/change
In `wp-content/themes/dovira/`:
- `inc/features/search-stats/Periods.php` (new, task 1)
- `inc/features/search-stats/TopQuery.php` (new, task 2)
- `inc/features/search-stats/Repository.php` (task 2)
- `inc/features/search-stats/SearchStats.php` (new, task 3)
- `inc/features/search-stats/Stats.php` (new, task 3)
- `inc/features/search-stats/bootstrap.php` (tasks 1–3)
- `tests/WpdbDouble.php` (task 2, shared test code)
- `tests/Unit/SearchStats/PeriodsTest.php` (new, task 1)
- `tests/Unit/SearchStats/RepositoryTopTest.php` (new, task 2)
- `tests/Unit/SearchStats/StatsTest.php` (new, task 3)

Docs:
- `docs/ARCHITECTURE.md` (tasks 1–3)
- `docs/DATA-MODEL.md` (task 2)
- `docs/TESTING.md` (task 2)
- `docs/DOMAIN.md` (task 3)
- this plan file (the ticks)

Checked and left unchanged:
- **Gate configs.** `php -l` in `bin/check.sh` lints every theme PHP file
  outside `vendor/` and `node_modules/`, so the new files are covered. The
  theme suite scans `tests/Unit/`. Tests load the code by path
  (`require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/…'`),
  never through PSR-4 and never through `bootstrap.php` (TESTING.md → Rules).
- **The theme's `CLAUDE.md`.** No convention changes: the feature's classes
  stay in its directory, loaded by its `bootstrap.php`
  (LEARNINGS "…missed the code area's own CLAUDE.md again").
- **`functions.php`, templates, JS.** Untouched.

### Tests to write
All in `tests/Unit/SearchStats/`. `$wpdb` is `WpdbDouble`, and
`wp_timezone()` is stubbed with Brain\Monkey. The code runs for real
(TESTING.md → Never mocked).

**`PeriodsTest`, task 1.** Every number below was computed while planning with
PHP's own `DateTimeImmutable` over `Europe/Kyiv`. 2026's transitions are
2026-03-29 01:00 UTC (to +3) and 2026-10-25 01:00 UTC (to +2).

`test_the_two_periods_for_a_clock` is one data provider, zone `Europe/Kyiv`,
with 7 cases. Each asserts both periods, `[from, to)` in UTC:

| case | `$now` | yesterday | 28 days |
|---|---|---|---|
| an ordinary afternoon, 2026-09-23 12:00 UTC | 1790164800 | 09-21 21:00:00 → 09-22 21:00:00 | 08-25 21:00:00 → 09-22 21:00:00 |
| the last second before local midnight, 20:59:59 UTC | 1790110799 | 09-20 21:00:00 → 09-21 21:00:00 | 08-24 21:00:00 → 09-21 21:00:00 |
| local midnight, UTC still on the 22nd, 21:00:00 UTC | 1790110800 | 09-21 21:00:00 → 09-22 21:00:00 | 08-25 21:00:00 → 09-22 21:00:00 |
| the morning after the spring night, 2026-03-30 07:00 UTC | 1774854000 | 03-28 22:00:00 → 03-29 21:00:00 (23 h) | 03-01 22:00:00 → 03-29 21:00:00 |
| the morning after the autumn night, 2026-10-26 07:00 UTC | 1792998000 | 10-24 21:00:00 → 10-25 22:00:00 (25 h) | 09-27 21:00:00 → 10-25 22:00:00 |
| 28 days across the spring change, 2026-04-10 07:00 UTC | 1775804400 | 04-08 21:00:00 → 04-09 21:00:00 | 03-12 22:00:00 → 04-09 21:00:00 |
| 28 days across the autumn change, 2026-11-05 07:00 UTC | 1793862000 | 11-03 22:00:00 → 11-04 22:00:00 | 10-07 21:00:00 → 11-04 22:00:00 |

(All dates are 2026; the test spells them out as `Y-m-d H:i:s`.)

`test_a_fixed_offset_has_no_clock_change` covers what the local install and
the committed snapshot actually carry: zone `+03:00`, `$now` 1792998000.
- Yesterday is 10-24 21:00:00 → 10-25 21:00:00, 24 hours.
- The 28 days are 09-27 21:00:00 → 10-25 21:00:00.

This case is not in the step text. It is the zone the code will really meet
(Checks → Docs vs reality).

**`RepositoryTopTest`, task 2:**
- `test_the_query_for_each_level_and_period`, a data provider with 3 levels ×
  2 periods = 6 cases, each with fixed bounds:
  - `$wpdb->prepared` holds exactly one entry, the SQL above with table
    `wp_dovira_search_queries`, and `[ level, from, to, 5 ]`;
  - `$wpdb->selected` holds the same SQL with the values in and `LIMIT 5`.
- `test_rows_become_top_queries_in_the_order_given`:
  - Two shaped site rows come back as two `TopQuery` objects in the same
    order.
  - For example, (`вакцинація`, `'0'`, `'7'`, `'3'`, `2026-09-22 10:00:00`)
    becomes query `вакцинація`, context `0`, count `7`, not flagged.
- `test_nothing_found_only_when_every_search_found_nothing`:
  - `max_results` `'0'` → flagged;
  - `'3'` → not flagged (the step's "3 then 0");
  - `NULL` → not flagged (a `services` row with `context_id` `'12'`).
- `test_no_rows_give_an_empty_list`: the answers `[]` and `null` both give
  `[]`.

**`StatsTest`, task 3.** The clock is 1790164800, zone `Europe/Kyiv`:
- `test_six_queries_in_order_with_the_clocks_bounds`:
  - The six `prepared` argument lists are:
    - `site` / `services` / `service`, each with yesterday
      `2026-09-21 21:00:00`, `2026-09-22 21:00:00`, `5`;
    - then the same level with the 28 days
      `2026-08-25 21:00:00`, `2026-09-22 21:00:00`, `5`.
  - Each property holds the rows of its own answer in the queue, one
    distinct row per answer.
- `test_an_empty_table_gives_six_empty_lists`: six empty answers give six
  `[]` properties.

Counts, if the tests are written as listed: theme 63 → 83 (+8, +9, +3).
Plugin 309, unchanged.

Tests the change could break. This is the result of a search, not a complete
list (LEARNINGS 2026-09-16):
- None expected. `WpdbDouble` gains members and changes none, and no existing
  test calls `get_results()`.
- `bootstrap.php` is never loaded by a test.

### Docs to update
- `docs/DATA-MODEL.md` → Table `{prefix}dovira_search_queries` (task 2):
  - the reading query `Repository::top()` runs, once per level and period;
  - that it uses `level_created_at` (equality on `level`, range on
    `created_at`), and that `created_at` alone stays the purge's key;
  - that `GROUP BY query_text` follows the column's collation.
- `docs/DOMAIN.md` → "Нічого не знайдено" confirmed, and "Пошуковий запит"
  gains the collation clause (task 3).
- `docs/TESTING.md` → Rules, the `WpdbDouble` line (task 2).
- `docs/ARCHITECTURE.md` → Modules, the `search-stats` row (tasks 1–3). This
  one is not in the step's list; it is added by core rule 5, because the row
  lists the feature's classes.

### Checks
- **ANTI-PATTERNS: none violated.**
  - The feature only reads its own table, and nothing records a search from
    PHP.
  - No field group, block or post type.
  - `5` and `28` are the fixed rules of DECISIONS "Three blocks…", as named
    constants.
  - The manual verification's seeded rows are local only and deleted after.
- **Docs vs reality: mismatches, each resolved here.**
  - *The site's zone has no name.* The local install and the committed
    `mysql.sql` snapshot have `timezone_string` empty and `gmt_offset` `3`,
    so `wp_timezone()` is `+03:00`, a fixed offset with no clock changes.
    - DECISIONS says the site's zone (`wp_timezone()`), and the code follows
      it whatever it is.
    - The `Europe/Kyiv` tests prove the DST handling for an install whose
      setting names the city; one extra test pins `+03:00`.
    - If production carries the snapshot's setting, "yesterday" in winter runs
      23:00→23:00 Kyiv time.
    - Naming the zone in Settings → General on both installs is a settings
      change, not a step task. It would also move the plugin's send time by
      the winter hour. It is for the developer to decide, outside this step.
  - *Collation.* `query_text` is `utf8mb4_unicode_520_ci`. Measured on the
    local MariaDB while planning:
    - `ґ` = `г` and `ё` = `е`;
    - `й` ≠ `и`, `ї` ≠ `і` and `є` ≠ `е`.

    So `GROUP BY query_text` counts «ґудзик» and «гудзик» as one query and
    prints one of the two spellings. The step fixes the SQL, and it is kept.
    The behaviour is written into DATA-MODEL and DOMAIN rather than changed.
  - *The table is empty locally* (0 rows). The verification seeds its own rows
    and deletes them afterwards.
  - *"The clock is a parameter, as in the plugin's classes".* That means
    `?int $now` Unix time, as in `Purge::run()`. `Periods` takes `int $now`
    because `Stats::build()` resolves the null once.
- **Rule dependents: n/a.** No rule other code depends on is added or
  narrowed. The new code is read only by Step 3.
- **Design: n/a.** No screen. The report template is Step 3's.
- **Check command:** `bin/check.sh` (`docs/TECH-STACK.md` → Check command).
  It exited 0 on `master` after Step 1's merge: 309 plugin tests, 124 theme
  files linted, 63 theme tests.
- **Environment, checked while planning:**
  - `wp_timezone()` is `+03:00`;
  - `wp_dovira_search_queries` has 0 rows;
  - the keys are `PRIMARY (id)`, `level_created_at (level, created_at)` and
    `created_at (created_at)`.
- **What the tasks make possible (manual verification).** Rows are seeded
  with `ddev wp db query` at `created_at` UTC values inside yesterday's
  local bounds and 30 days ago. Then
  `ddev wp eval 'var_dump( dovira\SearchStats\Stats::build() );'` shows:
  - the 30-day-old row in no list;
  - ties ordered by the newer search;
  - results 3 then 0 not flagged, and 0 then 0 flagged.

  Nothing is sent and no screen is involved.
- **Not locally verifiable: n/a.** Nothing on production changes until
  Step 3 hooks the filter. The real query plan on the production MariaDB is
  read with Step 3's report.

### Questions / ambiguities
none

## Plan — Sprint 2, Step 3: The three blocks in the message   (status: approved, in progress)

### Branch
`search-stats/sprint-2-report` ← `master`
(root `CLAUDE.md` → git model: simple, task branch → `master`. It is created
again from `master`, which carries Steps 1–2, and deleted at the close. This
is the sprint's last step: its merge into `master` is the whole sprint on
`master`. `master` → `kyiv` and the hand deploys are the developer's, at the
sprint boundary.)

### What this step touches
Code in the feature's own directory (`inc/features/search-stats/`) and its
tests. No file of the plugin, `core` or the theme's shared code changes.

The step connects the theme to the plugin's public filter
`gatb_extra_blocks`, added in Step 1. The plugin still never references the
theme. The theme only hooks the filter, and only when the plugin is loaded.

This is the first step whose output reaches the owner. After the hand
deploy, the morning message on each install gains up to three blocks.

### Tasks (ordered)
- [x] **1. `Renderer::blocks()`: the three blocks of `FEATURE.md` → UI.**
  - `inc/features/search-stats/Renderer.php`, `final class Renderer`,
    `public static function blocks( SearchStats $stats ): array`, returning
    a `list<string>` of 0–3 blocks in this order:
    - `🔎 <b>Пошук по сайту</b>`
    - `🗂 <b>Пошук у переліку послуг</b>`
    - `💊 <b>Пошук у послугах</b>`
  - **Block text.**
    - Each block is the heading, `Вчора:`, the rows, `За 28 днів:`, the
      rows, joined with `"\n"`.
    - A list with no rows prints `—` on its own line under its label (the
      template).
    - A block whose two lists are both empty is not contributed.
    - Six empty lists give `[]`.
    - The labels and headings are Ukrainian literals, class constants, as the
      theme's `CLAUDE.md` asks for template strings; DECISIONS "Three
      blocks…" fixes their wording.
  - **Rows.** Numbered from `1.` in the order the `SearchStats` lists hold
    them; the renderer never sorts.
    - Site level: `{n}. {query} — {count}`, plus ` · нічого не знайдено` when
      `nothing_found`.
    - The two context levels: `{n}. {query} — {title} — {count}`.
    - `{count}` is plain digits, `(string) $count`. `number_format_i18n()`
      is not used: its Ukrainian thousands separator is the named entity
      `&nbsp;`, which the plugin had to decode because Telegram rejects it.
  - **The 40-character cut.** A query longer than 40 characters
    (`mb_strlen`) is cut to its first 40 (`mb_substr`), the trailing space
    is trimmed, then `…` is added. The cut is made on the raw text **before**
    escaping, so an entity is never cut in half. This is the one defensible
    reading of "a query longer than 40 characters is cut with `…`".
  - **Titles.**
    - The stored `context_id` goes through
      `pll_get_post( $id, pll_default_language() )` when both functions
      exist. A `0`, `false` or `null` answer (no translation) falls back to
      the stored id. Otherwise the stored id is used as is.
    - `get_post( $id )` returning `null` prints `(видалено)`. Otherwise the
      title is `get_post_field( 'post_title', $id, 'raw' )`, the source the
      plugin names pages by, decoded with
      `html_entity_decode( …, ENT_QUOTES | ENT_HTML5, 'UTF-8' )`. Decoding
      matters because WordPress may store `&` as `&amp;` for an author
      without `unfiltered_html`.
    - A trashed post still exists and prints its title. Only a post that is
      gone prints `(видалено)`.
    - Measured locally: 1630, the Russian `Послуги`, maps to 12; 18 maps to
      itself; 999999 gives `0`.
  - **Escaping: every value, and in the one way Telegram accepts.**
    - It is `htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8', true )`,
      in one private helper, used on the query and on the title.
    - `double_encode` is true, so an entity a visitor typed is shown, never
      interpreted.
    - `ENT_HTML401` writes `'` as `&#039;`, which Telegram accepts as a
      numeric entity; `ENT_HTML5`'s `&apos;` it would reject.
    - Why not `esc_html()`: Checks → Docs vs reality.
  - Tests: `RendererTest` (Tests below).
  - Docs in the same commit:
    - `docs/features/search-stats/FEATURE.md` → UI, the notes under the
      template: the cut keeps 40 characters, counts are plain digits,
      escaping double-encodes.
    - `ARCHITECTURE.md` → Modules, the `search-stats` row: `Renderer`.

  → `feat(search-stats): write the three search blocks of the daily report`

- [ ] **2. Hook the blocks into the plugin's report.**
  - `Renderer::add_to( mixed $blocks ): mixed` is the filter callback.
    - Given an array, it returns
      `array_merge( $blocks, self::blocks( Stats::build() ) )`: the plugin's
      list with the theme's blocks appended, and the same list when there is
      nothing to add.
    - Given anything else, it returns it untouched. The plugin ignores a
      non-array return anyway (Step 1), and `array_merge()` on a non-array
      would be fatal.
    - The `Report` argument is not taken: the lists are the site's own, read
      at the time of the run (`Stats::build()` with no clock). So *Preview*,
      *Send now*, the schedule and a retry the same day all show the same
      blocks.
  - `bootstrap.php`:
    ```php
    // Only when the GA → Telegram plugin is loaded: without it, nothing more is loaded or hooked.
    if ( class_exists( \GaTelegramBridge\Plugin::class ) ) {
    	require_once __DIR__ . '/Renderer.php';
    	add_filter( 'gatb_extra_blocks', [ Renderer::class, 'add_to' ] );
    }
    ```
    - It adds `use dovira\SearchStats\Renderer;` beside the file's other
      `use` lines.
    - Plugins load before the theme's `functions.php`, and the plugin's main
      file calls `Plugin::boot()` as it loads, so the class exists whenever
      the plugin is active.
  - Tests: `BootstrapFilterTest` (Tests below).
  - Docs in the same commit:
    - `ARCHITECTURE.md` → Data flows gains **"Daily report → search blocks"**:
      the plugin's `MessageRenderer::compose()` applies `gatb_extra_blocks`
      → `Renderer::add_to()` → `Stats::build()` (six queries) →
      `Renderer::blocks()` → appended after the plugin's blocks, before the
      GA link; dropped from the end by the plugin's guard if the message
      would exceed 4 096. The Modules row gains the hook.
    - `DOMAIN.md` → "Щоденний звіт (daily report)" names the three search
      blocks.
    - `FEATURE.md` → UI marked confirmed against the rendered message. The
      render in the manual check is done before this commit.

  → `feat(search-stats): add the search blocks to the daily Telegram report`

### Files to create/change
In `wp-content/themes/dovira/`:
- `inc/features/search-stats/Renderer.php` (new, tasks 1–2)
- `inc/features/search-stats/bootstrap.php` (task 2)
- `tests/Unit/SearchStats/RendererTest.php` (new, task 1)
- `tests/Unit/SearchStats/BootstrapFilterTest.php` (new, task 2)

Docs:
- `docs/features/search-stats/FEATURE.md` (tasks 1–2)
- `docs/ARCHITECTURE.md` (tasks 1–2)
- `docs/DOMAIN.md` (task 2)
- this plan file

Checked and left unchanged:
- **The plugin.** Its filter and guard are Step 1's, with no plugin change
  here.
- **Gate configs.** `php -l` covers every theme file, and PHPUnit scans
  `tests/Unit/`.
- **The theme's `CLAUDE.md`.** The feature's hook is registered in its own
  `bootstrap.php`, as the file already says. No convention changes.
- **`functions.php`, templates, JS, `WpdbDouble`.** Unchanged.

### Tests to write
All in `tests/Unit/SearchStats/`. They stub `pll_get_post`,
`pll_default_language`, `get_post` and `get_post_field` from a small map:
- 12 → `Послуги`;
- 1630 → 12;
- 18 → `Приймальне відділення`;
- 4242 → no post.

Nothing else in the theme suite defines those functions today.

**`RendererTest`, task 1:**
- `test_three_blocks_as_the_template_writes_them`: a whole-array snapshot.
  - The site level has 2 rows yesterday, the second flagged, and 3 rows over
    28 days.
  - The services level has 1 row yesterday (context 1630, printed as
    `Послуги`) and an empty 28-day list, which prints `—`.
  - The service level has 1 row in each list.
  - The rows are deliberately not in count order, which proves the renderer
    keeps the order it is given.
- `test_a_block_with_two_empty_lists_is_not_contributed`: the services level
  is empty, so there are two blocks, site then service.
- `test_no_searches_give_no_blocks`: six empty lists give `[]`.
- `test_queries_and_titles_are_escaped_for_telegram`:
  - the query `<b>"a" & 'b'` prints `&lt;b&gt;&quot;a&quot; &amp; &#039;b&#039;`;
  - the query `&nbsp; &copy;` prints `&amp;nbsp; &amp;copy;`, where
    `esc_html()` would have kept them and Telegram would refuse the message;
  - a title stored as `Кіт &amp; пес "Мур"` prints
    `Кіт &amp; пес &quot;Мур&quot;`, with no `&amp;amp;`.
- `test_a_long_query_is_cut_at_40_characters`, on multibyte text:
  - 40 Cyrillic characters print whole;
  - 41 print the first 40 plus `…`;
  - a cut that lands after a space trims it before the `…`;
  - 39 characters followed by `&&` are cut before escaping, giving
    `…&amp;…` with no half entity.
- `test_the_context_is_named_by_its_ukrainian_post`: context 1630 prints
  `Послуги` (post 12).
- `test_a_context_that_no_longer_exists_is_named_deleted`: 4242 prints
  `(видалено)`.

**`BootstrapFilterTest`, task 2.** It tests `Renderer::add_to()`, the
callback `bootstrap.php` registers. `bootstrap.php` itself is never loaded
in a test (TESTING.md → Rules), and the one registration line is proven by
the manual check.
- `test_the_plugins_list_comes_back_with_the_blocks_appended`:
  - `WpdbDouble` answers one site row yesterday and nothing else, with the
    clock at the real time and `wp_timezone()` stubbed.
  - `[ '<b>plugin</b>' ]` comes back as `[ '<b>plugin</b>', {site block} ]`.
- `test_the_list_is_untouched_when_there_is_nothing_to_add`:
  - With an empty table, the same list comes back.
  - Given `'not a list'`, the same value comes back.

Counts, if the tests are written as listed: theme 83 → 92 (+7, +2). Plugin
309, unchanged.

Tests the change could break. This is the result of a search, not a complete
list:
- None expected. No existing test loads `bootstrap.php`, stubs these
  functions, or renders a message with extra blocks.
- The plugin's tests hook nothing into `gatb_extra_blocks`.

### Docs to update
- `docs/ARCHITECTURE.md`:
  - Data flows: the new **"Daily report → search blocks"** (task 2);
  - Modules, the `search-stats` row: `Renderer` and the hook (tasks 1–2).
- `docs/features/search-stats/FEATURE.md` → UI: confirmed against the
  rendered message, and the three notes (task 1: cut, digits, escaping;
  task 2: confirmed).
- `docs/DOMAIN.md` → "Щоденний звіт (daily report)" names the three search
  blocks (task 2).

### Checks
- **ANTI-PATTERNS: none violated.**
  - No search is recorded from PHP, and nothing writes the table.
  - No per-city branching: each install reads its own database.
  - No field group, block or post type.
  - The headings and labels are the Ukrainian literals DECISIONS
    "Three blocks…" fixes, kept as constants in the one class that prints
    them. This follows the theme's template convention, not core rule 3's
    configuration: the business wording is decided, and the theme has no
    settings screen for the report.
- **Docs vs reality: mismatches, each resolved here.**
  - *`esc_html` (step text) versus "every dynamic value HTML-escaped"
    (DECISIONS "Three blocks…", FEATURE.md).*
    - Measured on the local install: `esc_html( '&nbsp; &copy; &laquo;' )`
      returns them unchanged, because WordPress does not double-encode an
      entity it recognises.
    - Telegram's HTML accepts only `&lt;`, `&gt;`, `&amp;`, `&quot;` and
      numeric entities (the plugin's `MessageRenderer` documents this). So a
      visitor who searches `&nbsp;` once, through the public route, could
      make the whole report unsendable: refused, retried, then the failure
      notice.
    - Brain\Monkey's `esc_html` stub **does** double-encode, so a test using
      it would pass while production failed.
    - Precedence: DECISIONS over the step text. The plan escapes with
      `htmlspecialchars(…, double_encode: true)` and tests that exact case.
  - *`BootstrapFilterTest`.* TESTING.md forbids loading a feature's
    `bootstrap.php` in a test. The test therefore exercises the callback the
    bootstrap registers, under the step's name. The registration is checked
    in the manual check.
  - *The 40-character cut is ambiguous in the sources.* It is taken as 40
    characters, then `…` (task 1).
  - *No test for the branch where Polylang is inactive.* Brain\Monkey cannot
    undefine a function once a test defines it, so a "Polylang off" case
    would depend on test order. Both installs run Polylang. The branch is
    three lines: the stored id used as is.
  - Carried from Step 2, changing no task:
    - the site's zone is a bare `+03:00`;
    - the collation groups `ґ`/`г` and `ё`/`е`.
- **Rule dependents: n/a.** No permission or validation changes. The plugin's
  length guard (Step 1) may drop these blocks from the end, which is its
  documented behaviour. Three blocks of ≤ 39 lines fit beside the plugin's
  ~756-unit message.
- **Design: matches `FEATURE.md` → UI** (the message template). DECISIONS
  "No UI design phase…" makes it the reference, so there is no DESIGN.md
  change.
- **Check command:** `bin/check.sh`. It exited 0 on `master` after Step 2's
  merge: 309 plugin tests, 131 theme files linted, 83 theme tests.
- **Environment, checked while planning:**
  - Polylang is active, with default language `uk`;
  - `pll_get_post( 1630, 'uk' )` is 12, and 999999 gives 0;
  - titles: 12 `Послуги`, 18 `Приймальне відділення`;
  - no page or service title contains `&` or quotes;
  - `wp_dovira_search_queries` is empty;
  - the plugin is active at 0.3.0, with every credential set.
- **What the tasks make possible (manual verification):**
  - Seed the rows of Step 2's guide §4, which uses contexts 12 and 18.
  - *Preview* shows the three blocks between the devices block and the GA
    link, exactly as `FEATURE.md` → UI.
  - With the table emptied, *Preview* is the plugin's message alone.
  - *Send now* delivers the same to the configured chat.
  - **On screen `Settings` of the plugin, never screenshot or dump the whole
    page:** its top fields print the stored key and token. Read only the
    preview's `<pre>` (LEARNINGS 2026-09-10 and 2026-09-23). The shell
    render through `ddev wp eval` and `MessageRenderer::compose()` is the
    first check. Before the step is marked implemented, *Preview* is opened
    once with element-scoped reads only (LEARNINGS "A screen was called
    finished without anyone opening it").
- **Not locally verifiable.** Both productions carrying theme and plugin
  0.3.0 are verified by the sprint-boundary hand deploy of `master` to
  Kharkiv and `kyiv` to Kyiv, after `master` is merged into `kyiv`, files
  only. On each install:
  - the Sprint 1 recording check is repeated, and its test rows deleted;
  - the next morning's report carries that install's own search blocks, and
    Kyiv's queries never appear in Kharkiv's message;
  - on a morning with no searches, the message is the plugin's alone.

### Questions / ambiguities
none
