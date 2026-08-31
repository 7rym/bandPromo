# Session Handoff

## Resume point

**Hotfix build 440** — parse error from 439 (`brand-storage.php` stray `return` after `bandpromo_brand_admin_registry_entries`) that blanked HITZ (HTTP 500).

### Immediate

1. Publish **v0.8.37 build 440** and Site-update HITZ (and any host already on 439).
2. Confirm https://hitz.no/ loads login again.
3. Re-run hide-demo smoke from 439 on HITZ after recovery.

### Active fleet

| Host | Persona |
|------|---------|
| bandpromo.site | Vanilla |
| hitz.no | HITZ |
| spandexualtension.com | Band / release sequence |

Twisted Chronicles paused until v0.9 reinstall.

### v0.8 exit gate next

1. Player Campaign navigator — policy lock → ship → validate.
2. PCF round-trip smoke on active fleet.
3. Favicon/PWA from Branding; legacy audit refresh.

Last published target: **v0.8.37 build 440** (`v0.8.37-build-440`).
