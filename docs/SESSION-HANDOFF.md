# Session handoff — resume here

_Paused: 2026-08-16 (after chunked PRP import checkpoint)._

## Exact resume point

**Do not wipe, replace, or “re-seed” local `data/`, `media/`, or `log/`.** `log/` holds analytics/audit test data. This Google Drive folder is the operator working copy.

Fresh-install tests always run on **https://bandpromo.site** (Vanilla). The other remote test sites are **Twisted Chronicles** and **HITZ**. Never this working copy.

| Item | Value |
|------|--------|
| Git | `main` @ **v0.8.26 build 395** (after this checkpoint) |
| App tester package | Tag `v0.8.26-build-395` (`bandPromo.zip` + `release-manifest.json`) |
| Demo package | Durable tag **`demo-content`** (unchanged this checkpoint) |
| Local runtime | Operator working copy — **never wipe** |

## Next

1. Manual: Site update on the test host → re-import Spandexual `.prp` (Overwrite). Expect 2 MB chunk progress, then import success (or a concrete error).
2. Optional: Site update on **bandpromo.site** — Demo PRP refresh + legacy Visual relocate when old folders exist.
3. Continue from [TODO.md](TODO.md) / operator priority.

## Already done this stretch (do not redo)

- Admin PRP import: 2 MB chunked upload + assemble + stream-extract (fixes nginx 413 / OOM).
- Site update Demo PRP refresh, Visual intake retirement, living-cover heal, Publish SFX (earlier builds).

## Constraints

- Windows + PowerShell. Python **3.6.9** floor.
- Unqualified “checkpoint” = bump + commit + push + GitHub Release.
- Unqualified “fresh install” = **bandpromo.site only**. Never delete local `data/` / `media/` / `log/`.
