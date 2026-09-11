# Session Handoff

## Resume point

**Hotfix:** campaign delete/purge undefined `bandpromo_campaign_campaign_collect_asset_ids` (typo). Publishing. **Next:** Site-update; retry delete; re-export Cleaning House for playlists.

### Shipped / published already

**Last published:** **v0.8.40 build 449** (`v0.8.40-build-449`) — PCF import-as-job, `campaign_id` ownership hard cut, Ready SHA pending for large exports.

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
