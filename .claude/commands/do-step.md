---
description: Execute the plan of the current step and write its verification guide. Running this command is the approval of the written plan. Scope frozen.
disable-model-invocation: true
---

**Running `/do-step` is the approval.** `/plan-step` writes the plan and
either runs this command itself (nothing in the plan needed the user) or
stops at a gate — a question without a recommendation, a new dependency,
shared code of another feature — and the user runs `/do-step` after
answering. Never ask for a confirmation and never wait for one before
starting.

**Which step.** The step is the one whose plan `/plan-step` wrote in this
session — feature, N and M, the plan section, its `### Branch` and every
document `/plan-step` read are already in the context: nothing is re-read
to establish them (not `docs/WORKLOG.md`, not the Features table, not the
sprint file, not the plan file). Only a session that holds no such plan
derives the step from the files: take the feature from the latest
`docs/WORKLOG.md` entry, open
`docs/features/{feature}/sprints/SPRINT-{N}-PLAN.md` and look for sections in
state `awaiting approval` — exactly one: that is the step, run it; none or
several: STOP and ask which step to run. Never pick a step yourself.

**Preconditions — refuse to proceed if any fails:**
- The plan section has status `awaiting approval`.
- Every gate of the plan is resolved. A question under Questions /
  ambiguities is resolved when it carries `Resolved: …` — `/plan-step`
  writes `Resolved: as recommended — {answer}` for a question with a
  recommendation; a question the user answered in this session is resolved
  now with the user's words (`Resolved: {answer}`, and the tasks it affects
  change with it). `New dependencies` not `none` is resolved by the user's
  "yes" in this session — write `approved` after each package. `Shared code`
  not `none` is resolved by the user's "go" — write `approved` after it. A
  question without a recommendation and without an answer, or a dependency
  or shared-code task without a word from the user, makes the plan not
  runnable: print that item and STOP without changing any other file.
- The user's last message before `/do-step` was not an objection to the
  plan. An answer to a gate is folded in as above and execution continues.
  An objection or an edit to the tasks is a change request: fold it into
  this step's section of `SPRINT-{N}-PLAN.md` only (the status stays
  `awaiting approval`), say what changed in the tasks, in plain language
  (CLAUDE.md → Project profile → Verification) — not the plan — and STOP
  with exactly this line; the user re-runs `/do-step` against the revised
  plan:

  > Plan for **Step {M}** revised. `/do-step` runs it as written — running it is the approval, and it authorizes Step {M} only.

**Not this command.** A report that an item of a step's verification guide
failed — before or after the step's close, in the chat or with
`/fix-step <what failed>` — is never patched or re-run here: Read
`.claude/commands/fix-step.md` and follow it, the report being its
argument. A section in state `in progress — verification fix: …` belongs to
`/fix-step` too.

Mark the plan section `(status: approved, in progress)` and execute:

1. **Branch.** Check out the task branch named in the plan's `### Branch`:
   if it does not exist, update the base and create the branch from it (if
   the base itself — the sprint branch — does not exist yet, create it first
   per the git model in root `CLAUDE.md`); if it exists, continue on it.
   Every commit of this step goes to the task branch — never to the base or
   to `main` directly.
2. **Scope is frozen.** Execute only the approved tasks. If mid-step you discover
   a needed dependency, a design flaw, a doc/reality mismatch, or scope growth:
   **stop immediately**, describe the problem and options (plain language
   — CLAUDE.md → Project profile → Verification), and wait. Do not
   silently expand or "fix along the way". The user's reply decides; before
   going on, record it in a `### Mid-step decisions` subsection at the end of
   the plan section (`- {problem} → {decision}`, one sentence each — the
   only subsection this command adds to the plan; what was built, proven or
   looked at is the commits, the tests and the guide, not a report in the
   plan). This subsection is also where every choice made while building is
   recorded in one sentence — a default overridden, a rule a test made
   explicit, a term named — so that `/close-step` can judge which of them
   become a document record; the builder itself writes no rule into
   `TECH-STACK.md`, `DOMAIN.md` or `DECISIONS.md`:
   - the work stays out of this step (another step, the next sprint,
     `/adhoc`) → continue with the approved tasks;
   - the user explicitly approves a change to this step's tasks → edit the
     tasks in the plan section, mark each added or changed task
     `(mid-step)`, and continue; a new dependency needs its own explicit
     "yes" (root `CLAUDE.md` → Core rules);
   - the reply approves nothing specific → ask again; never infer.

   A Claude Design handoff received in this session is input for the
   approved tasks only — it never adds tasks or screens.

   **Irreversible actions** (root `CLAUDE.md` → Core rule 8) — resetting
   or deleting data outside a test database, deleting files the step did
   not create, rewriting git history, writing to an environment, a call
   that creates, changes, sends, charges or deletes something at a live
   outside service, a deploy — happen only after the user's explicit yes
   in this chat, asked for that one action. A call that only reads from a
   live service is not one: no yes, run it.

   **What this session can run, it runs** — after the yes where one is
   needed. A command is never handed to the user to run, except when it
   needs what only the user has (a password, a second factor, a sign-in
   in a browser, a machine this session cannot reach); a deploy stays the
   user's own action. A step text that has the user run or confirm
   something this paragraph lets the session do is not followed on that
   point — one bullet in `### Mid-step decisions`, no stop.

   Such an action, a loss it
   caused, the user stopping or correcting an action of this session, or a
   stop because two playbook rules cannot both hold → one entry in
   `docs/INCIDENTS.md` **at once**, in the shape its contract gives
   (`docs/CONTRACTS.md` → INCIDENTS.md: `loss`, `intervention`, `blocked`)
   — facts only, no proposal.
3. **One task → working state → commit.** Conventional commit messages.
   Never leave the branch broken between tasks. The project's check command
   (`docs/TECH-STACK.md` → Check command — one committed script that runs
   tests, lint, static analysis and build and exits non-zero on the first
   failure) is the proof; no check command in the project yet → the plan's
   first task creates it; if the plan does not list that task, stop
   (rule 2). It is run at two gates and nowhere else:
   - **Task gate — every commit that changes code:** the parts of the check
     command as `docs/TECH-STACK.md` → Check command names them, scoped to
     the step — the linter and the static analyser over the changed files,
     the test runner over the step's test files (the plan's Tests to write
     and every test file the task touched). Exit 0 → commit. If the Check
     command section names no parts, the task gate is the whole command.
   - **Step gate — once:** the whole check command on the final tree of
     the step, right before the hand-over (step 9), timed (`time`). Its
     exit 0 is the run `/close-step` relies on; a commit that changes code
     after it runs it again.
   One run per gate: a red run is read and fixed, then run again; nothing
   is run twice to confirm, and nothing is judged from grepped output or an
   inline shell chain in the command's place. A commit whose diff touches
   only `docs/**` and `*.md` files (`git diff --cached --name-only`) rides
   on the last green run: commit it with no gate, and say so in the
   commit's chat line.
4. **Tests are part of the task**, written in the same task as the code —
   especially in the Test-critical zones of root `CLAUDE.md` → Project
   profile. **The proof of a test is the check command going green, and
   nothing the plan does not name.** No mutation run (breaking the code to
   watch the test fail), no "seen red first" for every test, no second
   runner invocation to confirm a count, no screenshot of a passing
   suite — unless `docs/TESTING.md` requires that proof for the step's zone
   or the plan's task lists it. A habit remembered from another project or
   from your own memory files is not a rule of this one: the rules are in
   `docs/TESTING.md` and root `CLAUDE.md`, and a proof they do not ask for
   is not performed.
5. **The records the plan names are part of the task.** Every bullet of
   the plan's `### Docs to update` (`{file} → {record}`) is written in the
   same commit as the change it records. Before writing, read that file's
   Contract in `docs/CONTRACTS.md` → Contracts (`### docs/{file}`) and the
   section of the file the record goes to, whole; then write exactly the
   record the Contract shapes — a state document's section is replaced,
   never appended; no sprint, step, date, "as built" or future tense
   anywhere. A task that turns out to fire a Write-index trigger the plan
   did not name → add the bullet to `### Docs to update` and write the
   record in the same commit. A document the Write index does not name for
   this change is not touched, however related it looks.
6. **Tick the checkboxes** in `SPRINT-{N}-PLAN.md` as tasks complete.
7. Do **not** write the WORKLOG entry, tick the step in `SPRINT-{N}.md`, or
   merge the branch — that is `/close-step`'s job, after the user has
   verified the step.

When all tasks are done:

8. **Verification guide.** Write
   `docs/features/{feature}/verification/sprint-{N}/step-{M}.md` in the
   shape `docs/CONTRACTS.md` → Contracts gives for the verification guide
   — every section, in its order, none added; the shape is the rule, the
   guide of the previous step is not. The guide is written for the user:
   it covers every item of the step's Verification (manual), in full, in
   plain language (CLAUDE.md → Project profile → Verification): no class,
   method or test name outside Covered by tests. The measure of
   it is that a person who has not seen the code follows it without asking
   a single question — exact addresses, exact strings, exact data, the
   exact state to expect. What this computer holds and lacks (migrations
   waiting, data an item relies on, accounts) is read from the machine,
   never assumed. There is no budget: the guide is as long as the walk
   needs.
9. **Hand over.** The step gate (step 3) is green on this tree. If
   `docs/TESTING.md` → Layers still has the full run's time or a Cost of
   one test empty, or this step added a layer, write them from the step
   gate's run, in the shape of its Contract. With the guide written, mark
   the plan section `(status: implemented, awaiting verification)` and
   commit the guide with the plan file (and `docs/TESTING.md`, if written)
   on the task branch (a docs-only commit — the step gate covers it; no
   check run):
   `docs(step): verification guide for sprint {N} step {M}`. Finish with:

> Step {M} implemented on `{task branch}`. Verify it on this branch by `docs/features/{feature}/verification/sprint-{N}/step-{M}.md`: every item holds → `/close-step` (running it means verified); an item fails → describe what you did, expected and got (or `/fix-step <what failed>`) — the fix lands on this branch.
