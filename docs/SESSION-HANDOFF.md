# Session handoff — resume here

_Paused: 2026-08-14 (checkpoint). Next session: read this file first, then run session-start._

## Exact resume point

**Do this next:** full **fresh-install smoke** against the published app package **and** the new durable Demo PRP.

Do **not** reopen Files → Visual Catalogue / In use identity, Brand library, or master-tier T0–T7 unless the fresh install fails.

| Item | Value |
|------|--------|
| Git | `main` (this checkpoint) |
| VERSION | **v0.8.24 build 389** (confirm after bump) |
| App tester package | Tag `v0.8.24-build-389` (`bandPromo.zip` + `release-manifest.json`) |
| Demo package | Durable tag **`demo-content`** — refreshed from localhost export `prp-bandpromo-demo-20260814-183925-6d8324` (~216MB) |
| Policy | Original → master `ast_*` → deliverables from masters. Demo = PRP only. Visual usage identity = `ast_*` (not titles/stems). |

## Next session goals

1. **Confirm GitHub assets**
   - App release `v0.8.24-build-389` has only `bandPromo.zip` + `release-manifest.json`.
   - Tag `demo-content` has `bandPromo-demo.prp` + `demo-manifest.json`.

2. **Full fresh-install test**
   - Wipe runtime roots: `data/`, `media/`, `log/` (keep the PRP zip under `backups/` until import is proven). Never delete tracked templates.
   - Remove `data/.setup_complete` (gone with `data/`) and local `web-config.json`.
   - Run setup; confirm it imports `bandPromo-demo.prp` as a normal PRP then locks it.
   - Smoke: Files lists masters; Visual Catalogue / In use by `ast_*`; `/play` covers = Visual delivery; login SFX = `sfx/optimal/{ast_*}.mp3`; brand slots resolve; Rebuild all deliverables ends with “YOUR SITE IS READY”.

## Already done (do not redo)

- Master-tier T0–T7 complete.
- Files → Visual Catalogue is campaign usage (galleries, covers, posters, pages, Brand visual shell those campaigns play, including Base-brand fallback). Brand library membership is not a campaign and not Orphan.
- Visual In use matches by `ast_*` after resolving stored refs; titles/stems never match.
- Brand library (`library_asset_ids`), Branding delivery-URL picks, list thumb sizes, Files download reliability.
- Generated OG/1080 crops stay out of the Visual pool (`media/share/`).
- Demo PRP exported 2026-08-14 18:39 UTC and prepared for `demo-content` publish.

## Constraints (same as AGENTS.md)

- Windows + PowerShell. Python **3.6.9** floor.
- Unqualified “checkpoint” = bump + commit + push + GitHub Release.
- Demo content publish is separate from app release packaging.

## First commands next session

```powershell
powershell -ExecutionPolicy Bypass -File scripts/session-start.ps1
```

Then confirm GitHub Releases, wipe runtime roots, and run setup.
