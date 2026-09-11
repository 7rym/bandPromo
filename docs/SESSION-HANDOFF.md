# Session Handoff

## Resume point

**Published player soft-nav + page tab label** — validate campaign switching on a Site-updated host after this release. Existing pages that imported with `label = title` may need a re-save (or re-export/import) for a distinct Player tab.

### Shipped / published already

**Last published:** see `VERSION` / GitHub Releases after this checkpoint (soft campaign/playlist navigation; skip same-brand chrome re-paint; page document `label` for player tabs + PCF round-trip).

Prior: **v0.8.40 build 451** — campaign delete/purge typo fix; chunked upload append-as-received; background archive SHA; campaign navigator chrome.

### Also pending

- Fleet: Site-update; retry delete entire campaign; re-export Cleaning House for playlists (ownership fix).
- Timed Lyrics/Notes (policy locked — implement later)
- Favicon/PWA; legacy audit
- v0.9: delete `release_id` fallback inside `bandpromo_document_campaign_id` after all test installs upgraded

### Active fleet

| Host | Persona |
|------|---------|
| bandpromo.site | Vanilla |
| hitz.no | HITZ |
| spandexualtension.com | Band / release sequence |

### Local workspace

Checkout is **`C:\dev\bandpromo`** with `.git` in-tree. Never wipe `data/` / `media/` / `log/` / `backups/` here.
