# Session Handoff

## Resume point

**Brand assets library membership** — Site-update to the build that heals empty `library_asset_ids`, then open Files → Brand assets (or Refresh site files). Logos/shell media should return under the brand; Orphans should stop listing every track cover.

### What was wrong

- Brand assets filters by **library membership**, not folder/`brand_id` alone.
- Empty `library_asset_ids: []` skipped the one-time migrate forever.
- Branding save cleared slot `asset_ids` whenever the delivery path was empty → libraries collapsed.
- Orphans listed the whole Visual warehouse minus members → track covers flooded the view.

### Operator steps (Spandexual)

1. Site update to latest published build.
2. Optional: System → Status → Refresh site files (runs library heal in build).
3. Files → Brand assets → **Spandexual Tension** / **All brands** — shell members should reappear.
4. If a logo is still missing from the library but exists in Visual, use **Add existing**.

### Also pending

- Confirm Spandexual after update: Brand assets membership, player covers, hide demo
- Timed Lyrics/Notes; favicon/PWA; legacy audit

### Active fleet

| Host | Persona |
|------|---------|
| bandpromo.site | Vanilla |
| hitz.no | HITZ |
| spandexualtension.com | Band / release sequence |

### Local workspace

Checkout is **`C:\dev\bandpromo`** with `.git` in-tree. Never wipe `data/` / `media/` / `log/` / `backups/` here.
