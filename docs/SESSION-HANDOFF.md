# Session Handoff

## Resume point

Published **brand delete hotfix** (build 427): `manage-brand.php` accepts `?brand=`. HITZ can delete “the Retroscopy hour copy” after Site update.

Next: remaining legacy compat strip, or further HITZ triage.

## Operator notes (HITZ feedback)

### Brand delete “Theme id is required”

Fixed in build 427. Site update, then delete the copy brand again.

### Duplicate Retroscopy hour

Likely from **Duplicate campaign**. Safe cleanup:

1. Catalogue → open the **copy** campaign → Delete → **Campaign only** or **Entire campaign** as needed.
2. Branding → delete **the Retroscopy hour copy** (needs build 427+).
3. Clean leftover copy gallery/page rows if any.

### Cleaning House playlist associations

Build 426: Loading fixed; primary / Default-release containers appear in Available.

### Missing covers / visual registry

Repair catalogue / Content autofix, then rebuild.

## Next session — Legacy compat strip

1. Drop dual JSON keys where JS is canonical-only.
2. `admin.php` `cntab=themes` → branding; Welcome/demo canonical links.
3. Remove URL/query aliases after testers settle on current builds.
4. Keep: `data/releases/` path, `release_id` fields, `data/themes/` migration.

## Plan document

`docs/ADMIN-EDITOR-REFACTOR.md`
