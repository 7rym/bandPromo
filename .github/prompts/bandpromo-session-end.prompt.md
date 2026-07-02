---
name: "bandPromo Session End"
description: "Close a bandPromo dev session: validate docs/state, bump build number, commit, build package, and optionally publish the GitHub release."
argument-hint: "Checkpoint commit message (required)"
agent: "agent"
---
Use [session-end.ps1](../../scripts/session-end.ps1) for repository checkpointing.

Before running session end, make sure:

- tracked work is ready to checkpoint
- `docs/CHANGELOG.md` has a timestamped entry for this session
- planning docs are updated when behavior or scope changed

Run session end with a clear commit message. Prefer the full publishable path unless the user asked for status-only:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/session-end.ps1 `
  -CommitMessage "Checkpoint v0.8.x build N: short summary" `
  -Push `
  -Publish `
  -ReleaseSummary "short tester-facing summary"
```

The script will:

1. validate git/docs state
2. bump the build number in `VERSION` with `python scripts/bump_version.py`
3. commit non-forbidden tracked changes
4. build `dist/bandpromo-*.zip` locally
5. optionally push to `main` and trigger **Publish release package**

Report validation failures clearly instead of forcing a checkpoint through.
