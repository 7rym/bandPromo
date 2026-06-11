---
name: "bandPromo Session Start"
description: "Fast startup summary for the bandPromo repository: environment, git status, active tasks, current milestone, and next focus."
argument-hint: "Optional extra session context"
agent: "agent"
model: "GPT-5 (copilot)"
---
Use [session-start.ps1](../../scripts/session-start.ps1) as the default startup path for this repository.

Run the fast startup script first when tool access allows it. Then respond with a compact startup summary that includes:

- active environment and runtimes
- current git/worktree state
- available workspace tasks relevant to bandPromo
- current milestone target and first unresolved `v0.7` task
- recommended focus for this session

If the user supplied extra context, fold it into the recommendation instead of restating the whole backlog. Keep the answer concise.