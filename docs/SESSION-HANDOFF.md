# Session Handoff

## Resume point

**Spandexual cover / publish hotfix** — local fixes on `v0.8.42` (publish after Site-update + Refresh site files). Sticky assigned covers with missing Visual master/delivery now heal or re-extract; preflight rename fixed; Build log has Copy log.

### This session

- Root cause: assigned `display.cover` kept pointing at Visuals whose master/delivery was gone; audio XXH3 skip was a red herring.
- `ast_F831250M10AJHHFH05G6.png` “Skipped or failed” = no readable master (now falls back to original + clearer log).
- Preflight warning: `bandpromo_content_autofix_sync_releases` → `sync_campaigns`.

### Shipped / published already

**Last published:** **v0.8.41 build 453** — soft player campaign/playlist navigation; page tab `label` persistence.

### Also pending

- Fleet: Site-update Spandexual to this hotfix; Refresh site files; confirm broken playlist covers.
- Timed Lyrics/Notes; favicon/PWA; legacy audit
- v0.9: delete `release_id` fallback inside `bandpromo_document_campaign_id`

### Active fleet

| Host | Persona |
|------|---------|
| bandpromo.site | Vanilla |
| hitz.no | HITZ |
| spandexualtension.com | Band / release sequence |

### Local workspace

Checkout is **`C:\dev\bandpromo`** with `.git` in-tree. Never wipe `data/` / `media/` / `log/` / `backups/` here.
