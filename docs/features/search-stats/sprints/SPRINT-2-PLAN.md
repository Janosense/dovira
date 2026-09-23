# Step plans — search-stats, Sprint 2

<!-- Written by /plan-step, one section per step. Approval of a section
     authorizes that step only. -->

## Plan — Sprint 2, Step 1: Plugin 0.3.0: the `gatb_extra_blocks` filter and the length guard   (status: approved, in progress)

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

- [ ] **3. Release 0.3.0.**
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
