# Session handoff — resume here

_Paused: 2026-08-13 (after T5). Next session: read this file first, then [MASTER-TIER-AUDIT.md](MASTER-TIER-AUDIT.md). Replace this file at the next session end (or delete it when the slice is idle)._

## Exact resume point

**Do T6 next. Do not redo T0–T5. Do not start T7** except where a T6 path already forces a one-line verify.

| Item | Value |
|------|--------|
| Git | `main` — T5 implemented locally; **checkpoint/publish when asked** |
| VERSION | **v0.8.18 build 384** (session bumped; build unchanged until checkpoint) |
| Tester package | Last shipped: `v0.8.17-build-384`. Publish a new package only after checkpoint. |
| Policy | Original → master `ast_*` → deliverables from masters |

## T5 — done (do not reopen unless a regression)

Preferred master formats.

- Video materialize remuxes to `media/visual/master/ast_*.mkv`; Matroska tags via `visualMasterMetadata.py`; delivery stays MP4.
- Still masters: EXIF → `captured_at`; IPTC Core via XMP; heal empty display on materialize + autofix.
- Audio + SFX: WAV → FLAC masters; delivery `ast_*.mp3` under audio/sfx optimal.
- Living-cover ready = Visual `standard-stream` delivery exists.
- `visualMasterMetadata.py` is Python 3.6.9-safe (T6 item carried when touched).

## T6 — start here

Fail loud and delete shims. Check off in [MASTER-TIER-AUDIT.md](MASTER-TIER-AUDIT.md) T6:

1. Remove dual-read branches listed in C3/C4 once T1–T5 callers are gone.
2. Welcome starter-pack file list: drop `media/audio/original/bandPromo_*.flac` existence checks (Demo PRP marker / demo release doc only).
3. ~~`visualMasterMetadata.py` Python 3.6.9~~ (done in T5).
4. `initialSiteSeed.py` gallery `src`: Visual delivery / asset id, not `/media/photo|video/original/`.
5. Content autofix: keep one-shot original→master **repair** only; do not add new runtime original scans.

## Constraints (same as AGENTS.md)

- Windows + PowerShell. Python **3.6.9** floor. Demo campaign is **PRP only**.
- Unqualified “checkpoint” = bump + commit + push + GitHub Release.

## First commands next session

```powershell
powershell -ExecutionPolicy Bypass -File scripts/session-start.ps1
```

Then implement T6 from the audit checkboxes.
