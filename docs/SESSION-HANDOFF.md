# Session handoff — resume here

_Paused: 2026-08-13. Next session: read this file first, then [MASTER-TIER-AUDIT.md](MASTER-TIER-AUDIT.md). Replace this file at the next session end (or delete it when the slice is idle)._

## Exact resume point

**Do T2 next. Do not redo T0/T1. Do not start T3–T7** except where a T2 path already forces a one-line delivery-only change.

| Item | Value |
|------|--------|
| Git | `main` @ `2da3dea`, in sync with `origin/main` |
| VERSION | **v0.8.15 build 381** |
| Tester package | **Not shipped.** No GitHub Release for 381. Site update still offers **build 380**. Do not publish unless the user asks. |
| Policy | Original (write-once) → master `ast_{ULID}` (working copy) → deliverables from masters. All families: audio, Visual, SFX, Brand assets. |
| Living-cover tag | Visual **asset id**, not video original filename |

Prior chat: [Master-tier T1 identity](6ba7d8b7-c568-4027-8633-c458b192a1e7).

## T1 — done (do not reopen unless a regression)

Covers and living covers persist as visual `ast_*` ids (registry `display`, master tags, playlist payload). Publish extract writes `media/visual/original/embedded-{hash}.*` and registers a Visual master; it does **not** mint `img/original/{audioStem}.*` or `configured_release_cover.*`. Admin living-cover picker stores the asset id. Player `cover` / `living_cover` are ids; `cover_url` / `animated_cover` are delivery URLs.

**Still open (intentionally T6, not T2):** one-shot filename autofix, then fail loud on leftover bare names (audit T1 last checkbox). Autofix already rewrites audio cover/living refs.

Key T1 files (already on 381): `living-cover-helpers.php`, `cover-art-helpers.php`, `playlist-storage.php`, `release-storage.php`, `save-audio-master-detail.php`, `audio-master-detail-helpers.php`, `upload-media.php`, `admin.js`, `makePlaylists.py`, `audioMasterMetadata.py`, `scripts/register_visual_original.php`.

## T2 — start here

Working copy is the **master**. Original is download/delete/provenance only.

Check off in [MASTER-TIER-AUDIT.md](MASTER-TIER-AUDIT.md) T2:

1. Files index rebuild/list/delete/reference by `master_filename` / asset id; original is a label. Notifications use **master** existence (not `media/audio/original/{file}`).
2. Public play = delivery; operator listen = master; **drop original as a playable variant** (keep Download original).
3. Drop demo original-FLAC playback fallback.
4. Publish/Python working paths: **master or fail**. Do not scan original dirs to enumerate work (`resolve_audio_working_path`, `visual_working_path_for_asset`, `visual_video_source_path`).
5. Video delivery queue from Visual video **masters**, not `media/video/original/`.
6. SFX public URL = optimal MP3 only; login/player never `/media/sfx/original/`.
7. Download `variant=original` **404** if original missing (no silent master substitute). Delete by asset id (original + master + delivery).

Cluster C3 in the audit lists the current breach files.

## Constraints (same as AGENTS.md)

- Windows + PowerShell. Python **3.6.9** floor (`scripts/`). Do not mint `ast_*` in Python; PHP registers, Python consumes `master_filename`.
- Demo campaign is **PRP only** (`bandPromo-demo.prp`). No parallel demo packages. `/media` and `/data` are git-ignored. Never commit `desktop.ini`.
- `scripts/visualMasterMetadata.py` is not Python 3.6-safe (`from __future__ import annotations`) — fix when that file is touched (T6), not as a drive-by in T2.
- Unqualified “checkpoint” = bump + commit + push + GitHub Release. This pause was **checkpoint, not ship**.

## First commands next session

```powershell
powershell -ExecutionPolicy Bypass -File scripts/session-start.ps1
```

Then implement T2 from the audit checkboxes. Update this file when you pause again.
