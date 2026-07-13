# Changelog

All notable changes to this project will be documented in this file.

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

2026-05-16 16:32 - Audio metadata saves now trigger an automatic lightweight playlist scan immediately after the master update, so `play/playlist.json` and `play/playlist-validation.json` refresh without waiting for a manual Full Build; delivery publishing can still remain pending when those edits also require regenerated output files.

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
