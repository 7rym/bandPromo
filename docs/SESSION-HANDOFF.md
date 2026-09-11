# Session Handoff

## Resume point

**Brand colour honesty Phase 1** — Site-update, then check Branding Colours: Panels should tint transport/content glass; muted text on idle tabs. Phase 2 (not started): separate Player vs Content button stylers.

### Contract

- `#mediaplayer` — platform layout (scene, transport, scrubber); brand colours apply
- `#content-container` — freer for operators later; same colour scheme now
- `--panel-fill` = `surface_mid` × Panel dim (not black glass)
- Do not invent Visual originals from masters (prior policy)

### Operator steps

1. Site update to latest build.
2. Content → Branding: set a distinct Panels colour + Panel dim; confirm glass tints on `/play`.
3. Idle nav tabs should follow Muted text.

### Also pending

- Phase 2 button stylers (Player constrained / Content freer)
- Confirm Spandexual Visual pool after master re-register
- Timed Lyrics/Notes; favicon/PWA; legacy audit

### Active fleet

| Host | Persona |
|------|---------|
| bandpromo.site | Vanilla |
| hitz.no | HITZ |
| spandexualtension.com | Band / release sequence |

### Local workspace

Checkout is **`C:\dev\bandpromo`** with `.git` in-tree. Never wipe `data/` / `media/` / `log/` / `backups/` here.
