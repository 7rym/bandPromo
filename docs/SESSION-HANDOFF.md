# Session Handoff

## Resume point

**Vanilla orphan homes (11 demo assets)** — root cause fixed; publish build with import stamp + Site update migration, then Site update vanilla again (or Review → Apply on 532 meanwhile).

### Cause

Demo/PCF registry merge only remapped homes when the campaign id changed, and same-id demo import passed **empty** source/target ids — so packaged assets with empty `release_id` stayed orphans while playlists still used them. Site update only auto-runs Quick check (finds them); it does not Apply treatment.

### Fix (this tree)

1. PCF merge always passes package campaign ids; empty/primary audio+visual homes → package campaign.
2. Post-import heal + install migration `orphan-homes-in-containers-b533` stamps unambiguous container orphans on Site update.

### Next

1. Checkpoint + publish.
2. Site update bandpromo.site → migration should clear The bad; or Apply on 532 as interim.

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here.
