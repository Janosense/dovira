#!/usr/bin/env python3
"""playbook: v3.0.4 — action log.

One JSON line per Claude Code hook event, appended to
`.claude/log/{YYYY-MM-DD}.jsonl` under the project root. Registered for
every hook event in `.claude/settings.json`. Nothing else writes the file
and no step reads it: the playbook's author reads it in an analysis chat
(README → «Лог дій агента»), next to `docs/INCIDENTS.md`, whose `Log`
field points into it. The file is git-ignored. The agent never sees this
script run — it costs no tokens — and the script never fails the hook:
every error is swallowed and the exit code is 0, so logging cannot block
or slow a step.

Fields of a line (absent when the event does not carry them):
  ts      local time, ISO, seconds
  ev      hook event: SessionStart · UserPromptSubmit · UserPromptExpansion ·
          PreToolUse · PostToolUse · PostToolUseFailure · PermissionRequest ·
          Notification · SubagentStart · SubagentStop · Stop · PreCompact ·
          SessionEnd
  sid     session_id, first 8 characters
  agent   agent_id, first 8 — "" for the main context, set in a subagent
  tool    tool name (Read, Edit, Write, Bash, Grep, Glob, Task, …)
  id      tool_use_id, last 8 — pairs a PreToolUse with its PostToolUse
  target  what the tool was pointed at: the file path; the first 200
          characters of a Bash command; the subagent type and description;
          for UserPromptSubmit the prompt's first 120 characters as typed;
          for UserPromptExpansion `/{command_name} {command_args}` — the
          exact moment a playbook command was run by the user (/plan-step,
          /do-step, /close-step, /fix-step, /adhoc, /question); a command
          another command chains into by Reading its file (/plan-step →
          /do-step) has no expansion event — its Read is the marker;
          for AskUserQuestion the first question's text; for Stop the first
          120 characters of the last assistant message when the event
          carries it — a gate question, a hand-over or a report is then
          readable in the line; for Notification the message text
  ms      duration_ms when the event reports it
  out     size of tool_response in characters — how much came back into
          the context (a Read's file, a Bash command's output)
  ok      false on PostToolUseFailure
  note    PreCompact trigger (auto | manual), SessionStart source,
          UserPromptExpansion source (project | user | plugin), Notification
          type (permission_prompt | idle_prompt | …), Stop reason — when
          present
The three ways Claude Code asks the user, as the log shows them: a question
in the chat text ends the turn — `Stop` (target: the message's start), the
answer is the next UserPromptSubmit; AskUserQuestion is a tool — PreToolUse
when asked, PostToolUse when answered, `ms` the wait; a permission dialog —
PermissionRequest when shown (tool and target as for PreToolUse), and
Notification `permission_prompt` / `idle_prompt` while it waits.
What the log answers, by arithmetic over these lines: time inside tools by
tool and by command (every check-command run and its length); time of the
model — the gap between a PostToolUse and the next PreToolUse; time waiting
for the user — Stop to the next UserPromptSubmit; files read more than
once and the volume read; subagents; compactions; the phases of a step,
read from the events themselves (Read of `do-step.md`, Write of the plan
file, `git commit`, Write of the verification guide).
"""
import datetime
import json
import os
import sys


def main() -> None:
    raw = sys.stdin.read()
    try:
        d = json.loads(raw) if raw.strip() else {}
    except Exception:
        d = {}
    if not isinstance(d, dict):
        d = {}

    root = os.environ.get("CLAUDE_PROJECT_DIR") or d.get("cwd") or os.getcwd()
    ev = str(d.get("hook_event_name") or "")
    if not ev:
        return  # not a hook payload — nothing to record
    tool = str(d.get("tool_name") or "")
    ti = d.get("tool_input") or {}

    target = ""
    if isinstance(ti, dict):
        if tool == "Bash":
            target = str(ti.get("command") or "")[:200]
        elif tool in ("Task", "Agent"):
            target = (
                f"{ti.get('subagent_type') or ''}: {ti.get('description') or ''}"
            )[:120]
        elif tool == "AskUserQuestion":
            qs = ti.get("questions") or []
            first = qs[0] if isinstance(qs, list) and qs else {}
            target = str((first or {}).get("question") or "")[:120] \
                if isinstance(first, dict) else ""
        else:
            for key in ("file_path", "notebook_path", "pattern", "path",
                        "url", "query", "skill"):
                if ti.get(key):
                    target = str(ti[key])[:200]
                    break
    if ev == "UserPromptSubmit":
        target = str(d.get("prompt") or d.get("user_prompt") or "")[:120]
    elif ev == "UserPromptExpansion":
        name = str(d.get("command_name") or "")
        args = str(d.get("command_args") or "")
        target = (f"/{name} {args}".strip() if name
                  else str(d.get("prompt") or "")[:120])
    elif ev in ("Stop", "SubagentStop"):
        target = " ".join(str(d.get("last_assistant_message") or "").split())[:120]
    elif ev == "Notification":
        target = str(d.get("message") or "")[:120]

    now = datetime.datetime.now().astimezone()
    line = {
        "ts": now.isoformat(timespec="seconds"),
        "ev": ev,
        "sid": str(d.get("session_id") or "")[:8],
        "agent": str(d.get("agent_id") or "")[:8],
        "tool": tool,
        "id": str(d.get("tool_use_id") or "")[-8:],
        "target": target,
    }
    if d.get("duration_ms") is not None:
        line["ms"] = d["duration_ms"]
    resp = d.get("tool_response")
    if resp is not None:
        line["out"] = len(resp) if isinstance(resp, str) else len(
            json.dumps(resp, ensure_ascii=False))
    if ev == "PostToolUseFailure":
        line["ok"] = False
    note = (d.get("trigger") or d.get("source") or d.get("command_source")
            or d.get("expansion_type") or d.get("notification_type")
            or d.get("reason"))
    if note:
        line["note"] = str(note)[:80]

    log_dir = os.path.join(root, ".claude", "log")
    os.makedirs(log_dir, exist_ok=True)
    path = os.path.join(log_dir, now.strftime("%Y-%m-%d") + ".jsonl")
    with open(path, "a", encoding="utf-8") as fh:
        fh.write(json.dumps(line, ensure_ascii=False) + "\n")


if __name__ == "__main__":
    try:
        main()
    except Exception:
        pass
    sys.exit(0)
