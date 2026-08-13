# Session handoff — resume here

_Paused: 2026-08-13 (after T3). Next session: read this file first, then [MASTER-TIER-AUDIT.md](MASTER-TIER-AUDIT.md). Replace this file at the next session end (or delete it when the slice is idle)._

## Exact resume point

**Do T4 next. Do not redo T0–T3. Do not start T5–T7** except where a T4 path already forces a one-line format change.

| Item | Value |
|------|--------|
| Git | Local T3 work on `main` (not checkpointed yet unless session end ran) |
| VERSION | Session may still be **v0.8.16 build 382** until checkpoint |
| Tester package | Last shipped: **build 382** |
| Policy | Original → master `ast_*` → deliverables from masters |

## T3 — done (do not reopen unless a regression)

Deliverables from masters; stem dual-write/read removed.

- No `process_track_cover` / `img/optimal|thumb` dual-write; covers via Visual `process_visual_image_asset`.
- `videoSourceDelivery` / `optimizeVideo.process_one_video`: Visual delivery only (registered masters).
- No in-place `/media/special` resize.
- `bandpromo_visual_resolve_url`: delivery only; admin may fall back to master URL (not original).
- Gallery / page allowlist / admin+player posters: Visual delivery only.
- `/play` dropped `MEDIA_IMG_BASE`; player trusts `cover_url` + delivery candidates for `ast_*`.
- Playlist cover helper: no stem `img|photo` fallbacks.

## T4 — start here

Brand assets and leftover folders. Check off in [MASTER-TIER-AUDIT.md](MASTER-TIER-AUDIT.md) T4:

1. Relocate `media/special/` brand visuals into Visual original + `ast_*` master + delivery; brand slots are `asset_ids` only.
2. Brand duplicate clones **new `ast_*` masters**, not `{brand}_{slot}` files.
3. Setup/runtime dirs: stop creating `img/photo` optimal/thumb as product paths.
4. Config / OG / login / player shell fallbacks resolve Base brand `asset_ids` → delivery.
5. PRP SFX: export master or refuse; never pack `sfx/original`.
6. Files → Brand assets becomes a filter/role on Visual (or SFX).

## Constraints (same as AGENTS.md)

- Windows + PowerShell. Python **3.6.9** floor. Demo campaign is **PRP only**.
- Unqualified “checkpoint” = bump + commit + push + GitHub Release.

## First commands next session

```powershell
powershell -ExecutionPolicy Bypass -File scripts/session-start.ps1
```

Then implement T4 from the audit checkboxes.
