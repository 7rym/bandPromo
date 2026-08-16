# Session handoff — resume here

_Paused: 2026-08-16 (after checkpoint v0.8.27 build 401)._

## Exact resume point

**Do not wipe, replace, or “re-seed” local `data/`, `media/`, or `log/`.** `log/` holds analytics/audit test data. This Google Drive folder is the operator working copy.

Fresh-install tests always run on **https://bandpromo.site** (Vanilla). The other remote test sites are **Twisted Chronicles** and **HITZ**. Never this working copy.

| Item | Value |
|------|--------|
| Git | `main` @ **v0.8.27 build 401** (after this checkpoint) |
| App tester package | Tag `v0.8.27-build-401` (`bandPromo.zip` + `release-manifest.json`) |
| Demo package | Durable tag **`demo-content`** — SHA256 `36582ba1…` (export `prp-bandpromo-demo-20260816-200121`) |
| Local runtime | Operator working copy — **never wipe** |

## Next

1. **Vanilla fresh-install smoke on bandpromo.site** — after Site update / re-bootstrap to **build 401**, resume setup **Start building** (ffmpeg auto-install now uses ffbinaries GitHub). Then C–F.
2. Confirm new brand settings arrive via Demo PRP.
3. Continue from [TODO.md](TODO.md) / operator priority.

## Already done this stretch (do not redo)

- Catalogue delete: Entire campaign vs Release only.
- PRP chunked import hardening (397).
- Post-import image-only sync + server deliverables queue; gallery master fallback; page editor picker/layout; gallery grid center; player lightbox uses `huge` (398).
- Player transport dim+blur; Beggars banquet + Cover reflection brand toggles; shell backgrounds prefer Visual `huge`; admin Visual lightbox/gallery/audio-cover previews use delivery URLs (399).
- `.cursor/` untracked (local IDE only); Demo PRP republished with brand settings (400).

## Constraints

- Windows + PowerShell. Python **3.6.9** floor.
- Unqualified “checkpoint” = bump + commit + push + GitHub Release.
- Unqualified “fresh install” = **bandpromo.site only**. Never delete local `data/` / `media/` / `log/`.
- Browser automation: do not hang on long waits — ask the operator.
