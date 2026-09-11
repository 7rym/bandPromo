# Session Handoff

## Resume point

**Recover Visual masters on Spandexual** — Site-update to the build that re-registers uncatalogued `media/visual/master/ast_*` files, then **Refresh site files** (or Repair catalogue Apply). Files → Visual should grow beyond the 15 originals; master bytes were never wiped.

### What was wrong

- Files → Visual lists the **files index** (fed by registry + `original/`), not a live master-folder scan.
- Spandexual: `original/` and the pool showed ~15; `master/` still held many `ast_*` files with no registry rows.
- That broke the operator “content is safe / visible” promise at the catalogue layer — storage still had the masters.

### Operator steps (Spandexual)

1. Site update to latest published build.
2. System → Status → **Refresh site files** (or Content autofix / Repair catalogue Apply).
3. Confirm Files → Visual count rises; titles may be “Untitled …” until edited.
4. Re-attach brand shell / covers via Branding or Add existing if membership still empty.

### Also pending

- Confirm Spandexual after update: Visual pool count, Brand assets, player covers
- Timed Lyrics/Notes; favicon/PWA; legacy audit

### Active fleet

| Host | Persona |
|------|---------|
| bandpromo.site | Vanilla |
| hitz.no | HITZ |
| spandexualtension.com | Band / release sequence |

### Local workspace

Checkout is **`C:\dev\bandpromo`** with `.git` in-tree. Never wipe `data/` / `media/` / `log/` / `backups/` here.
