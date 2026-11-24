---
description: Answer a question about the project from its docs first, then the code — reads only, changes nothing; offers to draft an /adhoc text when the answer calls for a small change.
argument-hint: <question>
disable-model-invocation: true
---

The question: $ARGUMENTS

This command answers; it never changes anything. No file is written, no
branch, commit, stash or migration is made, no dependency is installed —
not even a doc fix for a mismatch the answer finds: a mismatch is reported,
and a change the answer implies is proposed, never made. A question is
never an instruction to change code.

1. **No question given above** → ask what to answer and stop.

2. **Find the answer in the documents first.** The project documents are
   written to answer exactly this kind of question; the code is read to
   confirm them, not instead of them. Take the question's own terms — the
   feature, the domain words, the tables, screens and modules it names —
   and resolve the feature through the Features table in root `CLAUDE.md`
   (it is the router: a feature's docs are `docs/features/{feature}/`, its
   code is the row's Code path — never guess from the file hierarchy). A
   question that names no feature is matched against the rows by its
   terms; one that fits no row is answered from the shared documents.

   Read **sections, not files**, and let `docs/CONTRACTS.md` → Read index
   say which document answers which kind of question: `grep -n '^## \|^###
   ' {file}` to find a section, then only it (`sed -n '/^## {heading}/,/^##
   /p' {file}`, or Read with the offset and limit the grep gives); a file is
   read whole only where the index says so; a file read once in this
   session is not read again. Then `grep -n -i` every document the index
   names for the question's terms and read every section a hit falls in — a
   rule is found by its words, not by the section it was filed under. Two
   sources the index does not list answer "what is built and what is
   planned": `docs/features/{feature}/sprints/SPRINT-{N}.md` — a ticked
   `### [x] Step` is done, an unticked one is not, whatever the other
   documents say; `SPRINT-{N}-PLAN.md` → the step in flight and its status.

3. **Confirm in the code.** Every claim the answer rests on — "X exists",
   "Y is validated here", "Z is not implemented" — is checked at the
   feature's Code path: grep for the term, open the file the hit is in,
   read the function or class it falls in. "Not implemented" is said only
   after the term, its synonyms and the data it would need were searched
   across the whole code area, not one directory. Where the documents and
   the code disagree, the code is the fact and the disagreement is part
   of the answer.

4. **Answer** (plain language — CLAUDE.md → Project profile →
   Verification: no class or method names — what happens, as the user
   sees it). The direct answer first, then what it rests on:
   - **Where:** the files and document sections, by path (`docs/DOMAIN.md`
     → `## Gift cards`, `{{path}}/GiftCard` → `redeem()`), so the user
     can check
   - **State** of what was asked about: implemented (where) | planned
     ({feature} Sprint {N}, Step {M}, not ticked) | in flight (the step the
     plan file names and its status) | not present anywhere
   - **Docs vs code:** match | each mismatch in one sentence — what the
     document says, what the code does
   - **Not found:** what was looked for and not found, if anything —
     never a guess presented as a finding
   The length is the question's, not the project's: one sentence for a
   yes/no question, never a table of contents of what was read.

5. **Decide whether the answer calls for a change.** It does when the
   question says what the user wants to add or change, or asks whether
   something exists and it does not. Then name the route the change
   takes — one sentence after the answer, never a draft nobody asked for:
   - a failed item of a step's verification guide → `/fix-step <what failed>`
   - the change is inside the current sprint's scope (an unticked step of
     the sprint in flight names it) → that step, when its turn comes:
     `/plan-step`
   - the change is larger than an ad-hoc — a schema change, shared code of
     another feature, more than ~3 commits, or a feature of its own → the
     next sprint or a feature chat in the Cowork Project (DISCOVERY →
     Feature mode); say so if `FEATURE.md` → Roadmap already lists it
   - otherwise → an ad-hoc: offer to write the text for `/adhoc`, since
     the context is already here.

   The user's "yes" to the offer → **draft the `/adhoc` text.** First ask
   the questions whose answers change the text — at most four, each with
   a default so "go" is an answer; what the documents and the code already
   settled is not asked. Then print one copy-pasteable block:
   `/adhoc {what changes, as the user sees it; where — the feature and the
   files the answer found; what must stay as it is; the rule or decision
   the change must respect, if the answer found one}` — under ~10 lines,
   nothing the `/adhoc` run works out for itself (branch names, commit
   messages, test lists). Drafting the text changes nothing: the user runs
   `/adhoc`, and its own mini-plan is where the approval happens.

The session ends with the answer (and the offer or the text, if any). No
WORKLOG entry, no INCIDENTS entry, no document fix: this command leaves
the repository exactly as it found it.
