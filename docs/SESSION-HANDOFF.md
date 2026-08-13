# Session handoff — resume here

_Paused: 2026-08-13 (session end). Next session: read this file first, then run session-start._

## Exact resume point

**Do this next:** refresh **Demo PRP** content, publish the durable `demo-content` package, then validate a **full fresh install**.

Do **not** reopen master-tier T0–T7 or the publish-summary work unless a fresh install fails.

| Item | Value |
|------|--------|
| Git | `main` @ `667145a` (in sync with `origin/main`) |
| VERSION | **v0.8.23 build 388** |
| App tester package | **Shipped.** Tag `v0.8.23-build-388` (`bandPromo.zip` + `release-manifest.json`) |
| Demo package | Durable tag **`demo-content`** — update only when campaign content changes |
| Policy | Original → master `ast_*` → deliverables from masters. Demo = PRP only. |

## Next session goals

1. **Update demo content**
   - On localhost: unlock the locked demo release if needed, edit/export the campaign as a new `.prp`.
   - Publish durable demo package (does **not** ship with every app build):

```powershell
python scripts/prepare_demo_content_package.py --prp path\to\export.prp --clean --publish
```

   - Confirm GitHub release tag `demo-content` has `bandPromo-demo.prp` + `demo-manifest.json`.
   - Contract: [PORTABILITY.md](PORTABILITY.md) (Demo PRP section).

2. **Full fresh-install test**
   - Clean host / empty install tree (or wipe runtime roots: `data/`, `media/`, `log/`, `backups/` per local practice — never delete tracked templates).
   - Run setup; confirm it imports `bandPromo-demo.prp` as a normal PRP then locks it.
   - Smoke: Files lists masters; `/play` covers = Visual delivery; login SFX = `sfx/optimal/{ast_*}.mp3`; brand slots resolve; Rebuild all deliverables ends with “YOUR SITE IS READY”.
   - Prefer testing against **build 388** Site update package (or newer) so publish-log + master-tier fixes are on the host.

## Already done (do not redo)

- Master-tier T0–T7 complete.
- Publish success banner + scoped counts (media / playlists / share images / manifest).
- Build-script dead-code cleanup; docs aligned.

## Constraints (same as AGENTS.md)

- Windows + PowerShell. Python **3.6.9** floor.
- Unqualified “checkpoint” = bump + commit + push + GitHub Release.
- Demo content publish is separate from app release packaging.

## First commands next session

```powershell
powershell -ExecutionPolicy Bypass -File scripts/session-start.ps1
```

Then start with Demo PRP export / `prepare_demo_content_package.py`, then the fresh-install smoke.
