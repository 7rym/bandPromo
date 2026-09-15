# Session Handoff

## Resume point

Published checkpoint: background **Repair Apply** + cooperative **Stop** for Repair and Refresh.

### After Site update (HITZ)

1. Preview → **Apply repairs** once (safe to leave the page; optional Stop).
2. **Refresh site files** for missing delivery thumbnails (Stop available between stages).

### Shipped

- Apply: Python `catalogRepair.py` supervisor → PHP CLI pipeline; browser only start/poll/stop.
- Stop: `log/*.stop` honoured after current Repair step / publish stage.
- Preview remains a quick sync check.

### Active fleet

| Host | Persona |
|------|---------|
| bandpromo.site | Vanilla |
| hitz.no | HITZ |
| spandexualtension.com | Band / release sequence |

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here.
