# Session Handoff

## Resume point

**Restore Visual originals from masters** — Site-update, then **Refresh site files**. Missing `media/visual/original/` files are copied back from durable `media/visual/master/` (plus orphan masters are re-registered).

### Why originals looked wiped

- Durable Visual bytes live in **master/**. PCF/PBF are **masters-only** — import does not recreate originals.
- Heal previously skipped “invent original from master,” so a short `original/` folder could stick while masters remained.
- Intentional deletes remove both tiers; demo hide does not delete files. No evidence of a silent original-only wipe in publish/site-update relocate (that only clears legacy leftovers after copy).

### Operator steps (Spandexual)

1. Site update to latest published build.
2. System → Status → **Refresh site files**.
3. Confirm `media/visual/original/` and Files → Visual grow with the masters.
4. Re-attach brand shell / covers if membership still empty.

### Also pending

- Confirm Spandexual Visual pool + original folder counts
- Timed Lyrics/Notes; favicon/PWA; legacy audit

### Active fleet

| Host | Persona |
|------|---------|
| bandpromo.site | Vanilla |
| hitz.no | HITZ |
| spandexualtension.com | Band / release sequence |

### Local workspace

Checkout is **`C:\dev\bandpromo`** with `.git` in-tree. Never wipe `data/` / `media/` / `log/` / `backups/` here.
