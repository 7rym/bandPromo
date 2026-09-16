# Session Handoff

## Resume point

Published dry-run safety pass for HITZ. On **hitz.no**: Site update to the new build, then click **Refresh site files** once — the first run defaults to a **read-only dry-run** (no prep heal, no media rebuild). Read the duplicate report in Peek under the hood. Do **not** run a full Refresh until you have reviewed that report.

### Fixed this session

- Log window: auto-refresh no longer steals scroll while reading older lines
- Next Refresh defaults to one-time `dry-run` profile (localStorage flag)
- `dry-run` skips publish prep + mutating stages; runs `buildDryRunDiagnostics.py` only
- Dry-run preflight is read-only (no mkdir/seed/pip install/ffmpeg download)

### Still follow-on

- Stop audio/visual master duplication at upload (content-hash / replace-in-place)
- Safe prune of orphan masters (operator confirm)
- Prep stripped to inventory + worklist (mutations only via Repair/Apply)
- Full Status page redesign
- Native Python catalogue / Repair Apply bodies

### Active fleet

| Host | Persona |
|------|---------|
| bandpromo.site | Vanilla |
| hitz.no | HITZ |
| spandexualtension.com | Band / release sequence |

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here.
