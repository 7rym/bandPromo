# Session Handoff

## Resume point

**PCF must include `data/brands/`** — Root cause: export cleared `brand_id` via registry normalize then omitted the brand file. Fixed to resolve/pack the brand from disk/ownership. On spandexualtension.com: Site update → re-export PCF → import here.

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
