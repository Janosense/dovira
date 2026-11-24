# Verification — ga-telegram-bridge, Sprint 1, Step 2

**Spike: service account → GA4 Data API** · written at close on 2026-09-09
Branch merged: `ga-telegram-bridge/sprint-1-report-on-demand` → `master`

What this step promises: with your service-account key in place, the plugin's future
auth path — RS256 JWT → token exchange → Data API — really works against the Kharkiv
property, and the pitfalls of that path are written down (`docs/LEARNINGS.md`, entry
"Sprint 1 spike findings"). The probe itself is throwaway: nothing in `master` uses it.

The spike is still on disk on purpose, so these checks can be run. It is removed at the
**sprint boundary**, not now — see §5.

Run everything from the repo root.

## 1. The number matches GA4 (the step's main check)
```bash
ddev wp eval-file wp-content/plugins/ga-telegram-bridge/spike/ga-probe.php happy
```
Expected: `token: status 200`, `runReport: status 200`, and a line

```
  activeUsers yesterday = <n>   ← compare with GA4 → Reports
```

Open GA4 → Reports for the Dovira Kharkiv property (id `533779496`), set the date range to
**yesterday**, and read *Active users*. The two numbers must be the same. On the run at
close (2026-09-09, reporting yesterday) it was **66**.

Note the report's own time zone: every response carries `metadata.timeZone`, `Europe/Kiev`
for this property, so "yesterday" is GA's day, not the server's.

## 2. The four date ranges come back identifiable
```bash
ddev wp eval-file wp-content/plugins/ga-telegram-bridge/spike/ga-probe.php batch
```
Expected: `dimension headers  dateRange` and four rows `date_range_0…date_range_3`.
**Look at the order** — it is by value, not by range (at close: `date_range_3=1577`,
`date_range_2=1559`, `date_range_1=394`, `date_range_0=66`). That is the finding Step 6
must respect: rows are keyed by the `dateRange` value, never by position.

## 3. The errors are the ones we will map in Step 4
```bash
ddev wp eval-file wp-content/plugins/ga-telegram-bridge/spike/ga-probe.php errors
```
Expected, in order:
- `bad-property-id` → **400** `INVALID_ARGUMENT`
- `no-access-property` → **403** `PERMISSION_DENIED`
- `bad-bearer-token` → **401** `UNAUTHENTICATED`
- `tampered-signature` → **400** `{"error":"invalid_grant","error_description":"Invalid JWT Signature."}`

The last one is the negative check that matters most: a **broken signature must never
produce a token**. If that line ever shows 200, stop — the JWT is not being verified and
nothing else about the auth path can be trusted.

## 4. Clock skew and quota (informational)
```bash
ddev wp eval-file wp-content/plugins/ga-telegram-bridge/spike/ga-probe.php skew
ddev wp eval-file wp-content/plugins/ga-telegram-bridge/spike/ga-probe.php quota
```
Expected: `iat` +300 s and −3600 s accepted, −7200 s rejected with `invalid_grant`
("short-lived token (60 minutes)"); and 15 sequential calls all `200` with a
`propertyQuota` line showing ~200 000 tokens/day remaining. A quota error is **not**
reproducible from one process — "documented, not observed" is the correct outcome here,
not a failure.

## 5. Nothing secret reached git — and still hasn't
```bash
git check-ignore -v wp-content/plugins/ga-telegram-bridge/spike/service-acount.json
git status --porcelain | grep spike || echo "spike/ invisible to git — correct"
git log -p --all -- wp-content/plugins/ga-telegram-bridge | grep -c "PRIVATE KEY"   # must print 0
```
Expected: the first prints the matching `.gitignore` rule, the second the "invisible"
message, the third `0`. This is the sprint's Definition-of-Done check done early.

**Negative check:** the gate must stay green even though an unpolished, non-WPCS file sits
inside the plugin directory —
```bash
bash bin/check.sh; echo "exit=$?"    # exit=0
```
If this ever fails on `spike/*`, the PHPCS exclusion added in this step was lost.

## 6. Cleanup, at the sprint boundary (not now)
The sprint's Definition of Done says "Spike code removed; no secret or key file in the repo".
When Sprint 1 is done — after Step 4 has taken its fixtures from `spike/dumps/` — remove
both the probe and your key:
```bash
rm -rf wp-content/plugins/ga-telegram-bridge/spike
```
A copy of the six recorded responses is kept outside the repo at `~/dovira-gatb-spike/dumps/`,
so Step 4 can still build `tests/fixtures/ga/*.json` from them afterwards.
