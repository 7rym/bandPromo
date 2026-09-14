# Session Handoff

## Resume point

**HITZ publish false-fail hotfix** — catalogue stage was silent for >90s; poller cleared `build.lock` and showed “Deliverables rebuild failed” while the build was still running. Fix ready locally (PID-aware lock + catalogue progress/heartbeats). **Publish via session-end -Push -Publish** so HITZ can Site-update, then re-run Deliverables rebuild.

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
