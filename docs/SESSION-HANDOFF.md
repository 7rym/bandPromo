# Session Handoff

## Resume point

**Published v0.8.40 build 451** — campaign delete/purge typo fix. **Next:** Site-update; retry delete entire campaign; re-export Cleaning House for playlists.

### Shipped / published already

**Last published:** **v0.8.40 build 451** (`v0.8.40-build-451`) — fix undefined `bandpromo_campaign_campaign_collect_asset_ids` on purge.

Also in this build: chunked upload append-as-received; background archive SHA worker; campaign navigator chrome + Brand Panel dim (from earlier session work).

### Also pending

- Timed Lyrics/Notes (policy locked — implement later)
- Fleet validate navigator; favicon/PWA; legacy audit
- v0.9: delete `release_id` fallback inside `bandpromo_document_campaign_id` after all test installs upgraded

### Active fleet

| Host | Persona |
|------|---------|
| bandpromo.site | Vanilla |
| hitz.no | HITZ |
| spandexualtension.com | Band / release sequence |

### Local workspace

Checkout is **`C:\dev\bandpromo`** with `.git` in-tree. Never wipe `data/` / `media/` / `log/` / `backups/` here.
