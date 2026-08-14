# Session handoff — resume here

_Paused: 2026-08-14 (checkpoint + fresh-install smoke)._

## Exact resume point

Fresh install against **v0.8.24 build 390** + durable `demo-content` PRP succeeded locally. Idle unless a hosted Site update / vanilla install needs a follow-up.

Do **not** reopen Files → Visual Catalogue / In use identity, Brand library, or master-tier T0–T7 unless a hosted install fails.

| Item | Value |
|------|--------|
| Git | `main` (build 390 hotfix after 389) |
| VERSION | **v0.8.24 build 390** |
| App tester package | Tag `v0.8.24-build-390` (`bandPromo.zip` + `release-manifest.json`) |
| Demo package | Durable tag **`demo-content`** — `bandPromo-demo.prp` SHA256 `13cb89b8…` (~216MB, export 2026-08-14 18:39 UTC) |
| Policy | Original → master `ast_*` → deliverables. Demo = PRP only. Visual usage = `ast_*`. |

## Fresh-install notes (local)

- Demo imported locked; Visual Catalogue/In use keyed by `ast_*`; Bio page pictures resolve; share crops in `media/share/`; publish ended **YOUR SITE IS READY** (25s).
- PHP CLI on this Windows host cannot fetch GitHub (missing local CA). Import used the published PRP bytes from `dist/demo-content/`. Hosted setup still downloads `demo-content`.
- SFX: first 389 pass left `media/sfx/optimal/` empty because materialize required originals. **390** encodes from the imported master and PRP import backfills optimal MP3s.
- Wiping `media/` locally also drops `media/icons/` (not in git). Hosted bootstrap/Site update restores icons from `bandPromo.zip`.

## Already done (do not redo)

- Master-tier T0–T7.
- Files → Visual Catalogue / In use by `ast_*`; Brand library; Branding delivery URL picks; Files download reliability.
- Demo PRP refreshed on GitHub `demo-content`.
- Local runtime wipe + Demo PRP import + full publish smoke.

## Constraints (same as AGENTS.md)

- Windows + PowerShell. Python **3.6.9** floor.
- Unqualified “checkpoint” = bump + commit + push + GitHub Release.
- Demo content publish is separate from app release packaging.
