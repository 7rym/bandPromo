# Session Handoff

## Resume point

**Audio master-first reconcile (uncommitted)** — Publish / Refresh / Repair catalogue / catalogue build now re-register uncatalogued `media/audio/master/ast_*` into the registry + Files → Audio (register-only; never invent originals; size-based leftover prune disabled). Matches Visual master recover. Playlist editor honesty fix (missing masters shown) still in the same local tree.

### HITZ next steps after Site update

1. Site update to this build, then **Refresh site files** or **Repair catalogue → Apply**.
2. Expect previously invisible leftover masters to appear under **Files → Audio** (many as Orphans).
3. Associate Retroscopy (and any other) orphans into the correct campaign Tracks, confirm playlist entries, rebuild deliverables if needed.
4. Keep the offline 108-file master ZIP until playback/catalogue look healthy.

### Also pending

- Shell / Player / Content preview parity
- Campaign-switch shell backdrop crossfade
- Follow-up: playlist stage still slow on large HITZ catalogues
- Follow-up: after master reconcile, campaign track membership may still need drag-associate or PCF if `tracks[]` empty

### Active fleet

| Host | Persona |
|------|---------|
| bandpromo.site | Vanilla |
| hitz.no | HITZ (needs this build for master reconcile salvage) |
| spandexualtension.com | Band / release sequence |

### Local workspace

Checkout is **`C:\dev\bandpromo`** with `.git` in-tree. Never wipe `data/` / `media/` / `log/` / `backups/` here.
