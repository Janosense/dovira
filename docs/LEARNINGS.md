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
