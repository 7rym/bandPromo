# Changelog

All notable changes to this project will be documented in this file.

2026-08-17 16:30 - Checkpoint operator Campaign copy, Content/Files ⓘ help, Backup compact, and Visual/Brand toolbar polish on `main` as **v0.8.29 build 406**; publish GitHub Release `v0.8.29-build-406` for Site update.

2026-08-17 16:20 - Files → Visual campaign filter says All campaigns. Brand assets type chips match Visual (icons for Still, Living, Sound effects); brand filter says All brands; Add existing shows only when a Brand is selected.

2026-08-17 16:10 - Files ⓘ help for Audio, Visual, Sound effects, and Brand assets: uploads become internal masters (original content + tags); pools/pickers use masters; delivery is generated from masters.

2026-08-17 16:00 - Catalogue ⓘ help: campaign owns master media (audio, images, video); assign assets and containers; Base info vs other tabs. Dropped upload copy from this tab.

2026-08-17 15:55 - Content ⓘ help for Catalogue, Galleries, Pages, and Branding matches Playlists: three bullets, what the container is, how it shows in the player, and how to operate it.

2026-08-17 15:50 - Playlists ⓘ help is a three-item bullet list (listening products, reverse-order series, save/delivery).

2026-08-17 15:45 - Playlists ⓘ help: playlists replaced albums/singles/EPs and can be reverse-order series (podcasts, DJ mixsets, shows). Dropped the “create from Campaign hub” nudge.

2026-08-17 15:15 - Operator UI calls the Catalogue umbrella a **Campaign** (editor, add/delete, Files column, Status tiles, help). Playlist stays the listening product. Track **Release date** and storage `release_id` / PRP are unchanged.

2026-08-17 14:30 - System → Backup: PRP export/import sit above site backup in a compact two-column row; import collision is a Refuse | Overwrite | Skip | AsNew radio pool.

2026-08-17 13:40 - Checkpoint compact Admin Status/Files chrome, local Python metadata bootstrap, and Visual list density on `main` as **v0.8.29 build 405**; publish GitHub Release `v0.8.29-build-405` for Site update. Next: operator-facing Campaign label (no storage rename). Python 3.6 checker skips `scripts/vendor`.

2026-08-17 13:25 - Files toolbars: tighter padding/gaps on type chips, filters, search, and Upload/Download/Delete so Visual All|Images|Video and the rest of the row stay on one line.

2026-08-17 13:15 - Files → Visual: one ⓘ help paragraph (Catalogue vs In use vs after-upload); panel keeps only the permanent-edit warning. Images/Video type filters are icon chips. List rows put title, status pill, and actions on one line.

2026-08-17 13:00 - Admin audio/visual metadata tasks inherit the PHP process environment (`$_ENV` is empty on Windows `php -S`) and bootstrap `scripts/vendor` before importing mutagen/Pillow. First rebuild now still writes `scripts/vendor` even when packages already import from user site-packages.

2026-08-17 12:50 - System → Status (was Deliverables): catalog counts match Catalogue (hide invisible `primary`, skip login FAQ); stream-ready ring uses campaign tracks not warehouse files; drop Files-pool tiles that mixed photos/videos with campaigns. Repair catalog is developer-only. Rebuild copy is Refresh site files, not a scare-panel.

2026-08-17 12:28 - Place Admin Notifications on the primary tab row (right of Documentation) so it does not sit in a leftover header row.

2026-08-17 12:25 - Compact Admin chrome: identity, role, version, Open site, and Logout sit on one line under the title; Notifications pill right-aligns at the content top.

2026-08-17 12:05 - Fix local tail hang before auto-next: advance when catalog duration is reached but browser MP3 metadata still claims more audio; stall watchdog near end; preload next track in the last 45s; skip coverflow animation on auto-next (regression from 2026-07-16); throttle living-video loop seeks to the last quarter-second.

2026-08-17 12:00 - Fix living shell/cover stopping after one play: loop watchdog seeks to start before `ended` on streamed MP4; only restart shell video when paused/ended (playlist load no longer interrupts mid-play); soften shell error fallback; clear still layer when living background is active.

2026-08-17 11:55 - Stabilize living shell/cover playback: cancel stale background attach on brand change instead of racing empty `video.load()` error handlers; restart on `ended` so loop survives flaky range requests; skip the <5 Mbps living-media gate on localhost; do not tear down an unchanged living background after playlist load.

2026-08-17 11:50 - Fix playlist switch landing on default: navigate with `/play/?playlist={id}` so demo selection works without `play/.htaccess` rewrites (PHP dev server and hosts missing the runtime stub).

2026-08-17 11:45 - Fix demo (and other) release page tabs missing after playlist switch: coverflow/selector now navigates to `/play/{slug}` so server-rendered Bio/Gallery tabs match the active playlist; playlist API exposes effective `release_id` and backfills empty track ownership; player falls back to playlist release when syncing tab visibility.

2026-08-17 09:35 - Checkpoint fresh-install shell cleanup, player living-background playlist-switch fix, and v0.9 code-layout plan on `main` as **v0.8.28 build 402**; publish GitHub Release `v0.8.28-build-402` for Site update.

2026-08-17 09:30 - Fix player shell background sticking after playlist switch: clear stale living-background video source when brand has no background_video; treat appConfig.media as authoritative in shell-background.js.

2026-08-17 09:15 - Plan v0.9 candidate code layout refactor: consolidate under `/lib`, move operator UI to `/admin/` (mirror `/play/`), URL compatibility policy. Stored in docs/CODE-LAYOUT-REFACTOR.md; linked from ROADMAP and TODO. No implementation in v0.8.

2026-08-16 23:20 - Fresh-install shell cleanup: hide invisible `primary` from Catalogue; stop auto-creating “Your own brand” (Base stays locked bandPromo Default until Duplicate); retire Content → Player layout in favor of release Pages associations for player tabs; remove Analytics → Quality (optimal-only delivery). Docs synced.

2026-08-16 22:35 - Checkpoint Linux ffmpeg auto-install via ffbinaries GitHub on `main` as **v0.8.27 build 401**; trigger GitHub Release `v0.8.27-build-401` for Site update / setup rebuild on bandpromo.site.

2026-08-16 22:30 - Linux ffmpeg auto-install downloads `ffmpeg` + `ffprobe` from GitHub `ffbinaries/ffbinaries-prebuilt` (pinned v6.1) instead of `johnvansickle.com`, so shared hosts that block or reset that origin can still finish Publish / setup builds.

2026-08-16 22:20 - Checkpoint after Demo PRP republish (brand settings) and `.cursor/` hygiene on `main` as **v0.8.27 build 400**; trigger GitHub Release `v0.8.27-build-400` for Site update. Durable `demo-content` SHA256 `36582ba1…`.

2026-08-16 21:55 - Stop tracking `.cursor/` (local Cursor IDE rules); ignore it like `.vscode/` / `.editorconfig`. Policy stays in `docs/AGENTS.md`.

2026-08-16 21:40 - Checkpoint player chrome dim/blur + Cover reflection, shell huge backgrounds, gallery centering, and admin Visual delivery previews on `main` as **v0.8.27 build 399**; trigger GitHub Release `v0.8.27-build-399` for Site update.

2026-08-16 20:45 - Residual Visual still-preview paths hardened: shared `visualStillUrlFromRef` / asset-id delivery helpers; gallery pool no longer falls back to `/media/visual/original/${file.name}`; picker select + audio cover/living-cover previews prefer delivery `card`/stream over invented original/master URLs.

2026-08-16 20:15 - Admin Visual lightbox preview prefers delivery `huge`/`card` (and tile `data-public-url`) over stale `/media/visual/original/…` master paths; caller src wins when opening a matched preview item; lightbox falls back huge→card→thumb and original/master `ast_*` → card.

2026-08-16 19:50 - Branding Player chrome adds **Cover reflection** toggle (`player.cover_reflection`, default on) for the mirrored cover under the main artwork; Beggars banquet stays as its own support-CTA control; live preview shows the mirror when enabled.

2026-08-16 19:45 - Content dim/blur hugs readable measures (playlist tracks, lyrics markdown, page prose/gallery panels) instead of filling the whole content column; page gallery grids use centered flex wrap so incomplete rows sit horizontally centered.

2026-08-16 19:35 - Player transport panel wraps track info / controls / scrubber with brand dim+blur; Branding adds a Beggars banquet toggle (`player.beggars_banquet`) and live preview matches the updated chrome (transport panel + optional support CTA).

2026-08-16 19:25 - Login `.login-container` uses brand Backdrop dim fill + Panel blur (inputs keep a light accent wash so they do not double-scrim).

2026-08-16 19:15 - Shell still backgrounds prefer Visual `huge` delivery (1920×1080 contain) with card fallback — login/player no longer use the 720px `card` for full-bleed backdrops.

2026-08-16 19:05 - Content panels (lyrics, playlists, pages, gallery, login inputs/lightbox) apply brand Backdrop dim fill together with Panel blur, matching Branding live preview; nested playlist rows stay lightly tinted so they do not double-scrim.

2026-08-16 18:35 - SFX uploads auto-join the active brand library (so Files → Sound effects lists them under All files / that brand); untitled SFX show a cleaned original filename instead of only "Unused sound effect".

2026-08-16 18:20 - Checkpoint PRP post-import deliverables + gallery heal, page-editor UX, gallery grid centering, and player huge lightbox on `main` as **v0.8.27 build 398**; trigger GitHub Release `v0.8.27-build-398` for Site update.

2026-08-16 18:05 - Player lightbox uses more viewport (≈96vw / 94vh) and prefers Visual `huge` delivery (falls back to card) for gallery, page, and cover views.

2026-08-16 17:55 - Page editor meta fields stack in a single column (no two-up wrap) and no longer stretch to fill blank vertical space.

2026-08-16 17:50 - Page gallery grid preset centers items horizontally (incomplete rows no longer stretch edge-to-edge).

2026-08-16 17:40 - Page editor: rich text and description fields grow with content (no nested height clamp); Picture blocks reuse the shared Files media picker (same modal as Share image / covers) instead of a separate content-image grid.

2026-08-16 17:05 - After PRP import: sync image-only delivery, server-queue deliverables-only rebuild, expand that profile with playlist + visual catchup; gallery resolve uses delivery-path `asset_id` and master fallback so Bio/admin thumbs work before cards exist; admin treats build start as success only when `ok === true`.

2026-08-16 16:50 - Checkpoint release purge-delete and remote-safe PRP chunked import on `main` as **v0.8.26 build 397**; trigger GitHub Release `v0.8.26-build-397` for Site update.

2026-08-16 16:45 - PRP chunked import (remote-safe): prefer install `data/upload_tmp`, assemble only on the final chunk, require matching `file_size`, and reject non-ZIP headers before ZipArchive open — corrupt assembly was producing bare "Could not open release package ZIP" on hosted imports.

2026-08-16 16:40 - PRP chunked import: stage parts in system temp (not Google Drive `data/upload_tmp`), assemble only on the final chunk, verify `file_size`, and surface ZipArchive status/size when open fails.

2026-08-16 16:30 - Catalogue delete release: operators choose Entire campaign (owned brand/playlists/galleries/pages + unreferenced media) or Release only (container; Files media stays). Shared duplicate media is retained on purge.

2026-08-16 16:20 - Checkpoint PRP gallery asset packing, page registry on import, and post-import deliverables rebuild on `main` as **v0.8.26 build 396**; trigger GitHub Release `v0.8.26-build-396` for Site update.

2026-08-16 15:30 - PRP export/import: resolve gallery `asset_id` from delivery `src` so band-member masters pack; register campaign pages on import; mark + auto-queue deliverables-only rebuild after import (thumbs/streams were masters-only by design).

2026-08-16 15:15 - Checkpoint chunked PRP import (2 MB parts, same as Files) on `main` as **v0.8.26 build 395**; trigger GitHub Release `v0.8.26-build-395` for Site update.

2026-08-16 15:10 - Admin PRP import uses 2 MB chunked upload (same as Files): assemble in `data/upload_tmp`, then stream-extract — avoids nginx HTTP 413 on large Spandexual-sized packages.

2026-08-16 15:05 - Checkpoint PRP import stream-extract + clearer Import errors on `main` as **v0.8.26 build 394**; trigger GitHub Release `v0.8.26-build-394` for Site update.

2026-08-16 14:50 - PRP import no longer loads each ZIP entry into PHP memory (`extractTo` instead of `getFromIndex`); admin Import shows HTTP status / body snippet when the host returns a non-JSON failure; fatal OOM during import returns JSON instead of a bare "Import failed".

2026-08-16 14:15 - Checkpoint Site-update Demo PRP refresh, living-cover publish heal, and Visual intake retirement (unified `media/visual/original/` + gated Publish/Site-update relocate) on `main` as **v0.8.25 build 393**; trigger GitHub Release `v0.8.25-build-393` for Site update.

2026-08-16 14:10 - Publish and Site update run a gated one-shot Visual intake relocate when any legacy `media/img|photo|video|special` folder still exists: move registered originals into `media/visual/original/`, delete leftover copies, and remove empty legacy folders.

2026-08-16 13:55 - Retire legacy Visual intake writes: image/video uploads (including photos) go only to `media/visual/original/`; relocate moves leftover `img`/`photo`/`video`/`special` copies into that tree and deletes them; setup stops creating those folders; Files/admin paths and deletes use the unified tree (dual-read leftovers only).

2026-08-16 13:40 - Living cover player gap: Rock Out had a legacy filename in the master tag and an empty registry link, so the published playlist shipped no `animated_cover` URL even after the Visual stream existed; republish resolves filename→`ast_*`+stream and syncs registry when they diverge.

2026-08-16 13:05 - Site update post-tasks refresh the platform Demo PRP when the published `demo-content` SHA differs from `data/demo-release-package.json` (overwrite locked demo; skip unlocked localhost authoring; soft-fail so app apply still succeeds).

2026-08-16 12:55 - Checkpoint track-cover naming, safer Visual delete, visual-delivery catch-up, and Publish sfx-delivery on `main` as **v0.8.25 build 392**; trigger GitHub Release `v0.8.25-build-392` for Site update.

2026-08-16 12:50 - Publish Rebuild now includes an SFX delivery stage (`buildSfxDelivery.py` / `bandpromo_sfx_backfill_tiers`) after audio optimize: heals missing `media/sfx/optimal/{ast_*}.mp3`, skips fresh deliveries, and counts toward the media summary.

2026-08-16 12:40 - Fix blank track covers after delete+rebuild: playlist extract builds Visual delivery immediately, and full Publish adds a visual-delivery catch-up stage after playlists (optimizeMedia ran before extract, so new embedded covers had masters but no thumb/card).

2026-08-16 12:25 - Visual delete no longer strips embedded cover art from audio masters by default: site/registry cover links still detach, and a delete-modal checkbox opts into clearing embedded art from linked masters.

2026-08-16 12:05 - Embedded track-cover extract one-time seeds empty Visual keywords (`Track cover`, artist) and `captured_at` from the audio date, alongside `Track cover: {title}`. Hash-match reuse and later builds never overwrite those fields.

2026-08-16 11:55 - Track covers extracted/linked from audio no longer all list as bare "Track cover": build fills empty Visual `display.title` as `Track cover: {track title}` (catalog/tags); Files listing synthesizes the same form from the linked track when title is still empty. Operator-edited titles are never overwritten.

2026-08-16 11:45 - Purge `docs/SETUP-SMOKE-SUITE.md` from `main` history and republish `v0.8.25-build-391` without it. Operator-private Vanilla smoke stays outside the repo.

2026-08-15 22:05 - Setup/Publish social-assets: empty `social.share_image` no longer resolves to the site root (IsADirectoryError crash); missing poster/share source warns and continues so first-run setup can Finish, then operators fix Branding → Shell media and rebuild.

2026-08-15 09:55 - Fix Spandexual/PRP track covers: playlist build no longer treats audio `ast_*` (or missing) refs in `display.cover` as assigned Visual covers, so embedded FLAC art can extract and link; resolve `ast_*.ext` cover refs to real Visual ids; persist/re-resolve `cover_url` for the player. Admin Open site ignores `install.site.url` when it is still `example.com` and prefers `site.url` / localhost.

2026-08-15 09:45 - Track platform favicon seed at `biblioteca/templates/icons/bP-icons.zip` (not under `/media`). Build, setup, and release packaging expand it into gitignored `media/icons/`; CI no longer seeds icons from the previous app ZIP.

2026-08-14 21:20 - Local PRP import hang: PHP built-in server used 2M/8M upload caps (Spandexual `.prp` ~378MB → “Failed to fetch”); raise dev-server + runtime `user.ini` ceilings, always dispatch backup/PRP jobs without requiring `fastcgi_finish_request`, surface size-limit errors, and register imported playlist/brand/gallery files so Catalogue can see them.

2026-08-14 21:08 - Hard rule: never wipe this working copy’s `data/` / `media/` / `log/` (analytics/audit test data) / `backups/` unless the operator names those paths in the same message (same bar as entering a password). Fresh installs always run on **bandpromo.site**; the other remote tests are Twisted Chronicles and HITZ. Docs / “fresh install” are not permission to wipe localhost.

2026-08-14 20:52 - Ship SFX masters-only delivery on `main` as **v0.8.24 build 390** so Demo PRP fresh installs get login `media/sfx/optimal/{ast_*}.mp3`.

2026-08-14 20:50 - Fresh-install: SFX delivery encodes from the imported master when `media/sfx/original/` is absent (PRP is masters-only); PRP import backfills `media/sfx/optimal/{ast_*}.mp3`.

2026-08-14 20:45 - Checkpoint Files → Visual usage-by-`ast_*`, Brand library, Branding delivery picks, and Files download reliability on `main` as **v0.8.24 build 389**; trigger GitHub Release `v0.8.24-build-389` and refresh durable `demo-content` PRP.

2026-08-14 20:40 - Docs: Visual In use / Catalogue identity is the registry `ast_*` id (ADMIN-UI, FEATURES, MEDIA-HANDLING). Ready to checkpoint and refresh Demo PRP.

2026-08-14 18:50 - Files → Visual usage (In use / Catalogue) matches by Visual `ast_*` id after resolving stored refs. Titles, operator titles, and filename stems are never identity; unregistered leftovers with no id do not match a registered asset.

2026-08-14 18:40 - Files → Visual In use counts Bio/page picture blocks that store Visual `asset_id` and `/media/visual/delivery/…` URLs, not only legacy `/media/img/` paths.

2026-08-14 18:15 - Files → Visual: Brand-library members are not Catalogue orphans (they list the Brand). Generated OG/1080 crops (`*_facebook.jpg`, `*_twitter.jpg`, `*_bg1080.*`) stay out of the Visual pool; makeSocial writes them to `media/share/` instead of beside originals.

2026-08-14 18:05 - Files → Visual Catalogue lists each matching campaign on its own line instead of joining titles with a middle dot.

2026-08-14 17:55 - Files → Visual Catalogue now includes Brand visual shell slots those campaigns play (logo, poster, still/living), including Base-brand fallback for empty slots. Site-wide demo backgrounds and logos list every inheriting release instead of Orphan. Brand library membership and Brand ownership on the asset still do not define Catalogue.

2026-08-14 17:45 - Pressing Play no longer kicks listeners back to login: activity logging (`log.php`) uses the same Windows session store as login/player, and the session watchdog ignores analytics 401s so a log miss cannot expire the player.

2026-08-14 17:30 - Branding live preview plays the living background as soon as it is picked: injected `<video>` elements are started explicitly, the still is used as the video poster instead of covering the stream, and living picks fall back to `/media/visual/delivery/{id}/standard-stream.mp4` when list payloads omit `stream_url`.

2026-08-14 17:20 - Branding living background picks store the Visual delivery stream, not a still poster, so the slot actually assigns. Live preview plays that stream (no reduced-motion hide in the editor) instead of staying on the still fallback.

2026-08-14 14:55 - Files → Visual In use / Unused follows live assignments: track covers match Visual `ast_*` ids (not just filenames), and brand shell slots (poster, still/living backgrounds, logo, SFX) count as used. Catalogue stays campaign-only; Brand library membership alone is not used.

2026-08-14 14:45 - Branding poster/shell picker stores Visual delivery URLs (`card` / stream / SFX optimal), not `/media/special/` or original paths, so slot thumbs and live preview no longer break after a pick. Brand documents resolve those delivery URLs on load when `asset_ids` are set.

2026-08-14 14:40 - Visual replace-upload refreshes the working original/master when the same filename is uploaded again (newer intake overwrites a stale `visual/original`). Files → Visual image uploads land in `media/visual/original/` instead of legacy `media/img/original/`.

2026-08-14 14:25 - Brand assets Add existing hides files already in the selected library instead of dimming them. Picker tiles use Visual `media_type` plus delivery poster/thumb, so MKV video masters show a preview instead of a document icon.

2026-08-14 14:20 - Files list thumbnail size control is S / M / L (70 / 100 / 125 px; default M).

2026-08-14 14:15 - Files → Visual / Brand assets list view: thumbnail size toggle (70 or 100 px, default 100) next to Grid/List. Delivery `thumb` is already 100px max edge; list was previously ~44px.

2026-08-14 13:55 - Files → Visual Catalogue names the campaign that uses the file (gallery, cover, poster, press photo, or page), not the Brand on the asset. Shared files can list more than one release; Brand logos with no campaign use show as Orphan.

2026-08-14 13:40 - Files → Brand assets: Warehouse column shows Visual or Sound effects (library membership is the brand filter, so members are never Orphan). Audio listen control sits in the Dimensions cell instead of overlapping Size.

2026-08-14 13:25 - Files → Brand assets Add existing is multi-select: click tiles to choose several Visual/SFX assets, then Add selected. Members already in the selected Brand library are dimmed and unselectable (delivery-not-ready tiles stay dimmed for that reason).

2026-08-14 13:20 - Files → Visual and Brand assets Dimensions column (and the asset-details Dimensions row) now show the master file pixel size from registry `master_width`/`master_height`, not a delivery variant. Upload heal, image/video delivery builds, and visual metadata heal stamp those fields from the on-disk master.

2026-08-14 13:15 - Files → Visual and Brand assets list mode add a sortable Dimensions column (largest delivery variant, typically `huge` / stream). Sound-effect rows show an em dash; the dedicated Sound effects panel stays Title / Brand / Size.

2026-08-14 13:10 - Files asset details modal now previews Brand library stills, living video, and sound effects from Visual/SFX delivery URLs instead of missing `/media/special/` originals; Brand-asset modal Delete is membership Remove (hidden when the asset is assigned or no Brand is selected).

2026-08-14 11:25 - Visual delivery adds the `huge` class for future fullscreen views: every still Visual build now emits a ratio-preserving derivative contained within 1920×1080px, without cropping, stretching, or upscaling; registry manifests and freshness checks support non-square dimension policies.

2026-08-14 11:12 - Brand library bulk removal now continues past assigned shell assets: removable selections are removed, protected rows remain selected with an Assigned role badge, amber marker, and lock action, and one summary toast reports both outcomes.

2026-08-14 11:05 - Fix Brand library removal persistence: legacy `brand_id` migration now seeds `library_asset_ids` only when the field is absent, so subsequent requests no longer re-add explicitly removed assets.

2026-08-14 10:47 - Brand assets list actions now use membership semantics: row and bulk controls say Remove instead of Delete, permanent deletion stays in global Visual/Sound effects, and the narrower action column fixes cramped list rows.

2026-08-14 10:30 - Brand assets is now an explicit cross-media per-Brand library (`library_asset_ids`) spanning Visual and Sound effects: upload/add/remove management, strict Branding slot pickers, hard deletion guards, shared-ID duplication, and complete PRP portability.

2026-08-14 10:00 - Files download: track one-time stream status server-side and show a persistent “Download completed” toast (including the saved filename) only after all bytes were sent.

2026-08-14 09:55 - Audio master downloads use the current saved metadata for filenames (`Artist - Title [Version].ext`) instead of the original upload filename; streamed bytes remain the master.

2026-08-14 09:50 - Files download: stream large media in 1 MB chunks with the request timeout disabled; restart local PHP with the configured 300-second dev limit so hour-long masters do not fail before the browser starts saving.

2026-08-14 09:45 - Files download: follow one-time `download_url` in a hidden iframe so the admin tab is not replaced by a blank download page.
2026-08-14 09:40 - Files download: preflight issues a one-time GET token; the browser follows `download_url` via a hidden link so large masters (~140MB) actually save (iframe POST was silently ignored).
2026-08-14 09:35 - Admin toasts stay until manually dismissed (× on every toast; no auto-hide timer).
2026-08-14 09:30 - Files download: large audio masters (e.g. hour-long Retroscopy episodes) no longer use fetch+blob; preflight then streams via a hidden iframe so ~140MB files save reliably. Local dev server allows longer PHP execution for big streams.
2026-08-14 09:25 - Files download: replace post-preflight form submit with fetch+blob save so browsers actually start the download after async auth check; master downloads use the original upload filename when known.
2026-08-14 09:20 - Fix Files → Audio download: `download-media.php` now uses configured session storage (`bandpromo_ensure_session_started`) so bulk/row downloads authenticate like other admin APIs.

2026-08-13 23:30 - Session end: handoff points next work at Demo PRP refresh (`demo-content` publish) plus a full fresh-install smoke on build 388+. Master-tier and publish-summary slices stay closed.

2026-08-13 23:25 - Checkpoint publish-log + build-script cleanup on `main` as **v0.8.23 build 388** and trigger GitHub Release `v0.8.23-build-388` for Site update. Docs aligned for master-tier-complete + scoped publish summary.

2026-08-13 23:20 - Build scripts cleanup: remove dead legacy helpers from `optimizeMedia` (special-resize / stem cover dual-write / unused tag readers), `optimizeVideo` (video/optimal dual-write helpers), and `makePlaylists` (unused aliases); drop unused `cover_filename` arg from audio delivery.

2026-08-13 23:15 - Publish success summary splits counts by kind: media files, player playlists, share images, and site manifest (no longer lumped as “media”).

2026-08-13 23:10 - Publish stats: “New deliverables” no longer counts always-rewritten artifacts. Playlist payloads, social share images, and site.webmanifest only count as created when content actually changes.

2026-08-13 23:05 - Publish success banner: end every successful rebuild with a clear “your site is ready” payoff (time, stage result, scoped counts), matching the stage header weight.

2026-08-13 23:00 - Publish log summary: show elapsed time plus handled / created / up-to-date counts (stages emit BUILD_STATS; path dump removed).

2026-08-13 22:50 - Checkpoint master-tier T7 (plan complete) on `main` as **v0.8.21 build 387** and trigger GitHub Release `v0.8.21-build-387` for Site update. Handoff: master-tier idle; resume from TODO/ROADMAP.

2026-08-13 22:45 - Master-tier T7 verify complete. Player enrich accepts Visual covers only (`/media/visual/delivery/…`); content autofix rewrites/clears invalid audio `display.cover` and optional playlist payload covers (does not strip playlist `entries`). Local verify: Files index masters, brand slots → delivery/SFX optimal, cover extract → `visual/original`, PRP masters-only paths confirmed. Plan: [MASTER-TIER-AUDIT.md](MASTER-TIER-AUDIT.md) — T0–T7 done.

2026-08-13 22:30 - Checkpoint master-tier T6 on `main` as **v0.8.19 build 386** and trigger GitHub Release `v0.8.19-build-386` for Site update. Handoff resume point is now T7.

2026-08-13 22:35 - Master-tier T6: fail loud and delete shims. Dropped stem `video/photo/optimal` dual-read helpers; pool-ready and video needs-delivery use Visual delivery only. Welcome/demo presence is Demo PRP marker / demo release only (no `bandPromo_*.flac` original probes). `initialSiteSeed` gallery seed uses Visual registry + delivery/`asset_id`. Removed dead original-path shims. Plan: [MASTER-TIER-AUDIT.md](MASTER-TIER-AUDIT.md).

2026-08-13 22:25 - Checkpoint master-tier T5 on `main` as **v0.8.18 build 385** and trigger GitHub Release `v0.8.18-build-385` for Site update. Handoff resume point is now T6.

2026-08-13 22:25 - Master-tier T5: preferred master formats. SFX WAV originals materialize to FLAC masters (parity with catalog audio); visual ensure-tiers heals empty display from EXIF/XMP or Matroska tags; `visualMasterMetadata.py` is Python 3.6.9-safe. Video MKV remux + living-cover `standard-stream` ready path confirmed. Plan: [MASTER-TIER-AUDIT.md](MASTER-TIER-AUDIT.md).

2026-08-13 22:15 - Checkpoint master-tier T4 on `main` as **v0.8.17 build 384** and trigger GitHub Release `v0.8.17-build-384` for Site update. Handoff resume point is now T5.

2026-08-13 22:00 - Master-tier T4: Brand assets fold into Visual/SFX. Brand uploads and clones write Visual original + `ast_*` masters (not `media/special/{brand}_{slot}`); shell slots resolve `asset_ids` → delivery; setup creates `media/visual/{original,master,delivery}` and `media/sfx/{original,master,optimal}` (no product img/photo optimal/thumb). PRP SFX packs masters only (refuse row if missing). Login/OG/player drop hardcoded `/media/special/bandPromo_*` fallbacks. Plan: [MASTER-TIER-AUDIT.md](MASTER-TIER-AUDIT.md).

2026-08-13 21:50 - Checkpoint master-tier T3 on `main` as **v0.8.16 build 383** and trigger GitHub Release `v0.8.16-build-383` for Site update. Handoff resume point is now T4.

2026-08-13 21:45 - Master-tier T3: deliverables from masters only. Dropped `process_track_cover` stem `img/optimal|thumb` dual-write and in-place `/media/special` resize; video delivery writes `media/visual/delivery/{ast_*}/` only. `bandpromo_visual_resolve_url` is delivery-only (admin may use master preview, never original as `<img src>`). Gallery/page/player/playlist cover paths no longer invent stem `img|photo|video` URLs; `MEDIA_IMG_BASE` removed from `/play`. Plan: [MASTER-TIER-AUDIT.md](MASTER-TIER-AUDIT.md).

2026-08-13 21:30 - Checkpoint master-tier T2 on `main` as **v0.8.16 build 382** and trigger GitHub Release `v0.8.16-build-382` for Site update. Handoff resume point is now T3.

2026-08-13 20:30 - Master-tier T2: working copy is the master. Files index lists by `master_filename` (original is a label); notifications gate on master existence. Public audio play is delivery-only; admin listen uses `variant=master` (original is download-only). Demo original-FLAC playback fallback removed. Publish/Python resolvers and audio collection are master-or-fail; video delivery queue reads Visual video masters; SFX public URL is optimal MP3 only; Download original 404s when missing; delete removes original+master+delivery by asset. Plan: [MASTER-TIER-AUDIT.md](MASTER-TIER-AUDIT.md).

2026-08-13 16:10 - Session pause pointer: [SESSION-HANDOFF.md](SESSION-HANDOFF.md) so the next session resumes at master-tier T2 (build 381 on `main`, not shipped). Session start prints the file when present.

2026-08-13 16:05 - Checkpoint T1 identity on `main` without a GitHub Release / Site update package: track covers and living covers persist as visual `ast_*` ids (registry, master tags, playlist payload). Publish extract writes `media/visual/original/embedded-{hash}.*` and registers a Visual master instead of `img/original/{audioStem}.*`; configured-cover minting is gone. Admin living-cover picker stores the asset id; player `cover`/`living_cover` are ids with delivery URLs in `cover_url`/`animated_cover`. Policy and plan: [MASTER-TIER-AUDIT.md](MASTER-TIER-AUDIT.md).

2026-08-13 14:35 - Master-tier audit: [MASTER-TIER-AUDIT.md](MASTER-TIER-AUDIT.md) inventories original/stem/sidecar working copies after the Demo PRP smoke tests and locks a T1–T7 fix plan (identity `ast_*`, masters as working copies, delivery from masters, Brand/special fold, MKV/IPTC). Living-cover tag policy is now visual asset id.

2026-08-13 13:45 - Smoke-test fixes: Files → Audio lists masters-only PRP tracks (index rebuild from registry + `media/audio/master`); `/play` resolves `ast_*` track covers via visual delivery/master (`cover_url` + player fallback) instead of legacy `/media/img/optimal`. PRP collect resolves `ast_*.ext` cover refs; Publish cover lookup checks visual master/delivery and extracts when assigned art is missing.

2026-08-13 12:55 - Welcome/demo catalog: treat Demo PRP (not legacy `media/audio/original` FLACs) as the installed starter catalog; FAQ stays advice. Operator-content / Hide demo requires an operator-created release with a track plus a playlist that exposes it; if that catalog is later deleted, the demo is shown again.

2026-08-13 11:20 - Setup helper copy: PRP expands to Portable Release Package (not “portable demo campaign”).

2026-08-13 10:30 - Setup layout seed: after Demo PRP import, do not fail when `media/audio/original` is empty — treat already-populated playlist/gallery containers as success, and fall back to playlist masters / `media/audio/master` when seeding from disk.

2026-08-13 00:15 - Lean GitHub releases: app packages ship only `bandPromo.zip` + `release-manifest.json`; Demo PRP moves to durable `demo-content` tag (`prepare_demo_content_package.py --publish`); drop operator default-theme ZIP; setup falls back to demo-content manifest; icons seed from app package; release titles stay `bandPromo vX.Y.Z build N`.

2026-08-13 00:20 - Publish CI: expand `bP-icons.zip` after seeding icons from the previous app package so required favicons exist before packaging.
2026-08-12 23:40 - Bootstrap GitHub downloads: restore proven classic cURL first (the HTTP/1.1+IPv4-only path could empty-reply on hosts that already downloaded Demo PRP); try alternate profiles + manual redirect hops; remove upload-ZIP workaround; keep multi-URL manifest candidates.

2026-08-12 22:05 - Bootstrap: when GitHub release CDN returns empty reply, allow Install from uploaded application ZIP; show outbound probe notes (Atom vs manifest) for hosting support.

2026-08-12 21:15 - Bootstrap/release downloads: force HTTP/1.1 + IPv4, retry empty GitHub/CDN replies, Atom fallback for newest tag resolve, clearer hosting hint for `release-assets.githubusercontent.com` blocks (“Empty reply from server”).

2026-08-12 18:15 - Setup first build: show Demo PRP download progress while build.php runs; accept masters-only PRP audio in preflight/playlist/optimize (no media/audio/original required); seed install icons from bP-icons.zip or default-theme; ship icons inside the application package.

2026-08-12 17:55 - Bootstrap/release manifest load: strip UTF-8 BOM before `json_decode` (Windows rewrites can poison published `release-manifest.json`). Re-published v0.8.15-build-375 manifest without BOM after PRP attach.

2026-08-12 17:40 - Checkpoint v0.8.15: Demo PRP on GitHub Releases (workflow uploads .prp); gallery In-use via visual delivery refs; playlist empty release_id -> brand inference; brand-owned player chrome; theme live preview polish.

2026-08-12 17:10 - Files → Visual In use: gallery reference matching resolves `/media/visual/delivery/{asset_id}/…` to original filenames via the asset registry; stop lowercasing Crockford asset ids in path lookup (which made demo-gallery visuals look Unused); recover empty gallery `asset_id` from delivery paths when materializing.

2026-08-12 16:20 - Player brand: when a playlist document has empty `release_id` but all track entries share one release, infer that ownership for effective brand (and fill it on normalize) so release `brand_id` reaches `/play` instead of falling back to the install Base brand.

2026-08-12 16:05 - Theme live preview: no shell scrim over player chrome / logo — dim + panel blur apply only to content sections below (playlist selector stays clear).

2026-08-12 15:55 - Playlist selector buttons: center-align in Brand live preview and `/play` container.

2026-08-12 15:50 - Playlist selector: drop visible “Playlists” label in `/play` and Brand live preview (dropdown/buttons); keep aria-label for accessibility.

2026-08-12 15:45 - Theme live preview: show Playlist selector mock (dropdown / buttons / cover flow) above typography, driven by brand `player.playlist_selector`; remove padding on `.theme-preview-shell-header`.

2026-08-12 14:10 - Brand-owned player chrome: drop Content → Player Still|Living and playlist-selector toggles; shell prefers living video when assigned (reduced-motion/slow-connection stay still); playlist selector lives on brand `player.playlist_selector` (Base brand drives `/play`); Brand editor radios + theme preview living backdrop; migrate from legacy web-config keys on brand normalize.

2026-08-12 11:55 - Theme preview architecture cleanup: centralize preview rendering in `theme-preview.js` only; remove duplicate preview markup/style fallback logic from `theme-editor.js`; remove stale preview hint node and canvas wrapper selectors so editor/release preview controllers only pass data/state to the shared renderer.
2026-08-12 12:00 - Theme preview housekeeping: remove unused `.theme-preview-section-title` and `.theme-preview-section-lead` CSS after the header/lead text removal in preview sections.
2026-08-12 13:30 - Theme live preview: add mobile-style media player chrome (cover/poster, track info, prev/play/next, scrubber) above the logo to match `/play` stacked layout; drop the old side-by-side player sample; paint shell scrim from backdrop dim.


2026-08-12 10:35 - Visual listing fallback: `operator_title` is role label only (e.g. `Track cover`) when `display.title` is empty — no brand/release suffix.

2026-08-11 23:30 - Audio upload: after embedded cover extract, register Visual `ast_*` track-covers and run image-delivery so playlist/release cover pickers can use them (no Repair catalog required). Healed existing extracted covers on this install.

2026-08-11 23:10 - Demo policy hardening: stop per-file soft-hide on delete (release-level hide only); clear install soft-hidden Brand assets; media pickers match Files (no include_hidden via demo toggle); Settings/Welcome copy drops `bandPromo_*` filename hide wording; new Release create no longer falls back to demo listen URL / active brand preview, forces catalogue refresh, and keeps Catalogue tab URL on the new id.

2026-08-11 17:25 - Brand shell media pickers: still/poster/living use Brand assets only (no Visual tab); picker tiles sort A–Z by display title; slot accept filters stills vs living video; hint points at Brand assets + Sound effects.

2026-08-11 17:05 - Sound effects: pool ▶ listen preview (same dock as Audio) via `play_url` / optimal MP3; Files modal can save SFX display fields; Brand shell audio slots keep `asset_id` on pick/save, show Listen ▶, and toast when Save is blocked by lock.

2026-08-11 16:52 - Files asset edit modal: show Alpha (Yes/No) after Dimensions for images.

2026-08-11 16:29 - Files pools: Visual / Brand assets / Sound effects list headers are sortable (Title, Catalogue|Brand, Size) like Audio; asset edit modal shows Dimensions next to Size (from delivery metadata, refined from original/preview media when loaded).

2026-08-11 15:51 - Visual delivery freshness: also compare variant longest-edge to `delivery-contexts.json` max_edge so thumb 100→150 (and similar policy bumps) rebuild without requiring a master change; `BANDPROMO_FORCE_VISUAL_DELIVERY=1` still forces a full rebuild.

2026-08-11 14:17 - Demo policy is release-level: `demo_release_id` / `demo_release_hidden` in `data/install-preferences.json` (legacy `demo_catalog_visible` kept as inverse); hide campaign-owned media only (not Brand assets/SFX); refuse hide with `hide_blockers` when non-demo containers still reference demo assets; deny delete of locked demo campaign media; persist id after ensure-demo / Admin bootstrap. Docs: PLATFORM-MODEL, MEDIA-HANDLING, PORTABILITY, AGENTS.

2026-08-11 11:50 - Thumbnails: increase `thumb` delivery variant from 100px to 150px to remove blur in Files pool/picker thumbnails.

2026-08-11 09:55 - Brand assets upload: operator uploads of `bandPromo_*` names are no longer forever-bundled — clear hide-on-upload, stamp `user-upload` origin, include filename in pool search, and toast when a same-name file is replaced (not a second pool entry).

2026-08-10 23:20 - Brand shell media: stop auto-hiding `bandPromo_*` Files → Brand assets / Sound effects when operator uploads exist or Hide demo catalog is on — shell files stay visible while brands still use them (explicit per-file hide unchanged).

2026-08-10 23:15 - Files pools: wire Brand assets / Sound effects checkbox selection into bulk Download/Delete (listener was Visual-only), and enable those toolbar actions for one or more selected files.

2026-08-10 23:05 - Files → Brand assets: reuse the Visual display editor in the shared pool modal (title/description/keywords/date) instead of opening details read-only.

2026-08-10 22:55 - Brands: bandPromo Default matches demo release lock policy — locked after PRP / on remote hosts; localhost may open Edit and save for PRP source without unlocking; stop force-locking in normalize; API exposes `can_edit` / `platform_default`.

2026-08-10 22:50 - Gallery editor Available pool: sort ready and pending media rows by display name.

2026-08-10 22:40 - Collapse demo special-cases onto PRP + locked: remove demo track/playlist sync and template seed fallback; `system_managed` no longer freezes edits (normal `locked` + localhost-only unlock for platform demo); stop `bandPromo_*`→demo release inference; demo ensure/create is PRP-only with post-import lock.

2026-08-10 19:40 - Demo release lock stickiness: sync no longer force-locks `bandpromo-demo` on CLI/Publish (empty HTTP_HOST) or localhost; only remote HTTP requests re-lock. Localhost unlocks for PRP edits survive builds.

2026-08-08 09:40 - Files → Visual / Brand assets: match Audio toolbar density, All/None select chips, list column headers (Title / Catalogue|Brand / Size), grid captions, and ✎ open action; keep Grid/List toggle. Sound effects list headers aligned to the same pattern.

2026-08-08 09:15 - Lock IG/TikTok native-post dimensions as deferred (API publish, v2+): IG feed 1080×1350, Stories/Reels & TikTok 1080×1920; do not extend makeSocial beyond OG 1200×630. Docs + delivery-contexts.json.

2026-08-08 09:10 - makeSocial: resolve brand poster from visual master/original first (delivery card only as last resort) so OG Facebook/Twitter crops are not upscaled from the 720px card.

2026-08-08 01:00 - Collapse demo special-cases onto release ownership: stop `bandPromo_*`→demo `release_id` inference (registry scan, listing meta, media id helpers); stop normalize/migrate force-ownership for playlist/brand/gallery/Bio; gut demo gallery heal parallel seed; sync demo tracks only from assets already owned by the demo release (no title overwrite). Keep setup PRP import, lock/localhost unlock, hide, duplicate, FAQ shell exclusion.

2026-08-08 00:55 - Policy: no special-case demo content handling after release ownership — demo is setup PRP import + lock / localhost unlock + export, hide, duplicate only. Docs (PLATFORM-MODEL, PORTABILITY, AGENTS, TODO); remove normalize/migrate force-`release_id` for Bio/demo gallery and demo title heal; associations follow normal locked-release rules (FAQ still excluded as install shell).

2026-08-08 00:40 - Release editor Pages: Available pool was wrong — FAQ (login shell) listed as assignable, Gallery registered as truncated `galle`, and Bio `release_id` cleared then forced back on save. Exclude FAQ/login/required pages from associations, heal `galle`→`gallery`, allow empty Bio ownership on localhost, and scope Bio/Gallery pages to the demo campaign like the protected gallery.

2026-08-08 00:35 - Release editor Galleries: keep the protected demo gallery out of Available for other releases (localhost may still reassign it only on the demo campaign); restore drifted demo gallery title to `bandPromo demo`.

2026-08-08 00:30 - Localhost demo PRP edits: clear gallery/page associations on bandPromo demo (runtime), unlock the demo release, and stop migrate/normalize/save from re-locking those associations on local hosts (remote installs stay frozen).

2026-08-08 00:25 - Files → Audio: fix filter toolbar clickability — row `.media-file-actions { width:100% }` no longer covers catalogue/search controls.

2026-08-08 00:20 - Files → Audio quick-edit: Release date chip uses the shared ISO date picker (📅).

2026-08-08 00:15 - Files → Audio quick-edit: drop Release/Track chips; empty optional chips show amber label only; Description/Lyrics/Cover open the full track editor.

2026-08-08 00:05 - Files → Audio: All/None select pill uses checked/unchecked box glyphs (☑ / ☐).

2026-08-08 00:00 - Files → Audio: replace filter-bar select-all checkbox with All/None pill in the list header (left of Track).

2026-08-07 23:55 - Files → Audio: right-align/compress Date+Release columns; fix filter bar so select-all is not full-width (single compact row).

2026-08-07 23:50 - Files → Audio: restyle filter toolbar (compact, separate from grid) and lock Date/Release/Size headers to the same column tracks as row cells.

2026-08-07 23:45 - Files → Audio: Date and Release as separate columns with clickable Track/Date/Release/Size sort headers (default Date ↓).

2026-08-07 23:35 - Fix local Windows session split: `bandpromo_configure_session_storage()` now runs from `bandpromo_enforce_https()` so login/player and `session-check.php` share `%LOCALAPPDATA%/bandPromo/php-sessions` (avoids immediate `session_expired=1` after player load).

2026-08-07 23:30 - Files → Audio: Listen (▶) uses green constructive intent (`.media-action-good`), same as edit/download.

2026-08-07 23:25 - Files → Audio pool: show each track’s publish date (`display_date` from master tags) instead of the catalogue release date; sort prefers the same.

2026-08-07 23:05 - Track editor: save on close (Done / ✕ / backdrop) instead of blur autosave; Abort discards without writing; status shows Close to save / Unsaved changes.

2026-08-07 22:45 - Track editor: move listen preview under Master audio asset (no labels; narrower player).

2026-08-07 22:40 - Files/track editor: admin audio listen preview via authenticated `audio.php` (delivery when ready, else source/master) — compact player in the track editor + ▶ on Files → Audio rows.

2026-08-07 22:30 - Track editor: place Player tab label inline to the right of the Lyrics/Notes pill (no vertical jump when switching to Notes).

2026-08-07 22:25 - Track editor: label the comment field as Track description / blurb.

2026-08-07 22:20 - Admin Markdown help: shared `?` control + modal for restricted player Markdown (track description/lyrics, release & playlist long descriptions); short/meta textareas and page richtext unchanged.

2026-08-07 21:45 - Track editor: stop autosave loop (silent cover-path sync + dirty signature; no toast/refresh on no_change); taller Lyrics/Notes field (min-height 200px).

2026-08-07 21:40 - Track editor: restore the compact Lyrics/Notes pill toggle (clearer active state, less space than Catalogue grey tabs).

2026-08-07 21:35 - Track editor: align with shared editor chrome — cover+`release-cover-meta`, `playlist-settings-field` labels, header autosave status via `visual-asset-display-status`.

2026-08-07 21:25 - Track editor: denser layout (covers beside chips, smaller previews, sticky status footer) and Catalogue-style autosave on blur/cover change; remove the Save metadata button.

2026-08-07 21:18 - Track editor: after a real save, refresh the Files → Audio pool when the modal closes (avoids rebuilding the list under the open editor).

2026-08-07 21:16 - Track editor: style the In release summary chip like Duration/Format (dim label + bold value).

2026-08-07 21:15 - Track editor: replace the Release name input with a summary chip `In release: …` before Duration (Catalogue owns the name; detail now includes `release_title`).

2026-08-07 21:10 - Track editor: Release name is truly read-only (Catalogue owns it; server ignores album edits); drop Track # from the compact summary; label the stats row as Master audio asset.

2026-08-07 21:05 - Track editor summary: persist bitrate / sample rate / bit depth on audio registry display; open path reads them (and one-shot inspect+cache when missing) so the compact summary is filled before the first save.

2026-08-07 20:55 - Files pools: when demo catalog is shown, keep bundled `bandPromo_*` campaign media visible even if the install already has operator uploads (Settings toggle is the gate; Brand assets still use kind-aware shell replacement hide).

2026-08-07 20:50 - Catalogue pool meta: drop the redundant `demo · localhost editable` label on bandPromo demo (localhost editors already know).

2026-08-07 20:45 - Migrate real campaigns off invisible `primary` orphan bucket: add `bandpromo_release_migrate_campaign_off_primary()` + `scripts/migrate_primary_campaign.php`; ran locally to move Winter Party → `winter-party` (playlist/brand/assets retargeted; empty primary restored).

2026-08-07 20:35 - Welcome-only catalog repair nudge (registry-JSON health snapshot; links to System → Deliverables → Repair catalog; no heavy migrate on nav). Catalogue/release cover previews prefer delivery card/thumb and no longer fall back to multi-MB `/media/*/original/` paints; restored Repair catalog Preview/Apply controls on Deliverables.

2026-08-07 20:30 - Admin nav speed (round 4): hot paths no longer run full asset-registry migrate (scandir/hash/SFX/visual tiers) on every Catalogue load — that alone was ~5s on Google Drive; heavy migrate is opt-in for autofix/bootstrap. Sessions remain under LOCALAPPDATA; get-config 403 fixed earlier this session.

2026-08-07 20:20 - Admin read-only nav: remove media chmod scans from admin.php entirely; config structure repair only on Settings; Site update/GitHub package checks only when Welcome requests include_package (not on Catalogue/Files notifications).

2026-08-07 20:10 - Admin nav speed (round 2): release PHP session lock in admin-api-guard + session-check + after admin.php auth (Google Drive session files were serializing every API behind ~5s waits); skip media chmod probes on Windows; dedupe get-releases/get-themes catalog fetches.

2026-08-07 20:00 - Admin localhost speed: skip Site update/GitHub checks on localhost (was blocking PHP's single-threaded server 10–20s per navigation); stop auto GitHub refresh + build-log reads on every tab; Files Visual trusts registry delivery variants (no is_file probes) and paints thumb_url/card_url instead of multi-MB originals.

2026-08-07 14:20 - Implement visual naming rebuildability: registry display title/description/keywords/captured_at; Files/picker title-first labels + drilldown edit; still masters write IPTC Core via XMP (EXIF date read-only via Pillow); video masters remux to MKV with Matroska tags (delivery stays MP4); gallery searchable multi-select add/remove; autofix remux+heal; MKV upload allowed.

2026-08-07 13:50 - Docs lock: visual naming + rebuildability — registry `display` title/description/keywords/date; still masters keep camera EXIF (read) and write IPTC Core via XMP; video masters remux to MKV with Matroska tags (delivery stays MP4); gallery multi-select picker IA; Twisted Chronicles tour gallery use case keeps fan comment/share (build v0.9+).

2026-08-07 12:20 - Video delivery: keep soundtrack only for `role=gallery`; living covers / shell backgrounds / other roles remux or transcode to silent `standard-stream` (MP4 copy path now strips audio when silent).

2026-08-07 12:10 - Sound effects three-tier: `media/sfx/{original,master,optimal}` with `ast_*` masters, tagless 192k delivery MP3s, playback prefers optimal; PRP packs SFX masters; upload/migrate/delete wired.

2026-08-07 12:00 - PRP export is masters-only for real: audio/visual omit `original/` bytes (registry keeps `original_filename`); SFX still ships `media/sfx/original` (its only working tier).

2026-08-07 11:50 - PRP visual export: pack unified `media/visual/original` + `media/visual/master` only (stop dual-packing legacy `img`/`photo`/`video`/`special` copies).

2026-08-07 11:40 - Admin CSRF: `get_csrf_token()` / get-admin-csrf now rotate expired tokens instead of re-issuing a token `validate_csrf_token()` would reject; max age raised to 12h (fixes Backup delete and other mutations after long admin sessions).

2026-08-07 11:25 - Publish build no longer requires Demo PRP / default-theme download: Admin Publish skips ensure; setup passes `ensure_demo` for first-run PRP import only; demo ensure no longer hard-depends on legacy default-theme media ZIP.

2026-08-07 11:20 - Tagless delivery audio (locked): delivery MP3s strip all ID3/APEv2 after build; freshness treats tagged files as stale; Media Session / Cast use registry+playlist metadata (docs: DELIVERY-ARCHITECTURE, PLATFORM-MODEL, MEDIA-HANDLING, FEATURES).

2026-08-07 10:55 - PRP export resolves track `display.cover` / `living_cover` filenames to visual asset ids (so still sidecars + living videos are packed); MP3 master/delivery writes strip leftover APEv2 so ID3 is the sole tag source.

2026-08-07 10:15 - Large backup/PRP downloads: stream in 1 MiB chunks with Accept-Ranges (avoid PHP `readfile` OOM/`ERR_INVALID_RESPONSE` on multi-GB archives); do not emit JSON after headers are sent.

2026-08-06 22:50 - PRP export is a background backup job (queue → build → Ready download in System → Backup list), matching full site backup behavior.

2026-08-06 22:45 - Catalogue + System → Backup & export: export portable release package (`.prp`) with download; platform demo release is editable on localhost only (still protected/undeletable on all hosts).

2026-08-06 22:30 - PRP implementation wave: export emits `.prp` with masters-only registry (strip delivery), FAQ excluded, VERSION/`platform_demo` flags; import keeps IDs with operator collision UI (refuse/overwrite/skip/allocate) and system demo overwrite; Cover flow default; player page tabs follow current track `release_id`; Gallery page in demo package + registry; release packaging builds `bandPromo-demo.prp`; campaign duplicate (shared media) + multi-ref delete guard.

2026-08-06 22:00 - Docs lock: portable release packages (PRP / `.prp`) as the campaign handoff contract — PORTABILITY, PLATFORM-MODEL, ACCESS-MODEL, TODO, AGENTS, FEATURES. Demo PRP = Bio+Gallery; FAQ system-owned; Primary = invisible orphan bucket; Cover flow + Living install chrome; contextual pages by current track release; duplicate shares media; follow-ups for log UID + backup rewrite.

2026-08-06 17:15 - Vendor bootstrap: if host pip rejects manylinux2014 tags (common on Python 3.6 shared hosts), unpack matching `scripts/vendor-wheels/*.whl` directly into `scripts/vendor/` and log pip failure tails.

2026-08-06 17:00 - Python **3.6.9 hard floor** for all `scripts/`: 3.6-safe syntax/APIs (`bandpromo_python_path`, `version_format`, `build_release_package`, `backfillWavAudioToFlac`); cp36 offline Pillow/xxhash wheels + requirements env markers; `check_python36_compat.py` gate in Template integrity CI; INSTALL/AGENTS document the floor.

2026-08-06 15:40 - Vanilla build hygiene: install Python deps into site-local `scripts/vendor/` (offline `scripts/vendor-wheels/` matched to host Python); quiet xxhash spam; ffmpeg diag respects bundled `scripts/bin`; demo gallery seeds/heals Rollercoaster via visual `asset_id` contract; rollercoaster required in default-theme starter media; optimizeVideo accounts for registry original filenames.

2026-08-06 14:50 - Site update follow-up: always auto-start **Rebuild all deliverables** on `run_recommended=1` (no longer gated on build-required state, which could skip the rebuild after redirect); toast copy matches whether rebuild started; package update always returns Deliverables follow-up; notification CTA says **Open Dashboard → Site update**.

2026-08-06 11:45 - System → Security: install sanity check compares managed Apache/PHP stubs to `biblioteca/templates/runtime/`, with preview/repair (CSRF + audit) for missing/empty/drifted files; writes `log/security-sanity-latest.json`. Build preflight also seeds missing protection stubs.

2026-08-06 10:50 - Repo hygiene: drop IDE prefs (`.vscode/`, `.editorconfig`) from git; fully ignore `data/`, `log/`, and `backups/`; move Apache/PHP host stubs into `biblioteca/templates/runtime/` so setup regenerates them when missing.

2026-08-06 10:00 - Git policy: ignore the entire `/media` tree and untrack remaining demo/special assets; package theme/demo ZIPs from on-disk media (CI seeds from the previous default-theme ZIP); keep `media/.htaccess` as a tracked template under `biblioteca/templates/` (later moved to `runtime/`).

2026-08-05 21:15 - Checkpoint: responsive player contract, seek-only scrubber, mobile portrait density, track-info rename, support CTA hardening, and floating-widget cleanup for tester release.

2026-08-05 21:05 - Housekeeping: remove dead Ko-fi floating-widget loader left after support CTA standardization; update third-party notices to match the in-flow link-only support path.

2026-08-05 21:00 - Playlist coverflow: drop selector/coverflow margins and align thumbs tightly so the playlist strip no longer wastes vertical space.

2026-08-05 20:40 - Mobile portrait compression: rename `.info-container` to `.track-info`; replace native audio chrome with a slim seek/time scrubber; tighten stacked player chrome and fit content tabs on one row without overflow.

2026-08-05 20:05 - Playlist coverflow: remove vertical padding so the selector strip hugs the cover thumbs in every view.

2026-08-05 19:45 - Player sizing limits: cap release logos at 100px high on large screens and 80px in compact/phone layouts; cap the active playlist cover at 80px below the full landscape-desktop layout.

2026-08-05 15:20 - Responsive follow-up: keep measured prose blocks as full-width flex rows so quarter-width Bio media stays in one row; hide the player rail scrollbar while retaining short-viewport scrolling.

2026-08-05 14:15 - Responsive player contract: replace competing device/pointer grids with stacked + dimension-aware split modes; separate cover/rail/content measures; center readable Notes/prose while keeping galleries wide; scroll narrow tabs to the active view; wire validated body/heading brand fonts at runtime; standardize the support CTA as an in-flow, contrast-checked link with a reduced-motion-safe intermittent halo.

2026-08-05 13:40 - Checkpoint housekeeping: ignore local theme/demo release package work directories so generated extraction trees never enter Git.

2026-08-05 12:00 - Player layout: support link sits in-flow under the player (`#beggars-banquet`) instead of fixed overlay; player rail capped with vw so content keeps a fair share (`--player-rail` / responsive `--card-size`).

2026-08-04 23:20 - Notes cue-sheet polish: fix CSS specificity so base heading rules no longer force coral on h2+; softer hierarchy (accent intro, calm entries, hairline separators), body 1.35, readable padding.

2026-08-04 23:15 - Player Notes mode: denser cue-sheet Markdown (`player-markdown--notes`) — tighter rhythm, body line-height 1.2, only h1 keeps primary accent; entry headings step down in white/muted.

2026-08-04 23:00 - Player responsive grid: mediaplayer column tracks `--card-size` + side gutters (`calc(...) 1fr`) instead of a greedy `minmax(0, 800px)` track that left empty player chrome and crushed `#content-container`.

2026-08-04 22:50 - Player Markdown: restore a clear h1–h4 size spread for operator styling; paragraph line-height 1.1; keep tight heading→paragraph gaps.

2026-08-04 22:45 - Player Markdown spacing: tighter heading→paragraph gaps and compact heading sizes (system default; not a brand control).

2026-08-04 22:15 - Brand Panel blur also applies to playlist rows, page and gallery panels (same `--panel-blur` token as lyrics/login).

2026-08-04 22:00 - Tester feedback: lyrics/Notes Markdown now renders headings/lists (lyrics keeps hard line breaks; Notes uses default paragraphs); Branding adds Readability controls (backdrop dim + panel blur); accent alpha stays auto-derived from Primary/Secondary.

2026-08-04 21:45 - TODO: capture Delivery smoothness leftovers (orphan visual delivery GC, Deliverables skip/reuse summary, Visual pool honesty polish); Cursor plans closed with remaining audit todos deferred here.

2026-08-04 21:25 - Track details form: Lyrics/Notes field uses only the role toggle (no duplicate field label); textarea `aria-label` follows the active role.

2026-08-04 21:10 - Publish fix: starter-pack install no longer fails on Google Drive `desktop.ini` (skip during copy_tree; workdir cleanup is best-effort with retries after a successful install).

2026-08-04 20:55 - Operator label rename: install **Active** brand → **Base** brand (Set as base / Base badge / help copy). Storage pointers (`active_theme_id` / `active_brand_id`) unchanged.

2026-08-04 20:50 - Branding editor: hide Brand narrative (mood/keywords/tone) from the operator UI for now; fields remain in brand documents for future premade themes / AI helpers.

2026-08-04 20:35 - Branding Shell media: cover-style overlay ✎/↺ on larger slot previews; tightened operator helper copy on the Branding editor.

2026-08-04 20:20 - Branding Shell media: replace the in-editor Brand assets drag/click pool with per-slot ✎ pickers (same shared media picker as covers); logo → Brand assets, poster/still → Visual+Brand assets, living → Visual video, SFX → Sound effects; picker tiles pass `asset_id` into brand `asset_ids`.

2026-08-04 19:30 - Playlist operator UX: friendly pool meta (`Published DATE from the release "TITLE" (N tracks)`); pin default playlist via `install.pointers.default_playlist_id` (player fallback remains latest public `publish_date`); `package_type` + `play_order` (`stored`/`reverse`, shows/podcasts default reverse) in storage, settings UI, and player payload track order. Playlist settings saves no longer wipe published player tracks (order applies at load time).

2026-08-04 18:35 - Per-track text panel role: Lyrics ↔ Notes toggle in Files → Audio (`display.text_role` + optional `notes_label`, default player nav Tracklist); same Lyrics tag/registry content; player `syncLyricsTab` renames the locked nav; live overlay on playlist payload without full Publish.

2026-08-04 17:45 - Brand shell override runtime: player applies release-brand logo + still/living backgrounds on playlist select (payload `brand_styles[].assets`); CSS tokens unchanged; install Active baseline kept for empty slots and login SFX; system scrim over busy shell media; living background re-attaches after playlist switch.

2026-08-04 13:55 - Publish fix: `optimizeMedia.py` summary referenced removed `photo_count`/`img_illus_count` after M4 register-or-fail, causing `NameError` and failing Rebuild all deliverables; summary now reports `orphan_count` and visual rebuilt/fresh/failed counts.

2026-08-04 17:40 - Visual identity M4–M6: stop stem dual-write + register-or-fail; Files `operator_title`; shell `asset_ids` heal; release campaign export ZIP (`export-release-package.php` / `bandpromo_release_campaign_export_to_zip`) with registry subset; import merges `data/assets/registry.json`.

2026-08-04 17:10 - M4/M5: stop visual stem optimal/thumb dual-write; register-or-fail skips unregistered image/video intake on Publish; resolver drops stem optimal dual-read (delivery → unified original/master → intake); shell heal backfills brand `asset_ids`; Files Visual exposes `operator_title` (role + context). Physical `media/special/` fold deferred; M6 export still open.

2026-08-04 16:20 - M3 XXH3 skip-if-fresh: audio + visual image delivery compare master XXH3 to `delivery.source_xxh3` (mtime only as legacy one-build fallback); `xxhash` added to scripts/requirements.txt; PHP stores/looks up `content_xxh3` alongside legacy `content_sha256`; Publish logs “already up to date (master XXH3 match)”; force via `BANDPROMO_FORCE_AUDIO_DELIVERY` / `BANDPROMO_FORCE_VISUAL_DELIVERY`.

2026-08-04 15:05 - M2 visual masters: `media/visual/original/` + `media/visual/master/ast_*.{ext}` with relocate/materialize helpers; new visual registrations get ULID master filenames; registry migrate + Content autofix backfill existing assets; optimizeMedia/optimizeVideo/makeSocial read master-first (legacy intake fallback); delete removes tier files; gitignore unified original/master trees.

2026-08-04 14:10 - M1 complete: brand shell parallel `asset_ids` map (normalize/resolve/sync + theme-editor drag/click writes); `makeSocial.py` prefers active brand poster `asset_id` → visual delivery; page picture picker persists `block.asset_id`; Content autofix backfills brand/gallery/page asset_ids and rewrites audio `display.cover` / `living_cover` filenames to `ast_*` when registered.

2026-08-04 13:20 - M1a visual `asset_id` read-path: page picture allowlist accepts `/media/visual/delivery/` (+ resolve by `asset_id`); gallery entries/materialize prefer registry delivery URLs; track-cover and living-cover resolvers accept `ast_*` with filename dual-read.

2026-08-04 12:55 - Policy lock: Visual identity completion track (M1–M6) — three-tier visuals like audio, `asset_id` addressing before on-disk masters, XXH3 for freshness/dedupe (not mtime; SHA-256 only for release ZIP crypto), operator role+context titles, shared-ref delete, replace-upload same `ast_*`, release packages require registry subset + import merge (no analytics). PLATFORM-MODEL / MEDIA-HANDLING / PORTABILITY / TODO / ROADMAP updated; Brand-assets fold absorbed into M1–M4.

2026-08-03 23:25 - Publish build log: fold stage label into the `====` script banner (`Stage N/M — …` + `Script: …`) instead of a separate `── Stage ──` line above `Running:`.

2026-08-03 23:05 - Audio delivery tag reader: MP3 masters use ID3 frames (`TIT2`/`TPE1`/…); optimizeMedia was probing EasyID3-style keys on raw `mutagen.mp3.MP3`, so the build falsely logged “no artist/title” and could rewrite delivery ID3 empty after copy. Reader now matches `audioMasterMetadata.py`, fills missing fields from registry display, and uses `TBPM` correctly.

2026-08-03 22:55 - Restore audio description/lyrics/cover from unregistered leftover masters onto matching live `ast_*` assets (identity match); writes tags onto the live master and re-links Visual pool sidecars. Wired into Content autofix audio-display sync and full Publish preflight. Local Retroscopy hour tracks restored (10/12 with pool covers; #06/#07 had no leftover sidecar — embedded art only).

2026-08-03 22:45 - Files → Audio empty titles/edit: registry `display{}` was blank after re-register even though masters had tags (C/A/T badges all red; editor looked empty). Full Publish preflight now rebinds stale release/playlist links and fills incomplete audio display from master tags (skips rows that already have title+artist+duration). Local install display cache refreshed; intentional orphans (e.g. Party with a banana) stay unassociated.

2026-08-03 22:30 - Content autofix: rebind stale release/playlist audio membership when registry IDs were replaced (re-upload). Matches live assets by artist/title identity (including `#06` ↔ `#06 FINAL` / `#11 NEWER WIP` suffixes), repairs playlist `entries` (not only legacy `tracks`), and clears player payloads so the next publish rebuilds from live masters. Files → Audio “orphan” means not on a release — true unassociated uploads stay orphaned.

2026-08-03 22:05 - Audio delivery skip-if-fresh: store master `source_mtime` on `assets[].delivery` after a successful build; later Publish/optimize skips unchanged masters (logs “already up to date”), records skipped counts, and only requires ffmpeg when a track actually needs a rebuild (`BANDPROMO_FORCE_AUDIO_DELIVERY=1` forces all).

2026-08-03 21:55 - Publish audio log: lead with track title/artist (tags + catalog display), put `ast_*` filename after source tier, clarify track-cover lines (Files assignment vs embedded art), and process the delivery queue in artist/title order instead of raw master-filename sort.

2026-08-03 21:35 - Shell media install harden: restore/heal missing demo poster paths (cover→share fallback), brand duplicate no longer keeps a broken missing-file path when cloning from locked bandPromo Default, Publish runs shell-media heal before build, starter-pack checks include `bandPromo_cover.png`, and social-assets diagnostics stop telling operators to edit the locked default brand.

2026-08-03 21:25 - Publish social-assets failure: when the share/poster source file is missing, `makeSocial.py` now names the config keys, active brand + poster slot, operator fix path (Content → Branding → Shell media), demo `bandPromo_*` note, and nearby `media/special/` images; `makePlaylists.py` cover warning points at the same Branding poster slot.

2026-08-03 21:15 - Content editors: put a left-aligned ← Back control on Playlist, Gallery, Pages, and Branding edit headers (same pattern as Catalogue release edit); drop named ← Playlists/Galleries/Pages/Branding buttons on the right.

2026-08-03 21:10 - Brands: allocate opaque `brd_{ulid}` storage ids on duplicate (including setup “Your own brand”); Content → Branding pool shows title + locked/active only — no copy-copy slugs. Legacy ids remain valid; PLATFORM-MODEL documents the contract.

2026-08-03 21:00 - Files Visual/Brand/SFX asset modal Delete: capture the selection key before closing the modal so confirm no longer opens with an empty file list (and silently no-ops).

2026-08-03 14:25 - Player coverflow: resolve playlist catalog art via visual delivery thumbs (not only legacy img/photo thumb paths), then fall back to owning release poster and first track cover so coverflow no longer shows letter placeholders when a playlist poster was never set.

2026-08-03 13:50 - Delivery smoothness P0+P1a: tag/cover saves keep last-good `/play` payloads (no empty wipe), sync ID3 onto existing optimal MP3s, quiet-republish affected playlists, and clear false “Prepare your songs” pending; shared covers use exact SHA-256 of intake/embedded bytes to link existing Visual assets instead of extracting per-stem clones, with migrate hash backfill + content-hash dedupe.

2026-08-03 12:57 - Post-upload delivery false failure: parse the JSON result from light Python tasks even when cover-conversion progress printed on stdout; route delivery-script progress to stderr so PHP sees a clean JSON payload.

2026-08-03 12:46 - Hotfix HITZ deliverables: `stdio_utf8.py` no longer uses `from __future__ import annotations` (SyntaxError on host Python &lt; 3.7).

2026-08-03 12:40 - Fix post-upload audio delivery prep: light Python tasks now receive a resolved `FFMPEG_PATH`, ffmpeg detection checks bundled `scripts/bin` and return codes, and UTF-8 stdout setup is idempotent so importing `makePlaylists` + `optimizeMedia` no longer closes stdout (`ValueError: I/O operation on closed file` on HITZ FLAC uploads).

2026-08-03 12:27 - Playlist cover picker: show a pending preview immediately on selection (same pattern as release covers), resolve `/media/...` paths when `poster_preview_url` is not yet available, keep overlapping autosaves queued, and sync the saved asset id after PATCH so edit mode does not leave “No cover selected” after a successful save.

2026-08-03 12:25 - Media picker modal: stop nested preview `<button>` inside tile `<button>` (HTML reparsing scattered labels across the grid); restyle picker tabs without Content sub-tab accent chrome.

2026-08-03 12:20 - Admin toasts: warning/error stay 10–20s with a dismiss control (success ~4.5s); track editor never fills Title with a ULID master filename — uses original stem / Untitled like the Files list.

2026-08-03 11:45 - Upload → edit → playlist without full Rebuild: audio upload fills registry display from master tags (filename-stem fallback), prepares delivery MP3s under master stems (`ast_*.mp3`), and surfaces delivery/scan failures in Notifications; playlist save delivers any missing MP3s and republishes that playlist’s player payload so `/play` works without Rebuild all deliverables.

2026-08-03 11:10 - Player branding follows the selected playlist’s owning release (`playlist.release_id` → release brand), not per-track `brand_id`. Playlist payload exposes `brand_id` + `brand_styles`; tracks no longer carry player brand. Docs and Branding help updated. Also: full Publish no longer resets install Active brand to bandPromo Default (Demo Release seed claims Active only when the pointer is empty).

2026-08-03 10:55 - Stop full Publish from resetting the install Active brand to bandPromo Default: Demo Release ensure/seed no longer claims `active_brand_id` when an operator brand is already set (first-run empty pointer only). Clarify Branding help — Active = login/shell baseline; player tokens follow the playing track’s release brand, not the opened playlist.

2026-08-02 22:55 - Release cover picker: queue overlapping autosaves so a new cover is not dropped while another save is in flight; keep a pending preview URL after selection; map registry intake aliases (`img`/`photo`) to real `/media/...` paths; fall back visual preview URLs to intake originals when delivery variants are missing.

2026-08-02 22:40 - Site update discovery: when `api.github.com` is blocked, resolve the newest package from the public Releases Atom feed (includes prereleases) before falling back to `/releases/latest`. Publish closed-beta packages as GitHub latest (`prerelease=false`) so stuck hosts still see updates. Includes track-editor Title/Version + optional Release name fix from build 350.

2026-08-02 22:30 - Track editor: stop mashing Version into Title (registry detail now returns separate fields); Release name is optional there because Catalogue → Release owns the campaign name.

2026-08-02 22:05 - Site update: always show Check again (including quiet up-to-date), and force a live GitHub package check when Dashboard loads, so a 15-minute notifications cache cannot hide a freshly published tester build.

2026-08-02 21:55 - Fix visual asset registry duplicate storm: lookup/register/backfill now reuse one visual row per original filename (update intake bucket in place instead of minting a new `ast_` ID on bucket mismatch); migrate prunes orphan duplicate visuals; unregister removes all rows for a basename; optimizeMedia dedupes the visual queue by filename so Publish cannot reprocess hundreds of clones of the same file (e.g. Lipstick Logo on HITZ).

2026-08-02 20:55 - Checkpoint v0.8.10 build 347 for closed-beta: Catalogue release associations + autosave, preview cleanup, ADMIN-UI intent ladder, and USE-CASES / PLATFORM-MODEL mental-model docs (Vanilla, Twisted Chronicles, HITZ).

2026-07-22 15:25 - Document closed-beta use cases (Vanilla, Twisted Chronicles, HITZ) in `docs/USE-CASES.md`; lock operator mental model (active vs release brand, global vs contextual player pages, Lyrics/Tracklist role) in `PLATFORM-MODEL.md`; reconcile FEATURES/MEDIA-HANDLING/ROADMAP/TODO/AGENTS and Catalogue help; mark Gallery player tab removed and playlist catalog rules as shipped.

2026-07-22 13:52 - Hide the Association empty list under Catalogue Preview / Base info: `#releaseAssociationActiveList { display: flex }` was overriding the `hidden` attribute (same class of bug already fixed for Associated tracks).

2026-07-22 13:50 - Catalogue Preview no longer shows the Tracks/Playlists/Galleries/Pages/Branding/Press kit tabbed surface under the cover; keep cover meta, brand preview, and long description preview only.

2026-07-22 13:45 - Align admin controls with `docs/ADMIN-UI.md`: pool tools use `.icon-btn--pool` (+ danger/active), delete confirms use `.btn-danger`, bare `button` coral only when unclassed, chips/media intents share tokens, gallery ✕ aliases layout remove, backlog cleared.

2026-07-22 13:30 - Catalogue association pools use the same bordered playlist-row chrome as Available tracks. Document admin button/intent colors in `docs/ADMIN-UI.md`; add shared `--intent-*` tokens, `.btn-secondary` / `.btn-danger`, and drop duplicate media-action panel color rules.

2026-07-22 13:15 - Release track membership and playlist/gallery/page associations now autosave on change (drop/✕), matching Base info. Remove the Catalogue `Save release` button and staged-dirty confirm flow.

2026-07-22 13:00 - Show the ✕ remove control on Associated playlists/galleries/pages again: those rows were incorrectly marked readonly, which hid the button via page-editor CSS.

2026-07-21 23:58 - Fix Release association drag-and-drop: Available playlists/galleries/pages no longer use the track-editor `.dragging` collapse class (which zeroed the row mid-drag). Harden Associated drop targeting and allow double-click to associate as a fallback.

2026-07-21 23:10 - Autofit the Release Base info Long description textarea to its content (no inner scrollbar).

2026-07-21 23:00 - Catalogue Preview / Base info now includes a `Long description preview` under Brand preview, rendering the release description as Markdown via the shared player markdown helper. Cover summary stays blurb-only.

2026-07-21 22:57 - Label the Base info / Catalogue Preview brand card with a `Brand preview` heading.

2026-07-21 22:55 - Catalogue Preview and Base info now show the associated brand card (logo shell, mood, swatches) directly under the cover/title preview, so branding is visible without opening the Branding drill-down tab.

2026-07-21 22:20 - Release editor Playlists/Galleries/Pages tabs now use Available ↔ Associated pools like Tracks: unassigned-only available items, ✕ to unassign, drag to associate, staged until Save release. Demo/protected containers stay immovable; another release's membership is never stolen from the available pool.

2026-07-21 20:16 - Promote the Release editor and contextual right-column headings from `h4` to `h3`.

2026-07-21 20:14 - Promote the Catalogue heading in the Release editor card from `h3` to `h2`.

2026-07-21 20:08 - Consolidate Release save feedback into one top-right card button; remove the inline Title status. Base info preview updates live while typing, fields persist on blur/change, and validation failures use operator toasts.

2026-07-21 19:58 - Clarify that Release Long description allows Markdown directly in its field label.

2026-07-21 19:56 - Expand Release Blurb and Long description to full-width multiline fields with labels above; increase their visible heights.

2026-07-21 19:54 - Move the Release editor section tabs directly beneath the `← Back / Release editor` header, outside the Base info form card.

2026-07-21 19:50 - Simplify Release Base info ordering to Title, Release date, Press contact, Branding, Blurb, Long description. Remove the Press kit/Enjoy here headings, Credits, streaming links, press-photo controls, and associated helper copy; deferred values remain preserved for a later schema redesign.

2026-07-21 19:35 - Consolidate Release editing around a first `Base info` tab: Title, Release date, Short description, Branding, and all EPK fields live there. Remove standalone Branding/Press kit tabs and their previews; Tracks now lazy-loads the full membership editor only when selected.

2026-07-21 19:25 - Release track membership is now explicitly unordered: Associated tracks uses a simple artist/title/duration list with no numbering or drag handles; available tracks can still be dropped in and the associated list re-sorts by track date, artist, title. Release saves no longer rewrite master tags or persist per-release track numbers; playlists remain the only ordered listening products.

2026-07-21 19:07 - Give each Release editor tab a contextual right-column heading: Associated tracks/playlists/galleries/pages, Brand preview, and EPK preview.

2026-07-21 19:02 - Label the Release editor’s right column `Track editor` while the Tracks tab is active; Branding remains `Live preview`.

2026-07-21 18:55 - Restore the local Retroscopy release → `hitz-copy` brand link after detecting an editor-transition overwrite during lazy-template verification.

2026-07-21 18:50 - Release editor tabs now lazy-inject their left-column editor templates. Branding and Press kit controls do not enter the DOM until selected; Press kit refreshes its saved data on activation, while the right column remains the corresponding live/read-only preview.

2026-07-21 18:40 - Release editor templates: Branding shows its selector on the left and the shared Content → Branding live preview on the right; Press kit owns the existing EPK form and a read-only preview. Non-Track tabs suppress track/cover editor surfaces.

2026-07-21 18:25 - Wire the Release editor tab-row state and move the Branding selector into a Branding panel beneath it. Other editor tabs remain content-empty for incremental wiring.

2026-07-21 18:22 - Reuse the Preview tab-row styling under Release editor Short description for Tracks, Playlists, Galleries, Pages, Branding, and Press kit; buttons are intentionally not wired yet.

2026-07-21 18:15 - Remove unused Release EPK Tagline and Genre from the editor and Press kit preview. Existing values remain preserved during saves/imports for package compatibility; EPK audit presence now uses fields with actual operator/runtime surfaces.

2026-07-21 18:10 - Reorder the Release editor’s primary fields as Title, Release date, Branding, Short description; align them as consistent labelled rows and move Short description out of the Press kit section.

2026-07-21 18:05 - Remove Catalog ID and its helper from the Release operator UI and pool subtitle; retain the underlying optional schema field for package/backward compatibility.

2026-07-21 18:00 - Release editor header now reads `← Back  Release editor`; the form begins below with horizontal Title and Release date rows. Remove the release-date and Branding helper paragraphs.

2026-07-21 17:52 - Release editor: move the Catalogue return control to the left edge of the editor header and shorten its label to `← Back`.

2026-07-21 17:45 - Release preview tabs now lazy-refresh only their selected section from a no-cache registry-backed endpoint on every activation. Catalogue selection still paints instantly from its initial lightweight payload; full assigned/available track state remains edit-only.

2026-07-21 17:32 - Local catalogue data: associate the `the-retroscopy-hour` page with the matching release.

2026-07-21 17:25 - Local catalogue data: associate the `the-retroscopy-hour` gallery with the matching release.

2026-07-21 17:18 - Restore the read-only Playlists preview list. Existing playlist ownership (`playlist.release_id`) now appears under the selected Catalogue release without exposing editor actions.

2026-07-21 17:10 - Catalogue release selection now renders entirely from lightweight registry preview data; the full assigned/available track endpoint loads only in Edit. Tracks preview is one unnumbered, read-only list (artist, title, duration), sorted descending by track release date, with the duplicate editor list force-hidden.

2026-07-21 16:58 - Add Tracks as the first Release preview tab and render the release-owned track pool inside it; the editor track list remains separate and appears only in edit mode.

2026-07-21 16:52 - Remove the forced minimum height from empty release preview tab panels so an empty Playlists preview does not leave a blank box.

2026-07-21 16:50 - Remove the redundant `release-preview-panels` wrapper; tab panels now sit directly under the release preview surface.

2026-07-21 16:47 - Hide the Release save-status row completely in read-only Catalogue preview; it remains available only in edit mode.

2026-07-21 16:45 - Catalogue preview is read-only: remove the redundant playlist row, Create playlist action, and editor hint; child lists render as static preview content; Branding preview drops its editor link; cover controls appear only in edit mode.

2026-07-21 16:40 - Catalogue release preview: pool label Registered releases; Preview column with title/date/summary beside cover; Campaign surfaces replaced by Playlists/Galleries/Pages/Branding/Press kit tabs; ownership children return titles + brand preview payload.

2026-07-21 16:10 - Files: Sound effects and Brand assets filter by brand (All / Orphans / each brand), not catalogue releases. list-media brand membership + brand_title; media picker swaps release vs brand filter by target.

2026-07-21 15:45 - Files filters: All files / Orphans / each catalogued release; drop Visual In use|Unused; search titles (audio) and references (visuals), never filenames; media picker gets the same catalogue + search; hide storage paths from operator surfaces; release hub uses Branding link id.

2026-07-21 15:20 - Files tabs polish: shared permanent delete warning; Visual In use/Unused only (no role/origin chips); pool cards show bold name + release trail; catalogue Orphans vs Unused wording split; Brand assets/SFX release labels; operator copy sticks to branding (not Theme/Identity).

2026-07-21 15:00 - Files tabs: masters-only Audio; shared All/Catalogue releases/Orphans + live name filter; Visual usage simplified to In use/Unused; SFX list-only and no brand/usage clutter; Brand assets hide shell audio; stick to branding/brands/brand assets wording.

2026-07-21 14:30 - Content tab label **Catalogue** (list of releases; each release remains the campaign entity). Move release-package import from the release editor to System → Backup, export & import.

2026-07-21 14:15 - Content UI: keep operator term **Playlist** (not “listening products”); Branding tab restored. Collapsible Content help holds the model copy; remove duplicate card-notes on Release/Playlists/Galleries/Pages/Branding/Player.

2026-07-21 14:00 - Release campaign umbrella implementation: ownership `release_id` on brand/playlist/gallery/page with migrate dual-read; Content → Release hub with campaign surfaces; shared Demo Release importer for setup (`bandpromo_ensure_demo_release_package`) and Admin import ZIP; build publishes `bandpromo-demo-release-*.zip` alongside default-theme dual-read. Export still deferred.

2026-07-21 13:00 - Product lock: Release is the campaign umbrella (owns tracks, identity/branding, EPK, galleries, pages); Playlist is the streaming listening product (album/single/tour packages reuse tracks). Brand identity is owned by the release (`release_id`). Demo/setup portability retargeted to a Demo Release package using the same importer; export follows after hub UX stabilizes. See PLATFORM-MODEL + PORTABILITY.

2026-07-21 12:00 - Sound effects pool: Files → Sound effects (`media/sfx/original/`, registry `kind=sfx`, single role `sfx`) separate from catalog Audio; Branding welcome/logged-in slots assign any SFX clip (usage on brand slots, not file roles); migrate special shell-audio refs into SFX. Extra UI SFX slots deferred. Brand-assets visual fold still open.

2026-07-21 11:30 - Visual pool Phase 3 operator wiring: track-cover assign stores pool ref + embed (no stem sidecar copy); build prefers assigned cover and prunes redundant sidecars; Files → Visual / media pickers get brand filter chips; Content gallery and pickers gate on `pool_ready` and name missing delivery variants. Brand-assets disk fold still open.

2026-07-21 11:00 - Visual pool Phases 0b–2: shared asset registry now registers visuals (`kind=visual`, role, brand_id, intake_bucket) with upload/backfill from img/photo/video/special; checked in `scripts/delivery-contexts.json`; format-aware optimize writes `media/visual/delivery/{asset_id}/` variants (alpha→PNG, opaque→JPEG) plus video poster/stream; PHP resolvers dual-read new paths then legacy optimal/thumb. Ignore `media/visual/delivery/`. Phase 3 remainders (brand filter chip, Content variant gating, Brand-assets fold) still open.

2026-07-17 12:30 - Cheap first-paint wins: Living shell shows still first and attaches MP4 after load/idle (login + player; no early `<source>`); audio `preload=none` until Play; page tabs hydrate on open only (not all after load); service worker v7 skips get-player-page/playlist and versioned JS/CSS so SW network-first no longer double-fetches them.

2026-07-17 12:25 - TODO: Favicon + PWA icons must be generated from Branding (not manual online generators) before the v0.8 exit gate. Cold HARs after rebuild show shell logos were optimized (~72–128KB); living shell MP4 backgrounds are still unoptimized and dominate transfer.

2026-07-17 12:05 - Site update follow-up: after a successful install, admin redirects to System → Deliverables, opens the build log, and auto-starts Rebuild all deliverables (`run_recommended=1`). Post-update notification severity is **Fix first**; Deliverables help copy explains the normal rebuild step.

2026-07-17 11:40 - Operator IA: rename **Content → Brands** to **Content → Branding** (tab label, cross-links, docs). **Brand** stays the saved identity document; **Brand assets** in Files is unchanged. Internal `cntab=themes` URL param kept for compatibility.

2026-07-16 23:55 - Player desktop grid: mediaplayer column caps at 800px (`minmax(0, 800px) 1fr`) so `#content-container` takes leftover width. Branding editor: Shell media slots and Brand assets assignable pool are sibling sections; Live preview drops the asset gallery and shows shell chrome (logo + still backdrop) only. Docs: special-path resize remains interim; Visual pool + roles is the destination; Still is a filter, not a top-level folder name.

2026-07-16 21:55 - Optimize shell media for first paint: resize brand logo to 180px tall PNG (keep alpha) and brand background to 1080px tall (JPG switch when alpha-free), plus clamp `#mediaplayer` to max-width 800px.

2026-07-16 21:45 - Player page hydrate: mark shells `loading` synchronously so concurrent after-load callers cannot double-fetch Bio/Gallery HTML.

2026-07-16 21:40 - Player Bio/Gallery (and other page tabs): empty shells in first HTML; hydrate after `window.load` (idle) via `get-player-page.php`. Active default page tab hydrates on DOMContentLoaded; opening a page tab early still fetches immediately. Gallery videos stay poster-only until lightbox.

2026-07-16 21:30 - HAR follow-up: page-gallery videos no longer set `src` until lightbox (poster + `data-src` only); shell background `<source>` is omitted in Still mode so hidden living bg video is not fetched on first paint.

2026-07-16 21:25 - Player load contention: thumbs were tiny but stalled ~10–13s behind eager audio/video downloads on the single-threaded PHP server. Audio now preloads metadata only until Play; living-cover video attaches on play; next-track audio preload waits until playing; service worker skips all `/media/` (cache bump v6).

2026-07-16 21:20 - Living cover returns to play-gated behavior: still cover when idle/paused; living loop only while audio is actively playing (keeps canplay/poster hardening).

2026-07-16 21:05 - Living cover materialize: fill empty master-tag `living_cover` from asset-registry display (fixes Retroscopy #09). Image delivery now writes 720px `optimal/` + 100px `thumb/`; player playlist rows and cover-flow use thumbs with lazy-load; main cover stays on optimal.

2026-07-16 20:40 - Living cover playback hardened: preload/auto + canplay retry, keep still underneath until video frames paint, strip stuck poster, force-hide still cover via CSS. Still independent of shell Still/Living background.

2026-07-16 20:25 - Living cover always shows when assigned and delivered (including idle/paused); independent of player shell Still/Living background. Video still pauses in background tabs; reduced-motion stays still.

2026-07-16 19:30 - Playlist cover flow / buttons: center when the row fits; left-align with a horizontal scrollbar when it overflows on smaller viewports.

2026-07-16 19:25 - Playlist selector cover flow / buttons: center-align the picker row.

2026-07-16 19:22 - Playlist selector hide: `[hidden]` now wins over `.playlist-selector { display: flex }`, so the picker no longer stays visible on Lyrics/Bio/other tabs.

2026-07-16 19:20 - Playlist cover flow: poster thumbnails only (no title labels). Playlist selector is shown only while the Playlists tab is active.

2026-07-16 19:15 - Content → Player: playlist selector style (`player.playlist_selector`) — Dropdown (default), Buttons with titles, or Cover flow (active 100px / others 70px posters). Catalog entries now include cover URLs for the cover-flow UI.

2026-07-16 19:05 - Player content tab label becomes **Playlists** (and the in-tab selector label matches) when more than one playlist is available to the listener.

2026-07-16 19:00 - Content → Player: Still / Living shell background switch (`player.shell_background`). Operators choose the player backdrop; login keeps adaptive auto behavior. Assign media under Brands as before.

2026-07-16 18:50 - Brand duplicate (including setup “Your own brand”) now physically clones shell media into Brand assets owned by the new brand (`media/special/{brand-id}_logo` etc.), so operators can delete or replace them without touching the source/system brand.

2026-07-16 18:40 - Living shell media: Brands assignable pool now includes Visual videos (and Visual stills for poster/still background). Files → Brand assets Living empty-state explains demo hide + Visual alternative. Kind-aware bundled demo hide + broader Brand assets upload accept remain.

2026-07-16 18:35 - Brand assets Living filter: bundled living demos (e.g. `bandPromo_background.mp4`) stay visible until the operator uploads their own living file — uploading a still/logo no longer hides all demo shell media. Brand assets upload accept now includes mov/webm/flac for living backgrounds and shell audio.

2026-07-16 18:25 - Brands Shell media: assign by drag-and-drop (or click) from an in-editor Brand assets pool — no filename pickers. Background slots use Still / Living labels; Files → Brand assets type chips match.

2026-07-16 18:15 - Shell media moved into Content → Brands: logo, poster/share cover, backgrounds, and welcome/logged-in audio edit on the brand document; active-brand save syncs into config (poster also writes `release.theme.cover` / `media.cover`). Settings → Theme tab removed; `?tab=settings&ctab=theme` redirects to Brands. Sharing share-image picker points operators to Brands.

2026-07-16 18:00 - Brand tokens on the player: always resolve live `brand_styles` from brand documents (no longer overwrite live CSS with stale Publish snapshots). Saving a brand also refreshes playlist brand_styles snippets.

2026-07-16 17:55 - Brands Colors: hex is a full readable text field (not clipped on the swatch); color square sits beside it; wider chip grid.

2026-07-16 17:50 - Brands editor: let the preview (last) column grow freely — remove the inner max-height scrollbar so the page scrolls instead.

2026-07-16 17:45 - Shell background image/video now apply on both login and player (shared `shell-background.js`); same speed/reduced-motion rules as login. Brand Colors keep hex as the primary input.

2026-07-16 17:35 - Brand Colors editor: hex text is the primary color input (picker strip remains on the right); live contrast-aware swatch fill.

2026-07-16 17:25 - Brand Colors editor: show hex codes overlaid on each color swatch (contrast-aware text; updates live while picking).

2026-07-16 16:56 - Files → Brand assets: Visual-like thumbnail manager (type chips including audio, usage filters, grid/list, shared drilldown). `list-media.php?target=special` now attaches theme/config `reference_info` so In use / Orphans is meaningful; storage stays `media/special/`.

2026-07-16 14:55 - Orphan detection: also scan page picture blocks, release posters/press photos, and playlist posters before labeling Visual pool files as orphans (stem-aware match for optimal vs original names).

2026-07-16 12:50 - Deliverables resilience: run cover JPEG conversion in a child process so a Pillow segfault on one corrupt image (exit -11) cannot abort the whole `deliverables-media` stage; fall back to copying the source cover when conversion crashes.

2026-07-16 12:40 - Player publish fix: `bandpromo_playlist_materialize_for_player()` again runs `materialize_entries` (Python tag/sidecar read) so covers, lyrics, and descriptions are written into published playlist payloads. Also refresh sparse registry display before publish fallback.

2026-07-16 12:30 - Host build fix: `makePlaylists.py` now resolves PHP via `php_cli.resolve_php_cli()` (Plesk hosts have no bare `php` on PATH). Also restore Python 3.6-safe `subprocess` args in `optimizeVideo.py` poster extraction (`universal_newlines` instead of `text=`).

2026-07-16 12:25 - Site update: "Check again" now force-refreshes the GitHub package check instead of reusing the 15-minute notifications cache, so freshly published tester builds appear immediately.

2026-07-16 12:15 - Player load failure UI: replace developer-only "PHP dev server" copy with a public-facing "music isn't ready yet" message. Operators still see a short Deliverables/rebuild hint under the public text.

2026-07-16 12:00 - Hotfix: restore Python 3.6 host compatibility in `makePlaylists.py` player-payload publish (`capture_output` → `stdout`/`stderr` pipes + `universal_newlines`); fail the build when playlist publish fails instead of reporting success.

2026-07-16 10:10 - Player auto-next timing: stop cutting tails by removing the old expected-duration `-0.5s` cutoff path, rely on precise media duration + native `ended`, and skip transition animation for auto-next handoff. Also warm the next track with `<link rel="preload" as="audio">` for faster start.

2026-07-16 00:25 - Docs audit close-out: PLATFORM-MODEL / MEDIA-HANDLING / ROADMAP / TODO / FEATURES / LEGACY-AUDIT aligned with shipped Files → Visual operator UX and Files → Brand assets rename; Phases 0b–2 remain deferred; note this checkpoint is push-only (no Site-update release package).

2026-07-16 00:20 - Files tab: rename Theme → Brand assets (operator label for legacy `media/special/` intake; internal target unchanged).

2026-07-16 00:15 - Content Catalog + Playlists date fields: shared form ISO date control (text + calendar button) matching track-editor shell; Playlists no longer uses a decorative-only calendar icon.

2026-07-16 00:05 - Files → Visual: muted looping video preview on hover/focus in pool tiles and the asset drilldown (respects prefers-reduced-motion; preview MP4 loads lazily on first hover).

2026-07-15 23:55 - Files → Visual UI: thumbnail-first grid (list toggle), clickable Image/Video filter chips, no filenames in the pool; thumbnail opens a drilldown with preview + usage/details; preview eye icon removed from Visual rows.

2026-07-15 23:45 - Files → Visual operator pool: collapse Illustrations/Photos/Video into one Files tab with type/usage/role filters; `list-media.php?target=visual` merges legacy intake buckets; uploads/deletes still target `img`/`photo`/`video` on disk; Theme/`special` stays separate; old `fpanel=` URLs redirect. Docs mark Phase 3 operator UX shipped; Phases 0b–2 remain.

2026-07-15 23:30 - Pool orphan detection: treat asset-registry track covers and living covers as live references immediately after track-editor save (no longer dependent on stale published playlist payloads); PHP playlist track rebuild now carries cover/living_cover from registry display.

2026-07-15 23:20 - Media picker: drop tile hover scale (was distorting video thumbnails in the living-cover picker); hover is border/shadow only.

2026-07-15 23:15 - Track editor: living-cover picker updates preview/form state immediately (uses delivery preview URL when available); release date uses real calendar picker again and keeps YYYY year-only values.

2026-07-15 23:05 - Files index: Admin Files GET reads `media-library-state.json` → `files` only (size/mtime/format/audio_master/pool_ready/video_meta) — no DirectoryIterator or filesize probes; write-back on upload/delete/delivery/Publish; inventory counts use the same index.

2026-07-15 22:55 - Track editor: store date/living_cover/lyrics and related editor fields in `assets[].display` on save; load them from the registry on open (fixes empty date + living cover lost after save); accept YYYY or YYYY-MM-DD in the date field; preview .mov living covers from original.

2026-07-15 22:45 - Registry-first lookups: admin GET paths (playlist/release preview, Files audio list, track detail) read `assets[].display` / stored docs only — no `playlistTrackEntries.py` or `audioMasterMetadata.py` inspect on load; playlist reorder and tag save update registry + clear player payloads without sync `makePlaylists`/delivery; Publish + delivery jobs write `assets[].delivery` and `data/delivery/inventory-snapshot.json` for Deliverables/notifications.

2026-07-15 22:20 - Static player playlists: Publish now writes full player-ready `tracks`, `brand_styles`, and `delivery_summary` into `data/playlists/{id}.json`; player endpoints only read that payload (no Python/materialize on request); reorder/edit clears stale payload until next Publish; operators bypass embargo client-side via stored `embargoed` flag.

2026-07-15 21:15 - Deliverables inventory chips: Catalog tracks count unique release members from live release documents (not stale registry track_count); Brands chip shows custom brands only.

2026-07-15 21:05 - Admin load speed: Notifications remain read-only (no catalog repair/materialize); list-media GET never materializes audio or auto-queues video; publish inventory only when Deliverables asks; default notifications scope is lite; Settings config PHP loads only on Settings tab.

2026-07-15 20:55 - Welcome checklist is one-shot: after core setup latches, Dashboard never rebuilds the install checklist (empty archival state + light “what next” tips only); Notifications/other tabs read the latch file instead of rescanning.

2026-07-15 20:50 - Welcome setup no longer reopens when a later track awaits delivery: latch core setup after install + starter + successful full build; delivery readiness becomes nonblocking live ops afterward.

2026-07-15 20:45 - Notifications hygiene: keep Welcome setup checklist on Welcome only; stop poll-time video auto-queue; throttle catalog repair scans; preserve cached welcome on lite polls; slow background prep polling to 8s; clearer “your track/video is preparing” copy.

2026-07-15 20:05 - Force-stop stuck video delivery loops that block Site update: require posters for delivery success, pause incomplete auto-retries, operator Stop retrying in Notifications, auto-clear running video jobs before package install, and invalidate stale package-update cache when VERSION already moved on.

2026-07-15 19:45 - Portability strategy: lock release package export/import as third operator service (masters + tags + linked visuals); document ambassador/demo handoff and optional paid release-prep services in PORTABILITY.md, MARKETING-STRATEGY.md, ROADMAP.md, PLATFORM-MODEL.md, FEATURES.md, and TODO.md.

2026-07-15 19:28 - Player living cover: show still cover when idle or paused; loop living cover only while audio is actively playing.

2026-07-15 19:22 - Track editor polish: cleaner modal header (drop generic subtitle), center-aligned cover pair, shared living-cover status row, compact centered metadata chips, single-line description helpers.

2026-07-15 19:15 - Track editor UI: pair still + living cover previews at top with in-preview edit controls; compact metadata chips; hide living-cover filename row.

2026-07-15 19:05 - Living cover: operator assigns looping video in track editor; store `BANDPROMO_LIVING_COVER` in master ID3 TXXX / FLAC Vorbis (video original filename); read through playlist materialization; remove stem-guessing linker; add `living-cover-helpers.php` and track editor picker UI.

2026-07-15 18:45 - Animated track covers: resolve delivery MP4 by stem or manifest link, expose `animated_cover` in player playlist payload, loop muted video on main flip-card cover with static reflection, reduced-motion and visibility pause, Files → Video admin hint, and PLATFORM-MODEL/TODO policy.

2026-07-15 18:25 - Player Markdown: add shared PHP/JS renderers, render lyrics and playlist track descriptions in the player, admin Markdown hints, OG/share plain-text strip helper, and player-markdown styles.

2026-07-15 18:20 - Lock player Markdown policy in PLATFORM-MODEL.md (storage, render-at-output, master-tag unchanged, field scope) and add Player Markdown implementation slice to TODO.md (closed-beta feedback).

2026-07-15 15:10 - Naming hygiene: move legacy `gallery.template.json` to `templates/legacy/gallery-flat-array.json`; rename `primary.release.template.json` to `default.release.template.json`; remove orphan demo gallery template; fix default release registry (`system` flag, display title); align delete-release copy with behavior; update template-integrity CI and docs.

2026-07-15 15:00 - Add **Mental model (read this first)** section to PLATFORM-MODEL.md: three actors (platform/operator/user), four layers, default slot vs demo vs operator catalog, provenance, ownership cheat sheet, and vocabulary traps.

2026-07-15 14:20 - Legacy/fallback purge (post build 332 fleet): remove silent gallery.json runtime fallback and template seeding; drop play/playlist-validation.json fallback reads; centralize validation report helpers; update build stage label; add docs/LEGACY-AUDIT.md snapshot.

2026-07-15 14:10 - Auto-queue background video delivery when originals are missing posters or optimal MP4s; Files → Video and notifications show in-progress status without operator action.

2026-07-15 14:00 - Files → Video admin previews: use delivery MP4 + poster thumbnails instead of inline original MOV sources; show delivery-pending placeholder when neither exists yet.

2026-07-15 13:50 - Admin performance + legacy cleanup (build 332 fleet): remove undefined playlist legacy sync from content autofix and dead legacy helper functions; slim operator notifications with lite/full scopes and cached package manifest checks; gate admin JS fetches and PHP work by active tab; fix list-media per-request catalog/reference rescans and stop reconcile side effects on file-list GET.

2026-07-13 23:30 - Checkpoint v0.8.7 build 332: analytics SQLite rollups, retention, batch ingest, and Log export.

2026-07-13 22:45 - Analytics SQLite tail: daily rollups (user/track/device/totals), 90-day raw retention maintainer, player warm-event batching + ingest rate limit, Analytics Log export (JSONL/CSV).

2026-07-13 22:35 - Planning: add v0.8 exit gate after analytics tail + Visual pool — sync 3 remote beta sites to latest build, then full legacy/fallback/hack codebase audit (TODO + ROADMAP).

2026-07-13 22:25 - Docs sync before build 331 checkpoint: ROADMAP/TODO/FEATURES reflect shipped backup/export, Brand core, playlist.json removal; clarify remaining Visual pool vs analytics tail.

2026-07-13 21:27 - Fix release editor date picker: use shared ISO date field wrapper (native calendar button) instead of inert icon-only shell.

2026-07-13 21:39 - Remove legacy play/playlist.json build artifact for good: player and admin now rely on playlist documents and runtime materialization; optimizeMedia cover linkage reads media library state.

2026-07-13 22:01 - Move playlist validation report into data: `data/validation/playlist-validation.json` (cached operator diagnostics, regenerated by playlist-scan) with backward-compatible fallback reads.

2026-07-13 22:10 - Roadmap: add v1.0 goal for garbage collection of derived helper artifacts (cached validation reports, etc.).

2026-07-13 20:35 - Fix media picker close/preview bugs: pin header close above scrolling grid, disable invisible preview hit targets, include hidden bundled assets in picker lists, close preview when dismissing picker, Escape closes preview before picker.

2026-07-13 20:18 - Fix track cover image picker: ignore audio pool release filter, stack picker above track editor, refresh cover preview on selection.

2026-07-13 20:05 - Fix player track descriptions for MP3 masters: makePlaylists reads ID3 COMM frames (not only Vorbis COMMENT).

2026-07-13 20:00 - Fix audio pool listing showing bare episode numbers (#8): stop over-stripping serial release titles and refresh stale display cache on list.

2026-07-13 19:48 - Fix player descriptions: stop applying release short description to every track (per-track description lives in master COMMENT tags only).

2026-07-13 19:40 - Release save: refresh playlist scan after release settings save (album/title/date tag sync).

2026-07-13 19:18 - Deliverables: remove redundant intro paragraph from delivery status card (inventory panel carries the message).

2026-07-13 19:15 - Deliverables delivery status: rich site inventory tiles (releases, playlists, tracks, media, galleries, pages, brands) with encouraging headline and stream-ready ring.

2026-07-13 19:05 - Rename System → Publish to Deliverables: status-first page, auto catalog repair in background, hide Repair catalog button, rebuild-all reassurance copy.

2026-07-13 18:45 - Welcome flow: auto-duplicate default brand to "Your own brand"; reorder setup checklist by install importance; fix false-positive operator media detection; suggest upload, FAQ, Pages, and backup import after core setup.

2026-07-13 18:30 - Fix bootstrap install package discovery: resolve newest GitHub release tag (including beta prereleases) instead of GitHub `releases/latest` stable-only URL.

2026-07-13 18:20 - Fix fresh-install setup build: ship bundled demo FLACs in the default theme package (track `media/audio/original/bandPromo_*.flac`).

2026-07-13 18:15 - Fix bootstrap.php HTTP 500 on fresh install: environment checks are self-contained (no biblioteca/ require before package download).

2026-07-13 18:05 - Backup & export: Create and Import panels side-by-side on desktop.

2026-07-13 18:00 - Backup import: upload ZIP, inspect manifest, restore or cross-site migrate with component picker, async import jobs, optional site URL repair.

2026-07-13 17:45 - Remove developer Activity log package card; listener/audit SQLite is covered by the Data backup component.

2026-07-13 17:40 - Backup builder UI: compact dark-theme checkbox rows (fix light-on-light contrast); 2-column sub-grid for components.

2026-07-13 17:30 - Backup & export: single Create backup panel with Full | platform | data | media | logs checkboxes (Full selects all); component-driven archive jobs replace separate full/data cards.

2026-07-13 17:10 - Fix track cover replacement: save sidecar and embedded artwork on the canonical master filename (not the original upload name); refresh release/playlist pool titles from live master tags when cached display data is stale.

2026-07-13 16:25 - Fix empty playlists: reject saving a playlist with no tracks; hide trackless playlists from the player catalog, default playlist resolution, and direct /play URLs.

2026-07-13 16:10 - Fix release editor available pool: stop auto-assigning uploaded audio to the primary release on publish/reconcile; restore document-membership filtering for Available content; repair stale catalog release_id values during catalog repair.

2026-07-13 15:00 - Fix Windows video upload: launch background delivery via cmd.exe instead of opening the .bat with Start-Process (avoids "choose app" dialog).

2026-07-13 14:05 - Backup & export UI: three action panels in one desktop row; compact copy and stacked controls per panel.

2026-07-13 11:15 - Backup & export: split create vs download; archives build in `backups/` with queued/ready status, polling, and delete.

2026-07-12 19:50 - System → Backup & export: operator full site backup ZIP, data export ZIP (config + data/), developer activity log package; aligns with PORTABILITY.md.

2026-07-12 19:40 - Checkpoint v0.8.6 build 325: developer activity log export/import, SQLite 3.8.0+ preflight, analytics filter bar UX.

2026-07-12 19:35 - Developer-only System → Activity logs: export/import listener activity and audit events as a JSON package (merge or replace) for copying data between installs.

2026-07-12 19:20 - Analytics filter bar: active period chip styling; ISO date inputs capped at 10 chars with in-field calendar icon.

2026-07-12 19:10 - Bootstrap/setup/Site update preflight: require bundled SQLite 3.8.0+ (via `SELECT sqlite_version()`), declared in `release-manifest.json` requirements.

2026-07-12 19:00 - Hotfix v0.8.6 build 324: replace SQLite UPSERT (`ON CONFLICT`) with portable UPDATE/INSERT for older bundled SQLite on shared hosts.

2026-07-12 18:45 - Hotfix v0.8.6 build 323: admin survives SQLite migration failures; Site update validates published `release-manifest.json` requirements before apply; install UI no longer resets during background refresh. Recovery for broken admin: `git pull` then reload.

2026-07-12 18:15 - Hotfix: admin no longer fatals when SQLite activity migration fails on first load after upgrade; legacy import records failure instead of throwing. Site update install UI ignores background refresh while installing (fixes first-click appears to do nothing).

2026-07-12 17:05 - Follow-up cleanup: removed unused `vendor/tinymce`; self-hosted Chart.js 4.4.1 under `vendor/chart.js`; refreshed SECURITY-AUDIT for SQLite activity store.

2026-07-12 16:55 - Legal/docs audit: refreshed THIRD-PARTY-NOTICES (SQLite, GitHub Releases, jsDelivr, HTML Purifier paths; TinyMCE marked vendored-not-loaded); wrote TRADEMARKS.md; linked license notice to third-party/trademark docs.

2026-07-12 16:45 - Setup/bootstrap preflight: require PHP `pdo_sqlite`; docs updated for SQLite activity store (`data/analytics/events.sqlite`).

2026-07-12 16:35 - Activity store migration hardening: strip UTF-8 BOM from legacy JSONL lines; fail import when a file has content but no parseable rows (prevents silent delete).

2026-07-12 16:30 - Activity log storage: SQLite at `data/analytics/events.sqlite`; one-shot import of legacy daily JSONL logs with delete; listener/audit ingest and analytics reads wired through `activity-store.php`.

2026-07-12 16:15 - Analytics migration policy: one-shot JSONL import into SQLite on upgrade, then delete legacy log files (no dual-write).

2026-07-12 16:00 - Lock analytics storage to v0.8: ANALYTICS-STORAGE.md (ActivityStore, SQLite events, rollups, legacy log migration); TODO priority 5; v0.9 limited to offline sync on top of the new store.

2026-07-12 15:45 - UTC time foundation: listener/audit logs store ISO UTC; analytics buckets on UTC; Settings → Basics adds admin UTC/local display toggle with timezone; release/playlist date hints document UTC calendar-day gates.

2026-07-12 15:25 - Analytics filter bar: period presets left of date pickers, order reversed (All → Day).

2026-07-12 15:20 - Analytics and audit filter bars: compact single-row layout; ISO `YYYY-MM-DD` text display with calendar picker button; merged activity filter into analytics log bar.

2026-07-12 15:05 - Checkpoint v0.8.6 build 321: admin read-performance caches and PHP playlist preview; compact operator dashboard (site update strip + icon quick actions); brands editor narrative fields; system shell vs brand overlay docs.

2026-07-12 15:00 - Dashboard layout: Site update above Quick actions; compact icon tiles for quick actions with shorter labels.

2026-07-12 14:55 - Dashboard Site update: single status strip with inline actions; hide PHP/hosting preflight checks and release-note lists from operators.

2026-07-12 14:50 - Dashboard: compact Site update panel — quiet reassurance when up to date; full card + Install button only when an update is available; Quick actions moved above update strip.

2026-07-11 18:12 - Dev server: default bind to 127.0.0.1 (Windows IPv6 localhost-only bind broke 127.0.0.1:8000 in browser).

2026-07-11 18:15 - Playlist editor: sync settings panel (including cover preview) when preview loads, matching release editor behavior.

2026-07-11 18:10 - Hotfix admin performance caches: return runtime cache buckets by reference (fixes PHP notice output breaking pages); reentrant-safe ensure_seeded guards for release and playlist seeding.

2026-07-11 18:05 - Admin UI performance: request-level caches for asset/release registries and release documents; asset→release membership index replaces per-track release scans; playlist preview uses PHP asset registry (no Python cold-start); trim redundant player enrichment on release preview.

2026-07-11 17:45 - Planning docs: lock system shell vs brand overlay contract (platform-owned layout + dark baseline; brand replaces enumerated slots only; shell asset runtime deferred to v1). PLATFORM-MODEL.md, ROADMAP.md, TODO.md.

2026-07-11 17:30 - Brands editor: mood/keywords/tone narrative fields in Content → Brands; welcome checklist nudge to duplicate default brand; fix save-theme registry write for `brands` key.

2026-07-11 15:40 - Checkpoint v0.8.6 build 320: v0.8 management slice — Brand storage (`data/brands/`), release `brand_id` + editor picker, per-release player branding, brand-aware CSS alpha tokens, login brand tokens, OG/Twitter removed from player+login until v0.9; page container metadata fields; planning docs aligned.

2026-07-11 15:30 - Login page: remove OG/Twitter meta until v0.9; wire active brand CSS tokens through login.css (primary/secondary/alpha vars, typography inherit); theme-color from brand background.

2026-07-11 15:25 - Player: remove Open Graph and Twitter Card meta tags until v0.9 anonymous share entry; keep browser/PWA head (title, favicons, manifest). `share-tools.php` kept for v0.9 public routes.

2026-07-11 15:15 - Player CSS: replace hardcoded cyan rgba accents with `--primary-a**` / `--secondary-a**` variables derived from brand tokens via `color-mix()`; inject derived alpha vars in brand CSS output.

2026-07-11 15:05 - Player applies per-release brand tokens: tracks carry resolved `brand_id`, playlist API returns `brand_styles`, player swaps CSS variables on playlist load and track change; initial page render uses the active track's brand.

2026-07-11 15:00 - v0.8 management slice start: brand storage on `data/brands/` with themes migration and `bandpromo-default` seed; `brand-storage.php`; release `brand_id` + editor picker; page container metadata fields in document storage + editor (OG runtime deferred to v0.9); admin Brands labeling.

2026-07-11 14:30 - FEATURES.md: note Brand-replaces-Theme direction and v0.8 management slice (Visual pool, role tags, content AI wizards).

2026-07-11 14:30 - Planning docs: v0.8 management machine (Brand replaces Theme, Visual pool + explicit role tags, release brand_id many-to-one, content AI wizards); v2+ marketing machine; upload tagging policy; special/Theme tab legacy migration. Updated PLATFORM-MODEL.md, MEDIA-HANDLING.md, ROADMAP.md, TODO.md.

2026-07-10 14:20 - Checkpoint v0.8.5 build 319: demo catalog visibility toggle, Site update dev-host reliability and ahead-of-published state, dashboard panel order; planning docs synced to v0.8.5 hotfix slice.

2026-07-10 14:05 - Site update shows a green ahead-of-published state when the local VERSION is newer than the latest GitHub release package, with the published version called out as a developer publish reminder.

2026-07-10 13:35 - Site update HTTPS readiness now requires curl or openssl (not allow_url_fopen alone); manifest fetch errors name missing PHP extensions.

2026-07-10 13:30 - Site update localhost checks: writable probe via log/data instead of is_writable on Google Drive paths; ZipArchive missing on local dev is advisory and no longer blocks readiness.

2026-07-10 13:24 - Remove redundant Dashboard title card; the tab label is enough once setup is complete.

2026-07-10 13:22 - Dashboard panel order: demo catalog nudge, Site update, then Quick actions (setup Welcome checklist layout unchanged).

2026-07-10 13:15 - Add install-level demo catalog visibility: Settings toggle plus Welcome nudge hide the shipped bandPromo demo release, playlist, gallery, and bundled demo media from the player and content editors while publish builds continue to process demo files on disk.

2026-07-10 13:00 - Hotfix v0.8.5 build 318: create-playlist-from-release copies release metadata; future playlists hidden from normal users in the player.

2026-07-10 12:50 - Player playlist catalog and API now hide future-dated playlists from normal users; operators and developers still see and can preview them. Create-playlist-from-release copies the release publish date (including future dates) after flushing unsaved release settings.

2026-07-10 12:30 - Hotfix v0.8.5 build 317: admin date fields use ISO text inputs (YYYY-MM-DD); optimal streaming now requires publish-built MP3s (no silent FLAC/WAV fallback for catalog audio); operators see delivery-pending locks and a player publish notice; welcome checklist tracks missing streaming delivery; legacy player playlist.json fetch path removed.

2026-07-10 12:00 - Hotfix v0.8.5 build 316: player playlist picker uses a dark surface so options are readable on light themes; demo and unpublished tracks stream from original audio when delivery MP3s are missing; playlist materialization falls back to PHP when Python is unavailable on the host.

2026-07-09 19:00 - Hotfix v0.8.5 build 315: player cover art falls back to original images before publish optimizes them; default playlist prefers operator playlists over the demo; empty Primary Release is hidden from the catalog when a real release exists; new playlists use today as publish date when the release date is still in the future.

2026-07-09 18:45 - Hotfix v0.8.5 build 314: paginate GitHub Releases API when resolving Site update packages so prerelease builds are found even when `/releases/latest` points at an older tag.

2026-07-09 18:30 - Hotfix v0.8.5 build 313: release editor now lists unassigned catalog tracks in Available content; release preview loads from the asset registry without a Python cold-start; auto-registered audio rows no longer show false yellow description/lyrics badges before the first publish build.

2026-07-09 18:15 - Hotfix v0.8.5 build 312: make `scripts/php_cli.py` compatible with Python 3.6 hosts (remove `from __future__ import annotations` and 3.7+ type-hint syntax).

2026-07-09 18:00 - Hotfix v0.8.5 build 311: PHP CLI resolution now uses exec smoke tests when open_basedir hides Plesk binaries; export `BANDPROMO_PHP_CLI` into Python publish subprocesses (catalog stage and video-delivery finish hook); deduplicate resolver in `auto-build-tasks.php`.

2026-07-09 17:30 - Hotfix v0.8.5 build 310: publish build now runs launch diagnostics (`[diag]` lines in build log) including PHP/Python proc_open and nohup smoke tests, recommends the safest launcher path, and falls back to legacy nohup→python when detached PHP cannot run on the host.

2026-07-09 16:45 - Hotfix v0.8.5 build 309: resolve Plesk/Linux PHP CLI paths for publish build launcher so background `build-runner.php` actually starts on shared hosting.

2026-07-09 16:30 - Hotfix v0.8.5 build 308: Site update now ranks published packages by monotonic build number so legacy tags like `v0.8-build-307` are offered over older `v0.8.4-build-304` releases.

2026-07-09 15:20 - Hotfix v0.8.5 build 307: Unix publish build launcher falls back to `nohup` when `proc_open` is blocked and resolves a real PHP CLI instead of php-fpm; build runner can start Python through `exec` on the same hosts.

2026-07-09 15:00 - Hotfix v0.8.5 build 306: Site update now parses `major.minor.session build` versions and `v0.8.5-build-305` release tags; publish legacy-tag package `v0.8-build-306` so installs still on build 302 can receive the fix.

2026-07-09 14:40 - Checkpoint v0.8.5 package refresh: publish a new tester update package after build 302 was still the latest offered package.

2026-07-02 10:00 - Session start/end shortcuts: `session-start.ps1` now pulls, bumps session number, starts dev server, and summarizes backlog; `session-end.ps1` validates docs/state, bumps build, commits, builds package, and can publish; tracked `.vscode/tasks.json` plus `/bandpromo-session-end`; docs updated for `v<major>.<minor>.<session> build <number>` versioning.

2026-07-01 51:00 - Checkpoint v0.8.4 build 303: VERSION format `major.minor.session build`; legacy cleanup Phase D, initial site seed rename, gallery `bandpromo-demo` migration.

2026-07-01 50:30 - Rename system gallery id `main` to `bandpromo-demo` with legacy migration; align demo naming across playlists, releases, and galleries.

2026-07-01 50:00 - Rename setup compose to initial site seed (`initialSiteSeed.py`, `data/initial-site-seed.json`).

2026-07-01 49:30 - Legacy cleanup Phase D: container-first initial site seed and gallery saves, remove delete-media gallery.json scrub, rename content pool CSS classes.

2026-07-01 49:00 - Checkpoint v0.8 build 302: legacy cleanup Phase C — delete-media container paths, track cover clear helper, docs on publish artifacts.

2026-07-01 48:30 - Legacy cleanup Phase C: remove delete-media play/playlist.json scrubbing, clear track covers via master helpers, drop dead optimizeMedia load_orig_config, sync docs on container-first reads.

2026-07-01 48:00 - Checkpoint v0.8 build 301: legacy cleanup Phase B — container-first admin reads, no save-time play/playlist.json sync.

2026-07-01 47:30 - Legacy cleanup Phase B: stop syncing play/playlist.json on admin save; player OG tags and media helpers read playlist containers.

2026-07-01 47:00 - Checkpoint v0.8 build 300: legacy cleanup Phase A — registry-driven makePlaylists, container-aware delete-media, playlist preview by playlist id, release Enjoy-here slug URLs.

2026-07-01 46:30 - Legacy cleanup Phase A: registry-driven makePlaylists.py, playlist container delete-media updates, playlistPreview respects selected playlist id, release Enjoy-here URLs use playlist slug.

2026-07-01 46:00 - Checkpoint v0.8 build 299: multi-playlist/release editor slice — playlist metadata and slug URLs, release editor pool/create-from-release, registry-backed playlist pool, remove legacy `main` playlist, player slug track links.

2026-07-01 45:30 - Player URLs use playlist slug (not internal id) and shorten track links to `/play/{playlist-slug}/{track-slug}`; legacy 3-segment release paths still resolve. Create-from-release no longer copies release id as playlist id.

2026-07-01 45:00 - Playlist editor metadata: cover panel, slug (public `/play/{slug}` URL), date picker, description and short description; move available track pool under active playlist (release drill-down layout).

2026-07-01 44:30 - Release editor: remove redundant cover action buttons; add Create playlist from release (new playlist, release track order, opens playlist editor).

2026-07-01 44:00 - Playlist editor available pool: show track versions in titles and sort by release document track order when filtering by release.

2026-07-01 43:30 - Playlist editor pool: merge asset registry like release editor (canonical pool + release-document filter); registry-backed tracks draggable in admin; demo delivery prep uses demo release masters.

2026-07-01 43:00 - Remove legacy `main` playlist: seed `bandpromo-demo` as the system playlist, drop playlist migration from `play/playlist.json`/`playlist-order.json`, migrate existing `main` into demo when empty, default player/admin URLs use active playlist id.

2026-07-01 42:30 - Demo playlist content lives in `main` (remove separate `bandpromo-demo` playlist); migrate legacy demo playlist into empty main on seed.

2026-07-01 42:15 - Playlist editor: drop playlist id from pool meta; seed system-owned demo playlist on clean install; player default playlist = latest publish date not under embargo; release drill-down filter uses release document membership (not asset release_id).

2026-07-01 42:00 - Playlist editor pool: match release meta line (`7 tracks released 2026-07-01 as main`); ownership `system` (main) vs `operator`; track count from documents; track # left of drag handle.

2026-07-01 41:45 - Catalog release pool meta: `7 tracks released 2026-11-20 as WP-2026` (catalog id bold at end).

2026-07-01 41:30 - Catalog release pool row meta: bold catalog id, then "7 tracks released on 2026-11-20" (no dot separators).

2026-07-01 41:15 - Catalog release track list: track number left of drag handle for more title space.

2026-07-01 41:00 - Fix release cover preview after save: resolve `poster_asset_id` (ast_* or media path) to `poster_preview_url` on the server so autosave no longer clears the cover image.

2026-07-01 40:45 - Media picker grid: drop filename labels under tiles; 2% scale-up on hover (filename stays in title/aria-label).

2026-07-01 40:30 - Media picker (release cover etc.): thumbnail grid instead of tall file rows; click a tile to select, hover for preview.

2026-07-01 40:15 - Fix release cover media picker: remove stray `syncBundledToggleUi()` call that threw before tabs/files loaded.

2026-07-01 40:00 - Admin UI read path uses asset registry `display` cache (title, version, artist, album, duration) instead of per-row Python inspect; sync on audio save and Repair catalog backfill.

2026-07-01 39:30 - Fix track title polish when song title matches release name (e.g. Winter Party [Original Club Mix] on release Winter Party).

2026-07-01 39:15 - Fix Catalog release editor version display: preserve `version` in cloneTrack and always read title/version from master tags when enriching tracks.

2026-07-01 39:00 - Catalog release editor: split track title/version from master tags (same rules as Files → Audio); inspect messy titles and show `Title [Version]` with artist on the meta line.

2026-07-01 38:45 - Fix Files → Audio orphan detection: require release document track membership (ignore asset release_id defaulting to primary).

2026-07-01 38:30 - Files → Audio: show `{release date} on {release name}` after metadata chips; mark unassigned or undated tracks as Orphan; add Orphaned files release filter.

2026-07-01 38:15 - Files → Audio: move bold release date from row title to after the C/A/T/R/D/L metadata chips.

2026-07-01 38:00 - Files → Audio row label includes version in brackets: `{date} {artist} - {title} [{version}] ({duration})`.

2026-07-01 37:45 - Files → Audio row label: use base title only (strip `[version]` and DJ key/BPM suffixes); inspect master tags when pool title is messy so rows match quick-edit chips.

2026-07-01 37:30 - Files → Audio row label: bold release date without colon (`2026-07-01 7rym - Title (3:42)`); fill missing artist/duration from master tag inspect when catalog pool is stale.

2026-07-01 37:15 - Files → Audio row label: single line `{release date}: {artist} - {title} ({duration})` from catalog metadata (pool, asset registry, release date); no filename in the name cell.

2026-07-01 37:00 - Files → Audio list: sort tracks by release date (newest first), show title + artist per row, and drop filename subtitles from the list UI.

2026-07-01 36:30 - Catalog editor available pool: only tracks registered to the current release (excludes demo and other releases per one-track-one-release model).

2026-07-01 36:15 - Fix Catalog editor right column overlap: release active/available track lists use content height instead of flex-fill + overflow spill.

2026-07-01 36:00 - Catalog track lists: grow with content (no fixed scroll height), show title + artist + duration (drop album subtitle in release editor), normalize filename-style titles, enrich from master metadata when needed; saving release tracks or metadata syncs album/date/track-number tags on member audio files.

2026-07-01 35:15 - Catalog editor: align release date and catalog ID fields on a shared grid row; hint spans full width below.

2026-07-01 35:00 - Release `catalog_id` field: optional operator-defined catalog reference (CD001, EP002, label schemes) on release documents, Catalog editor, PATCH via `manage-release.php`; documented in `PLATFORM-MODEL.md`.

2026-07-01 34:30 - Remove confusing "default catalog" operator copy from Catalog editor (pool meta shows date and track count only; demo labeled "demo").

2026-07-01 34:00 - Catalog editor polish: fix misleading `system` pool label (only bandPromo demo is system-managed; primary shows as default catalog), sort releases by release date descending, stop auto-adding uploaded audio to release track lists (assign `release_id` only), improve ast_* master title resolution, enable release cover picker in preview mode.

2026-07-01 33:00 - Content navigation labels: Release → Catalog, Playlist → Playlists, Gallery → Galleries (URLs unchanged); aligned editor card titles, catalog back button, Publish hint, and page-editor gallery hint.

2026-07-01 32:30 - Build pipeline Phase E: reorder publish stages — deliverables before artifacts (`makePlaylists` after `optimizeMedia`/`optimizeVideo`); stage `group` metadata in manifest.

2026-07-01 32:00 - Build pipeline Phase D: registry-scoped audio delivery in `optimizeMedia.py`; playlist used only for cover linkage; `playlistAudioDelivery.py` resolves registered assets.

2026-07-01 31:30 - Checkpoint v0.8 build 298: build pipeline Phase C (catalog stage, log timestamps).

2026-07-01 31:00 - Build pipeline Phase C: catalog stage (`buildCatalog.py` / `build-catalog-helpers.php`) before deliverables; audio uploads finalize catalog masters. Build logs start with `LOG_STARTED` timestamp lines.

2026-07-01 30:30 - Build pipeline Phase B: `scripts/build-stages.json` stage manifest, structured `STAGE_START`/`STAGE_END` logging in `build.py`, and `build.php` profile/`stages[]` support via `build.meta.json`.

2026-07-01 30:00 - Checkpoint v0.8 build 296: build pipeline Phase A/F (no autofix on publish, layout seed on setup, stage-ready publish UX) and System → Publish compact actions + site-wide Publish status.

2026-07-01 29:15 - Publish tab layout: **Publish status** card first; all publish action buttons on one row.

2026-07-01 29:00 - Publish tab: **Publish status** card replaces playlist-only validation with site-wide catalog/delivery/pending checks (`publish-status-helpers.php`); Repair catalog button uses 🛠️ icon.

2026-07-01 28:30 - Publish **Repair catalog** button label (preview-first flow); removed redundant row label.

2026-07-01 28:15 - Publish actions card: compact toolbar layout with consistent `btn` styling for build, image refresh, and catalog repair.

2026-07-01 28:00 - Publish tab UX: moved Run Publish Build, Refresh Image Files, and Repair catalog into a single **Publish actions** card; System sub-tabs are navigation-only (like Content editors).

2026-07-01 27:15 - Build pipeline Phase A + F: removed `content-autofix` from publish preflight (config repair only); added Publish tab **Repair catalog** card wired to `content-autofix.php`; renamed validation UI to **Playlist validation**; removed `setupCompose.py` from `build.py` (5-step publish); added `biblioteca/run-layout-seed.php` and setup wizard post-build layout seed call.

2026-07-01 26:30 - Locked build pipeline policy: deliverables for every registered asset; prune on asset delete only; initial layout seed (setupCompose) limited to setup + explicit disaster recovery, not publish.
2026-07-01 26:00 - Added build pipeline rework section to `docs/TODO.md` (policy + phases linked to `BUILD-PIPELINE-AUDIT.md`).
2026-07-01 26:00 - Added `docs/BUILD-PIPELINE-AUDIT.md`: target build order (preflight → site shell → catalog/masters → deliverables → artifacts; compose defined as first-run layout only), current-gap analysis, and refactor phases.
2026-07-01 25:35 - Publish build launcher: shell-free `proc_open` chain (`php.exe` → `build-runner.php` → `python.exe`); no cmd/PowerShell/.bat (AV-friendly).
2026-07-01 25:20 - Publish build launcher: removed Windows `.bat` runner (detached PowerShell only); build log only appends validation after a successful run; publish hints use the same labels as the action buttons.
2026-07-01 25:00 - Dev server start script spawns detached PHP (no long-lived terminal); output goes to `log/dev-server.log`.
2026-07-01 24:45 - Dropped `router.php` dev-server workaround. Publish-build fix is launcher-only: Windows runners live in temp (never under web root); production log access stays denied via `log/.htaccess` on Apache.
2026-07-01 24:15 - Publish build launcher: Windows runner `.bat` moved to temp dir (not web-served `log/`).

2026-07-01 24:00 - Publish build: detect/clear stale `build.lock`, attach log polling when a build is already running, and return log content on duplicate-start.

2026-07-01 23:45 - Clarify playback model: player exposes playlists only; release Enjoy here defaults to `/play/main`, not release-native URLs.

2026-07-01 23:30 - Contact examples use `7rym <7rym@7rym.net>`; release Enjoy here links default to `/play/main`; admin `url`/`email` inputs match theme.

2026-07-01 23:00 - Contact handling hardened for future mail: canonical RFC 5322 normalize on save, stricter addr-spec checks, empty derive on localhost; `bandpromo_site_contact_mailbox()` for future mailto/SMTP; policy noted in `PLATFORM-MODEL.md`.

2026-07-01 22:00 - Site contact (`site.email` + `site.email_auto`): RFC 5322 validation, install/setup step 2 field, auto-suggest from author + URL, hidden `site.language=en`; shared `site-contact.php` / `site-contact.js`.

2026-07-01 21:00 - Added `site.email` to web config (Settings → Basics); Release editor prefills empty press contact from `site.author` + `site.email` in RFC 5322 `Name <email>` form.

2026-07-01 20:00 - Release cover moved to column two with audio-master-style preview/picker; explicit `window.openMediaPicker` wiring; RFC 5322 press contact example.

2026-07-01 19:00 - Release EPK layout: short description under full description, tagline full-width (160 chars), compact Enjoy here links grid, press contact hint for named email formats.

2026-07-01 18:00 - Release editor fixes: native date picker styling on release date, release cover picker (media modal moved before admin.js + delegated picker clicks), cover above description, new `short_description` field (300 chars).

2026-07-01 17:00 - Release editor UX: ISO date picker, release cover file picker (`poster_asset_id`), bandPromo-first streaming links (Bandcamp removed), social profiles imported from Sharing settings, metadata column + available tracks under active list in column two.

2026-07-01 16:00 - Release pool lock control uses 🔒/🔓 icon buttons (active/inactive) instead of labeled checkboxes.

2026-07-01 15:30 - Release lock control moved to the release pool list (per-row Lock checkbox); removed from drill-down settings. Lock still blocks unsaved track edits when toggled while editing.

2026-07-01 15:00 - Release EPK metadata: `data/releases/{id}.json` now stores `description`, `poster_asset_id`, and nested `epk` (tagline, genre, credits, press contact, streaming links, press photo asset refs); Content → Release editor fields PATCH via `manage-release.php`; schema locked in `PLATFORM-MODEL.md`.

2026-07-01 14:00 - Fix Release editor: show edit/delete actions on operator releases (only `bandpromo-demo` is system-managed), resolve track titles via audio pool aliases for `ast_{ULID}` masters, and accept original filenames on save.

2026-07-01 13:30 - Session 4 / v0.8.4: ship Release editor — Content → Release tab with pool/result UX, `manage-release.php` / `get-release-preview.php` / `save-release-tracks.php`, release CRUD + track membership in `release-storage.php`, and `biblioteca/release-editor.js`.

2026-07-01 13:00 - Added standalone `biblioteca/release-editor.js` for Content → Release: release pool with add/delete actions, playlist-style multi-select drag/drop assignment between Available content and Release tracks, PATCH-saved release settings, locked/demo preview safeguards, and shared content-save button state handling.

2026-06-16 18:00 - Checkpoint v0.8 build 295: ships v0.8.3 trust/UX (config auto-repair, Publish preflight, playlist `kind: system`, Content editor header/toolbar/delete UX, post-update copy); hotfix media delete network error; documents v0.8.4 unified Visual pool and delivery scaling plan.

2026-06-16 17:00 - Expanded v0.8.4 visual media plan: unified Visual pool (collapse Illustrations/Photos/Video), extend `ast_{ULID}` registry to all visual assets, tags + picker filters instead of folder categories; audio stays separate. Updated `MEDIA-HANDLING.md`, `PLATFORM-MODEL.md`, `TODO.md`, `ROADMAP.md`, `FEATURES.md`.

2026-06-16 16:00 - Documented v0.8.4 image delivery plan from beta feedback: stop forced JPEG deliverables and alpha flattening; audit real UI display sizes; multi-variant delivery (`thumb`, `card`, `lightbox`, `share`) sized and encoded by content need. Updated `MEDIA-HANDLING.md`, `TODO.md`, `ROADMAP.md`, and `FEATURES.md`.

2026-06-16 15:30 - Hotfix v0.8 build 295: fixed Files tab delete failing with "Network error" for all media types — `delete-media.php` redeclared `bandpromo_video_delivery_path()` already loaded via `gallery-helpers.php`, causing a PHP fatal before JSON could be returned.

2026-06-16 14:00 - v0.8.3 implementation: silent `web-config.json` structure auto-repair on admin load; Publish preflight runs config repair + content preparation before full builds; removed Dashboard content-model upgrade card; improved post-update notification copy; fixed operator playlists to `kind: system` with migration on publish; Content editor header UX parity (inline name, back right) for Playlist/Gallery/Pages/Themes; sticky richtext toolbars; page delete on pool rows.

2026-06-16 12:00 - Checkpoint v0.8.3 docs (build 293): captured closed-beta feedback after build 292 — v0.8.3 slice in `TODO.md` (operator trust, config auto-repair, Publish-integrated content preparation, playlist `kind: system` fix, Content editor UX, Release editor + EPK metadata, backup/export MVP); expanded beta-tester update/workflow guidance in `ROADMAP.md` and `INSTALL-UPDATE.md`; container presentation fields and playlist kind bug note in `PLATFORM-MODEL.md`; closed legacy HTML page import scope (all testers on JSON pages).

2026-06-15 22:00 - Added TODO follow-up: pre-publish guard to ensure shipped PHP entrypoints only require git-tracked files before release packages are published.

2026-06-15 21:00 - Hotfix v0.8 build 292: ship platform storage/API files (`theme-storage.php`, `playlist-storage.php`, `gallery-storage.php`, `asset-registry.php`, `release-storage.php`, and related endpoints) that were required by `admin.php` since build 290 but had never been committed, which caused a blank admin panel after Site update on hosted installs.

2026-06-15 18:00 - Audio delivery alignment: `makePlaylists.py` now writes `play/playlist.json` with `ast_{ULID}` master filenames from `data/playlists/main.json`, so publish regenerates `media/audio/optimal/ast_*.mp3` and prunes legacy human-name MP3s; added operator folder-tier summary to `MEDIA-HANDLING.md`. Demo git hygiene: stopped tracking `bandPromo_*` audio originals (demo ships via setup starter pack); updated `.gitignore`, `INSTALL-UPDATE.md`, and TODO/ROADMAP scheduling.

2026-06-15 17:00 - Admin IA restructure: **Settings** (Basics, Theme, Support, Sharing) replaces Config; **System** combines Publish (former Build tab) and Audit; legacy `?tab=config|build|audit` URLs redirect; welcome dashboard drops the Build quick link; notifications are the primary publish nudge (bell pulses on urgent items; Build tab pulse removed).

2026-06-15 16:15 - Theme duplicate now allocates short unique ids (`theme-copy-{hex}`) instead of appending to the source id, so copying a copy no longer collides at the 48-character id limit.

2026-06-15 16:00 - Fixed theme pool list not refreshing after deleting the currently selected theme.

2026-06-15 15:45 - Theme pool polish: locked Setup Default has no edit button, and active themes no longer use a green title (dot/meta/badge remain).

2026-06-15 15:30 - Removed the media player hover dim (`opacity: 0.9` on `#mediaplayer:hover`) on the public player page.

2026-06-15 15:15 - Removed the theme editor preview status line; success is shown by UI chips/buttons and rare errors use admin toasts.

2026-06-15 15:00 - Removed Media player / cover art size from the theme editor; player layout stays responsive via `style.css` breakpoints and themes no longer inject `--card-size`.

2026-06-15 14:45 - Fixed theme pool selection: clicking a theme now refreshes the pool highlight and uses the shared page-pool row selector.

2026-06-15 14:30 - Theme pool rows can delete non-active, unlocked themes via a confirmation modal; active themes now use green OK styling in the pool list, edit header badge, and Set active control.

2026-06-15 14:15 - Theme pool rows now carry edit and per-item duplicate actions (no header duplicate), and the save button no longer sticks on Saved after returning to the pool list.

2026-06-15 14:00 - Moved theme rename into the edit-view header: an inline editable name sits to the right of ← Themes, with compact active/locked badges and save status beside it; removed the separate theme name settings panel.

2026-06-15 13:50 - Compacted the theme name settings panel: status now sits top-right in the box and active/locked badges share the label row.

2026-06-15 13:45 - Removed the redundant theme title intro block from the theme editor live preview to reclaim vertical space.

2026-06-15 13:30 - Theme editor UX pass: rename themes from edit mode, compact color swatches, operator-friendly font/size presets, typography above media player settings, and live preview showing all page-editor text styles (H1–H3, paragraph, small, code) plus a clearer Media player section.

2026-06-15 13:00 - Content → Themes now matches the other editors: theme pool with edit button on the left, token editor in edit mode, and a live preview panel on the right that renders typography, player card, buttons, surfaces, links, palette, and brand assets with the selected theme tokens.

2026-06-15 12:45 - Unified Content editor “Add playlist/gallery/page” buttons to the compact Gallery header style by removing a `font: inherit` override on Pages/Playlist and locking shared sizing in `admin.css`.

2026-06-15 12:30 - Page editor live preview now re-renders through `preview-page-document.php`, so gallery block source and layout changes update the preview immediately without saving.

2026-06-15 12:15 - Page editor gallery block dropdown now lists all galleries from the registry (including user-created ones), not only system galleries.

2026-06-15 12:00 - Gallery editor now matches the playlist editor UX: pool list with add/edit/delete on the left, read-only preview on the right, and edit mode with gallery name settings, available media pool, drag-and-drop ordering, and save. Added `manage-gallery.php` plus create/update/delete helpers in `gallery-storage.php`.

2026-06-16 08:45 - Uncatalogued audio uploads now self-heal: opening Files or refreshing notifications auto-registers originals into the asset catalog, syncs primary release membership, and queues Build; operators only see a notification when automatic registration fails.

2026-06-16 08:15 - `optimizeMedia.py` now resolves audio delivery sources through the asset registry (same as `makePlaylists.py`), so `ast_{ULID}` masters are used for MP3 delivery instead of falling back to originals when master filenames no longer match upload stems.

2026-06-16 08:00 - Fixed Windows full-build failure in `setupCompose.py` by decoding `makePlaylists.py` subprocess output as UTF-8, added `scripts/run-local-cleanup.php` for local asset/playlist reconciliation, and cleaned this install: removed duplicate salsa FLAC asset, linked salsa playlist entries, cleared stale background tasks, and completed a full build.

2026-06-16 07:50 - Removed inaccurate publish-date helper text from the playlist settings panel in edit mode.

2026-06-16 07:45 - Fixed Content → Playlist not loading after the settings panel change: removed a duplicate `playlistDeleteCancelBtn` declaration that broke `admin.js` parsing.

2026-06-16 07:30 - Playlist edit mode now includes a compact settings panel above Available content for playlist name and publish date, with PATCH support in `manage-playlist.php` and registry/document sync in `playlist-storage.php`.

2026-06-16 07:15 - Fixed playlist delete doing nothing on Content → Playlist: the confirmation modal was only rendered on the Pages sub-tab, so it is now shared across all Content views with a confirm() fallback.

2026-06-16 07:00 - Added Welcome → Content model upgrade tool (`content-autofix.php`) to batch-migrate legacy installs: seed containers, materialize/link audio masters, rename masters to `ast_{ULID}` filenames, sync playlist `asset_id` links and release membership, refresh validation, and queue Build. Playlist delete now uses a proper confirmation modal instead of an instant delete.

2026-06-16 06:35 - Fixed playlist validation false positives when a saved title tag matches the filename stem (e.g. Salsa guacamole), taught makePlaylists to read ULID masters from the asset registry, and saved track 1 metadata on Salsa_guacamole.mp3.

2026-06-16 06:20 - Audio quick-edit chips now match real publish rules: only Artist and Title show red when missing, Release and Track use amber unless the catalog has multiple tracks (then empty Track is red), and Version/BPM/Key/Release date/Genre show amber "Optional" or "Recommended" instead of alarming red "Missing".

2026-06-16 06:00 - Fixed broken audio operator workflow for ULID masters: asset IDs now validate at the generated 20-character length (registry was silently empty), orphan ast_* masters reconcile to their original upload, duplicate master copies are pruned, Files → Audio shows friendly track titles with disk names as subtitles, mp3/flac/wav rows stay editable even while a master is pending, delete removes registry-linked masters, failed video notifications can be dismissed, and stale validation items for missing originals are filtered out.

2026-06-16 05:15 - Fixed Files → Audio edit lock for ULID-based masters: asset lookup now resolves original filenames like Salsa_guacamole.mp3 to ast_* master files, materializes missing masters for existing assets, and teaches audioMasterMetadata.py to use the asset registry.

2026-06-16 04:45 - Retuned operator notifications for current tooling: metadata issues use one Fix song info action with track-number wording that matches song tags (not playlist order), pending publish prep points to Build instead of Files, and failed video delivery explains retry via Build or re-upload.

2026-06-16 04:15 - Operator notifications now show when each item was last checked, and inbox action links (for example Fix song info) close the modal and navigate reliably to Files with the audio quick-edit panel opened when possible.

2026-06-16 03:45 - Declared bundled demo content as locked release `bandpromo-demo` ("bandPromo demo"): seeded registry/document templates, asset migration assigns demo masters to that release, replaced User files/Include demo filters with All releases + release-name pool filters in Files and Content editors, and removed auto-suppressed demo pool behaviour.

2026-06-16 03:00 - Fixed playlist editor preview to be container-first: new playlists start with an empty active list and the full audio pool in Available content, instead of inheriting legacy global order or auto-filling every track.

2026-06-16 02:30 - Reworked Content → Playlist to match the Pages two-column pattern: playlist pool with add/edit/delete on the left, track pool only in edit mode, read-only track preview on the right, plus `manage-playlist.php` for create/delete.

2026-06-16 02:00 - Split page `picture` (plain caption only) and `picture_richtext` (image + rich body) blocks with migration on save/load, updated bio template, and separate + Picture / + Picture + text editor actions.

2026-06-16 01:45 - Removed the Gallery player tab now that galleries ship as page module blocks: gallery module defaults off, tab-order keys strip `module:gallery`, player layout editor no longer surfaces it, and play shell drops gallery.js/INITIAL_GALLERY_ITEMS.

2026-06-16 01:20 - Added Content → Playlist and Content → Gallery container pool sidebars (registry on the left, drag editor on the right) with `?playlist=` / `?gallery=` URL state and admin APIs `get-playlists.php`, `get-galleries.php`, `get-gallery.php`.

2026-06-16 01:00 - Implemented platform model slice 7 (themes): `biblioteca/theme-storage.php` with `data/themes/` registry, setup-default protected seed, duplicate + active pointer in `web-config.json`, CSS variable injection on login/player/admin shells, admin APIs (`get-themes`, `get-theme`, `save-theme`, `duplicate-theme`, `set-active-theme`), and Content → Themes pool editor with token form.

2026-06-16 00:15 - Fixed deep-link player URLs (`/play/{playlist}/{release}/{track}`) loading CSS/JS from wrong paths by switching play shell assets to root-absolute `/biblioteca/...` URLs.

2026-06-16 00:05 - Clarified the player file:// error screen with bandPromo-specific PHP dev-server instructions and hide the broken player chrome when playlist load fails.

2026-06-15 23:55 - Page gallery blocks now open images and videos in the player lightbox (same behavior as the Gallery tab), with a play overlay on video thumbnails.

2026-06-15 23:45 - Fixed page gallery blocks not showing demo photos: gallery image delivery resolution now maps `original/*.png` sources to existing `optimal/*.jpg` delivery files (matching the player gallery tab) instead of broken `.png` optimal URLs.

2026-06-15 23:30 - Implemented platform model slice 3 (galleries): `biblioteca/gallery-storage.php` with `data/galleries/` registry and migration from legacy `data/gallery.json`, dual-write save path, page `gallery` module block with grid preset rendering, admin/page-editor gallery block support, and template bootstrap seeding.

2026-06-15 23:00 - Implemented platform model slice 2: `biblioteca/playlist-storage.php` with `data/playlists/` registry and migration from legacy `play/playlist.json`, authenticated `get-player-playlist.php` endpoint, player path deep links via `play/.htaccess`, playlist selector UI, embargoed tracks shown as locked/non-playable, admin preview fallback from playlist containers, and template bootstrap seeding for playlists.

2026-06-15 22:00 - Implemented platform model slice 1: `biblioteca/asset-registry.php` with `ast_{ULID}` IDs and legacy master migration, `biblioteca/release-storage.php` with seeded `data/releases/primary.json` and lock guards, ULID-based audio master intake on upload, removed playlist-save master tag sync, and release-based track number suggestions in audio metadata saves.

2026-06-15 21:00 - Completed v0.8 policy definitions: added `docs/ACCESS-MODEL.md` (tiers, VIP per-track overrides, login/FAQ/shared links), `docs/DELIVERY-ARCHITECTURE.md` (protected delivery, PWA, full-media cast scope), and `docs/PORTABILITY.md` (full backup vs data export/import, moved-site repair); expanded `docs/PLATFORM-MODEL.md` with theme semantic tokens, module registry, and `web-config` target shape; updated `docs/TODO.md` to mark policy complete and leave implementation slices only.

2026-06-15 20:00 - Locked the v0.8 platform model in `docs/PLATFORM-MODEL.md`: releases as catalog anchor, `ast_{ULID}` asset registry, `data/` containers for playlists/galleries/themes, per-release track slugs with path URLs (`/play/{playlist}/{release-slug}/{track-slug}`), release locking, unified media pools, legacy playlist→master tag sync removal, and updated `docs/TODO.md`, `docs/ROADMAP.md`, and `docs/MEDIA-HANDLING.md` to match.

2026-06-15 18:30 - Checkpoint v0.8 build 289: fix Site update to detect prerelease packages via GitHub Releases API so v0.8 beta builds are offered over older stable v0.7 releases.

2026-06-15 18:00 - Fixed Site update not detecting v0.8 prereleases: GitHub `releases/latest` skips prerelease assets, so the updater now resolves the newest published release tag (including beta prereleases) via the GitHub Releases API before loading `release-manifest.json`.

2026-06-15 17:00 - Checkpoint v0.8 build 288: open the v0.8 beta version line with continuous build numbering, reorganize `docs/TODO.md` and `docs/ROADMAP.md` around the active v0.8 milestone, and update session-start plus planning doc version references.

2026-06-15 16:30 - Renamed the repository version line to `v0.8 build 287`, continuing build numbering from v0.7 build 286 without resetting; updated planning docs and session-start tooling for the active v0.8 milestone.

2026-06-15 16:00 - Reorganized `docs/TODO.md` and `docs/ROADMAP.md` to mark v0.7 exit gates complete and v0.8 beta as the active milestone; split v0.8 priority 3 into shipped Content editor/delivery automation (3a) and active platform model work (3b).

2026-06-15 15:00 - Checkpoint v0.7 build 286: ship unified Content editor pool/result UX (Playlist, Gallery, Pages, Player layout), upload-time background delivery automation with delivery-ready pool gates, playlist save that materializes pool tracks without requiring a full build, setup compose for initial layout, and non-blocking video delivery with Windows MOV fix.

2026-06-15 14:25 - Fixed MOV/WEBM background video delivery on Windows: ffmpeg logs no longer corrupt the result JSON, subprocess output uses UTF-8, and failed jobs now surface the real delivery error instead of “failed without a result payload”.

2026-06-15 14:10 - Video uploads no longer block on the final chunk: delivery and poster generation always run in a background job (including MP4), and the upload UI shows “finishing upload…” instead of stalling near 88%.

2026-06-15 13:50 - Locked Content editor pool headers to Gallery dimensions (48px, 118px control slot, “Available content” label) so Playlist, Gallery, Pages, and Player tabs keep the same box positions when switching.

2026-06-15 13:35 - Content editor column headers now use a fixed height and compact pool actions so Pages “Add page” matches Playlist/Gallery/Player layout and tabs no longer jump when switching editors.

2026-06-15 13:20 - Content editors (Playlist, Gallery, Pages, Player) now share column header sizing and a compact save control: hidden until edits exist, amber Save while dirty, green Saved after a successful save.

2026-06-15 13:00 - Removed the non-functional Include demo filter from Content → Player layout; demo filtering stays on Playlist and Gallery pools only.

2026-06-15 12:45 - Include demo on the playlist editor now auto-prepares missing bundled demo delivery files and shows not-ready demo tracks in the pool until their MP3 is ready.

2026-06-15 12:35 - Clarified empty pool states in Content editors (items already placed vs delivery still preparing) and fixed gallery pool staying on “Loading media…” when no delivery-ready files exist.

2026-06-15 12:25 - Removed redundant playlist pool filter hint under Available tracks; the Include demo control is self-explanatory.

2026-06-15 12:20 - Pages block editor keeps the add-block toolbar pinned while only the block list scrolls inside a viewport-sized left panel.

2026-06-15 12:15 - Updated Pages block editor hint to mention reordering blocks and live preview while editing.

2026-06-15 12:10 - Fixed Pages editor block editor leaking below the pool: `display: flex` on `.page-editor-view` no longer overrides the `hidden` attribute.

2026-06-15 12:00 - Redesigned Content → Pages to match other editors: available-pages pool on the left with Add page in the header, edit icon per row opens the block editor in place, live preview stays on the right, and new pages open straight into the editor.

2026-06-15 11:15 - MOV/WEBM video uploads now spawn async background delivery jobs with progress and completion surfaced in Notifications; MP4 uploads still prepare synchronously.

2026-06-15 10:30 - Background delivery automation: uploads now auto-run validation scan plus audio/image/video delivery tasks, Content pools only list delivery-ready assets, the header inbox is renamed to Notifications, setup full build composes initial playlist/gallery/player layout via `scripts/setupCompose.py`, and post-setup installs no longer nudge operators toward the Build tab for routine uploads.

2026-06-15 08:00 - Playlist save now validates missing optimal delivery files and runs a partial audio-delivery pass for newly added tracks (including bundled demo audio) so playback works without a full publish build.

2026-06-15 07:50 - Fixed playlist/gallery/player layout reordering bouncing back by finalizing within-list drags before removing the drop placeholder.

2026-06-15 07:45 - Pool demo filter no longer removes playlist or gallery items: preview keeps active entries regardless of filter, and filter changes reload only the available pool.

2026-06-15 07:40 - Fixed playlist save dropping pool-only tracks (including bundled demo audio): save now materializes missing entries from source audio before writing `play/playlist.json`.

2026-06-15 07:35 - Moved the demo asset control into a right-aligned pool filter on Content editors (Playlist, Gallery, Player layout), matching the Files tab filter pattern and removing the standalone Demo button.

2026-06-15 07:25 - Redesigned Content → Playlist editor as a two-column pool/playlist layout with multi-select drag-and-drop, matching Gallery and Player layout. Saving now writes only the active playlist; excluded tracks stay in the pool across preview, save, and build.

2026-06-15 07:15 - Player layout editor supports multi-select drag-and-drop with visible selection highlights on both pool and layout lists.

2026-06-15 07:10 - Fixed Gallery order row selection highlight so multi-select styling is visible on the active list (not overridden by gallery row styles).

2026-06-15 07:05 - Gallery editor supports multi-select drag-and-drop: Shift/Ctrl/Cmd-click to select multiple pool or gallery items and move them together.

2026-06-15 06:55 - Redesigned Gallery editor to match Player layout: Available content pool, Gallery order panel, header save row, and cross-list drag-and-drop.

2026-06-15 06:50 - Moved Player layout save button and status into the panel header, matching the Pages editor pattern.

2026-06-15 06:45 - Restyled Player layout pool and list panels with bordered boxes, clearer headers, and renamed the left column to Available content.

2026-06-15 06:40 - Player layout rows now show read-only page titles with icons; tab labels are edited in Pages only, and remove buttons sit inside each row like the gallery editor.

2026-06-15 06:35 - Clarified Player layout column headers with proper title styling and a divider so they read as section headings, not helper text.

2026-06-15 06:30 - Removed FAQ mention from Player layout help copy; FAQ is login-only and not part of this editor.

2026-06-15 06:25 - Player tab bar and content panels now follow the saved `player.tab_order` layout; page visibility no longer depends on the legacy pages module toggle.

2026-06-15 06:15 - Restyled Player layout editor as a two-column pool/result editor using playlist row styling, drag handles, and gallery-style layout.

2026-06-15 06:00 - Rebuilt Content → Player layout editor with drag-and-drop active/available page lists, locked Playlist/Lyrics rows, FAQ excluded, persisted `player.tab_order`, and reorderable gallery/page tabs.

2026-06-15 05:45 - Removed padding from page editor panels and preview panel so blocks and live preview fill the column width.

2026-06-15 05:40 - Removed `#pageEditorRoot` card padding so the page editor workzone uses the full content width.

2026-06-15 05:35 - Compacted page editor layout: removed `#pageEditorRoot` card bottom margin, tightened panel/block padding and gaps, and slightly increased preview viewport height.

2026-06-15 05:25 - Block style switches (¶, headers, small, code) now preserve paragraph alignment; only the Clear formatting (⌫) control resets alignment back to left.

2026-06-15 05:15 - Fixed page editor toolbar state tracking after rich-text changes: restored selection after block transforms, stopped consuming selection while reading toolbar state, and derive active icons from the selected blocks instead of stale `execCommand` values.

2026-06-15 05:00 - Checkpoint v0.7 build 284: ship consistent page editor rich-text formatting (multi-paragraph styles, clear formatting, selection-safe toolbar, paste cleanup).

2026-06-15 04:45 - Fixed page editor rich text consistency: toolbar clicks preserve selection, block styles apply to every paragraph in a multi-block selection, code/small/heading styles toggle back to normal paragraph, added Clear formatting (⌫), and paste now inserts clean paragraph blocks instead of foreign markup.

2026-06-15 04:15 - Checkpoint v0.7 build 283: operator inbox surfaces incomplete Welcome checklist steps and available site package updates from one shared notifications endpoint.

2026-06-15 04:00 - Operator inbox now includes incomplete Welcome checklist steps and available site package updates, using shared welcome-state logic in `admin-welcome-state.php` and an expanded `get-operator-notifications.php` payload.

2026-06-15 03:15 - Checkpoint v0.7 build 282: ship block-based page editor (richtext/picture/list blocks, Word-style toolbar, live preview, delete confirmations), JSON page templates, player page lightbox fix, login FAQ alignment, and v0.8 platform/marketing documentation.

2026-06-15 03:00 - Refreshed `faq.template.json` from the latest locally saved FAQ page (centered H1 title, bold question labels, updated copy).

2026-06-15 02:45 - Fixed page editor left alignment for headings and paragraphs: align-left now applies an explicit `page-align-left` class (instead of only stripping center/right), clears inherited wrapper `div` alignment, and editor/preview CSS uses heading-specific left-align rules so FAQ headers can be left-aligned in the input box and live preview.

2026-06-15 02:35 - Fixed page editor text toolbar on all pages (including FAQ): format/align/style buttons are handled before the `[data-action]` guard so toolbar clicks are not ignored. Login FAQ lightbox CSS now respects `page-align-*` classes instead of forcing every `h2` centered.

2026-06-15 02:25 - Refreshed `bio.template.json` and `faq.template.json` from the current local block-based page content (richtext/picture blocks, v2 schema) so new installs seed the updated demo pages.

2026-06-15 02:15 - Added operator-facing `docs/MARKETING-STRATEGY.md` (teaser → bridge → experience model, low-cost outbound tactics, feature timing, non-goals) and linked it from README, ROADMAP, FEATURES, AGENTS, and the admin documentation browser.

2026-06-15 02:00 - Documented the post-page-editor platform direction: pages as composition surface (core blocks + module blocks), multiple playlist/gallery libraries, player-centric playlists/lyrics with track deep links, gallery modules on pages (no Gallery tab end state), access tiers and Chromecast defined in v0.8 but implemented in v0.9+, fan credits and news in v1+, and beta-tester expectation notes in `ROADMAP.md`, `TODO.md`, and `FEATURES.md`.

2026-06-15 01:45 - Standardized page editor operator copy to say "block" instead of "section" in delete controls and confirmation modal text.

2026-06-15 01:40 - Fixed page block delete in the editor: block removal now uses the same in-app confirmation modal as page delete (instead of `window.confirm`, which was blocking deletes), and block action clicks resolve via `closest('[data-action]')` so the trash button is always detected.

2026-06-15 01:30 - Added a proper delete-page confirmation modal in the page editor (replacing the native browser prompt) and confirmation before deleting individual page blocks.

2026-06-15 01:20 - Renamed legacy page UI artifacts: player dynamic pages now use `.page-box` instead of `.bio-box`, the admin preview uses `.page-preview`, dead band-member/promo-photo and layout-chip CSS was removed, and the Picture add-block action now uses `picture` consistently.

2026-06-15 01:10 - Fixed page image lightbox in the player: `bindPageLightboxes()` now binds click-to-zoom on every `[data-page-id]` container instead of the removed `#bioBox` element.

2026-06-15 01:00 - Fixed recurring admin navigation blocks in the page editor by comparing a saved content fingerprint on navigation instead of a sticky dirty flag, and by recapturing the baseline after the DOM settles on load.

2026-06-15 00:50 - Fixed picture caption alignment in the player: toolbar align classes now apply inside `.page-picture-body`, row-flow captions default to centered, legacy `.bio-box` heading margins no longer break grid tiles, and picture cells keep a 2px gutter.

2026-06-15 00:45 - Restored a minimum 2px padding around page text blocks (richtext, picture captions, lists) while keeping picture image tiles edge-to-edge for fraction widths.

2026-06-15 00:40 - Compacted picture block controls: inline Width/Flow row, narrower dropdowns, smaller change button, and hint moved to a tooltip.

2026-06-15 00:35 - Small text now keeps normal body color (no muted tint) and the page editor toolbar uses tighter horizontal spacing with grouped style buttons.

2026-06-15 00:25 - Expanded the page editor text styles to H1–H3 headings plus Small and Code blocks, with matching delivery CSS and sanitizer support (legacy H4 content still renders).

2026-06-15 00:10 - Fixed picture fraction widths in the player: removed flex gap and extra width subtraction so three 1/3 blocks fit per row, cleared picture block margins/padding, and overrode legacy `.bio-box img` max-height/width rules for structured page pictures.

2026-06-14 23:55 - Fixed page editor navigation getting stuck on false dirty state: suppress dirty tracking during load/render, reset unload bypass after saves, guard all admin links with the unsaved modal instead of only page tabs, and ignore non-user input events that were flipping dirty on hydration.

2026-06-14 23:45 - Moved the page editor Save button and status into the Live preview header row so they stay visible beside the sticky preview without scrolling past all blocks.

2026-06-14 23:30 - Replaced picture Size/Align/Text controls with fraction Width (numerator/denominator 1–6, e.g. 2/5) and a single Flow picker (in row, end of row, wrap, beside); legacy size/align/text/layout values migrate on load and save as `width_num`, `width_den`, and `flow`.

2026-06-14 22:45 - Reduced page editor input lag by tracking dirty state with a lightweight flag instead of re-serializing the whole document on every keystroke, and scoped toolbar selection updates to active editors only.

2026-06-14 19:00 - Reworked the page editor toward a Word-like operator flow: combined Picture+text sections (text lives in the picture block, not separate blocks), single rich-text sections with style/bold/italic/underline/link toolbar, six plain-language placements including left/right with text underneath, instant layout chips without full re-render lag, and a sticky live preview that stays visible while editing.

2026-06-14 18:00 - Simplified the page editor for non-technical operators: three add buttons (Text, Picture, List), plain-language text styles, bold/italic/link toolbar, friendly picture placement options with text-wrap flow groups, and left-aligned wrapped text in `page-flow` containers.

2026-06-14 17:00 - Fixed page editor stuck on "Loading page blocks…": `page-editor.js` now waits for `DOMContentLoaded` and loads after `#pageEditorShell` exists instead of running from `<head>` before the markup is parsed.

2026-06-14 16:30 - Fixed blank page editor on installs that still had only legacy `data/bio.html` / `data/faq.html`: `bandpromo_page_load_document()` now auto-seeds missing `data/pages/*.json` files from tracked templates on first load instead of failing silently in admin.

2026-06-14 16:00 - Dropped legacy HTML page support for fresh-install beta rollout: removed `page-html-import.php`, `bio.template.html`, and `faq.template.html`; JSON-only load/save/seed in `page-storage.php` and `save-page.php`; simplified `scripts/build.py` seeding; and removed admin migration UI. Pages now live only in `data/pages/*.json` with server-rendered HTML at delivery time.

2026-06-14 15:00 - Aligned fresh-install page seeding with the block editor: added `bio.template.json` and `faq.template.json`, updated `template-bootstrap.php` and `scripts/build.py` to seed `data/pages/*.json`, and switched the welcome checklist starter-page detection to JSON block checksums.

2026-06-14 14:00 - Shipped the block-based page editor (**v0.7 build 281**): JSON page documents in `data/pages/`, server-side block rendering with semantic image presets, thumbnail image picker, player-styled live preview in admin, and public delivery updates for Bio and FAQ.

2026-06-14 12:00 - Shipped the admin-panel package updater (**v0.7 build 280**): Dashboard → Site update checks `release-manifest.json`, downloads and verifies immutable release ZIPs, applies tracked app files while preserving runtime state, runs post-update tasks, logs outcomes to `log/package-updates.jsonl`, and documents the operator flow in `docs/INSTALL-UPDATE.md`.

2026-06-14 10:00 - Beta polish: added semantic player link tokens and `.bio-box` link styles for readable visited links on dark backgrounds, swapped admin gear/debug icon order with borderless utility buttons, and reordered `docs/TODO.md` / `docs/ROADMAP.md` so the package updater stays v0.8 priority 1 and the page editor/presentation overhaul is priority 2 before broader platform-model work.

2026-06-13 17:15 - Published operator package **v0.7 build 279** with the beta Files-tab polish batch, login splash/session redirect improvements, and synced operator documentation.

2026-06-13 17:00 - Synced operator docs for the Files list header UX, demo/source filtering, login splash, session-expiry redirect, and removal of verbose Used-by row text in `docs/FEATURES.md`, `docs/MEDIA-HANDLING.md`, `docs/ROADMAP.md`, `docs/TODO.md`, and `docs/SECURITY-AUDIT.md`.

2026-06-13 16:35 - Removed the operator-facing "Used by" reference line from Files list rows while keeping the compact in-use/orphan badges.

2026-06-13 16:25 - Added Download and Delete text labels to Files list header bulk actions so Upload, Download, and Delete share the same icon-plus-label pattern.

2026-06-13 16:15 - Reordered Files list header actions to Upload, Download, Delete and labeled the upload control with icon plus text.

2026-06-13 16:00 - Styled Files list header filters consistently and moved bundled demo visibility into the same dropdown pattern as other file filters (User files / Include demo).

2026-06-13 15:30 - Moved Files list controls into a row-style list header aligned with file items, replacing Select all/Clear text buttons with a master checkbox that supports all/none/indeterminate selection.

2026-06-13 14:00 - Replaced Files tab filter button rows with compact fixed-width dropdowns and a split toolbar so download/delete/add actions stay pinned on the right when switching media panels.

2026-06-13 12:00 - Beta quick wins: login splash now shows the install logo with "Preparing your experience…", admin file lists gained Select all/Clear controls, expired sessions redirect to login on both admin and player via shared session-auth handling, and the completed-setup welcome dashboard no longer repeats setup-complete messaging or duplicate operator inbox content.

2026-06-12 22:30 - Completed the first full hosted bootstrap audit on `bandpromo.site`, published operator package `v0.7 build 277`, and locked the admin-panel package updater as the first v0.8 implementation priority in `docs/TODO.md` and `docs/ROADMAP.md`.

2026-06-12 16:00 - v0.7 code cleanup before v0.8: removed dead endpoints (`save-bio.php`, `get-gallery-items.php`, `get-build-required.php`, `check-uploads.php`), extracted shared helpers (`array-helpers.php`, `json-file-helpers.php`, `quiz-input.php`), deduplicated `delete-media.php` reference/JSON logic, unified video poster paths in `gallery-helpers.php`, and hoisted shared `admin.js` date/HTML helpers.

2026-06-12 15:12 - Track `log/.htaccess` in git via `.gitignore` exception so log-folder HTTP deny rules ship with releases.

2026-06-12 15:10 - v0.7 release checkpoint: enforced admin-role guards on `admin.php` and admin biblioteca APIs via `bandpromo_require_admin_session()`, added `log/.htaccess`, restored `docs/SECURITY-AUDIT.md`, and refreshed `docs/FEATURES.md`, `docs/MEDIA-HANDLING.md`, and `docs/ROADMAP.md` for shipped v0.7 behavior.

2026-06-12 14:45 - Closed the remaining `v0.7` beta-readiness scope in `docs/TODO.md`: admin help text is complete enough for closed beta with ticket-driven follow-up, and trial-use caching/update propagation moves to the `v0.8` gate.

2026-06-12 14:30 - Rewrote admin Operator inbox copy for non-technical operators. Task titles, severity badges, build steps, validation fixes, action buttons, and dashboard summary text now use plain language about what fans will see and what to do next instead of publish/build jargon.

2026-06-12 14:05 - Finished Operator inbox modal polish: removed obsolete drawer CSS, aligned modal layering and width, and updated dashboard help text to point operators at the inbox modal instead of an inline task list.

2026-06-12 13:45 - Moved the admin Operator inbox from an inline expanding drawer into a focused modal. The header bell and dashboard summary now open the same modal, while the Welcome dashboard keeps only a short status line plus an Open operator inbox button instead of rendering the full task list inline.

2026-06-12 13:25 - Reworked the admin Welcome page into a dashboard once all setup checklist items are complete. Completed installs now show a site-named dashboard view with quick actions, an always-visible operator inbox, and a collapsible setup archive, while incomplete installs keep the original checklist and next-step flow. The tab label, help text, and status callout now switch between setup and dashboard wording automatically.

2026-06-12 13:00 - Standardized Files tab panel intros so every sub-tab opens with the same bold permanent-action warning pattern. Audio keeps the metadata-edit plus delete warning, while Photos, Video, Illustrations, and Theme now lead with the shared delete warning before their panel-specific workflow guidance.

2026-06-12 12:35 - Added shared orphan detection for Files -> Photos and Files -> Video. `biblioteca/media-reference-helpers.php` now computes `reference_info` from gallery entries and theme settings, `list-media.php` exposes it for illustrations/photos/video, and the admin file lists now show in-use/orphan badges, reference lines, and `All` / `In use` / `Orphans` filters on the photo and video panels.

2026-06-12 12:05 - Finished cover art management Phases 2 and 3 plus the Gallery reorder UX follow-up. Files -> Illustrations now shows cover role/origin/reference badges, filter chips for track covers/orphans/build-generated files, and richer delete hints for theme references and regenerable build artifacts. Stale `configured_release_cover.*` variants are now cleaned up after playlist regeneration, and the Gallery editor reuses the Playlist-style dashed placeholder row while dragging active items.

2026-06-12 11:30 - Started cover art management Phase 1. Added `biblioteca/cover-art-helpers.php` and extended `data/media-library-state.json` with advisory `assets` metadata so Files -> Illustrations can expose per-file `cover_info` (role, origin, references, orphan/regenerable flags) from `biblioteca/list-media.php`. Build, upload, and audio-master cover saves now record cover origins, `scripts/makePlaylists.py` tags build-extracted and configured release covers, and illustration delete previews now include theme/config references through the shared cover reference index. `docs/TODO.md` also adds a Gallery editor follow-up to match the Playlist drag-placeholder reorder UX.

2026-06-12 10:15 - Added selective inline quick-edit for short audio metadata in Files -> Audio. Expanding an editable track row now keeps the compact tag-bullet view; clicking a tag edits Artist, Title, Version, Release, Track, Release date, Genre, BPM, or Key inside that bullet, reusing the existing audio-master save path with no-op suppression and validation refresh. Release date quick-edit now preserves the existing `date` / `year` / `TDRC` value, including year-only tags such as `2026`, while Description, Lyrics, and Cover remain read-only status chips and broader packaging work stays behind the existing pencil-icon editor.

2026-06-12 09:44 - Added the first warn-and-clean media deletion flow. `biblioteca/delete-media.php` now supports a preview step that reports playlist/gallery references for the selected file(s), and confirmed deletes now remove those references automatically before removing the file when the operator chooses to continue. `biblioteca/admin.js` now shows that warning text inside the existing delete modal and passes the confirmed delete through the new cleanup path.

2026-06-12 09:38 - Fixed local JPG photo uploads after the recent media-task refactor. `biblioteca/light-build-tasks.php` now avoids redeclaring the shared `bandpromo_first_command_path()` helper when loaded alongside `audio-master-helpers.php`, preventing `upload-media.php` from crashing before it could return JSON, and `scripts/optimizeMedia.py` now lets image-only refreshes continue without requiring audio/playlist prerequisites that are irrelevant to plain photo or illustration uploads.

2026-06-12 09:18 - Tightened the media-management direction after the latest review. `docs/TODO.md` now treats in-use media deletion as an operator-confirmed warn-and-clean flow that removes playlist/gallery references automatically if the operator still chooses delete, and `scripts/build.py` now describes source artwork/audio versus publish-ready delivery outputs without implying that originals must be specific codec/container formats such as PNG.

2026-06-12 09:10 - Fixed Python 3.6 compatibility in `scripts/optimizeVideo.py` after a real remote full-build test on `bandpromo.site`. The video build step was reaching the optimized MP4 copy path but failing poster extraction because the host Python 3.6 runtime does not accept `subprocess.run(..., text=True)`, so the script now uses `universal_newlines=True` for both transcode and poster ffmpeg calls.

2026-06-12 09:02 - Fixed gallery lightbox video playback behavior in `biblioteca/lightbox.js`. Opening a gallery video now resets it, starts playback immediately, leaves sound enabled, and turns on looping so the lightbox behavior matches the expected poster-to-video experience.

2026-06-12 08:53 - Fixed the stale Files -> Video operator guidance in `admin.php` after remote verification. The panel now says original videos are kept locally while publish-ready MP4 files are prepared during the full build, and it correctly tells operators to run `Run Publish Build` after video uploads instead of claiming that no build is needed.

2026-06-12 00:06 - Ignored generated video poster artifacts in `.gitignore` so `media/video/poster/` stays local like other derived media outputs and does not leak into checkpoints after validation builds.

2026-06-11 19:33 - Added a separate build-stage video delivery task instead of doing heavy transcoding during upload. Video uploads now mark a `video-delivery` follow-up task in `biblioteca/build-required.php` and `biblioteca/upload-media.php`, the full build pipeline in `scripts/build.py` now runs the new `scripts/optimizeVideo.py` step with visible progress, public gallery loading in `play/index.php`, `biblioteca/gallery.php`, and `biblioteca/get-gallery-items.php` now prefers built `media/video/optimal/*.mp4` assets when they exist, and `biblioteca/delete-media.php` cleans up the derived delivery file together with the source video.

2026-06-11 19:11 - Clarified the planned video-transcoding workflow. `docs/TODO.md` and `docs/MEDIA-HANDLING.md` now treat `.mov` / `.webm` to `.mp4` conversion as a separate build-stage task with visible operator progress instead of an upload-time action, so heavy video processing does not make uploads look stalled or ambiguous.

2026-06-11 19:02 - Added the first video-poster workflow for gallery media. `biblioteca/upload-media.php` now generates a JPG poster from uploaded videos when `ffmpeg` is available, stores it under `media/video/poster/`, and reports poster-generation warnings without blocking the upload. `biblioteca/save-gallery.php` now writes matching `poster` fields into saved video gallery items when a derived poster exists, `biblioteca/gallery.js`, `biblioteca/lightbox.js`, and the gallery editor in `biblioteca/admin.js` now use those posters for preview/lightbox cover rendering, and `biblioteca/delete-media.php` removes the derived poster when the source video is deleted.

2026-06-11 18:34 - Made the media optimizer source-aware for audio delivery generation. `scripts/optimizeMedia.py` now copies MP3 sources directly into the delivery tier instead of re-encoding them, only requires `ffmpeg` when at least one playlist track still needs transcoding, and logs the chosen per-track delivery route more clearly while `docs/TODO.md` and `docs/ROADMAP.md` move the completed optimizer split out of the remaining v0.7 work.

2026-06-11 18:12 - Verified the new task-driven image-refresh automation path on live local admin flow. Theme-cover saves now consistently return `auto_tasks` including `image-delivery` where applicable, clear pending build-required task state after the cheap image-only refresh completes, and keep operator inbox/build-required state aligned without requiring a manual build step for that path.

2026-06-10 16:55 - Automated safe image-only delivery refreshes. `biblioteca/save-config-raw.php` now auto-runs the image-only optimizer after theme-cover changes, `biblioteca/upload-media.php` now auto-runs the same image-delivery refresh after image-only uploads or cover-image uploads when that cheap work is enough, `biblioteca/build-required.php` now treats pending task units as authoritative so `image-delivery` can clear independently of unrelated audio follow-up, and `biblioteca/admin.js` now reports those automatic image refreshes in operator-facing save/upload messaging.

2026-06-10 16:42 - Clarified the image-refresh log scope in the optimizer output. `scripts/optimizeMedia.py` now states up front that image-only runs refresh track covers, photos, and illustrations, while `media/special` theme/share assets are used directly and social share variants are handled by `makeSocial.py`, reducing confusion about what `Refresh Image Files` is actually updating.

2026-06-10 16:36 - Split the current optimizer so image refreshes no longer re-encode audio. `scripts/optimizeMedia.py` now defaults to an `image-only` mode that regenerates track-cover, photo, and illustration derivatives without converting audio files, while `scripts/build.py` now invokes that optimizer explicitly in `full` mode during publish builds so audio delivery generation stays part of the full pipeline.

2026-06-10 16:29 - Allowed already-seeded local installs to build without a live starter-pack fetch. `biblioteca/release-package.php` now falls back to locally recorded starter-pack markers or the source-tree validation manifest when the required starter assets are already present on disk, so normal builds can continue on local/dev installs even if PHP lacks outbound HTTPS support for the published release manifest.

2026-06-10 16:21 - Routed operator-inbox build actions into the live Build view. `biblioteca/admin.js` now sends `Run Publish Build` / recommended build actions from the operator inbox through the Build tab with a one-shot query flag, auto-scrolls to the live log card, and starts the recommended build action there after the current build-required state loads, so operators see immediate visual feedback instead of triggering heavy work invisibly from another tab.

2026-06-10 16:18 - Made the operator inbox refresh consistently after state-changing admin actions. `biblioteca/admin.js` now refreshes the derived operator notification state after successful audio metadata saves, media uploads, and config/social saves instead of only updating the local build nudge, so resolved validation issues disappear immediately and new publish-follow-up items replace them without waiting for a manual page refresh.

2026-06-10 12:41 - Refined the Admin build wording around the new task-unit model. `admin.php` and `biblioteca/admin.js` now present the Build tab as `Run Publish Build` and `Refresh Image Files`, update the Build-tab help text and pending-work nudges to describe task-oriented follow-up instead of the older `Full Build` / `Optimize Media` phrasing, and use pending task units in the Build-tab status copy and recommended-action button.

2026-06-10 12:27 - Started the backend task-model refactor behind build-required state. `biblioteca/build-required.php` now records concrete task units such as `playlist-scan`, `audio-delivery`, `image-delivery`, `social-assets`, and `manifest` alongside the legacy `full` / `optimize` action so pending work can be described more precisely without changing the current manual build buttons yet. `biblioteca/admin.js` now uses those task units in operator-facing follow-up messaging and upload notifications instead of speaking only in coarse build-mode terms.

2026-06-10 12:02 - Replaced the temporary build-warning pattern with a persistent operator inbox in the Admin UI. `admin.php`, `biblioteca/admin.js`, and `biblioteca/admin.css` now expose an Unraid-style operator inbox bell, drawer, and Welcome-panel task surface driven by live build-required and playlist-validation state instead of a one-off top badge, while the new read-only `biblioteca/get-operator-notifications.php` endpoint provides the current build and validation data without mutating state. The inbox keeps heavy publish steps and operator-owned fixes visible until they are resolved, while leaving simple background work quiet.

2026-06-10 11:05 - Added a fast startup routine and VS Code shortcuts for repository sessions. `scripts/session-start.ps1` now prints a concise environment/worktree/tasks/backlog/changelog summary so session startup does not require repeated manual doc reads, `.vscode/tasks.json` now exposes that routine as `bandPromo: Fast session startup`, `.github/prompts/bandpromo-session-start.prompt.md` adds a slash-command chat shortcut, and `docs/AGENTS.md` plus `docs/DEVELOPMENT.md` now treat that fast path as the default session bootstrap.

2026-05-25 16:32 - Surfaced the new environment events in the Admin analytics views. `biblioteca/admin-helpers.php` now formats `login_environment`, `environment_snapshot`, and `environment_changed` rows into readable device/environment summaries, and both `admin.php` and `biblioteca/get-user-detail.php` now use that shared formatter so the analytics raw log and per-user activity modal show viewport, screen, display mode, orientation, fullscreen/standalone state, and related environment details instead of blank track-centric rows.

2026-05-25 16:20 - Added environment logging around login and player runtime changes. `index.php` and `biblioteca/login.js` now capture a client environment snapshot at login time so successful sign-ins record viewport, screen, orientation, display mode, touch capability, and related device context alongside the login event, and `biblioteca/player.js` now writes an initial `environment_snapshot` plus low-noise `environment_changed` entries when the authenticated player detects meaningful environment shifts such as orientation, viewport, fullscreen, online, or display-mode changes.

2026-05-23 15:03 - Added a theme-roadmap note for favicon package intake. `docs/ROADMAP.md` now calls out support for uploading RealFaviconGenerator ZIP packages and unpacking them into `media/icons/` as part of future operator-friendly brand asset handling.

2026-05-23 14:58 - Fixed the player playlist panel defaults and hover stability. `play/index.php` now places `Playlist` first in the header and makes it the default active panel on load, `biblioteca/player.js` now resolves panel buttons by explicit `data-view` keys so the new order stays stable, and `biblioteca/style.css` no longer shifts playlist rows sideways on hover.

2026-05-23 14:51 - Fixed the login-page background so it refreshes after the speed test completes. `biblioteca/login.js` now reapplies the background choice when the measured speed result, cached speed result, or speed-test failure state is written, so the login screen no longer stays stuck on the static-image fallback after a fast connection test.

2026-05-23 14:51 - Restored login-page speed testing while keeping the quality choice hidden and fixed to Optimized. `index.php` again shows the speed-test result and re-test link on the login form, while `biblioteca/login.js` once more runs the speed test and uses its measured connection result to choose between the video background and static image without bringing back the old quality buttons.

2026-05-23 14:46 - Simplified the login-page quality UX by removing the quality chooser and fixing login playback preference to Optimized. `index.php` now treats `low` as the fixed login quality instead of requiring a user-selected quality field, and `biblioteca/login.js` now persists that Optimized choice without running the old speed-test/button-selection flow on the login screen.

2026-05-23 14:21 - Closed the manual-setup documentation gap around ZipArchive. `setup.php` now warns repository/manual installs when the PHP ZipArchive extension is missing, explaining that setup/build can continue but bootstrap package flows, package updates, and multi-file downloads will stay unavailable until the host enables it. `README.md` and `docs/DEVELOPMENT.md` now also describe ZipArchive as an ongoing requirement for package flows and multi-file downloads, not only for the bootstrap installer.

2026-05-23 14:17 - Stopped failed file-download requests from exposing the raw download endpoint in the Admin UI. `biblioteca/download-media.php` now supports a JSON preflight mode for validating download requests without streaming a file, and `biblioteca/admin.js` now calls that preflight before submitting the real download form so operator-facing errors stay inside the admin panel as toasts instead of replacing the page.

2026-05-23 14:12 - Refined the expanded Files-audio Lyrics chip wording so missing lyrics are labeled more clearly. `biblioteca/admin.js` now shows `Missing` instead of `No` when the expanded audio metadata view has no lyrics saved.

2026-05-23 14:11 - Completed the expanded Files-audio metadata chip set with a dedicated Lyrics indicator. `biblioteca/admin.js` now includes a `Lyrics` inline chip in the expanded audio row view, showing a simple `Yes` or `No` value based on whether lyrics are present while still using the existing saved-tag health state for its traffic-light color.

2026-05-23 14:08 - Aligned the expanded Files-audio metadata view with the traffic-light system. `biblioteca/admin.js` now renders the expanded inline metadata chips with green/amber/red classes based on the same saved-tag health states used by the condensed audio badges, and it replaces the old badge summary with explicit `Missing` values where needed for the expanded fields. `biblioteca/admin.css` now hides the compact C/A/T/R/D/L badge strip while an audio row is expanded and styles the inline chips with matching traffic-light colors.

2026-05-23 14:02 - Replaced the old Files-audio row click-to-edit behavior with a one-at-a-time inline tag summary. `biblioteca/admin.js` now uses row clicks to expand a compact set of saved audio metadata fields from the existing detail endpoint, keeps the explicit edit action on the dedicated row edit button, and refreshes an expanded row after metadata saves. `biblioteca/admin.css` now supports the stacked row layout and the compact inline metadata chips shown under the active audio file.

2026-05-23 13:50 - Tightened the Admin Files traffic-light language so the preferred/safe path reads clearly and destructive actions stop overpowering the panel. `admin.php` now marks the audio view toggle as a dedicated display-mode control with `Master` as the default selected state and applies the same action-tone classes to header add/download/delete controls across the Files panels. `biblioteca/admin.js` now starts the audio list in `master` mode and syncs the toggle state so `Master` renders as the green preferred view while `Original` renders as the amber alternate view. `biblioteca/admin.css` now gives add/edit/download actions a visible green treatment, preview an amber treatment, and delete a restrained red outline treatment instead of a dominant filled-danger look.

2026-05-23 13:33 - Reworked the Admin Files panels to match the documented original/master media model and reduce operator clutter. `admin.php` now starts each panel with drag-and-drop guidance plus a permanence warning, uses compact icon-based group actions, adds an audio-level `Original`/`Master` toggle, and moves each file-count summary below the list. `biblioteca/admin.js` now treats the header download/delete controls as true multi-select actions, renders per-row edit/download/delete actions, and switches audio row names/sizes plus bulk-download behavior based on the current original/master view. `biblioteca/list-media.php` now includes prepared master file size and modified-time metadata so the master view can show the correct file details, and `biblioteca/admin.css` adds the supporting footer, action-row, and disabled-state styling.

2026-05-22 13:50 - Finished the Welcome checklist severity styling and removed the redundant intro note. `admin.php` now tags each Welcome checklist item as `blocking` or `nonblocking`, uses those severities to color both the checklist links and the next-action links, and no longer renders the extra `card-note` sentence under the Welcome heading. `biblioteca/admin.css` now overrides both normal and visited Welcome links so browser blue/purple defaults cannot leak through, with green for completed items, amber for non-blocking work, and red for blocking work.

2026-05-22 14:02 - Replaced the Admin Welcome pages-published heuristic with a normalized checksum test after a false negative on a long-running personalized site. `admin.php` now treats `data/bio.html` and `data/faq.html` as starter-page reuse only when their normalized content hashes still exactly match the shipped starter templates, instead of guessing from overlapping phrases.

2026-05-22 13:46 - Polished the admin Welcome checklist again after another localhost pass. `admin.php` now auto-writes a local starter-pack marker when the shipped starter files are already present but `data/default-theme-package.json` is missing, so source-tree installs stop showing the unresolved marker warning and instead record the starter pack as installed. The `What to do next` list now uses action labels such as `Publish your own info` rather than repeating checklist labels verbatim, and `biblioteca/admin.css` brightens the Welcome help/callout/detail/link styling while keeping the traffic-light palette.

2026-05-22 13:35 - Reworked the admin Welcome page again after a localhost review so it now behaves like a system-owned checklist instead of a vague status summary. `admin.php` now marks six concrete states directly in the Welcome card: starter pack installed, installation personalized, own media present, own pages published, latest full build successful, and installation up and running. The page now derives the next action from the first incomplete check instead of asking the person running the site to judge whether things "look correct," and it treats source-tree installs with the shipped starter files as having the starter pack available even when no `data/default-theme-package.json` marker was written. `biblioteca/admin.css` adds the checklist-specific presentation styles.

2026-05-22 13:19 - Corrected the new Welcome-page state logic after a localhost UX pass. `admin.php` no longer treats every missing starter-pack marker as proof that a fresh-install build is still required; when an install already has real uploaded content and no pending build state, the Welcome callout now explains that the marker may simply be missing and suggests checking Build only if the public site still looks unfinished.

2026-05-22 13:17 - Reworked the admin Welcome page so it behaves like a practical control-room start page instead of a platform manifesto. `admin.php` now uses existing site signals such as pending build state, visible uploaded media, starter-pack presence, and site identity setup to generate a clearer `what now?` summary plus direct next-step links into Config, Files, Content, Build, and Documentation. The Welcome help text now speaks directly to the person running the site, the starter-pack note is folded into the status view instead of living as its own card, and the five low-value philosophy cards were removed in favor of one compact status-driven overview. `biblioteca/admin.css` adds the supporting layout styles and the helper hint pointing to the help toggle.

2026-05-22 12:57 - Documented the remaining mobile screen-off playback failure as a recognized architecture issue instead of continuing to stretch v0.7 around it. `docs/ROADMAP.md` now frames real-phone background continuation and next-track handoff failures as part of the future playback/delivery/cache/offline architecture track, and `docs/TODO.md` now records the current behavior explicitly as a known limitation that should be documented and carried into v0.8 rather than treated as a v0.7 gate blocker.

2026-05-22 12:33 - Hardened the audio streaming endpoint after a live mobile retest suggested the remaining failure might be in range handling rather than the player state machine. `biblioteca/audio.php` now only serves `206 Partial Content` for a single byte range and ignores unsupported multi-range requests instead of incorrectly replying with a truncated single-range response. That keeps the endpoint within the HTTP contract for the range patterns it actually implements and avoids malformed `206` responses during browser media probes.

2026-05-22 12:19 - Tightened the hidden/background next-track path again after live phone retesting on `v0.7 build 259`. `biblioteca/player.js` now keeps the media element in autoplay mode across track source swaps and preserves the auto-next transition marker until playback has actually advanced into the new track, so browsers that treat background source changes more strictly have a better chance of continuing seamlessly and any remaining failure can still be labeled as part of the next-track handoff instead of a generic interruption.

2026-05-22 11:50 - Refined mobile/background playback behavior around automatic track changes. `biblioteca/player.js` now switches to the next track immediately when auto-advance happens while the page is hidden instead of relying on delayed animation timers, which are more likely to be throttled or blocked after the screen turns off. The player also now tags auto-next transitions explicitly so interruption alerts can say the next-track switch failed, and `play/index.php` no longer uses the extra inline `onended` handler on the audio element now that the scripted ended path is the single source of truth.

2026-05-22 10:10 - Clarified version labeling around the starter design pack and developer docs. `admin.php` now treats the starter design pack as its own package version for display purposes, showing `1.0` instead of mirroring repository build numbers when older install markers still carry app-style `v0.7 build ...` values, and `biblioteca/release-package.php` now preserves an explicit `display_version` in future starter-pack markers. `docs/MEDIA-HANDLING.md` and `docs/SECURITY-AUDIT.md` now use version-only `v0.7` headers instead of stale build-specific references, keeping build numbering confined to checkpoints and the changelog.

2026-05-22 09:47 - Fixed two small playback/admin regressions. `biblioteca/light-build-tasks.php` now degrades cleanly on hosts that disable `shell_exec` or `proc_open`, and `biblioteca/get-playlist-preview.php` now falls back to the last built `play/playlist.json` so the Admin playlist/build view still loads instead of returning a 500 when the lightweight Python preview task is unavailable. `biblioteca/player.js` now checks support and reports errors against the actual streamed delivery file (so optimal-mode tracks no longer falsely report FLAC when MP3 is being served), and it now wires Media Session metadata/actions plus a targeted hidden-page resume path to improve phone lock-screen/background playback resilience.

2026-05-20 13:09 - Reframed the root documentation around the real operator audience. `README.md` now keeps the top-level story focused on what bandPromo is, the preferred bootstrap install path, and the supported repository/manual fallback without developer-heavy release workflow detail. Added `docs/DEVELOPMENT.md` as the home for repository workflow, build/package commands, and release-publishing notes, tightened `docs/TODO.md` plus `docs/ROADMAP.md` so they reflect the current bootstrap-first install reality, and corrected the top-level requirement wording so the documented bootstrap requirements now match the actual installer checks (`PHP 8+`, `ZipArchive`, outbound HTTPS download support, and a writable target folder).

2026-05-20 12:46 - Refined the final thank-you line in `setup.php` so the closing message now lands on its own emphasized line in the setup-complete screen.

2026-05-20 12:37 - Hardened the install/setup entry points after reviewing post-setup security. `bootstrap.php` now immediately redirects completed installations to `admin.php` instead of exposing the installer again, `biblioteca/setup-init.php` now refuses to run once setup is complete and tags setup-authenticated sessions explicitly, and `biblioteca/complete-setup.php` now requires that active setup session and destroys it after writing the completion marker so operators see the normal login flow afterward.

2026-05-20 12:21 - Refined the setup-complete copy in `setup.php` so the final screen now reads like a warmer operator handoff. It now congratulates the operator, explains the two clear next choices (`Open site` or `Open Admin panel`), and accurately notes that signing into the player with an admin or developer account shows a gear shortcut back into the Admin panel.

2026-05-20 12:14 - Tightened the setup wizard's visual signals in `setup.php`. Active step dots and primary actions now use the green success color instead of the earlier warning-like red, red remains reserved for actual errors, and step 3 now hides `Start building` after a successful build while promoting `Finish` as the green primary next action.

2026-05-20 11:41 - Polished setup step 3 for first-run operators. `setup.php` now introduces the build phase as "Downloading demo content and building site" with friendlier first-run copy and a `Start building` action, while `biblioteca/build.php` and `biblioteca/release-package.php` now write explicit starter-pack progress lines into the build log for checking, downloading, verifying, extracting, and installing the demo package before the normal site build starts.

2026-05-20 11:10 - Refocused the `bootstrap.php` post-install flow after a real test-server pass. The numbered step cards now own the operator journey: install lives in step 1, successful install turns step 1 into a success state, step 2 becomes the promoted setup action, the duplicate setup panel is gone, and latest-package status now appears as one more prerequisite card instead of a visually separate panel.

2026-05-20 10:52 - Compressed the `bootstrap.php` installer layout into a shorter, friendlier operator flow. The primary install action is now a green success button, the redundant `Ready when you are` panel is gone, release-package status now lives inside the `Before you install` section, and the success-state `What happens next` steps now sit directly under the welcome confirmation instead of reading like a separate document.

2026-05-20 10:40 - Compacted the `bootstrap.php` environment-check wording again after another local UX read. Failed check cards now describe only the missing requirement instead of repeating "ask your hosting provider" on every item, while the provider-help box now includes the exact site/domain and install-folder path the operator can send along with the generated request list.

2026-05-20 10:35 - Tightened the blocked-state operator experience in `bootstrap.php` after a local UX pass. The welcome area no longer teases setup before installation has succeeded, the disabled hero action is replaced with a clearer waiting message, release-support detail is tucked behind a secondary disclosure, and failed environment checks now generate an exact ready-to-send request the operator can forward to their hosting provider.

2026-05-20 10:28 - Rewrote the operator-facing `bootstrap.php` welcome flow so the installer feels encouraging instead of technical. The page now leads with a top-level install button and motivational copy, turns environment checks into plain-language readiness/help messages for hosting providers, simplifies the "what happens next" explanation into three operator steps, and makes failed checks stop with calmer wording that explains nothing was changed and what kind of help is needed.

2026-05-20 10:03 - Cleared the GitHub Actions Node 20 deprecation warning in the manual release-publish workflow. `.github/workflows/publish-release-package.yml` now uses `actions/checkout@v6`, `actions/setup-python@v6`, and `softprops/action-gh-release@v3`, moving the workflow onto the current Node 24 action runtime without changing the release-publish behavior.

2026-05-20 10:18 - Softened the new setup-theme-package language for nontechnical operators. `setup.php` now describes the required media bundle as a starter design pack instead of a package dependency, `biblioteca/build.php` now returns a friendlier setup error if that pack cannot be prepared, and `admin.php` now shows a plain-language Welcome card explaining whether the starter design pack is already installed on the site.

2026-05-20 10:02 - Moved the required demo/starter media contract out of `bootstrap.php` and into the setup/build path where it belongs. `scripts/build_release_package.py` now publishes a separate required default-theme asset ZIP alongside the core app ZIP and records it in `release-manifest.json`, while `biblioteca/build.php` now ensures that package is downloaded, checksum-verified, extracted, and recorded before the first build starts. `setup.php` and `docs/INSTALL-UPDATE.md` now reflect that bootstrap installs the app and setup is responsible for the required default-theme assets.

2026-05-20 09:34 - Corrected the bootstrap seed-media fix after a second live retest on bandpromo.site: the packaged demo FLACs and `media/icons/bP-icons.zip` were still skipped because the installer preserved the `media/` directory before recursing into its children. `bootstrap.php` now still preserves operator-managed runtime media on updates while descending into preserved media folders that contain packaged seed assets, so fresh installs can actually copy the bundled demo inputs needed for the first successful build.

2026-05-20 09:20 - Fixed the first real bootstrap-install blocker discovered during live testing: the release package already contained the tracked demo FLACs and `media/icons/bP-icons.zip`, but `bootstrap.php` was preserving the entire `media/` tree and therefore skipped those bundled seed assets on fresh installs. The bootstrap copy rules now still preserve operator-managed media on updates while allowing the packaged icon bundle and tracked `bandPromo_*` demo media to be copied into a brand-new install.

2026-05-20 00:56 - Added `docs/FIRST-BOOTSTRAP-TEST-CHECKLIST.md` as a narrow real-host smoke-test checklist for the operator installer, linked it from `README.md`, and marked the first tester-checklist task complete in `docs/TODO.md`. The checklist also records the two current blockers to the first full hosted bootstrap trial: no reachable published release manifest yet and no deployed `bootstrap.php` URL yet.

2026-05-20 00:43 - Narrowed the remaining v0.7 beta-readiness scope: `docs/TODO.md` now explicitly defers backup/restore flow design and moved-site recovery handling to v0.8 instead of treating them as v0.7 blockers.

2026-05-20 00:31 - Added the first operator-facing install/update guidance document as `docs/INSTALL-UPDATE.md`, written for non-technical hosted operators rather than Git/SSH users. `README.md` and `docs/SUPPORT.md` now point to that guide, and `docs/TODO.md` marks the bootstrap-install and future package-updater guidance tasks complete.

2026-05-20 00:13 - Finished the operator-first bootstrap install path for the v0.7 reusability gate: `bootstrap.php` now requires the published `release-manifest.json` as the authoritative release source, validates that the manifest exposes both version and package URL, installs the discovered immutable package without a user-editable ZIP field, and no longer falls back to the mutable `main.zip` branch snapshot in the normal operator flow. `README.md` and `docs/TODO.md` now reflect that the bootstrap path is manifest-driven rather than a manual URL entry flow.

2026-05-19 01:18 - Reframed the mobile-data/offline playback work as a v0.8 scaling architecture track instead of a late v0.7 implementation bucket: `docs/ROADMAP.md` now treats playback delivery, caching, and installed-PWA reliability as one architecture effort, while `docs/TODO.md` keeps only the definition/audit tasks in the current milestone and explicitly pushes the actual delivery/cache/offline implementation work into v0.8.

2026-05-19 01:02 - Wired the next immutable-release slice for the operator installer path: `bootstrap.php` now tries the latest published `release-manifest.json` asset before falling back to a manual ZIP URL, verifies SHA256 when the manifest provides it, `scripts/build_release_package.py` can now emit release-aware manifest fields, and `.github/workflows/publish-release-package.yml` adds an explicit manual publish path for builds that should become immutable GitHub Release assets.

2026-05-19 00:48 - Added an explicit distributable-package builder path instead of packaging every build automatically: `scripts/build_release_package.py` now assembles a tracked-file install ZIP plus checksum manifest, and `.github/workflows/build-release-package.yml` exposes the same step as a manual `workflow_dispatch` action. `README.md` documents that install packages are created only on intentional action, and `docs/TODO.md` now marks that manual packaging-path decision complete.

2026-05-19 00:34 - Added the first real browser-driven package installer entry point as `bootstrap.php`: it performs host checks, downloads a ZIP package into a temporary work area, extracts and validates the application root, copies tracked files into place while preserving runtime state (`web-config.json`, `.env`, `data/`, `log/`, `media/`), and then hands off to `setup.php`. The README and TODO tracker now document this as an initial bootstrap implementation rather than a completed immutable-release workflow.

2026-05-19 00:20 - Aligned the top-level operator-facing docs with the intended install/setup workflow: `README.md` now presents the bootstrap/package flow as the primary operator path, keeps the repository upload path as a manual/developer fallback, and documents seeded demo content plus the admin-first success checklist; `docs/FEATURES.md` now reflects the same setup expectations. `docs/TODO.md` now marks that README/setup alignment task complete.

2026-05-19 00:10 - Defined the first-run verification model for the v0.7 reusability gate: reusable installs should ship with seeded demo content by default, treat that content as a practical verification surface, and confirm success primarily through an admin landing with a clear next-step checklist. `docs/TODO.md` now marks that planning task complete.

2026-05-19 00:02 - Defined the install-locked paid add-on entitlement model for the v0.7 reusability gate: the roadmap now distinguishes install-locked bandPromo add-ons from audience/member premium access, scopes the first entitlement model to themes and modules, preserves legitimate moved installs when runtime identity survives, and requires a generous local grace period during entitlement-service outages. `docs/TODO.md` now marks that planning task complete.

2026-05-18 23:52 - Clarified premium terminology in the roadmap and TODO tracker so two different future models are not conflated: operator-defined `premium access` for audience/member access inside an installation versus install-locked paid add-ons/services sold by bandPromo itself.

2026-05-18 23:44 - Closed the install-shell versus release-identity split as a v0.7 planning contract: the roadmap now states exactly which current `site` fields stay install-level, which mixed fields (`site.name`, `site.short_name`, `site.description`, `media.cover`) must split, and how install-shell assets differ from release-scoped presentation. `docs/TODO.md` now marks that schema-boundary task complete.

2026-05-18 23:34 - Defined the installation-identity model for the v0.7 reusability gate: the roadmap now distinguishes a stable local `install_id` from stronger runtime-only install secret/key material, preserves identity across normal moves/restores, and makes explicit that this identity is a product/runtime primitive rather than a complete paid-entitlement defense. `docs/TODO.md` now marks that identity-definition task complete.

2026-05-18 23:22 - Closed the install/update telemetry-definition cluster for the v0.7 reusability gate: the roadmap now defines the release-observability model, the maintenance-telemetry payload boundary, and the friendly setup/admin consent UX, and `docs/TODO.md` now marks those three planning items complete.

2026-05-18 23:11 - Defined the admin-panel updater contract for the v0.7 reusability gate: the roadmap now spells out how update checks, package download/apply, integrity validation, failure handling, preserved runtime state, post-update tasks, and operator-facing messaging should work without exposing Git-centric deployment jargon, and `docs/TODO.md` now marks that definition task complete.

2026-05-18 23:01 - Defined the missing package-install contracts for the v0.7 reusability gate: the roadmap now spells out the bootstrap installer contract, ZIP update/preservation contract, and package source/version-check contract, and the TODO now marks those definition tasks complete. The docs also now explicitly treat GitHub `main.zip` branch snapshots as a manual developer fallback rather than the intended operator package source.

2026-05-18 22:52 - Synced the v0.7 milestone tracker with the shipped setup work: `docs/TODO.md` now marks the license/operator-responsibility acknowledgment flow as implemented and live-verified on `bandpromo.site`, so the remaining reusability work is focused on package installs, update contracts, telemetry policy, and install identity rather than already-completed wizard UX.

2026-05-18 22:46 - Extended the recorded setup acknowledgment with install-location context: `complete-setup.php` now stores the current host plus both the current and configured site URL in `data/operator-acknowledgment.json`, so the installation record is more useful for later recovery, identity, and support checks.

2026-05-18 22:28 - Upgraded the first-run acknowledgment flow from a plain note to a recorded setup contract: `setup.php` now uses friendly in-wizard modals instead of raw document links, `setup-init.php` enforces explicit confirmation before setup can proceed, and `complete-setup.php` now writes a local `data/operator-acknowledgment.json` record alongside the normal setup-complete marker.

2026-05-18 22:13 - Added the first setup-wizard acknowledgment for operator boundaries: `setup.php` now shows a friendly required confirmation covering the AGPL license and operator responsibilities, with direct links to the shipped license and responsibility documents before the admin account step can continue.

2026-05-18 22:01 - Extended the setup-policy planning docs so the future install flow includes a friendly acknowledgment step for the AGPL license and operator responsibilities, with links back to the shipped responsibility and license documents instead of a vague one-line disclaimer.

2026-05-18 21:53 - Refined the install/update observability plan with product-identity constraints: planning docs now require a friendly opt-in setup question for maintenance-success reporting, keep the core product independent of telemetry or activation, and reserve a stronger install-secret/entitlement model for any future paid modules or themes so a copied plain UID is not treated as sufficient licensing proof.

2026-05-18 21:42 - Extended the pre-implementation install/update policy with observability rules: GitHub release download counts are now documented as the preferred passive adoption signal, while any future install/update webhook is explicitly framed as opt-in maintenance telemetry with minimal payloads and clear privacy boundaries.

2026-05-18 21:33 - Refined the pre-implementation install/update policy: `.gitignore` now explicitly serves as the runtime-preservation checklist for future ZIP installs/updates, planning docs now lock GitHub-hosted ZIP releases plus `VERSION`-based update checks as the preferred operator path, and the roadmap/TODO now record backup/restore plus moved-site recovery as first-class future requirements.

2026-05-18 21:22 - Locked the installation/update strategy in planning docs before implementation: bandPromo should move toward a one-file bootstrap installer plus ZIP-based release packages for both first installs and future updates, with Git/Plesk/SSH explicitly treated as a developer path rather than the expected operator workflow.

2026-05-18 21:08 - Refined setup hostname naming for branded installs: the first-install wizard now preserves the intended `bandPromo` casing when deriving a site name from `bandpromo.site`, instead of falling back to generic title-casing as `Bandpromo`.

2026-05-18 20:58 - Fixed first-install setup prefills for non-technical operators: the setup wizard now treats the shipped demo/template site profile as an unconfigured state and falls back to domain-derived defaults instead of showing placeholder values like `Your Site Name`, `https://example.com`, or the local demo site details on a fresh install.

2026-05-16 17:12 - Checkpointed the audio-metadata workflow session as `v0.7 build 243`, syncing the media-handling doc's current-state reference with the required VERSION bump used for the push routine.

2026-05-16 17:05 - Refreshed the session-affected docs for a checkpoint: `TODO.md`, `ROADMAP.md`, `MEDIA-HANDLING.md`, and `FEATURES.md` now reflect the shipped Files -> Audio metadata editor, automatic playlist/validation refresh after metadata edits, embedded track-number alignment rules, no-op save suppression, and the remaining gap that real metadata edits still collapse into the coarse build-required model.

2026-05-16 16:48 - Fixed the no-op audio metadata save path: opening a track and saving it unchanged no longer triggers a fresh `Full Build` requirement, because the save endpoint now detects unchanged metadata/cover state and skips the master update plus build-required marking.

2026-05-16 16:39 - Fixed automatic track-number handling during audio metadata saves: the save path now preserves any existing embedded track tag and, when that tag is blank, automatically writes the current playlist position instead of silently clearing the tag and leaving `missing_track_number` warnings behind.

2026-05-16 16:32 - Audio metadata saves now trigger an automatic lightweight playlist scan immediately after the master update, so `data/validation/playlist-validation.json` refreshes without waiting for a manual Full Build; delivery publishing can still remain pending when those edits also require regenerated output files.

2026-05-16 16:20 - Documented the deferred nondestructive media-naming direction across planning docs: TODO now tracks an operator-facing alias/display-name layer that preserves immutable original filenames as source identity, and the roadmap/media-handling docs now describe that future separation between trustable source anchors and human-facing names.

2026-05-16 16:09 - Tightened the audio-file badges and tagging workflow: Files badges now distinguish between build-breaking missing data and softer improvements, the badge order now starts with Cover and includes Description, and the track editor now splits the stored title tag into separate operator-facing Title and Version fields while still saving a combined `Title [Version]` tag to the master file.

2026-05-16 15:52 - Playlist reordering now also updates the embedded track number in each audio master where possible, so the saved operator order and the master metadata stay aligned instead of drifting apart until a later manual metadata edit.

2026-05-16 15:40 - Polished the audio metadata editor and Files helper copy: the release-date picker icon is now styled for the dark admin theme and moved to the left side of the field, and the Files audio helper now explains the row colors more clearly as Good, Could be improved, or Missing required data that can block the build.

2026-05-16 15:28 - Refined the Files audio experience for operators: the helper copy is now shorter, warmer, and focused on safe uploads plus better listener experiences; the audio header now tells operators they can click a track or drop new files to upload; and editable audio rows now open the track editor directly, so the separate edit icon is no longer needed.

2026-05-16 13:31 - Updated the Files panel operator copy: the media-panel header now carries the direct upload instruction, and the Files helper text now explains the original-backup, master-packaging, and delivery-file flow in more operator-friendly language while clarifying what the panel shows and why accurate metadata tagging in the track editor matters before publishing.

2026-05-16 13:24 - Fixed the media-picker preview lightbox in the admin UI: preview clicks inside the cover picker now open the shared admin lightbox again because the lightbox is initialized lazily after its DOM exists, instead of being constructed too early during page startup.

2026-05-23 16:04 - Simplified the Files tab badges and added operator download actions. The shared audit badge style no longer forces a minimum width, audio file badges are more compact and now present the tiers as `Orig` and `Ready` with explanatory tooltips instead of longer raw labels, and Files tab panels now support selection-based downloads: normal media can be downloaded from the current file list selection, audio selections can download either uploaded files or prepared copies, and multiple selected files are streamed as a ZIP archive from the new authenticated `biblioteca/download-media.php` endpoint.

2026-05-23 15:36 - Fixed the new playlist reorder interaction after local testing showed tracks could no longer be dragged at all in normal browser use. The selected rows are now collapsed only on the next animation frame after `dragstart` begins, preserving native HTML5 drag initiation, and the insertion placeholder is explicitly rendered as a block so the open gap can appear consistently.

2026-05-23 15:24 - Fixed a regression in the new Files bulk-selection UI discovered during local browser validation: audio/file row checkbox clicks were being stopped before the delegated selection handler saw them, so `Delete selected` stayed disabled even when boxes were checked. The checkbox handler now listens in capture phase so row click suppression still protects the editor/open behavior without breaking bulk selection.

2026-05-23 15:05 - Improved the local admin file and playlist UX: Files tab rows now have bulk-delete selection checkboxes with range selection support and a shared delete flow that can remove multiple files in one action, while Content -> Playlist now supports Shift/Ctrl multi-selection and block dragging with a visible insertion gap instead of requiring operators to drop directly on another track.

2026-05-16 13:18 - Fixed the track-cover preview after save so the newly selected image stays visible immediately: the save response now carries the updated sidecar cover state and a cache-busted sidecar URL, preventing the modal from repainting with stale cover data or a browser-cached older image.

2026-05-16 13:12 - Fixed the track-cover picker path validator so cover selections from Illustrations, Photos, and Theme Assets no longer fail with `Invalid cover path`; the backend now accepts the real media paths emitted by the picker such as `/media/img/original/...`, `/media/photo/original/...`, and `/media/special/...`.

2026-05-16 13:05 - Simplified the track-cover chooser in the audio metadata editor: the verbose cover info panel was removed, the adjacent area now shows only compact static track info, and cover actions were moved onto the artwork itself as small corner icons for choose-cover and use-release-cover.

2026-05-16 12:55 - Tightened the audio metadata editor for operators: required fields are now ordered as Release name, Release date, Genre, BPM, Key, Artist, and Title; Track # is no longer editable and is shown only as compact playlist information; the modal header now shows human-facing track details instead of source filenames; helper/status copy was simplified to avoid internal system terms; BPM and Key are constrained to 3 characters; and the compact stats row was condensed further for better use of space.

2026-05-16 12:35 - Reworked the audio master metadata editor so operators see a denser, more actionable layout: Cover now shows the current build cover with a live preview plus a shared-image picker and release-cover fallback, static file info now includes duration/format/bitrate/sample rate/bit depth/filesize, the field order was compacted, `Release date` replaces the old year/date wording and uses an ISO date picker, Track # and BPM now enforce 3-digit input, track descriptions are capped at 300 characters, and the audio file-list badges now patch immediately from saved master metadata after a save instead of waiting for the last build-validation snapshot. The save endpoint also gained runtime-safe length checks for PHP installs without `mbstring`.

2026-05-16 11:35 - Fixed stale-CSRF failures when saving audio master metadata from long-lived admin tabs: the admin UI now refreshes its CSRF token and retries once on the specific `Invalid CSRF token` response, backed by a small authenticated token endpoint so the normal Save Metadata button works again without requiring a page reload.

2026-05-16 01:16 - The operator-facing validation flow now includes actions and file-level health badges: Build summary items link directly to metadata editing, playlist order, or Files as appropriate, Files -> Audio rows now show compact latest-build status badges for Artist, Title, Release, Lyrics, and Cover after the master badge, and admin deep links can focus the relevant playlist row or audio file/modal when opened from those actions.

2026-05-16 00:45 - Locked the next operator-repair direction in planning docs: `docs/TODO.md` now marks the first metadata-editing and master-building policy items as defined, adds follow-up tasks for actionable validation links, a persistent task/notification panel, and selective inline quick-edit, while `docs/ROADMAP.md` and `docs/MEDIA-HANDLING.md` now reflect the decision that validation issues should become auto-resolving operator tasks routed to the correct editor surfaces instead of acting like manual checklist items or full Build-tab forms.

2026-05-16 00:12 - Build now shows an operator-facing validation summary outside the raw log: the admin Build tab renders a dedicated validation card using the locked `Cannot build` / `Fix before publish` / `Recommended fix` / `Can be repaired automatically` labels, grouping unsupported files and per-track metadata fixes into plain-language actions while keeping the raw build log available underneath.

2026-05-15 23:58 - Updated planning docs after the automatic audio-master backfill checkpoint: `docs/TODO.md` now records the legacy master backfill work as complete, and `docs/ROADMAP.md` now reflects the current policy that supported originals should create or backfill masters automatically during normal admin inspection instead of waiting for a separate operator-driven repair step.

2026-05-15 23:43 - Older libraries now backfill missing audio masters automatically when Files -> Audio inspects them: shared audio-master helper logic was extracted so the media listing can silently seed missing FLAC/MP3 copies, convert legacy WAV originals into FLAC masters on first encounter, and clear most `Master pending` badges without asking operators to run a separate migration step.

2026-05-15 23:12 - Fixed player playback for WAV-backed playlist entries in optimized mode: `biblioteca/player.js` now maps supported source audio endings `.flac` and `.wav` to the generated `.mp3` delivery file when building `variant=optimal` playback URLs, so tracks like the Salsa upload no longer request a missing WAV file from `media/audio/optimal/`.

2026-05-15 23:28 - Theme/special audio now follows the same WAV-to-FLAC policy as track intake: special-target WAV uploads are auto-converted into `/media/special/*.flac` instead of remaining WAV files, special audio uploads no longer incorrectly seed `media/audio/master/`, `index.php` now serves WAV theme audio with the correct MIME type when legacy files still exist, and a new `scripts/backfillWavAudioToFlac.py` action can promote existing special WAV files and legacy WAV masters to FLAC while updating matching config references.

2026-05-15 23:07 - WAV intake now promotes to a canonical FLAC master on upload instead of copying WAV into the master tier: the upload handler converts WAV originals into `media/audio/master/*.flac`, the build/optimizer/audio-detail tools resolve same-stem masters by preference so WAV originals transparently work against FLAC masters, Files -> Audio now shows original/master format badges per row, and deleting an audio original also removes its matching master artifact.

2026-05-15 22:33 - Fixed WAV delivery cleanup in `scripts/optimizeMedia.py`: optimized audio cleanup now derives the kept `.mp3` filenames from any supported source-audio entry in `play/playlist.json`, so successful WAV-to-MP3 conversions are no longer deleted at the end of optimization.

2026-05-15 22:47 - WAV is now accepted as a first-class audio source original: the Files -> Audio uploader accepts WAV files, playlist/build validation now treats WAV as supported instead of known-but-skipped, audio optimization converts supported source audio to delivery MP3 from either originals or masters, and the audio details editor button stays limited to formats with editable masters (currently FLAC and MP3).

2026-05-15 22:31 - Older installs no longer fail in Files -> Audio when opening track details before any master copies exist: `scripts/audioMasterMetadata.py` now auto-seeds a missing FLAC/MP3 master from the preserved original on first inspect/update, so existing libraries can use the metadata editor without a separate migration step.

2026-05-15 22:18 - Fixed the live Content -> Playlist preview endpoint on Linux hosts: `scripts/playlistPreview.py` no longer re-wraps `sys.stdout` before importing `makePlaylists.py`, avoiding the closed-stream failure that caused `get-playlist-preview.php` to return 500 after login on deployed servers.

2026-05-15 05:58 - Updated the repository agent instructions so an unqualified "checkpoint" request now means a publishable checkpoint by default: agents should summarize the milestone state, validate the touched work, bump `VERSION`, commit, push, and verify sync unless the user explicitly asks for a status-only checkpoint.

2026-05-15 05:43 - Fixed a small admin layout regression in the shared media-panel header so the new Content -> Playlist title stays left-aligned while its Demo action remains on the right.

2026-05-15 05:34 - Content -> Playlist now uses a live source-audio preview instead of relying only on the last built `play/playlist.json`: the admin playlist editor reloads current source tracks through a lightweight preview endpoint, includes a Demo toggle in the Content tab, and saves the full preview order into `data/playlist-order.json` so bundled demo tracks return to the editor immediately when user uploads are removed without forcing an immediate public playlist rewrite.

2026-05-15 05:11 - Bundled demo audio suppression is now consistent across Files browsing, playlist generation, and the admin playlist editor: once real user-uploaded audio exists, `bandPromo_*` placeholder tracks are treated as effectively hidden for the install even if they were not manually hidden one by one, so demo tracks stop leaking into playlist toggles and regenerated playlists.

2026-05-15 05:00 - Files panel media counts now show both item count and aggregate size, so headers read summaries like `(2 files, 129.9 MB total)` instead of only a bare count.

2026-05-15 04:53 - Audio master metadata editing now includes lyrics: the Python metadata helper exposes and writes lyrics for FLAC (`lyrics` / `unsyncedlyrics`) and MP3 (`USLT`) master files, the save endpoint accepts the field, and the admin track details modal now shows a dedicated lyrics textarea.

2026-05-15 04:43 - Fixed the new audio master details modal rendering bug in the admin UI: the overlay was moved out of the Files tab subtree into the shared modal area, so it no longer inherits `display:none` from inactive tab containers and now opens visibly after the metadata request succeeds.

2026-05-15 04:34 - Fixed admin route access for unauthenticated browser sessions: `admin.php` now delays loading the auth-enforcing helper until after the login form branch, so the admin panel once again shows its login page instead of returning a raw `Unauthorized` 403 before sign-in.

2026-05-15 04:27 - Fixed the audio master metadata endpoint regression that caused `get-audio-master-detail.php` to fail with a 500 error: the JSON task helper in `biblioteca/light-build-tasks.php` now opens child stdin with the correct pipe mode, so PHP can pass metadata payloads into the Python helper instead of failing with a bad file descriptor.

2026-05-15 04:18 - Files -> Audio now includes editable audio master metadata: a new track details modal loads metadata from `media/audio/master/`, lets operators update common text tags on FLAC/MP3 master files without touching originals, marks the install as needing a full rebuild after save, and records the change in admin audit logs.

2026-05-15 03:39 - The bundled demo visibility control in Files was compacted into a header action button: the old full-width toggle card was removed, each Files panel now shows a shared `Demo` button to the left of `+ Add files`, and the button stays grey when inactive while still controlling the same cross-panel bundled-demo state.

2026-05-15 03:25 - The build pipeline now begins treating audio masters as the canonical working surface when present: `scripts/makePlaylists.py` and `scripts/optimizeMedia.py` prefer files in `media/audio/master/` over `media/audio/original/` for playlist metadata scans and audio delivery generation, while still falling back to originals for tracks that have not been promoted yet.

2026-05-15 03:10 - Hidden bundled demo audio is now excluded from playlist generation and from the admin playlist editor view: `scripts/makePlaylists.py` skips bundled `bandPromo_*` source tracks hidden for the current install and records them in validation output, while `admin.php` filters already-generated playlist rows the same way so placeholder tracks stop resurfacing in Content -> Playlist before the next rebuild.

2026-05-15 02:55 - Moved the Files tab `+ Add files` actions out of the sub-tab row and into each media panel header, relocated the bundled-demo toggle into a shared admin-level control above the tab content, and switched the Gallery available-media loader to use the shared filtered media helper so the toggle now affects content-side media browsing as well as Files and pickers.

2026-05-15 02:45 - Supported audio uploads now seed a local `media/audio/master/` working copy immediately after landing in `media/audio/original/`, so the platform begins the eager-master intake path without changing current player/build reads yet, and the Files -> Audio help/upload feedback now mentions the prepared master copy.

2026-05-15 02:20 - Added an explicit "Show bundled demo assets" toggle to both the Files tab and the shared media picker so admins can reveal hidden `bandPromo_*` placeholder files for troubleshooting without making bundled demo media the default browsing experience again.

2026-05-15 02:05 - Media browsing now distinguishes bundled `bandPromo_*` demo assets from user uploads in runtime state, suppresses bundled placeholders by default once a media group contains real user files, and turns delete on bundled demo files into a local hide-for-this-install action so tracked placeholders do not feel like they "come back" after later pulls.

2026-05-15 01:35 - Expanded the media-handling docs to lock two operator-model rules: masters should be created or queued as early as possible after upload so admin work can happen against the canonical master instead of the preserved original, and bundled repo placeholder assets must be distinguishable from user uploads and hidden by default in normal media browsing through a runtime visibility/origin flag rather than by treating git-tracked demo files as ordinary user content.

2026-05-15 01:05 - Expanded the media-handling docs to distinguish the logical `original` / `master` / `delivery` model from the current on-disk `original` / `optimal` folders, locking that today's `optimal` paths are legacy delivery outputs rather than the future master tier and that broader intake-format support should follow, not precede, that storage/build contract.

2026-05-15 00:35 - Locked the media-handling docs to use operator-facing validation labels (`Cannot build`, `Fix before publish`, `Recommended fix`, `Can be repaired automatically`) and mapped the current playlist warning codes to those fix-first messages, with `docs/TODO.md` updated so the remaining work is the admin summary implementation rather than more terminology definition.

2026-05-14 00:20 - Tightened the compact mobile landscape player spacing so standalone/PWA mode wastes less room at the top edge, using safe-area-aware top padding instead of the earlier extra inset.

2026-05-14 00:05 - The developer debug modal now fetches and reports the live manifest orientation/display/start_url so remote tests can distinguish installed PWA state from the server-served manifest.

2026-05-13 01:35 - The compact player landscape layout now activates for coarse-pointer/mobile landscape screens up to 1024px wide instead of requiring a max-height of 500px, so standalone PWAs can switch layouts in landscape.

2026-05-13 01:20 - The developer debug modal now reports viewport, visual viewport, screen size, orientation, and device pixel ratio so standalone/PWA landscape layout thresholds can be verified on remote devices, and the screen/display metrics are grouped together in the debug output.

2026-05-13 01:05 - Tracks without lyrics now generate blank lyric fields instead of helper text, the build still flags missing lyrics in validation, and the player cleanly hides the Lyrics tab and falls back to Playlist for tracks that have no displayable lyrics.

2026-05-13 00:35 - Added explicit developer/admin access helpers and a developer-only player debug modal with logout, app-cache clearing, safe-rendered live diagnostics, and cache-clear summaries for external mobile/PWA testing.

2026-05-12 23:55 - Player quality precedence now honors the explicit browser-side selection before the server session fallback, preventing stale session quality from forcing original/FLAC playback after choosing Optimized on the login screen.

## [Unreleased] - 2026-04-30

### Fixed
- **2026-05-12 — Player now also honors the explicit login quality choice from session storage**
  - Updated `biblioteca/login.js` to persist the user’s chosen login quality in `sessionStorage`.
  - Updated `biblioteca/player.js` to prefer that explicit stored choice before falling back to server-injected preference or cached speed-test heuristics, so selecting optimized more reliably results in optimized playback.

- **2026-05-11 — Player page now attempts fullscreen in mobile wide mode and PWA orientation is no longer portrait-locked**
  - Updated `biblioteca/player.js` so the `/play/` screen, not just the login page, makes a best-effort fullscreen request when a mobile device enters wide landscape mode.
  - Updated `scripts/makePWA.py`, `index.php`, and `play/index.php` so generated manifests are no longer locked to `portrait-primary`, and the manifest URL now carries the tracked app version to help production clients pick up orientation changes.

- **2026-05-11 — Login speed-test label no longer appends a stray `HIGH` suffix**
  - Updated `biblioteca/login.js` so the informational login speed-test text now ends at `Max quality available` instead of rendering a leftover `: HIGH` artifact from the previous copy change.

- **2026-05-11 — Login defaults to optimized mode and can enter fullscreen in mobile wide mode**
  - Updated `index.php` and `biblioteca/login.js` so the login screen defaults to optimized quality, relabels the optimized option to `Optimized (Mobile Friendly)`, and changes the speed-test status text to `Max quality available: HIGH` instead of auto-recommending and auto-selecting high quality.
  - Added a best-effort mobile landscape fullscreen hook in `biblioteca/login.js` so the login page attempts to enter fullscreen when the device is in wide mobile mode and exits that fullscreen state again when leaving it.

- **2026-05-11 — Login assets now use VERSION-based cache busting**
  - Updated `index.php` to load `biblioteca/login.css` and `biblioteca/login.js` with the tracked app version in the query string so production browsers pick up responsive login changes immediately instead of serving stale cached assets.

- **2026-05-11 — Login page now fits small screens in landscape**
  - Updated `biblioteca/login.css` with a compact low-height landscape layout so the login form stays usable on phones in horizontal mode by reducing logo size and vertical spacing, top-aligning the page, and allowing scroll when needed.
  - Tightened the landscape behavior further with a true two-column flow: logo and the rotating “about” link on the left, form on the right, with equal-width columns and an extra-short viewport rule so the logo no longer consumes most of the vertical space on devices like iPhone 12 Pro landscape.
  - Expanded the left column to let the logo use the available vertical space more fully while centering the full form block vertically in the right column for short landscape phone layouts.
  - Fixed the remaining weak effect in landscape by giving the login grid real viewport height, so the larger logo and vertically centered form now have space to take effect instead of collapsing back to content height.
  - Restructured the login markup into explicit left and right columns so the rotating “about” box stays directly under the logo instead of drifting below the full layout when the landscape form column grows taller.
  - Scaled the working landscape layout up for Galaxy S8-class screens by widening the two-column container, enlarging the logo/about block, and increasing form control sizing so the page uses more of the available horizontal and vertical screen estate.
  - Removed the remaining `login-container` inner padding in the short landscape rules so the Galaxy S8-class layout can use the full available width and height without extra inset space.

- **2026-05-11 — Demo support control now uses the compact link button**
  - Updated `play/index.php` to force the player-facing support control onto the link-button path whenever support is enabled and a support URL is available, bypassing the larger Ko-fi floating widget for the current demo phase.

- **2026-05-11 — Player audio seeking now works again in local demo/testing**
  - Updated `biblioteca/player.js` so playback always uses `biblioteca/audio.php` instead of the local direct `/media/audio/...` path that broke seeking during localhost demos.
  - Kept the optimized-image delivery changes in place while routing audio back through the range-aware PHP endpoint until the larger audio-delivery rewrite is done.

- **2026-05-11 — Player now honors the selected audio quality and serves optimized images in the public player**
  - Updated `play/index.php` and `biblioteca/player.js` so the player uses the quality choice saved at login instead of ignoring it and re-deciding solely from the cached speed-test result.
  - Updated `biblioteca/player.js` and `biblioteca/gallery.js` so regular player-facing cover and gallery images are loaded from optimized delivery paths instead of original PNG-heavy sources.

- **2026-05-11 — VERSION workflow actions were updated for the Node 24 runner transition**
  - Updated `.github/workflows/version-bump.yml` from `actions/checkout@v4` to `actions/checkout@v6` and from `actions/setup-python@v5` to `actions/setup-python@v6`.
  - This removes the current deprecation warning about Node 20-based actions and keeps the VERSION validation workflow aligned with GitHub's upcoming Node 24 default.

- **2026-05-11 — VERSION validation workflow now fetches the commit range it compares**
  - Updated `.github/workflows/version-bump.yml` so `actions/checkout` fetches full history before comparing `${{ github.event.before }}` with `${{ github.sha }}`.
  - The workflow now also uses an explicit `if git diff --quiet ...; then ... fi` check so a valid `VERSION` change no longer leaks `git diff` exit code `1` into the job result.
  - This prevents false CI failures on pushes where `VERSION` was bumped correctly but the default shallow checkout did not contain the `before` commit object or the diff check returned the expected "files changed" status.

### Added
- **2026-05-09 — Admin panel now includes an operator-facing welcome page**
  - Added a new Welcome tab to `admin.php` with operator-focused overview copy about the platform's purpose, strategic value, and safety boundaries.
  - Made the Welcome tab the default admin landing page so operators arrive in the product vision before drilling into analytics or configuration tasks.

- **2026-05-09 — Support settings are now config-driven instead of Ko-fi-hardcoded**
  - Added a new `support` config branch plus a `Config -> Support` admin form for operator-owned support links and the optional Ko-fi floating widget.
  - Replaced the hardcoded Ko-fi widget in `play/index.php` with config-driven rendering so the player now uses saved support settings instead of embedded values.
  - Added support defaults to the config loader and template, while keeping new installs disabled by default and preserving the current demo behavior through `web-config.json`.

### Documentation
- **2026-05-09 — Future support-provider API work was recorded in planning docs**
  - Updated `docs/ROADMAP.md` to state that any later Ko-fi/Patreon/Stripe/PayPal/Vipps-style API use should sit behind an optional, operator-owned integration layer rather than turning bandPromo into the payment flow.
  - Updated `docs/TODO.md` so the anonymous/registered/premium access discussion explicitly includes deciding whether later provider APIs need a reusable integration layer.

- **2026-05-04 — Operator responsibility document was written instead of remaining a placeholder**
  - Replaced the stub content in `docs/OPERATOR-RESPONSIBILITY.md` with a real operator-boundary document.
  - Clarified responsibility split for content rights, hosting, security, privacy, integrations, media publication decisions, moderation, and support boundaries.

- **2026-05-03 — Roadmap no longer tracks transient build numbers**
  - Removed the `Current version: v0.7 build ...` header from `docs/ROADMAP.md` so the roadmap stays focused on product direction and milestones rather than repository build count.

- **2026-05-03 — Roadmap/TODO cache language and gate taxonomy were aligned**
  - Updated `docs/TODO.md` so the completion-rule guidance now matches the current practice of keeping completed policy items in place when they clarify ordering and dependencies.
  - Replaced the old `cache-busting` wording in TODO/ROADMAP with the actual product goal: aggressive safe caching, low needless re-downloads, reliable update propagation, and offline-capable mobile behavior.
  - Updated `docs/ROADMAP.md` so media repair/editing tools are framed under beta-operator/media-handling readiness rather than under the User Friendliness gate.

- **2026-05-03 — TODO planning order is now explicitly policy-first**
  - Updated `docs/TODO.md` to require definition work, real-world cases, and scope boundaries before implementation tasks.
  - Updated `docs/AGENTS.md` so future planning/doc cleanups keep sections grouped by meaning instead of turning into mixed bags of decision work and coding work.

- **2026-05-03 — Media handling TODO was reordered into policy-first work**
  - Updated `docs/TODO.md` so the Media handling section now separates policy already locked in docs, policy still to define, and implementation follow-up.
  - Marked the media-policy items already documented in `docs/MEDIA-HANDLING.md` as complete instead of leaving them as open thought work.
  - Moved the stray `web-config` branch audit item back to Admin UX, where it reflects config/editor structure rather than media policy.

- **2026-05-03 — Media handling section was broadened beyond the old gallery-deferred framing**
  - Updated `docs/TODO.md` to rename `Media handling (deferred from v0.7 gallery work)` to `Media handling`, since the section now covers the full source-media, packaging, validation, repair, and file-management model.
  - Moved the first file-manager metadata-tool and master-building-tool definition items out of `Beta operator readiness` and into `Media handling`, where they match the actual work.

- **2026-05-03 — TODO gate headers now include scope guidance**
  - Updated `docs/TODO.md` with short scope notes under each gate/section header so future tasks can be filed by meaning instead of implementation history.
  - Added explicit classification guidance for Stability, Trust, Reusability, User Friendliness, Admin UX, Media handling, and Beta operator readiness.

- **2026-05-03 — Checked TODO items were also reclassified to match gate intent**
  - Updated `docs/TODO.md` so completed items are grouped by meaning, not by the section they happened to be implemented under.
  - Moved metadata-warning visibility into Media handling, localhost setup verification into Reusability, and rewrote the quiz-removal item so it reflects Stability rather than module planning.
  - Narrowed the checked asset-scope item in Reusability to install/release/track personalization concerns instead of broader media-role wording.

- **2026-05-03 — Reusability gate was narrowed to deployment reuse and personalization**
  - Updated `docs/TODO.md` so Reusability now focuses on reusable deployment/setup, first-run verification, and install-level theming/personalization concerns.
  - Moved source-media policy, tier definitions, build regeneration rules, and metadata-validation behavior out of Reusability and into Media handling.
  - Updated `docs/ROADMAP.md` so the Reusability gate is defined around reusable installs and branded deployments rather than operator media-repair policy.

- **2026-05-03 — Admin UX dependency on media-handling policy is now explicit in TODO**
  - Updated `docs/TODO.md` so the remaining metatag-repair Admin UX task is clearly marked as dependent on the media-handling validation policy being locked first.

- **2026-05-03 — Media validation TODO items moved from Stability to Media handling**
  - Updated `docs/TODO.md` so metadata warning prominence, validation severity policy, and operator-facing validation language are tracked under Media handling instead of Stability.
  - This keeps Stability focused on delivery/runtime sturdiness while Media handling covers how administrators diagnose and repair weak source packages.

- **2026-05-03 — VERSION bumping is now local-first instead of a post-push bot commit**
  - Added `scripts/bump_version.py` so the tracked build number can be incremented locally before pushing to `main`.
  - Updated `.github/workflows/version-bump.yml` to validate the `VERSION` file on push instead of creating a second remote-only commit.
  - Updated `README.md` and `docs/AGENTS.md` to document the new local-first versioning workflow.

- **2026-05-03 — Agent guidance now requires session environment preflight**
  - Updated `docs/AGENTS.md` to require an environment check at the start of each session, covering OS, shell, workspace context, available tasks, and relevant runtimes.
  - Added explicit guidance to prefer PowerShell-native commands and repo tasks in Windows sessions instead of probing Bash/Linux commands first.

- **2026-05-03 — Trust policy now defines how playback and session events should be interpreted**
  - Added a canonical v0.7 playback/session analytics policy to `docs/ROADMAP.md`, covering `play_start`, `track_started`, `track_resumed`, `track_exited`, and `session_end` semantics.
  - Locked the current rule that only progress-bearing `track_exited` and active-track `session_end` entries contribute listening-time/completion metrics, while null `session_end` records remain session-boundary only.
  - Documented that `inactive_start` and `session_timeout` remain future events until explicit idle-detection rules are implemented.

- **2026-05-03 — Share-metadata fallback cleanup moved out of the v0.7 Trust gate**
  - Updated `docs/TODO.md` so hardcoded player/share fallback metadata is no longer tracked as a current Trust blocker.
  - Updated `docs/ROADMAP.md` to treat that cleanup as part of the later anonymous/public-access release track, where share metadata becomes externally user-facing.

### Fixed
- **2026-05-03 — Local uploads and scanner artifact folders are ignored correctly**
  - Updated `.gitignore` so arbitrary user-uploaded files under `media/special/` stay local by default, while bundled `bandPromo_*` placeholder assets remain tracked.
  - Added ignore rules for `_avg_` antivirus/scanner artifact folders so they cannot appear as accidental repo changes.

- **2026-05-03 — Analytics parser now understands current `session_end` and `track_exited` behavior**
  - Updated `biblioteca/analytics.php` so listening-time metrics use actual progress events instead of the old `track_started.duration` assumption, which no longer matches recent logs.
  - Session summaries now close on `session_end`, preventing long idle gaps from being merged into the previous play session.
  - Dashboard/trend/completion stats now count meaningful `session_end` track progress alongside normalized `track_exited` events.

- **2026-05-03 — Analytics sessions now split after 15 minutes without playback**
  - Updated `biblioteca/player.js` so analytics sessions follow actual playback starts, not just the play button, which also fixes missed session starts from playlist taps or resumed playback after interruptions.
  - Added a 15-minute no-playback timer that logs `session_end` for analytics only, without logging the user out.
  - A later resume now opens a fresh analytics session with a new `play_start`, matching the documented Trust policy.
  - Added a dev-only browser override for the inactivity threshold so the real timeout path can be exercised in seconds during local verification without changing the production default.

- **2026-05-03 — Short analytics windows no longer render as `0 hours` in admin**
  - Updated the Analytics dashboard and Quality stat cards so short listening totals render as `m:ss` instead of rounding down to `0 hours`.
  - This keeps quick trust-verification windows readable while still switching to hour-based display for longer totals.

### Added
- **2026-05-02 — Documentation tab now separates operator docs from developer docs**
  - Added a first-class `developer` user role so documentation visibility can follow an actual role instead of a hardcoded file list shown to everyone.
  - Documentation tab now defaults operators to operator-safe docs only, while developers get a developer-docs view with the ability to switch between developer, operator, and combined documentation scopes.
  - Moved developer-only docs such as `AGENTS.md`, `TODO.md`, `ROADMAP.md`, and `SECURITY-AUDIT.md` out of the default operator documentation surface.

- **2026-05-02 — Admin Documentation tab added for tracked project docs**
  - Added a new primary `Documentation` tab in the admin panel with a two-panel browser: available docs on the left and rendered content on the right.
  - `README.md` now opens by default, and tracked markdown files from `docs/` can be opened directly from the admin UI.
  - Added safe markdown rendering for the current documentation set, including headings, lists, checklists, code blocks, and internal links between docs.

- **2026-05-02 — Audit status badges added for quicker scanning**
  - Styled the Audit tab status column with semantic badges so successful, failed, denied, and neutral admin events can be scanned visually without reading the whole row.

- **2026-05-02 — Admin audit trail now captures failed login attempts and build outcomes**
  - Added explicit `admin_login_failed` audit records for missing credentials, invalid credentials, and rejected non-admin login attempts.
  - Added one-time build completion/failure audit records keyed to each build run so long-running jobs now leave a clear finished or failed trace, not just a start event.
  - Improved the Audit tab detail column so common admin actions render as readable summaries instead of raw JSON blobs.

- **2026-05-02 — Dedicated admin audit log added for multi-admin tracing**
  - Added `biblioteca/admin-audit.php` so management actions are logged separately from listener/playback activity under a dedicated admin-audit log path.
  - Added a new `Audit` admin tab in `admin.php` with date, action, and actor filters for browsing recent management events.
  - Wired the current admin mutation surface into the separate audit log, including login/logout, user management, page/gallery/playlist/config saves, media upload/delete actions, and build starts.

- **2026-05-02 — Admin Pages editor now exposes Bio and FAQ through one shared tool**
  - Replaced the Content -> `Bio` admin tab with a `Pages` editor that now exposes both `data/bio.html` and `data/faq.html` through one allowlisted editing surface.
  - Updated `admin.php` and `biblioteca/admin.js` so both pages use the same rich-text/source editor workflow, local optimized-image picker, and per-page save handling.
  - Added `biblioteca/save-page.php` so editable HTML documents now save through a shared sanitized endpoint instead of a Bio-only path.

### Fixed
- **2026-05-02 — Player logo now reads the runtime theme config instead of falling back to defaults**
  - Fixed `play/index.php` so playlist JSON no longer overwrites the global runtime config array loaded by `config-loader.php`.
  - This restores `get_config(...)` reads later in the player page, including the visible `content-logo`, so the player now follows the configured install logo instead of silently falling back to `bandPromo_logo.png`.
  - The login page was already reading the correct logo; the bug was isolated to the authenticated player page variable collision.

- **2026-05-02 — Public player and login now use the current install logo reliably**
  - Updated `play/index.php` and `index.php` so the visible shell/logo uses `install.brand.logo` instead of the release-brand alias.
  - This avoids migrated installs showing a stale release-level logo override after the operator updates the current theme/logo in admin.
  - Kept release-level brand fields available for future scoped planning, while the current single-site shell now follows the install-level logo consistently.

- **2026-05-02 — Admin JS parse error no longer breaks Users modals after Pages editor changes**
  - Repaired a corrupted handoff between the shared Pages editor logic and the Gallery editor block in `biblioteca/admin.js`.
  - This removes the frontend syntax error that prevented later admin helpers such as `openUserModal()` from being defined at runtime.
  - Verified on localhost that the Users tab loads and the `Add User` modal opens again.

- **2026-05-02 — Change-password modal now saves the edited password correctly**
  - Fixed `biblioteca/admin.js` so the user modal posts `edit_password` in password-change mode instead of always posting `new_password`.
  - Removed the hardcoded white readonly username field style in the change-password modal and replaced it with the normal admin dark-theme readonly styling.
  - This restores end-to-end password changes for existing users through the admin UI.

- **2026-05-01 — Dev server start/stop scripts and session workflow**
  - Added `scripts/start-dev-server.ps1` and `scripts/stop-dev-server.ps1` so the local PHP dev server uses a single repo-local start/stop path.
  - Added a workspace `Stop bandPromo webserver` task alongside the existing auto-start task.
  - Added a workspace `Restart bandPromo webserver` task for a clean stop/start cycle on port 8000.
  - Disabled terminal persistent sessions in the workspace so the dev-server terminal is not restored across VS Code restarts.

- **2026-04-30 — Google Drive Git protection script**
  - Added `scripts/protect-google-drive-git.ps1` to relocate `.git` outside the Google Drive-synced worktree.
  - The script also removes `desktop.ini` files from both the worktree and the relocated Git metadata directory.
  - This closes the gap that `.gitignore` cannot cover: Google Drive writing directly into `.git/refs`, `.git/logs`, and `.git/objects`.

### Documentation
- **2026-05-02 — README, features, and notices refreshed for current admin docs/pages state**
  - Updated `README.md` to include `faq.template.html` in the tracked first-time setup templates.
  - Updated `docs/FEATURES.md` so the feature list reflects the current Pages editor, audit trail, and documentation browser instead of the older Bio-only WYSIWYG wording.
  - Updated `docs/THIRD-PARTY-NOTICES.md` so TinyMCE and HTML Purifier descriptions match the current Pages editor and `save-page.php` sanitization path.

- **2026-05-02 — Example v0.8 page document added to roadmap planning**
  - Added an illustrative first JSON page document to `ROADMAP.md` so the static-page redesign is anchored to a concrete schema shape rather than only prose.
  - Included representative block types such as heading, paragraph, image, quote, list, divider, and callout, plus semantic image presets.
  - Documented the rule that the first real schema must be able to carry typical `bio` and `faq` content without falling back to raw HTML authoring.

- **2026-05-02 — v0.8 static page schema direction documented**
  - Expanded `ROADMAP.md` with a concrete `v0.8` static-page content model: JSON block documents as the source of truth, rendered HTML for delivery, semantic image presets, and a legacy HTML migration window.
  - Replaced the broad page-model placeholders in `TODO.md` with actionable planning tasks for schema shape, image presentation presets, renderer rules, migration, and editor replacement.
  - Kept this explicitly in post-`v0.7` planning so the platform can finish `v0.7` before taking on the page-model redesign.

- **2026-05-02 — Structured page-content redesign moved into v0.8 planning**
  - Updated `ROADMAP.md` so core/v0.7 language no longer locks static pages to a long-term WYSIWYG HTML model; the current requirement is usable operator-facing page editing.
  - Added explicit `v0.8` planning scope for structured static-page content: block JSON as the authoring source with rendered HTML delivery.
  - Updated `TODO.md` so the page-editor replacement and JSON page model are tracked as post-`v0.7` planning work instead of pressure on the `v0.7` finish line.

- **2026-05-02 — TODO post-v0.7 planning structure clarified**
  - Renamed the stale Admin UX heading in `TODO.md` from a planned proposal to a follow-up section, reflecting that most of that work has already shipped.
  - Replaced `Next after v0.7` with a clearer `Post-v0.7 planning` block and nested the PWA/offline items under it so they no longer read like hidden `v0.7` exit gates.
  - Used the cleanup pass to keep the TODO aligned with recent admin and documentation work rather than leaving completed wording behind.

- **2026-05-02 — TODO clarified around Admin UX forms vs media-handling metadata work**
  - Updated `TODO.md` so the Admin UX section now reflects the current shipped direction: operator-facing forms for supported config instead of a planned raw JSON return path.
  - Clarified that future scoped config structure remains internal and should not be surfaced to operators as raw JSON editing work.
  - Reworded the open metatag item so it is clearly the admin/editor surface for broader media-handling work, rather than a separate policy track.

- **2026-05-02 — Third-party notices inventory added**
  - Added `docs/THIRD-PARTY-NOTICES.md` to document the currently verified third-party libraries, build tools, hosted scripts, and external service endpoints used by bandPromo.
  - Updated `README.md` to link the new notices document from the main documentation and license sections.
  - Recorded current verified components including Pillow, Mutagen, Chart.js, FFmpeg, the Ko-fi widget, and the Cloudflare speed-test endpoint, while keeping future editor/sanitizer candidates out of the live inventory until actually adopted.

- **2026-05-02 — Current single-brand model vs future scoped model clarified**
  - Updated `MEDIA-HANDLING.md` to distinguish the current exposed operator model (`one branded site`) from the future internal scoped model (`brand`, `theme`, `social`, then install/release inheritance later).
  - Moved logo/poster planning language under clearer `brand` terminology so the docs no longer blur identity assets together with theme or social concerns.
  - Updated `TODO.md` to record that the future identity-asset rule is now locked while keeping multi-release concepts out of the current admin UI.

- **2026-05-02 — Future identity wording normalized to poster/share image**
  - Updated `MEDIA-HANDLING.md` so future release-identity planning now consistently refers to the social poster asset as `poster / share image` instead of only `share image`.
  - Kept this wording change internal to planning/docs so the current admin UI can stay simple and single-release for operators.

- **2026-05-02 — Future identity asset rules clarified for multi-release planning**
  - Updated `MEDIA-HANDLING.md` so the future model now treats the site-level logo and poster/share image as mandatory fallback identity assets.
  - Clarified that releases may override both logo and poster/share image in the future multi-release model, while current admins should still see only the simple single-release mental model.
  - Updated `ROADMAP.md` and `TODO.md` so the planning work now reflects mandatory install fallbacks plus optional release-specific identity overrides.

- **2026-05-01 — PWA/service-worker audit added to planning work**
  - Updated `TODO.md` so offline/PWA work now explicitly includes a service-worker audit, cache-busting review, and installed-phone success criteria.
  - Expanded `MEDIA-HANDLING.md` to state that the service worker is part of the playback architecture and must be audited for stale-cache risk, update behavior, and real user value.
  - Updated `ROADMAP.md` so the installed PWA experience is now treated as core product infrastructure rather than a one-time install feature.

- **2026-05-01 — Offline audio planning reframed as delivery architecture work**
  - Updated `TODO.md` so offline playback is no longer framed as a pure caching task; it now starts with replacing PHP-streamed audio delivery with a cacheable protected delivery model.
  - Expanded `MEDIA-HANDLING.md` with an explicit offline-playback architecture section covering the current PHP-streaming tension, the required sequencing, and the preferred long-term model.
  - Updated `ROADMAP.md` so scalable playback and offline-capable playback now point to the same architectural direction: PHP authorization with non-PHP byte delivery.

- **2026-05-01 — Scoped config migration contract added to planning docs**
  - Expanded `MEDIA-HANDLING.md` with a staged migration plan from the current `site` / `social` / `media` fields to the future scoped schema.
  - Documented compatibility-read priority, dual-write transition rules, and the first safe migration targets for runtime and admin work.
  - Updated `TODO.md` and `ROADMAP.md` so the next implementation steps now explicitly call for compatibility reads before any schema-first UI or legacy cleanup.

- **2026-05-01 — Future schema naming direction added to planning docs**
  - Expanded `MEDIA-HANDLING.md` with proposed scoped schema blocks for install shell defaults, release identity/presentation, and track presentation exceptions.
  - Added a compatibility bridge from today's `site`/`social`/`media` field names into the future scoped schema.
  - Updated `TODO.md` and `ROADMAP.md` so the next planning step now includes migration rules from the current single-release config into the future multi-release schema.

- **2026-05-01 — Current config field-scope map added to planning docs**
  - Expanded `MEDIA-HANDLING.md` with a concrete transition map from today's `site`, `social`, and `media` fields into install-level defaults, release-level overrides, and track-level exceptions.
  - Updated `TODO.md` to mark the role/inheritance/field-scope planning decisions as documented and to add follow-up tasks for splitting mixed install-vs-release identity fields in the future schema.
  - Updated `ROADMAP.md` to state explicitly that the single-release `web-config` shape must be split deliberately before multi-release support lands.

- **2026-05-01 — Theme inheritance model added to planning docs**
  - Expanded `MEDIA-HANDLING.md` with an explicit inheritance model: install defaults, release overrides, and track-specific exceptions.
  - Updated `ROADMAP.md` and `TODO.md` so multi-release planning now treats inheritance rules as part of the platform architecture, not an implementation detail.

- **2026-05-01 — Media role and scope model added to planning docs**
  - Expanded `MEDIA-HANDLING.md` with an explicit media-role model covering theme assets, release covers, track covers, gallery media, and page illustrations.
  - Added scope language for install-wide, release-scoped, track-scoped, and page/module-scoped assets to support the future multi-release model.
  - Updated `ROADMAP.md` and `TODO.md` so multi-release planning now treats media-role clarity as platform groundwork rather than incidental terminology cleanup.

- **2026-05-01 — Build action matrix added to media-handling docs**
  - Added a concrete task-level orchestration model to `MEDIA-HANDLING.md` covering playlist scan, audio delivery, image delivery, social assets, and manifest generation.
  - Added an admin action matrix describing which tasks should auto-run, which should queue as heavy work, and when the operator should not be bothered with a build message.
  - Added a planning note in `TODO.md` to rename Files -> `System` to Files -> `Theme` if that panel continues to represent install-specific branding assets.

- **2026-05-01 — Build-pipeline refactor work added to planning docs**
  - Updated `ROADMAP.md` to state that the current `full` vs `optimize` split is too coarse for the `original` / `master` / `delivery` media strategy.
  - Updated `TODO.md` with concrete planning tasks for task-level build requirements, source-aware optimization, and clearer admin build wording.

- **2026-04-30 — Media tier strategy added to planning docs**
  - Updated `ROADMAP.md` to define the `original` / `master` / `delivery` media model as part of the product direction.
  - Updated `TODO.md` so v0.7 exit work now explicitly covers weak-source scenarios, master-building rules, and real delivery-target definitions.
  - Clarified that FEATURES/README should only advertise this workflow once the admin/build implementation is genuinely ready.

- **2026-04-30 — `METADATA.md` renamed to `MEDIA-HANDLING.md`**
  - Reframed the document from a narrow metadata contract into a broader media-handling policy.
  - Added the `original` / `master` / `delivery` tier model, realistic intake scenarios, and clearer language around what bandPromo can improve.
  - Updated internal documentation links in `README.md`, `TODO.md`, and `.github/copilot-instructions.md`.

- **2026-04-30 — Media handling policy expanded with intake matrix**
  - Added a concrete intake policy matrix to `MEDIA-HANDLING.md` covering accepted scenarios, autofixes, publish blockers, master targets, and delivery targets.
  - Defined a four-level validation severity model: hard blockers, publish blockers, warnings, and autofixable issues.
  - Reframed `optimal` as explicit delivery targets tied to actual UI and listening contexts.

- **2026-04-30 — Updated AGENTS.md with desktop.ini guidance**
  - Expanded documentation on desktop.ini files (Windows + Google Drive artifacts).
  - Noted that they corrupt `.git/refs/` if accidentally committed and require cleanup if they appear in remote refs.
  - Added desktop.ini issues to Common Pitfalls section.

### Changed
- **2026-04-30 — `content` merged into `social`; dead `build` branch removed**
  - Moved `keywords` and `categories` into the `social` branch so sharing, SEO, and manifest-facing fields live together.
  - Updated the Sharing tab and save endpoint so these fields are editable in the same branch-scoped UI as the other social metadata.
  - Removed the unused `build` branch from the current config, template config, and config-loader defaults because `speedtest_threshold_mbps` was no longer read anywhere.

- **2026-04-30 — Admin: branch-scoped Config -> Basics editor**
  - Changed `Config -> Basics` to edit only the `site` branch in `web-config.json`, matching the branch-scoped `Theme` and `Sharing` pattern.
  - Kept the existing raw-config save path under the hood so basics changes still use the normal validation and build-required flow.

- **2026-04-30 — Admin: dedicated Config -> Theme tab**
  - Added a new `Config -> Theme` sub-tab that edits only the `media` branch in `web-config.json`.
  - Introduced a smaller presentation-focused editor for logo, cover, and background paths instead of exposing the whole config in that tab.
  - Reused the existing raw-config save endpoint so theme changes still trigger the normal build-required flow.

- **2026-04-30 — Admin: new Content primary tab**
  - Added a dedicated "📄 Content" primary tab between Files and Config in the admin panel.
  - Moved Playlist, Gallery, and Bio management out of Config into Content sub-tabs (`?tab=content&cntab=playlist|gallery|bio`).
  - Config tab now contains only Basics and Sharing sub-tabs, reducing clutter.
  - Content tab uses its own URL parameter `cntab` to avoid collision with Config's `ctab`.

### Fixed
- **2026-05-02 — Bio editor now uses local rich text editing with server-side sanitization**
  - Added a self-hosted TinyMCE Community 8.5.0 integration for `admin.php` and `biblioteca/admin.js` so the Bio editor now offers rich text tools with a source-mode fallback instead of only raw HTML editing.
  - Added `biblioteca/list-page-images.php` so page content images can be selected from optimized local illustrations and photos rather than from original uploads.
  - Updated `biblioteca/save-bio.php` to sanitize saved HTML with HTML Purifier 4.19.0 and additional local URL rules, so unsafe markup and disallowed external image sources are stripped before `data/bio.html` is written.
  - Updated `docs/THIRD-PARTY-NOTICES.md` so TinyMCE Community and HTML Purifier are now listed as active vendored dependencies with their versions, licenses, and integration roles.

- **2026-05-02 — Internal runtime reads now use clearer brand-asset aliases**
  - Added compatibility read aliases in `biblioteca/config-loader.php` for install/release logo and poster assets so future schema work can refer to brand identity assets more clearly without breaking current config files.
  - Updated shared runtime consumers such as `share-tools.php`, `index.php`, `play/index.php`, and the Sharing admin bootstrap in `admin.php` to read logo/poster assets through the new brand aliases while keeping current behavior unchanged.

- **2026-05-02 — Optional theme assets can now be cleared intentionally in admin**
  - Added explicit `Clear` actions for background image, background video, welcome audio, and logged-in audio in `admin.php`.
  - Updated `biblioteca/admin.js` so clearing those picker-backed fields blanks the stored value safely instead of requiring manual workarounds.

- **2026-05-02 — Optional login-page media can now be intentionally disabled**
  - Added a non-empty config getter in `biblioteca/config-loader.php` so optional media fields can treat blank strings as "disabled" instead of as real asset URLs.
  - Updated `index.php` so empty background video/image and welcome/logged-in audio settings no longer emit empty media sources, the public login page no longer tries to play a missing welcome sound, and authenticated redirects no longer wait on a missing logged-in sound.
  - Updated `biblioteca/login.js` so the public page now handles "no background selected" cleanly by clearing the background instead of trying to render `url('')`.

- **2026-05-02 — Theme and sharing asset selection now use media pickers instead of raw paths**
  - Replaced the Theme and Sharing asset path inputs in `admin.php` with operator-facing selectors that show the chosen file name while keeping the internal media path hidden.
  - Added a reusable media picker modal in `biblioteca/admin.js` that reuses the existing uploaded media library by type, supports previewing files, and can jump straight into the upload flow when the needed file is not there yet.
  - Added picker styling in `biblioteca/admin.css` so asset selection stays consistent with the rest of the admin UI.

- **2026-05-02 — Basics and Theme config are now operator-facing forms instead of raw JSON editors**
  - Replaced the raw JSON textareas in `admin.php` for Config -> Basics and Config -> Theme with labeled form fields for the supported site and media settings.
  - Updated `biblioteca/admin.js` so those forms merge only the supported fields back into the full runtime config before saving, preserving unknown config keys instead of dropping them.
  - Added matching form layout and textarea styling in `biblioteca/admin.css` so the new admin UI stays consistent with the existing Sharing form.

- **2026-05-02 — Raw runtime config loading centralized for admin config editors**
  - Added `bandpromo_load_runtime_config_raw()` to `biblioteca/config-loader.php` so pages that need the exact stored config payload can load it through one shared helper instead of open-coding JSON reads.
  - Updated the Config -> Basics and Config -> Theme editors in `admin.php` to use that helper, removing the last ad-hoc `web-config.json` decodes from the live admin page while preserving current editor/save behavior.

- **2026-05-02 — Remaining page-level legacy config reads moved onto scoped getters**
  - Updated `play/index.php` so default OG/share metadata now resolves through the shared scoped config getters instead of reading `site` and `social` fields directly from decoded JSON.
  - Updated `admin.php` so the page-level site title and configured site URL now use compatibility-aware scoped getters instead of direct legacy `web-config.json` reads.

- **2026-05-01 — Local dev gallery no longer waits on PHP audio streaming**
  - Updated `play/index.php` to preload gallery items into the authenticated player page and expose a local-dev flag for the frontend.
  - Updated `biblioteca/gallery.js` to render from the preloaded gallery data first, avoiding an extra PHP gallery request when the Gallery tab opens on localhost.
  - Updated `biblioteca/player.js` so localhost playback uses direct static audio URLs instead of the PHP streaming endpoint, reducing single-thread dev-server contention while music is playing.

- **2026-05-01 — Admin copy kept the scoped migration internal**
  - Updated Config help text and card notes in `admin.php` to describe Basics and Theme in operator language instead of exposing raw branch names.
  - Changed the repair warning so missing internal config sections no longer surface scoped-schema names like `install` or `release` to admins.
  - Updated the Sharing tab preview seed values in `admin.php` to read through compatibility-aware scoped getters while keeping the UI itself single-release and non-technical.

- **2026-05-01 — Fresh config seeding now includes scoped schema values**
  - Expanded `biblioteca/templates/web-config.template.json` so fresh installs seed both the legacy branches and the new scoped `install.*` / `release.*` blocks.
  - Updated `biblioteca/setup-init.php` and the Admin config repair path in `admin.php` to sync legacy values into the scoped fields after template merges, preventing template placeholder drift in migrated configs.
  - Updated the older setup save endpoint in `biblioteca/save-config.php` to keep scoped fields synchronized across the seeded config, not only the edited `site` branch.

- **2026-05-01 — Setup/bootstrap paths now honor scoped config migration**
  - Updated `setup.php` to prefill setup values through the shared scoped-config resolver instead of reading only the legacy `site` branch.
  - Updated `biblioteca/setup-state.php` so setup-complete detection accepts the scoped release-title field during the migration window.
  - Updated the older `biblioteca/save-config.php` endpoint to mirror legacy site writes into the future scoped schema using the shared sync helper.

- **2026-05-01 — Dual-write config saves and first scoped runtime reads**
  - Updated `biblioteca/save-config-raw.php` and `biblioteca/save-social.php` so branch-scoped admin saves now mirror the first safe transitional fields into the future scoped config structure while preserving the current legacy fields.
  - Added shared scoped-field sync helpers in `biblioteca/config-loader.php` so the save endpoints use one mapping for legacy-to-scoped normalization.
  - Switched selected runtime consumers in `index.php` and `play/index.php` to read the scoped keys directly for release identity, release theme assets, install theme assets, and the release share image.

- **2026-05-01 — Scoped config compatibility reads in runtime helpers**
  - Added alias-aware config resolution in `biblioteca/config-loader.php` so future scoped keys such as `release.theme.cover` and `install.theme.logo` can fall back to the current single-release `web-config.json` fields.
  - Updated `biblioteca/share-tools.php` to read title, description, share image, author, and social fields through compatibility-aware `get_config()` calls instead of raw legacy branch access.
  - Verified that scoped keys resolve correctly against the current legacy config without changing the on-disk schema yet.

- **2026-05-01 — Theme panel rename and light config follow-up tasks**
  - Renamed the Files -> `System` panel to Files -> `Theme` in the admin UI to better reflect install-specific branding/design assets.
  - Updated site basics saves to refresh `site.webmanifest` immediately instead of always escalating straight to a rebuild prompt.
  - Updated sharing saves to refresh social/share assets and the manifest immediately when the light tasks succeed.
  - Updated theme saves so ordinary media-path changes no longer trigger a build prompt by default; changing the configured release cover now refreshes playlist metadata first and only leaves image optimization pending.

- **2026-05-01 — Admin upload refresh and System target routing**
  - Fixed `biblioteca/upload-media.php` so uploads from the Files -> System panel stay in `media/special` even when the file extension is audio or video.
  - Prevented System uploads from incorrectly flagging audio-build work just because the uploaded file happened to be `.mp3` or `.flac`.
  - Fixed `biblioteca/admin.js` so the visible file list refreshes after partial-success upload batches instead of only after all-success batches.

- **2026-04-30 — CSS fixes for small screens (≤430px)**
  - Added `display: flex; flex-direction: column; align-items: center` to `#mediaplayer` in the `max-width: 430px` breakpoint so player content centers correctly on small phones.
  - Hidden `.reflection` at `max-width: 430px` to reduce visual clutter on small screens.
  - Added `align-self: center` to `.playlist-track-cover` so cover art aligns vertically center within playlist items.
  - Fixed `.vscode/tasks.json` dev server task: wrapped `${workspaceFolder}` path in single quotes in `Set-Location` to handle spaces in the path correctly on Windows PowerShell.

## [Unreleased] - 2026-04-20

### Added
- **2026-04-30 — Admin playlist ordering**
  - New drag-and-drop playlist editor in Admin → Config → Playlist tab.
  - Tracks can be reordered by dragging; numbers update live.
  - Save button posts to new `biblioteca/save-playlist-order.php` which rewrites `play/playlist.json` immediately (no rebuild needed) and persists `data/playlist-order.json`.
  - `scripts/makePlaylists.py` now reads `data/playlist-order.json` on build: known tracks appear in saved order, newly added tracks are appended at the end. Build also writes/updates the order file to keep it consistent.

### Changed
- **2026-04-30 — Player UI terminology and layout refactoring**
  - Renamed `#main-content` → `#content-container` and `.lyrics-toggle` → `.content-toggle` across PHP, JS, and CSS.
  - Elevated Gallery to a top-level content tab alongside Lyrics, Playlist, and Bio.
  - Moved band logo out of the Bio section into a new `.content-logo` area displayed above the tab bar.
  - Simplified Bio to a static page: removed inner "The Band" / "The Visuals" sub-tabs and all `toggleBioTab()` logic.
  - Gallery tab now owns `#visualsGallery` and triggers `loadVisualsGallery()` on first activation.
  - Removed `.bio-header`, `.bio-logo`, `.bio-tabs`, `.bio-tab-btn`, `.bio-tab-content` CSS; added `.content-logo`, `.content-logo-img`, `.gallery-box` styles.

- **2026-04-30 12:56 local — Stability hardening and config cleanup**
  - Fixed login-page bootstrap failure caused by FAQ include hard-exiting when `data/faq.html` was missing.
  - Added same-origin speed-test fallback endpoint (`biblioteca/speed-test.php`) and improved login speed-test diagnostics.
  - Removed duplicate invalid service-worker registration path from `biblioteca/login.js`.
  - Added localhost canonicalization in `biblioteca/https.php`: loopback hosts now redirect to `http://localhost` (port/path/query preserved).
  - Hardened build preflight to seed required runtime files from templates.
  - Updated build JSON validation to accept both object and array roots (fixes `gallery.template.json` preflight failure).
  - Added repository `.editorconfig` UTF-8 defaults.
  - Removed deprecated `build.generate_lq` from template/default/current config and loader defaults.
  - Updated `docs/AGENTS.md` with UTF-8, English-only, and timestamped changelog-note conventions.
- **Documentation review for v0.7 build 185**
  - Updated roadmap build number to match `VERSION`.
  - Updated feature and metadata docs to reflect admin build-log metadata validation output.
  - Rewrote `SECURITY-AUDIT.md` from an old vulnerability list into a current-state audit with remaining findings.
  - Cleaned one non-English code comment in `scripts/makePlaylists.py`.
- **Refactor: naming cleanup** (Phases 1–5)
  - `scripts/makeLQ.py` → `scripts/optimizeMedia.py`
  - `LQ/` player directories → `optimal/` throughout; `media/audio/LQ/` → `media/audio/optimal/`; `media/img/LQ/` → `media/img/optimal/`
  - `scripts/makeConfig.py` → `scripts/makePlaylists.py`; output `play/config.json` → `play/playlist.json`
  - `pwa-debug.php` → `biblioteca/pwa-debug.php`
  - `web-config.example.json` → `biblioteca/web-config.example.json`
  - `generate_manifest()` inline function extracted to `scripts/makePWA.py`
  - All path references, exclusion lists, and docs updated to match

---

## [0.7] - 2026-04-19 (build 60)

### Added
- **Setup wizard** — full 4-step first-run wizard (account → site config → upload → build)
  - Auto-creates required directory structure on load
  - Step 1: Creates admin account (seeds `data/terces`)
  - Step 2: Site info form, writes `web-config.json`
  - Step 3: Media upload with chunked upload (2 MB/chunk, bypasses PHP limits)
  - Step 3: Detects previously uploaded files and offers to reuse them
  - Step 4: Triggers build pipeline with live log streaming
  - Redirects to admin panel when `data/.setup_complete` exists
- **Build pipeline** (`scripts/build.py`)
  - Sub-scripts (`makePlaylists.py`, `optimizeMedia.py`) stream output line-by-line via `Popen`
  - Build runs fully in background via `nohup` — no more gateway timeouts
  - Exit code written to log as `EXITCODE:N`; polling endpoint returns `success` bool
  - pip uses `--only-binary` to skip source compilation; falls back to importability check
  - `PYTHONIOENCODING=utf-8:replace` propagated to sub-scripts
- **UTF-8 stdout redirect** in all three build scripts for Python 3.6 ASCII locale
- `biblioteca/check-uploads.php` — lists existing HQ media files (no auth required)
- `VERSION` file introduced; format: `MAJOR.MINOR+BUILD`

### Fixed
- `Pillow>=9.0.0` → `>=8.0.0`, `python-dotenv>=1.0.0` → `>=0.19.0` for Python 3.6
- `Image.Resampling.LANCZOS` fallback for Pillow < 9.1
- `build.php` and `get-build-log.php` accept setup session (`$_SESSION['user']`)
- `setup-init.php` handles page-refresh by re-authenticating existing user

## [Unreleased]

### Security
- **Phase 5: Rate limiting protection** [PHASE5]
  - Created biblioteca/rate-limit.php - Rate limiting system
    - Per-user: Max 5 quiz submissions per minute
    - Per-IP: Max 100 total requests per minute
    - Uses session-based request tracking with 60-second rolling windows
    - Functions: check_submission_rate_limit(), check_ip_rate_limit()
    - Automatic cleanup of expired request timestamps
    - Handles proxied requests (Cloudflare, X-Forwarded-For)
  - Integrated rate limiting into save-score.php
    - Rate checks happen after CSRF validation
    - Returns 429 Too Many Requests when limits exceeded
    - Response includes retry_after and reset_at timestamps
    - Per-user limit prevents submission spam
    - Per-IP limit prevents brute force attacks from single source
  - Prevents: Quiz submission spam, brute force score guessing, DDoS

- **Phase 4: Completion verification & server-side scoring** [PHASE4]
  - Created biblioteca/quiz-validator.php - Server-side answer validation
    - calculate_quiz_score($quizType, $userAnswers) - Calculates score from answers
    - verify_score_integrity($quizType, $userAnswers, $submittedScore) - Validates integrity
    - Loads quiz data server-side to verify all user answers
    - Prevents client-side score tampering
  - Updated quiz.js to collect and transmit user answers
    - quizState.userAnswers[] - Tracks all answered questions
    - selectAnswer() now stores answer details (question index, user answer, question ID)
    - saveScore() now includes full answers array in POST data
    - Example payload: { quizType, score, answers: [{questionIndex, answer, questionId}], csrf_token }
  - Integrated validation into save-score.php
    - If answers provided, server-side calculates expected score
    - Compares submitted score vs calculated score
    - Returns 400 Bad Request if mismatch detected (score tampering)
    - Prevents: Fake score submissions, modified answers, impossible high scores
  - Prevents: All forms of answer/score tampering

- **Phase 3: CSRF token protection** [542a75f]
  - Created biblioteca/csrf.php - CSRF token management helper
    - generate_csrf_token() - Creates and stores token in session (1 hour validity)
    - validate_csrf_token() - Validates token from request data
    - Tokens use cryptographically secure random_bytes (32 bytes)
    - Hash comparison with hash_equals() to prevent timing attacks
  - Added CSRF token validation to /biblioteca/save-score.php
    - Rejects requests without valid token → 403 Forbidden
    - Prevents cross-site request forgery attacks
  - Updated index.php to generate and expose CSRF token
    - Token made available as JavaScript variable
    - Stored in sessionStorage for use on other pages
  - Updated quiz.js to include CSRF token with score submissions
    - Automatically reads token from sessionStorage
    - Sends with every scoring request
  - Prevents: Cross-site request forgery

- **Phase 2.5: PHP API self-protection** [051e198]
  - Added HTTP Accept header validation to all API endpoints
  - `/biblioteca/quiz.php` now blocks direct browser requests → 403 Forbidden
  - `/biblioteca/save-score.php` now blocks direct browser requests → 403 Forbidden
  - `/biblioteca/get-highscores.php` already has header validation
  - `/biblioteca/get-gallery-items.php` already has header validation
  - Detection logic: If Accept header has text/html WITHOUT application/json → browser request → reject
  - Legitimate API calls (accept: application/json) still work → 200 OK
  - Prevents accidental data exposure if PHP files are accessed directly

- **Phase 2: Score validation & integrity checks** [57abe79]
  - Added score validation in /biblioteca/save-score.php
    - Loads quiz structure to calculate maximum possible score
    - Rejects scores > maximum quiz score (prevents impossible high scores)
    - Rejects negative scores (score >= 0)
    - Prevents leaderboard manipulation via fake score submissions
  - Scores now validated against actual quiz content
    - Each question = 1 point
    - Max score = number of questions in quiz
    - Example: 10 questions = max score 10
  - Fake score submissions now return 400 Bad Request with error details

- **Phase 1: Closed data exposure vulnerabilities** [472ec46]
  - Removed quiz answer exposure from /biblioteca/quiz.php API
    - Historical note: the current `quiz.php` implementation again includes `correct` for client-side feedback while keeping server-side scoring authoritative.
    - If quiz-answer secrecy matters, remove `correct` from the API response again and move feedback behind server validation.
  - Created /biblioteca/get-highscores.php - Secure leaderboard API
    - Requires session authentication
    - Blocks direct browser access (403 Forbidden)
    - Allows JavaScript API calls (200 OK)
    - Replaces deprecated get-top-scores.php (now redirects)
  - Created /biblioteca/get-gallery-items.php - Secure gallery API
    - Same security model as get-highscores.php
    - Protects gallery data from direct access
  - Updated .htaccess to route all data files through secure APIs
    - quizbase-*.json → /biblioteca/quiz.php
    - highscores.json → /biblioteca/get-highscores.php
    - gallery.json → /biblioteca/get-gallery-items.php
    - web-config.json → /biblioteca/get-config.php
  - SECURITY-AUDIT.md created with full vulnerability analysis

- **Protected web-config.json from direct browser access** [ec4d09e]
  - Created biblioteca/get-config.php - Secure configuration API controller
  - HTTP header validation: Blocks direct browser requests (Accept: text/html)
  - Allows legitimate JavaScript/API requests (XMLHttpRequest, application/json)
  - Returns appropriate HTTP status codes (403 for browser, 200 for API)
  - .htaccess redirects direct web-config.json access through security controller
  - Prevents accidental exposure of sensitive configuration data

### Added
- **Info lightbox extraction** - Moved login page info/about content to config-driven biblioteca/info-display.php
  - Replaces hardcoded HTML with configuration-based sections
  - Supports dynamic Q&A format with "heading" + "content" fields
  - Integrates with web-config.json for easy customization per project
- **--media-only flag** to deploy.py for uploading only media assets without code changes
- **Auto-play next song** when current track ends naturally
- **Null checks** in login.js functions to prevent errors on non-login pages
- **Dynamic gallery system** with JSON-based image management (biblioteca/gallery.js, gallery.php, gallery.json)
- **Error detection** on login to prevent audio playback on failed authentication
- **Comprehensive favicon setup** with all browser/device sizes (16x16, 32x32, 96x96, SVG, ICO) [7e520d6]
- **Apple mobile web app support** with home screen icon, app title, and status bar styling [7e520d6]
- **PWA manifest enhancements** with maskable icon support for Android 12+ adaptive icons [7e520d6]
- **web-config.json system** for flexible multi-project deployment [9dc25f6]
  - Site configuration (name, description, URL, author, language)
  - Branding settings (theme colors, backgrounds, accents)
  - Social media configuration (Twitter, Facebook, Instagram handles)
  - Content categories and keywords
  - Build options (LQ generation, speed test threshold)
- **biblioteca/config-loader.php** for centralized configuration management [9dc25f6]
  - Loads web-config.json with graceful defaults
  - Provides get_config() helper for dot notation access
- **biblioteca/share-tools.php** for centralized meta tag generation [9dc25f6]
  - generate_og_tags() - Creates Open Graph meta tags
  - generate_twitter_tags() - Creates Twitter Card tags
  - generate_standard_meta_tags() - Common meta tags
- **Automatic manifest generation** in build.py [9dc25f6]
  - Generates site.webmanifest from web-config.json
  - Populates PWA manifest with site configuration
  - Replaces hardcoded values

### Changed
- **Background media selection**: Changed from quality button choice to actual speedtest result [ef2a602]
  - Background image shown only for slow connections (< 5 Mbps 🐌)
  - Background video shown for faster connections (≥5 Mbps)
  - Makes background choice independent of manual quality selection
- **Unified lightbox system**: Consolidated three separate lightbox functions (openLightbox, openPromoLightbox, openGalleryImage) into single openLightbox() function for all image types
- **Speed test timing**: Changed from DOMContentLoaded to window.load event for accurate connection measurements
- **Pulse guide removal**: Now listens to audio player 'play' event instead of UI clicks for more reliable triggering
- **Playlist highlighting**: Now updates correctly when tracks change via triggerSongChange() animation
- **Meta tag generation**: Moved from hardcoded HTML to dynamic generation via share-tools.php [9dc25f6]
  - All index.php files now use generate_og_tags() and generate_twitter_tags()
  - Easier to customize per page/track
- **Icon organization**: Reorganized media/icons/ folder structure [9dc25f6]
  - Consolidated all icon files into media/icons/
  - Cleaner media folder structure

### Removed
- **Duplicate speed test** from player.js (testDownloadSpeed function removed)
- **Hardcoded gallery data** from player.js (now loaded from JSON)
- **All debug console.log statements** - kept only console.error for actual errors
- **Old band bio content** from index.php files (extracted to biblioteca/bio.php)
- **Hardcoded meta tags** from index.php files (replaced with config-driven generation) [9dc25f6]

### Fixed
- Speed test running before page fully loaded (media file not ready)
- **Keywords handling in share-tools.php** - Now supports both string and array formats [d64688b]
  - web-config.json uses strings for keywords, system now handles both transparently
  - Prevents PHP implode() error when keywords is a string
- Lightbox not working for gallery images (was using old CSS approach)
- Pulse animation not stopping when music started from hardware controls
- Playlist item not highlighting when song auto-advances
- Console errors on player page from login.js trying to access missing DOM elements
- Audio playback starting before authentication completed on login failure

### Technical Improvements
- Extracted static content to reusable includes (bio.php)
- Created modular gallery system matching quiz pattern
- Added proper authentication checks in gallery.php
- Improved code organization and reduced duplication
- Better separation of concerns (content vs logic)

---

## How to Update This File

When making changes, add them to the [Unreleased] section in the appropriate category:
- **Added**: New features or functionality
- **Changed**: Changes to existing functionality
- **Removed**: Removed features or code
- **Fixed**: Bug fixes
- **Technical Improvements**: Refactoring, optimization, code quality

When releasing a version, change [Unreleased] to the version number with date, e.g. [1.0.0] - 2026-03-31
