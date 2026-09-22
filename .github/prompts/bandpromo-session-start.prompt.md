---
name: "bandPromo Session Start"
description: "Start a bandPromo work session: sync repo, bump session number, start dev server, and summarize what to work on next."
argument-hint: "Optional extra session context"
agent: "agent"
---
Use [session-start.ps1](../../scripts/session-start.ps1) **with `-BumpSession`** — this slash command is an explicit operator request to start a work session.

**Resume first:** if [docs/SESSION-HANDOFF.md](../../docs/SESSION-HANDOFF.md) exists, read it before exploring the backlog. It is the exact pause point. Do not redo checked-off work or jump ahead unless the user asks.

Run:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/session-start.ps1 -BumpSession
```

Then respond with a compact startup summary that includes:

- active environment and runtimes
- repository sync result (`git pull --ff-only origin main`)
- current `VERSION` after the session-number bump (`major.minor.session build`)
- dev server URL if it started successfully
- current git/worktree state
- available workspace tasks relevant to bandPromo
- current milestone target and first unresolved v0.8 tasks from `docs/TODO.md`
- recommended focus for this session

If the user supplied extra context, fold it into the recommendation instead of restating the whole backlog. Keep the answer concise.

Do **not** bump the session on ordinary chat resume — only when the operator uses this command or clearly asks to start a session.

Session end is handled separately by [session-end.ps1](../../scripts/session-end.ps1) or `/bandpromo-session-end`.
