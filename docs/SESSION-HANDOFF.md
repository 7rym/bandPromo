# Session handoff — resume here

_Paused: 2026-08-16 (after PRP gallery/page/deliverables checkpoint)._

## Exact resume point

**Do not wipe, replace, or “re-seed” local `data/`, `media/`, or `log/`.** `log/` holds analytics/audit test data. This Google Drive folder is the operator working copy.

Fresh-install tests always run on **https://bandpromo.site** (Vanilla). The other remote test sites are **Twisted Chronicles** and **HITZ**. Never this working copy.

| Item | Value |
|------|--------|
| Git | `main` @ **v0.8.26 build 396** (after this checkpoint) |
| App tester package | Tag `v0.8.26-build-396` (`bandPromo.zip` + `release-manifest.json`) |
| Demo package | Durable tag **`demo-content`** (unchanged this checkpoint) |
| Local runtime | Operator working copy — **never wipe** |

## Next

1. Manual: Site update on the test host → **re-export** Spandexual `.prp` locally first (old package missing gallery masters) → import → confirm brand/gallery/page + deliverables rebuild.
2. Optional: Site update on **bandpromo.site**.
3. Continue from [TODO.md](TODO.md) / operator priority.

## Already done this stretch (do not redo)

- PRP: gallery `src`→`asset_id` heal for export; page registry on import; auto deliverables-only after import.
- Chunked PRP import, stream-extract, Visual intake retirement, Demo PRP Site-update refresh.

## Constraints

- Windows + PowerShell. Python **3.6.9** floor.
- Unqualified “checkpoint” = bump + commit + push + GitHub Release.
- Unqualified “fresh install” = **bandpromo.site only**. Never delete local `data/` / `media/` / `log/`.
