# Learnings — Dovira

<!-- Harness defect log. Every time the agent did the wrong thing (overreach,
     misread instruction, ignored a rule, broken assumption) — it goes here,
     framed as a PROCESS defect, not a one-off annoyance. At sprint boundaries
     the user reviews this file and transfers fixes into the playbook repo
     (new playbook version). This is the improvement loop. At the retro every
     entry's "Transferred to playbook" is filled: a version, "local" (fixed in
     this project only) or "n/a" — "pending" survives only until the next
     retro. -->

Entry format:

## {{YYYY-MM-DD}} — [{{feature}}] {{What happened, one line}}
- **Incident:** {{what the agent did vs. what was expected}}
- **Root cause:** {{which instruction was missing, ambiguous, or overridable}}
- **Fix applied here:** {{change to this project's CLAUDE.md/commands/docs}}
- **Transferred to playbook:** {{version, or "pending"}}

---

## 2026-09-09 — [ga-telegram-bridge] A whole options row was printed to read two of its fields, and it held the real key and the bot token
- **Incident:** Starting the live check of Sprint 2 Step 2, I ran `ddev wp option get gatb_settings --format=json` to see how the dev install was configured. The row came back with the real service-account private key and the real Telegram bot token in it, both now in the session transcript. Core rule 7 says secrets are never logged; the plugin is built so that nothing but `Settings`' getters ever touches those two fields, and the one command that ignores that design is `wp option get` on the row that contains them.
- **Root cause:** The rule was read as being about the code (do not write a secret into a log, a notice or an exception) and not about the agent's own shell. Nothing in the project's instructions says how to *look* at a settings row that holds credentials, and the obvious command is the wrong one. The step's own plan had even recorded that this install was unconfigured, so the row was expected to be empty — an expectation is not a safeguard.
- **Fix applied here:** Everything afterwards went through the typed getters (`ddev wp eval 'echo GaTelegramBridge\Settings::property_id();'`) or `wp option patch update gatb_settings <key> <value>` for one key at a time, and the verification guide for this step uses only those. Rule taken: **never print a row that can contain a credential — read the fields you need by name, and write them one key at a time.** It applies to `wp option get`, to database dumps and to `print_r` of anything under `gatb_settings`. The user was told the two values had been displayed so they can rotate them.
- **Transferred to playbook:** pending

## 2026-09-09 — [ga-telegram-bridge] The plan described the dev site from memory, and the dev site had moved on
- **Incident:** The Step 2 plan's "Not locally verifiable" section stated, in detail, that the dev install was back at defaults, that `spike/service-acount.json` had been deleted and that a `sent` row would need the property id, the key, the token and the chat id "entered again by hand". By then the install had been fully configured — a bot, a chat, a real key — and had delivered a report the evening before. So the plan deferred, as unprovable, exactly the thing the site could prove; it took the user's answer to one question to turn that around and verify the failure notice against the real chat.
- **Root cause:** `/plan-step` sends the planner into the code and the docs, and I inspected both thoroughly — but the claim was about the *environment*, and the environment is not in the repository. I carried it over from Step 1's close instead of asking the site: one `wp eval` with the getters would have said what was configured. Facts about a live install age faster than anything in git.
- **Fix applied here:** The live check ran against the real configuration and the notice was proven end to end; the verification guide now has two paths, one for a configured install and one for an empty one. Rule taken: **a plan that claims something about a running environment checks it there while planning** — never from a previous step's report — and, for this project, that check reads the typed getters, not the row (see the entry above).
- **Transferred to playbook:** pending

## 2026-09-09 — [ga-telegram-bridge] The plan's file list missed the code area's own CLAUDE.md again
- **Incident:** The Step 2 plan listed every file the four tasks would touch except the plugin's `CLAUDE.md`, whose Cron section is where this project records how cron is used — and this step changed two of its rules (which triggers book a retry, and that an event carrying arguments has to be cleared with `wp_unschedule_hook()`). It was added afterwards, in a sixth commit outside the four planned tasks.
- **Root cause:** The identical mistake is already in this file from Sprint 1 Step 8 — "when a plan lists files, the code area's conventions are part of the source it derives them from, not only the sprint text" — and the rule did not survive into the next plan, because it lives in `LEARNINGS.md` rather than in the command that writes the file list. A rule that has to be remembered at the right moment is a rule that will be missed.
- **Fix applied here:** The convention is in `wp-content/plugins/ga-telegram-bridge/CLAUDE.md` (commit `305a006`) and is what Step 3's `uninstall.php` will need. The repeat is the point of this entry: the retro should move the rule into `/plan-step` — the area `CLAUDE.md` belongs in "Files to create/change" whenever a step changes a convention it states.
- **Transferred to playbook:** pending

## 2026-09-09 — [ga-telegram-bridge] The first settings save on a fresh install scheduled nothing, and the search for why started in the wrong place
- **Incident:** With the scheduler committed, 237 unit tests green and the gate passed, saving the settings on the dev site left no cron event at all — on exactly the state every new install starts in. The cause is one branch of core's `update_option()`: when the stored value still equals the option's registered default, it hands the write to `add_option()`, which fires `add_option_{$option}` and never `update_option_{$option}`. `register_setting()` gives `gatb_settings` the whole defaults array as its default, so a fresh install's first save always took that branch — and the plugin only listened for the other action. Before finding that, I spent about twenty minutes on the wrong hypothesis: the save worked from WP-CLI, so I assumed php-fpm was serving stale code, and reached for OPcache and DDEV's file sync instead of asking whether the callback ran. One `error_log()` at the top of the callback answered it in one request.
- **Root cause:** Two, both mine. The step plan asserted "hooked on `update_option_gatb_settings`" from what I remembered of `update_option()` rather than from the function — and WordPress core is checked into this very repository, four lines of `sed` away (`wp-includes/option.php`). And when the screen disagreed with the tests I theorised about the environment before instrumenting the decision point; "does my callback run at all" is one line and rules out half the search space.
- **Fix applied here:** Both actions now reschedule, with the reason in a comment and in the plugin's `CLAUDE.md` → Cron so the next screen does not lose it; `PluginTest` asserts both registrations. No unit test in this suite could have caught it — Brain\Monkey stubs `update_option()`, so the branch never runs — which is why the Step 7 rule (open the screen before calling a step implemented) earns its keep a second time. Two rules taken: **when a step depends on a WordPress hook firing, read the core function that fires it, in this repo, while planning**; and **when the screen contradicts the tests, prove which line runs before theorising about the environment.**
- **Transferred to playbook:** pending

## 2026-09-09 — [ga-telegram-bridge] Running a cron event by hand moved the daily slot
- **Incident:** After `ddev wp cron event run gatb_daily_report`, the screen said the next run was 17:21 tomorrow — not the 18:05 that had just been saved. Nothing was broken: WordPress reschedules a recurring event from the moment it actually ran (`wp_reschedule_event()` adds the interval to `time()` when the event was not yet due), so forcing a run moves the slot for good.
- **Root cause:** The step's verification asks for a forced run; a reader who then looks at "Next run" would take the shifted time for a defect of the plugin.
- **Fix applied here:** §3 of `verification/sprint-2-step-1.md` states it and has the reader save the settings again to put the time back.
- **Transferred to playbook:** n/a — project-technical.

## 2026-09-09 — [ga-telegram-bridge] Two files looked unchanged because DDEV had not synced them yet
- **Incident:** Regenerating the `.pot` inside the container printed `Success: POT file successfully generated.`, and the host copy still held the previous 101 entries a moment later. The `.po` was rebuilt from that stale file and would have shipped without the fourteen strings Step 8 adds — the screen would have been half English. It was caught only because the generator prints an entry count and the count had not moved. The same lag had already made a freshly written `.mo` invisible in Step 7.
- **Root cause:** DDEV syncs container writes to the host asynchronously, so a command that writes a file inside the container and a host-side script that reads it straight afterwards are not ordered. Nothing in the project's commands says so, and "Success" from the container reads like the file is there.
- **Fix applied here:** after any container-side write, read the file back on the host and check something that must have changed — an entry count, a byte size — before using it; noted in the plugin's `CLAUDE.md` next to the i18n commands. Second, smaller lesson: the step plan listed the files task 3 would touch without the translation files, although the plugin's own `CLAUDE.md` had said one step earlier that a new string means regenerating them. When a plan lists files, the code area's conventions are part of the source it derives them from, not only the sprint text.
- **Transferred to playbook:** pending — the second half; the sync lag itself is project-technical.

## 2026-09-09 — [ga-telegram-bridge] A screen was called finished without anyone opening it
- **Incident:** Step 7's *Preview* button passed 187 unit tests, the gate and three commits, and was broken on the screen. wp-admin prints the settings errors of every screen whose parent is Settings by itself — `admin-header.php` requires `options-head.php`, which calls `settings_errors()` — and it does so **before** the page callback runs. So the preview was printed twice: once by wp-admin as a single bold paragraph with the message's line breaks gone, once by the block the screen renders. The same mechanism had been showing the *Check GA* and *Check Telegram* notices **twice since Step 4**, through three closed steps and three verification guides, because each guide said "the notice appears" and never "appears once". Both were found in the first minute of actually opening the page in a browser.
- **Root cause:** Two of them. The plan reasoned about `settings_errors()` from the function's own source without asking who else calls it — the answer was one `grep` away and was never run. And nothing in the step protocol says that a step whose deliverable is a **screen** has to be opened before it is called implemented: `/do-step`'s gate is the check command, and unit tests with Brain\Monkey never load wp-admin, so no test in this suite can see the order in which wp-admin prints things.
- **Fix applied here:** The preview is taken out of the notices on `all_admin_notices`, which `admin-header.php` fires three lines before it requires `options-head.php`, and the screen no longer calls `settings_errors()` at all (recorded in the plugin's `CLAUDE.md` → Admin, so the next screen does not put the call back). The rule taken for this project: **a step that builds or changes a wp-admin screen is not implemented until the screen has been opened and its buttons pressed** — before `/close-step`, not during the user's manual verification. The `/close-step` guide for such a step states how many times each notice must appear, not merely that it appears.
- **Transferred to playbook:** pending

## 2026-09-09 — [ga-telegram-bridge] An option was put to the user with a benefit that had never been measured
- **Incident:** The gate ran out of memory again in Step 5. I stopped and offered three options, one of which — dropping `tests/` from PHPStan's paths — was described as making "memory fall well under 512M with no flag at all". The user chose it. It does not: measured afterwards, excluding the whole test suite saves **8 MB** (732 MB → 724 MB peak) and the run still crashes at 512M and at 576M, passing only from 640M, exactly as before. The memory is the WordPress stubs each worker loads, not the analysed files. I had to return with the measurement and ask a second time, and the user then took the option I had recommended first.
- **Root cause:** The three options were written from reasoning ("fewer files, less memory") while the *other* two numbers in the same table were measured. Nothing in `/do-step` says that when a stop puts choices to the user, every claim that distinguishes them has to be measured before it is offered — and a plausible mechanism reads exactly like a measured one once it is in a table next to real figures.
- **Fix applied here:** The rule taken: **an option's stated cost or benefit is measured before it is offered, or it is written as an estimate in so many words.** A stop is worth one extra minute of measuring; a wrong option costs the user a decision they then have to unmake. Concretely for this project: before proposing anything about the gate, run the variant and record the number, as was eventually done here (512M / 576M / 640M for both configurations).
- **Transferred to playbook:** pending

## 2026-09-09 — [ga-telegram-bridge] Two tests passed only because of the alphabet
<!-- Tooling finding rather than a harness defect, kept here because Steps 6 to 8 add many more
     test classes and will meet it again. -->
- **`define()` in one test class leaks into every class that runs after it.** `SettingsSecretConstantsTest` defines the real `GATB_*` secret constants, and a PHP constant cannot be undefined. `Settings::telegram_bot_token()` then returns that constant whatever `get_option()` is stubbed to. `GaClientTest` had been passing only because "G" sorts before "S"; the new `TelegramClientTest` sorts after it and every token assertion failed at once — on correct code.
- **The fix is configuration, not isolation.** `#[RunClassInSeparateProcess]` + `#[PreserveGlobalState(false)]` did **not** contain the constants and added three PHPUnit deprecations. What works is declaring the order: `phpunit.xml.dist` now has two suites, the constants class alone in the second. Written up in `docs/TESTING.md` → How to run.
- **Transferred to playbook:** n/a — project-technical finding, not a process defect.

## 2026-09-09 — [ga-telegram-bridge] The gate failed on code that had no errors, and said the wrong thing about why
- **Incident:** With Step 4's last test file added, `bin/check.sh` stopped passing: `Child process error: PHPStan process crashed because it reached configured PHP memory limit: 512M ... while running parallel worker`. The obvious reading — "the analysis needs more memory" — is wrong. The same analysis run single-threaded (`phpstan analyse --debug`) reports `[OK] No errors`. PHPStan sizes its worker pool from the CPU count, so on a 14-core machine it spawns many workers, each loading the WordPress stubs; the pool, not the analysis, exhausted the limit.
- **Root cause:** The gate's PHPStan configuration left the worker count to the machine, so the same commit passes or fails depending on how many cores the developer has — and the failure text points at memory rather than at concurrency. Nothing in the step protocol says what to do when the *gate itself* breaks for reasons unrelated to the code.
- **Fix applied here:** `phpstan.neon.dist` pins `parallel: maximumNumberOfProcesses: 2` (commit `e5c5cbb`), which makes the run identical on the host and inside DDEV whatever the core count; `docs/TECH-STACK.md` → Check command records why. The rule learned: when the gate fails, first re-run the failing tool **single-threaded** to find out whether the code or the tooling is at fault, and never raise a limit before knowing which. `/do-step` was stopped and the choice put to the user rather than changing project-level tooling inside a feature step — the tooling fix is its own commit, separate from the step's three.
- **Transferred to playbook:** pending

## 2026-09-09 — [ga-telegram-bridge] Two assumptions about the gate's own tools broke in Step 3
<!-- Tooling findings rather than a harness defect, kept here because the next four steps write
     many more tests and will meet both again. -->
- **PHPUnit 12 does not read metadata from doc comments.** `@dataProvider` (and `@covers`,
  `@group`, `@test`) are gone — the annotation is silently ignored and the test then fails with
  `ArgumentCountError: Too few arguments`. Use the attributes instead:
  `use PHPUnit\Framework\Attributes\DataProvider;` and `#[DataProvider( 'provider_name' )]`
  between the docblock and the method. Cost one red run in Step 3; Steps 4, 6 and 7 are the
  fixture-heavy ones.
- **PHPCS progress output counts batches, not files, when `parallel` is on.** With
  `<arg name="parallel" value="8"/>` the plugin's 10 files print as `5 / 5 (100%)`, which reads
  like half the tree was skipped. It is not: `vendor/bin/phpcs -q --report=json` lists all ten,
  and `--parallel=1` prints `10 / 10`. Do not "fix" a green gate on the strength of that number.
- **Transferred to playbook:** n/a — project-technical findings, not a process defect.

## 2026-09-08 — [ga-telegram-bridge] A pathspec commit silently dropped every new file
- **Incident:** The working tree carried staged deletions of `templates/*` made outside the session, so each task was committed with `git commit -- <paths>` to keep them out of the step's commits. For task 2 the new files had not been `git add`ed first: the commit succeeded but contained only the already-tracked plan file — the tooling commit had no tooling in it.
- **Root cause:** The assumption that a pathspec commit picks up untracked files under those paths. It does not: it commits only what is already in the index. Nothing in the step protocol asks for a check of what a commit actually contains.
- **Fix applied here:** `git add <paths>` before every pathspec commit and `git show --stat HEAD` after it; the empty commit was redone via `git reset --soft HEAD~1`. Standing rule for this project: after each task commit, read its stat output against the plan's "Files to create/change".
- **Transferred to playbook:** pending

## 2026-09-09 — [ga-telegram-bridge] Sprint 1 spike findings: the GA4 Data API through a service account
<!-- Technical findings, not a harness defect: SPRINT-1.md Step 2 routes the spike's pitfalls here.
     Observed against property 533779496 with a service account holding Viewer, via wp_remote_post. -->
- **Pitfall — `batchRunReports` rows are NOT in date-range order.** Several `dateRanges` in one request make GA add a `dateRange` dimension by itself (values `date_range_0..N`, numbered in request order), but the rows come back ordered by metric value: the four users ranges returned `date_range_3=1577, date_range_2=1559, date_range_1=394, date_range_0=66`. `ReportBuilder` must key rows by the `dateRange` dimension value and never by row index — an index-based parser would silently swap "yesterday" with "previous 28 days".
- **Not a pitfall — the `private_key` needs no newline repair.** `json_decode()` of the key file already yields real newlines, and `openssl_pkey_get_private()` accepts the string as-is (2048-bit key). The usual `str_replace( '\\n', "\n", … )` workaround is unnecessary here and would corrupt the key.
- **Clock skew:** `iat` 300 s in the future → accepted; `iat` 3600 s in the past (so `exp` = now) → accepted; `iat` 7200 s in the past → `400 invalid_grant` / "Token must be a short-lived token (60 minutes) and in a reasonable timeframe". So `exp` must stay within 60 minutes of now and must not be in the past; a host clock off by a few minutes is harmless.
- **Two different error shapes — one parser each.** The token endpoint answers with `{"error": "...", "error_description": "..."}`: a tampered signature gives **400** `invalid_grant` / "Invalid JWT Signature." (not 401). The Data API answers with `{"error": {"status": ..., "message": ...}}`: bad property id → **400 INVALID_ARGUMENT** "Invalid property ID: abc…"; a property the account cannot read → **403 PERMISSION_DENIED** "User does not have sufficient permissions for this property."; an invalid bearer token → **401 UNAUTHENTICATED** "Request had invalid authentication credentials…". `GoogleAuth` and `GaClient` therefore need separate error parsers (Step 4).
- **Quota — documented, not observed.** 15 sequential `runReport` calls all returned 200; a single PHP process cannot reach the concurrency limit. `returnPropertyQuota: true` reports the real headroom: `tokensPerDay` 200 000 (1 consumed per report), `tokensPerHour` 40 000, `concurrentRequests` 10, `serverErrorsPerProjectPerHour` 10. Google documents **429 `RESOURCE_EXHAUSTED`** for exceeding it. At ~6 tokens a day the plugin cannot come close, so quota handling stays a mapped error, not a design concern.
- **Every response carries `metadata.timeZone`** (here `Europe/Kiev`) and `currencyCode`, so the property's reporting time zone — the one that decides what "yesterday" means (FEATURE.md invariant) — can be read from the report itself rather than assumed.
- **Latency and token lifetime through `wp_remote_post`:** token exchange 185–356 ms with `expires_in` 3599 s, `runReport` ~610 ms, `batchRunReports` (4 ranges) ~923 ms. The 15 s / 20 s timeouts planned for Step 4 are generous.
- **`wp eval-file` evaluates the file inside a method**, so a script run that way cannot use `const`, `declare()` or `__DIR__` (the latter points at WP-CLI's own file) — build paths from `WP_CONTENT_DIR` and use closures instead of top-level functions. Relevant again for the `wp eval` verification snippets planned in Steps 6 and 8.
- **Transferred to playbook:** n/a — project-technical findings, not a process defect.
