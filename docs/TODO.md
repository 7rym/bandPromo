# bandPromo TODO

Purpose: keep a short, practical list of what must be finished before moving from `v0.7` to `v0.8 beta`.

Reference: See [MEDIA-HANDLING.md](MEDIA-HANDLING.md) for the current media policy, operator guidance, and source-media handling rules. If you encounter build or playback issues, check that your source files meet the current requirements described there.

Rules for this file:

- Keep it tied to the roadmap, not random future ideas.
- Prefer short actionable items over long explanations.
- Order work from definition to implementation: policy, terminology, scope boundaries, and real-world cases must be listed before the coding tasks that depend on them.
- Group tasks by meaning, not by implementation history. Do not mix decision work, UX work, and coding work under the same heading just because they were discovered together.
- Move completed items to the bottom or remove them when they no longer help. Keep completed items in place only when they clarify ordering, preserve locked policy decisions, or explain why later implementation tasks are blocked/unblocked.
- Use this as the first checkpoint when resuming work after a break.

## Current milestone

Current target: finish `v0.7` cleanly before opening `v0.8 beta`.

Status: all `v0.7` exit-gate sections are complete. The first hosted bootstrap audit on `bandpromo.site` succeeded; published package **v0.7 build 279** is the current operator install source. **v0.8 priority 1 (admin package updater) is now shipped in admin.** Closed-beta feedback keeps the page editor/presentation overhaul as **v0.8 priority 2** before broader platform-model work begins.

Reference: see `ROADMAP.md` for the full milestone and release structure.

## v0.7 exit gates


### Stability

Scope: runtime sturdiness and safe delivery. Put items here only when they affect whether setup, build, playback, auth, or core admin flows behave reliably under normal use.

- [x] Verify player behavior on desktop and mobile after recent logging changes.
- [x] Review remaining known issues in admin, build, auth, and player flow.
- [x] Confirm media upload -> build -> playback works end to end after recent cleanup.
- [x] Ensure that local user-specific files (e.g. configuration, uploaded media, personal data) are never overwritten by the repo during git pull, and are never committed back into the repository.
- [x] Enforce strict setup seeding for required runtime files (`web-config.json`, `data/gallery.json`, `data/bio.html`, `data/faq.html`) from tracked templates.
- [x] Remove silent runtime fallbacks for required content/config files and fail with actionable errors.
- [x] Remove quiz from the required core player flow so non-core features cannot destabilize playback.

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
  - Remaining tabs (Analytics, Content, Build, Config): revisit from beta tickets/bug reports rather than pre-emptive rewrites
- [x] Write operator-facing installation guidance for the future bootstrap installer, with no assumption of Plesk, SSH, Git, Cloudflare, or shell/root access.
- [x] Write operator-facing update guidance for the future admin/package updater so hosted users can stay current without developer/server-admin tools.
- [x] Prepare a short tester checklist for the first closed beta.

Deferred to v0.8:

- trial-use caching and update propagation: aggressive safe caching, low needless re-downloads, no stale generated artifacts after updates (see Post-v0.7 planning → PWA offline audio caching)
- backup/restore operator flow definition
- moved-site recovery and host-specific config repair flow
- add a nondestructive naming layer for tracks and other media so operators can work with human-facing display names and aliases while the platform still preserves immutable original filenames as the source identity
- separate gallery media from page illustrations in the admin/build model so image behavior follows role, not only folder location

## Post-v0.7 planning

### v0.7 cleanup (completed 2026-06-12)

- [x] Remove dead biblioteca endpoints superseded by newer APIs
- [x] Extract shared PHP helpers (`bandpromo_deep_merge`, JSON read/write, quiz input sanitize)
- [x] Deduplicate `delete-media.php` gallery/reference helpers against `media-reference-helpers.php`
- [x] Unify video poster path helpers in `gallery-helpers.php`
- [x] Hoist shared `admin.js` date/HTML escape helpers

Deferred to later refactors: split `admin.js` into modules, remove remaining `save-page.php` HTML sanitizer duplication if a shared page-save helper is introduced.

### v0.8 implementation order (2026-06-14 beta feedback)

Priority 1 — operator delivery:

- [x] **Priority 1:** ship the admin-panel package updater for hosted operators: version check against published `release-manifest.json`, plain-language update summary, download/verify/apply with runtime preservation, post-update tasks, and retry-safe failure reporting (spec already in `ROADMAP.md` and `INSTALL-UPDATE.md`).

Priority 2 — page editor and presentation (beta blocker; complete before broader v0.8 platform definitions):

- [ ] **Priority 2:** ship the page editor and presentation overhaul: block-based authoring, semantic image presets, player-styled live preview, and a usable image picker with thumbnails/upload instead of the current TinyMCE + filename dropdown workflow.
- [ ] Lock the first static-page JSON schema for v0.8: document metadata, ordered block array, and a narrow first-party block set.
- [ ] Lock the first page-image presentation model for v0.8: semantic responsive presets instead of pixel sizing.
- [ ] Define the server-rendering contract for JSON-backed pages: safe HTML output, allowed block rendering rules, and optional cached HTML artifacts.
- [ ] Define the migration path from `data/bio.html` and `data/faq.html` into JSON-backed pages, including the compatibility window.
- [ ] Plan the page-editor replacement for v0.8 around the locked schema and block-based editing flow rather than raw HTML authoring.
- [ ] Design the first theme/config structure and player semantic color tokens so page presentation and future theme packs share one contract.

Priority 3 — platform model (after updater + page editor):

- [ ] Define the `v0.8` multi-release data model.
- [ ] Define anonymous vs registered access levels.
- [ ] Define how support/payment providers fit the future access model: keep v0.7 support links/widgets config-driven, then decide later whether Ko-fi/Patreon/Stripe/PayPal/Vipps-style APIs need a reusable provider integration layer for registered or premium access rules.
- [ ] Replace hardcoded player/share fallback meta values with fully config-driven defaults before anonymous/public access is introduced.
- [ ] Define core vs module boundaries in implementation terms, not only roadmap language.
- [ ] Replace the single-release `web-config` field names with explicit install, release, and track scopes in the future schema.
- [x] Define which theme and asset fields are install defaults, which are release overrides, and which may be overridden per track.
- [x] Implement runtime compatibility reads so scoped config keys can fall back to current single-release fields.
- [x] Implement dual-write admin saves for transitional fields during the schema migration window.

### PWA offline audio caching and offline logging

- [ ] Record the current v0.7 limitation explicitly in operator/developer notes: real-phone screen-off playback can still fail during background continuation or next-track handoff even after the current player and `audio.php` hardening, so this behavior is recognized but deferred to the v0.8 playback architecture track rather than treated as a v0.7 gate blocker
- [ ] Define the protected-audio delivery model for production: PHP authorization plus web-server/static delivery handoff, signed URLs, or equivalent protected media strategy
- [ ] Define which core services can work offline, which should degrade gracefully, and which still require online authorization/runtime support
- [ ] Define the installed-phone success criteria: what must feel better in the PWA than in the browser, especially offline listening, startup behavior, update reliability, and media availability
- [ ] Audit `service-worker.js` end to end: current exclusions, cache strategy, stale-asset risks, update behavior, and which legacy workarounds should be removed
- [ ] Audit update propagation and cache invalidation for installed PWAs so phones can cache aggressively without getting stuck on stale player, config, gallery, or shell assets
- [ ] Lock the v0.8 architecture direction now: no future playback path should keep PHP in the long-lived audio byte-delivery path
- [ ] Move the actual delivery/caching/offline implementation work into the v0.8 scaling plan: protected delivery handoff, player contract changes, service worker audio caching, cache eviction, and offline log sync

## Notes

- `ROADMAP.md` is the long-term direction.
- `TODO.md` is the short-term working list.
- **v0.8 order (2026-06-14 beta feedback):** package updater → page editor/presentation → platform model/PWA tracks.
- Current operator model: one branded site. Prepared internal model: separate `brand`, `theme`, and `social` concerns, with install defaults and future release overrides kept internal until multi-release is real.
- If a task does not help finish `v0.7` or unlock `v0.8 beta`, it probably belongs in the roadmap, not here.
