# SPRINT 3 CLOSE — Trend at a glance (ga-telegram-bridge)

Written by `/close-sprint` (Phase 1) on 2026-09-16, from the files, the code and git.
Phase 2 settled the boundary the same day: the deploy is done and confirmed, and the October
clock change is carried. The sections after the Definition of Done are the Phase 1 snapshot.
It is the handoff to the sprint boundary, to the retro, and to whatever follows this feature.

## Definition of Done

| Item | Evidence |
|---|---|
| Every step closed via /close-step (report + verification guide + worklog) | **ticked** — Steps 1–3 are `### [x]` in `SPRINT-3.md` and `(status: closed)` in `SPRINT-3-PLAN.md`; close commits `0a326600`, `65774b3f`, `20c7ed25`; guides `verification/sprint-3-step-1.md` … `-3.md`; three WORKLOG entries of 2026-09-16 |
| `bin/check.sh` green — with `failOnRisky` in effect | **ticked** — simple git model: each step's task branch was merged into `master` (`7d338fe1`, `b4ce8e03`, `932f2715`) and no sprint branch exists, so the gate ran on `master` at `932f2715` on 2026-09-16 and exited 0: PHPCS clean, PHPStan level 8 `[OK] No errors`, `OK (294 tests, 1060 assertions)`, 108 theme files linted. `failOnRisky="true"` has been in `phpunit.xml.dist` since Step 1 (`932662e`), so "green" now excludes a test that asserts nothing |
| Docs match reality (DATA-MODEL, ARCHITECTURE, DECISIONS current) | **ticked** — `DATA-MODEL.md`'s `blocks` row lists six keys including `trend` and states the stored-vs-submitted default rule. `ARCHITECTURE.md` says a full report is **seven** GA4 reports in both rows, names `Sparkline` in the plugin row, and its "Daily GA report" flow carries the re-anchor. `FEATURE.md` has `trend` in Data, the trend line in UI, `visitors_by_day` in Interfaces and the re-anchor in Invariants. `DOMAIN.md` has the `trend` block and the term, with why a daily sum is not a period total. `TESTING.md` has the `failOnRisky` rule, the new `call-2` shape and the pinned-clock rule. `DECISIONS.md` gained "The tests' pinned clock belongs to the GA recordings" (2026-09-16), and the three 2026-09-16 entries written before the sprint have no contradicting text left: `ARCHITECTURE.md` still describing `dev.dovira.vet` as infrastructure is what the dev-site decision explicitly asks for |
| Merged to `master`; `master` merged into `kyiv`; both productions deployed by hand — the newest run-log row on each install shows `Розклад · Надіслано` for a morning after the deploy, with the trend line visible in the owner's chat | **ticked** — the repo half: the three step merges are on `master` (`7d338fe1`, `b4ce8e03`, `932f2715`) and `master` is merged into `kyiv` (`bc1be64b`), `bin/check.sh` exit 0 on both. The two hand deploys and the morning report carrying the trend line in the owner's chat on each install — confirmed by user 2026-09-16; nothing in the repo can show them |
| The October clock change passes without a shifted report | **carried to the next sprint boundary** — 2026-10-25 is five weeks after this close, and this item's own wording provides for exactly that: it is confirmed once the developer reads the run-log rows of 2026-10-25/26 on either production install. **This note is the record the next boundary picks up.** The mechanism itself is proven: a fixed-clock test over the real nights (25 h in October, 23 h in March) and, on the local install, an event put an hour early and corrected by one run |

## Built

Everything in `wp-content/plugins/ga-telegram-bridge/` (namespace `GaTelegramBridge`).
What Sprint 3 adds to what Sprint 2 left:

| File | What this sprint put there |
|---|---|
| `src/Sparkline.php` | **new.** Pure: `render( ?array $series ): string` over `▁▂▃▄▅▆▇█`, scaled between the series' own ends, a flat series (all zeros included) drawn at mid height, `null` or empty drawn as nothing |
| `src/Settings.php` | `BLOCKS` gains a sixth block `trend`, last so the request order of the others cannot move; `merge_blocks()` reads a **stored** row with `array_key_exists()`, so a key written before a block existed takes its default rather than `false`; the form path keeps the opposite rule in its own `submitted_blocks()` |
| `src/ReportBuilder.php` | a seventh request when `trend` is on — `activeUsers` by `date` over `28daysAgo`–`yesterday`, ordered by the dimension, `limit` 28 — and `visitors_by_day()`, which keys GA's rows by their own date and walks the 28 days ending on the report date, so an omitted day is a zero in its place. Seven requests split 5 + 2; two `batchRunReports` calls remain the ceiling |
| `src/Report.php` | `?array $visitors_by_day` as the fourteenth constructor parameter, appended last; `has_block( 'trend' )` |
| `src/MessageRenderer.php` | `trend()`: one line under *За 28 днів*, `<code>{28 characters}</code> {min}–{max}`, no heading and no blank line of its own; a switched-off block and an empty series are both refused before the numbers are read |
| `src/Scheduler.php` | `run_daily()` re-anchors the daily event in a `finally` — the next occurrence from the configured local time, not WordPress's fixed 86 400 s — and `reschedule()` takes the optional clock the rest of the plugin's entry points take. Only `DAILY_HOOK` is cleared, so a retry the same run booked survives |
| `src/Admin.php` | the sixth checkbox with `block_descriptions()`, printed in the `<span class="description">` the "(always sent)" note already used |
| `phpunit.xml.dist` | `failOnRisky="true"` — the gate hole `LEARNINGS` 2026-09-10 found and Sprint 2 deferred. Nothing had to be fixed: the suite was already clean under it |
| `tests/fixtures/ga/` | `batch-run-reports-daily-call-2.json` re-recorded against the live property (two reports, `devices` then `trend`, 28 dated rows); the one-report recording kept as `batch-run-reports-daily-call-2-no-trend.json` |
| `languages/` | 127 → 130 strings: the `Trend` label, its description, and the `{min}–{max}` range format, all translated |
| `tests/` | 269 → **294** tests: `SparklineTest` (new, 7), and new cases across `SettingsTest`, `ReportBuilderTest`, `AdminTest`, `MessageRendererTest` and `SchedulerTest` |
| `readme.txt`, `ga-telegram-bridge.php` | version **0.2.0** in the header and in `Stable tag`; the readme describes the trend line in ASCII and the changelog names both changes |

No new option, no table, no dependency. `gatb_settings.blocks` gains one key, which
existing rows acquire by reading rather than by migration.

## Not locally verifiable

- **How a monospace run of block characters draws in each Telegram client.** Different
  clients pick different monospace fonts; Step 2's guide takes one phone's screenshot, and
  the first scheduled morning after the deploy answers for the owner's own client.
- **The October clock change on a production install.** The arithmetic is proven over the
  real nights by a fixed-clock test (2026-10-25 is 25 hours, 2027-03-28 is 23) and the
  re-anchor is proven on the local install, but only the morning of **2026-10-26** proves it
  where it matters.
- **Both production installs running 0.2.0** — sections B–E of `PRODUCTION-CHECKLIST.md` were
  worked through for Sprint 2; this sprint changes no configuration, so the deploy is the
  files and nothing else.
- Still open from earlier sprints: **a fresh install set up from the readme alone**, **a GA4
  property with no traffic**, **activation without OpenSSL**, **the 429 quota mapping**.

## Deferred

- **The deploy itself**: `master` to Kharkiv and `kyiv` to Kyiv, by hand. No step may deploy
  (root `CLAUDE.md` → Git model).
- **Rotate the local install's service-account key and bot token.** Carried from Sprint 2 and
  more pressing now, not less: Step 1 re-recorded a live fixture through that same key.
  `SPRINT-3.md` → Out of scope calls it operational and the developer's task.
- **GA's own labels stay English** in the Ukrainian message (`mobile`, `Organic Search`) —
  deferred in Sprint 1, again in Sprint 2, and again by `SPRINT-3.md` → Out of scope.
- **`FEATURE.md` is 94 lines against the template's ≤ 80.** `SPRINT-3.md` → Risks suggested a
  later re-planning may move the message template into its own reference; this sprint added
  one invariant line and did not move it.
- **Packaging** (`Requires at least: 7.1`, distribution to other sites) — Out of scope in both
  sprints; see Contradiction 2.

## Contradictions

Cross-read of `FEATURE.md` (Roadmap, UI), `SPRINT-3.md` → Out of scope, `docs/DESIGN.md` and
`docs/DECISIONS.md`, and git where a file makes a claim about branches. There is no
`SPRINT-4.md`. Listed, not resolved.

1. **The screen registry.** `docs/DESIGN.md` → Screens is the project's registry and lists
   neither of this plugin's two screens, while DECISIONS "No UI design phase; message format
   and configurable blocks" forbids `DESIGN.md` changes for this feature. Unresolved since
   Sprint 1 Step 3, carried by Sprint 2 and named in `SPRINT-3.md` → Risks as needing a
   one-line decision either way. Still needs it.
2. **"Any WordPress site" versus `Requires at least: 7.1`.** `FEATURE.md` → Purpose says the
   plugin works on any WordPress site; the plugin header and `readme.txt` declare 7.1 —
   `Tested up to: 7.1` as well — which is the core version this repository ships. Carried
   from Sprint 2, untouched by this sprint's release commit.
3. **`origin/main`.** DECISIONS "Verification profile and deploy model" says `origin/main` is
   unused and `ARCHITECTURE.md` → Environments says it "is not part of this flow"; in git,
   `master`'s upstream is `origin/main`, the remote has no `master`, and `master` is now
   **24 commits ahead** of it — the whole of Sprint 3 and the two closes before it.
   `SPRINT-3.md` → Risks asked for this to be raised with the developer *before* the
   boundary; it reaches the boundary unsettled and needs a DECISIONS entry or a remote rename.
4. *Not a contradiction, but the fact whoever reads this next needs:* `FEATURE.md` → Roadmap
   ends at Sprint 3. Everything after it is under "Later (not planned)" — key events, a weekly
   digest, a PNG chart, translated GA labels, several properties or chats, packaging. There is
   no Sprint 4 to plan from.

## LEARNINGS

Fifteen entries are still `Transferred to playbook: pending` — thirteen carried from Sprints 1
and 2, and two written this sprint:

1. **The plan read the code and the tests, but not what the fixtures quietly rely on**
   (Step 1, 2026-09-16): the trend report is the first fixture whose rows are calendar dates,
   which the pinned clock silently contradicted; and a cost quoted to the developer mid-step
   was reasoned rather than checked.
2. **A number in the plan was true of the scenario the plan imagined, not of the test it
   asked for** (Step 3, 2026-09-16): the "at least 23 h ahead" assertion held for a run that
   fires at its configured time, not for the test as planned. The gate caught it on the first
   run.

Both are the same family and the retro should read them together. One older entry changed
state rather than staying pending: **"Running a cron event by hand moved the daily slot"**
(2026-09-09, `n/a — project-technical`) now carries a **Resolved** line — Step 3 fixed the
mechanism it described, so a forced run no longer moves the slot for good.
