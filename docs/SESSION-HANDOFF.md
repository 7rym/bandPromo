# Session Handoff

## Resume point

**PCF brand must travel** — Import that only brought the campaign (dangling `brand_id`, Branding list missing Spandexual) is fixed: export/import now fail if the brand JSON is absent. Re-export from spandexualtension.com after Site update, or import the brand as a **PBF** then link it on the campaign. Also: Jobs SHA-pending UI fix (exports only).

### Also pending

- Shell / Player / Content preview parity (post-hotfix) — rename Common→Shell; shared `/play` markup+CSS; inert preview
- Campaign-switch shell backdrop crossfade (blink reports — separate UX slice)
- Favicon/PWA from Branding; Spandexual Visual pool confirm
- Timed Lyrics/Notes; legacy audit
- Future: mediaplayer skins add-on

### Active fleet

| Host | Persona |
|------|---------|
| bandpromo.site | Vanilla |
| hitz.no | HITZ |
| spandexualtension.com | Band / release sequence |

### Local workspace

Checkout is **`C:\dev\bandpromo`** with `.git` in-tree. Never wipe `data/` / `media/` / `log/` / `backups/` here.
