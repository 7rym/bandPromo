# Session Handoff

## Resume point

HITZ: **track cover replace reverts to old art** — root cause found and fixed locally (not published yet).

After save, playlist republish ran a sparse `refresh_audio_display` (often because Description/comment is empty). That reused a **stale request-scoped Python inspect cache** whose `sidecar_cover` was still the previous Visual id, and wrote it back over the new `display.cover`. Player enrich also preferred the stored playlist track `cover` over the live registry assignment.

Fix (pending Site update): invalidate inspect cache after metadata/cover save; sparse refresh never replaces an existing registry cover; player enrich prefers registry `display.cover`.

Also still open: Cleaning House playlist association (`release_id=primary` → Active Retroscopy shell) — associate playlist on HITZ.

Published **v0.8.32 build 431** (shell binding). Cover fix needs a new publish.

Next: checkpoint/publish cover fix; HITZ associate Cleaning House playlist; legacy compat strip.

## Operator notes (HITZ feedback)

### Track cover replace — old image returns

Fixed locally (pending publish): stale inspect cache during post-save republish was restoring the previous cover. After Site update, re-assign the cover once on an affected track and confirm it sticks on reopen + `/play`.

### Playlist DnD “undefined function bandpromo_playlist_set_release_id”

Fixed in build 429: associations call `bandpromo_playlist_set_campaign_id()`. Site update, then Catalogue → campaign → Playlists → drag Available → Associated.

### Brand delete “Theme id is required”

Fixed in build 427. Site update, then delete the copy brand again.

### Duplicate Retroscopy hour

Likely from **Duplicate campaign**. Safe cleanup:

1. Catalogue → open the **copy** campaign → Delete → **Campaign only** or **Entire campaign** as needed.
2. Branding → delete **the Retroscopy hour copy** (needs build 427+).
3. Clean leftover copy gallery/page rows if any.

### Cleaning House playlist associations

Build 426+: Loading fixed; primary / Default-release containers appear in Available. DnD needs build 429+.

Playlist still reported on `release_id=primary` with empty Associated — drag Cleaning House into Associated after Site update.

### Missing covers / visual registry

Repair catalogue / Content autofix, then rebuild.

### Wrong living background / Retroscopy shell on Cleaning House

Admin Branding can be correct while `/play` showed Retroscopy shell. Fixed in build 431: effective brand inference, campaign brand save republishes owned playlists, association republishes payloads, player stops inheriting Active shell when playlist brand differs.

After Site update: Catalogue → Cleaning House → Branding → re-save once → hard-refresh `/play` on that playlist. **Also associate the playlist** (see above) or shell still falls through to Active.

### Stage 5 playlist build

Full build publishes player payloads for every playlist only. Metadata/cover validation is the separate `playlist-scan` / `validation-only` path (runs after audio metadata saves), not part of the heavy publish walk.

## Next session — Legacy compat strip

1. Drop dual JSON keys where JS is canonical-only.
2. `admin.php` `cntab=themes` → branding; Welcome/demo canonical links.
3. Remove URL/query aliases after testers settle on current builds.
4. Keep: `data/releases/` path, `release_id` fields, `data/themes/` migration.

## Plan document

`docs/ADMIN-EDITOR-REFACTOR.md`
