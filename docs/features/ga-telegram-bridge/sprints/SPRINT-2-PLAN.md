# SPRINT 2 — step plans (ga-telegram-bridge)

<!-- Written by /plan-step, one section per step, executed by /do-step and
     closed by /close-step. Sections of closed steps are never edited. -->

## Plan — Sprint 2, Step 1: Scheduler   (status: closed)

### Branch
`ga-telegram-bridge/sprint-2-runs-by-itself` ← `master`
(root `CLAUDE.md` → git model: simple — task branch → `master`; the name is the
**Branch** line of `SPRINT-2.md`. Deleted at the close, as in Sprint 1.)

### Tasks (ordered)

- [x] **1. `gatb_state` remembers when WP-Cron last ran.** `RunLog` gains a third
  state key `last_cron_hit` (int, `0` = never): `state()` completes and types it,
  `save_state()` **merges into the stored row instead of replacing it** — so
  `mark_sent()` / `mark_failed()` can no longer drop a key they do not know about
  — plus `mark_cron_hit()` and `last_cron_hit()`. A row written by Sprint 1
  completes to the new key on read, so there is no migration.
  `docs/DATA-MODEL.md` gets the key in the same commit (core rule 8).
  → `feat(gatb): remember the last wp-cron hit in gatb_state`

- [x] **2. The scheduler.** New `src/Scheduler.php` — per the plugin's
  `CLAUDE.md`, the only class that schedules or clears events:
  - `DAILY_HOOK = 'gatb_daily_report'`, `RETRY_HOOK = 'gatb_retry_report'`
    (only *cleared* here; Step 2 is what schedules it), `CRON_HIT_MAX_AGE = 86400`
    (a plugin constant, not `DAY_IN_SECONDS`: the unit tests load no WordPress).
  - `next_occurrence( DateTimeZone $zone, string $send_time, int $now ): int` —
    the pure maths, tested directly like `Settings::sanitize_settings()`: today's
    `HH:MM` in the site zone, tomorrow's when that moment has already passed or
    is exactly now, returned as a UTC timestamp. A time that does not exist on a
    spring-forward day resolves forward the way `DateTimeImmutable` does.
  - `reschedule(): void` — `wp_clear_scheduled_hook( DAILY_HOOK )`, then
    `wp_schedule_event( next_occurrence( wp_timezone(), Settings::send_time(), time() ), 'daily', DAILY_HOOK )`.
  - `clear(): void` — clears **both** hooks.
  - `next_run(): ?int` — `wp_next_scheduled( DAILY_HOOK )`, `false` → `null`.
    The next run is **not** stored anywhere: the step reads it from
    `wp_next_scheduled()`, and a copy in `gatb_state` would have to be
    invalidated on every reschedule (see Checks → Docs vs reality).
  - `run_daily(): void` — the `gatb_daily_report` callback: `Runner::run( 'cron' )`,
    guard on. `Runner` itself does not change: the day it compares is the
    property's, per DECISIONS "The date guard runs after the report is built,
    not before".
  - `note_cron_hit(): void` — when `wp_doing_cron()`, `RunLog::mark_cron_hit()`.
  - `external_cron_missing( bool $wp_cron_disabled, int $last_hit, int $now ): bool`
    — the pure predicate behind the warning: true only when WP-Cron is switched
    off in `wp-config.php` and the last hit is missing or older than 24 h.

  `Plugin::boot()` registers three more hooks (`gatb_daily_report` →
  `Scheduler::run_daily`, `update_option_gatb_settings` → `Scheduler::reschedule`,
  `wp_loaded` → `Scheduler::note_cron_hit`); `Plugin::activate()` calls
  `Scheduler::reschedule()` after creating the option; a new `Plugin::deactivate()`
  calls `Scheduler::clear()` and `ga-telegram-bridge.php` registers it with
  `register_deactivation_hook`. `docs/ARCHITECTURE.md` → Data flows is updated in
  the same commit.

  The event is registered whatever the install holds, exactly as the step says.
  An unconfigured site therefore writes one `failed` log row a day and makes **no
  network call** — `GoogleAuth` refuses an empty key before any request
  (`src/GoogleAuth.php:128`) — while a "schedule only when configured" rule
  appears in no document and would leave the screen with no next run to show.
  → `feat(gatb): schedule the daily report`

- [x] **3. The screen says when the next run is.** `Admin::render_schedule_section()`
  stops saying "Nothing runs on a schedule yet." and prints instead: the next run
  in site time, through the same private `moment()` helper the log rows use, so
  the two dates on the page read alike; the sentence that it is not scheduled yet
  when `Scheduler::next_run()` is `null`; and, when
  `Scheduler::external_cron_missing( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON, RunLog::last_cron_hit(), time() )`,
  a `notice notice-warning inline` naming `wp-cron.php` and the system-cron
  alternative. `readme.txt` loses its two now-false sentences (the Schedule
  bullet's "nothing runs on a schedule until the next release" and the closing
  "The report is sent by hand for now") — the readme's full rewrite stays Step 3.
  `FEATURE.md` → UI and → Data are corrected in the same commit.
  → `feat(gatb): show the next run and warn about a missing external cron`

- [x] **4. Translations.** Regenerate `languages/ga-telegram-bridge.pot`, translate
  the new and changed entries in `ga-telegram-bridge-uk.po`, rebuild the `.mo` —
  the plugin's `CLAUDE.md` requires it of every step that adds or changes a
  string, and its DDEV note (read the `.pot` back on the host and check the entry
  count moved) is followed, per LEARNINGS "Two files looked unchanged because
  DDEV had not synced them yet".
  → `chore(gatb): translate the scheduler strings`

No task touches shared code: nothing outside
`wp-content/plugins/ga-telegram-bridge/` except the four project docs, and no
project tooling. The plugin hosts one feature.

### Files to create/change
**Create**
- `wp-content/plugins/ga-telegram-bridge/src/Scheduler.php`
- `wp-content/plugins/ga-telegram-bridge/tests/Unit/SchedulerTest.php`

**Change**
- `src/RunLog.php` (task 1), `src/Plugin.php` and `ga-telegram-bridge.php` (task 2),
  `src/Admin.php` and `readme.txt` (task 3)
- `tests/Unit/RunLogTest.php`, `tests/Unit/PluginTest.php`, `tests/Unit/AdminTest.php`
- `languages/ga-telegram-bridge.pot`, `-uk.po`, `-uk.mo`
- `docs/DATA-MODEL.md`, `docs/ARCHITECTURE.md`,
  `docs/features/ga-telegram-bridge/FEATURE.md`,
  `docs/features/ga-telegram-bridge/sprints/SPRINT-2-PLAN.md` (checkboxes)

Not touched: `Runner`, `ReportBuilder`, `MessageRenderer`, `TelegramClient`,
`GaClient`, `GoogleAuth`, `Settings` (`send_time` and `max_attempts` are already
validated and typed since Step 3).

### Tests to write
`SchedulerTest` (new, fixed clock — no test may depend on the day it runs on):
1. the next occurrence of `09:00` is today's when the moment is before it
2. …and tomorrow's when it has passed, and when the moment **is** exactly it
3. across the spring transition the local time stays `09:00` while the UTC offset
   goes `+02 → +03`
4. …and the same across the autumn transition
5. around midnight: `00:30` computed at `23:50` local lands 40 minutes later, on
   the next date
6. a send time that does not exist on the spring-forward day (`03:30`) resolves
   forward instead of throwing
7. `reschedule()` clears the old event first and registers exactly one `daily`
   event at that timestamp (Brain\Monkey expectations) — the step's
   "re-registration on save replaces the old event"
8. `clear()` clears both `gatb_daily_report` and `gatb_retry_report`
9. `run_daily()` runs as `cron` with the guard on: the second run for the same
   day sends nothing and logs nothing, the next day sends — the step's date-guard
   test, the half `RunnerTest` does not already cover ("allows the next day")
10. `note_cron_hit()` records on a cron request and does nothing on a normal one
11. `external_cron_missing()`: true when WP-Cron is off and the hit is missing or
    older than 24 h; false when it is recent, and false whenever WP-Cron is on

`RunLogTest`: `last_cron_hit` survives `mark_sent()` and `mark_failed()` (the
state write merges); a Sprint 1 row without the key reads back as `0`.

`PluginTest`: the three new hooks are registered; activation schedules the daily
event; deactivation clears both hooks.

`AdminTest`: the schedule section prints the next run in site time; it says the
report is not scheduled when nothing is; the warning is printed when WP-Cron is
off with no hit in 24 h and is absent otherwise (the state the reader sees, not
only the markup).

### Docs to update
- `docs/DATA-MODEL.md` — `gatb_state` gains `last_cron_hit`; the sentence
  promising `next_run` is corrected to say the next run is read from
  `wp_next_scheduled()` and never stored
- `docs/ARCHITECTURE.md` — the "Daily GA report" flow gains its scheduled half
  (the daily event, the callback, the trigger `cron`); the plugin's module row
  names `Scheduler`
- `docs/features/ga-telegram-bridge/FEATURE.md` — Data (the `gatb_state` keys),
  UI (screen `Settings` shows the next run and the WP-Cron warning; screen
  `Run log` has no next-run column)
- At the close: `docs/WORKLOG.md`, the verification guide, the sprint tick.
  `docs/DECISIONS.md` only if something is decided that these tasks do not
  already carry.

### Checks
- **ANTI-PATTERNS:** none violated. The list is theme-shaped (ACF groups and
  blocks, `service-city`, city branching, `assets/`, ops directories, post-type
  registration, `mu-plugins/`, Kyiv-only changes on `master`, CF7 ids) and this
  step adds a plugin class, three hooks and a section of copy. The one rule that
  reaches the plugin — no globally installed tooling — is untouched: no
  dependency is added, so core rule 4 is not in play either.
- **Docs vs reality:** five mismatches, all settled here, none of them a question:
  1. **Which time zone the guard compares** — `SPRINT-2.md` → Step 1 says
     "yesterday in the site time zone"; DECISIONS "The date guard runs after the
     report is built, not before" says the property's day, and the built `Runner`
     does that. DECISIONS wins over a sprint file: `run_daily()` calls
     `Runner::run( 'cron' )` and nothing about the guard changes. This is
     Contradiction 1 of `SPRINT-1-CLOSE.md`, resolved in favour of the shipped
     code; the close will record it.
  2. **`gatb_state.next_run`** — the step's own task text reads the next run from
     `wp_next_scheduled()`, while its "Docs to update" line and
     `FEATURE.md` → Data promise a stored `next_run`. The task text is the
     concrete instruction and the stored copy would have to be invalidated on
     every reschedule for no gain, so the key is not created and both documents
     are corrected. `last_cron_hit` **is** stored — the warning cannot be
     computed without it.
  3. **Where the next run is shown** — `FEATURE.md` → UI still gives screen
     `Run log` a *next run* column; the step puts the value on screen `Settings`,
     and a column repeating one value on every row of a list of past runs is not
     a table. The step wins; `FEATURE.md` → UI is corrected (Contradiction 6 of
     `SPRINT-1-CLOSE.md`).
  4. **Translations** — `SPRINT-2.md` → Step 3 collects "regenerate the `.pot`,
     complete `uk_UA` for all strings added in Sprints 1–2", but the plugin's
     `CLAUDE.md` requires it of every step that changes a string, Steps 7 and 8
     did it that way, and the locale is `uk`, not `uk_UA` (Contradiction 2).
     Task 4 keeps the per-step rule; Step 3 will find only its own strings left.
  5. **Sprint 1's boundary** — the user has ticked all five Definition-of-Done
     boxes in `SPRINT-1.md` (unstaged) and `spike/` is gone from disk, while
     `SPRINT-1-CLOSE.md` records two of those items as open at the close and
     `origin/dev` is still 63 commits behind `master`. `SPRINT-1-CLOSE.md` is a
     report of the close and is not rewritten; the consequence for this step is
     in **Not locally verifiable** below.
  Two observations that change no task: a `wp_schedule_event( …, 'daily', … )`
  fires every 86 400 s, so after a DST change the report arrives an hour off
  local time until the settings are saved again — re-anchoring is neither in the
  step nor in DECISIONS, and belongs to Step 2 or the retro; and screen
  `Settings` now shows a value (`Next run`) that is not a setting, which is why
  it goes into the section description rather than into a field.
- **Design:** n/a — DECISIONS "No UI design phase; message format and
  configurable blocks" forbids a design phase and `docs/DESIGN.md` changes for
  this feature; the screen record is `FEATURE.md` → UI, updated in task 3.
  (`DESIGN.md` → Screens still lists neither of the plugin's screens —
  Contradiction 5, unchanged and not this step's to settle.)
- **Check command:** `bin/check.sh` (`docs/TECH-STACK.md` → Check command) —
  exists and is green on `master` right now: `OK (215 tests, 687 assertions)`,
  PHPCS + PHPStan level 8 clean, 108 theme files linted.
- **Not locally verifiable:** the scheduled run's **delivery**. The schedule
  itself is fully verifiable on the dev site — set the send time a few minutes
  ahead, save, `ddev wp cron event list` shows one `gatb_daily_report`, hit the
  front page after the time and the log gains exactly one `cron` row, hit it
  again and it gains nothing. What that row says depends on credentials the dev
  install no longer has: it is back at defaults (property, key, token and chat
  all unset) and `spike/service-acount.json` was deleted at the boundary. With
  nothing configured the verification proves the firing, the trigger `cron`, the
  date guard and the log row, and the row reads `failed`; a `sent` row needs the
  property id, the service-account key, the bot token and the chat id entered
  again by hand. Two consecutive real mornings on both production installs stay
  Step 4 and the sprint boundary either way.

### Questions / ambiguities
none

## Plan — Sprint 2, Step 2: Retries and the failure notice   (status: closed)

### Branch
`ga-telegram-bridge/sprint-2-runs-by-itself` ← `master`
(root `CLAUDE.md` → git model: simple — task branch → `master`; the name is the
**Branch** line of `SPRINT-2.md`. Recreated from `master`, deleted at the close,
as in Step 1.)

### Tasks (ordered)

- [x] **1. Telegram waits once when it is asked to.** `TelegramClient::send_message()`
  gains one second attempt inside the same run, for HTTP 429 only: when
  Telegram's `parameters.retry_after` is between 1 and `MAX_WAIT` seconds the
  client waits that long and posts the message once more; a second refusal, a
  429 without `retry_after`, and a `retry_after` beyond the cap are the failure
  they already are, with the mapped sentence Step 5 wrote and no change to any
  other status.
  - `MAX_WAIT = 30` — the plugin only ever sends from a WP-Cron or an admin
    request, both of them HTTP requests running under the host's own
    `request_terminate_timeout`; a longer pause is what the hourly retry of
    task 2 is for. Not a setting: the interval a business changes here is
    `max_attempts`, which is one already (core rule 6).
  - The wait is injected — `send_message( string $chat_id, string $html, ?callable $wait = null )`,
    `sleep(...)` when nothing is passed — the way `$now` is injected into
    `Runner::run()` and `ReportBuilder::build()`, so the tests assert the number
    of seconds asked for without any test sleeping. Both production callers pass
    nothing.
  `docs/ARCHITECTURE.md` → Integrations, the plugin's Telegram row, gains the
  in-run wait in the same commit.
  → `feat(gatb): wait once when Telegram asks to slow down`

- [x] **2. A failed run books another one an hour later.** The scheduling stays
  in `Scheduler` — the plugin's `CLAUDE.md` makes it the only class that
  schedules or clears events, so `Runner` asks it rather than calling
  `wp_schedule_single_event()` itself (the sprint text puts the call "in
  `Runner`"; the area convention decides where the line lives, not whether it
  happens).
  - `Scheduler`: `RETRY_DELAY = 3600` (written out like `CRON_HIT_MAX_AGE`: the
    unit tests load no WordPress; one hour is fixed by DECISIONS "Scheduling,
    retries and idempotency on WP-Cron"), `schedule_retry( string $date, ?int $now = null ): void`
    → `wp_schedule_single_event( ( $now ?? time() ) + RETRY_DELAY, RETRY_HOOK, array( $date ) )`,
    and `run_retry( string $date = '' ): void` → `Runner::run( 'retry', false, null, $date )`.
    The default keeps a hand-scheduled event without arguments from fataling.
  - `Scheduler::clear()` switches both hooks to `wp_unschedule_hook()`.
    `wp_clear_scheduled_hook( $hook )` unschedules only the events registered
    with **no** arguments (`wp-includes/cron.php`), so from this task on a
    pending retry — which carries its date — would survive deactivation. Read in
    core while planning, per LEARNINGS "The first settings save on a fresh
    install scheduled nothing".
  - `Plugin::boot()`: `add_action( Scheduler::RETRY_HOOK, array( Scheduler::class, 'run_retry' ), 10, 1 )`
    — WP-Cron dispatches with `do_action_ref_array( $hook, $args )`
    (`wp-cron.php`), so the argument arrives only if one is accepted.
  - `Runner::run()` gains a fourth parameter `?string $for_date = null`: the day
    a retry was booked for. It is used for the log row when the report cannot be
    built at all (in place of the site's yesterday, which is a guess), and it is
    what task 3 compares the built report against.
  - On a failure, `Runner` books the retry through `Scheduler::schedule_retry()`
    while the attempt just spent is below `Settings::max_attempts()` — the
    step's `attempt < max_attempts`: with the default 3 a day costs three
    attempts and two retries, which is what DECISIONS calls "a maximum of 3
    attempts".
  `docs/DATA-MODEL.md` (the retry hook carries the date), `FEATURE.md` → Data /
  Interfaces and `docs/ARCHITECTURE.md` → Data flows are updated in the same
  commit.
  → `feat(gatb): try a failed report again an hour later`

- [x] **3. The last attempt tells the chat, and the day is let go.** In `Runner`,
  a failure that books no retry is the end of that day:
  `MessageRenderer::render_failure( $date )` goes to the configured chat through
  `TelegramClient`, `RunLog::reset_attempt()` puts the counter back to 0 so the
  next day starts at 1, and `last_report_date` is left alone — the day stays
  unsent, it is only no longer being tried. The notice is best effort: a
  `TelegramException` from it is caught and never rethrown.
  - **One row per run stays true.** The log row is written after the notice is
    attempted, and its message is the mapped reason plus one sentence saying
    that the chat was told, or that the notice could not be delivered either
    (with its own mapped reason). That is where "a failure of the notice itself
    is only logged" lands; a second row for the same run would say the same
    thing twice.
  - **A retry whose day has moved on.** All GA date ranges are relative and are
    resolved in the property's zone (FEATURE.md → Invariants, DECISIONS "Report
    content and comparison baselines"), so after the property's midnight the day
    a retry was booked for can no longer be read — the report that comes back is
    about the next day. `Runner` therefore compares the built report's date with
    `$for_date` and, when they differ, sends the notice for `$for_date`, logs
    the original day with the reason, resets the counter and books nothing: no
    later retry could do better, and the report it happens to hold belongs to
    the run the daily event will make at the configured time. This is what "the
    retry callback uses the date it was scheduled with, not 'yesterday' at retry
    time" can mean without making the ranges absolute, which the invariant
    forbids.
  - Two sentences that this task makes false are corrected with it: the
    `max_attempts` field description on screen `Settings` ("How often a failed
    report is tried again…" — with `attempt < max_attempts` the number is
    attempts, not retries) and `readme.txt`'s two "the retries arrive in the
    next release" lines. The readme's full rewrite stays Step 3.
  `docs/DECISIONS.md` gains the entry this settles (what starts a retry chain,
  and what a retry does when its day has passed); `docs/ARCHITECTURE.md` →
  Integrations loses "the retries follow in Sprint 2" on both rows;
  `docs/DATA-MODEL.md` records that `attempt` is also reset by the final
  failure; `FEATURE.md` → Invariants gains the notice.
  → `feat(gatb): tell the chat when a day could not be reported`

- [x] **4. Translations.** Regenerate `languages/ga-telegram-bridge.pot`,
  translate the new and changed entries in `ga-telegram-bridge-uk.po`, rebuild
  the `.mo` — the plugin's `CLAUDE.md` requires it of every step that adds or
  changes a string, and its DDEV note (read the `.pot` back on the host and
  check the entry count moved) is followed, per LEARNINGS "Two files looked
  unchanged because DDEV had not synced them yet".
  → `chore(gatb): translate the retry strings`

No task touches shared code: nothing outside
`wp-content/plugins/ga-telegram-bridge/` except the four project docs, and no
project tooling. The plugin hosts one feature.

### Files to create/change
**Create** — none. No new class: the retry lives in the two classes that own
scheduling and running, and every test file it needs exists.

**Change**
- `src/TelegramClient.php` (task 1)
- `src/Scheduler.php`, `src/Plugin.php`, `src/Runner.php` (task 2)
- `src/Runner.php`, `src/RunLog.php` (`reset_attempt()`), `src/Admin.php` (one
  field description), `readme.txt` (task 3)
- `tests/Unit/TelegramClientTest.php`, `tests/Unit/SchedulerTest.php`,
  `tests/Unit/RunnerTest.php`, `tests/Unit/RunLogTest.php`,
  `tests/Unit/PluginTest.php`
- `languages/ga-telegram-bridge.pot`, `-uk.po`, `-uk.mo`
- `docs/ARCHITECTURE.md`, `docs/DATA-MODEL.md`, `docs/DECISIONS.md`,
  `docs/features/ga-telegram-bridge/FEATURE.md`,
  `docs/features/ga-telegram-bridge/sprints/SPRINT-2-PLAN.md` (checkboxes)

Not touched: `ReportBuilder`, `Report`, `Dynamics`, `GaClient`, `GoogleAuth`,
`Settings` (`max_attempts` is validated and typed since Step 3),
`MessageRenderer` (`render_failure()` was built and tested in Step 7 and is
called here for the first time).

**Existing tests whose expectations change** (nothing loses an ability):
- `SchedulerTest::test_deactivation_clears_both_events` — two
  `wp_clear_scheduled_hook` calls become two `wp_unschedule_hook` calls.
- `RunnerTest` — its `setUp()` gains stubs for `wp_schedule_single_event` and
  `wp_unschedule_hook`, recording what was booked; the four existing failure
  tests keep their assertions (a manual run counts its attempt exactly as it
  does today).
- `AdminSendNowTest::test_a_refused_send_is_reported_as_an_error` — unchanged,
  because a manual run books nothing (Questions § 1).

### Tests to write
`TelegramClientTest` (on `error-too-many-requests.written.json`, `retry_after` 27):
1. a 429 followed by a success posts twice and returns, having asked to wait
   exactly 27 seconds
2. a 429 twice throws the mapped 429 sentence and posts exactly twice
3. a `retry_after` beyond `MAX_WAIT` waits not at all and posts once
4. a 429 without `parameters.retry_after` waits not at all and posts once
5. no other status is ever posted twice (401 stays one call)

`SchedulerTest`:
6. `schedule_retry()` books one single event, an hour after the given moment,
   carrying the date as its only argument
7. `run_retry( '2025-09-09' )` runs as `retry` for that day, with the guard on
8. `run_retry()` without an argument still runs (a hand-scheduled event)
9. `clear()` removes both hooks through `wp_unschedule_hook()`, so an event
   carrying arguments goes too

`RunnerTest` (the chain runs for real against the recorded responses, as in
Sprint 1):
10. a failed cron run books a retry an hour later for the day it was about, and
    counts the attempt
11. the attempt that reaches `max_attempts` books nothing, sends the notice to
    the configured chat and resets `gatb_state.attempt` to 0
12. the notice's own refusal is caught: the run still returns one `failed` row,
    whose message names both reasons
13. a retry whose day has moved on (the report comes back for the following
    day) logs the **original** date, sends the notice for it and books nothing
14. a failed manual run books nothing and sends no notice (Questions § 1)
15. success resets the counter (Sprint 1 asserts it; kept explicit because the
    step asks for it)
16. negative — neither the bot token nor a fragment of the service-account key
    appears in what the failure notice sends or in anything `gatb_log` stores

`RunLogTest`:
17. `reset_attempt()` sets the counter to 0 and leaves `last_report_date` and
    `last_cron_hit` where they were

`PluginTest`:
18. the retry hook is registered on `Scheduler::run_retry` and accepts one
    argument

### Docs to update
- `docs/ARCHITECTURE.md` — Integrations: the Telegram row gains the one in-run
  wait on 429, and both plugin rows lose "the retries follow in Sprint 2" for
  what actually happens (retry an hour later while attempts remain, then one
  notice to the chat); Data flows: the "Daily GA report" flow gains its failure
  half (the single event `gatb_retry_report` with the date it carries)
- `docs/DATA-MODEL.md` — `gatb_state.attempt` is also reset by the final
  failure, not only by a success; the cron hooks paragraph records that
  `gatb_retry_report` carries the day it is for
- `docs/DECISIONS.md` — one entry: which runs start a retry chain, and what a
  retry does when the day it was booked for is no longer readable
- `docs/features/ga-telegram-bridge/FEATURE.md` — Invariants (a day is retried
  at most `max_attempts` times and then reported once to the chat), Interfaces
  (the retry hook's argument)
- At the close: `docs/WORKLOG.md`, the verification guide, the sprint tick.
  `docs/LEARNINGS.md` only if a failure mode surprises during the step.

### Checks
- **ANTI-PATTERNS:** none violated. The list is theme-shaped (ACF groups and
  blocks, `service-city`, city branching, `assets/`, ops directories, post-type
  registration, `mu-plugins/`, Kyiv-only changes on `master`, CF7 ids); this
  step changes three plugin classes and one field description. The one rule that
  reaches the plugin — no globally installed tooling — is untouched: no
  dependency is added, so core rule 4 is not in play either.
- **Docs vs reality:** four mismatches, all settled here:
  1. **Where the retry is scheduled.** The step says "In `Runner`: … schedule
     `wp_schedule_single_event`"; the plugin's `CLAUDE.md` says `Scheduler` is
     the only class that schedules or clears events. The area convention wins on
     placement — `Runner` asks, `Scheduler` books — and the behaviour the step
     describes is unchanged.
  2. **`wp_clear_scheduled_hook` does not clear an event with arguments.** Step 1
     shipped `Scheduler::clear()` with it, which was right while nothing carried
     arguments and stops being right in task 2; core is explicit
     (`wp-includes/cron.php`), and `wp_unschedule_hook()` is the function that
     clears a hook whatever its events carry. Fixed in the task that creates the
     situation, not left for `uninstall.php` in Step 3 — which will need the same
     function.
  3. **`max_attempts` counts attempts, not retries.** The step's formula
     (`attempt < max_attempts`) and DECISIONS ("a maximum of 3 attempts") agree;
     the field description shipped in Step 3 and one line of `readme.txt` say
     "how often a failed report is tried again", which is one less. The formula
     wins and the two sentences are corrected in task 3.
  4. **A retry cannot re-read a day that has passed.** The step asks the retry
     to use "the date it was scheduled with"; FEATURE.md → Invariants and
     DECISIONS "Report content and comparison baselines" fix every GA date range
     as relative, resolved in the property's zone. Absolute ranges would reopen
     a fixed decision, so the date is what the run reports **about** — the log
     row and the notice — and a retry that finds the day gone ends the chain
     instead of sending another day's numbers under yesterday's date.
  Two observations that change no task: a `daily` event fires every 86 400 s, so
  after a DST change the report arrives an hour off local time until the
  settings are saved again — re-anchoring is in neither this step nor DECISIONS
  and belongs to the retro or an `/adhoc`, and it is unrelated to the retry
  chain, whose events are single ones an hour out; and screen `Settings` says
  nothing about a pending retry, which no step asks for — the run log's `retry`
  rows and `wp cron event list` are where it shows.
- **Design:** n/a — DECISIONS "No UI design phase; message format and
  configurable blocks" forbids a design phase and `docs/DESIGN.md` changes for
  this feature; the one screen string this step corrects is recorded in
  `FEATURE.md` → UI, which already describes the Schedule section.
- **Check command:** `bin/check.sh` (`docs/TECH-STACK.md` → Check command) —
  exists and is green on `master` right now: `OK (238 tests, 748 assertions)`,
  PHPCS + PHPStan level 8 clean, 108 theme files linted.
- **Not locally verifiable:** the **arrival** of the failure notice in a real
  chat, and a real 429. Everything else the step promises is verifiable on the
  dev site with nothing configured: `max_attempts` = 2, a broken property id,
  `ddev wp cron event run gatb_daily_report` → one `failed` row plus a
  `gatb_retry_report` event an hour out carrying the date (`wp cron event list`),
  `ddev wp cron event run gatb_retry_report` → a second `failed` row, trigger
  `retry`, the counter back at 0 and no third event. With no bot token that last
  row also proves the best-effort branch, because the notice itself is refused
  and says so in the same row. Seeing the notice arrive needs the bot token and
  chat id that no install has yet — the same gap Step 1 recorded, closed by Step
  4 and the sprint boundary. A real 429 cannot be provoked (one bot, one message
  a day), so the fixture stays `*.written.json` per `docs/TESTING.md`.

### Questions / ambiguities

1. **Does a failed *Send now* start the retry chain?** The step says "on
   {exception} … schedule … while `attempt < max_attempts`" without naming a
   trigger, and DECISIONS says "the run schedules a retry"; both were written
   about the scheduled report, and `Runner` has three triggers.
   - **Only `cron` and `retry` (recommended).** *Send now* stays what DECISIONS
     calls it, "a manual, logged action": the administrator is looking at the
     screen, gets the mapped reason as a notice and can press again. Two
     concrete reasons beyond taste: a manual run bypasses the date guard, so a
     retry booked by one for a day already delivered would wake up an hour later,
     hit the guard, log nothing and leave the counter raised for the next real
     day; and an administrator testing a wrong property id three times would
     have the plugin tell the owner's chat that the day could not be reported.
     Failures still count — `gatb_state.attempt` keeps rising exactly as it does
     today, so a day that failed by hand and then by cron reaches its last
     attempt sooner, which is true rather than convenient. Tasks: one condition
     on the trigger in `Runner` and test 14.
   - **Every run.** The literal reading of the step. Tasks: the condition and
     test 14 go; `AdminSendNowTest` gains a `wp_schedule_single_event` stub, and
     its refused-send test then books a retry the assertion has to allow for.
   Recommendation: **only `cron` and `retry`**.
   *Resolved: approved as recommended — only a `cron` or `retry` run books a
   retry or sends the failure notice; a failed manual run counts its attempt
   and is logged, exactly as it is today.*

## Plan — Sprint 2, Step 3: Lifecycle, readme, translations   (status: closed)

### Branch
`ga-telegram-bridge/sprint-2-runs-by-itself` ← `master`
(root `CLAUDE.md` → git model: simple — task branch → `master`; the name is the
**Branch** line of `SPRINT-2.md`. Recreated from `master`, deleted at the close,
as in Steps 1 and 2.)

### Tasks (ordered)

- [x] **1. Deleting the plugin leaves nothing behind.** New
  `wp-content/plugins/ga-telegram-bridge/uninstall.php`, the file core includes
  when a plugin is deleted from wp-admin:
  - It deletes the three options `gatb_settings`, `gatb_state`, `gatb_log`, the
    transient `gatb_google_access_token`, and both cron events through
    `wp_unschedule_hook( 'gatb_daily_report' )` / `( 'gatb_retry_report' )` —
    the function Step 2 established, because `wp_clear_scheduled_hook()` matches
    only events registered without arguments and a pending retry carries its day.
    That list is exactly what the plugin owns: the audit for this plan
    (`grep` over `src/` for every `add_option`, `update_option`, `set_transient`)
    finds nothing else, and the one other transient the code touches —
    `settings_errors` — is **core's own**, written by `wp-admin/options.php` the
    same way and expiring in 30 seconds, so the plugin must not delete it.
  - **The names are literals in this file, not class constants.** Core
    `define()`s `WP_UNINSTALL_PLUGIN` and `include`s uninstall.php with the
    plugin itself unloaded (`wp-admin/includes/plugin.php` → `uninstall_plugin()`,
    read while planning), so there is no autoloader and no `Settings::OPTION` to
    read. What keeps the literals honest is the test: it compares every deleted
    key with the class constant it must equal, so a renamed option fails the gate.
  - The file guards with `if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }` —
    the WordPress convention. That line cannot have a unit test (an `exit` inside
    the suite would end the run), so it is a negative check of the manual guide
    instead: requesting the file over HTTP deletes nothing.
  - Single-site cleanup, like the rest of the plugin: `Plugin::activate()`
    already ignores `$network_wide` and nothing here is network-aware. A
    multisite network would keep the rows of its other sites — no document asks
    for more, and neither Dovira install is a network.
  The other half of the step's first task — "deactivation clears cron only,
  settings survive" — was built in Sprint 2 Step 1 and is asserted by
  `PluginTest::test_deactivation_clears_the_schedule_and_nothing_else`; nothing
  to change, and the guide re-checks it.
  `docs/DATA-MODEL.md`, `FEATURE.md` → Data, the plugin's `CLAUDE.md` and
  `docs/TESTING.md` are updated in the same commit.
  → `feat(gatb): remove every trace when the plugin is deleted`

- [x] **2. The readme sets a strange site up from scratch.** `readme.txt`
  rewritten into the WordPress readme format — `== Description ==`,
  `== Installation ==`, `== Frequently Asked Questions ==`, `== Changelog ==` —
  around what is already there (Steps 3, 5, 7, 8 and Sprint 2 wrote the field
  descriptions, the buttons, the run log, the schedule and the retries; that
  material is kept, not rewritten for its own sake — `SPRINT-1-CLOSE.md` →
  Contradiction 3). What this task adds is what a reader cannot work out alone:
  - **Google Cloud, in order:** pick or create a project → enable the *Google
    Analytics Data API* (and only that one — DECISIONS ""Check GA" proves the
    connection through the Data API, not the Admin API") → create a service
    account → create a JSON key for it → in GA4 give that service account's
    e-mail address at least *Viewer* on the property → read the **numeric
    property id** from Admin → Property settings, which is not the measurement
    id `G-…`.
  - **Telegram, in order:** create a bot with BotFather and keep the token →
    decide the recipient (a person must write to the bot once; a channel needs
    the bot added as an administrator) → find the numeric chat id, with the
    `getUpdates` call written against a placeholder token, never a real one.
  - **The two `wp-config.php` constants** as the recommended place for the key
    and the token, with the reason stated: what is in the options row travels in
    every database dump.
  - **WP-Cron**, with the recipe the step asks for: what WP-Cron does and does
    not guarantee, and for `DISABLE_WP_CRON` installs an actual crontab line
    calling `wp-cron.php`, next to the screen's own warning.
  - **Data freshness**, written from what this project knows rather than from a
    number nobody here measured (LEARNINGS "An option was put to the user with a
    benefit that had never been measured"): the report covers *yesterday in the
    property's own reporting time zone*, every range is relative and resolved by
    Google, the day is sent once and never revised, so figures that Google is
    still processing can move after the message was sent. Any window quoted for
    Google's own processing is attributed to Google's documentation in the
    sentence itself.
  - **Requirements** in one place: WordPress, PHP 8.1, the `openssl` extension
    (refused at activation without it), outbound HTTPS to `oauth2.googleapis.com`,
    `analyticsdata.googleapis.com` and `api.telegram.org`.
  The `== Changelog ==` entry for 0.1.0 stops saying "plugin skeleton" and says
  what 0.1.0 contains; the version is **not** bumped and `Requires at least: 7.1`
  is left alone — DECISIONS parks the minimum-WordPress question for packaging,
  which `SPRINT-2.md` → Out of scope excludes.
  → `docs(gatb): write the setup guide into readme.txt`

- [x] **3. Translations.** Regenerate `languages/ga-telegram-bridge.pot` per the
  plugin's `CLAUDE.md` and read it back on the host (LEARNINGS "Two files looked
  unchanged because DDEV had not synced them yet"). **The expected outcome is
  that nothing moved:** this step adds no `__()` anywhere — `uninstall.php` has
  no user-facing text and `readme.txt` is not scanned — so the 122 entries should
  come back as 122 with only `POT-Creation-Date` different, in which case the
  regeneration is discarded and the task closes with no commit. If any entry did
  move, it is translated in `-uk.po`, the `.mo` is rebuilt and all three files
  are committed.
  → `chore(gatb): translate …` — **or no commit**, with the entry count recorded
  in the close report.

No task touches shared code: nothing outside
`wp-content/plugins/ga-telegram-bridge/` except three project docs. The plugin
hosts one feature.

### Files to create/change
**Create**
- `wp-content/plugins/ga-telegram-bridge/uninstall.php`
- `wp-content/plugins/ga-telegram-bridge/tests/Unit/UninstallTest.php`

**Change**
- `readme.txt` (task 2)
- `wp-content/plugins/ga-telegram-bridge/CLAUDE.md` — the area's Data bullet
  gains why uninstall.php spells its names out; listed here deliberately, after
  LEARNINGS "The plan's file list missed the code area's own CLAUDE.md again"
- `docs/DATA-MODEL.md`, `docs/features/ga-telegram-bridge/FEATURE.md`,
  `docs/TESTING.md`
- `docs/features/ga-telegram-bridge/sprints/SPRINT-2-PLAN.md` (checkboxes)
- `languages/*` only if the `.pot` entry count moves (task 3)

Not touched: every class in `src/`. This step adds no runtime code path —
`uninstall.php` is never loaded while the plugin runs.

### Tests to write
`UninstallTest` (new; it defines `WP_UNINSTALL_PLUGIN` and `include`s the file
once per test — the file declares no function and no class, so including it
repeatedly is safe):
1. the three options are deleted, and the assertion names them through
   `Settings::OPTION`, `RunLog::STATE_OPTION` and `RunLog::LOG_OPTION`, so
   renaming an option without changing `uninstall.php` fails the gate
2. the transient `GoogleAuth::TRANSIENT` is deleted
3. both `Scheduler::DAILY_HOOK` and `Scheduler::RETRY_HOOK` go through
   `wp_unschedule_hook()` — the function that removes an event whatever
   arguments it carries
4. negative: **nothing else** is deleted — the set of keys passed to
   `delete_option()` is exactly those three, so core's `settings_errors`
   transient, another plugin's rows and WordPress's own options are untouched
5. negative: `wp_clear_scheduled_hook()` is never called, which is how the
   Step 2 lesson stays fixed

`PluginTest` already covers the deactivation half
(`test_deactivation_clears_the_schedule_and_nothing_else`: both hooks cleared,
`delete_option()` never called) — no change.

### Docs to update
- `docs/DATA-MODEL.md` — the line "`uninstall.php` (Sprint 2) removes both
  options, the settings and the transient" becomes the exact list, the two cron
  events included, and says what deactivation does instead
- `docs/features/ga-telegram-bridge/FEATURE.md` → Data — `uninstall.php` exists
  and is named as the one thing that removes the feature's data; Interfaces is
  unchanged, because this step adds no hook (the step's own docs line makes that
  conditional)
- `wp-content/plugins/ga-telegram-bridge/CLAUDE.md` — one line: uninstall.php
  runs with the plugin unloaded, so it spells the names out and a unit test holds
  them to the constants
- `docs/TESTING.md` — one sentence: the fixed suite order is about the `GATB_*`
  secret constants; `UninstallTest` defines `WP_UNINSTALL_PLUGIN`, which no other
  test reads, so it stays in the first suite
- At the close: `docs/WORKLOG.md`, the verification guide, the sprint tick.
  `docs/DECISIONS.md` only if something is decided that these tasks do not
  already carry.

### Checks
- **ANTI-PATTERNS:** none violated. The list is theme-shaped (ACF groups and
  blocks, `service-city`, city branching, `assets/`, ops directories, post-type
  registration, `mu-plugins/`, Kyiv-only changes on `master`, CF7 ids); this step
  adds one plugin file, one test class and prose. The one rule that reaches the
  plugin — no globally installed tooling — is untouched: no dependency is added,
  so core rule 4 is not in play either.
- **Docs vs reality:** seven items, all settled here, none of them a question:
  1. **Half of task 1 is already shipped.** "Deactivation clears cron only" was
     built in Step 1 and hardened in Step 2; this step adds only `uninstall.php`.
  2. **The readme overlaps** (`SPRINT-1-CLOSE.md` → Contradiction 3). Everything
     the step lists except the Google Cloud and Telegram walkthroughs, the cron
     recipe and the freshness note is already written; task 2 keeps that text and
     restructures around it rather than writing it twice.
  3. **`uk`, not `uk_UA`** (Contradiction 2). The locale on these installs is
     `uk`; a `uk_UA` file would never load, and the strings of Sprints 1–2 were
     translated step by step, so task 3 is a confirmation rather than a backlog.
  4. **Seven `msgstr` are empty on purpose.** They are the plugin name, the
     author, the author URI and the brand labels *GA → Telegram*, *Google
     Analytics*, *Telegram*, *Google Analytics → Telegram*, whose Ukrainian is
     the same text; gettext falls back to the source, so the screen is fully
     Ukrainian already. Translating them would add noise, not language.
  5. **`docs/DATA-MODEL.md` undercounts what uninstall removes** — its current
     line omits the two cron events. Corrected in task 1.
  6. **Nothing in this project has measured GA4's own data freshness.** The
     Sprint 1 spike measured request latency (185–356 ms, ~610 ms, ~923 ms), not
     how long Google keeps processing a day. The note therefore describes the
     mechanism — yesterday in the property's zone, relative ranges, one message
     per day, never revised — and attributes any processing window to Google's
     documentation in the sentence that carries it.
  7. **The version fields stay as they are.** DECISIONS parks lowering
     `Requires at least: 7.1` as a packaging question and `SPRINT-2.md` → Out of
     scope excludes packaging, so `readme.txt` keeps its header and the changelog
     entry describes 0.1.0 instead of bumping it.
  One observation that changes no task: the dev install now keeps the
  service-account key and the bot token in `gatb_settings` rather than in the two
  `wp-config.php` constants, so the uninstall check in the guide is destructive
  unless the row is copied first — the guide does that with `wp eval`, without
  ever printing the row (LEARNINGS "A whole options row was printed to read two
  of its fields").
- **Design:** n/a — DECISIONS "No UI design phase; message format and
  configurable blocks" forbids a design phase and `docs/DESIGN.md` changes for
  this feature, and this step changes no screen at all.
- **Check command:** `bin/check.sh` (`docs/TECH-STACK.md` → Check command) —
  exists and is green on `master` right now: `OK (255 tests, 815 assertions)`,
  PHPCS + PHPStan level 8 clean, 108 theme files linted.
- **Not locally verifiable:** **a fresh install configured by following only the
  readme, ending in a successful *Send now*.** Every other promise of this step
  is checked on the dev site and written into the guide: deactivation leaves the
  options and takes both events, `wp plugin uninstall ga-telegram-bridge
  --skip-delete` (the same code path wp-admin's *Delete* takes, without removing
  the files this repository versions) leaves no `gatb_*` row, no `gatb_*` event
  and no transient, and a direct HTTP request to `uninstall.php` deletes nothing.
  The readme-only setup is the reader's own run, because it means typing in a
  service-account key and a bot token — credentials this session never handles —
  and because a readme is only proven by someone who does not already know the
  answers.

### Questions / ambiguities
none
