# SPRINT 3 — step plans (ga-telegram-bridge)

<!-- Written by /plan-step, one section per step, executed by /do-step and
     closed by /close-step. Sections of closed steps are never edited. -->

## Plan — Sprint 3, Step 1: Trend data — block `trend`, the daily-visitors request and `Report::visitors_by_day`   (status: implemented, awaiting close)

### Branch
`ga-telegram-bridge/sprint-3-trend` ← `master`
(root `CLAUDE.md` → git model: simple — task branch → `master`; the name is the
**Branch** line of `SPRINT-3.md`. Recreated from `master` for each step and
deleted at the close, as in Sprints 1–2.)

### Tasks (ordered)

- [x] **1. `Settings` learns the sixth block, and an absent key stops meaning
  "off".** `Settings::BLOCKS` gains `'trend'` after `'devices'`; `defaults()`
  needs no change (`array_fill_keys( self::BLOCKS, true )` already makes it
  default-on).

  The step asks to *confirm* that an install whose stored `blocks` array
  predates the key gets `trend => true`. It does not: `merge_blocks()` writes
  `! empty( $stored[ $block ] )` for every known block, so an absent key is
  `false`. The two callers need different rules and therefore become two
  methods:
  - `merge_blocks( $stored )` — the **stored-option** path (`merge_defaults()`,
    `blocks()`): `array_key_exists()` decides. A key that is there is taken as
    it is; a key that is not there takes the default for that block, which is
    `true`. `visitors` stays forced on.
  - new `submitted_blocks( $raw )` — the **form** path, called from
    `sanitize_settings()`: unchanged behaviour, an absent key is off. This is a
    rule the shipped code states and tests in as many words
    (`SettingsTest::test_the_block_switches_follow_the_submitted_checkboxes`:
    "an unchecked box is off whether it arrives as 0 or not at all"), and
    `Admin::block_checkboxes()` prints a hidden `0` before every checkbox, so
    in the real form every key always arrives anyway. Folding the two paths
    into one rule would make a block impossible to switch off for any caller
    that posts a partial array — hence two methods rather than a flag.

  `docs/DATA-MODEL.md`'s `blocks` row is corrected in the same commit (core
  rule 5): six keys, and "a key the stored row does not carry yet takes its
  default" next to "unknown keys dropped".
  → `feat(gatb): add the trend block and default an unknown block to on`

- [x] **2. The sixth checkbox on screen `Settings`.** `Admin::block_labels()`
  gains `'trend' => __( 'Trend', … )`; a parallel `block_descriptions()` returns
  one optional line per block (only `trend` has one today), which
  `block_checkboxes()` prints after the label as
  `<span class="description">…</span>` — the same element the `(always sent)`
  note beside `visitors` already uses, so no new markup pattern and no CSS.
  Proposed English source strings:
  - label: `Trend`
  - description: `A 28-day sparkline inside the visitors block.`

  Two new strings, so per the plugin's `CLAUDE.md` the `.pot` is regenerated
  and the `uk` `.po`/`.mo` rebuilt in the same commit (`Тренд`, `Спарклайн за
  28 днів у блоці «Відвідувачі».`). The `.pot` goes from 127 entries to 129,
  and — per LEARNINGS 2026-09-09, "Two files looked unchanged because DDEV had
  not synced them yet" — the host copy is read back and its entry count checked
  before the `.po` is rebuilt from it.
  → `feat(gatb): offer the trend block on the settings screen`

- [x] **3. `Report` carries the series.** A fourteenth constructor parameter
  `?array $visitors_by_day`, appended **last**: `Settings::BLOCKS` and
  `ReportBuilder::requests()` both put `trend` last, and appending keeps the
  three existing `new Report()` call sites a one-argument change
  (`ReportBuilder::to_report()`, and `report_with()` plus `without_pages_and_devices()`
  in `MessageRendererTest`). `has_block()` gains `'trend' => $this->visitors_by_day`
  so the method keeps answering for every optional block; the class docblock's
  "four optional blocks" becomes five. `to_report()` passes `null` for now — the
  request does not exist until task 4, so the branch stays green and no message
  changes.

  `docs/features/ga-telegram-bridge/FEATURE.md` → Interfaces records in the same
  commit that the `gatb_report_data` payload gains `visitors_by_day`
  (**touches shared surface** — the filter is the plugin's public, documented
  contract; no other feature in this repo consumes it, and appending a readonly
  property breaks no reader).
  → `feat(gatb): add visitors_by_day to the Report value object`

- [x] **4. The seventh request, its parser, and the recordings.** In
  `ReportBuilder::requests()`, after `devices`:

  ```php
  if ( ! empty( $blocks['trend'] ) ) {
      $requests['trend'] = array(
          'dimensions' => array( array( 'name' => 'date' ) ),
          'metrics'    => array( array( 'name' => 'activeUsers' ) ),
          'dateRanges' => array( $month ),          // 28daysAgo → yesterday
          'orderBys'   => array(
              array(
                  'dimension' => array( 'dimensionName' => 'date' ),
                  'desc'      => false,
              ),
          ),
          'limit'      => self::PERIOD_DAYS,
      );
  }
  ```

  Built inline like the `visitors` request, not through `ranked()`, which orders
  by metric. Chunking is untouched: seven requests split 5 + 2, still two
  `batchRunReports` calls (FEATURE.md invariant), and a switched-off `trend`
  issues nothing.

  New `private static function visitors_by_day( array $report, string $report_date ): array`:
  the rows are keyed by their `date` dimension (`YYYYMMDD`) rather than read by
  position — GA orders rows by metric unless told otherwise, and LEARNINGS
  "Sprint 1 spike findings" is explicit that an index-based parser is how blocks
  get swapped. It then walks the 28 calendar days ending on `$report_date`,
  built with an explicit `UTC` `DateTimeZone` so `modify( '+1 day' )` does pure
  date arithmetic, and writes `array<string, int>` keyed `Y-m-d`, oldest first,
  with `0` for any day GA left out. `to_report()` hoists the report date into a
  variable (it is now needed twice) and fills the field when the response is
  there.

  Fixtures, per `docs/TESTING.md` ("a new fixture is added in the same commit as
  the parser that reads it"):
  - `tests/fixtures/ga/batch-run-reports-daily-call-2.json` re-recorded against
    the live property — now **two** reports, `devices` then `trend` — checked
    for the property id, the service-account address and any token before it is
    committed;
  - today's one-report recording kept as
    `tests/fixtures/ga/batch-run-reports-daily-call-2-no-trend.json`, read by the
    "trend off" test.

  Test plumbing that has to learn about the 5 + 2 split (all of it existing
  scaffolding, no new behaviour):
  - `ReportBuilderTest::identify()` — a request whose dimension is `date` is
    `trend`; today's fallback would call it `devices`.
  - `ReportBuilderTest::recorded_report()` — `devices` is report 0 of call 2,
    `trend` report 1.
  - `SchedulerTest`, `RunnerTest`, `AdminSendNowTest` — each stubs
    `wp_remote_post` with `$requests > 1 ? call-1 : call-2`; with two requests in
    call 2 that hands call 2 the five-report file and `answers()` throws. The
    stub keys on the real count instead (five → call 1, otherwise call 2).
  - `AdminPreviewTest::test_previewing_posts_nothing_to_telegram` answers every
    call with call-1's five reports; same fix.
  - The tests that configure blocks by **omission** — `ReportBuilderTest`'s
    `test_visitors_alone_is_one_request_naming_no_other_block`, `pages_of()` and
    `test_a_property_with_no_traffic_reports_nothing_without_failing`, and
    `MessageRendererTest::silent_report()` — now spell out `false` for what they
    want off, because after task 1 an absent key means "default", not "off".

  `docs/ARCHITECTURE.md` is corrected in the same commit (core rule 5): the
  plugin row and the Google integration row both say a full report is **six**
  reports, which this task makes seven. "At most two `batchRunReports` calls"
  stays as it is. `docs/TESTING.md` → Never mocked gets the new shape of
  `call-2` and the kept `-no-trend` recording.
  → `feat(gatb): read 28 daily visitor counts for the trend block`

- [x] **5. A test that asserts nothing fails the gate.** `phpunit.xml.dist`
  gains `failOnRisky="true"` beside the three `failOn*` attributes already
  there. Verified before planning: `vendor/bin/phpunit --fail-on-risky` on
  `master` at `0cfb3d3` is green (269 tests, 854 assertions, nothing risky), so
  there is nothing to fix or delete — the deferral in SPRINT-2-CLOSE and in
  LEARNINGS 2026-09-10 ("the retro decides: it is a one-line change with, today,
  no failing test behind it") is settled by this line.
  `docs/TESTING.md` → How to run states the rule, which is what the step means
  by "replaces the LEARNINGS note"; the LEARNINGS entry itself is not edited
  (it is an add-only incident log, and its `Transferred to playbook` line is the
  retro's to fill).
  → `chore(gatb): fail the suite on a risky test`

### Decided during the step

Task 4 was stopped and put to the user, because neither the step nor this plan
had seen it: the trend report is the **first** fixture whose rows carry calendar
dates. Every other recorded block is keyed by `date_range_0…3` or by a label, so
the suite's pinned clock (`NOON`, 2025-09-10) could be any instant. A trend
report recorded today is dated 2026-08-19…2026-09-15, and `ReportBuilder` walks
the 28 days ending on the report date — with the old clock it would have read
all 28 as `0` and the test would still have passed.

Chosen, of three options: **move the clock to the recording.** `NOON` becomes
`1789560000` (2026-09-16 12:00 UTC, report date 2026-09-15) in the three
fixture-driven test classes, and the dates that come out of `build( NOON )` move
with it. The self-contained date tests — `report_date()`'s own midnight cases,
`render_failure()`'s argument, `AdminSendNowTest`'s hand-written log rows — were
left alone. Rejected: re-recording call-1 as well (every asserted figure, both
message snapshots and `tests/SitePosts.php` would move, nearly all of it
unrelated to this step) and shifting the recorded dates onto the old window
(the report would stop being a recording).

One correction to what the options said: re-recording `call-2` also moved the
**device** figures, which live in that call — mobile 1248/desktop 304/tablet 10
became 1230/284/8, so the mobile share is 81% where it was 80%, in
`ReportBuilderTest` and in the whole-message snapshot. Nothing else changed.

### Files to create/change

Code — `wp-content/plugins/ga-telegram-bridge/`:
- `src/Settings.php` (tasks 1)
- `src/Admin.php` (task 2)
- `src/Report.php` (task 3)
- `src/ReportBuilder.php` (task 4)

Config:
- `phpunit.xml.dist` (task 5)
- Checked and **not** changed, per LEARNINGS "The plan's file list missed a
  config the gate reads": `phpstan.neon.dist` already analyses
  `ga-telegram-bridge.php`, `uninstall.php`, `src`, `tests`, and this step adds
  no PHP file outside them; `phpcs.xml.dist` scans the same tree; the `.pot`
  exclude list (`vendor,tests,spike,.phpunit.cache`) is unaffected; the plugin's
  own `CLAUDE.md` names no block list and no fixture, so it has nothing to learn.

Translations (task 2): `languages/ga-telegram-bridge.pot`,
`languages/ga-telegram-bridge-uk.po`, `languages/ga-telegram-bridge-uk.mo`.

Fixtures (task 4): `tests/fixtures/ga/batch-run-reports-daily-call-2.json`
(re-recorded), `tests/fixtures/ga/batch-run-reports-daily-call-2-no-trend.json`
(new — today's recording under its new name).

Tests: `tests/Unit/SettingsTest.php`, `tests/Unit/AdminTest.php`,
`tests/Unit/ReportBuilderTest.php`, `tests/Unit/MessageRendererTest.php`,
`tests/Unit/AdminPreviewTest.php`, `tests/Unit/AdminSendNowTest.php`,
`tests/Unit/RunnerTest.php`, `tests/Unit/SchedulerTest.php`.

Docs: `docs/DATA-MODEL.md` (task 1),
`docs/features/ga-telegram-bridge/FEATURE.md` → Interfaces (task 3),
`docs/ARCHITECTURE.md` (task 4), `docs/TESTING.md` (tasks 4 and 5).

### Tests to write

From the step, plus the profile's test-critical zones (per-city pricing and the
form pipelines are not touched; the GA request/response path is this plugin's
equivalent and is covered below):

- `SettingsTest`
  - the defaults array now carries six blocks, `trend` among them, all `true`;
  - **new:** a stored `blocks` row holding the five Sprint-2 keys and no `trend`
    reads back as `trend => true`, through both `merge_defaults()` and
    `blocks()` — the "old install" case the step names;
  - **new:** a submission whose `blocks` array omits a key still switches that
    key off, so the two merges cannot be collapsed by accident later;
  - `test_a_submission_without_blocks_leaves_them_untouched` and
    `test_the_getters_report_the_stored_block_switches` restated against the new
    rule: they assert "off" by omission today, and must name `false` to keep
    meaning it (the second also gains `trend` in its `array_keys` list).
- `ReportBuilderTest`
  - **new:** the trend request asks for dimension `date`, metric `activeUsers`,
    `28daysAgo`–`yesterday`, `orderBys` on the dimension ascending, `limit` 28;
  - the split test becomes five **and two**: seven requests, two calls;
  - **new:** with `trend` off, six requests, two calls, no `date` dimension
    anywhere in what was asked, and `visitors_by_day` is `null` (reads the
    `-no-trend` recording);
  - **new:** the recorded 28-row response parses to 28 entries, keys ascending
    `Y-m-d` ending on the recorded day, integer values;
  - **new:** a response missing days fills those days with `0` and keeps the
    other 27 in place;
  - **new:** a response with no rows at all gives 28 zeros, not `array()`;
  - the block-combination test runs 32 combinations instead of 16, with `trend`
    in the loop that asserts `has_block()` — that test exists precisely to catch
    a request-to-response mis-zip, which is what a seventh block can cause.
- `AdminTest` — the sixth checkbox renders with id `gatb_block_trend`, follows
  the stored switch, is not disabled, and prints its description line; the
  existing hidden-input test already loops `Settings::BLOCKS` and so covers it.
- The whole suite green with `failOnRisky="true"` (task 5).

### Docs to update
- `docs/DATA-MODEL.md` — `gatb_settings.blocks`: six keys, and a key absent from
  a stored row takes its default rather than `false`.
- `docs/features/ga-telegram-bridge/FEATURE.md` → Interfaces — the
  `gatb_report_data` payload gains `visitors_by_day` (touches shared surface).
  Its **Data** and **UI** sections already describe `trend` (written by the
  re-planning chat, commit `0cfb3d3`) and need nothing.
- `docs/ARCHITECTURE.md` — "a full report is six" in the plugin row and in the
  Google integration row.
- `docs/TESTING.md` — the `failOnRisky` rule under How to run; under Never
  mocked, what `call-2` now holds and why the one-report recording is kept.

### Checks
- **ANTI-PATTERNS:** none violated. No ACF field group, block directory or post
  type is involved (the report "blocks" are settings keys, not editor blocks);
  no city branching; no hand-edited `assets/`; no Kyiv-only change; no new
  tool installed globally — `failOnRisky` is an attribute in a committed
  config, and no dependency is added, so core rule 1 is not engaged.
- **Docs vs reality:** four mismatches, all resolved inside the tasks.
  1. The step says to *confirm* that an old install gets `trend => true`; the
     shipped `merge_blocks()` gives `false`. Task 1 makes the step's sentence
     true rather than reporting it as confirmed.
  2. `docs/ARCHITECTURE.md` says a full report is six reports in two places.
     Not in the step's "Docs to update", but core rule 5 settles it: it
     describes code this step changes, so it is corrected in task 4's commit.
  3. `FEATURE.md` → Data and → UI already describe `trend` and the trend line,
     while no code implements either. Expected — the re-planning chat wrote them
     on 2026-09-16; Step 1 closes the Data half, Step 2 the UI half.
  4. Carried out of `SPRINT-2-CLOSE.md`: the local install's service-account key
     and bot token are pending rotation (they reached two session transcripts),
     and task 4 re-records a fixture through that same key. The rotation is
     `SPRINT-3.md` → Out of scope ("operational, the developer's task"); the
     recording itself carries no credential, and the response body holds no
     property id — it is checked before the commit all the same.
  Also noted, changing no task: after Step 1 the screen offers a block that
  costs one GA request and changes no message; Step 2 renders it. That is the
  sprint's intended shape, not a defect.
- **Design:** n/a. `FEATURE.md` → UI records screen `Settings` as stock wp-admin
  components with no design export, and `docs/DESIGN.md` does not cover this
  plugin (SPRINT-2-CLOSE → Contradiction 1, still open and out of scope here).
  The new checkbox reuses the markup `block_checkboxes()` already prints.
- **Check command:** `bin/check.sh` (docs/TECH-STACK.md → Check command).
  Verified before planning: exit 0 on `master` at `0cfb3d3`.
- **Not locally verifiable:** that GA4 accepts the seventh request — the
  `orderBys` dimension shape and `limit` 28 — is proven by the live call that
  re-records the fixture and by the step's `wp eval` verification against the
  real property, never by `bin/check.sh`, which only ever sees the recording.
  Per LEARNINGS "Sprint 1 spike findings", that snippet is run with `wp eval`
  and no `const`, `declare()` or `__DIR__`.

### Questions / ambiguities
none
