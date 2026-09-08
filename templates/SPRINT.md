# SPRINT {{N}} — {{Name}} ({{date range}})

<!-- Written by discovery (Phase C) together with every other sprint of the
     plan — never by Claude Code. Rewritten by a re-planning chat only while
     no step is closed; afterwards steps may only be appended. When the last
     step closes, /close-step writes SPRINT-{{N}}-CLOSE.md next to this file
     (a report, not a sprint file). -->
**Branch:** {{per the git model in CLAUDE.md}}
**Goal:** {{one paragraph: what is demonstrably true when the sprint is done —
phrased as user/admin-visible outcomes, not as a task list}}

## Fixed decisions
{{Decisions already made that must NOT be reopened during the sprint, with links
to docs/DECISIONS.md entries. If an implementation finding challenges one of
these — stop and raise it, don't silently deviate.}}

## Steps
<!-- Ordered by dependency. One step = one /plan-step → /do-step → /close-step
     cycle and must fit one working session. Every step has all five
     subsections, even if a subsection is "—". The checkbox in the heading is
     ticked by /close-step. -->

### [ ] Step 1 — {{name}}
- **Tasks:**
  - {{…}}
- **Tests:** {{what must be covered; reference test-critical zones}}
- **Verification (manual):** {{what the user opens/runs/clicks and what must happen; a screen step names the screen as in FEATURE.md → UI}}
- **Docs to update:** {{which docs this step is expected to touch}}
- **Depends on:** —

### [ ] Step M — {{name}}
- **Tasks:**
- **Tests:**
- **Verification (manual):**
- **Docs to update:**
- **Depends on:** {{Step X, Step Y — every step that must be closed before this one; or "—"}}

## Definition of Done
- [ ] Every step closed via /close-step (report + verification guide + worklog)
- [ ] `test` and `lint` green on the sprint branch
- [ ] Docs match reality (DATA-MODEL, ARCHITECTURE, DECISIONS current)
- [ ] {{sprint-specific outcomes, e.g. "deployed to staging from `main` for
      client review" — a deploy is always a Definition of Done item, never a
      step task: environments track `main`, and this deploy is the run that
      verifies any deploy tooling the sprint wrote}}

## Out of scope
{{Explicit list of tempting-but-later items, with the sprint where they land.}}

## Risks / notes
{{External dependencies (credentials, client-side inputs), known unknowns,
things to chase on day 1.}}
