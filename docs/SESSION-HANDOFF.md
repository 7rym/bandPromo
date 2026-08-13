# Session handoff — resume here

_Paused: 2026-08-13 (after T7 / master-tier plan complete). Next session: read this file first. Replace or delete when work resumes._

## Exact resume point

**Master-tier plan is complete (T0–T7).** Do not reopen unless a regression appears.

| Item | Value |
|------|--------|
| Git | `main` @ checkpoint T7 |
| VERSION | **v0.8.21 build 387** |
| Tester package | **Shipped.** Tag `v0.8.21-build-387`. |
| Policy | Original → master `ast_*` → deliverables from masters |

## Done this session (T7)

- Verified Files index lists `ast_*` masters; brand shell/SFX resolve delivery/optimal; player covers are visual delivery only; extract path is `visual/original`; download original 404s when missing.
- Hardened `bandpromo_playlist_enrich_tracks_for_player` (visual-only covers).
- Hardened content autofix `audio_visual_refs` (rewrite/clear invalid covers; never call `clear_player_payload_fields` when saving covers).

## Next work (outside master-tier)

Return to [TODO.md](TODO.md) / [ROADMAP.md](ROADMAP.md) v0.8 active items (config-driven player meta, access-tier enforcement, etc.).

## Constraints (same as AGENTS.md)

- Windows + PowerShell. Python **3.6.9** floor. Demo campaign is **PRP only**.
- Unqualified “checkpoint” = bump + commit + push + GitHub Release.

## First commands next session

```powershell
powershell -ExecutionPolicy Bypass -File scripts/session-start.ps1
```
