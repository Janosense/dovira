# Dovira — website of the Dovira veterinary clinic (dovira.vet, kyiv.dovira.vet)

<!-- playbook: v1.17 — Core rules and Step protocol are verbatim copies of
     templates/CLAUDE.md; never edit them here. -->

A WordPress site that tells pet owners what the clinic offers, at what price
and in which city, and turns contact / vacancy / blood-donor forms into admin
records with Telegram notifications. Staff assemble pages from ACF blocks and
edit prices themselves. One codebase serves two installs (Kharkiv `dovira.vet`,
Kyiv `kyiv.dovira.vet`) in Ukrainian and Russian; all custom code is the theme.

## Project profile
- Deploy: manual — never assume push-to-deploy. Production Kharkiv by hand
  from `master`, production Kyiv by hand from `kyiv` (= `master` + Kyiv
  analytics ids in `header.php`); only dev auto-deploys (GitHub Actions FTP
  on push to `dev`). See ARCHITECTURE.md
- Test-critical zones (code without tests here = unfinished task):
  form-to-record pipelines (CF7 → `conversation`/`application`, REST →
  `questionary`) and their Telegram notification; per-city price grouping of
  a Service; REST routes under `dovira/v1`; `wp dovira translate*` commands
- Git model: simple: task branch → `master` (branch names carry the feature
  name: {feature}/sprint-N-short-name). Environments track `master` (or the
  deployment branch named under Deploy) — never a task or sprint branch.

## Documentation (read before the relevant task)
| File | When to read |
|---|---|
| `docs/ARCHITECTURE.md` | Before structural work: new modules, endpoints, integrations, deploy questions |
| `docs/TECH-STACK.md` | Before adding dependencies or choosing an approach. Contains the ANTI-PATTERNS section — mandatory |
| `docs/DATA-MODEL.md` | Before any schema change, migration, query, or API response shape |
| `docs/DOMAIN.md` | Before implementing or changing any domain logic: the customer's terms, rules, invariants |
| `docs/CONTRACTS.md` (if present) | Before touching any endpoint, event, or payload shape |
| `docs/TESTING.md` (if present) | Before writing or changing tests |
| `docs/DESIGN.md` (if present) | Before any UI work: tokens, components, screen names. Design files in `docs/features/{feature}/design/` are references, never code to copy |
| `docs/DECISIONS.md` | Before proposing an architecture/tooling change — it may already be decided |
| `docs/features/{feature}/FEATURE.md` | Before any work in a feature: its scope, data ownership, invariants, interfaces |
| `docs/features/{feature}/sprints/SPRINT-N.md` | Current sprint scope and steps |
| `docs/features/{feature}/sprints/SPRINT-N-CLOSE.md` | At the first step of Sprint N+1: what Sprint N left behind — built, deferred, contradictions between docs |
| `docs/WORKLOG.md` | At session start: latest 5 entries (top of file) = project memory |
| `docs/LEARNINGS.md` | When something went wrong before — check if it's a known failure mode |
| `wp-content/themes/dovira/temp-data/README.md` | Before touching Yoast meta or the SEO import script (one-off ops tooling, not a feature) |

## Core rules
1. New dependencies only after explicit approval. Propose, explain why, wait for a "yes". This includes transitive tooling (linters, build plugins).
2. Do not "improve" without being asked. No speculative abstractions, caches, or extra layers. See an opportunity — propose it, don't do it silently.
3. Never hardcode business values. Prices, limits, intervals, texts that the business may change are configuration, not constants.
4. Secrets only via environment config. Never commit keys, never log secret values.
5. Documentation is part of the task. Docs that describe changed code are updated in the same commit as the change; a schema change without a `docs/DATA-MODEL.md` update is an unfinished task.
6. Project state is derived, never asked for: the newest `docs/WORKLOG.md` entry names the feature, sprint and step; that feature's `sprints/SPRINT-N.md` shows which steps are ticked, `SPRINT-N-PLAN.md` the step in flight (awaiting approval / in progress / implemented / closed). Derive it before any work on the project — not before answering a question. No WORKLOG entries = the project has not started.

## Step protocol
- Code is changed only inside a step (`/plan-step` → `/do-step`) or an
  `/adhoc`. The unit of work is **one Step** of the feature's `SPRINT-N.md` —
  never a whole sprint. If the user asks to "do the sprint" or "start the sprint":
  do not execute it; propose `/plan-step` for the first incomplete step.
- A step plan is produced only by `/plan-step` and executed only by
  `/do-step`. Running `/do-step` is the approval of the written plan — the
  user is never asked to say "approved"; any other message after the plan is
  a change request. Approval authorizes that step only — never the
  following steps.
- An implemented step is closed with `/close-step` before any other work begins.
- A closed step whose manual verification fails is re-opened only by
  `/fix-step <what failed>` and re-closed by `/close-step`. A failure report
  is never an instruction to patch the step directly.
- The next step begins only with a new `/plan-step` from the user.
- A sprint is complete when `/close-step` ticks its last step: that run
  merges the sprint into `main` and says so; `/plan-step N+1 1` does not
  start before the sprint is on `main`. Nothing in `SPRINT-N.md` is
  edited by hand.

Outside the step cycle:
- Questions (explain code, "why is X built this way", "what would it take
  to…", reading/analysis): answer anytime, with no ceremony — but a
  question is never an instruction to change code. If the answer implies a
  change, say so and wait.
- Ad-hoc tasks (a change outside the current sprint's scope: hotfix, small
  tweak, config change): go through `/adhoc`. Never fold ad-hoc changes into
  an open step's commits or plan.

## Domain invariants
1. A form submission is saved as a post (`conversation`, `application`,
   `questionary`) BEFORE any Telegram call; a Telegram failure never loses it.
2. `service-city` is shared across languages and never Polylang-translatable:
   Service price field names derive from its term ids (`price_city_{term_id}`,
   `city_{term_id}`); city names are translated as Polylang strings.
3. A price row applies only to the cities ticked in its `cities` checklist; a
   city without a price shows that city's phone from the options page, never
   a blank or a fabricated amount.
4. Kharkiv vs Kyiv is decided once, from the site URL (`kyiv` in
   `get_site_url()`); no other per-city branching in templates.
5. Editors get only the theme's `acf/*` blocks on every post type except
   `post` (`allowed_block_types_all`); core blocks are not re-enabled ad hoc.
6. All field groups are PHP (ACF Builder) and version-controlled; never
   create field groups in the ACF admin UI.
7. Ukrainian is the source language; Russian is a Polylang translation
   (`/ru/` is `noindex`). ACF options are per language (`options_*`, `options_ru_*`).
8. Public `dovira/v1` REST routes validate required fields before writing anything.

## Features
This table is the router: /plan-step resolves paths through it, not through
the file hierarchy.

| Feature | Docs (FEATURE.md + sprints) | Code |
|---|---|---|
| core | `docs/features/core/` | `wp-content/themes/dovira/` (the theme as-is; new features: `inc/features/{name}/`) |
| ga-telegram-bridge | `docs/features/ga-telegram-bridge/` | `wp-content/plugins/ga-telegram-bridge/` (standalone plugin, own `CLAUDE.md`) |
| search-stats | `docs/features/search-stats/` | `wp-content/themes/dovira/inc/features/search-stats/` (theme feature; JS in `source/scripts/features/search-stats/`, tests in `tests/Unit/SearchStats/`) |

## Commands
```bash
bin/check.sh                                          # check command: PHPCS + PHPStan + PHPUnit (plugin) + php -l (theme)
ddev start                                            # local WP at https://dovira.ddev.site (PHP 8.3, MariaDB 10.11, nginx)
ddev import-db --file=mysql.sql                       # load the DB snapshot from the repo root
cd wp-content/themes/dovira && npm install && composer install
cd wp-content/themes/dovira && npm start              # Vite dev server :3000, sets WP_ENVIRONMENT_TYPE=development in .env
cd wp-content/themes/dovira && npm run build          # production bundle into assets/ (committed), WP_ENVIRONMENT_TYPE=production
ddev wp dovira translate --post-type=page --dry-run   # AI translation uk→ru; needs ANTHROPIC_API_KEY in the theme .env
```
