# Session Handoff

## Resume point

**HITZ dedupe fallout (build 515 Apply):** silent Full content pass deleted 652 clones; remap chains deleted file-hash keepers (e.g. `023DFK…` removed while still a target). Visual Files index kept stale rows → ~671 broken Unused thumbs vs 100 registry visuals. SFX empty is separate: registry still `0 sfx` (4 disk masters never registered).

**Fixes in flight:** collapse remaps + retarget-then-delete; visual index strip on rebuild; Apply scope already in 516.

### HITZ recovery after publish

1. Site update to latest build.
2. Status → Quick check (should be clean on file-hash) or Force delivery if D chips stay amber.
3. Hard-refresh Files → Visual — index should match ~100 registry rows after rebuild (Force/Treat or Status after this fix).
4. SFX: still need register-in-place for `sfx/master` (not a dedupe delete).
5. Playlist/player smoke: if track covers or audio 404, restore from backup/PCF — chain deletes may have left bad refs.

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here.
