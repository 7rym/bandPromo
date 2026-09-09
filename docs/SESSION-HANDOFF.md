# Session Handoff

## Resume point

**Player Campaign navigator** — chrome re-locked 2026-09-09: static header logo; campaign logo strip + playlist selector inside the Playlists panel. **Next:** validate on Vanilla / Spandexual Tension / HITZ after checkpoint/publish; then PCF smoke, favicon/PWA, legacy audit.

### Shipped this session (not yet published)

- Campaign-first navigation; single-campaign / single-playlist collapse
- Static header logo (identity only); campaign **logo strip** at top of Playlists panel (wide ~2:1 chips; no visible “Campaigns” label); shares row with playlist selector on wide, stacks on narrow
- Campaign page tabs fixed to use page `campaign_id` ownership (tabs return after campaign switch); strip active/selectable contrast strengthened
- `localStorage` last campaign + playlist; campaign change stops playback (full navigation)
- Hard-cut URLs `/play/{campaign}/{playlist}/{track}` (no playlist-first legacy)
- Brand Panel dim separate from Backdrop dim; preview zero-value fix
- Backup Jobs: heartbeat + progress during PCF/PBF/site builds; auto-fail stale `building` after 30 min without heartbeat (HITZ Remixes orphan recovery)
- Policy: no speculative fallbacks ([AGENTS.md](AGENTS.md))

### Also pending

- Timed Lyrics/Notes (policy locked — implement later)
- Fleet validate navigator; PCF round-trip smoke; favicon/PWA; legacy audit
- **Publish** stale-backup recovery so HITZ can auto-fail / delete the stuck Remixes job, then re-export

### Shipped / published already

**Last published:** **v0.8.37 build 441** (`v0.8.37-build-441`) — identity migrate + Base brand fallthrough.

### Active fleet

| Host | Persona |
|------|---------|
| bandpromo.site | Vanilla |
| hitz.no | HITZ |
| spandexualtension.com | Band / release sequence |

### Local workspace

Checkout is **`C:\dev\bandpromo`** with `.git` in-tree. Never wipe `data/` / `media/` / `log/` / `backups/` here.
