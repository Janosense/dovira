---
description: Close the current step — report, docs self-check, verification guide, worklog, merge.
---

**Which step.** The step is the one implemented in this session — feature, N
and M come from its plan section. If this session implemented nothing: take
the feature from the latest `docs/WORKLOG.md` entry, open
`docs/features/{feature}/sprints/SPRINT-{N}-PLAN.md` and look for sections in
state `implemented, awaiting close` — exactly one: that is the step; none or
several: STOP and ask which step to close. Never pick a step yourself.

1. **Completeness check.** The plan section is `implemented, awaiting close`,
   all its checkboxes are ticked, and the check command (`docs/TECH-STACK.md`
   → Check command) exits 0 on the task branch. If not — list what's missing
   and stop.

2. **Docs self-check.** Go through this checklist and update files where the
   answer is "yes" (in the same closing commit):
   - schema / table / field / index changed → `docs/DATA-MODEL.md` (and the feature's `FEATURE.md` data section)
   - new module, endpoint, flow, background job, integration → `docs/ARCHITECTURE.md`; feature-internal structure → its `FEATURE.md`
   - a decision was made, changed, or a fixed decision was challenged → new entry in `docs/DECISIONS.md`
   - dependency or tool added → `docs/TECH-STACK.md`
   - new domain term or rule appeared or changed → `docs/DOMAIN.md`
   - endpoint / event / payload shape changed → `docs/CONTRACTS.md` (if the project keeps one)
   - design token or shared UI component added or changed, or a screen built → `docs/DESIGN.md` (if the project keeps one: Tokens / Components "In code" / Screens) and the feature's `FEATURE.md` UI section
   - something went wrong with the process itself this step (agent overreach,
     misread instruction, broken assumption) → entry in `docs/LEARNINGS.md`;
     on a re-close, why the step's tests did not catch the failure is such an
     entry when the cause is the process (a test-critical zone left untested,
     a guide that did not cover the step's promise), not a one-off slip

3. **Verification guide.** Write
   `docs/features/{feature}/verification/sprint-{N}-step-{M}.md`: a numbered
   manual guide — what to open/run, what to click, what must happen,
   including at least one negative check (what must NOT be possible), when
   relevant. If profile = user-verified: assume the reader never reads code;
   include exact URLs, commands, and expected screen states. On a re-close
   the guide is rewritten in place and the item that failed becomes an
   explicit check.

4. **Worklog.** Add a new entry at the TOP of `docs/WORKLOG.md` (3–6 lines;
   existing entries are never edited): date, [feature] sprint/step, what
   changed, key decisions, open questions.
   If the step touched shared code, say so explicitly — other features read this.
   On a re-close (the step was reopened by `/fix-step` after a failed
   verification — its section reads `implemented, awaiting close — reopened:
   …`): a NEW short entry titled `… — re-closed`, stating what failed and
   what was fixed (from the section's `### Reopen` subsection).

5. **Status and commit.** Tick the step's checkbox in
   `docs/features/{feature}/sprints/SPRINT-{N}.md` (`### [x] Step {M} — …`),
   mark the plan section `(status: closed)`. If this tick leaves no unticked
   step in `SPRINT-{N}.md`, write the sprint's close file now (step 8, first
   part). Then commit everything from steps 2–5 on the task branch:
   `chore(step): close sprint {N} step {M}` (`re-close` on a re-close).

6. **Merge.** Merge the task branch into its base (both named in the plan's
   `### Branch`) with `--no-ff`, message `merge: sprint {N} step {M} — {title}`,
   then delete the task branch. The check command exits 0 on the base after
   the merge; a conflict is stopped and reported, never resolved silently. On a
   re-close, the `-reopen` branch created by `/fix-step` is merged the same
   way and deleted.

7. **Final report** in the chat (plain language if profile = user-verified):

   ```
   ## Step {M} closed — {title}
   - What was done:
   - Files changed:
   - Commits:  (+ merged into {base})
   - Deviations from plan:
   - Verification guide: `docs/features/{feature}/verification/sprint-{N}-step-{M}.md`
   - Not locally verifiable: n/a | {artefact} — pending {the run named in the plan, e.g. the sprint-boundary deploy from `main`}
   - Docs updated: [list]
   - Design: n/a | unchanged | changed — `docs/DESIGN.md` (Tokens / Components "In code" / Screens) and `FEATURE.md` → UI updated in this close
   - Open questions:
   - Next: /plan-step [feature] {N} {M+1}   (feature name required when several exist)
     — or, if this was the last step of the sprint, the sprint-boundary
     line chosen below
   ```

8. **Last step of the sprint.** When every step of `SPRINT-{N}.md` is now
   ticked:

   First (before the closing commit of step 5) write
   `docs/features/{feature}/sprints/SPRINT-{N}-CLOSE.md` — the sprint's
   handoff to the retro, the re-planning chat and `/plan-step {N+1} 1`. It is
   a report, not a sprint file; write it from the files and the code, never
   from memory of the session:
   - **Definition of Done** — every item of `SPRINT-{N}.md` with its evidence
     (check command output, commit, verification guide) or `open — {why}`
   - **Built** — modules, actions, policies, components, schema (names and
     paths) that the next sprint inherits
   - **Not locally verifiable** — artefacts still pending their real run
   - **Deferred** — every item the sprint's plans, reports or WORKLOG entries
     pushed to "the sprint boundary" or to a later sprint
   - **Contradictions** — cross-read `FEATURE.md` (Roadmap, UI),
     `SPRINT-{N}.md` → Out of scope, `SPRINT-{N+1}.md` if it exists,
     `docs/DESIGN.md` and `docs/DECISIONS.md`: list every item two files place
     differently (which files, which placements) — do not resolve them
   - **LEARNINGS** — entries of this sprint still marked `pending`

   Then check whether `docs/features/{feature}/sprints/SPRINT-{N+1}.md`
   exists and put exactly one of these on the Next line:
   - it exists → "Sprint {N} complete — check the Definition of Done in
     SPRINT-{N}.md, then the sprint boundary (merge into `main`, deploy from
     `main`, retro over SPRINT-{N}-CLOSE.md). Next:
     `/plan-step [feature] {N+1} 1`". The next sprint starts from its file;
     re-planning is needed only if the retro changed its scope or
     SPRINT-{N}-CLOSE.md → Contradictions touches its scope.
   - it does not exist but `FEATURE.md` → Roadmap lists Sprint {N+1} →
     "Sprint {N} complete — SPRINT-{N+1}.md is missing: open a re-planning
     chat in the Cowork Project (DISCOVERY → Feature mode, Re-planning) — it
     reads SPRINT-{N}-CLOSE.md first — to write it; then
     `/plan-step [feature] {N+1} 1`".
   - the Roadmap ends at Sprint {N} → "Sprint {N} complete — this was the
     feature's last sprint; the next feature starts with a discovery chat in
     the Cowork Project (Feature mode)".
   Re-planning and discovery never run in Claude Code: you never write or
   extend a sprint file. If the user asks you to "re-plan" or "plan Sprint
   {N+1}", answer with the matching line above — the chat to open and the
   file it will write — never with a bare "not my job".
