# Session Handoff

## Resume point

Published checkpoint covering **HITZ Repair CPU budget** + **Environment host egress / multi-operator locks** (this session). HITZ should Site-update, then:

1. **Repair Apply** (may need 2–3 passes until Preview is quiet — deferred steps are intentional on 30s hosts).
2. **Refresh site files** for the ~449 missing delivery thumbnails (Repair does not build card/thumb variants).
3. Confirm Welcome no longer mis-routes delivery-only gaps to Repair; Environment shows egress + lock status.

### Shipped in this checkpoint

- Repair: CPU-aware yield / step budgets; incomplete-only audio display sync; lock release on fatal timeout.
- Environment: host egress probe; build/optimize/repair lock status.
- Site update refuses while Publish / Optimize / Repair locks are held; Manual Repair shares `catalog-repair.lock`.

### Active fleet

| Host | Persona |
|------|---------|
| bandpromo.site | Vanilla |
| hitz.no | HITZ |
| spandexualtension.com | Band / release sequence |

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here.
