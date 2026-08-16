# Session handoff — resume here

_Paused: 2026-08-16 (after checkpoint v0.8.27 build 398)._

## Exact resume point

**Do not wipe, replace, or “re-seed” local `data/`, `media/`, or `log/`.** `log/` holds analytics/audit test data. This Google Drive folder is the operator working copy.

Fresh-install tests always run on **https://bandpromo.site** (Vanilla). The other remote test sites are **Twisted Chronicles** and **HITZ**. Never this working copy.

| Item | Value |
|------|--------|
| Git | `main` @ **v0.8.27 build 398** (after this checkpoint) |
| App tester package | Tag `v0.8.27-build-398` (`bandPromo.zip` + `release-manifest.json`) |
| Demo package | Durable tag **`demo-content`** (unchanged this checkpoint) |
| Local runtime | Operator working copy — **never wipe** |

## Next

1. Remote: Site update → re-import Spandexual `.prp` (Overwrite). Expect gallery thumbs/Bio gallery, deliverables rebuild starting (or clear System → Deliverables prompt).
2. Optional Entire-campaign delete test after import looks good.
3. Continue from [TODO.md](TODO.md) / operator priority.

## Already done this stretch (do not redo)

- Catalogue delete: Entire campaign vs Release only.
- PRP chunked import hardening (397).
- Post-import image-only sync + server deliverables queue; gallery master fallback; page editor picker/layout; gallery grid center; player lightbox uses `huge`.

## Constraints

- Windows + PowerShell. Python **3.6.9** floor.
- Unqualified “checkpoint” = bump + commit + push + GitHub Release.
- Unqualified “fresh install” = **bandpromo.site only**. Never delete local `data/` / `media/` / `log/`.
