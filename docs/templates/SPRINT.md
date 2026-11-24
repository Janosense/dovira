# SPRINT {{N}} — {{Name}} ({{date range}})

<!-- playbook: v3.0.4. Written by discovery (Phase C) together with every
     other sprint of the plan — never by Claude Code. Rewritten by a
     re-planning chat only while no step is closed; afterwards steps may
     only be appended. This file
     describes the sprint and nothing else: goal, fixed decisions, steps.
     The only thing written here during the sprint is the tick in a step's
     heading, by /close-step. -->
**Branch:** {{per the git model in CLAUDE.md}}
**Goal:** {{one paragraph: what is demonstrably true when the sprint is done —
phrased as user/admin-visible outcomes, not as a task list}}

## Fixed decisions
{{Decisions already made that must NOT be reopened during the sprint, with links
to docs/DECISIONS.md entries. If an implementation finding challenges one of
these — stop and raise it, don't silently deviate.}}

## Steps
<!-- Ordered by dependency. One step = one /plan-step → /do-step → /close-step
     cycle and must fit one working session. Every step has all four
     subsections, even if a subsection is "—". The checkbox in the heading is
     ticked by /close-step. Which documents a step updates is not written
     here — /plan-step derives it from the tasks by the Write index of
     docs/CONTRACTS.md. -->

### [ ] Step 1 — {{name}}
- **Tasks:**
  - {{one sentence each: what is built, where}}
- **Tests:** {{the rules this step adds or changes — one phrase each, as a rule is stated ("a second run changes nothing", "an anonymised account signs in nowhere"); never a list of facts to assert (a column is empty, a count is the same, a word is on a page), test names or counts — how many tests and in which layer is the planner's decision under Core rule 7}}
- **Verification (manual):** {{what the user opens, runs or presses and what must happen; a screen is named exactly as its artboard and its FEATURE.md → UI row}}
- **Depends on:** —

### [ ] Step M — {{name}}
- **Tasks:**
- **Tests:**
- **Verification (manual):**
- **Depends on:** {{Step X, Step Y — every step that must be closed before this one; or "—"}}
