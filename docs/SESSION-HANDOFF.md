# Session Handoff

## Resume point

Published **v0.8.32 build 431** (pending session-end): player shell brand binding — Cleaning House no longer keeps Retroscopy/Base logo/living when campaign brand is set.

Next: legacy compat strip; HITZ covers / duplicate cleanup after Site update.

## Operator notes (HITZ feedback)

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

### Missing covers / visual registry

Repair catalogue / Content autofix, then rebuild.

### Wrong living background / Retroscopy shell on Cleaning House

Admin Branding can be correct while `/play` showed Retroscopy shell. Fixed in build 431: effective brand inference, campaign brand save republishes owned playlists, association republishes payloads, player stops inheriting Active shell when playlist brand differs.

After Site update: Catalogue → Cleaning House → Branding → re-save once → hard-refresh `/play` on that playlist.

### Stage 5 playlist build

Full build publishes player payloads for every playlist only. Metadata/cover validation is the separate `playlist-scan` / `validation-only` path (runs after audio metadata saves), not part of the heavy publish walk.

## Next session — Legacy compat strip

1. Drop dual JSON keys where JS is canonical-only.
2. `admin.php` `cntab=themes` → branding; Welcome/demo canonical links.
3. Remove URL/query aliases after testers settle on current builds.
4. Keep: `data/releases/` path, `release_id` fields, `data/themes/` migration.

## Plan document

`docs/ADMIN-EDITOR-REFACTOR.md`
