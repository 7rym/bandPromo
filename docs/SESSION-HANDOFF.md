# Session Handoff

## Resume point

**Do not fake originals from masters** — policy corrected. Heal re-registers orphan `media/visual/master/ast_*` into the registry/Files pool only. Missing `original/` stays missing (provenance honesty). Site-update + Refresh site files.

### Policy

- **original/** = exact as-uploaded archival bytes (write-once). Never invent by copying masters.
- **master/** = durable working tier for pool, delivery, metadata.
- PCF/PBF are masters-only — hosts may legitimately have no originals after import.

### Operator steps (Spandexual)

1. Site update to latest published build.
2. Refresh site files — Visual pool should list masters even when `original/` stays short.
3. “Download original” may correctly fail when archival bytes are gone; prepared/master download remains.

### Also pending

- Confirm Spandexual Visual pool count vs original folder count (pool ≫ original is OK)
- Timed Lyrics/Notes; favicon/PWA; legacy audit

### Active fleet

| Host | Persona |
|------|---------|
| bandpromo.site | Vanilla |
| hitz.no | HITZ |
| spandexualtension.com | Band / release sequence |

### Local workspace

Checkout is **`C:\dev\bandpromo`** with `.git` in-tree. Never wipe `data/` / `media/` / `log/` / `backups/` here.
