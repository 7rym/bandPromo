# Session Handoff

## Resume point

**Status health redesign** (plan `refresh_site_files_ui_899761a4`) — slice work with local checkpoints (no publish until asked).

### Done

- **Slice 1:** Thin `build.php` start; prep in Python → `publish-prep-cli.php`; heartbeats in meta; poller `job` fields; honest orphan-lock copy.

### Next

- **Slice 2:** Poll heartbeat → Refresh progress/stuck/failed chrome (friendly status line).
- **Slice 3:** Full System → Status health-page redesign (recommendation, consent, under the hood).
- **Slice 4:** Port Repair Apply off PHP CLI into Python; stuck prep clear.
- **Slice 5:** Operator health visibility + FEATURES docs.

### Active fleet

| Host | Persona |
|------|---------|
| bandpromo.site | Vanilla |
| hitz.no | HITZ |
| spandexualtension.com | Band / release sequence |

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here.
