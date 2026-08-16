# Session handoff — resume here

_Paused: 2026-08-16 (after purge-delete + PRP chunk-import checkpoint)._

## Exact resume point

**Do not wipe, replace, or “re-seed” local `data/`, `media/`, or `log/`.** `log/` holds analytics/audit test data. This Google Drive folder is the operator working copy.

Fresh-install tests always run on **https://bandpromo.site** (Vanilla). The other remote test sites are **Twisted Chronicles** and **HITZ**. Never this working copy.

| Item | Value |
|------|--------|
| Git | `main` @ **v0.8.26 build 397** (after this checkpoint) |
| App tester package | Tag `v0.8.26-build-397` (`bandPromo.zip` + `release-manifest.json`) |
| Demo package | Durable tag **`demo-content`** (unchanged this checkpoint) |
| Local runtime | Operator working copy — **never wipe** |

## Next

1. Manual remote: Site update → re-import Spandexual `.prp` (Overwrite). Expect chunk progress + successful import (or size/header error). Then optional Entire-campaign delete test.
2. Continue from [TODO.md](TODO.md) / operator priority.

## Already done this stretch (do not redo)

- Catalogue delete: Entire campaign vs Release only.
- PRP chunked import: final-chunk assemble, `file_size` verify, ZIP header check, `data/upload_tmp` staging.
- Earlier: gallery asset packing, page registry on import, post-import deliverables, stream-extract.

## Constraints

- Windows + PowerShell. Python **3.6.9** floor.
- Unqualified “checkpoint” = bump + commit + push + GitHub Release.
- Unqualified “fresh install” = **bandpromo.site only**. Never delete local `data/` / `media/` / `log/`.
