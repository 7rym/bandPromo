# Session Handoff

## Resume point

HITZ trust emergency hotfix ready to publish (build bump on session-end). After Site update on **hitz.no**: run **Refresh site files** once — cover fallback no longer writes PNG as `.jpg`; Status should show real stage progress instead of false “stuck”.

### Fixed this session

- False stuck: poller exposes `log_age_s`; UI prefers log mtime; warn only after ~10 min silence; **Stop refresh** label
- Honest finish: warnings → “Finished with warnings”, not “Up to date”
- Prep log: no more “omitted from log”; visual reconcile heartbeats
- Double prep removed from `optimizeMedia.py`
- Cover conversion: in-process recover + asset id in warnings; never copy PNG bytes to `.jpg`
- Catalog/playlist supervisors keep `build.meta.json` heartbeats alive

### Still follow-on

- Full Status page redesign (not copy polish)
- Native Python catalogue registry mutations (supervisor is Python; rules still in `build-catalog-cli.php`)
- Native Python Repair Apply body / remaining PHP playlist CLI

### Active fleet

| Host | Persona |
|------|---------|
| bandpromo.site | Vanilla |
| hitz.no | HITZ |
| spandexualtension.com | Band / release sequence |

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here.
