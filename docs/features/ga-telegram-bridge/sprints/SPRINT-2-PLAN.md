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
