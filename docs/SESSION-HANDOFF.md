# Session Handoff

## Resume point

**Container ownership hard cut + large PCF import/export** — this checkpoint. **Next after Site update:** re-export Cleaning House on source host and re-import locally (playlists should travel); fleet validate navigator.

### Shipped this checkpoint

- Container ownership hard cut (`campaign_id` helpers; central legacy read until v0.9 fleet cut)
- PCF/PBF import-as-job after chunked assemble
- Chunked upload append-as-received; skip inline SHA for large assemblies
- Background Ready SHA worker + UI “SHA pending…”
- Campaign navigator chrome + Brand Panel dim split (earlier in session)
- Backup Jobs: sliced export, smallest-first pack, Ready without multi-GB SHA, Cancel

### Also pending

- Timed Lyrics/Notes (policy locked — implement later)
- Fleet validate navigator; favicon/PWA; legacy audit
- v0.9: delete `release_id` fallback inside `bandpromo_document_campaign_id` after all test installs upgraded

### Shipped / published already

**Last published:** updated by session-end below after this push.

### Active fleet

| Host | Persona |
|------|---------|
| bandpromo.site | Vanilla |
| hitz.no | HITZ |
| spandexualtension.com | Band / release sequence |

### Local workspace

Checkout is **`C:\dev\bandpromo`** with `.git` in-tree. Never wipe `data/` / `media/` / `log/` / `backups/` here.
