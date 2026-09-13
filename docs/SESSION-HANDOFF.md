# Session Handoff

## Resume point

**Content editor breadcrumbs** — Catalogue, Playlists, Galleries, Pages, and Branding use shared `{Section} > Pool|Editor` headings; root crumb returns to Pool (same leave path as ← Back). Preferred pattern documented in ADMIN-UI / AGENTS / `.cursor/rules/admin-breadcrumb-headings.mdc`. Smoke each Content tab Pool↔Editor on local admin if not already checked.

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
