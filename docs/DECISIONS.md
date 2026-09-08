# Decisions log — Dovira

<!-- One decision log per project, across ALL features and code areas.
     Append-only; a reversed decision gets a NEW entry that links the old one,
     the old entry is never edited. Read this file before proposing any architecture or
     tooling change — it may already be decided. -->

Entry format:

## {{YYYY-MM-DD}} — {{Short title}}
- **Context:** {{what forced the decision}}
- **Decision:** {{what was decided, one sentence, imperative}}
- **Alternatives rejected:** {{and the one-line reason each lost}}
- **Consequences:** {{what this commits us to; what becomes an anti-pattern}}

---

## 2026-09-08 — Adopt the playbook (v1.14) into the existing codebase
- **Context:** The site has been live and developed without a written record; new work should go through the playbook's discovery → step cycle.
- **Decision:** Register the whole `dovira` theme as feature `core` with no sprints; every new piece of work is a new feature under `inc/features/{name}/` planned in Feature mode.
- **Alternatives rejected:** splitting the existing theme into several features retroactively — no value without a refactor, and Adoption changes no code.
- **Consequences:** `core` never receives sprints; the theme gets a code-area `CLAUDE.md`; the first feature's Sprint 1 Step 1 creates the check command.

## 2026-09-08 — Verification profile and deploy model
- **Context:** Adoption needs the profile slots of root `CLAUDE.md`; the repo shows a `dev` FTP workflow but nothing for production.
- **Decision:** Profile is developer-reviewed; deploys are manual from `master` (Kharkiv) and `kyiv` (Kyiv), with `kyiv` a deployment branch that only merges `master` in; git model is simple (task branch → `master`).
- **Alternatives rejected:** chained sprint branches — overhead for a one-developer site; treating `kyiv` as a long-lived feature branch — it must never diverge beyond analytics ids.
- **Consequences:** no step may deploy; a sprint's Definition of Done includes "merge to `master`, merge `master` into `kyiv`, deploy both by hand"; `origin/main` is unused.

## 2026-09-08 — Document the UI from code (DESIGN.md) instead of deleting the skeleton
- **Context:** The design exists only as the client's Figma; the theme has a clear token/component structure in `source/styles/`.
- **Decision:** Keep `docs/DESIGN.md`, filled from code, with the code as the source of truth.
- **Alternatives rejected:** deleting the skeleton — future UI features would have no shared vocabulary for screens and components.
- **Consequences:** a UI feature's design brief starts from DESIGN.md → Tokens/Components; new components are added there in the same step.

---

## Open questions from the adoption audit (not decisions — to be settled in a Feature-mode discovery)
1. **Telegram bot token and join password are string literals in code**
   (`inc/rest-api/TelegramController.php`, `inc/utils/{conversation,application}.php`,
   `inc/rest-api/QuestionaryController.php`) — conflicts with core rule 7
   (secrets via environment config). Candidate: move to the theme `.env`
   and rotate the token.
2. **Three `dovira/v1/telegram/*` routes are public** (`permission_callback:
   __return_true`), including `send-test-message` (hardcoded personal chat id
   and unrelated text) and `reset-telegram-log` — a cleanup/lock-down candidate.
3. **Legacy LMS remnants** (`templates/{sign-in,sign-up,profile,testing}.php`,
   `student` role, `user_study_state`, `chapter`/`question` helpers,
   `authentication.js`, `reset-progress.js`, `learning-plan` styles): remove or keep?
4. **CF7 form ids (6, 1430, 1399) and admin URLs (`dovira.vet`, `dev.dovira.vet`)
   are hardcoded** — they differ per environment/city install; a settings
   option would make Kyiv and dev behave the same as Kharkiv.
5. **`blood_group` choices differ** between the field definition
   (`fields/post-type-questionary.php`) and the admin filter
   (`utils/questionary.php`) — one list should win.
6. **`processing_date` adds a fixed +10800 s** instead of using WordPress
   timezone functions.
7. **No check command, tests, linter or static analysis** — the first
   feature's Sprint 1 Step 1 creates the gate (TECH-STACK.md → Check command).
8. **`wp-content/themes/dovira/temp-data/` and `reports/`** hold one-off ops
   data (SEO import, translation bundles, city diffs) inside the deployable
   tree; keep, move, or gitignore?
