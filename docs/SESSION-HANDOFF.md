# Session Handoff

## Resume point

**Brand SFX missing from Files + PCF** — Spandexual Welcome / Logged-in play in Branding (optimal URLs) but were absent from Files → Sound effects and dropped from PCF when `media/sfx/master/` was missing. Fixed: promote optimal→master on export, heal/register delivery-only brand slots, list optimal-only SFX in Files. Site update, open Files → Sound effects once, then re-export PCF.

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
