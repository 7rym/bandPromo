# Session Handoff

## Resume point

**SFX missing on HITZ (and any host with disk masters + empty registry):** Site health now probes `media/sfx/master`, flags `uncatalogued_sfx_masters`, and Treat runs `sfx_register_in_place` + Files SFX index rebuild + delivery. Publish, then on HITZ: Site update → Quick check → Review → Apply.

### HITZ recovery checklist

1. Site update to latest build (SFX register-in-place + prior Full audio dedupe / remap / index fixes).
2. Status → Quick check — expect Sound effects finding if registry still 0 sfx with masters on disk.
3. Review → Apply (registers SFX; rebuilds Files → Sound effects).
4. Hard-refresh Files → Sound effects; login Welcome/Logged-in smoke.
5. Full check still available for dual-tag audio content clones if needed.

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here. Local already has 4 registered SFX; HITZ was the empty-registry case.
