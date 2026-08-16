# Session handoff — resume here

_Paused: 2026-08-16 (after checkpoint v0.8.27 build 399)._

## Exact resume point

**Do not wipe, replace, or “re-seed” local `data/`, `media/`, or `log/`.** `log/` holds analytics/audit test data. This Google Drive folder is the operator working copy.

Fresh-install tests always run on **https://bandpromo.site** (Vanilla). The other remote test sites are **Twisted Chronicles** and **HITZ**. Never this working copy.

| Item | Value |
|------|--------|
| Git | `main` @ **v0.8.27 build 399** (after this checkpoint) |
| App tester package | Tag `v0.8.27-build-399` (`bandPromo.zip` + `release-manifest.json`) |
| Demo package | Durable tag **`demo-content`** (unchanged this checkpoint) |
| Local runtime | Operator working copy — **never wipe** |

## Next

1. Remote: Site update on test hosts → confirm Admin Visual 👁️ preview + player Cover reflection / transport dim.
2. Optional: re-import Spandexual `.prp` / Entire-campaign delete test if still pending from 398.
3. Continue from [TODO.md](TODO.md) / operator priority.

## Already done this stretch (do not redo)

- Catalogue delete: Entire campaign vs Release only.
- PRP chunked import hardening (397).
- Post-import image-only sync + server deliverables queue; gallery master fallback; page editor picker/layout; gallery grid center; player lightbox uses `huge` (398).
- Player transport dim+blur; Beggars banquet + Cover reflection brand toggles; shell backgrounds prefer Visual `huge`; admin Visual lightbox/gallery/audio-cover previews use delivery URLs (399).

## Constraints

- Windows + PowerShell. Python **3.6.9** floor.
- Unqualified “checkpoint” = bump + commit + push + GitHub Release.
- Unqualified “fresh install” = **bandpromo.site only**. Never delete local `data/` / `media/` / `log/`.
