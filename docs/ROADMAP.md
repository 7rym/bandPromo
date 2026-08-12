# bandPromo Roadmap

This roadmap exists to keep bandPromo focused on stability, trustworthiness, and a clear progression from a private single-release platform to a reusable self-hosted artist platform.

## Product direction

bandPromo **v0.7** delivered a reusable, self-hostable private release platform for one artist and one release. **v0.8 beta is the active milestone** (since 2026-06-14): platform-model definitions and core deliverables that turn bandPromo into a reusable artist-platform foundation.

bandPromo v1.0 should become a releaseable self-hosted artist platform that lets artists or micro-labels run promotion, access control, playback, analytics, and fan-facing releases on their own domain.

bandPromo v1.0 should be able to:

- present more than one release
- support both anonymous and registered users
- provide trustworthy playback, analytics, and admin workflows
- let artists promote music, events, merch, and fan engagement from one place

bandPromo v2+ can expand into integrations, automation, content generation, and multi-service marketing tools.

## Platform stance

bandPromo is self-hosted publishing software, not a centralized content platform.

bandPromo does not act as a distributor, editorial gatekeeper, or algorithmic curator of operator content.
Each site operator is responsible for the content they publish, the rights they rely on, and the legal/compliance obligations that apply in their jurisdiction.

bandPromo may provide technical controls, access rules, and operator-facing moderation tools, but content responsibility remains with the operator of each installation.

## Version principles

- **v0.7 is complete** — exit gates passed 2026-06-15. Repository version line is **`v<major>.<minor>.<session> build <number>`** (continuous build numbering from v0.7).
- **v0.8 beta is active — the management machine** — catalog, media, brands, containers, delivery pipeline, and **content AI wizards** that help operators fill missing pieces from release + brand canon.
- **v0.9 — access and engagement foundation** — implements access tiers defined in v0.8, login/anonymous entry, user roles, and user-facing services on stable deliverables.
- **v1.0** is public-ready, stable, and trustworthy.
- **v1.x** expands fan and artist utility without overloading the core.
- **v2+ — the marketing machine** — campaign automation, scheduled social pushes, and semi-autonomous promotion **based on content that already exists** (not the same as v0.8 content-creation wizards).
- bandPromo should favor operator control over centralized platform behavior.
- Core decisions should preserve self-hosting, local ownership, and predictable operator control.

## Current milestone (v0.8 beta)

**Status: active** — closed-beta work on platform foundations.

| Track | Status |
|-------|--------|
| Package updater | Shipped |
| Block-based page editor | Shipped |
| Unified Content editors + upload-time delivery automation | Shipped |
| Platform model (multi-playlist/gallery, module blocks, delivery architecture) | In progress |
| Brand containers + semantic player colors (replaces Theme) | Core shipped (Branding editor, release `brand_id`, player tokens); legacy `theme` URLs/APIs remain |
| Visual pool + role tags + registry-scoped delivery | Phases 0b–2 + **identity completion M1–M6 shipped** 2026-08-04 (`asset_id`, masters, XXH3, register-or-fail, operator titles, campaign export); physical `media/special/` retirement residual |

| Content AI wizards (release + brand canon) | Defined; v0.8 deliverable |
| PWA / protected delivery architecture | Defined; implementation in progress |

**Next focus:** v0.8 residual polish — visual naming (IPTC/XMP stills, MKV video masters) + gallery multi-select pickers; physical Brand-assets `media/special/` fold into Visual-only intake; Content AI wizards remain defined. Page container metadata / OG polish still v0.9. Fan comment/share on gallery assets is **v0.9+** (keep in USE-CASES tour story). See [TODO.md](TODO.md).

## Core vs modules

Core features are part of every bandPromo install and define the listening + operator foundation:

- authentication and session foundation (including the required FAQ/login surface for shared-link entry)
- media player with **Playlists** and **Lyrics** tabs (playlist + lyrics stay in the player shell; they are not page-embedded modules)
- enhanced playlists with short informational summaries of track contents
- **multiple playlist libraries** in admin (v0.8+), with a playlist selector in the player tab
- playback and behavior logging
- admin UI for easy management and access to tools
- build pipeline and media handling
- analytics foundation
- release/content model (releases and playlists are related but independent entities)
- operator-owned configuration and deployment model
- explicit responsibility boundaries for content, privacy, and integrations
- editing of metatags in media files (with user-friendly tools for missing/invalid tags)
- block-based static page editing for core site content (Text, Picture, List, and future core block types)
- **gallery libraries** in admin (v0.8+); galleries are placed on pages via module blocks, not a dedicated player tab
- track deep links in page content that open the player on the right playlist and track
- PWA installs
- basic tools for sharing to social media platforms

Modular features can be enabled or omitted per install. Modules extend pages and operator workflows; they do not replace core playback:

- **gallery presentation blocks** on pages (grid, carousel, parallax, and similar layouts referencing a gallery library)
- news publishing with timed release and social push (v1+)
- fanboard, feeds, and similar engagement surfaces
- quizzes and games
- merch and shop features
- events and tour listings (may start as static pages; richer modules later)
- newsletters and mailing integrations
- OAuth providers
- analytics integrations such as Google Analytics
- community/chat features
- future automation and publishing integrations
- semi-automatic **marketing** tools (**v2+** — campaigns from existing catalog content)
- external AI **marketing** helpers (**v2+** — social series, timed pushes, newsletter drafts from published releases)

**Composition rule:** operators build pages from **core blocks** (text, pictures, lists, and future first-party block types). **Module blocks** (gallery, news, fanboard, feeds, etc.) reference module libraries or services and are rendered inside those pages. The player remains the primary listening shell.

**Access tiers** (admin/dev, VIP pre-access, registered fan, anonymous with restrictions) are documented in v0.8 and **implemented in v0.9**. Fan credits and rebate/boon mechanics are **v1+**.

**Exposure/distribution** (Chromecast and similar cast targets): architecture and product boundaries are **defined in v0.8** after the playback/delivery model is stable; **implementation follows in v0.9+**, not before deliverables are trustworthy.

## Brand strategy (replaces Theme strategy)

**Brand** replaces the former Theme container. Colors, typography, mood narrative, and curated visual assets belong in one **brand identity package** per era or campaign — not split across Theme vs brand asset paths.

Initial brand support should focus on:

- design tokens and CSS variables (colors, typography)
- curated asset refs in the Visual pool: logos, portraits, style references, shell backgrounds/audio
- **`bandpromo-default`** locked seed on first install so demo content works out of the box; operators duplicate as their first customization task
- **release `brand_id` links** — many releases (singles, EPs, album, post-album singles) may share one brand; release cover stays **`poster_asset_id` on the release**
- explicit **role tags** on visual uploads; brand filter in Files and pickers
- operator-friendly favicon package intake (RealFaviconGenerator ZIP → `media/icons/`)
- module templates inheriting from the base brand

The first brand system does not need arbitrary custom templating. It needs a clean brand API and a Visual pool filtered by `brand_id` + role.

**System shell vs brand overlay:** v0.8 ships brand **management** and **token overlay** on the stable dark system shell (layout, breakpoints, and behavior stay platform-owned). v1.0 adds **shell asset runtime** so installs can look genuinely different while a broken brand still degrades to default — see [PLATFORM-MODEL.md](PLATFORM-MODEL.md) → Brands.

### Content AI wizards (v0.8 — management machine)

v0.8 ships **content-creation wizards** — operator-triggered helpers that fill **missing container fields** using **release + linked brand** as canon:

- EPK blurbs, page block drafts, descriptions, alt text, metadata suggestions
- optional image briefs from brand style refs
- operator-configured external model/API settings
- outputs are drafts; generated assets enter the Visual pool with `origin: ai-generated` until confirmed

This is **not** the v2+ marketing machine (campaign calendars, scheduled social pushes, multi-post series from an existing catalog). Those stay v2+.

See [PLATFORM-MODEL.md](PLATFORM-MODEL.md) → Brands and [MEDIA-HANDLING.md](MEDIA-HANDLING.md) → upload role tagging.

## Identity strategy

bandPromo should support both anonymous and registered users. **v0.8** locks the FAQ/login/shared-link and tier **definitions**; **v0.9** implements restricted anonymous entry and tier enforcement; **v1.x** expands OAuth and fan-credit mechanics.

Recommended path:

1. Establish the internal user/account model first.
2. Add local/manual account support for registered-only features.
3. Add Google OAuth as the first external login provider.
4. Evaluate other providers later only if they add clear value.

Google OAuth is easier for users, but it does not replace the need for internal user roles, permissions, moderation, or account lifecycle logic.

## License

bandPromo is distributed under the GNU Affero General Public License v3 (AGPLv3). This ensures that both the source code and any modifications remain open, including for network-based services. 
- Each operator is responsible for content, privacy, and any third-party integrations on their own installation.
- third-party payment, analytics, email, and identity integrations remain the operator's responsibility once enabled

## Installation and portability strategy

bandPromo should be reproducible as software, not just clonable as a project.

Current v0.7 install reality:

- the preferred operator path is now the bootstrap installer plus the latest published release manifest/package
- the repository-upload plus `setup.php` path still works, but it remains a developer/server-admin fallback rather than the main operator story

Before v1.0, the platform should move toward:

- installation and upgrade paths that are reproducible and documented
- operator configuration that is portable between installs
- branding and content changes that do not require manual code edits
- setup workflows that a non-developer operator can realistically follow

Current installation-policy clarification:

- Git/Plesk/SSH deployment remains acceptable for developer-operated installs, but it is not the target operator path.
- The baseline operator assumption should be reduced to: the host can serve files from a folder and supports PHP.
- bandPromo should not assume Plesk, Cloudflare, shell access, SSH access, root access, cron access, or server-admin comfort for normal operators.

Preferred operator install/update direction:

- first install should use a one-file bootstrap PHP flow: upload one bootstrap file, open it in the browser, and let bandPromo fetch and install a release package automatically
- install and update artifacts should be versioned ZIP/release packages rather than Git operations
- release ZIPs should be published alongside the GitHub repository so install/update logic can use the same upstream source as the developer path without requiring Git credentials on operator hosts
- lightweight version checks should be able to rely on the published `VERSION` file before downloading a full package
- GitHub-hosted release downloads should also provide the first passive installation signal for project-level adoption tracking
- the bootstrap flow should validate environment requirements, writable paths, and package integrity before handing off to the normal setup wizard
- the setup wizard should also include a friendly acknowledgment step that reminds operators they are using AGPL-licensed self-hosted software and remain responsible for their content, rights, privacy choices, hosting, and enabled integrations
- the update flow should preserve runtime/user-managed paths (`web-config.json`, `.env`, `data/`, `media/`, logs) while replacing tracked application files
- the long-term best operator flow should include admin-panel updates driven by release packages, not by `git pull` or hosting-panel repository tools

Bootstrap installer contract for v0.7 exit:

- operator entry point should be one uploaded PHP bootstrap file placed in the target web folder
- the bootstrap should check minimum PHP capabilities first: writable target folder, `pdo_sqlite`, `ZipArchive`, HTTPS-capable remote download support, and the required extensions already documented by the project
- the bootstrap should fetch a package manifest or equivalent lightweight metadata first, then verify what package URL and version it is about to install before downloading the ZIP
- the bootstrap should unpack into a staging folder, validate the expected application structure, and only then copy the tracked application files into place
- first install should seed required runtime files from tracked templates after extraction, not rely on example files being used directly as runtime fallbacks
- if any check fails, the bootstrap should stop with a plain-language explanation and safe re-entry instructions instead of leaving a half-installed tree behind
- rerunning the bootstrap in the same folder should be safe: it should detect an unfinished install, an already-installed runtime, or a recoverable partial extraction and explain the next valid action

First-run verification model for v0.7 exit:

- a brand-new reusable install should land with seeded demo content by default rather than an entirely empty public shell
- that seeded content is part of the first-run verification experience: it should prove that install, build, playback, theming, and the basic site shell are functioning on a real host
- the seeded content may remain editable in place so a non-technical operator can start by replacing example values instead of facing a blank admin
- setup success should be confirmed primarily by opening admin with a clear next-step checklist rather than only dropping the operator on a public placeholder page
- the public site should still be viewable immediately after setup, but the main success signal for the operator is a readable admin path that says what to replace, review, and publish next
- if the product later offers a cleaner empty-state path, that can exist as an additional mode, but the baseline reusable-install contract for v0.7 is seeded demo content plus an admin-first success checklist

ZIP update contract for v0.7 exit:

- updates should replace only tracked application files and directories from the package payload
- updates must preserve operator/runtime-managed files and paths, at minimum: `web-config.json`, `.env`, `data/`, `media/`, and logs
- the preservation list should be treated as an explicit product contract, not as an incidental side effect of `.gitignore`
- updates should run any required post-update tasks automatically where practical: cache refresh, manifest refresh, build-required recalculation, and any schema or runtime migrations needed for the shipped version
- if a package extract or copy step fails, the updater should stop before claiming success and should leave preserved runtime state untouched
- package retry should be safe after a failed update attempt; operators should not need shell access to recover from a normal failed extraction or copy

Package source and version-check contract for v0.7 exit:

- the preferred operator package source should be immutable GitHub release assets, not mutable branch snapshots
- lightweight update checks should read the published `VERSION` first and only download a full package when a newer compatible package exists
- the package metadata should eventually expose at least: version, package URL, checksum or equivalent integrity signal, and optional release notes text for admin/update messaging
- `https://github.com/7rym/bandPromo/archive/refs/heads/main.zip` can remain a developer/manual fallback for ad-hoc testing, but it should not be the primary operator install/update source because it is mutable, branch-shaped, and not a stable release artifact
- the operator-facing bootstrap/updater should prefer URLs that map to explicit packaged versions so support, recovery, and telemetry all refer to the same immutable build identity

Admin-panel updater model for v0.7 exit:

- the admin updater should be an operator-facing package workflow, not a thin wrapper around `git pull`
- update availability should begin with a lightweight remote version check against the published package metadata and `VERSION`, then present a plain-language summary before any package download begins
- the operator should see at least: current version, available version, whether the update is recommended or optional, and short release notes or a compact change summary when available
- the update action should download the selected immutable package into a temporary runtime-safe location, verify integrity, extract to staging, validate expected structure, then apply the update using the same preservation rules defined for ZIP updates
- the updater should not require shell access, SSH keys, Git configuration, or hosting-panel tools for normal use
- the updater should present a clear pre-apply warning that runtime/operator-managed files are preserved while tracked application files are replaced
- after apply, the updater should run required post-update tasks automatically where practical and then report whether the site is ready, needs a build refresh, or needs a follow-up admin action
- if any step fails, the updater should report the failing stage in plain language: version check, download, integrity verification, extraction, file apply, migration, or post-update tasks
- failed updates should leave preserved runtime state untouched and should offer a retry path without forcing the operator into manual cleanup
- successful updates should record enough local state for support and diagnostics: previous version, new version, package identity, timestamp, and whether post-update tasks succeeded
- the updater UI should be readable to non-technical operators: no raw Git terms, no branch names, no repository jargon, and no assumption that the operator understands deployment internals

Observability preference for install/update adoption:

- passive package-download counts from GitHub releases are the safest default signal and should be treated as coarse adoption data only
- if bandPromo later reports install/update events centrally, that should be a separate explicit opt-in feature, not a hidden default behavior
- the setup wizard should ask this in friendly plain language as a maintenance-success question, not as a technical telemetry toggle, and core product behavior must not depend on accepting it
- any future install/update webhook should report only minimal product-maintenance data such as event type, version, optional channel, and a site-generated install identifier designed for future extensibility
- install/update telemetry must not become audience analytics, playback tracking, or content reporting by another name
- operators should be able to disable or never enable the webhook path without losing core install/update functionality
- the basic install identifier used for maintenance reporting should be treated as a product identity primitive, but a plain copied UID is not a sufficient future basis for paid-module enforcement
- if premium modules, themes, or other paid expansions are introduced later, they should bind entitlements to a stronger installation identity model such as a locally held secret or keypair plus server-issued activation state, so copying files alone is not enough to duplicate access

Release-observability model for v0.7 exit:

- package-download counts from immutable GitHub releases are the passive baseline and should be reported internally as download counts, not as active-install counts
- the product should distinguish between package distribution signals and actual opt-in maintenance-success signals
- the only future central install/update events worth considering in core are maintenance events such as `install_succeeded`, `update_succeeded`, and optional `update_failed`
- if failure reporting is ever included, it should stay optional and coarse enough to help diagnose product issues without collecting local content or audience data
- local installs should also keep their own local update/install history for operator review and support, so central reporting is not the only source of truth

Install/update telemetry payload boundary for v0.7 exit:

- payloads should stay minimal and product-maintenance focused
- allowed fields should be limited to things such as: event type, timestamp, bandPromo version, previous version for updates, package identity/channel, anonymous install identifier, and a narrow environment summary useful for compatibility debugging
- environment summary should stay coarse, for example PHP major/minor version and installer/update path, not full server inventory
- raw domains, operator names, uploaded content, track names, media metadata, audience analytics, admin credentials, IP-based audience data, and third-party integration secrets must not be sent as part of maintenance telemetry
- if host context is ever needed centrally, it should prefer a one-way or otherwise privacy-preserving form over sending the plain live domain by default
- operators should be able to inspect the exact telemetry fields in documentation and, later, in admin UI before enabling reporting

Setup consent UX for maintenance telemetry for v0.7 exit:

- the setup wizard should ask one friendly yes/no question after the operator-responsibility acknowledgment, framed around helping bandPromo learn whether installs and updates succeed in the real world
- the default should be no reporting until the operator explicitly enables it
- the copy should say plainly that bandPromo works fully without this, what kind of maintenance events may be reported, and that no audience or content analytics are included
- the choice should be revisitable later from admin settings without editing files manually
- if the operator skips or declines reporting during setup, later update flows may remind them that the option exists, but should not nag on every update
- the consent UI should avoid technical jargon such as webhook, telemetry, payload, or install ping in the primary operator-facing sentence; those details belong in secondary help text

Installation-identity model for v0.7 exit:

- each installed site should generate one stable installation identity during first successful setup or bootstrap completion
- that identity should have at least two parts: a non-secret opaque `install_id` for local records and support references, plus a stronger runtime-only install secret or key material that is never treated as a public identifier
- both parts should be generated locally with cryptographically strong randomness and stored only in runtime-managed state, not in tracked application files or release packages
- the install secret or private key material should live in a preserved runtime path such as `data/` so normal updates do not replace it
- maintenance telemetry should use an anonymous reporting identifier derived from local identity state; it should not send the raw secret, and it should not rely on a human-visible copied UID alone
- the model must not hard-bind core installation identity to the current domain, because legitimate restore and moved-site recovery need to preserve continuity when the host changes
- backup and restore should preserve installation identity by default so a real moved installation remains the same installation for local history, support, and future entitlement continuity
- the model should allow an explicit future repair or reissue path when an operator intentionally wants to reset installation identity after a compromised clone, migration mistake, or licensing/support event
- a copied filesystem clone may still duplicate the local identity state; that is acceptable for core offline behavior, but it means installation identity alone must not be treated as sufficient paid-entitlement enforcement
- the installation identity contract is about the install shell and runtime instance, not about release title, artist branding, or site-presentation fields
- future terminology should stay explicit: `premium access` means operator-defined audience/member access levels inside an installation, while install-locked `paid add-ons/services` means bandPromo-sold modules, themes, services, or entitlements bound to the installation itself

Install-locked paid add-on entitlement model for v0.7 exit:

- this model applies first to bandPromo-sold themes/skins and modules/features; it should not be confused with operator-defined audience/member premium access inside a site
- core bandPromo must remain fully usable without central activation; only the paid add-on itself may depend on entitlement checks
- each paid add-on entitlement should bind to the stronger installation identity state, not to a visible copied UID alone
- the entitlement service should recognize a legitimate moved or restored installation when the preserved runtime identity is still intact, so normal host moves do not force a punitive relicensing flow
- the product should still support an explicit reissue or repair path for real edge cases such as lost runtime identity, compromised clones, or operator support events
- a local entitlement cache should allow a generous grace period when the entitlement service is temporarily unreachable, so hosting outages or provider downtime do not immediately disable paid add-ons on legitimate installations
- grace behavior should fail soft for legitimate operators: warn in admin, keep the paid add-on working during the grace window, and surface plain-language recovery guidance before any hard disable decision
- entitlement checks should record enough local state for support and diagnostics, such as add-on identity, entitled version or tier, last successful verification time, grace expiry, and recent entitlement errors
- copied filesystem clones that preserve runtime identity may still appear valid locally for some time; the enforcement boundary should therefore assume provider-side reissue/revocation logic and install-identity continuity checks, not secret local files alone
- add-on entitlements should be revocable and transferable through a documented support/operator flow without threatening the operator's access to the free core product

Design constraints for that direction:

- package installation must fail safely and explain what the operator needs to fix when hosting requirements are not met
- the setup acknowledgment should be phrased as respectful operator guidance, not a hostile clickthrough wall, while still requiring a clear confirmation before first-run completion
- updates must be resumable or retryable without leaving the install in an ambiguous half-updated state
- package integrity should be treated as the first safety line: if the ZIP is verified and extraction succeeds, the install should be able to recover without depending on a complex rollback system
- rollback is not the preferred primary strategy; a clearer recovery model is preserved runtime state plus a documented restore path
- runtime migrations, cache refresh, build-required state, and post-update regeneration tasks must be part of the product contract, not left to manual shell steps
- private-repo distribution should be treated as a developer path; the operator path should rely on release packages that do not require GitHub credentials or SSH keys
- backup/restore should become a first-class operator feature: operators should be able to export their site state, move it to another host, and restore it there
- **release package export/import** should let operators and ambassadors move a finished release (masters, tags, linked visuals, release metadata) between installs for demos and paid prep handoffs — see [PORTABILITY.md](PORTABILITY.md)
- moved-site recovery should be explicit: when restored runtime data no longer matches the current host/base URL, setup/bootstrap should recognize that situation and offer to repair host-specific config rather than forcing manual file edits
- maintenance telemetry consent must remain reversible and must never block install, update, playback, or admin use when disabled

## Media intake and publishing strategy

bandPromo should help non-technical artists move from weak source material to a professional publish-ready package without requiring external tagging or packaging tools.

The platform should treat audio fidelity and media packaging as separate concerns:

- audio quality remains the responsibility of the artist or producer
- metadata quality, artwork packaging, lyrics embedding, file naming, and delivery optimization are product responsibilities bandPromo can actively improve

bandPromo should adopt three explicit media tiers:

- `original`: the exact user upload, preserved untouched for trust, recovery, and future regeneration
- `master`: a bandPromo-authored canonical release asset with corrected metadata, embedded artwork/lyrics where applicable, and standardized naming/structure
- `delivery`: publish-ready derivatives generated from the master tier for actual playback and display contexts

Practical implications:

- WAV and FLAC should be accepted as preferred source formats
- WAV uploads should be promoted into FLAC masters during normal intake where possible, and older supported originals should be able to backfill missing masters automatically when admin tools first inspect them
- lossy sources may be improved in packaging and metadata, but must not be misrepresented as higher-fidelity audio
- images should be delivered according to real UI needs, not merely preserved at oversized source dimensions
- build validation should distinguish hard blockers, publish blockers, warnings, and autofixable issues
- the admin UI should guide non-technical operators through fixing weak source packages rather than rejecting them upfront

Build-pipeline implications:

- the current `full` vs `optimize` split is too coarse for the intended `original` / `master` / `delivery` model
- build-required tracking should evolve from broad action labels into task-level requirements such as playlist scan, metadata validation, audio delivery generation, image delivery generation, social asset generation, and manifest generation
- admin-facing build language should describe concrete outputs instead of the vague word `media`
- validation issues should become persistent operator tasks with direct actions to the correct editor surface instead of remaining buried in build logs or transient summaries
- light admin repairs should prefer automatic light-task refresh and no-op suppression: unchanged saves should not create new pending work, and lightweight validation refresh should happen immediately when possible
- publish-blocking and recommended-fix tasks should derive from current validation/build state and clear automatically when the underlying issue is fixed; manual acknowledgement should be reserved for informational notices, not used as the truth source for fixable blockers
- the first remediation flow should prefer navigation to dedicated editors, while inline quick-edit stays limited to short metadata fields such as artist, title, version, release/album name, track, release date, genre, BPM, and key
- source-aware processing must stop assuming every supported audio input follows the same FLAC-first path
- offline-capable playback and scalable playback should share the same future direction: PHP should authorize access, but long-lived audio byte delivery should move away from PHP streaming toward a cache-friendly protected delivery path
- the PWA/service-worker layer should be audited as product infrastructure, not treated as a one-time install checkbox; update behavior, cache-busting, stale-shell risk, and offline value on phones are part of the core user experience
- background playback stability on real phones is now a confirmed product-level concern rather than a small player bug: even after tightening player state handling and HTTP range correctness in v0.7, screen-off continuation and next-track handoff can still fail on mobile browsers. Treat this as a known limitation of the current delivery model, not as a reason to keep stretching v0.7. The v0.8 playback architecture should make playback state authoritative, move visual transitions into a best-effort presentation layer, and revisit delivery/cache/offline behavior as one coherent stability track.

Platform-model implications:

- media roles must be explicit before multi-release support lands: `theme asset`, `release cover`, `track cover`, `gallery media`, and `page illustration`
- media scope must be explicit before multi-release support lands: install-wide, release-scoped, track-scoped, and page/module-scoped assets cannot keep sharing one blurred `cover` concept
- admin UI and build logic should evolve toward role-based behavior even if the storage layout changes more gradually
- operator-facing naming should be decoupled from immutable source filenames: originals remain the trust/recovery anchor, while display names, aliases, and future master/delivery naming can evolve without losing asset identity
- theme and asset inheritance must be explicit before multi-release support lands: install defaults, release overrides, and track-specific exceptions should replace one-off duplicated theme definitions
- install-level identity assets must remain mandatory even after release overrides exist: the site shell needs a required fallback logo and poster/share image, while each release can optionally override both
- the current single-release `web-config` shape must be split deliberately: install shell fields stay install-wide, release identity fields move to release scope, and track overrides remain narrow exceptions rather than a second theme layer
- the future schema should use explicit scoped blocks such as `install.site`, `install.theme`, `install.social`, `release.identity`, `release.theme`, `release.social`, and a narrow track-presentation exception layer
- the migration must be staged: compatibility reads first, then dual-write saves, then schema-first admin UI, and only then legacy cleanup

Install-shell versus release-identity split for v0.7 exit:

- the current flat `site` surface should no longer be treated as one identity block with mixed meanings
- `site.url`, `site.language`, and `site.author` belong to the install shell and should remain install-level concerns
- `site.name` must split into an install-level shell title and a release-level title; one field cannot continue to mean both the site identity and the highlighted release identity
- `site.short_name` must split into an install-level app short name and, only when needed later, an optional release short label
- `site.description` must split into install description versus release description so shell copy and release copy stop overwriting each other conceptually
- `media.cover` should stop acting as a blurred primary artwork field and should be replaced conceptually by an explicit release-level cover, with track cover remaining a separate narrow override
- install-level social handles and shell assets remain part of the install shell, while release-level keywords, categories, poster/share imagery, and background presentation belong to release scope with install defaults only as fallback
- this split is a schema and migration contract first; current single-release admin UX does not need to expose multi-release complexity before the product is ready

This strategy is part of the v0.7 exit work because it defines what "usable by non-technical operators" actually means in practice.

## v0.7 exit criteria (completed)

**Status: passed 2026-06-15.** v0.7 delivered the single-release private platform promise. Development continues on **v0.8 beta** (see below).

v0.7 is complete when bandPromo can honestly be described as:

"A reusable, self-hostable private release platform for one artist and one release, with a stable player, understandable admin flow, dependable build process, and trustworthy analytics."

Exit gates:

Recent hardening completed (Apr–Jun 2026):

- strict runtime-file seeding from tracked templates (`web-config`, `gallery`, `bio`, `faq`)
- runtime fallback removal for required content paths (fail loudly with actionable errors)
- local-only file policy hardening (`data/*` strategy + guard workflow)
- non-core quiz feature moved out of core player flow and preserved as modular assets
- localhost admin quality-of-life fix for "Open site" link behavior
- welcome dashboard after setup completion, operator inbox modal, plain-language inbox copy
- Files list header UX: master checkbox selection, compact filter dropdowns, labeled bulk actions, operator-facing row badges without verbose reference lines
- login splash uses install branding; expired admin/player sessions redirect to login
- cover-art management, orphan detection, reference-aware media deletion
- admin-role guard on operator panel and admin APIs (listener accounts cannot open admin surfaces)

### 1. Stability gate

- no major known bugs in player, admin, build, or auth flow
- no common action causes broken layout, PHP errors, or stale UI state
- media upload/delete/build cycle works reliably
- required runtime files are seeded during setup and validated by CI template checks

### 2. Trust gate

- playback events and analytics are internally coherent
- session logging is separated from playback logging well enough to avoid false conclusions
- admin analytics match observed user behavior closely enough to be useful

Current v0.7 playback/session analytics policy:

- `play_start` starts a user play session when playback actually begins while no analytics session is active. It counts toward session totals, but it is still a session-boundary signal rather than proof of meaningful listening on its own.
- `track_started` counts as a play attempt for track-level totals. It does not by itself contribute listening time, completion, skip, or quality-time metrics.
- `track_resumed` is informational only for raw logs and future analysis. Current core analytics should not count it as a new play, session, or completed listen.
- `track_exited` is the canonical playback progress event. It should carry the most reliable progress snapshot for natural endings, next/previous clicks, and playlist-driven interruptions.
- `session_end` is the canonical session-boundary event. It always closes the current session summary, even when no track is active.
- `session_end` may also carry active-track progress when the page closes during playback. When it includes a track snapshot with at least 5% completion, analytics may use that progress for listening-time and completion metrics.
- Null-payload `session_end` entries are session-boundary records only. They must not increase plays, listening time, skip counts, or completion analysis.
- Meaningful listening time currently comes only from progress-bearing `track_exited` and active-track `session_end` entries with at least 5% completion. The analytics layer must not infer listening time from idle gaps or from `track_started` alone.
- Skip/completion analysis should treat `track_exited` as authoritative for exit reasons. `session_end` progress may inform completion totals, but it is not an explicit skip reason.
- Current inactivity rule: if no music is playing for 15 minutes, the current analytics session should be ended without logging the user out.
- A resumed listen after that 15-minute no-playback window should start a new analytics session with a fresh `play_start` event.
- `inactive_start` and `session_timeout` are still future event names. In `v0.7`, the inactivity split is implemented by logging `session_end` at the 15-minute no-playback boundary rather than by introducing additional lifecycle events.

Trust-gate scope note for `v0.7`:

- Public/share metadata fallback cleanup is not a `v0.7` Trust blocker while bandPromo remains a closed authenticated system.
- That work moves into the anonymous/public-access release track, where OG/share metadata correctness becomes user-facing outside authenticated playback.

### 3. Reusability gate

- a fresh install can be cloned into a new web folder
- setup docs and first-run flow are clear enough that a new install can be verified quickly
- repeated deployments do not overwrite local runtime/operator-managed files
- a new operator can configure branding, theme defaults, and install personalization without code surgery
- install-vs-release identity boundaries are clear enough that the same platform can be reused for different branded deployments

### 4. Beta operator gate

- help text and admin structure are understandable for non-technical testers
- common setup and operation steps are documented well enough for trial use
- weak source material can be uploaded, diagnosed, and repaired through understandable admin guidance instead of expert-only metadata tooling
- operator-facing media repair/editing tools follow the locked media-handling policy instead of exposing raw tagging concepts first
- unresolved publish blockers and recommended fixes remain visible in a persistent operator task/notification surface until the underlying issue is actually fixed

### 5. User Friendliness gate

- Usable operator-facing editing for static pages (bio, FAQ, etc.)
- Playlist editing
- Gallery editing
- Suitable and optimized designs for various display scenarios (vertical/horizontal, mobile/tablet, desktop/TV)

These features must be working and accessible before this gate is considered passed

Caching and update propagation (aggressive safe caching, low needless re-downloads, no stale shell/player/config artifacts after updates) were explicitly deferred to the **v0.8** PWA/service-worker architecture track rather than treated as a remaining `v0.7` blocker. See `docs/TODO.md` → v0.8 active work.

## v0.8 beta goals (active)

Theme: architectural shift from a private single-release site to a reusable artist platform foundation — **v0.8 is the management machine** (catalog, media, brands, content wizards), not every future fan or marketing feature at once.

Closed-beta feedback (2026-06-14) locked the first three implementation priorities:

1. **Package updater** — **shipped** in admin (**Dashboard → Site update**).
2. **Page editor and presentation** — **shipped** in admin (**Content → Pages**): block JSON authoring, Width/Flow picture model, rich text toolbar, live preview, and JSON-only page storage in `data/pages/`.
3. **Content editor pool model and delivery automation** — **shipped** in admin: Playlist, Gallery, Pages, and Player layout use the shared pool/result editor; uploads auto-run delivery tasks; Content pools gate on delivery-ready assets; Notifications surfaces background video work.
4. **Platform model** — multi-playlist, multi-gallery, page composition, module boundaries, playback/delivery architecture, and stable specs for later access and distribution work.

### What v0.8 delivers vs defines

| Area | v0.8 deliverables | v0.8 definitions only (implementation later) |
|------|-------------------|-----------------------------------------------|
| Pages | Block-based editor, page registry, player-styled preview, unified pool/result Content editor UX | Module block types (gallery layouts, news, etc.) |
| Playlists | Single-library pool/result editor with delivery-ready gates; multiple playlist libraries + player selector (in progress) | Access rules per tier (see v0.9) |
| Galleries | Single-library pool/result editor with delivery-ready gates; multiple gallery libraries (in progress) | Gallery module blocks on pages (grid/carousel/parallax); remove Gallery player tab |
| Player | Core Playlists + Lyrics; Player layout editor; track deep links from pages (planned) | Chromecast/cast targets |
| Access | FAQ/login requirement; shared-link → login + FAQ copy | Tier enforcement, anonymous entry, VIP embargo |
| Delivery/PWA | Upload-time background delivery; delivery-ready Content pool gates (**shipped**) | Protected delivery architecture, cache contract, full offline audio cache + cast send |
| Brand / Visual | Brand containers (replaces Theme); Visual pool + role tags; registry visual delivery; content AI wizards | — |
| AI | Content wizards at point of need (release + brand canon) | Marketing automation and campaign AI (**v2+**) |

Primary goals:

- ship the admin-panel package updater (**complete**)
- ship the page editor and presentation overhaul (**complete**)
- ship unified Content editors and upload-time delivery automation (**complete**)
- **ship** Brand containers (migrate from Theme), Visual pool with explicit role tags, and release `brand_id` links
- **ship** content AI wizards with operator-configured models and release + brand prompt context
- **define** the multi-release / multi-playlist / multi-gallery data model and admin library UX
- **define** core vs module block boundaries and the page composition model
- **define** the FAQ/login + shared-link entry model (FAQ page remains required; login page adds a restricted anonymous path)
- **define** access-tier rules (admin/dev, VIP pre-access with embargo + override, registered fan, anonymous released-only) — **implementation in v0.9**
- **define** exposure/distribution architecture (Chromecast and similar) on top of the new delivery model — **implementation in v0.9+**
- rewrite audio delivery to be scalable and less resource-intensive server-side (**shipped** for UID delivery names)
- visual delivery rework: format/dimension-aware multi-variant output from registry
- a solid PWA solution with strong offline support (delivery/cache architecture first)

Suggested scope:

- admin-panel package updater workflow for hosted operators (**shipped**)
- unified Content editor pool/result UX across Playlist, Gallery, Pages, and Player layout (**shipped**)
- upload-time background delivery for audio, image, and video; Content pools gate on delivery-ready assets (**shipped**)
- artist → releases → tracks model; playlists as **independent** entities that releases can reference
- multiple playlist libraries + playlist selector in the player **Playlists** tab
- multiple gallery libraries + gallery **module blocks** embedded in pages (no Gallery player tab in the end state)
- track deep links in page blocks: a link opens the player, activates the target playlist, and focuses the track
- basic module registry or config-based enable/disable model
- brand tokens and brand selection model (replaces theme tokens)
- explicit role tags on visual assets; Visual pool with brand filter
- content AI wizards (release + brand canon)
- structured static-page content model: block JSON source with rendered HTML delivery (**shipped**)
- nondestructive media naming (display names/aliases) layered on immutable source-file identities
- role-based media handling that separates gallery media from page illustrations in admin/build behavior
- protected-audio delivery + cache-friendly playback path (architecture; pairs with PWA track)
- actual LICENSE file added to the repo

Not in v0.8 implementation scope (documented for later milestones):

- access-tier enforcement and fan credits (**v0.9** definitions implementation; credits **v1+**)
- Chromecast/cast send (**v0.9+**, after delivery definitions are stable)
- news timed release + social push (**v1+**)
- merch, chatrooms, heavy automation, many third-party integrations

### Beta tester expectations (v0.8 beta — active)

Betatesters should treat current builds as **v0.8 beta**, not a finished v1.0 platform.

Closed-beta fleet personas (Vanilla demo install, **Twisted Chronicles** band campaign, **HITZ** label + long-form shows): [USE-CASES.md](USE-CASES.md). Use those stories when giving feedback on Catalogue, player tabs, branding, and Lyrics vs Tracklist.

**Checkpoint 2026-06-16 (v0.8.3 docs):** all betatesters are on the latest build (292+). Legacy HTML pages (`data/bio.html`, `data/faq.html`) are **not** imported automatically — content lives in `data/pages/*.json` only. If you still have old HTML files on the host from backups, copy text into the Pages editor manually.

**Image delivery (v0.8 visual slice):** today's Publish step still flattens illustrations and photos to a single oversized JPEG in `optimal/`, which destroys PNG transparency (logos) and wastes bandwidth. **Operator UX shipped (2026-07-15/16):** Files → Visual merges the old Illustrations/Photos/Video tabs; Files → Brand assets is the label for legacy `media/special/`. **Still planned (Phases 0b–2):** `ast_{ULID}` visual identity, format-aware multi-variant delivery, brand filter. Workaround until migration: store transparent logos under **Brand assets** (`media/special/`).

**Updating safely:**

1. **Site update** (Dashboard) replaces application code only. Your `web-config.json`, `.env`, `data/`, `media/`, and `log/` are preserved.
2. After every successful Site update, run **Rebuild all deliverables** once (System → Deliverables). This is **normal**, not a sign that something failed.
3. Soon (v0.8.3): Publish will also prepare your content links automatically — you will not need a separate “content model upgrade” step.
4. Before large updates on heavy installs: download a ZIP backup of `data/`, `media/`, and `web-config.json` via your host until in-app backup ships.

**Release and tour workflow (target model):**

- **Releases** = campaign umbrellas (not merely one CD tracklist) with `release_date`, track membership, brand, EPK/press metadata, and associated playlists/galleries/pages.
- **Playlists** = listening packages (“Single 1”, “Album”, “Tour set”) with their own `publish_date`; latest public catalog playlist opens by default in the player.
- **Pages** = story, bio/EPK, art pages — **target:** globals from Content → Player plus **contextual** pages for the playing track’s release ([USE-CASES.md](USE-CASES.md) Twisted Chronicles). Today only globals/`show_in_player` ship.
- **Galleries** = containers embedded via page gallery blocks (no dedicated Gallery player tab).
- **News module** (v1+) = dated tour/diary posts that archive forever; pages + galleries cover this pattern until then.
- **Sharing** (playlist `/play/{id}`, track deep links, page `/pages/{id}`) gets richer cards after description + poster fields ship — not before core trust work.

- **Shipped now:** package updater; block-based Pages editor; unified Content editors; upload-time delivery; platform storage/API; **Backup & export** (component picker + import); **Brand core** (Content → Branding, release `brand_id`, player brand tokens); **SQLite activity store**; **Deliverables** page; playlist documents + runtime materialization (legacy `play/playlist.json` removed).
- **In progress in v0.8:** Visual pool + registry migration; content AI wizards; analytics rollups/export/retention; gallery module blocks; track deep links; playback/delivery architecture polish.
- **Planned (player context — policy locked 2026-07-22):** release-contextual player page tabs ([PLATFORM-MODEL.md](PLATFORM-MODEL.md), [USE-CASES.md](USE-CASES.md), [TODO.md](TODO.md)). Brand shell override (visual) and Lyrics ↔ Notes text panel role shipped.
- **Planned v0.8 management slice (remaining):** unified Visual tab polish; format/dimension-aware delivery; visual `ast_{ULID}` backfill from legacy folders.
- **v0.8 exit gate (after analytics tail + Visual pool):** sync all **3 remote beta test sites** (Vanilla / Twisted Chronicles / HITZ) to the latest published build, then audit the codebase for legacy paths, silent fallbacks, compatibility shims, and dirty hacks — remediation checkpoint before v0.9 access-tier work. See [TODO.md](TODO.md) → Beta fleet sync + legacy audit gate.
- **Defined in v0.8, built in v0.9:** login/FAQ/shared-link flow with restricted anonymous entry, access tiers (VIP pre-access, anonymous released-only, etc.), user/VIP playlists.
- **v1+:** fan credits, news module with timed release and social push, richer engagement modules (fanboard, feeds).
- **v0.9+:** Chromecast and similar cast/distribution features once playback deliverables are stable.

Feedback is welcome on all of the above; missing items in the “In progress” rows are usually **planned**, not forgotten.

### v0.8 page composition model

The flexible page builder is the long-term composition surface:

- **Core blocks** (every install): rich text, picture+text, list — with more core block types over time.
- **Module blocks** (optional per install): gallery (grid/carousel/parallax referencing a gallery library), news, fanboard, feeds, and similar.
- **Not module blocks:** playlists and lyrics remain in the **player shell**. Pages link **into** playlists via track URLs; they do not embed playlist players.

Admin keeps familiar **Playlist** and **Gallery** management areas, but each supports **multiple libraries** instead of exactly one. Placement differs:

- **Galleries:** chosen when inserting a gallery module block on a page.
- **Playlists:** chosen in the player **Playlists** tab (selector above the track list); page links can deep-link to a specific track.

### v0.8 login, FAQ, and shared links

Required for the foreseeable future:

- **FAQ page** stays required (`faq`): login info lightbox copy and operator guidance.
- **Shared URLs** that expect authentication should reroute to the login page with FAQ context explaining the site.
- **Login page** will offer **restricted anonymous entry** (listen/browse within released-only rules) in addition to full accounts — full tier behavior is **v0.9**; the UX contract is locked in v0.8 docs.

### v0.8 static page content model

Static pages use a JSON-first content contract (**shipped**):

- canonical source: JSON block documents per page in `data/pages/` plus `data/pages/registry.json`
- delivery format: server-rendered safe HTML for the player page tabs
- fresh installs seed from `biblioteca/templates/*.template.json`; no legacy HTML migration path

Shipped first-party **core** block types:

- `richtext` — headings, paragraphs, small/code styles, bold/italic/underline, links, alignment
- `picture` — image + caption body, fraction **Width**, **Flow** placement
- `list` — ordered or unordered

Schema notes:

- page identity: `id`, `title`, `updated_at`, schema `version`
- ordered `blocks` array as the main body
- legacy block shapes (`heading`, `paragraph`, `image` presets) migrate on load/save during the transition window
- module blocks (gallery, news, etc.) are a separate schema family added when each module ships

Image/layout direction (**shipped** for pictures):

- fraction widths (numerator/denominator) and flow modes (in row, end of row, wrap left/right, beside left/right)
- renderer/theme CSS decides responsive row layout; no operator pixel sizing

Future core block types may extend the editor; **module blocks** reference gallery libraries, news feeds, etc., and inherit theme/module templates.

Migration direction:

- all v0.8 betatesters are on JSON pages (`data/pages/*.json`); **legacy HTML import is closed** — no automatic import from `data/bio.html` / `data/faq.html`
- legacy HTML files on disk are ignored; operators copy content manually if recovery is needed from host backups
- HTML is generated at render/save time only; JSON remains the sole source of truth

Illustrative document shape (simplified; shipped editor uses `richtext` / `picture` / `list`):

```json
{
    "version": 1,
    "id": "bio",
    "title": "Band Bio",
    "updated_at": "2026-05-02T12:00:00Z",
    "blocks": [
        {
            "type": "richtext",
            "html": "<h2>About the Band</h2><p>Structured pages, not a full CMS.</p>"
        },
        {
            "type": "picture",
            "src": "/media/photo/optimal/band-portrait.webp",
            "alt": "Band portrait",
            "width_num": 1,
            "width_den": 2,
            "flow": "row",
            "body": "<p>Current lineup promo photo</p>"
        },
        {
            "type": "list",
            "style": "unordered",
            "items": [
                "Private release delivery",
                "Multiple playlists",
                "Gallery blocks on pages",
                "Track links into the player"
            ]
        }
    ]
}
```

Not yet required in v0.8 implementation:

- gallery module blocks (grid/carousel/parallax) — defined here, built after multi-gallery libraries land
- access-tier enforcement
- Chromecast send
- news timed release + social push
- merch implementation
- chatrooms
- heavy automation
- many third-party integrations

Documentation rule for this strategy:

- ROADMAP defines the product direction and tier model first
- TODO tracks the concrete implementation slices required to make it real
- FEATURES and README should only advertise this workflow once the admin/build path actually supports it at a trustworthy level

### PWA and exposure roadmap

- v0.8 should treat playback delivery, caching, and offline support as one scaling architecture track rather than as isolated PWA polish
- PHP should authorize access, but long-lived audio byte delivery should move to a cache-friendly protected/static delivery path instead of PHP byte streaming
- safe aggressive caching needs an explicit contract across immutable build assets, revalidated runtime data, and protected media delivery
- service worker audio caching should only land after the delivery path is cacheable and update-safe
- installed-PWA reliability should be part of the architecture scope: shell update propagation, stale-cache avoidance, and bounded offline storage/eviction
- offline logging and sync remain part of the target model, but they should follow the delivery/cache redesign rather than block it
- **Chromecast and similar cast targets:** product/architecture boundaries are **defined in v0.8** alongside the delivery model; **implementation is v0.9+** — do not ship cast send before playback deliverables are stable

Responsive design must work for all common screen sizes:
    - 360–430px: Mobile (vertical)
    - 431–767px: Large mobile/small tablet
    - 768–1365px: Tablet/small laptop
    - 1366px and up: Desktop
- Layout and --card-size automatically adapt for each segment.


## v0.8 testing plan

This is the right point to involve others more intentionally.

### Phase 1: very small closed beta

When: **now** — v0.7 exit gates are met and the first v0.8 deliverables (page editor, Content editors, delivery automation) are shipped.

Who:

- 2 to 5 trusted testers
- ideally one technical self-hoster, one artist/operator, and one non-technical admin-type user

What to test:

- setup on a fresh environment
- admin comprehension
- private release flow
- media upload/build flow
- playback/logging stability
- early multi-release model assumptions

How to run it:

- give each tester a narrow test mission
- collect issues in one place with severity labels
- ask for screenshots, browser/device, exact steps, and expected vs actual result
- prefer structured feedback over open-ended comments first

### Phase 2: limited open beta

When: after the first closed-beta issues are triaged and the platform survives multiple installs and usage patterns.

Who:

- a small public invitation group
- artists or operators who understand they are testing a beta

Scope:

- more device/browser coverage
- real-world setup variance
- broader UX feedback
- early evidence of what modules are most needed

Rules:

- keep expectations explicit
- document what is beta-only, unsupported, or likely to change
- provide a clear bug-report template
- avoid promising stability that the platform has not yet earned

## v0.9 goals

Theme: public-readiness, **access-tier implementation**, user roles, and user-facing engagement services on stable v0.8 deliverables — **not** the v2 marketing machine.

Goals:

- **implement** the access model defined in v0.8: admin/dev (full), VIP (pre-access via embargo schedule + per-item operator override), registered fan (released catalog), anonymous (released-only with clear login upsell)
- **implement** login/FAQ/shared-link entry: shared URLs → login + FAQ context; **restricted anonymous entry** on the login page
- registered-user foundation beyond today's listener accounts
- excerpt/full-access rules where needed for public previews
- public-facing artist/release browsing foundations
- **implement** Chromecast/cast send and related exposure tools on top of the v0.8 delivery architecture
- stronger theme and module polish

Suggested scope:

- anonymous users can browse static/public content and listen within released-only rules
- registered users unlock VIP/fan tiers per operator configuration
- release selector / discography structure begins to take shape
- playlist selector in player uses the multi-playlist libraries from v0.8
- gallery module blocks on pages replace the legacy Gallery player tab
- track deep links from pages activate the correct playlist and track in the player
- operator configuration for access tiers is understandable and enforced in playback + page delivery

## v1.0 goals

Theme: releaseable platform.

bandPromo v1.0 should be stable enough that a new operator can reasonably choose it for a real artist site.

Expected capabilities:

- multi-release artist presentation
- anonymous and registered access model (**tier enforcement mature from v0.9**)
- stable player and trustworthy analytics
- usable admin experience with **multiple playlists and galleries**
- page composition with core blocks + shipped module blocks (gallery layouts, etc.)
- dependable build and media workflow
- garbage collection for derived helper artifacts (for example cached validation reports under `data/validation/`), so stale reports can be regenerated and old caches pruned safely before v1.0.
- tours/events support
- simple merch/shop support or clear merch integration path
- brand shell override support sufficient for different-looking installs (token + visual shell overlay shipped; Welcome/Logged-in SFX remain Active/login)
- module structure stable enough for future expansion
- setup and branding reproducible without code surgery
- operator control preserved without centralized platform dependency

## v1.1 to v1.3 goals

- Google OAuth as the first external identity provider
- first registered-user fan features
- **fan credits** ledger: earn through engagement, apply to rebates/boons/ticket-merch perks (operator-defined)
- **news module** with timed release and social push integrations
- stronger events/tour support
- merch improvements
- light interaction features (fanboard, feeds, and similar modules)

Examples of good early registered-user features:

- saved items or favorites
- quizzes/highscores identity
- newsletter opt-in
- early-access content via VIP tier
- simple fan participation features with manageable moderation cost

## v2+ goals

Theme: integrations, **marketing machine** automation, and campaign tooling built on catalog content the operator already manages in v0.8.

Examples:

- semi-automatic marketing campaigns from existing releases and pages
- **timed worldwide drops** (`release_at_utc` or operator-local instant) with fan countdown, pre/post-drop chat, and drop-moment analytics
- social/share copy and image series scheduled from catalog state
- newsletters and mailing integrations tied to tour/release calendars
- QR and shortlink generation for campaigns
- campaign checklists and operator workflow automation
- AI-assisted **marketing** drafts (multi-post series, channel-specific variants) — distinct from v0.8 **content wizards** that fill missing fields during editing
- Google Analytics and other analytics integrations
- social publishing integrations and scheduling/background jobs (Instagram / TikTok **API posts**, not public-site share buttons). Locked still targets: IG feed **1080×1350**, Stories/Reels & TikTok **1080×1920** — generate on demand when publish exists; do not pre-emit in v0.8 `makeSocial.py` (OG stays **1200×630** only). See [MEDIA-HANDLING.md](MEDIA-HANDLING.md).
- approval workflows for generated campaign content

These belong after the core platform is stable and its content model is mature.
See the discussion in the last part of this document

## Testing and feedback process

bandPromo should not move from private beta to wider use in one jump.

Recommended release path:

1. Internal development
2. Very small closed beta
3. Closed beta with a few real operators
4. Limited open beta
5. Stable public release

For each phase:

- define what is being tested
- define what is not yet supported
- collect bug reports in a consistent format
- separate stability bugs from product-direction feedback
- turn repeated tester confusion into documentation or UX fixes

## Roadmap checkpoints

**v0.8 beta is open** (2026-06-15):

- v0.7 exit gates are met
- package updater, block-based page editor, and Content editor/delivery automation are shipped
- platform-model definitions (multi-playlist/gallery, modules, delivery) are active with explicit beta-tester expectations documented

Before opening v0.9:

- v0.8 platform deliverables are stable (multi-playlist/gallery, gallery module blocks, track deep links, delivery architecture)
- access-tier and login/anonymous specs from v0.8 are complete and reviewed
- Chromecast/cast architecture is defined against the delivery model
- multi-release and access-model assumptions are proven enough to continue
- theme/module direction is stable enough not to be reworked immediately

Before calling v1.0 releaseable:

- setup and branding are reproducible
- anonymous and registered flows both work predictably
- analytics are trustworthy enough for real operator decisions
- the platform clearly offers more than a single private album page
- installation and operator handoff are documented well enough for real trial use


## v2+ Direction: Semi-Autonomous Promotion, Direct Fan Relationships, and Platform Independence

### Background and intent

bandPromo started as a self-hosted way for artists to present music better than simply sending files through cloud storage links, download folders, or generic sharing services. The original need was not to build another streaming platform, but to give the artist control over presentation, context, access, identity, and listener experience.

The project has since grown into a broader idea: small artists, independent musicians, micro-labels, and experimental creators need a credible way to publish, preview, promote, and monetize their work outside large platform ecosystems. Many of these artists receive little practical benefit from the discovery advantages of major streaming platforms. Their work is technically available, but effectively buried beneath large catalogs, algorithmic preference structures, playlist gatekeeping, advertising budgets, major-label leverage, and high-volume content strategies.

bandPromo should therefore remain focused on artist-owned presence rather than platform dependency. The purpose is not to replace Spotify, Apple Music, YouTube, Bandcamp, SoundCloud, or social media. The purpose is to give artists a home base that they control: their own installation, their own domain, their own release presentation, their own fan contact surface, and their own route to direct support, sales, downloads, mailing lists, private listening, press access, and campaign material.

For v2 and beyond, this can be expanded into semi-autonomous promotion: tools that help the operator prepare, publish, test, schedule, and distribute promotional material while keeping the artist or site operator in control of final decisions.

### Core principle

bandPromo must not become a centralized music platform.

The safest and most strategically consistent model is self-hosted software where each artist or operator runs their own installation and remains responsible for the site they publish. The project provides tools, structure, documentation, and workflows. The operator remains responsible for rights, content, privacy, integrations, payments, communication, compliance, moderation, hosting, backups, and public-facing decisions.

This separation is already central to the project documentation. bandPromo is described as self-hosted publishing software that provides tools for running a music site, but does not take over responsibilities that belong to the site operator. The operator is responsible for the actual installation they run, including content, rights, privacy, hosting, third-party integrations, and real-world consequences of what they publish.

v2+ development should preserve this distinction. Every new promotional feature should be evaluated against the question:

Does this feature help the operator promote their own work from their own installation, or does it move bandPromo toward becoming a centralized platform, intermediary, publisher, payment processor, or moderation authority?

If a feature increases central responsibility, it should either be avoided, made optional and locally operated, or documented with clear boundaries.

### Strategic scenario

The music market is moving toward more platform control, not less. Large streaming services are likely to continue presenting themselves as neutral distribution systems while increasingly controlling visibility, classification, monetization, anti-fraud enforcement, metadata rules, recommendation systems, and eligibility for payment.

AI-generated and AI-assisted music has intensified this trend. Public debate often presents the issue as if the production method itself is the problem: whether music is “real,” “human,” “authentic,” or “worthy” of royalties. This framing is misleading. Production method alone is a weak criterion for artistic value or payment eligibility.

The more concrete problems are different:

- false streams
- bot activity
- impersonation
- misleading artist identity
- unclear rights
- platform spam
- manipulated discovery systems
- opaque royalty withholding
- lack of transparency in payment redistribution
- excessive dependency on centralized platforms

Fraud is a real problem, but it is not specific to AI music. Fraud is unlawful or illegitimate regardless of whether the content was made by a human, an AI system, a band, a producer, a session musician, a DJ, a sample library, a synthesizer, or a drum machine. Treating fraud as an argument against a production method is a category error. It shifts attention away from platform infrastructure, enforcement, payment transparency, and business-model weaknesses.

bandPromo should be built around the opposite premise: if a listener voluntarily chooses music, supports the artist, joins a mailing list, buys a release, requests access, shares a private preview, or engages with the artist directly, that relationship has value. The artist should not have to depend entirely on opaque platform metrics to prove that value exists.

### Why semi-autonomous promotion matters

Small artists usually lack the resources that make platform participation effective. They may be able to upload music to streaming services, but they often cannot compete for visibility. Their real opportunity is direct connection: a controlled landing page, a strong presentation, a private listening experience, a press-ready release page, a direct support link, and a reusable promotional workflow.

Semi-autonomous promotion should help with the repetitive and technical parts of this work without replacing the artist’s judgment.

**Operator-facing guide:** [MARKETING-STRATEGY.md](MARKETING-STRATEGY.md) explains the teaser → bridge → experience model, low-cost tactics for sending listeners to the operator’s own domain, and how bandPromo milestones support that workflow.

Possible v2+ goals include:

- preparing release pages from structured metadata
- generating draft promotional copy for different audiences
- creating press-kit style summaries
- producing share text for social platforms
- preparing email/newsletter drafts
- generating private listening links or access-controlled campaigns
- creating QR/share assets for posters, gigs, and physical promotion
- suggesting missing metadata, weak descriptions, or unclear presentation
- helping operators produce consistent release announcements
- assisting with campaign timing and checklist workflows
- summarizing analytics in plain language for the operator
- identifying which tracks, pages, or campaigns receive meaningful engagement
- helping the artist understand direct audience behavior without depending on platform dashboards

These features should be assistant-like, not fully autonomous. The operator should remain responsible for publishing, sending, claiming, targeting, and approving promotional material. bandPromo can draft, suggest, package, organize, validate, and schedule locally, but should not silently act as the artist in public channels without explicit operator approval.

### Product boundaries for v2+

The following boundaries should guide future design.

bandPromo should remain an artist-site toolkit, not a streaming service.

bandPromo should not position itself as a replacement for large platforms. It should be a controlled home base that can coexist with them. Artists may still link to Spotify, Apple Music, YouTube, Bandcamp, SoundCloud, Ko-fi, Patreon, Vipps, Stripe, PayPal, merch stores, ticketing pages, or other tools. bandPromo should help the artist own the presentation and relationship around those links.

bandPromo should not become the payment flow by default.

The safest model is for payments, donations, purchases, subscriptions, and tips to go directly through services controlled by the operator. bandPromo may provide buttons, embeds, links, metadata, or integration points, but should avoid holding funds, splitting revenue, storing payout details, or becoming a payment intermediary unless a future version intentionally accepts the legal and operational burden of doing so.

If future support, membership, or premium-access features need provider-side verification or synchronization, bandPromo should treat that as an operator-owned integration layer for services such as Ko-fi, Patreon, Stripe, PayPal, Vipps, or similar APIs. That layer should stay provider-agnostic where practical, remain optional per installation, and be designed after the anonymous vs registered vs premium-access model is defined clearly enough to know what audience or member access bandPromo is actually enforcing.

If bandPromo later sells paid modules, themes, services, or other install-locked add-ons directly, a simple visible installation UID will not be enough to protect those entitlements. The future-proof design boundary should be: core bandPromo stays fully functional without central activation, while install-locked paid add-ons may rely on a stronger installation identity and provider-side entitlement check that can distinguish a legitimate moved/restored install from a copied clone.

bandPromo should not become a central discovery catalog.

A central catalog would change the project’s role. It could create moderation, ranking, takedown, spam, copyright, and platform-governance obligations. If discovery features are ever considered, they should be treated as a separate strategic decision with a much higher risk profile.

bandPromo should avoid central user accounts across installations.

A shared identity system would create privacy, security, and governance responsibilities. Local accounts per installation are safer and more consistent with self-hosting.

bandPromo should not make legal, copyright, privacy, or tax decisions for operators.

It may provide warnings, documentation, checklists, and configuration prompts, but the operator remains responsible for rights clearance, privacy notices, retention choices, payment tools, tax obligations, consumer-law obligations, and local compliance.

bandPromo should not classify music as more or less valuable based on production method.

AI-assisted, AI-generated, sample-based, synth-based, recorded, programmed, live, remixed, or hybrid production methods should not be treated as inherently superior or inferior by the software. The relevant operational questions are whether the operator has the rights to publish the material, whether the presentation is honest, whether the artist identity is misleading, whether third-party rights are violated, and whether the audience interaction is real.

### AI and disclosure position

bandPromo should not shame or devalue music because AI tools were used. However, it may support honest disclosure when the operator wants or needs it.

A practical distinction should be made between:

- AI as a production tool
- AI-assisted writing, arrangement, mixing, mastering, artwork, or metadata
- fully AI-generated music
- artist-inspired style references
- impersonation or misleading identity
- unauthorized voice cloning
- unclear third-party rights
- fraudulent or deceptive presentation

The software should not assume that AI involvement is a problem. It should help the operator describe the work accurately where relevant. If disclosure fields are added, they should be neutral and operator-controlled, not warning labels designed to reduce perceived value.

Possible future metadata fields could include:

- production notes
- tools used
- credits
- human contributors
- AI-assisted elements
- rights notes
- source material notes
- voice/likeness confirmation
- public disclosure text

These should support transparency, not moral judgment.

### Risk analysis

#### 1. Role creep

The largest strategic risk is that bandPromo gradually moves from self-hosted software into platform behavior. Features such as central hosting, central analytics, shared accounts, centralized discovery, payment processing, moderation services, or hosted fan databases would change the responsibility profile.

Mitigation:

- keep installations independent by default
- avoid central services unless clearly separated
- document operator responsibility
- make integrations operator-owned
- avoid holding money or content centrally
- avoid global ranking or catalog features unless intentionally designed as a separate product

#### 2. Rights and content risk

Operators may upload music, lyrics, artwork, videos, logos, photos, samples, remixes, AI-generated assets, or promotional material they do not have rights to use. bandPromo cannot verify ownership or licensing.

Mitigation:

- require operators to accept responsibility during setup
- include clear rights reminders in upload and publish flows
- link setup acknowledgment copy directly to the shipped `LICENSE` and operator-responsibility documentation instead of hiding the actual terms behind vague UI summaries
- provide optional metadata fields for credits and rights notes
- avoid claims that bandPromo clears, verifies, licenses, or approves material
- keep takedown responsibility local to the operator unless a future hosted service exists

#### 3. Privacy and analytics risk

v2+ promotional tools may increase the amount of audience data collected. Playback logs, admin logs, mailing lists, access links, campaign tracking, referrers, and fan interactions can all become personal data depending on configuration and jurisdiction.

Mitigation:

- data collection should be minimal by default
- analytics should be local to the installation where possible
- retention settings should be configurable
- privacy documentation templates may be provided, but not presented as legal advice
- operators should be reminded that they are responsible for their own privacy obligations
- avoid central telemetry unless explicitly opt-in and documented
- treat any future install/update webhook as maintenance telemetry only, with narrow payloads and explicit operator control
- ask for that maintenance-reporting consent in friendly setup/admin language that makes the optional nature clear

#### 4. Payment and tax risk

Direct monetization is attractive, but it is also one of the fastest ways to increase legal and operational complexity. If bandPromo handles money directly, the project may take on payment, refund, tax, consumer-law, fraud, chargeback, accounting, and possibly KYC/AML-related issues.

Mitigation:

- prefer external payment links and operator-owned accounts
- do not store card details or payout information
- do not split or redistribute money
- make it clear that the operator is responsible for payment provider terms, tax reporting, refunds, and customer obligations
- treat any future native payment layer as a major separate milestone, not a small feature

#### 5. Support burden

A self-hosted tool with PHP, Python, media processing, ffmpeg, metadata handling, image generation, hosting requirements, HTTPS, admin access, and build steps can create significant support expectations. Free software can still create heavy emotional and practical support load if boundaries are unclear.

Mitigation:

- keep support boundaries explicit
- document common installation patterns
- separate user docs, operator docs, and developer docs
- provide diagnostics where possible
- avoid promising timelines, compatibility, or individual deployment help
- make errors understandable so operators can resolve issues locally

#### 6. Security risk

A bandPromo installation may include admin login, file uploads, media handling, generated public pages, private links, analytics, logs, and third-party integrations. Misconfiguration or vulnerabilities could harm operators, visitors, or the project’s reputation.

Mitigation:

- keep secure defaults
- enforce HTTPS except for localhost
- use CSRF protection and secure password hashing
- minimize writable directories
- document file permissions
- provide update guidance
- avoid unnecessary server-side complexity
- treat private listening links and access control as security-sensitive features

#### 7. Reputational risk

Because bandPromo supports independent publishing and may be used by artists who work with AI, some people may mischaracterize the project as an AI-spam tool or anti-platform project. That would weaken its real position.

Mitigation:

- describe bandPromo as an artist-owned presentation and promotion toolkit
- avoid language that sounds like evasion or circumvention
- emphasize direct fan relationships, controlled presentation, private listening, and independent release infrastructure
- distinguish lawful AI-assisted creativity from fraud, impersonation, and spam
- keep quality-focused workflows and clear operator responsibility

#### 8. Over-automation risk

Semi-autonomous promotion can easily become spam if it pushes content outward without enough human control. The goal should not be to automate artists into becoming promotional bots.

Mitigation:

- keep operator approval in the loop
- generate drafts rather than auto-posting by default
- provide campaign checklists instead of uncontrolled automation
- rate-limit or discourage repetitive outreach
- help artists improve message quality rather than maximize volume
- avoid manipulative growth-hacking language in the product

### Design philosophy for v2+

The software should help artists do the work that platforms usually make invisible: presentation, packaging, access, metadata, fan communication, release context, private previews, and direct support.

It should not try to solve every part of the music economy. It should not promise discovery. It should not pretend that self-hosting automatically creates an audience. It should make the artist’s own audience relationship more usable, more professional, and less dependent on centralized platforms.

The core value is control:

- control over domain
- control over release presentation
- control over context
- control over access
- control over audience relationship
- control over support links
- control over private listening
- control over analytics
- control over how the artist explains the work

This is especially important for smaller artists because they often do not benefit meaningfully from platform-scale discovery. If they are already doing the work of finding their own listeners, bandPromo should help them capture more value from that work.

### Long-term product direction

A mature v2+ version of bandPromo could become a self-hosted promotional operating system for independent artists.

Possible long-term modules:

- release landing pages
- private listening rooms
- press/EPK pages
- fan access links
- mailing list integration
- campaign checklists
- social/share copy drafting
- QR and shortlink generation
- direct support/payment link management
- local analytics summaries
- tour/gig promotion pages
- media kit downloads
- release archive
- lyric and artwork presentation
- controlled preview campaigns
- AI-assisted metadata and copy suggestions
- optional disclosure/credit metadata
- operator-facing rights and privacy reminders
- exportable static builds for low-cost hosting
- import/export tools to avoid lock-in

The project should continue to prioritize independence, portability, and operator control. Artists should be able to move their site, back up their content, change integrations, replace payment providers, export data, and retain their domain identity.

### Summary position

bandPromo exists because small artists need more than a file link and less than a centralized platform.

The project should give artists a professional, self-hosted way to present music, manage private listening, communicate with fans, and connect support or payment options without surrendering their identity and audience relationship to large platforms.

v2+ semi-autonomous promotion should support that goal by helping operators prepare and manage promotional work, not by taking control away from them. The operator remains responsible for what they publish, how they present it, which rights they rely on, which integrations they enable, and how they communicate with their audience.

The central principle should remain:

bandPromo provides the toolset.  
The operator controls the installation.  
The audience chooses what they value.