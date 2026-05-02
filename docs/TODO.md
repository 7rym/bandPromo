# bandPromo TODO

Purpose: keep a short, practical list of what must be finished before moving from `v0.7` to `v0.8 beta`.

Reference: See [MEDIA-HANDLING.md](MEDIA-HANDLING.md) for the current media policy, operator guidance, and source-media handling rules. If you encounter build or playback issues, check that your source files meet the current requirements described there.

Rules for this file:

- Keep it tied to the roadmap, not random future ideas.
- Prefer short actionable items over long explanations.
- Move completed items to the bottom or remove them when no longer useful.
- Use this as the first checkpoint when resuming work after a break.

## Current milestone

Current target: finish `v0.7` cleanly before opening `v0.8 beta`.

Reference: see `ROADMAP.md` for the full milestone and release structure.

## v0.7 exit gates


### Stability

- [x] Verify player behavior on desktop and mobile after recent logging changes.
- [x] Review remaining known issues in admin, build, auth, and player flow.
- [x] Confirm media upload -> build -> playback works end to end after recent cleanup.
- [x] Surface metadata validation warnings in the admin UI build log after builds finish.
- [ ] Improve metadata warning prominence outside the raw build log, especially for non-technical operators.
- [x] Ensure that local user-specific files (e.g. configuration, uploaded media, personal data) are never overwritten by the repo during git pull, and are never committed back into the repository.
- [x] Enforce strict setup seeding for required runtime files (`web-config.json`, `data/gallery.json`, `data/bio.html`, `data/faq.html`) from tracked templates.
- [x] Remove silent runtime fallbacks for required content/config files and fail with actionable errors.
- [x] Move quiz out of core player flow while preserving quiz assets as module-ready files.
- [x] Fix localhost admin UX issue where "Open site" could resolve to `127.0.0.1`.
- [ ] Define clear issue severity in media validation: hard blockers vs publish blockers vs warnings vs autofixable issues.
- [ ] Present media validation in operator language focused on fixes, not raw tag terminology.

### Trust

- [ ] Review raw logs after the normalized `track_exited` change and confirm events match real behavior.
- [ ] Verify analytics views still interpret old and new log formats correctly.
- [ ] Decide final policy for `session_end`, inactivity tracking, and future `session_timeout` / `inactive_start` events.
- [ ] Replace hardcoded player/share fallback meta values with fully config-driven defaults.

### Reusability

- [ ] Test a fresh setup path from clone to working private release site.
- [ ] Confirm README/setup docs still match the current actual workflow.
- [x] Add a real `LICENSE` file to the repo to match the chosen licensing direction for v0.7/v0.8.
- [ ] Define the practical source-media policy: accepted source formats, weak-source scenarios, and what the platform repairs vs only warns about.
- [ ] Formalize the three media tiers: `original` (untouched upload), `master` (bandPromo-authored canonical package), and `delivery` (publish-ready derivatives).
- [ ] Define the master-tier rules for audio packaging: metadata, artwork, lyrics, naming, and downloadable corrected masters.
- [ ] Define the delivery-tier rules for images and audio based on actual UI/device needs rather than raw source size.
- [ ] Break build-required tracking into concrete tasks instead of the current coarse `full` vs `optimize` split.
- [ ] Define which edits actually require playlist regeneration, audio delivery regeneration, image delivery regeneration, social asset generation, and manifest generation.
- [x] Rename Files -> `System` to Files -> `Theme` if that panel remains the home for install-specific branding/design assets.
- [x] Define the explicit media-role model for install-wide theme assets, release covers, track covers, gallery media, and page illustrations.
- [x] Define the inheritance contract for install defaults, release overrides, and track-level exceptions.
- [ ] Split the current mixed `site` identity fields into explicit install-shell fields vs release identity fields.
- [x] Define target schema names for install shell, release identity/presentation, and track exception fields.
- [x] Lock the future identity-asset rule: mandatory site-level logo/poster fallbacks, optional release-level logo/poster overrides, without exposing multi-release concepts in the current admin UI.
- [x] Define the migration and compatibility rules from current `site` / `social` / `media` fields into the future scoped schema.
- [x] Decide how build-time metadata validation should warn operators about missing or weak tags: `play/playlist-validation.json`, build-log output, and admin build-log summary.
- [ ] Implement tag editing tools in the admin UI after warnings are visible.

### User Friendliness

- [x] Update CSS breakpoints for modern screen segments. Flow design for vertical views, grid for wide screens.
- [x] Test view at 360–430px (mobile) with vertical layout and --card-size: 260px
- [x] Test view at 431–767px (large mobile/small tablet) with vertical layout and --card-size: 400px
- [x] Test view at 768–1365px (tablet/small laptop) with grid and --card-size: 430px
- [x] Test view at >=1366px (desktop) with grid and --card-size: 600px
- [ ] Ensure all these features are working and accessible before closing v0.7
- [ ] Test and improve landscape orientation on mobile (360–430px wide, ~360–430px tall): layout likely needs a horizontal/grid split similar to tablet view rather than the current vertical stack
- [ ] Test landscape on large mobile/small tablet (431–767px wide in landscape): confirm grid layout triggers correctly or add a dedicated landscape breakpoint
- [ ] Consider using `orientation: landscape` media queries to switch mobile views to a two-column layout when height is constrained

### Admin UX follow-up

- [x] Replace Config -> Basics raw JSON with guided form editing for `site` basics.
- [x] Keep the future scoped config model internal and continue exposing supported settings through operator-facing forms instead of raw JSON.
- [x] Keep social editing in Sharing only (single source of truth for `social`).
- [x] Add a dedicated Config sub-tab for theme/media presentation settings (rename from low-level `media` wording to user-facing `theme`).
- [x] Audit `web-config` branches (`content`, `build`, `quizzes`) and move non-core branches out of base config where appropriate.
- [ ] Implement operator-facing tools for fixing missing or invalid media metatags.
- [x] Implement playlist editing
- [x] Implement gallery editing
- [x] Replace Playlist placeholder with real drag-and-drop track ordering UI.
- [x] Persist manual playlist order in `play/playlist.json` from admin edits.
- [x] Update build generation so existing manual playlist order is preserved and new tracks are appended at the end.
- [x] Replace Bio/FAQ-only editing with a Pages feature for editing multiple HTML pages.
- [x] Add WYSIWYG page editing mode with safe HTML handling and fallback source mode.

Admin UX note: metadata repair belongs to media handling and operator readiness for policy/behavior, while this section tracks the operator-facing editor flows.


### Media handling (deferred from v0.7 gallery work)

- [ ] Map realistic intake scenarios: perfect FLAC, WAV export with no tags, partial metadata, lossy-only source, filename-driven metadata, missing cover art, mixed-quality release sets.
- [ ] Preserve user uploads as immutable originals while generating corrected masters and delivery derivatives separately.
- [ ] Decide when WAV should be converted into a tagged FLAC master and how lossy sources should be handled without false "quality upgrade" claims.
- [ ] Redefine "optimal" media output into explicit delivery targets (thumbnail, mobile, lightbox/desktop, stream/download tiers).
- [ ] Refactor build modes and UI wording so operators see task-specific actions instead of the ambiguous `Optimize Media` / `Full Build` pairing.
- [ ] Split the current optimizer into source-aware tasks; MP3 sources must not be treated as if they always need the FLAC-to-MP3 path.
- [x] Separate release cover and track cover into explicit product concepts instead of treating `cover` as a loose inferred role.
- [ ] Separate gallery media from page illustrations in the admin/build model so image behavior follows role, not only folder location.
- [ ] Video upload post-processing: generate thumbnail/poster frame from first frame (e.g. via ffmpeg) for gallery preview and lightbox cover
- [ ] Video transcoding: convert uploaded .mov/.webm to .mp4 on upload for broad browser compatibility
- [ ] Cover art (`media/img/`) management: distinguish build-generated covers from manually uploaded ones; prevent orphan accumulation; expose in admin file manager
- [ ] Orphan detection: identify files in media/img/, media/photo/, media/video/ that are not referenced by any active gallery entry or playlist track, and expose in admin
- [ ] Media deletion: add a safe delete action in the file manager that checks for active references before removing a file
- [ ] Video poster attribute: once thumbnail generation exists, write `poster` field into gallery.json entries and use it in gallery.js

### Beta operator readiness

- [ ] Review admin help text and identify remaining confusing areas for non-technical operators.
- [ ] Confirm cache-busting and generated-artifact handling are stable.
- [ ] Prepare a short tester checklist for the first closed beta.
- [ ] Define the first metadata editing tools needed in the file manager: title, artist, album, track number, lyrics, and cover handling.
- [ ] Define the first master-building tools needed in admin: metadata repair, artwork embedding, lyrics embedding, filename cleanup, and export/download of corrected masters.

## Post-v0.7 planning

### Immediate next after v0.7

- [ ] Define the `v0.8` multi-release data model.
- [ ] Define anonymous vs registered access levels.
- [ ] Define core vs module boundaries in implementation terms, not only roadmap language.
- [ ] Design the first theme/config structure.
- [ ] Lock the first static-page JSON schema for v0.8: document metadata, ordered block array, and a narrow first-party block set.
- [ ] Lock the first page-image presentation model for v0.8: semantic responsive presets instead of pixel sizing.
- [ ] Define the server-rendering contract for JSON-backed pages: safe HTML output, allowed block rendering rules, and optional cached HTML artifacts.
- [ ] Define the migration path from `data/bio.html` and `data/faq.html` into JSON-backed pages, including the compatibility window.
- [ ] Plan the page-editor replacement for v0.8 around the locked schema and block-based editing flow rather than raw HTML authoring.
- [x] Define which theme and asset fields are install defaults, which are release overrides, and which may be overridden per track.
- [ ] Replace the single-release `web-config` field names with explicit install, release, and track scopes in the future schema.
- [x] Implement runtime compatibility reads so scoped config keys can fall back to current single-release fields.
- [x] Implement dual-write admin saves for transitional fields during the schema migration window.

### PWA offline audio caching and offline logging

- [ ] Replace PHP-streamed audio delivery with an architecture that can support both scalable playback and offline-capable cached audio delivery
- [ ] Define the protected-audio delivery model for production: PHP authorization plus web-server/static delivery handoff, signed URLs, or equivalent protected media strategy
- [ ] Audit `service-worker.js` end to end: current exclusions, cache strategy, stale-asset risks, update behavior, and which legacy workarounds should be removed
- [ ] Implement service worker audio caching for offline playback only after the audio delivery path is cacheable and no longer depends on PHP byte streaming
- [ ] Add cache management for audio (eviction, update handling)
- [ ] Audit cache-busting and update propagation for installed PWAs so phones do not get stuck on stale player, config, gallery, or shell assets
- [ ] Implement offline logging (store logs locally when offline, sync when online)
- [ ] Define which core services can work offline, which should degrade gracefully, and which still require online authorization/runtime support
- [ ] Define the installed-phone success criteria: what must feel better in the PWA than in the browser, especially offline listening, startup behavior, update reliability, and media availability

## Notes

- `ROADMAP.md` is the long-term direction.
- `TODO.md` is the short-term working list.
- Current operator model: one branded site. Prepared internal model: separate `brand`, `theme`, and `social` concerns, with install defaults and future release overrides kept internal until multi-release is real.
- If a task does not help finish `v0.7` or unlock `v0.8 beta`, it probably belongs in the roadmap, not here.

