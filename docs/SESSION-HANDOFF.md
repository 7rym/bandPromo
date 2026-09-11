# Session Handoff

## Resume point

**Published deep Spandexual heal** — Site-update then Refresh site files. Brand shell delivery can rebuild from `media/special/`; config no longer keeps poison delivery URLs; Hide demo should unlock for Premature Release-style playlists.

### Root cause (why admin looked fine while publish failed)

- Sharing / admin showed `card.png` labels and cached previews without verifying delivery files on disk.
- Brand shell clones live under `media/special/`; Python optimizeMedia only looked in visual/img/photo originals — so shell assets never rebuilt.
- Config sync re-wrote stale delivery paths when resolve failed.
- Hide-demo required campaign `tracks[]`, not playlist entries.

### Shipped / published already

Prior: **v0.8.44 build 456** — disk-check cover URLs. **455** catalog orphan. **454** FLAC cover heal.

### Also pending

- Confirm Spandexual after update: player covers, social crops, hide demo checkbox
- Timed Lyrics/Notes; favicon/PWA; legacy audit

### Active fleet

| Host | Persona |
|------|---------|
| bandpromo.site | Vanilla |
| hitz.no | HITZ |
| spandexualtension.com | Band / release sequence |

### Local workspace

Checkout is **`C:\dev\bandpromo`** with `.git` in-tree. Never wipe `data/` / `media/` / `log/` / `backups/` here.
