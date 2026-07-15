# Legacy / fallback audit snapshot

_Date: 2026-07-15 — post fleet sync (build 332)_

## Remediated in this pass

| Item | Action |
|------|--------|
| Undefined `bandpromo_playlist_sync_legacy_artifacts()` call | Removed (prior session) |
| Dead `bandpromo_playlist_legacy_order_path()` | Removed (prior session) |
| Dead `bandpromo_gallery_sync_legacy_artifacts()` | Removed (prior session) |
| Silent `data/gallery.json` runtime fallback in `bandpromo_load_gallery_items()` | Removed — containers only; import remains in `bandpromo_gallery_migrate_from_legacy()` |
| `data/gallery.json` template seeding in `build.py` / `template-bootstrap.php` | Removed — galleries seed via `data/galleries/` |
| `play/playlist-validation.json` fallback reads | Removed — `data/validation/playlist-validation.json` only |
| Stale `play/playlist.json` build stage label | Renamed to playlist validation export |
| Misleading template names (`gallery.template.json`, `primary.release.template.json`) | Moved/renamed: `legacy/gallery-flat-array.json`, `default.release.template.json`; removed orphan `bandpromo-demo.gallery.template.json` |
| Default release labeled `system` in registry template | Removed — only `bandpromo-demo` is system-managed in admin |
| Operator copy "Primary Release" / "move to primary release" | Renamed to **Default release**; delete copy matches behavior (tracks stay in audio library) |

## Intentional migration shims (keep until visual pool / v0.9)

| Item | Reason |
|------|--------|
| `bandpromo_gallery_migrate_from_legacy()` reads `data/gallery.json` once | One-shot import when demo gallery document missing |
| `bandpromo_playlist_remove_legacy_main_playlist()` | One-shot `main` → `bandpromo-demo` migration |
| `bandpromo_gallery_remove_legacy_main_gallery()` | One-shot `main` gallery migration |
| Config dual-read (`bandpromo_config_legacy_fallbacks`) | Transitional schema window |
| Theme API names + `data/themes/` migration | Brand core transition |
| Admin `?tab=config\|build\|audit` redirects | Operator bookmarks |
| Hardcoded share/player meta defaults | v0.9 public-share work |
| `release-fallback` cover role | Documented install-level identity policy |

## Deferred to unified visual pool

- Illustrations / Photos / Video folder-category split in Files tab
- Sidecar image/video resolution paths in build scripts
- Visual `ast_{ULID}` registry-first pickers

## Fail-loud bar (AGENTS.md)

Runtime paths must not silently read removed artifacts. Missing validation reports return empty operator diagnostics until the next playlist-scan publish step — not legacy path fallbacks.
