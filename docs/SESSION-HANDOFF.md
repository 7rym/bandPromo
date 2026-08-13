# Session handoff — resume here

_Paused: 2026-08-13 (after T4). Next session: read this file first, then [MASTER-TIER-AUDIT.md](MASTER-TIER-AUDIT.md). Replace this file at the next session end (or delete it when the slice is idle)._

## Exact resume point

**Do T5 next. Do not redo T0–T4. Do not start T6–T7** except where a T5 path already forces a one-line format change.

| Item | Value |
|------|--------|
| Git | `main` — T4 implemented locally; **checkpoint/publish when asked** |
| VERSION | **v0.8.17 build 383** (session bumped; build unchanged until checkpoint) |
| Tester package | Last shipped: `v0.8.16-build-383`. Publish a new package only after checkpoint. |
| Policy | Original → master `ast_*` → deliverables from masters |

## T4 — done (do not reopen unless a regression)

Brand assets and leftover folders.

- Brand visuals: Visual original + `ast_*` master + delivery; slots via `asset_ids`; clone creates new masters (not `{brand}_{slot}` in `special/`).
- Setup dirs: `media/visual/{original,master,delivery}` + `media/sfx/{original,master,optimal}`; no product `img/photo` optimal/thumb or `media/special`.
- Config / OG / login / player: resolve Base brand `asset_ids` → delivery (no hardcoded `/media/special/bandPromo_*.png`).
- PRP SFX: master only or refuse the row; never pack `sfx/original`.
- Files → Brand assets: filter/role on Visual (Brand-tab audio → SFX); uploads to `media/visual/original/`.

## T5 — start here

Preferred master formats. Check off in [MASTER-TIER-AUDIT.md](MASTER-TIER-AUDIT.md) T5:

1. Video materialize: remux to `media/visual/master/ast_*.mkv` + Matroska tags; delivery stays MP4.
2. Still masters: EXIF read for `captured_at`; IPTC Core via XMP write-through; heal empty `display` from embeds.
3. Confirm WAV → FLAC audio master and SFX master/delivery naming match the policy table.
4. Living-cover “ready” = Visual `standard-stream` delivery exists (not `video/optimal/{stem}.mp4`).

## Constraints (same as AGENTS.md)

- Windows + PowerShell. Python **3.6.9** floor. Demo campaign is **PRP only**.
- Unqualified “checkpoint” = bump + commit + push + GitHub Release.

## First commands next session

```powershell
powershell -ExecutionPolicy Bypass -File scripts/session-start.ps1
```

Then implement T5 from the audit checkboxes.
