---
name: "bandPromo Session Start"
description: "Start a bandPromo dev session: sync repo, bump session number, start dev server, and summarize what to work on next."
argument-hint: "Optional extra session context"
agent: "agent"
---
Use [session-start.ps1](../../scripts/session-start.ps1) as the default startup path for this repository.

Run the session-start script first when tool access allows it. Then respond with a compact startup summary that includes:

- active environment and runtimes
- repository sync result (`git pull --ff-only origin main`)
- current `VERSION` after the session-number bump (`major.minor.session build`)
- dev server URL if it started successfully
- current git/worktree state
- available workspace tasks relevant to bandPromo
- current milestone target and first unresolved v0.8 tasks from `docs/TODO.md`
- recommended focus for this session

If the user supplied extra context, fold it into the recommendation instead of restating the whole backlog. Keep the answer concise.

Session end is handled separately by [session-end.ps1](../../scripts/session-end.ps1) or `/bandpromo-session-end`.
