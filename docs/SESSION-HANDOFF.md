# Session handoff — resume here

_Paused: 2026-08-13 (mid-session update after T2). Next session: read this file first, then [MASTER-TIER-AUDIT.md](MASTER-TIER-AUDIT.md). Replace this file at the next session end (or delete it when the slice is idle)._

## Exact resume point

**Do T3 next. Do not redo T0–T2. Do not start T4–T7** except where a T3 path already forces a one-line Brand/format change.

| Item | Value |
|------|--------|
| Git | `main` @ `19b0cbd`, in sync with `origin/main` |
| VERSION | **v0.8.16 build 382** |
| Tester package | **Shipped.** Tag `v0.8.16-build-382` (Publish release package workflow triggered). Site update should offer **build 382** once the release finishes. |
| Policy | Original (write-once) → master `ast_{ULID}` (working copy) → deliverables from masters |
| Living-cover tag | Visual **asset id** |

Prior chat: this session (Master-tier T2 working copy).

## T2 — done (do not reopen unless a regression)

Working copy is the master; original is download/delete/provenance only.

- Files index rebuilds registry-first by `master_filename`; `original_filename` is a label; notifications require master existence.
- Public play = delivery (`optimal`); operator listen = `master`; `original` is not a playable variant (`audio.php` maps legacy `original` → `master`).
- Demo original-FLAC playback fallback removed.
- Python `resolve_audio_working_path` / `visual_working_path_for_asset` / `visual_video_source_path` + `collect_audio_source_files`: master or fail.
- Video delivery queue from Visual video masters (`bandpromo_list_videos_needing_delivery`, `videoSourceDelivery.py`).
- SFX public URL = optimal MP3 only; login/player never `/media/sfx/original/`.
- Download `variant=original` 404 if missing; delete by asset id cleans original + master + delivery.

## T3 — start here

Deliverables from masters; kill stem dual-write/read. Check off in [MASTER-TIER-AUDIT.md](MASTER-TIER-AUDIT.md) T3:

1. Delete `process_track_cover` stem `img/optimal|thumb` dual-write; covers go through Visual `process_visual_image_asset`.
2. Retire `videoSourceDelivery.py` stem `video/optimal` + `video/poster` path for registered assets (already visual delivery for registry); finish stem retirement.
3. Stop in-place resize of `/media/special/*`.
4. `bandpromo_visual_resolve_url` public path: delivery only. Admin preview: master file URL or dedicated original-download — not original as page `<img src>`.
5. Gallery / page allowlist / `admin.js` `videoPosterPathFromSrc`: Visual delivery variants only.
6. `play/index.php`: drop `MEDIA_IMG_BASE='/media/img'` stem contract; `player.js` uses server `cover_url`.
7. Playlist cover helper: no `/media/img|photo/{thumb|optimal}/{stem}.jpg` fallback.

## Constraints (same as AGENTS.md)

- Windows + PowerShell. Python **3.6.9** floor (`scripts/`). Do not mint `ast_*` in Python; PHP registers, Python consumes `master_filename`.
- Demo campaign is **PRP only**. Never commit `desktop.ini`.
- `scripts/visualMasterMetadata.py` Python 3.6 debt remains T6.
- Unqualified “checkpoint” = bump + commit + push + GitHub Release.

## First commands next session

```powershell
powershell -ExecutionPolicy Bypass -File scripts/session-start.ps1
```

Then implement T3 from the audit checkboxes. Update this file when you pause again.
