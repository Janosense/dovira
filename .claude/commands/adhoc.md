---
description: Execute a small task outside the current sprint scope (hotfix, tweak, config). Isolated from the step cycle.
argument-hint: <short description of the task>
disable-model-invocation: true
---

The task: $ARGUMENTS

An ad-hoc task is a change that does not belong to the current sprint's steps.
It must never contaminate an open step's plan, branch or commits.

0. **Enter plan mode.** Call the `EnterPlanMode` tool before anything else.
   Steps 1–3 are read-only analysis and planning: no file, git or shell
   change until the mini-plan is approved. Tool unavailable (non-interactive
   session): keep the same discipline and print the mini-plan in the chat.
1. **Scope check first.** No task given above → ask what to change and
   stop. Otherwise read the latest `docs/WORKLOG.md` entry (it names
   the feature, sprint and step last worked on), then that feature's
   `docs/features/{feature}/sprints/SPRINT-{N}.md` and `SPRINT-{N}-PLAN.md`
   (the step in flight and its state); no WORKLOG entry yet, or a feature
   without `sprints/` (such as `core`) → no step in flight. If the request actually belongs to
   the current sprint's scope, or is too large for an ad-hoc (touches schema,
   shared code across features, or needs more than ~3 commits): STOP and
   propose adding it to the sprint plan or the next sprint instead. Do not
   execute. A failed item of a step's verification guide — before or after
   the step's close — is not an ad-hoc either → `/fix-step <what failed>`.
2. **Read what the Read index names — sections, not files.** The
   mini-plan is written only after this step. `docs/CONTRACTS.md` → Read
   index says, for each kind of task, which document and which sections to
   read; nothing else decides it. The task text above stands where a
   sprint step's tasks stand in `/plan-step`: its kinds of work choose the
   rows. Find a section with `grep -n '^## \|^### ' {file}` and read only
   it (`sed -n '/^## {heading}/,/^## /p' {file}`, or Read with the offset
   and limit the grep gives). A file is read whole only where the index
   says "whole"; a file read once in this session is not read again.
   Besides the named sections: `grep -n -i` every document the index names
   for this task for the task's own terms — the tables, modules, screens
   and domain words its text uses — and read every section a hit falls in:
   a rule the change must follow is found by its words, not by the section
   it was filed under. In this order:
   - the feature the task falls in, resolved through the Features table in
     root `CLAUDE.md` by the task's own terms — it may differ from the
     feature of the latest WORKLOG entry; a task that fits no row reads
     the shared documents only
   - `docs/CONTRACTS.md` → Read index
   - every Read index row the task's kinds of work match — the row for
     every step of a feature holds for an ad-hoc in that feature
     (`FEATURE.md` → whole), and the row for any task that writes code
     holds whenever a file outside `docs/**` and `*.md` will change
   - outside the index: the code area's `CLAUDE.md` (the feature's host
     directory), if the feature's Code path has one

   What the reading shows to be too large for an ad-hoc (step 1: schema,
   shared code across features, more than ~3 commits) → STOP as in step 1.
3. **Mini-plan** (plain language — CLAUDE.md → Project profile →
   Verification): what will change, files, branch
   `adhoc/{short-name}` ← base, whether tests are needed (yes for anything
   in the profile's Test-critical zones), the rules step 2 found that the
   change must follow — one sentence each with its `{document} →
   {section}`, or "none found" — and risks.
   Nothing goes into `SPRINT-{N}-PLAN.md`. Present it with `ExitPlanMode`
   and **wait for explicit approval** — the approval of the plan is the
   approval; do not ask again in the chat.
4. **State isolation.** If a step is `in progress` or
   `implemented, awaiting verification`, its task branch must be left clean:
   commit finished tasks, stash unfinished work; when the ad-hoc is done,
   check the task branch out again and restore the stash. Ad-hoc work
   happens on its own branch `adhoc/{short-name}` created from the branch
   the fix targets (per the git model in root `CLAUDE.md`: `main` for a
   hotfix of what is released, the sprint branch for a fix of sprint work)
   — never on a step's task branch.
5. **Execute:** small increments, tests where required, conventional commits
   prefixed `fix:` / `chore:`. Before every commit that changes a file
   outside `docs/**` and `*.md` run the check command (`docs/TECH-STACK.md`
   → Check command) and commit only on exit code 0 — one run per commit,
   judged by the exit code, never grepped output; a docs-only commit rides
   on the last green run.
6. **Close:** Read `.claude/commands/close-step.md`, step 2 (**Docs
   self-check**) and apply it to this change — the Write index of
   `docs/CONTRACTS.md` against the diff, the records in the shape of their
   Contracts, in the same commit as the change they record; then step 3
   (**Worklog**): the entry tagged `[adhoc] [{feature}]` in the shape of
   the WORKLOG Contract, and `## Open` replaced. An irreversible action in
   an ad-hoc follows root `CLAUDE.md` → Core rule 8 (explicit yes first, then
   the session runs it itself; `docs/INCIDENTS.md` at once). Then merge the ad-hoc branch into its base with
   `--no-ff` and delete it (a conflict limited to entries added at the TOP
   of `docs/WORKLOG.md` → keep both, newest on top; any other conflict →
   `git merge --abort`, report the conflicting files and stop). Do not
   touch `SPRINT-{N}.md` or `SPRINT-{N}-PLAN.md` — the step/sprint state is
   unchanged.
7. **Report** (plain language — CLAUDE.md → Project profile →
   Verification): what changed, as the user sees it; the branch it was
   merged into; how to verify it by hand — no file or commit list, `git
   log` holds them. Then remind:

   > Ad-hoc done. Sprint state unchanged: [feature] Sprint {N}, Step {M} ({state}) — or "no step in flight".
