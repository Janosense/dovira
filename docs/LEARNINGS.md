# Learnings — Dovira

<!-- Harness defect log. Every time the agent did the wrong thing (overreach,
     misread instruction, ignored a rule, broken assumption) — it goes here,
     framed as a PROCESS defect, not a one-off annoyance. At sprint boundaries
     the user reviews this file and transfers fixes into the playbook repo
     (new playbook version). This is the improvement loop. At the retro every
     entry's "Transferred to playbook" is filled: a version, "local" (fixed in
     this project only) or "n/a" — "pending" survives only until the next
     retro. -->

Entry format:

## {{YYYY-MM-DD}} — [{{feature}}] {{What happened, one line}}
- **Incident:** {{what the agent did vs. what was expected}}
- **Root cause:** {{which instruction was missing, ambiguous, or overridable}}
- **Fix applied here:** {{change to this project's CLAUDE.md/commands/docs}}
- **Transferred to playbook:** {{version, or "pending"}}

---

## 2026-09-08 — [ga-telegram-bridge] A pathspec commit silently dropped every new file
- **Incident:** The working tree carried staged deletions of `templates/*` made outside the session, so each task was committed with `git commit -- <paths>` to keep them out of the step's commits. For task 2 the new files had not been `git add`ed first: the commit succeeded but contained only the already-tracked plan file — the tooling commit had no tooling in it.
- **Root cause:** The assumption that a pathspec commit picks up untracked files under those paths. It does not: it commits only what is already in the index. Nothing in the step protocol asks for a check of what a commit actually contains.
- **Fix applied here:** `git add <paths>` before every pathspec commit and `git show --stat HEAD` after it; the empty commit was redone via `git reset --soft HEAD~1`. Standing rule for this project: after each task commit, read its stat output against the plan's "Files to create/change".
- **Transferred to playbook:** pending
