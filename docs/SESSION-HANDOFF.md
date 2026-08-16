# Session handoff — resume here

_Paused: 2026-08-16 (after PRP import stream-extract checkpoint)._

## Exact resume point

**Do not wipe, replace, or “re-seed” local `data/`, `media/`, or `log/`.** `log/` holds analytics/audit test data. This Google Drive folder is the operator working copy.

Fresh-install tests always run on **https://bandpromo.site** (Vanilla). The other remote test sites are **Twisted Chronicles** and **HITZ**. Never this working copy.

| Item | Value |
|------|--------|
| Git | `main` @ **v0.8.26 build 394** (after this checkpoint) |
| App tester package | Tag `v0.8.26-build-394` (`bandPromo.zip` + `release-manifest.json`) |
| Demo package | Durable tag **`demo-content`** (unchanged this checkpoint) |
| Local runtime | Operator working copy — **never wipe** |

## Next

1. Manual: Site update on the test host → re-import Spandexual `.prp` (Overwrite). Confirm import succeeds or shows a concrete error (not bare "Import failed").
2. Optional: Site update on **bandpromo.site** — Demo PRP refresh + legacy Visual relocate when old folders exist.
3. Continue from [TODO.md](TODO.md) / operator priority.

## Already done this stretch (do not redo)

- PRP import streams ZIP entries to disk (`extractTo`); admin surfaces HTTP/body on non-JSON failures; fatal import returns JSON.
- Site update refreshes locked Demo PRP when published SHA is newer (skip unlocked localhost).
- Living-cover player gap heal (Rock Out) + publish sync of registry living_cover.
- Retire `media/img|photo|video` intake writes; unified `media/visual/original/` only.
- Publish + Site update gated one-shot legacy Visual relocate when those folders still exist.
- Earlier: track-cover titles/keywords, safer Visual delete, visual-delivery catch-up, Publish SFX stage (build 392).

## Constraints

- Windows + PowerShell. Python **3.6.9** floor.
- Unqualified “checkpoint” = bump + commit + push + GitHub Release.
- Unqualified “fresh install” = **bandpromo.site only**. Never delete local `data/` / `media/` / `log/`.
