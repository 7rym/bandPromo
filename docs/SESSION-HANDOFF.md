# Session Handoff

## Resume point

**Full audio dedupe (ID3+APE / dual-artwork clones):** Full check now demux-copies and XXH3-hashes the **entire** audio elementary stream for every candidate — no duration pre-bucket. Same compressed audio with different tags/artwork/file sizes matches; true remasters with different bitstreams do not. Video still uses duration buckets + min-prefix demux.

Publish next build before HITZ re-test. On HITZ: Site update → **Full check** (not Quick) to catch Cleaning House vs Remastered when streams match.

### HITZ recovery (post build 515 Apply fallout)

1. Site update to latest build (include remap-collapse + visual index strip + this Full audio fix).
2. Status → Quick check (file-hash clean) then **Full check** for tag-skewed audio clones.
3. Hard-refresh Files → Visual — index should match registry after rebuild.
4. SFX: still need register-in-place for `sfx/master` (not a dedupe delete).
5. Playlist/player smoke: if track covers or audio 404, restore from backup/PCF.

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here.
