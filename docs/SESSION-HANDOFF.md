# Session handoff — resume here

_Paused: 2026-08-18 (after checkpoint v0.8.29 build 408)._

## Exact resume point

**Do not wipe, replace, or “re-seed” local `data/`, `media/`, or `log/`.** `log/` holds analytics/audit test data. This Google Drive folder is the operator working copy.

Fresh-install tests always run on **https://bandpromo.site** (Vanilla). The other remote test sites are **Twisted Chronicles** and **HITZ**. Never this working copy.

**Next work:** page gallery **Parallax** preset (Grid / List / Carousel are shipped; Parallax is still compact tiles).

| Item | Value |
|------|--------|
| Git | `main` — checkpoint **v0.8.29 build 408** |
| App tester package | Publish **`v0.8.29-build-408`** after push |
| Demo package | Durable tag **`demo-content`** — unchanged (SHA256 `813689c4f96c2398ca6c22256940293d2af3444b156b40a9976d4ece2cad05e1`) |
| Local runtime | Operator working copy — **never wipe** |

## Next

1. Confirm GitHub Release **`v0.8.29-build-408`** published; **Site update** on bandpromo.site offers build 408.
2. Build the Gallery **Parallax** page-block preset (leave Grid / List / Carousel alone unless a bug appears).
3. **Deferred (v0.9 candidate):** code layout refactor — [CODE-LAYOUT-REFACTOR.md](CODE-LAYOUT-REFACTOR.md).

## Shipped in build 408 (do not redo)

- Page **Video** blocks (Audio/Loop chips, Width/Flow including Full row).
- Gallery page blocks: **Grid** (native ratios, Max across), **List** rows, **Carousel** (snap, peek, dots, optional in-view autorotate + Speed).
- Page editor chrome: sticky Page builder / Live preview, `--border2` headers, Page building blocks, removed redundant field labels.
- Video delivery keeps soundtrack by default; living shell/cover stays silent.
- `/play/{playlist}/…` path deep links work on php -S (not only `?playlist=`).
- Admin main tabs remember the last used sub-tab.

## Shipped in build 407 (do not redo)

- Admin header **Open player** links to `/play/` (was Open site).
- Hide demo catalog no longer treats demo-owned track covers / posters as external blockers. Real blockers name the track, playlist, gallery, page, or campaign to fix.

## Constraints

- Windows + PowerShell. Python **3.6.9** floor.
- Unqualified “checkpoint” = bump + commit + push + GitHub Release.
- Unqualified “fresh install” = **bandpromo.site only**. Never delete local `data/` / `media/` / `log/`.
- Browser automation: do not hang on long waits — ask the operator.
