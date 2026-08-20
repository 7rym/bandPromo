# Session Handoff

## Resume point

Published **HITZ association Loading hotfix** (claim playlists stuck on Default release / primary). Next: **admin legacy compat strip** (unchanged plan below), or further HITZ triage.

## Operator notes (HITZ feedback)

### Duplicate Retroscopy hour

Likely from **Duplicate campaign**. Safe cleanup:

1. Catalogue → open the **copy** campaign (title often ends in “copy”) → Delete → **Campaign only** if you only want the catalogue entry gone and media to stay in Files; or **Entire campaign** to also remove owned brand / playlists / galleries / pages created by that duplicate (shared media files are kept).
2. Then delete leftover **copy** brand / gallery / page rows under Branding / Galleries / Pages if they remain.
3. Do **not** delete the original Retroscopy hour campaign if that is still the live one.

### Cleaning House playlist not in campaign associations

Playlist meta showed **from the campaign "Default release"** (`primary`). Available pool previously hid primary-owned containers. Hotfix offers them in Available so the operator can drag Cleaning House onto the Cleaning House campaign. After associate: brand shell and player campaign context follow that campaign.

### Missing covers / “Not registered in the visual asset registry”

Extracted track covers sitting in Visual without registry rows. Operator: **System → Status → Repair catalogue** (or Content autofix), then refresh site files / rebuild so delivery thumbs appear. Re-pick covers in Audio master if needed.

## Next session — Legacy compat strip

1. Drop `data.releases` read path in JS once PHP is canonical-only; remove duplicate `releases` JSON keys from manage/duplicate campaign endpoints.
2. Fix migration gaps: `manage-brand.php` `?brand=`, `list-media.php` `?campaign=`, `admin.php` `cntab=themes` → branding.
3. Canonical Welcome/demo links.
4. Remove URL/query aliases after testers on current build.
5. Remove JSON `themes` aliases.
6. Keep: `data/releases/` path, `release_id` fields, `data/themes/` migration.

## Plan document

`docs/ADMIN-EDITOR-REFACTOR.md`
