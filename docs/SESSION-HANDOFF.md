# Session handoff — resume here

_Paused: 2026-08-13 (after T6). Next session: read this file first, then [MASTER-TIER-AUDIT.md](MASTER-TIER-AUDIT.md). Replace this file at the next session end (or delete it when the slice is idle)._

## Exact resume point

**Do T7 next** (verify). Do not redo T0–T6 unless a verify failure forces a fix.

| Item | Value |
|------|--------|
| Git | `main` — T6 implemented locally; **checkpoint/publish when asked** |
| VERSION | **v0.8.19 build 385** (session bumped; build unchanged until checkpoint) |
| Tester package | Last shipped: `v0.8.18-build-385`. Publish a new package only after checkpoint. |
| Policy | Original → master `ast_*` → deliverables from masters |

## T6 — done (do not reopen unless a regression)

Fail loud and delete shims.

- Removed stem `video/photo/optimal` dual-read helpers; pool-ready / needs-delivery use Visual delivery only.
- Welcome/demo presence = Demo PRP marker / demo release doc (no `bandPromo_*.flac` original probes).
- `initialSiteSeed.py` gallery seed uses Visual registry + delivery / `asset_id`.
- Dead shims removed (`sfx_web_path`, demo FLAC fallback, living-cover original path, stem gallery poster dual-write, in-place special optimize).
- Content autofix remains one-shot original→master repair only.

## T7 — start here

Fresh **Demo PRP** install (masters-only) plus an operator upload — check off in [MASTER-TIER-AUDIT.md](MASTER-TIER-AUDIT.md) T7:

1. Files → Audio / Visual / Sound effects list **masters**; titles from registry; original name is secondary.
2. `/play` covers and living covers load `/media/visual/delivery/{ast_*}/…` only.
3. Login welcome/logged-in SFX is `media/sfx/optimal/{ast_*}.mp3`.
4. Publish extract does **not** create `img/original/{stem}.*`; new covers are Visual `ast_*`.
5. PRP export includes track-cover and living-cover **visual masters** and SFX masters.
6. Operator Download original works when original exists; 404 on PRP rows. Delete removes original+master+delivery.
7. Brand logo/poster/backgrounds resolve from `asset_ids` → visual delivery.

## Constraints (same as AGENTS.md)

- Windows + PowerShell. Python **3.6.9** floor. Demo campaign is **PRP only**.
- Unqualified “checkpoint” = bump + commit + push + GitHub Release.

## First commands next session

```powershell
powershell -ExecutionPolicy Bypass -File scripts/session-start.ps1
```

Then run T7 verify from the audit checkboxes.
