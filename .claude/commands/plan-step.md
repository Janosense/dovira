---
description: Plan one sprint step into the plan file and run it — stops only for a question without a recommendation, a new dependency or shared code of another feature.
argument-hint: [feature] <sprint> <step>
disable-model-invocation: true
---

You are planning exactly one step. This command never writes or edits
application code, runs migrations, or installs anything; the only file it
writes is the plan file.

1. **Parse arguments:** `$ARGUMENTS` → optional feature name, sprint number
   N, step number M (e.g. `1 3` or `event-calendar 1 3`).
   Resolve the feature through the Features table in root `CLAUDE.md` — it is
   the router; never guess from the file hierarchy. If no feature name was
   given: with exactly one row in the table, use it; with several, STOP and
   ask which one — never pick one yourself. The row gives the feature's
   **Code** path; the feature's docs always live in `docs/features/{feature}/`:
   - `docs/features/{feature}/FEATURE.md` — the feature's architecture
   - `docs/features/{feature}/sprints/SPRINT-{N}.md` — the sprint
   - `docs/features/{feature}/sprints/SPRINT-{N}-PLAN.md` — the plan file this command writes

   If `docs/features/{feature}/sprints/SPRINT-{N}.md` does not exist — STOP.
   Never write a sprint file yourself; sprint files come only from discovery,
   which writes one file per sprint of the plan. Do not offer to plan the
   sprint here; tell the user what to do:
   - the feature has no `sprints/` at all (e.g. `core` registered by Adoption
     — it has no sprints by design) → a feature with sprints must be created
     first via discovery (DISCOVERY → Feature mode);
   - the feature has earlier sprints and `FEATURE.md` → Roadmap lists
     Sprint {N} → the file is missing: a re-planning chat in the Cowork
     Project (DISCOVERY → Feature mode, Re-planning) writes it;
   - the Roadmap does not list Sprint {N} → the feature has no Sprint {N}:
     say so.

2. **Read what the Read index names — sections, not files.**
   `docs/CONTRACTS.md` → Read index says, for each kind of task, which
   document and which sections to read; nothing else decides it. Find a
   section with `grep -n '^## \|^### ' {file}` and read only it (`sed -n
   '/^## {heading}/,/^## /p' {file}`, or Read with the offset and limit the
   grep gives). A file is read whole only where the index says "whole"; a
   file read once in this session is not read again. Besides the named
   sections: `grep -n -i` every document the index names for this step for
   the step's own terms — the tables, modules, screens and domain words its
   text uses — and read every section a hit falls in: a rule the step must
   follow is found by its words, not by the section it was filed under.
   In this order:
   - `docs/CONTRACTS.md` → Read index (and the Write index, kept for step 5)
   - `docs/features/{feature}/sprints/SPRINT-{N}.md` — the whole file first
     (goal, fixed decisions, dependencies), then Step {M}: its tasks are
     the task kinds the Read index rows are chosen by
   - every Read index row this step's task kinds match — the rows for the
     planner's "what changed since", every step of a feature, any task
     that writes code and the sprint's Fixed decisions always do
   - outside the index, for this command only: for M = 1 and N > 1 the
     `docs/WORKLOG.md` entry that ends Sprint {N-1} — what it left open is
     Docs vs reality input for this plan; the code area's `CLAUDE.md` (the
     feature's host directory), if the feature's Code path has one

3. **Inspect the actual codebase state** relevant to the step. Do not trust the
   docs blindly — if reality and docs disagree, say so explicitly in the plan
   (Checks → Docs vs reality).

   Always:
   - The check command named in `docs/TECH-STACK.md` → Check command
     exists: its script or entry is found in the repository (the manifest's
     scripts, a `Makefile`, or the file the command names). It is **not run** here — planning changes no code, so
     there is nothing to check; `/do-step` runs it on the first commit, and
     a red run there is that task's problem. If the project has none yet,
     the first task of this plan creates it: one committed script that runs
     tests, lint, static analysis and build and exits non-zero on the first
     failure.

   Then each of these whose condition holds for this step:
   - **A task bootstraps a framework or skeleton into a non-empty
     repository** → list the skeleton's top-level files in the plan and, for
     each name that already exists (`CLAUDE.md`, `README.md`, `.gitignore`,
     agent instruction files such as `AGENTS.md`), state what happens to it.
     Never adopt the skeleton's agent file or bootstrap notes; a dependency
     they ask for needs explicit approval like any other (root `CLAUDE.md` →
     Core rules).
   - **`docs/TECH-STACK.md` has versions marked `(unverified — pinned at
     bootstrap)`** → verify each against the package registry and list in
     the plan the locked version that TECH-STACK will record.
   - **The step builds or changes a screen** →
     1. extract the text of that screen's artboards from their `.dc.html`
        files before listing strings;
     2. strings: `brief.md`'s wording wherever the brief spells it out, the
        artboard of the screen state for every other string; record each
        difference between the two under Checks → Docs vs reality — never a
        silent choice;
     3. scope: What the artboard of the step's screen state draws is the
        step's scope. Plan every control or field it draws, including one
        the step text does not list — do not defer it or ask about it —
        unless another step or `FEATURE.md` → Roadmap names it;
     4. files: an artboard uses a file `docs/DESIGN.md` → Source lists as
        left in the artifacts (a font, an image) and the code area does
        not have it yet → one question under Questions / ambiguities,
        always **without a recommendation**: the user puts `{file}` into
        `{path}` — the task that needs it is never built on a stand-in.

4. **Verify preconditions** — stop and report if any fails:
   - No step is in flight: neither this feature's `SPRINT-{N}-PLAN.md` nor
     the plan file named by the latest `docs/WORKLOG.md` entry (its feature
     and sprint) has a section in state `in progress` or
     `implemented, awaiting verification`, and no task branch named in a
     plan section's `### Branch` is left unmerged into its base
     (`git branch --no-merged`) — the plan file on the base may not show a
     step that awaits verification on its branch. An implemented step is
     verified by the user and closed with `/close-step` before another step
     is planned; steps are closed sequentially, never in parallel sessions.
   - All steps listed in Step {M} → "Depends on" are closed: their checkboxes
     in `SPRINT-{N}.md` are ticked (`### [x] Step … `).
   - Sprints run in order: for N > 1, every step of `SPRINT-{N-1}.md` is
     ticked. If not — say which steps of Sprint {N-1} are still open and stop.
   - Sprint {N-1} is on `main` (M = 1, N > 1): with chained sprint
     branches, the branch of `SPRINT-{N-1}.md` is merged into `main`
     (`git branch --merged main` lists it) — `/close-step` of its last step
     does that. If not — stop: "Sprint {N-1} is not merged into `main` —
     run `/close-step`: it resumes the unfinished merge". Never merge it
     here.
   - Step {M} itself is not already closed. If it is — say so; rework of a
     closed step goes through `/fix-step <what failed>`, not a new plan.

5. **Derive the plan from Step {M} of `SPRINT-{N}.md` only.** The step's
   subsections are the source:
   - **Tasks** → the plan's tasks: the same work, made concrete against the
     real codebase — split into commit-sized ordered tasks with exact files.
     A task is one to three sentences: what is built, where, and its commit
     message. The design of the solution — which class answers what, how a
     rule is worked out, what each caller does — is not written here: it is
     the code and the documents the task updates
   - **Tests** (+ the Test-critical zones of root `CLAUDE.md` → Project
     profile) → the plan's tests, decided here and nowhere else: the
     step's Tests subsection is a list of rules, not of tests. One test
     per rule the step adds or changes — the rules the subsection names
     and a rule a task turns out to add — at the layer `docs/TESTING.md` →
     Layers gives for the kind of rule (Core rule 7): a domain rule with
     no framework and no database, an operation once per path, a page only
     for a state a rule decides, a design-system component never. A fact
     that is no rule — a column is empty, a count is the same, a word is
     on a page, a second path to the same refusal — is an assertion inside
     the rule's test or an item of the verification guide, never a test of
     its own; a test the subsection asks for and Core rule 7 refuses is not
     written, and that is not a question. One bullet per test file — the
     file, its layer and the rules it holds. The assertions are the test
     file, not the plan
   - **Docs to update** — derived here, from no other source: for each
     task, match what it changes against `docs/CONTRACTS.md` → Write index
     — the diff triggers by the files the task will touch, the *judgement*
     triggers by what the task is (the first of its kind, a new term, state
     or rule, shared code read or exposed) — and write one bullet per hit:
     `{file} → {record}`, the record named as the file's Contract names it.
     A task that fires no trigger writes no document; a document the Write
     index does not name is not written
   - **Verification (manual)** → what the tasks must make possible
   Check the derived tasks and tests against the real code, not only against
   the step text.

   Always:
   - Every task leaves the branch green on its own. Tasks that build screens
     or modules linking to each other register the routes / contracts in the
     first of them, or the plan states how a link to a not-yet-existing
     target is written until it exists.

   Then each of these whose condition holds for this step:
   - **The step adds, narrows or conditions on a rule other code depends
     on** (permission / Policy, validation, invariant) → read the whole rule
     set, not the diff, and put in the plan:
     1. every existing test whose actor loses an ability, and what that test
        becomes;
     2. for every screen state conditioned on an ability: the ability that
        already admitted the reader to the screen, and how the two differ.
        If they cannot differ, the state is unreachable — no fallback task,
        no test.
   - **The step builds or changes UI** → the plan names the component whose
     DOM renders each pinned, conditional or interactive control, and at
     least one test asserts the state the user sees after the interaction
     (enabled / disabled, rendered / hidden), not only the markup at first
     paint.
   - **The step adds or changes a call to an outside service** (API,
     webhook, realtime, payments) → read the provider's documentation at
     the link `docs/ARCHITECTURE.md` → Integrations → Docs gives, for the
     version it names (`docs/API.md` → Consumed first if the service has
     the block), and derive the tasks from it: address, auth, what is sent,
     what comes back, the errors to map. What the documentation states is
     no question and no task: never a probe, a spike or a "check that it
     answers" task — the user checks the call in the verification guide.
     A point the documentation leaves open, with tasks that differ by the
     answer → Questions / ambiguities. The link is never searched for in a
     step: a service with no Integrations row or no Docs link → one
     question under Questions / ambiguities — the user gives the link,
     and `### Docs to update` adds it to the row.
   - **The step creates an artefact the check command cannot execute**
     (deploy script, cron line, server or hosting config) → no invented
     local test; name under Checks → Not locally verifiable the one real run
     that verifies it — for deploy tooling, the next deploy from `main`,
     done by the user.
   - **A step task says "deploy"** → Deploying an environment is never a
     task of a step: environments track `main` (git model in root
     `CLAUDE.md`). Put the task under Questions / ambiguities with the
     recommendation to drop it from the step.

   The plan never adds work the step does not name (what the artboard of the
   step's screen draws is named by the step — §3). Work that turns out
   necessary but is missing from the step → "Questions / ambiguities" (the
   user decides: this step, another step, or the next sprint). Work from
   other steps, or work that `FEATURE.md` → Roadmap places in a later
   sprint → never.

   **What is a question.** An item goes to Questions / ambiguities only when
   both hold: the plan's tasks differ depending on the answer, and no source
   settles it — the sprint (goal, Fixed decisions, this and the other
   steps), FEATURE.md, DECISIONS.md, DATA-MODEL, DESIGN.md / the
   artboard, the shipped code, the provider's documentation of an outside
   service the step calls. Everything else is not a question:
   - a mismatch between two sources that the precedence rules settle (brief
     over artboard for strings; DECISIONS over everything; the data model
     over what a screen happens to draw; a string key already shipped for
     the same rule over a second wording of it; Core rule 7 over the step's
     Tests) → resolve it and record it
     in Checks → Docs vs reality / Design with the resolution taken;
   - an observation outside this step whose recommendation is "leave it for
     another step or `/adhoc`" → one bullet in
     Checks → Docs vs reality (nothing, if it is not a mismatch) — it changes
     no task, so it is not a question;
   - a choice with one defensible answer → make it in the task and say so
     there.
   One fixed exception: a design file left in the artifacts (§3, item 4)
   is always a question, never with a recommendation.
   `none` is the expected content of the section for most steps; a plan is
   not better for having questions. A question that survives names the two
   (or more) answers, what changes in the tasks under each, and a
   recommendation where one exists.

6. **Write the plan** into `docs/features/{feature}/sprints/SPRINT-{N}-PLAN.md`
   (add or replace the section for this step; never touch other steps'
   sections), using this structure:

   ```
   ## Plan — Sprint {N}, Step {M}: {title}   (status: awaiting approval)
   ### Branch
   `{task branch}` ← `{base}`   (per the git model in root `CLAUDE.md`;
   base = the **Branch** of `SPRINT-{N}.md`, e.g. `{feature}/sprint-{N}-short-name` ← `{feature}/sprint-{N}`)
   ### Tasks (ordered)
   - [ ] task → expected commit   (one to three sentences: what, where, the commit message)
   (a task that changes shared code is marked "touches shared code — may
   affect other features" and names the consuming features)
   ### Files to create/change
   ### Tests to write
   - `{File}Test` ({layer}) — {the rules it holds}   (one bullet per file)
   ### Docs to update
   - `{file}` → {record}   (one bullet per Write-index hit; none → "none")
   ### Checks
   - ANTI-PATTERNS / CONVENTIONS: none violated | conflict: …   (the verdict only — never the list of what was checked)
   - Docs vs reality: match | mismatch: … → {resolution}   (one bullet per mismatch that changed a task; an observation that changes no task is not written)
   - Design: n/a | matches the artboard `{Screen}--{state}` | deviation: … → {resolution}   (one bullet per difference that changed a string or a control)
   - Check command: `{command}` (docs/TECH-STACK.md → Check command) | missing — created in task 1
   - New dependencies: none | {package} — {why}   (runtime and tooling alike)
   - Shared code: none | touches {what} — consuming features: {list}
   - Not locally verifiable: n/a | {artefact} — verified by {the one real run: the next deploy from `main`, cron tick, …}
   ### Questions / ambiguities
   none | (each question with a recommended answer where one exists; a question
   without a recommendation makes the plan NOT approvable until answered)
   ```

   **The section is a plan, not a record of the planning.** It holds the
   subsections above and nothing else; `/do-step` adds `### Mid-step
   decisions` and `/fix-step` `### Verification fix` — no other subsection
   exists. A plan that explains instead of deciding is cut: the
   explanations, the design of the solution and the lists of what was
   checked go — the tasks stay.

7. **Do not print the plan — run it, unless something needs the user.**
   The plan file is the record; the user reads it when something goes
   wrong, not before every step. Compare the written plan against the
   three gates:
   1. a question under Questions / ambiguities has **no recommendation**;
   2. **New dependencies** is not `none` (root `CLAUDE.md` → Core rules: a
      dependency needs the user's explicit "yes", runtime and tooling alike);
   3. **Shared code** is not `none` — a task changes code another feature
      consumes.

   None holds → write `Resolved: as recommended — {answer}` under every
   question that has a recommendation, then Read `.claude/commands/do-step.md`
   and follow it in this same session: the plan is approved as written, as
   if the user had run `/do-step`, and the approval authorizes Step {M}
   only.

   Any holds → the section stays `awaiting approval`. Print only what the
   gate needs (plain language — Project profile → Verification): the
   questions with their options, the dependencies with their reasons, the
   shared-code tasks with their consumers — not the plan — and STOP with
   exactly this closing line:

   > Plan for **Step {M}** written — waiting on: {questions | dependencies | shared code} above. Answer or approve, then run `/do-step` — it continues from the written plan.

   The user's answer, "yes" or "go" that follows, together with `/do-step`,
   resolves the gate; an objection to the plan is a change request, which
   `/do-step` folds into the plan file. Approval always refers to the
   written plan, never to the chat. A report that an item of a step's
   verification guide fails is not a change request: Read
   `.claude/commands/fix-step.md` and follow it.
