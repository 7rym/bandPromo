# Session Handoff

## Resume point

**Admin in-app confirms (v0.8)** — `bandpromoConfirm` / `#adminConfirmModal` replaces native browser confirms in admin (Site update, Jobs, Backup import, editors, etc.). Toast→inbox OMP still v0.9. Smoke: Dashboard → Finish update should show the in-app modal, not the browser dialog.

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
