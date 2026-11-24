---
description: Close a verified step — running it means the user verified the step by its guide. Docs self-check, worklog, merge; the last step of a sprint also merges the sprint into main.
disable-model-invocation: true
---

**Running `/close-step` means verified.** The user followed the step's
verification guide (written by `/do-step`) on the task branch, and every
item holds. Never ask whether the step was verified.

**Which step.** The step is the one implemented or fixed in this session —
feature, N and M, the plan section, its `### Branch` and the documents the
session already read are in the context: nothing is re-read to establish
them (not `docs/WORKLOG.md`, not the Features table, not the sprint file).
Only a session that did neither derives the step from the files:
the checked-out branch is a task branch named in a plan section's
`### Branch` → that step. Otherwise take the feature from the latest
`docs/WORKLOG.md` entry, open its
`docs/features/{feature}/sprints/SPRINT-{N}-PLAN.md` files (every N) and
look for sections in state `implemented, awaiting verification` — exactly
one: that is the step; several: STOP and ask which step to close; none:
check **Resume** below, and if it does not apply either, STOP and ask.
Never pick a step yourself. A step whose section is already `closed` is
never closed again — only **Resume** applies to it.

**Resume.** A previous `/close-step` stopped at a merge (a conflict, or the
check command failing after it). Its step is already `closed` and
committed: do not repeat steps 1–4 and do not write a second WORKLOG entry.
- A `closed` section whose task branch (its `### Branch`) still exists →
  continue from step 5, then steps 6–7 as usual.
- Every step of `SPRINT-{N}.md` is ticked, the git model has chained sprint
  branches, and `git branch --merged main` does not list the sprint branch
  → do the merge of step 7, then the report of step 6 with the lines of
  step 7.

1. **Completeness check.** The plan section is
   `implemented, awaiting verification` and all its checkboxes — those of
   its `### Verification fix` subsections too — are ticked. The check
   command (`docs/TECH-STACK.md` → Check command) is run here only when a
   commit after the step gate (the last run of the whole command) changed a
   file outside `docs/**` and `*.md` (`git log --name-only` since that
   commit); otherwise that green run stands and nothing is run. If something is missing or the run
   is red — list it and stop. The verification guide
   `docs/features/{feature}/verification/sprint-{N}/step-{M}.md` missing →
   write it (Read `.claude/commands/do-step.md`, step 8), commit it on the
   task branch and STOP: the user verifies by it, then runs `/close-step`
   again.

2. **Docs self-check.** Two parts, both against `docs/CONTRACTS.md`.

   The first is mechanical and reads no document: take `git diff
   --name-only {base}..HEAD` on the task branch and walk the **Write
   index** of `docs/CONTRACTS.md` — every diff trigger whose change the
   diff shows must have its file in the same diff. A trigger hit with no
   record → the task that changed the code did not do its documentation:
   write the record now, in the shape the file's Contract gives, in this
   closing commit. A document that is in the diff is not re-read here —
   `/do-step` wrote it. The plan section holds the template's subsections,
   `### Mid-step decisions` and `### Verification fix` only; any other
   subsection a session added (a report of what was built, proven or
   looked at) is deleted in the closing commit — its decisions, if any,
   become one sentence each under `### Mid-step decisions`.

   The second part is judgement, over the step's plan section — its
   Questions with their `Resolved:` answers and its `### Mid-step
   decisions` — not over the diff. These are the Write index's
   *judgement, at close* triggers; the criterion for every one of them is
   the same: **the next step, written by an agent with no memory of this
   one, would do it differently without the record.** An item that does
   not meet it goes with the plan and is written nowhere.
   - **`docs/TECH-STACK.md`** — an item shows a stack default overridden
     or switched off, an idiom that contradicted a decision or an
     invariant, or a new architecture test → one sentence: ANTI-PATTERNS
     when the stack's default leads the other way (`Do not X — Y instead
     (pointer)`), otherwise CONVENTIONS (`X is Y (pointer)`); a rule of
     one code area only → that area's `CLAUDE.md` → Area conventions; a
     rule CLAUDE.md → Domain invariants already states is not repeated.
     A sentence this step made untrue is edited or removed.
   - **`docs/DOMAIN.md`** — an item states a law of the domain the step
     uncovered (a rule a test can prove, a term, a state) → one Rules
     sentence, a Glossary row or a Lifecycles row; if it was a choice
     between alternatives, it is a DECISIONS entry instead. A Rules
     sentence this step rewrote or removed that root `CLAUDE.md` → Domain
     invariants copies → the copy is replaced verbatim (removed with its
     rule) in the same commit; a step adds no sentence there.
   - **`docs/TESTING.md`** — an item fixes how tests are written in this
     project, or names a trap that will recur → one Rules sentence; a
     trap met once is not written.
   - **`docs/DECISIONS.md`** — the user chose between alternatives in a
     plan question, or an item reversed or refined a fixed decision → one
     entry in the shape of its Contract (Context · Decision · Alternatives
     rejected · Consequences · Supersedes), and the bullet `Superseded by:
     {date — title}` on the entry it reverses. Only when real alternatives
     were weighed and the loser is one a future agent might reasonably
     propose; how the step built a decided thing is never an entry.
   - **`docs/INCIDENTS.md`** — this is the second `/fix-step` on this
     step, or the same failure the previous step had → one `repeat` entry
     in the shape of its Contract. Nothing else about the playbook is
     written: an inconvenience, a plan that proved wrong against the code,
     a rule the agent found awkward are not events.
   - **`docs/TESTING.md` → Layers** — the check command ran in step 1 →
     the sentence with the full run's time is refreshed.

3. **Worklog.** Write the step's entry at the top of `docs/WORKLOG.md` →
   Entries, in the shape of its Contract (`docs/CONTRACTS.md` →
   WORKLOG.md), in the language of the documents: the heading `## {date} —
   [{feature}] Sprint {N} Step {M} — {title}`, then exactly Changed ·
   Decisions (the titles of the DECISIONS entries step 2 added, or "—") ·
   Shared code · Opened / closed. Then replace `## Open`: remove every
   bullet this step closed, add one bullet per item it leaves owed — a
   guide item not verified, an artefact pending the run the plan named
   (Checks → Not locally verifiable), a question carried forward — each
   opening with today's date. Existing entries are never edited; an Open
   item is never repeated inside an entry. If this is the last step of the
   sprint, the entry ends with the sentence `Sprint {N} complete — {sprint
   branch} merged into main.` The entry is the agent's memory, not the
   report: the report is step 6.

4. **Status and commit.** Tick the step's checkbox in
   `docs/features/{feature}/sprints/SPRINT-{N}.md` (`### [x] Step {M} — …`),
   mark the plan section `(status: closed)`. Then commit everything from
   steps 2–4 on the task branch: `chore(step): close sprint {N} step {M}` —
   a docs-only commit, no check run. Nothing else is written to
   `SPRINT-{N}.md` — the tick is the only thing this command changes there.

5. **Merge.** Merge the task branch into its base (both named in the plan's
   `### Branch`) with `--no-ff`, message `merge: sprint {N} step {M} — {title}`.
   Then `git diff --quiet {task branch} {base}`: an empty diff means the
   base now holds exactly the tree the task branch was verified with — no
   check run, say so in the report; a non-empty diff (the base moved since
   the branch was made) → run the check command on the base (exit 0). Then
   delete the task branch.
   A conflict limited to entries added at the TOP of `docs/WORKLOG.md` is
   resolved by keeping both, newest on top; any other conflict is never
   resolved silently: run `git merge --abort`, report the conflicting files
   and STOP. The check command failing after the
   merge: report it and STOP, keeping the task branch (the fix goes through
   `/adhoc` on the base). Either way the next `/close-step` resumes here
   (**Resume**).

6. **Report.** The chat reply is built from the WORKLOG entry of step 3
   and the plan — not the entry verbatim: in plain language (CLAUDE.md →
   Project profile → Verification) — what the user can now see or do,
   which of their answers shaped it, what stays open and what closes it —
   as long as it needs to be understood, and
   nothing a file already shows (no file list, no commit list: `git log`
   and the plan hold them). It ends with two bullets:

   ```
   - Merged: `{task branch}` → `{base}` ({merge commit}; check on {base}: passed | not run — tree identical to the task branch)
   - Next: /plan-step [feature] {N} {M+1}   (feature name required when several exist)
     — or, if this was the last step of the sprint, the lines of **Last step of the sprint** below
   ```

7. **Last step of the sprint.** When every step of `SPRINT-{N}.md` is now
   ticked (this one included), the sprint is complete and you say so — the
   user must not have to check the sprint file to learn it.
   - **Merge the sprint into `main`.** Per the git model in root
     `CLAUDE.md`: with chained sprint branches, merge the sprint branch (the
     base of step 5) into `main` with `--no-ff`, message
     `merge: sprint {N} — {sprint name}`; the sprint branch stays. Then
     `git diff --quiet {sprint branch} main`: empty → no check run; otherwise
     the check command exits 0 on `main`, and if it fails, report it and
     STOP (the fix goes through `/adhoc` on `main`). A conflict is handled
     as in step 5 (WORKLOG entries only → keep both; otherwise
     `git merge --abort`, report the conflicting files and STOP — the next
     `/close-step` resumes here, **Resume**). With
     the simple model (task branch → `main`) step 5 already landed on
     `main` — nothing more to merge.
   - **Say it.** The report's Next line — and the last sentence of your
     reply — starts with exactly:

     > Sprint {N}: this was the last step — every step of `SPRINT-{N}.md` is closed. Sprint merged into `main` ({merge commit} | nothing to merge — simple git model).

     followed by what comes next, derived from the files: if
     `docs/features/{feature}/sprints/SPRINT-{N+1}.md` exists →
     `Next: /plan-step [feature] {N+1} 1`; if it does not but `FEATURE.md` →
     Roadmap lists Sprint {N+1} → the file is missing: name the re-planning
     chat in the Cowork Project (DISCOVERY → Feature mode, Re-planning) that
     writes it; if the Roadmap ends with Sprint {N} → the feature is done:
     the next feature comes through discovery. You never write or extend a
     sprint file yourself.
