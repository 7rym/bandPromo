# Session Handoff

## Resume point

**HITZ false-healthy hotfix — publish ASAP.**

Root cause: site_health `is_asset_id` used `{26}` instead of bandPromo `{20}` Crockford body, so every real master was ignored (`ast_* 0`) and Check reported healthy with registry 0 audio / 185 disk masters.

### HITZ ops (after publish)

1. Site update  
2. Check site health — must show critical uncatalogued audio  
3. Treat recommended  
4. Confirm Files → Audio  

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here.
