# Worklog — Dovira

<!-- Add-only project memory for the agent: entries are never edited or
     removed. Written ONLY by /close-step (and /adhoc for off-cycle tasks),
     newest entry at the TOP, directly under the entry format. A fresh Claude
     Code session reads the latest 5 entries at start (CLAUDE.md core rule 9).
     Keep entries 3–6 lines; this is a memory index, not a diary — details live
     in commits and verification guides. -->

Entry format:

## {{YYYY-MM-DD}} — [{{feature}}] Sprint {{N}} Step {{M}} — {{title}}
(ad-hoc tasks: `## {{YYYY-MM-DD}} — [adhoc] [{{feature}}] — {{title}}`)
- Changed: {{what, at module/feature level}}
- Decisions: {{key ones made or DECISIONS.md entries added, or "—"}}
- Open: {{unresolved questions carried forward, or "—"}}

---

## 2026-09-09 — [ga-telegram-bridge] Sprint 1 Step 2 — Spike: service account → GA4 Data API
- Changed: no production code. `spike/` (gitignored, PHPCS-excluded) holds a throwaway probe that ran the real path — RS256 JWT → token exchange → `runReport` / `batchRunReports` — against the Kharkiv property 533779496; six recorded responses kept for Step 4's fixtures, findings written to `docs/LEARNINGS.md`.
- Shared code: none touched. The only tracked changes are two ignore/exclude lines in the plugin and the LEARNINGS entry.
- Decisions: none — no finding contradicts a fixed decision, so DECISIONS.md is untouched. Key findings for later steps: `batchRunReports` returns rows ordered by metric value, so Step 6 must key them by the `dateRange` dimension; the token endpoint and the Data API use two different error shapes, so Step 4 needs a parser for each; the `private_key` needs no newline repair.
- Open: the quota 429 stays documented-but-not-observed (unreachable from one process; ~6 of 200 000 daily tokens used). The spike directory and the service-account key are still on disk — removed at the sprint boundary per the Definition of Done, not at this close, because the manual verification runs the probe.

## 2026-09-08 — [ga-telegram-bridge] Sprint 1 Step 1 — Delta-audit, plugin skeleton, check command
- Changed: new code area `wp-content/plugins/ga-telegram-bridge/` — plugin header (WP 7.1 / PHP 8.1), own PSR-4 autoloader, `Plugin::boot()` loading the textdomain, activation guard on the OpenSSL extension, dev-only PHPUnit/PHPCS/PHPStan tooling and 3 unit tests; plus the project's first check command `bin/check.sh` (PHPCS → PHPStan → PHPUnit in the plugin, then `php -l` over the theme's 108 files).
- Shared code: none touched — the plugin references no theme code, function, option or hook. `bin/check.sh` is new project-level tooling that gates every feature from now on.
- Decisions: DECISIONS "The check command runs on PHP 8.3+, tooling pinned to what 8.3 accepts" (PHPUnit ^12 / PHPCS ^3 pins, committed lock, PHPStan level 8 at phpVersion 8.1). The delta-audit confirmed no theme coupling: the theme's Telegram bot is a separate ARCHITECTURE → Integrations row, not a shared surface.
- Open: activation on a host without OpenSSL cannot be reproduced locally (the extension is compiled into both PHP binaries) — covered by the unit test and a `wp eval` of the guard. `templates/*` deletions staged in the working tree from outside this session were left untouched and are in none of this step's commits.
