# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased] - 2026-04-30

### Documentation
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
