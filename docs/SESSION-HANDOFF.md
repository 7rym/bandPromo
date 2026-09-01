# Session Handoff

## Resume point

**Identity migrate + Base brand fallthrough** (local, not yet published).

### Shipped in tree

1. Player/campaign brand fallthrough → install **Base** (`bandpromo_brand_active_canonical_id`), not hard-coded `bandpromo-default`.
2. `player.js` shell inherit compares to `BANDPROMO_ACTIVE_BRAND_ID`.
3. Catalogue Associated shows **Suggested** playlists when tracks unanimously belong to the campaign; Content autofix step `playlist_campaign_ownership` stamps them.
4. Campaign duplicate playlist ids prefer source slug (not `Title copy`).
5. Editable **Storage id** on Branding + Playlist Base info with confirm + runtime migrate; unique titles.

### Operator recovery on HITZ (after Site update)

1. Open player — Retroscopy should use Base/campaign chrome, not bandPromo cyan, even before ownership stamp.
2. Content → Catalogue → the Retroscopy hour → Playlists: accept suggested association, **or** System → Repair catalogue (ownership autofix).
3. Optionally migrate legacy brand id `hitz-copy` / playlist `the-retroscopy-hour-copy` via Storage id fields.

### Active fleet

| Host | Persona |
|------|---------|
| bandpromo.site | Vanilla |
| hitz.no | HITZ |
| spandexualtension.com | Band / release sequence |

### v0.8 exit gate next

1. Publish this checkpoint for testers.
2. Player Campaign navigator — policy lock → ship → validate.
3. PCF round-trip smoke on active fleet.

Last published: **v0.8.37 build 440**. Next publish bumps build after session-end.
