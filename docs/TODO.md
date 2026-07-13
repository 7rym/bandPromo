# bandPromo TODO

Purpose: the short, practical working list for the **active v0.8 beta** milestone.

Reference: See [MEDIA-HANDLING.md](MEDIA-HANDLING.md) for the current media policy, operator guidance, and source-media handling rules. If you encounter build or playback issues, check that your source files meet the current requirements described there.

Rules for this file:

- Keep it tied to the roadmap, not random future ideas.
- Prefer short actionable items over long explanations.
- Order work from definition to implementation: policy, terminology, scope boundaries, and real-world cases must be listed before the coding tasks that depend on them.
- Group tasks by meaning, not by implementation history. Do not mix decision work, UX work, and coding work under the same heading just because they were discovered together.
- Move completed items to the bottom or remove them when they no longer help. Keep completed items in place only when they clarify ordering, preserve locked policy decisions, or explain why later implementation tasks are blocked/unblocked.
- Use this as the first checkpoint when resuming work after a break.

## Current milestone

**v0.8 beta (active) — the management machine** — catalog, media, brands, containers, delivery scaling, and content AI wizards. Prepare everything operators need to manage releases and identity before v0.9 access tiers and v2 marketing automation.

**v0.8.5 hotfix slice (2026-07-09 — 2026-07-10):** closed-beta recovery after builds 302–305 Site-update gaps and player/catalog regressions on hosted installs. **Shipped:** monotonic build ranking, Plesk/Linux publish launcher fixes, delivery-gated streaming, ISO date fields, playlist-from-release metadata, future playlist visibility, demo catalog hide toggle (Settings + Welcome nudge), Site update dev-host reliability, ahead-of-published developer state. **Still open from earlier slices:** backup/export MVP, page container metadata + OG wiring, v0.8 management slice (Brand, Visual pool, AI wizards).

**v0.8.4 working slice (2026-07-01):** legacy cleanup, VERSION session format, Release editor, initial site seed rename — largely complete; visual media policy remains open. See **v0.8.4 active slice** below.

**v0.8.3 working slice (2026-06-16):** closed-beta feedback after build 292 — operator trust, invisible maintenance, playlist `kind` fix, Content editor UX parity, Release editor. Most items shipped in builds 295+.

**v0.7 is complete.** All exit gates passed by 2026-06-15. Repository version line is **`v<major>.<minor>.<session> build <number>`** (for example `v0.8.5 build 319`; build numbering continues from v0.7).

| Priority | Scope | Status |
|----------|-------|--------|
| 1 | Admin package updater | **Shipped** |
| 2 | Block-based page editor + presentation | **Shipped** |
| 3a | Unified Content editors + upload-time delivery automation | **Shipped** |
| 3b | Platform model: multi-playlist/gallery, module blocks, delivery architecture | **Active** |
| 4 | v0.8 management slice: Brand, Visual pool, role tags, content AI wizards | **Active — primary focus** |
| 5 | Analytics storage: ActivityStore, SQLite events, rollups, legacy log migration | **Active — v0.8 data foundation** |

Access-tier **implementation** and Chromecast **implementation** belong to **v0.9+**; their **definitions** must be stable in v0.8 first. **Analytics storage implementation** also belongs to **v0.8** so beta installs are not crushed when v0.9 opens access.

Reference: see `ROADMAP.md` for milestone structure and beta-tester expectations.

## v0.8.3 active slice (2026-06-16 beta feedback)

Policy and operator messaging — **lock before implementation**:

- [x] Close legacy `data/bio.html` / `data/faq.html` import scope: all betatesters on current JSON pages; recovery is manual copy only if old files exist on host backups.
- [x] Lock **operator update contract**: Site update preserves `web-config.json`, `.env`, `data/`, `media/`, `log/`; one follow-up **Update the live site** (Publish) is normal after every package update — not a failure state.
- [x] Lock **invisible maintenance** contract: config structure auto-repair and content-model preparation run automatically before Publish; no separate operator-facing “content model upgrade” card in normal workflow.
- [x] Lock **container presentation fields** for shareable containers: `description`, `poster_asset_id` on playlists and pages; extended **release EPK** fields on releases (see [PLATFORM-MODEL.md](PLATFORM-MODEL.md)). *(Release + playlist fields shipped in editor + storage; page fields + OG runtime wiring still open.)*
- [x] Lock **v0.8 playlist kind rule**: operator-created playlists are **`system`** until user/VIP playlists ship in v0.9+; fix current bug that creates `kind: "user"` (invisible to player).

Implementation order (v0.8.3):

Trust and operator calm:

- [x] **Config auto-repair** — silently deep-merge missing `web-config.json` sections from template on admin load (same as Settings → Repair today); audit-log only; remove scary “Incomplete config” banner for operators.
- [x] **Publish preflight** — before build tasks, run content-model preparation (`content-autofix` pipeline); apply when needed; plain-language Publish status only; hide Dashboard **Content model upgrade** card once integrated.
- [x] **Post-update notification copy** — after Site update, nudge **Update the live site** once with success-first wording (not “Publish prep did not finish automatically”).
- [x] **Backup/export MVP** — Admin → System → Backup & export: queue export archives in `backups/`, poll until ready, download/delete; import ZIP with restore/migrate modes and component picker (2026-07-13). Setup-time import wizard remains open per [PORTABILITY.md](PORTABILITY.md).

Platform fixes:

- [x] **Playlist `kind` bug** — create/save operator playlists as `kind: "system"`; migrate existing `user` playlists on existing installs during Publish preflight; player selector appears when **two or more system playlists** exist.
- [x] **Release editor** — operator UI for `container.release` (create/edit releases, track membership, lock state) using existing `data/releases` storage.
- [x] **Container marketing metadata — pages (storage only)** — playlist/release fields shipped; page `description`, `short_description`, and `poster_asset_id` in document storage + editor. **OG/share runtime wiring deferred to v0.9** (anonymous access).

Content editor UX (match Themes pattern):

- [x] **Edit header pattern** — inline editable name in header; **← Back** aligned right on Playlist, Gallery, and Pages edit views (Themes already ships this).
- [x] **Pages richtext toolbar** — pin toolbar outside the scrolling text area (sticky header or split block chrome) so long blocks remain editable.
- [x] **Pages delete control** — move page delete to pool rows (like Playlist/Gallery); remove delete from edit header.

Deferred (documented, not v0.8.3):

- [ ] Rich share cards and player playlist presentation on mobile (after metadata fields + Release editor).
- [ ] News/tour archive module (v1+); pages + galleries cover interim tour content.
- [ ] User/VIP-authored playlists (`kind: "user"`) — v0.9+ after access model implementation.

## Build pipeline rework (2026-07-01 — policy before code)

Reference: [BUILD-PIPELINE-AUDIT.md](BUILD-PIPELINE-AUDIT.md).

Policy — **lock before implementation**:

- [x] Lock **target stage order**: preflight (tools) → site shell (theme/config/social/PWA inputs) → catalog (masters/registry) → deliverables (from registry) → artifacts (playlist.json, share crops, manifest) → initial layout seed (setup/recovery only).
- [x] Lock **deliverable scope**: **every registered asset**, independent of release/playlist membership.
- [x] Lock **prune rule**: deliverables removed **only on asset delete**, not on playlist/release membership change.
- [x] Lock **publish must not mutate catalog**: move `content-autofix` out of publish preflight; explicit **Repair catalog** action with dry-run.
- [x] Lock **initial layout seed** (formerly “compose”): **setup** + explicit **recover layout from disk** only; never routine publish; rename in UI/docs.

Implementation order:

- [x] Phase A — stop the bleeding (remove autofix from publish, fix validation UX labels).
- [x] Phase B — stage runner skeleton (skippable stages, structured log).
- [x] Phase C — catalog stage (masters for all originals).
- [x] Phase D — registry-scoped deliverables (`optimizeMedia.py` decoupled from playlist scope).
- [x] Phase E — artifacts stage (`makePlaylists.py` after deliverables).
- [x] Phase F — demote layout seed to setup-only (`run-layout-seed.php` + `scripts/initialSiteSeed.py`; removed from `build.py`).

## v0.8 management slice (Brand + Visual pool + content AI)

Primary focus after hotfix stability. Policy locked 2026-07-11 — see [PLATFORM-MODEL.md](PLATFORM-MODEL.md), [MEDIA-HANDLING.md](MEDIA-HANDLING.md), [ROADMAP.md](ROADMAP.md).

**Product framing:** v0.8 = **management machine**; v2+ = **marketing machine** (campaign automation from existing content).

### Brand (replaces Theme)

Policy — **locked**:

- [x] Lock **Brand replaces Theme**: colors, typography, mood narrative, and asset refs live in `data/brands/` — not a separate Theme container.
- [x] Lock **many releases → one brand** via release `brand_id` (singles, EPs, album, post-album singles in the same era).
- [x] Lock **release cover on release**: `poster_asset_id` picked from Visual pool with brand filter; not stored inside the brand document.
- [x] Lock **install default brand**: seed locked `bandpromo-default` on first install; duplicate as suggested first customization task.
- [x] Lock **upload role tagging**: contextual uploads inherit role + brand; bulk Visual uploads default to `role: unassigned` — never block upload on role selection.
- [x] Lock **`special` is legacy intake only**, not a brand role — migrate `media/special/` into Visual pool with explicit role tags.
- [x] Lock **system shell vs brand overlay**: platform owns layout and dark-shell baseline; brand replaces enumerated identity slots only; broken brand degrades to default, not a broken site (see [PLATFORM-MODEL.md](PLATFORM-MODEL.md) → Brands).

Implementation order:

- [x] **Brand storage + migration** — `data/brands/` registry; migrate `data/themes/` + `active_theme_id` → `active_brand_id`; compatibility reads. *(Shipped build 320.)*
- [x] **Content → Brands editor** — mood/keywords/tone narrative fields; duplicate + Set active; brand labeling in admin UI.
- [x] **Release editor brand picker** — `brand_id` on releases; inherit install default when empty.
- [x] **Player per-release brand** — resolve release `brand_id` at playlist/track load; swap CSS variables; brand alpha tokens in shared CSS.
- [x] **Login + player OG deferred** — remove Open Graph/Twitter from authenticated surfaces until v0.9; login uses active brand CSS tokens.
- [x] **Welcome nudge** — post-install checklist item to duplicate default brand and customize.

### Analytics and activity log storage (v0.8 data foundation)

Policy locked 2026-07-12 — see [ANALYTICS-STORAGE.md](ANALYTICS-STORAGE.md). **Ship in v0.8**, not v0.9: v0.9 opens wider access and higher concurrent load; beta sites must not still rely on full JSONL scans.

Policy — **lock before implementation**:

- [x] Lock **UTC at rest** for listener and audit timestamps (see [ACCESS-MODEL.md](ACCESS-MODEL.md)).
- [x] Lock **ActivityStore abstraction** — one ingest/query interface; no new features reading `log/*.log` directly.
- [x] Lock **SQLite as primary event store** at `data/analytics/events.sqlite` (WAL).
- [x] Lock **rollup-first admin reads** — dashboards and charts query materialized aggregates, not raw event scans.
- [x] Lock **legacy migration** — detect old JSONL logs on upgrade, import once into SQLite, delete legacy files (no dual-write).
- [ ] Lock **retention defaults** — raw events 90 days, rollups indefinite; export path in [PORTABILITY.md](PORTABILITY.md).

Implementation order:

- [x] **Activity store module** + SQLite schema/indexes + legacy import on first use.
- [x] **Wire ingest** — `log.php`, `admin-audit.php` append through activity store.
- [x] **PlaybackAnalytics rewrite** — query SQLite; hourly chart uses SQL aggregation.
- [x] **Legacy import** — migrate existing `log/` and `log/admin-audit/` into SQLite, delete JSONL daily files.
- [x] **Setup/bootstrap preflight** — require `pdo_sqlite` and bundled SQLite **3.8.0+** before install and setup continue.
- [ ] **Rollup maintainer** — expand daily user/track/device rollups beyond hourly.
- [ ] **Client batching** — player buffers warm events; rate limit on ingest endpoint.
- [ ] **Admin export** — JSONL/CSV dump for operator backup.

Deferred (uses the v0.8 store, not part of storage core):

- [ ] Offline log queue + sync (v0.9 — [DELIVERY-ARCHITECTURE.md](DELIVERY-ARCHITECTURE.md)).
- [ ] Drop-moment / concurrent-listener rollups (v2 marketing).

### Visual pool + delivery

Policy — **locked** (extends v0.8.4 visual media plan):

- [x] Lock **two media families**: `audio` and `visual` (images + video). Retire Illustrations / Photos / Video / Theme as product categories.
- [x] Lock **visual `ast_{ULID}` identity** and **explicit role tags primary** (see [PLATFORM-MODEL.md](PLATFORM-MODEL.md)).
- [x] Lock **brand filter** on Visual pool and pickers.
- [ ] Lock **format-by-content** and **dimension-by-context** rules in delivery (see [MEDIA-HANDLING.md](MEDIA-HANDLING.md)).
- [ ] Check in **delivery context registry** JSON (`scripts/delivery-contexts.json` or equivalent).

Implementation order:

- [ ] **Phase 0b — visual registry + migration** — register visual uploads at intake; backfill from `img/`, `photo/`, `video/`, `special/`; dual-read compatibility.
- [ ] **Phase 1 — format-aware delivery** — preserve alpha; sanity max dimensions per role; stop white-background flatten.
- [ ] **Phase 2 — multi-variant storage** — `media/visual/delivery/{asset_id}/{variant}`; per-asset variant manifest.
- [ ] **Phase 3 — Files → Visual** — single tab with brand/role/type filters; context pickers; variant gating in Content pools.

### Content AI wizards (v0.8)

Policy — **locked**:

- [x] Lock **v0.8 scope**: wizards fill missing **container/content** fields from release + linked brand canon — not v2 campaign automation.
- [x] Lock **prompt context contract**: release facts, EPK fields, brand mood/keywords/tone, role-tagged asset refs.
- [ ] Lock **operator API settings**: provider, keys, limits, disclosure for AI-generated assets (`origin: ai-generated`).

Implementation order:

- [ ] **Settings → AI** (or Integrations) — operator-configured model endpoints and safe defaults.
- [ ] **Wizard entry points** — Release EPK, Pages, playlist/page descriptions, optional metadata/alt text.
- [ ] **Draft → confirm → save** — generated text/assets stay drafts until operator confirms; assets enter Visual pool with role + origin tags.

## v0.8.4 active slice (visual media — merged into management slice above)

Legacy heading kept for changelog references. **Do not start new work under this heading** — use **v0.8 management slice** instead.

Policy — **lock before implementation**:

- [x] Lock **two media families**: `audio` (unchanged) and `visual` (images + video). Retire Illustrations / Photos / Video as product categories.
- [x] Lock **visual `ast_{ULID}` identity**: extend `data/assets/registry.json` to all visual uploads; containers reference `asset_id`, not legacy folder paths.
- [x] Lock **tags-over-folders**: explicit role tags primary; `brand_id` on assets; picker brand filter (see [PLATFORM-MODEL.md](PLATFORM-MODEL.md)).
- [x] Lock **picker filter contract** per admin context (track cover, release cover, gallery, page picture, brand logo, shell background, share source).
- [ ] Lock **format-by-content** rule: delivery codec follows alpha and role requirements; no global JPEG flattening (see [MEDIA-HANDLING.md](MEDIA-HANDLING.md)).
- [ ] Lock **dimension-by-context** rule: delivery pixels target real UI surfaces (+ retina margin), not source upload dimensions.
- [ ] Complete **display-context audit**: verify seed matrix (logo 320px, playlist thumb 70px, card 320px, gallery grid 160px, lightbox, share crops) on phone/tablet/desktop; publish delivery context registry.
- [ ] Lock **variant set per role**: which of `thumb` / `card` / `lightbox` / `share` / video `poster` / `standard-stream` each reference context requires.

Implementation order (v0.8.4):

- [ ] **Phase 0b — registry + migration design**: visual asset registration at upload; autofix backfill from `img/`, `photo/`, `video/`, `special/`; dual-read compatibility layer.
- [ ] **Phase 1 — format-aware delivery**: preserve alpha (PNG/WebP); sanity max dimensions per role; stop white-background flatten.
- [ ] **Phase 2 — multi-variant storage**: `media/visual/delivery/{asset_id}/{variant}`; migrate off flat `optimal/*.jpg` and stem-based video paths.
- [ ] **Phase 3 — admin + pickers**: Files → Visual tab; context-filtered pickers; pool gating and validation on variant manifest.

## v0.8 active work

### Implementation order (2026-06-14 beta feedback)

Priority 1 — operator delivery:

- [x] **Priority 1:** ship the admin-panel package updater for hosted operators: version check against published `release-manifest.json`, plain-language update summary, download/verify/apply with runtime preservation, post-update tasks, and retry-safe failure reporting (spec already in `ROADMAP.md` and `INSTALL-UPDATE.md`).

Priority 2 — page editor and presentation (**complete**):

- [x] **Priority 2:** ship the page editor and presentation overhaul: block-based authoring, Width/Flow picture model, rich text toolbar, player-styled live preview, and image picker with thumbnails/upload.
- [x] Lock the first static-page JSON schema for v0.8: document metadata, ordered block array, and core block types (`richtext`, `picture`, `list`).
- [x] Lock the first page-image presentation model for v0.8: fraction widths + flow modes (not pixel sizing).
- [x] Define the server-rendering contract for JSON-backed pages: safe HTML output, allowed block rendering rules, and optional cached HTML artifacts.
- [x] Define the JSON-only page storage contract for v0.8 beta: `data/pages/*.json` as the sole runtime source, with HTML rendered at delivery time.
- [x] Plan and ship the page-editor replacement around the locked schema and block-based editing flow.
- [x] Design the first theme/config structure and player semantic color tokens so page presentation and future theme packs share one contract (see [PLATFORM-MODEL.md](PLATFORM-MODEL.md) → Themes).

Priority 3a — Content editors and delivery automation (**complete**):

- [x] Unify Content editors (Playlist, Gallery, Pages, Player layout) around one pool/result layout with shared headers, demo filter on media pools, and amber/green save controls.
- [x] Auto-run upload-time delivery tasks (audio, image, video) and gate Content pools on delivery-ready assets.
- [x] Surface background video delivery progress and failures in Notifications instead of blocking uploads.
- [x] Playlist save materializes pool tracks without requiring a full build; `initialSiteSeed.py` seeds initial playlist/gallery containers and player layout on setup (not legacy `play/playlist.json` / `data/gallery.json`).

Priority 3b — platform model (**active**):

Policy and model — **locked in [PLATFORM-MODEL.md](PLATFORM-MODEL.md)** (2026-06-15):

- [x] Lock the page composition model: **core blocks** vs **module blocks**; playlists/lyrics stay in the player (no page-embedded playlists).
- [x] Define multi-playlist libraries: playlists independent of releases; admin library UX; player **Playlists** tab selector above track list.
- [x] Define multi-gallery libraries: admin library UX; galleries placed via **module blocks** on pages; **remove Gallery player tab** when module blocks ship.
- [x] Define track deep links: path URLs `/play/{playlist}/{release-slug}/{track-slug}`; page links `/pages/{page-id}`.
- [x] Define gallery module presentation presets (grid, list, carousel, parallax, etc.) at product level.
- [x] Define asset identity: `ast_{ULID}` on-disk names, `data/assets` registry, operators never depend on filenames.
- [x] Define release locking: locked releases block track metadata edits; playlist reorder must not mutate masters.
- [x] Define the multi-release data model: explicit release records + required track membership; playlists reference tracks, not the reverse.
- [x] Define FAQ/login/shared-link model (see [ACCESS-MODEL.md](ACCESS-MODEL.md)).
- [x] Define access-tier rules for v0.9 implementation: admin/dev (all), VIP (per-release early-access default + per-track override), registered fan (released), anonymous (released-only + visible locked embargo rows). **No fan credits in v0.8/v0.9.**
- [x] Define core vs module boundaries and Config → Modules toggles (see [PLATFORM-MODEL.md](PLATFORM-MODEL.md) → Module registry).
- [x] Define exposure/distribution architecture: full playable/viewable media cast scope on delivery grants — **implement v0.9+** (see [DELIVERY-ARCHITECTURE.md](DELIVERY-ARCHITECTURE.md)).

Implementation slices (see [PLATFORM-MODEL.md](PLATFORM-MODEL.md) order):

- [x] Implement `data/assets` registry with `ast_{ULID}` IDs; migrate filename-keyed references to `asset_id`.
- [x] Implement `data/releases` + required track membership + release `locked` guards.
- [x] Remove playlist-save → master tag sync (`bandpromo_sync_playlist_order_to_audio_masters`) and `playlist_tracknumber` metadata fallbacks.
- [x] Implement `data/playlists` + registry; admin/runtime reads use containers (`play/playlist.json` remains a publish artifact refreshed by build/repair only).
- [x] Implement playlist selector in player **Playlists** tab; default = latest system playlist with `publish_date <= now`. Selector renders when two or more `kind: "system"` playlists exist.
- [x] Implement path deep links with per-release track slugs; embargoed tracks visible but not playable.
- [x] Implement `data/galleries` + registry; migrate off `data/gallery.json`.
- [x] Implement first gallery **module block** on pages (minimum: `grid` preset).
- [x] Implement `data/themes` + setup protected seed + duplicate + active pointer. *(Transitional — migrate to `data/brands/` + `bandpromo-default` in management slice.)*
- [x] Split `picture` (plain caption) and `picture_richtext` page blocks.
- [x] Remove Gallery player tab once page-embedded gallery modules cover the operator workflow.
- [x] Restructure admin IA: **Settings** (Basics, Theme, Support, Sharing), **System** (Publish + Audit); legacy `?tab=config|build|audit` redirects; notification-first publish nudging (no Build tab pulse).
- [x] **Audio delivery alignment** — `media/audio/optimal/` uses `ast_{ULID}.mp3` delivery names keyed off `master_filename`; `makePlaylists.py` / delivery scripts read playlist `master_file` order from `data/playlists/` (not `original/` scan); publish pass prunes orphaned legacy-name delivery files.
- [x] **Demo media git hygiene** — remove tracked `bandPromo_*` originals from git; bundled demo ships only via setup starter pack (`bandpromo-demo` locked release); document in `MEDIA-HANDLING.md` and `INSTALL-UPDATE.md`.
- [x] **Release editor** — operator UI for `container.release` (create/edit releases, track membership, lock state) using existing `data/releases` storage.
- [x] Rename protected system gallery id `main` to `bandpromo-demo`; migrate registry, page blocks, and templates.
- [x] **Demo catalog visibility** — install preference hides shipped demo release, playlist, gallery, and bundled `bandPromo_*` media from player, editors, and pickers; publish builds still process demo files on disk.
- [x] **Site update reliability** — writable probe for synced folders, HTTPS requires curl or openssl, ZipArchive advisory on local dev, ahead-of-published OK state when local VERSION exceeds the published package.
- [ ] Replace hardcoded player/share fallback meta values with fully config-driven defaults before anonymous/public access ships in v0.9.

Transitional schema work (in progress):

- [x] Define target `web-config.json` install-shell + pointers shape (see [PLATFORM-MODEL.md](PLATFORM-MODEL.md)).
- [ ] Replace the single-release `web-config` field names with explicit install, release, and track scopes in the future schema (implementation: dual-read migration).
- [x] Define which theme and asset fields are install defaults, which are release overrides, and which may be overridden per track.
- [x] Implement runtime compatibility reads so scoped config keys can fall back to current single-release fields.
- [x] Implement dual-write admin saves for transitional fields during the schema migration window.

Deferred to v0.9 (implement after v0.8 definitions are stable):

- [ ] Implement access-tier enforcement in playback and page delivery.
- [ ] Implement login/FAQ/shared-link + restricted anonymous entry UX.
- [ ] Implement Chromecast/cast send on the v0.8 delivery architecture.

Deferred to v1+:

- [ ] **Brand shell override runtime** — login applies active brand shell assets; player applies release brand shell assets; system-owned scrim for busy backgrounds (see [PLATFORM-MODEL.md](PLATFORM-MODEL.md) → System shell vs brand overlay).
- [ ] **Brand typography v2** — web/display font slots per brand.
- [ ] **Brand starter templates** — duplicate-only era/genre seeds (optional convenience, not a theme engine).
- [ ] Fan credits ledger and rebate/boon mechanics.
- [ ] News module with timed release and social push.
- [ ] Fanboard, feeds, and richer engagement modules beyond gallery.
- [ ] Define how support/payment providers fit fan-credit and merch flows: keep v0.7 support links/widgets config-driven until a reusable provider layer is needed.

### PWA offline audio caching and offline logging

Policy locked in [DELIVERY-ARCHITECTURE.md](DELIVERY-ARCHITECTURE.md):

- [x] Record the current v0.7 limitation: real-phone screen-off playback can fail during background continuation or next-track handoff — recognized limitation, fixed via delivery/state architecture not ad-hoc player patches.
- [x] Define the protected-audio delivery model: PHP authorization + cache-friendly static/signed delivery handoff.
- [x] Define offline/degraded modes per service class.
- [x] Define installed-PWA success criteria.
- [x] Lock architecture direction: no long-lived PHP audio byte streaming in the target path.

Implementation (after `data/` platform model):

- [ ] Audit `service-worker.js` end to end: exclusions, cache strategy, stale-asset risks, update behavior.
- [ ] Audit update propagation and cache invalidation for installed PWAs.
- [ ] Implement protected delivery handoff, player contract changes, service worker audio caching, cache eviction, and offline log sync.

### Deferred from v0.7 (still v0.8 scope)

- trial-use caching and update propagation: see PWA implementation slices in [DELIVERY-ARCHITECTURE.md](DELIVERY-ARCHITECTURE.md)
- [x] backup/restore operator flow definition — full site backup vs data export/import (see [PORTABILITY.md](PORTABILITY.md))
- [x] moved-site recovery and host-specific config repair flow (see [PORTABILITY.md](PORTABILITY.md))
- [x] Lock nondestructive naming: `ast_{ULID}` storage + `data/assets` registry; `original_filename` preserved; human names at UI/URL/export only (see [PLATFORM-MODEL.md](PLATFORM-MODEL.md))
- unified visual asset pool with role from references (not folder); gallery vs page illustration is usage-based (see [PLATFORM-MODEL.md](PLATFORM-MODEL.md))

## v0.7 exit gates (completed)


### Stability

Scope: runtime sturdiness and safe delivery. Put items here only when they affect whether setup, build, playback, auth, or core admin flows behave reliably under normal use.

- [x] Verify player behavior on desktop and mobile after recent logging changes.
- [x] Review remaining known issues in admin, build, auth, and player flow.
- [x] Confirm media upload -> build -> playback works end to end after recent cleanup.
- [x] Ensure that local user-specific files (e.g. configuration, uploaded media, personal data) are never overwritten by the repo during git pull, and are never committed back into the repository.
- [x] Enforce strict setup seeding for required runtime files (`web-config.json`, `data/gallery.json`, and `data/pages/*.json`) from tracked templates.
- [x] Remove silent runtime fallbacks for required content/config files and fail with actionable errors.
- [x] Remove quiz from the required core player flow so non-core features cannot destabilize playback.
- [ ] Add a pre-publish release guard: verify every `require_once` / `require` target in `admin.php` and other shipped entrypoints (`index.php`, `play/index.php`, `setup.php`) resolves to a **git-tracked** file before **Publish release package** runs (would have blocked builds 290–291 blank-admin incident).

### Trust

Scope: correctness and interpretability of observed behavior. Put items here when they affect whether logs, analytics, and admin reporting can be trusted to reflect real user behavior.

- [x] Review raw logs after the normalized `track_exited` change and confirm events match real behavior.
- [x] Verify analytics views still interpret old and new log formats correctly.
- [x] Decide final policy for `session_end`, inactivity tracking, and future `session_timeout` / `inactive_start` events.

### Reusability

Scope: reusable deployment, setup, and personalization. Put items here when they determine whether bandPromo can be installed again easily, configured without code surgery, and still feel specific to each deployment.

- [x] Test a fresh setup path on a real hosted server and document the friction points in the current Git/Plesk/private-repo deployment path.
- [x] Finish replacing the current repo-upload/Git-first installation story with an operator-first package story: `bootstrap.php` now discovers the published `release-manifest.json` asset as the authoritative operator source, defaults to the published release/version automatically, and no longer exposes the old manual/developer ZIP fallback in the normal install path.
- [x] Add an explicit distributable-package builder path that does not run on every build: a manual script/workflow should create install ZIPs only for builds that intentionally qualify as operator-facing packages.
- [x] Define the bootstrap installer contract: required PHP capabilities, release ZIP download/extract flow, writable-path checks, runtime-file seeding, failure handling, and safe re-entry behavior.
- [x] Define the update contract for ZIP-based releases: which tracked app files are replaced, which runtime/user-managed paths are preserved (`web-config.json`, `.env`, `/data`, `/media`, logs), and which post-update tasks or migrations run automatically.
- [x] Define the package source/version-check contract: GitHub-hosted immutable release ZIPs alongside the repo, with `VERSION` used as the first lightweight update check before download; mutable `main.zip` snapshots stay a developer/manual fallback only.
- [x] Define the admin-panel updater model around release packages instead of Git operations, including version checks, download/apply flow, integrity validation, restore-after-failure behavior, and operator messaging.
- [x] Define the release-observability model for installs and updates: GitHub release download counts as the passive baseline, plus an optional documented webhook/ping model for install/update events.
- [x] Define the install/update telemetry payload boundary before implementation: minimal event data, explicit opt-in, no audience tracking, no content data, and clear operator-facing disclosure/controls.
- [x] Define the setup-wizard consent UX for maintenance telemetry: a friendly plain-language question during setup, safe default behavior, and later admin controls for changing that choice.
- [x] Define and implement the setup-wizard acknowledgment UX for license and operator responsibility: friendly plain-language summary, in-wizard modals, explicit confirmation, recorded acceptance, and live verification on `bandpromo.site`.
- [x] Define the installation-identity model before premium modules exist: a locally generated install ID plus a stronger install secret/keypair stored in runtime state, so telemetry and later entitlements do not depend on a copyable plain UID alone.
- [x] Define the install-locked paid add-on entitlement model for future modules/themes/services: core remains fully usable without activation, but bandPromo-sold add-ons must bind to a stronger installation identity with transfer/reissue/recovery rules so copying files or a visible ID is not enough to clone access. Keep this separate from any future audience/member premium-access model inside an installation.
- [x] Confirm README/setup docs match the intended operator workflow, not only the current developer/server-admin path; the root README now stays operator-first while repository workflow details live in `docs/DEVELOPMENT.md`.
- [x] Decide the minimal first-run verification model for reusable installs: documented empty-state setup, seeded demo content, or both.
- [x] Fix localhost install/admin "Open site" link resolution so local setup and verification use the expected host.
- [x] Rename Files -> `System` to Files -> `Theme` if that panel remains the home for install-specific branding/design assets.
- [x] Define the explicit asset-scope model for install-wide theme assets plus release and track-level overrides.
- [x] Define the inheritance contract for install defaults, release overrides, and track-level exceptions.
- [x] Split the current mixed `site` identity fields into explicit install-shell fields vs release identity fields.
- [x] Define target schema names for install shell, release identity/presentation, and track exception fields.
- [x] Lock the future identity-asset rule: mandatory site-level logo/poster fallbacks, optional release-level logo/poster overrides, without exposing multi-release concepts in the current admin UI.
- [x] Define the migration and compatibility rules from current `site` / `social` / `media` fields into the future scoped schema.

### User Friendliness

Scope: end-user and operator usability at the interaction level. Put items here when they improve accessibility, layout behavior, clarity, or ease of use across real device/view scenarios.

- [x] Update CSS breakpoints for modern screen segments. Flow design for vertical views, grid for wide screens.
- [x] Test view at 360–430px (mobile) with vertical layout and --card-size: 260px
- [x] Test view at 431–767px (large mobile/small tablet) with vertical layout and --card-size: 400px
- [x] Test view at 768–1365px (tablet/small laptop) with grid and --card-size: 430px
- [x] Test view at >=1366px (desktop) with grid and --card-size: 600px
- [x] Test and improve landscape orientation on mobile (360–430px wide, ~360–430px tall): player now switches to a compact two-column landscape layout for coarse-pointer/mobile screens and uses tighter safe-area-aware spacing in standalone mode.
- [x] Test landscape on large mobile/small tablet (431–767px wide in landscape): compact landscape layout now triggers from a mobile/coarse-pointer landscape rule rather than the old low-height-only threshold.
- [x] Consider using `orientation: landscape` media queries to switch mobile views to a two-column layout when height is constrained

### Admin UX follow-up

Scope: operator-facing editing and control surfaces. Put items here when the question is how admins change, manage, or review things in the UI after the underlying policy and data model are already defined.

- [x] Replace Config -> Basics raw JSON with guided form editing for `site` basics.
- [x] Keep the future scoped config model internal and continue exposing supported settings through operator-facing forms instead of raw JSON.
- [x] Keep social editing in Sharing only (single source of truth for `social`).
- [x] Add a dedicated Config sub-tab for theme/media presentation settings (rename from low-level `media` wording to user-facing `theme`).
- [x] Audit `web-config` branches (`content`, `build`, `quizzes`) and move non-core branches out of base config where appropriate.
- [x] Implement the first operator-facing audio metadata repair tools in Files -> Audio so operators can fix common master fields without leaving admin.
- [x] Implement playlist editing
- [x] Implement gallery editing
- [x] Replace Playlist placeholder with real drag-and-drop track ordering UI.
- [x] Add playlist-style drag placeholder rows to the Gallery editor so reordering active gallery items shows empty drop targets between rows, matching the Playlist editor reorder UX.
- [x] Persist manual playlist order in `play/playlist.json` from admin edits.
- [x] Update build generation so existing manual playlist order is preserved and new tracks are appended at the end.
- [x] Replace Bio/FAQ-only editing with a Pages feature for editing multiple HTML pages.
- [x] Add WYSIWYG page editing mode with safe HTML handling and fallback source mode.

Admin UX note: metadata repair belongs to media handling and operator readiness for policy/behavior, while this section tracks the operator-facing editor flows. The first audio-master repair surface now exists; the remaining repair work is about deeper task routing, persistent issue visibility, and broader packaging workflows.


### Media handling

Scope: intake rules, media policy, validation, packaging, and repair behavior. Put items here when they define what source media is accepted, how it is classified, what gets generated, and how weak inputs should be diagnosed or fixed.

Policy already locked in docs:

- [x] Map realistic intake scenarios: perfect FLAC, WAV export with no tags, partial metadata, lossy-only source, filename-driven metadata, missing cover art, mixed-quality release sets.
- [x] Lock the three-tier policy: preserve user uploads as immutable originals while generating corrected masters and delivery derivatives separately.
- [x] Define the practical source-media policy: accepted source formats, weak-source scenarios, and what the platform repairs vs only warns about.
- [x] Formalize the three media tiers: `original` (untouched upload), `master` (bandPromo-authored canonical package), and `delivery` (publish-ready derivatives).
- [x] Define clear issue severity in media validation: hard blockers vs publish blockers vs warnings vs autofixable issues.
- [x] Decide how build-time metadata validation should warn operators about missing or weak tags: `play/playlist-validation.json`, build-log output, and admin build-log summary.
- [x] Decide when WAV should be converted into a tagged FLAC master and how lossy sources should be handled without false "quality upgrade" claims.
- [x] Define the master-tier rules for audio packaging: metadata, artwork, lyrics, naming, and downloadable corrected masters.
- [x] Lock the preferred operator workflow: preserve originals untouched, create or queue masters immediately after upload where supported, then treat masters as the normal admin-facing working assets while delivery variants regenerate in the background.
- [x] Redefine "optimal" media output into explicit delivery targets (thumbnail, mobile, lightbox/desktop, stream/download tiers).
- [x] Define the delivery-tier rules for images and audio based on actual UI/device needs rather than raw source size.
- [x] Lock the transition rule that current `optimal` folders represent legacy delivery outputs, not the future `master` tier, and that intake-format expansion must follow the `original` / `master` / `delivery` model rather than precede it.
- [x] Lock the bundled-placeholder policy: repo demo assets must be distinguishable from user uploads and hidden by default in normal operator media browsing.
- [x] Define which edits actually require playlist regeneration, audio delivery regeneration, image delivery regeneration, social asset generation, and manifest generation.
- [x] Separate release cover and track cover into explicit product concepts instead of treating `cover` as a loose inferred role.

Policy still to define before implementation:

- [x] Lock operator-facing media validation language focused on fixes, not raw tag terminology.
- [x] Define the first metadata editing tools needed in the file manager: simple metadata fixes should start with title, artist, release/album name, and lyrics on the audio-master surface; track-order fixes should route to Playlist, and cover fixes should route to the dedicated media/theme surfaces instead of a generic build notice form.
- [x] Define the first master-building tools needed in admin: the first repair layer should use actionable validation tasks plus deep links to the right editor, with later selective quick-edit for simple metadata fields; artwork embedding, lyrics embedding, filename cleanup, and corrected-master export/download should stay in dedicated metadata/master tools rather than bloating the Build tab.

Implementation follow-up after policy:

- [x] Surface metadata validation warnings in the admin UI build log after builds finish.
- [x] Implement the operator-facing validation summary outside the raw build log, using the locked `Cannot build` / `Fix before publish` / `Recommended fix` / `Can be repaired automatically` labels.
- [x] Add actionable validation actions from the Build summary to the right editor surfaces (`Edit metadata`, `Open playlist order`, and Files/theme/media when cover work is needed).
- [x] Refresh playlist and validation data automatically after audio metadata saves so file badges and warnings can update without waiting for a manual full build.
- [x] Keep embedded master track numbers aligned with operator playlist order by syncing reordered tracks back into masters and autofilling blank track tags from playlist position during metadata saves.
- [x] Suppress fresh build-required warnings for true no-op audio metadata saves while still preserving existing pending build state from earlier real changes.
- [x] Add a persistent operator task/notification panel for unresolved build and validation issues, driven by current system state and auto-resolved when the underlying issue is fixed instead of relying on manual checklist truth.
- [x] Add selective inline quick-edit for short metadata fields (artist, title, version, release/album name, track, release date, genre, BPM, key) only after the task/action model is in place; keep larger fields, cover work, and broader master-building in their dedicated editors.
- [x] Surface compact latest-build metadata health badges in Files -> Audio for Artist, Title, Release, Lyrics, and Cover so operators can scan file completeness without opening each track.
- [x] Add placeholder-origin and hidden-state support to media listing/picker flows so bundled demo assets are suppressed by default once a real install has user media in that group, and bundled delete actions hide them locally instead of pretending git-tracked demo files were truly removed.
- [x] Start the eager-master intake path for supported audio uploads by preserving originals in `media/audio/original/` and seeding a local working copy in `media/audio/master/` without changing current playback or delivery reads yet.
- [x] Backfill missing audio masters for older libraries automatically when Files -> Audio inspects preserved originals, so legacy installs do not leave operators stuck with persistent `Master pending` rows for supported FLAC/MP3/WAV sources.
- [x] Refactor build modes and UI wording so operators see task-specific actions instead of the ambiguous `Optimize Media` / `Full Build` pairing.
- [x] Break build-required tracking into concrete tasks instead of the current coarse `full` vs `optimize` split; pending work now records task units and can clear targeted work such as `image-delivery` independently.
- [x] Split the current optimizer into source-aware tasks; MP3 sources are now copied to delivery without unnecessary re-encoding, while FLAC/WAV sources still take the transcode path.
- [x] Finish audio delivery alignment: `optimal/` MP3s use `ast_{ULID}` names matching masters; build/playlist scripts use registry + playlist `master_file` (see Priority 3b slice above).
- [x] Video upload post-processing: generate thumbnail/poster frame from first frame (e.g. via ffmpeg) for gallery preview and lightbox cover
- [x] Video transcoding: add a separate build task that converts queued `.mov` / `.webm` sources into `.mp4` delivery assets with visible operator progress, instead of doing that heavy work during upload
- [x] Cover art (`media/img/`) management: distinguish build-generated covers from manually uploaded ones; prevent orphan accumulation; expose in admin file manager
  - [x] Phase 1: cover reference index + `list-media.php` enrichment (roles, origins, references, orphan flag)
  - [x] Phase 2: Illustrations panel badges, filters, and delete hints
  - [x] Phase 3: orphan prevention on cover replace and theme-cover refresh
- [x] Orphan detection: identify files in media/img/, media/photo/, media/video/ that are not referenced by any active gallery entry or playlist track, and expose in admin
- [x] Media deletion: warn when a file is still referenced by playlist or gallery data, let the operator choose whether to continue, and if they do, remove those references automatically and refresh the affected playlist/gallery state after delete
- [x] Video poster attribute: once thumbnail generation exists, write `poster` field into gallery.json entries and use it in gallery.js

### Beta operator readiness

Scope: first real tester/operator experience. Put items here when they concern help text, trial-use guidance, supportability, and whether non-technical testers can operate the system without expert intervention.

- [x] Review admin help text and identify remaining confusing areas for non-technical operators.
  - [x] Files tab: standardized permanent-action warning line across sub-tabs
  - [x] Files tab: list-header filters, master checkbox selection, and labeled Upload/Download/Delete bulk actions
  - [x] Welcome tab: setup checklist vs completed-install dashboard help text and layout; completed installs rely on the header inbox instead of duplicate dashboard task cards
  - [x] Login/player session expiry redirects back to login with a clear message
  - [x] Operator inbox: open focused modal instead of inline expanding drawer
  - [x] Operator inbox: plain-language copy for non-technical operators
  - [x] Admin IA: Settings + System tabs; Publish and Audit under System; legacy tab URL redirects
  - Remaining tabs (Analytics, Content): revisit from beta tickets/bug reports rather than pre-emptive rewrites
- [x] Write operator-facing installation guidance for the future bootstrap installer, with no assumption of Plesk, SSH, Git, Cloudflare, or shell/root access.
- [x] Write operator-facing update guidance for the future admin/package updater so hosted users can stay current without developer/server-admin tools.
- [x] Prepare a short tester checklist for the first closed beta.

### v0.7 cleanup (completed 2026-06-12)

- [x] Remove dead biblioteca endpoints superseded by newer APIs
- [x] Extract shared PHP helpers (`bandpromo_deep_merge`, JSON read/write, quiz input sanitize)
- [x] Deduplicate `delete-media.php` gallery/reference helpers against `media-reference-helpers.php`
- [x] Unify video poster path helpers in `gallery-helpers.php`
- [x] Hoist shared `admin.js` date/HTML escape helpers

Deferred to later refactors: split `admin.js` into modules, remove remaining `save-page.php` HTML sanitizer duplication if a shared page-save helper is introduced.

## Notes

- `ROADMAP.md` is the long-term direction and includes **beta tester expectations** for what is shipped vs planned.
- `TODO.md` is the short-term working list for the **active v0.8 beta** milestone.
- **v0.7 is complete** (exit gates passed 2026-06-15). Repository version line is **`v<major>.<minor>.<session> build <number>`** (continuous build numbering from v0.7).
- **v0.8 = management machine** — Brand (replaces Theme), Visual pool + role tags, release `brand_id`, content AI wizards, delivery scaling. See **v0.8 management slice**.
- **v0.9** — access-tier implementation, login/anonymous entry, user roles, Chromecast/cast implementation.
- **v2+ = marketing machine** — campaign automation and marketing AI from existing catalog content.
- Current operator model: one active brand (duplicate demo default), multiple releases/playlists/galleries — releases link to shared brands by era.
- If a task does not help ship or define the current v0.8 milestone, it probably belongs in the roadmap, not here.
