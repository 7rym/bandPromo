# Session handoff — resume here

_Paused: 2026-08-16 (after Publish SFX delivery + track-cover/Visual-delete polish checkpoint)._

## Exact resume point

**Do not wipe, replace, or “re-seed” local `data/`, `media/`, or `log/`.** `log/` holds analytics/audit test data. This Google Drive folder is the operator working copy.

Fresh-install tests always run on **https://bandpromo.site** (Vanilla). The other remote test sites are **Twisted Chronicles** and **HITZ**. Never this working copy.

| Item | Value |
|------|--------|
| Git | `main` @ **v0.8.25 build 392** (after this checkpoint) |
| App tester package | Tag `v0.8.25-build-392` (`bandPromo.zip` + `release-manifest.json`) |
| Demo package | Durable tag **`demo-content`** (unchanged this checkpoint — no Demo PRP content change) |
| Local runtime | Operator working copy — **never wipe** |

## Next

1. Optional: Site update on **bandpromo.site** to build 392 and spot-check login SFX + Rebuild includes `sfx-delivery`.
2. Continue product work from [TODO.md](TODO.md) / operator priority — do not reopen checked-off cover/Visual/SFX pipeline items unless regressions appear.

## Already done this stretch (do not redo)

- Track cover titles (`Track cover: {title}`), extract-only keywords + Captured seed.
- Safer Visual delete (default keep embedded art; optional strip checkbox).
- Blank cover after delete+rebuild: playlist inline delivery + `visual-delivery-catchup` stage.
- Publish **`sfx-delivery`** stage (skip-if-fresh `media/sfx/optimal/{ast_*}.mp3`).
- Operator-private Vanilla smoke suite lives outside the repo (`C:\Users\Trym\.bandpromo\SETUP-SMOKE-SUITE.md`).

## Constraints

- Windows + PowerShell. Python **3.6.9** floor.
- Unqualified “checkpoint” = bump + commit + push + GitHub Release.
- Unqualified “fresh install” = **bandpromo.site only**. Never delete local `data/` / `media/` / `log/`.
