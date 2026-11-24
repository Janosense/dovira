# {{PROJECT_NAME}} — {{one-line description}}

<!-- playbook: v3.0.4 — Core rules and Step protocol are verbatim copies of
     docs/templates/CLAUDE.md; never edit them here. -->

{{2–4 sentences: what the product does, who uses it, the one platform-level
truth if there is one (e.g. "HubSpot is the source of truth for CRM data").}}

## Project profile
- Verification: user-verified — the user does not read code. What the
  user reads — gate questions, stops, mini-plans, reports, answers and
  verification guides — is in plain language: what happens as the user
  sees it, no class, method or test name (a test's name only in a guide's
  Covered by tests). The plan file `SPRINT-N-PLAN.md` is the agent's
  record and names files and tests exactly. This sets the language of
  what is written, not the tests: Core rule 7 holds in full.
- Deploy: {{manual — never assume push-to-deploy | describe pipeline}}
- Test-critical zones (code without tests here = unfinished task):
  {{e.g. payments/entitlements, access control, webhook idempotency, AI output validation}}
- Git model: {{chained sprint branches: {feature}/sprint-N ← main, task
  branches {feature}/sprint-N-short-name merged --no-ff | simple: task
  branch → main. Sprint numbers are per feature, so branch names carry the
  feature name}}. Environments track `main` (or the deployment branch named
  under Deploy) — never a task or sprint branch; a deploy is the user's own
  action from `main`, never a step task.

## Documentation
Every project document has a contract in `docs/CONTRACTS.md`: when to read
it (Read index), which change obliges which record (Write index) and the
record's exact shape (Contracts). Before reading any document, consult the
Read index and read the sections it names — a file is read whole only when
the index says so. Before writing one, follow its contract: the record, in
the task's commit, nothing else. At session start: `docs/WORKLOG.md` →
`## Open` and the newest entry (Core rule 6). A document not in
`docs/CONTRACTS.md` is not written — project-specific documents are added
there by discovery, not here; `docs/ops/` holds material for a person and
is not read by a step.

## Core rules
1. New dependencies only after explicit approval. Propose, explain why, wait for a "yes". This includes transitive tooling (linters, build plugins).
2. Do not "improve" without being asked. No speculative abstractions, caches, or extra layers. See an opportunity — propose it, don't do it silently.
3. Never hardcode business values. Prices, limits, intervals, texts that the business may change are configuration, not constants.
4. Secrets only via environment config. Never commit keys, never log secret values.
5. Documentation is part of the task. Which document a change obliges and the shape of the record are fixed in `docs/CONTRACTS.md` (Write index, Contracts); the record is written in the same commit as the change. A change whose trigger fires without its record is an unfinished task.
6. Project state is derived, never asked for: `docs/WORKLOG.md` → `## Open` holds what is still owed, and its newest entry names the feature, sprint and step; that feature's `sprints/SPRINT-N.md` shows which steps are ticked, `SPRINT-N-PLAN.md` the step in flight (awaiting approval / in progress / awaiting verification / closed). Derive it before any work on the project — not before answering a question. No WORKLOG entries = the project has not started.
7. Tests prove rules, not screens. A rule is tested once, in the layer where it is decided (`docs/TESTING.md` → Layers): a rule of a domain class in a test with no framework and no database; an operation once per path through it; a page only for a state a rule decides — never for its look — and an interaction through the operation it calls, not by pressing. A design-system component gets no test. A dataset only where the behaviour differs between its cases — never one per role. What the framework guarantees is not tested. A rule without a test is unfinished; a test without a rule is removed.
8. An irreversible action — resetting or deleting data outside a test database, deleting files the step did not create, rewriting git history, writing to an environment, a call that creates, changes, sends, charges or deletes something at a live outside service, a deploy — happens only after the user's explicit yes in the chat, asked for that action; a call that only reads from a live service is not one and needs no yes. After the yes the agent runs the action itself: a command is handed to the user only when it needs what only the user has (a password, a second factor, a sign-in in a browser, a machine this session cannot reach), and a deploy stays the user's own action. Such an action, a loss it caused, or a correction by the user is a `docs/INCIDENTS.md` entry at once (`docs/CONTRACTS.md` → INCIDENTS.md).

## Step protocol
- Code is changed only inside a step (`/plan-step` → `/do-step`, a
  `/fix-step`) or an `/adhoc`. The unit of work is **one Step** of the
  feature's `SPRINT-N.md` — never a whole sprint. "Do the sprint" → propose
  `/plan-step` for the first incomplete step.
- A step plan is produced only by `/plan-step`, written to
  `SPRINT-N-PLAN.md` and never printed. When nothing in it needs the user
  — a question without a recommendation, a new dependency, shared code of
  another feature — `/plan-step` continues into `/do-step` itself; otherwise
  it prints only what needs the user and stops. Running `/do-step` is the
  approval of the written plan — never ask for a confirmation. Approval
  authorizes that step only — never the following steps.
- An implemented step is verified by the user on its task branch, by the
  verification guide `/do-step` wrote, and then closed with `/close-step` —
  running it means verified. No other step begins before the close.
- A failed item of a verification guide — reported in the chat or with
  `/fix-step <what failed>` — is handled only by the fix procedure: Read
  `.claude/commands/fix-step.md` and follow it, the report being its
  argument. Before the close the fix lands on the step's task branch; after
  it, on a fix branch. A failure report is never an instruction to patch
  the step directly.
- The next step begins only with a new `/plan-step` from the user.
- A sprint is complete when `/close-step` ticks its last step: that run
  merges the sprint into `main` and says so; `/plan-step N+1 1` does not
  start before the sprint is on `main`. A coding session changes nothing
  in `SPRINT-N.md` except the tick `/close-step` puts on a closed step.

Outside the step cycle:
- Questions (explain code, "why is X built this way", "what would it take
  to…", reading/analysis): answer anytime, with no ceremony — but a
  question is never an instruction to change code. If the answer implies a
  change, say so and wait. `/question <question>` is the same answer with
  the search fixed: the project documents first, the code to confirm them,
  the state of what was asked (built / planned / in flight), and the route
  a change would take — it may offer to draft the `/adhoc` text.
- Ad-hoc tasks (a change outside the current sprint's scope: hotfix, small
  tweak, config change): go through `/adhoc`. Never fold ad-hoc changes into
  an open step's commits or plan.

## Domain invariants
<!-- A selection of docs/DOMAIN.md → Rules, copied verbatim: the rules every
     step must know without opening DOMAIN.md. A change is made in DOMAIN.md
     first, then here, in the same commit. Only discovery adds a rule. -->
{{Numbered, non-negotiable, project-specific rules — e.g. "server-side
entitlement checks on every endpoint", "every outside response is validated
before it touches the database", "persist first, then broadcast".}}

## Features
This table is the router: a feature's docs are `docs/features/{feature}/`,
its code is the Code path below — resolve through the table, never through
the file hierarchy.

| Feature | Docs (FEATURE.md + sprints) | Code |
|---|---|---|
| {{core}} | `docs/features/{{core}}/` | `{{path}}` |

## Commands
```bash
{{check command from docs/TECH-STACK.md first (the commit gate), then
  dev / lint / test / build / migrate — exact, copy-pasteable}}
```
