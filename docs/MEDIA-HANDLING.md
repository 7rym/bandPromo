# bandPromo Media Handling

This document describes how bandPromo should handle source media, canonical masters, and publish-ready delivery files.

It replaces the older narrow metadata-only framing. Metadata is still a core concern, but it now sits inside a broader media-handling policy that covers intake scenarios, packaging, delivery targets, and what the platform can realistically improve for non-technical operators.

Current state: `v0.7 build 243`

## Why this matters

bandPromo should help artists publish professionally even when their input files are weak in packaging quality.

The platform cannot turn low-fidelity audio into higher-fidelity audio. That remains the responsibility of the artist, producer, or engineer.

The platform can improve everything around that audio:

- metadata quality
- artwork packaging
- lyrics embedding
- naming consistency
- release structure
- delivery sizing and formatting

That is the practical value proposition behind the media-handling model.

## Core media policy

bandPromo should use three explicit media tiers:

- `original`: the exact user upload, preserved untouched
- `master`: a bandPromo-authored canonical asset with corrected packaging and metadata
- `delivery`: publish-ready derivatives generated from the master tier for actual playback and display contexts

bandPromo should also distinguish media by role and scope, not only by file type or storage folder.

This distinction becomes mandatory once the platform supports multiple releases.

## Media roles and scopes

The platform should stop using `cover` as a loose catch-all term. A file type such as PNG or JPG is not enough to describe how an asset behaves in the platform.

The important distinction is:

- file type: audio, image, video, text
- media role: what the asset is for
- scope: whether the asset belongs to the install, a release, a track, or a page/module

### Install scope

Assets that belong to the whole install or active theme:

- logo
- favicon/app icons
- poster / share image
- background image/video
- welcome audio / logged-in audio

These should be treated as install-wide shell assets, not as release packaging.

Within that install-wide shell layer, the distinction should be:

- `brand assets`: logo, favicon/app icons, poster / share image
- `theme assets`: background image/video, welcome audio, logged-in audio

### Release scope

Assets that belong to one release / playlist / album context:

- primary release cover
- release-level gallery media when a gallery is scoped to that release
- release-level packaging metadata

The `release cover` should be a first-class concept, not merely an inferred image role.

### Track scope

Assets that belong to one track:

- optional track cover override
- track-specific lyrics, metadata, and packaging state

Track cover should be distinct from release cover. A release may use one shared cover while some tracks optionally override it.

### Page or module scope

Assets that belong to content pages or future modules:

- bio/page illustrations
- module-specific promotional graphics
- future merch/event/community visuals

These should not be treated as release covers unless the operator explicitly assigns them that role.

### Gallery scope

Gallery media should be a separate role family:

- gallery photos
- gallery videos

Gallery media is for browsing and presentation, not for release packaging by default.

### Role model summary

The intended product concepts are:

- `brand assets`: install-wide or release-level identity assets such as logo and poster / share image
- `theme assets`: presentation assets such as backgrounds and shell audio
- `release cover`: one primary cover for the release/playlist/album context
- `track cover`: optional per-track artwork
- `gallery media`: photos and videos for gallery presentation
- `page illustrations`: images for static pages and future modules

Storage folders do not have to match these roles one-to-one immediately, but the admin UI, validation rules, and build logic should move toward this role model.

### Current exposed model vs prepared internal model

Before bandPromo exposes true multi-release administration, operators should still experience the product as one branded site.

That means the current admin/UI model should remain:

- one visible site identity
- one visible set of shell/theme choices
- no release-scope terminology exposed unless multi-release is a real product feature

Under the hood, the code and docs should still prepare the future structure so later multi-release support is additive rather than a rewrite.

The practical distinction is:

- exposed now: one branded site
- prepared internally: separate `brand`, `theme`, and `social` concerns
- exposed later: install defaults plus optional release overrides

So when the internal schema uses names such as `install.brand.*` or `release.brand.*`, that should be understood as preparation for future scope-aware inheritance, not as a signal that current admins must think in terms of releases already.

## Inheritance model

Once bandPromo supports multiple releases, the platform should prefer inheritance over duplication.

The target model is:

- install defaults
- release overrides
- track-specific exceptions only where needed

This keeps a multi-release install manageable even when one label or artist presents many releases with distinct visual identities.

### Install defaults

Install defaults define the shared baseline for the whole site.

For identity assets, the install-level baseline should be mandatory so the site shell always has a usable fallback even when a release does not define its own branding yet.

Examples:

- base theme tokens and typography
- platform shell layout defaults
- default logo / favicon / app icons
- default poster / share image
- default share behavior
- default fallback theme assets

These values should be reusable across all releases unless a release explicitly overrides them.

### Release overrides

Each release should be able to override the install defaults where the release has its own identity.

Typical release overrides:

- release cover
- release gallery
- release palette or token overrides
- release background media
- release logo variant
- release poster / share image
- release-specific descriptive metadata

The important rule is that a release should override only what it needs. It should not require a fully separate theme definition when install defaults are already sufficient.

### Track-specific exceptions

Track-level overrides should exist, but remain intentionally narrow.

Typical track exceptions:

- track cover override
- track lyrics and packaging state
- track-specific metadata refinements

Track-level overrides should not replace release-level configuration unless a real per-track difference exists.

### Inheritance rules

The intended resolution order is:

1. track-level override when explicitly defined
2. release-level override when explicitly defined
3. install-level default otherwise

This should apply to both assets and presentation-related settings.

### Why inheritance matters

Without inheritance, a label presenting many releases would have to duplicate the same theme and asset definitions repeatedly.

With inheritance:

- one install can define the shared shell once
- each release can override only the pieces that give it its own identity
- tracks can remain lightweight unless they need true exceptions

This is the maintainable path for labels, micro-labels, and artists with large catalogs.

## Current config transition map

bandPromo's current `web-config.json` is still a single-release shape, so several fields mix install-wide concerns and release-specific concerns.

Before the multi-release model lands, the project should treat the current fields as a transition surface and map them deliberately into install scope, release scope, or track scope.

### Install-level fields

These fields belong to the install shell and should normally stay install-wide:

- `site.url`
- `site.language`
- `site.author`
- `social.twitter`
- `social.facebook`
- `social.instagram`
- `social.tiktok`
- `social.youtube`
- `media.logo`
- `media.welcome_audio`
- `media.loggedin_audio`

These define the site host, base language, operator identity, install-wide social accounts, and shell-level theme assets.

### Release-default fields

These fields should resolve at release scope, with install defaults allowed where reuse makes sense:

- `social.share_image`
- `social.share_image_width`
- `social.share_image_height`
- `social.categories`
- `social.keywords`
- `media.background_image`
- `media.background_video`

In practice, a label may want one global default poster/share image or one default background treatment, but each release must be able to override them cleanly.

### Mixed fields that should be split

Some current fields are too overloaded for the future model and should be split into clearer concepts:

- `site.name`
	- current meaning: the visible identity of the whole site and the current release at the same time
	- target direction: separate `install title` / shell name from `release title`
- `site.short_name`
	- current meaning: manifest shorthand and release shorthand mixed together
	- target direction: keep an install-level app short name, with an optional release-specific short label only where needed
- `site.description`
	- current meaning: site description, release summary, and social summary blurred together
	- target direction: separate install description from release description
- `media.cover`
	- current meaning: a loosely inferred cover path used as the primary visible artwork
	- target direction: replace with an explicit `release cover` concept at release scope

This split is important because a multi-release install cannot keep treating one field as both the site shell identity and the active release identity.

### Track-level exceptions

The current config does not yet expose track-level theme fields, but the intended exception layer is narrow:

- optional `track cover` override
- track-specific metadata refinements
- track-specific lyrics or packaging state where needed

Track scope should remain metadata- and artwork-focused. It should not become a second full theme system.

### Resolution contract for the current field surface

Until the data model is split more explicitly, the target behavior should be interpreted as:

1. install-only fields always resolve from install scope
2. release-default fields resolve from release scope first, then fall back to install defaults
3. `media.cover` should be treated as a transition field whose long-term meaning is `release cover`
4. track-level cover should be a separate field, never another meaning hidden behind `media.cover`

### Immediate schema implications

The current planning direction implies these concrete future changes:

- keep install shell fields separate from release identity fields
- rename or replace `media.cover` with an explicit release-level field
- allow release-level overrides for poster/share image and background media
- keep welcome/login audio and logo as install-theme assets unless a later product need proves otherwise
- keep track-level overrides intentionally narrow so the operator does not manage a full theme per track

## Target schema naming direction

The future multi-release schema should stop treating `site`, `social`, and `media` as one flat surface for all concerns.

The preferred direction is to separate:

- install shell defaults
- release identity and release presentation
- track exceptions

### Proposed install-level blocks

Install-wide fields should move into blocks whose names describe the shell, not the current release.

Suggested direction:

- `install.site`
	- canonical site URL
	- default language
	- operator/author identity
- `install.brand`
	- mandatory shell logo
	- favicon/app icons
	- mandatory default poster/share image
- `install.theme`
	- welcome audio
	- logged-in audio
	- install-level fallback background assets
- `install.social`
	- install-wide social handles
	- default share behavior when a release does not override it

The goal is that install-level fields describe the site shell that exists even when no single release is currently being highlighted.

### Proposed release-level blocks

Release-specific fields should move into blocks that describe one release as a product entity.

Suggested direction:

- `release.identity`
	- release title
	- optional short label
	- release description
- `release.brand`
	- optional release logo variant
	- optional release poster/share image
- `release.theme`
	- release cover
	- release background image/video
- `release.social`
	- release-specific keywords/categories where needed
- `release.gallery`
	- release-scoped gallery/media presentation configuration

In practical product terms, the future rule should be:

- every install must provide a site-level logo and a site-level poster/share image
- every release may override the site logo and poster with its own release-specific identity assets
- current single-release admins should still see only the simple current model until multi-release presentation is a real product feature

This is the level where the current `site.name`, `site.short_name`, `site.description`, `media.cover`, and most visible share-preview identity should ultimately land.

### Proposed track-level block

Track-level customization should stay intentionally small.

Suggested direction:

- `track.presentation`
	- track cover override
	- other rare presentation exceptions only when truly needed

Track metadata, lyrics, and packaging data may still live alongside playlist/track records, but the theme-facing exception surface should remain narrow.

### Compatibility bridge from current names

The current single-release config can be translated into the future model like this:

- `site.url` -> `install.site.url`
- `site.language` -> `install.site.language`
- `site.author` -> `install.site.author`
- `site.name` -> `release.identity.title`
- `site.short_name` -> `release.identity.short_label` or install-level app short name, depending on actual usage
- `site.description` -> `release.identity.description` by default, with a separate install description added later
- `social.twitter|facebook|instagram|tiktok|youtube` -> `install.social.*`
- `social.share_image*` -> `release.brand.poster*` with mandatory `install.brand.poster*` defaults available
- `social.keywords|categories` -> `release.social.*` by default, with install-level fallbacks allowed
- `media.logo` -> `install.brand.logo`, with future `release.brand.logo` override support
- `media.welcome_audio` -> `install.theme.welcome_audio`
- `media.loggedin_audio` -> `install.theme.loggedin_audio`
- `media.background_image|background_video` -> `release.theme.*` with install fallback support
- `media.cover` -> `release.theme.cover`

### Naming rules

To keep the model stable, the schema should follow these rules:

- use `install` only for true shell-wide defaults
- use `release` only for release-specific identity and presentation
- keep track presentation overrides in a dedicated narrow block, not mixed back into release or install fields
- avoid another generic `media` bucket once roles are explicit
- prefer field names that describe product meaning (`release.theme.cover`) over storage location or file type

## Migration and compatibility contract

bandPromo cannot switch from the current `site` / `social` / `media` structure to the future scoped schema in one step.

The runtime currently reads literal dotted keys such as `site.name`, `site.description`, `social.share_image`, and `media.cover` directly through `get_config()`.

That means the migration path must include a compatibility layer, not only a new schema.

### Migration goals

The migration should guarantee all of the following:

- existing installs keep working with current `web-config.json` files
- the runtime can begin reading future scoped fields without breaking old templates or admin flows
- admin save paths can migrate gradually instead of requiring one large schema flip
- install defaults and release overrides can coexist during the transition

### Recommended migration phases

#### Phase 1: compatibility reads

Introduce support for reading future scoped keys while still accepting current keys.

Examples:

- `release.identity.title` with fallback to `site.name`
- `release.identity.description` with fallback to `site.description`
- `release.theme.cover` with fallback to `media.cover`
- `install.brand.logo` with fallback to `install.theme.logo` and `media.logo`
- `release.brand.poster` with fallback to `release.social.share_image` and the current single-release poster/share image field

During this transition, the important product rule remains:

- current admins edit one branded site
- `brand` naming is internal cleanup and future-proofing
- release-specific brand editing should not become an operator concept until multi-release exists

At this phase, writes may still target the current structure, but reads become scope-aware.

#### Phase 2: dual-write admin saves

Once reads support both formats, admin save endpoints may begin writing both:

- the new scoped field
- the current legacy field needed by older runtime consumers

This phase prevents partial upgrades from breaking pages that still read only the old names.

#### Phase 3: schema-first admin UI

After the runtime and save endpoints support the scoped schema, admin labels and editors should present the new model directly:

- install shell
- release identity
- release theme
- release sharing
- track exceptions only where relevant

Legacy field names should become implementation details, not operator-facing language.

#### Phase 4: legacy cleanup

Only after read paths, writes, and seeded templates are fully migrated should the legacy aliases be removed.

This is the point where `site.name`, `site.description`, `media.cover`, and similar transitional fields can stop being first-class runtime keys.

### Compatibility rules for runtime resolution

During the migration window, config resolution should prefer the new scoped field first and then fall back to the legacy field.

The intended priority is:

1. scoped field in the future schema
2. legacy single-release field
3. hardcoded default or seeded template default

This lets new installs adopt the better structure without breaking old installs.

### Compatibility rules for writes

During the dual-write phase, the system should follow these rules:

- if the admin edits a scoped release field, also update the corresponding legacy single-release field when one still exists
- if the admin edits a true install-shell field, do not silently copy it into release-specific fields
- if a field has no clean legacy equivalent, keep it scoped-only and document that it is unavailable to old templates

The goal is to duplicate only transitional fields, not to keep two competing schemas forever.

### First migration targets

The safest first migration targets are the fields that already have clear meaning today:

- `media.cover` -> `release.theme.cover`
- `media.logo` -> `install.theme.logo`
- `media.welcome_audio` -> `install.theme.welcome_audio`
- `media.loggedin_audio` -> `install.theme.loggedin_audio`
- `social.share_image` -> `release.social.share_image`
- `site.name` -> `release.identity.title`
- `site.description` -> `release.identity.description`

These fields are already used concretely in the current runtime and will give the migration effort the highest leverage.

### Fields that need special care

Some current fields should not be moved mechanically without a product decision:

- `site.short_name`
	- may represent install-level app naming, release shorthand, or both
- `social.keywords`
	- may need both install-level defaults and release-level overrides
- `social.categories`
	- may need the same install-default plus release-override pattern
- `media.background_image` and `media.background_video`
	- should support release overrides, but may also serve as install-level fallbacks

These fields need an explicit migration rule before code starts dual-writing them.

### Template and setup implications

The seeded config template should lag behind the runtime migration only briefly.

Once compatibility reads exist, the setup/template path should be updated to generate the future scoped schema so new installs start clean.

Old installs should rely on compatibility reads until their config is rewritten or re-saved through admin tooling.

### Original tier

The original tier exists for trust, recovery, and future regeneration.

- never rewritten in place
- preserved as-uploaded
- may be weak, incomplete, or inconsistently tagged
- should remain available as the archival source

### Master tier

The master tier is where bandPromo helps the operator become more professional.

It may include:

- corrected title, artist, album, and track number
- embedded cover art
- embedded lyrics where supported
- cleaned naming and organization
- a tagged FLAC master created from WAV when appropriate

Important constraint:

- lossy source audio may be repackaged and better tagged, but it must not be misrepresented as higher-fidelity audio

Preferred operator model:

- when a newly uploaded source format has a defined master path, bandPromo should create or queue the master immediately after upload
- after that point, operator-facing repair/editing tools should work against the master representation, not against the preserved original upload
- delivery generation should read from the current approved master, not directly from the original, except where the original temporarily satisfies the master contract

This keeps the operator workflow simple:

- upload source once
- let bandPromo prepare the canonical working copy
- edit/fix the canonical package
- publish delivery outputs from that canonical package

The original remains available for trust, recovery, replacement, and future regeneration, but it should usually disappear from day-to-day operator work once the master is ready.

### Delivery tier

The delivery tier is for publishing, not archival purity.

Delivery assets should be optimized for actual player and UI needs rather than simply mirroring the largest source asset.

Examples:

- image sizes based on actual cover/card/lightbox dimensions
- audio delivery profiles based on practical listening contexts
- JPEG/WebP for non-transparent artwork where that is the real best-fit delivery format

## Logical tiers vs physical storage

The product model and the current folder names are not the same thing.

This distinction must stay explicit while bandPromo transitions away from the old `optimal` naming.

### Current reality

Today the repository effectively has:

- `original` folders that hold uploaded source material
- `optimal` folders that hold generated web-facing outputs

That current `optimal` layer should be treated as a legacy generated-output bucket, not as the future `master` tier.

Reason:

- the current optimizer mainly creates web-serving outputs such as MP3 delivery files and compressed JPEG/WebP-style image outputs
- those files are generated for playback/display efficiency, not as the canonical packaging source for future regeneration

So the current mapping is:

- current `original` folder -> `original` tier
- current `optimal` folder -> temporary legacy `delivery` bucket

It is not:

- current `optimal` folder -> `master` tier

### Cross-media rule

The logical tier model should be the same across media types:

- `original`
- `master`
- `delivery`

But the delivery outputs under that model should remain media-specific.

bandPromo should unify the operator mental model, not force audio, image, and video into identical packaging fields or identical derivative names.

### Target storage direction

The long-term filesystem/build direction should move from:

- `media/<type>/original/`
- `media/<type>/optimal/`

to something conceptually closer to:

- `media/<type>/original/`
- `media/<type>/master/`
- `media/<type>/delivery/<variant>/`

Where `<type>` may include `audio`, `img`, `photo`, `video`, and later other explicit media-role families.

The important rule is not the exact folder spelling yet. The important rule is that `master` and `delivery` must become separate product concepts in both code and storage.

### Delivery naming guidance by media type

The future delivery variants should be named by user context, not by vague quality words such as `optimal`.

Examples:

- audio delivery variants: `standard-stream`, `mobile-stream`, `lossless-download` when genuinely supported
- image delivery variants: `thumb`, `card`, `lightbox`, `share`
- video delivery variants: `poster`, `standard-stream`, `mobile-stream`

This keeps the system honest about what each generated asset is for.

### Master-tier guidance by media type

The master tier is still one concept, but the master contract differs by media type.

Audio master:

- corrected canonical packaging
- embedded artwork/lyrics where supported
- normalized naming and release metadata
- may be FLAC generated from WAV, but must not misrepresent lossy sources as lossless

Image master:

- corrected canonical source for future delivery generation
- may normalize filename, orientation, metadata, or embedded descriptive fields
- should preserve alpha/transparency and source capabilities when that matters for future outputs

Video master:

- corrected canonical source for future poster/transcode generation
- may include normalized naming, poster association, and packaging metadata
- should not be prematurely flattened into one streaming format if the canonical edited source should remain richer

### Important implementation constraint

`master` does not have to mean that every uploaded file is copied immediately into a new second file.

The requirement is conceptual first:

- the system must know which asset is the canonical regeneration source
- delivery files must be generated from that canonical source when possible
- originals must remain preserved untouched

In practice, some already-good uploads may temporarily satisfy both the `original` and `master` contract until a repair/export action creates a distinct master artifact.

Preferred build sequencing:

- on upload: validate intake, preserve original, and create or queue the master as early as the format policy allows
- after master creation: treat the master as the normal admin-facing working asset
- in background: generate or refresh delivery variants as required for playback, cards, lightboxes, sharing, and downloads

This should feel "magic" to the operator while still keeping the underlying source-preservation guarantees intact.

### Migration rule for current code

Until the folder migration is implemented, the codebase should follow these rules:

- keep treating `media/*/original/` as the immutable source-upload area
- treat `media/*/optimal/` as legacy delivery output, not as a canonical master area
- do not rename current `optimal` folders to `master` without also splitting delivery variants out properly
- do not introduce new intake formats unless the intended `master` and `delivery` behavior for those formats is defined first

This sequencing matters because broadening upload acceptance without a locked canonical-output model would only spread ambiguity through the build pipeline.

## Bundled placeholder asset policy

bandPromo currently ships with bundled placeholder/demo assets for empty installs.

Those assets are useful for setup, screenshots, and first-run verification, but they should not behave like operator-owned media once a real install is underway.

### Problem to avoid

If a bundled placeholder file is tracked by the repository and the operator deletes it locally, it can reappear on a later pull.

That is technically correct from git's point of view, but it feels broken and annoying from the operator's point of view if the admin UI treated that file as normal user content.

### Required product rule

Bundled placeholder assets must carry a machine-readable origin/status distinction separate from normal user uploads.

The first practical contract should be:

- `bundled-placeholder`: repository-authored seed/demo media
- `user-upload`: operator-provided runtime media
- later, when needed: `generated-master` and `generated-delivery`

The current `bandPromo_*` naming convention may be used as a temporary implementation hint, but filename prefixes alone should not be the long-term product contract.

### Admin visibility rule

Bundled placeholder/demo assets should be hidden by default in normal operator-facing media pickers and file lists.

They may be shown only when one of these is true:

- the install is still in an explicit empty/demo/setup state
- the operator enables a dedicated `show bundled demo assets` toggle
- a developer/admin troubleshooting view intentionally asks to include them

This means an operator who replaces demo assets with real media should not keep seeing the old bundled files return as if they were ordinary active content.

### Deletion rule

Deleting a bundled placeholder from the operator-facing view does not need to mean "remove it from git forever."

Instead, the product should support a local hidden/disabled state for bundled placeholders so that:

- they no longer appear in normal admin media browsing
- they are not offered in ordinary media pickers
- a future git pull does not make them feel resurrected inside the UI

In other words, the operator-facing action is closer to `hide bundled demo asset from this install` than to `delete canonical repository file`.

### Recommended first implementation shape

The first implementation does not need a complex asset database.

A small runtime manifest or flag file is enough if it can record per-asset visibility/origin state such as:

- bundled placeholder or not
- hidden in this install or not
- active/currently referenced or not

That gives the media browser and media picker enough information to suppress bundled demo files by default without breaking the repository's tracked setup assets.

## What bandPromo should improve

bandPromo should help operators fix or generate:

- core track metadata
- album/release packaging consistency
- embedded artwork
- embedded lyrics
- download-ready corrected masters
- display-sized and bandwidth-aware delivery assets

bandPromo should not claim to:

- improve the underlying fidelity of lossy audio by wrapping it in FLAC
- treat oversized source images as inherently optimal for the web
- require expert tagging knowledge before a user can begin publishing

## Practical intake scenarios

These are realistic scenarios the platform should explicitly prepare for.

### 1. Release-ready source

- FLAC with complete metadata and embedded artwork
- ideal current path

### 2. Good audio, weak metadata

- FLAC or WAV with partial or missing tags
- should be accepted and repaired through admin tooling

### 3. Raw DAW export

- WAV only
- filename-driven naming
- no metadata
- very common and should be treated as a first-class operator path

### 4. Lossy-only source

- MP3 or other compressed source is all the operator has
- should usually be accepted with quality warnings, not rejected outright

### 5. Mixed-quality release set

- some tracks are complete, others are weak
- some have covers, others do not
- track numbering is incomplete or inconsistent

### 6. Filename-driven packaging

- the only usable metadata comes from filenames or folder order
- bandPromo should infer what it can and ask for confirmation

### 7. Single-track release with minimal assets

- one song, one image, almost no album packaging
- should be easy to publish without making the operator learn audio tagging tools first

## Intake policy matrix

This matrix translates the strategy into concrete expected behavior.

| Scenario | Accept upload? | Auto-infer / auto-fix | Publish blocker? | Master output target | Delivery target |
| --- | --- | --- | --- | --- | --- |
| FLAC with complete tags and artwork | Yes | Normalize minor naming/whitespace only | No | Keep FLAC as canonical tagged master | Generate delivery audio and image variants from master |
| FLAC with partial tags | Yes | Infer from filename/folder order where safe; allow admin repair | Yes, if core release fields remain missing | Corrected tagged FLAC master with embedded art/lyrics when available | Generate delivery variants from corrected master |
| WAV with no metadata | Yes | Infer title/track order from filename where possible | Yes, until title/artist/cover or configured release defaults are resolved | Tagged FLAC master created from WAV after operator confirmation | Generate delivery variants from FLAC master |
| WAV with partial metadata or sidecar assets | Yes | Merge filename, sidecar image, release defaults, and operator edits | Yes, if required publish fields remain unresolved | Tagged FLAC master with embedded artwork/lyrics where possible | Generate delivery variants from FLAC master |
| MP3 with solid tags | Yes | Normalize minor inconsistencies only | No, unless metadata is contradictory or broken | Preserve original MP3; optionally create corrected packaged master without claiming higher fidelity | Generate delivery streams from packaged master/original as policy allows |
| MP3 with weak or missing tags | Yes | Infer from filename/folder order where safe; allow admin repair | Yes, if core publish metadata remains missing | Corrected packaged master with fixed tags, artwork, and lyrics where possible | Generate delivery variants without presenting the source as lossless |
| Mixed-quality album | Yes | Reuse good tracks as-is, flag weak tracks individually, infer sequence from filenames/order | Yes, only for tracks/release fields still unresolved | Per-track corrected masters; release package normalized across all tracks | Consistent release-wide delivery variants |
| Filename-driven release only | Yes | Parse title, track number, artist, disc/order from naming conventions | Yes, if inference confidence is too low or required fields remain empty | Corrected masters after operator confirmation | Delivery variants from corrected masters |
| Single-track release with minimal assets | Yes | Use site/release defaults and filename inference where safe | Yes, only if the published page would be obviously broken | One corrected master with minimal required packaging | Mobile, player, and cover delivery variants sized for actual UI |
| Unsupported/corrupt audio file | No for build; yes for upload retention | None beyond diagnostics | Yes | No master until operator replaces or converts it | None |

## Validation severity model

bandPromo should classify media issues into four levels:

### Hard blockers

These prevent build or reliable asset generation.

- unreadable or corrupt source file
- unsupported source format with no current conversion path
- missing file referenced by the release model
- image/audio processing failure that leaves no usable asset

### Publish blockers

These allow upload and draft management, but should block publish until fixed.

- missing title when no safe inference exists
- missing artist when no site/release default is intentionally approved
- missing release or track ordering where the published presentation would be misleading
- missing cover when the release is configured to require one

### Warnings

These should not block upload or publish on their own.

- lossy-only source
- missing lyrics
- inconsistent album casing or naming
- suspicious filename-derived metadata
- oversized source image that will be downscaled for delivery
- mixed metadata quality across a release

### Autofixable issues

These are cases where bandPromo should propose or apply a safe repair.

- whitespace cleanup in titles/artists/albums
- deriving track order from filename prefixes
- carrying forward approved release-level defaults
- embedding artwork into the master when separate cover art exists
- embedding lyrics from approved sidecar text files

## Delivery target principles

`Optimal` should be treated as a deprecated label in planning language. Delivery outputs should instead be defined by actual use case.

### Image delivery targets

Initial target buckets should be explicit:

- `thumb`: small list/grid previews
- `card`: standard player and content-card artwork
- `lightbox`: enlarged artwork for the current largest practical UI view
- `share`: social sharing derivative sized for the platform target

Guidance:

- do not serve 2048px PNGs when the UI never presents them near that size
- default to high-quality JPEG or WebP for non-transparent delivery assets
- keep the original upload and any corrected master artwork separately from delivery derivatives

### Audio delivery targets

Initial target buckets should be explicit:

- `archive`: original upload preserved untouched
- `master`: corrected canonical package for operator download and regeneration
- `standard-stream`: default web playback target
- `mobile-stream`: lower-bandwidth/mobile-friendly target when needed
- `lossless-stream` or `download`: only when the source and policy genuinely support it

Guidance:

- do not present repackaged lossy audio as lossless quality
- delivery tiers should be chosen by real listening context, not by inherited source-file size alone
- the operator should understand why each delivery asset exists and what user context it serves

## Current v0.7 support stance

Current supported source audio formats for playlist generation:

- `FLAC`
- `MP3`

Known but currently unsupported source audio formats are surfaced as skipped during build validation, including:

- `WAV`
- `AIFF`
- `M4A`
- `AAC`
- `OGG`
- `WMA`

This is an operator-safety improvement, not full support.

## Current pipeline model

The current pipeline is split into two main metadata paths:

- source audio reading for playlist generation
- FLAC-to-MP3 conversion with ID3 tag writing for delivery files

That means the platform does not yet use one single universal metadata format internally.

## Why this matters for multi-release support

The current folder-first model is survivable for a single artist / single release install, but it becomes ambiguous once one install can hold multiple releases.

Without explicit roles and scopes, the platform cannot cleanly answer:

- which cover belongs to which release
- whether a track inherits the release cover or overrides it
- which images are gallery-only and should never be treated as release art
- which assets are install-wide theme assets versus release-scoped assets

This is why media-role cleanup is not merely terminology work. It is preparation for the multi-release platform model.

## Target build orchestration model

The current codebase already keeps the main build functions in separate scripts. That should remain the direction.

The orchestration layer should treat these as concrete task units instead of collapsing most changes into a broad `full build` label.

### Task units

The intended task units are:

- `playlist-scan`: read source audio, infer ordering, refresh `play/playlist.json`, and update `play/playlist-validation.json`
- `audio-delivery`: generate or refresh publish-ready audio derivatives from the approved source/master path
- `image-delivery`: generate or refresh publish-ready covers, photos, and illustration derivatives
- `social-assets`: regenerate social/share image derivatives
- `manifest`: rewrite `site.webmanifest`

### Automation rule

bandPromo should prefer this operator model:

- run cheap tasks automatically and silently where possible
- queue or expose only tasks that are materially heavy or slow
- avoid generic `build required` nudges when the system can finish the necessary light work immediately

In practice, the heavy work is mostly media transcoding and image recompression. Playlist scanning, metadata validation, share-image generation, and manifest writing are comparatively light.

Current implementation note:

- Files -> Audio metadata saves already run `playlist-scan` automatically after a real change so `play/playlist.json` and `play/playlist-validation.json` refresh immediately.
- The save flow now preserves an existing embedded `tracknumber` or backfills it from the current playlist position when the master tag is blank.
- A true no-op metadata save is treated as a no-op and does not create a fresh build-required reason.
- Real metadata changes still fall back to the older coarse build-required model, so task-level follow-up remains incomplete.

### Action matrix

This matrix defines the preferred future behavior.

| Admin action | Task units | Default behavior | Operator message |
| --- | --- | --- | --- |
| Upload audio source | `playlist-scan`, `audio-delivery`, sometimes `image-delivery` if cover extraction changes files | Run `playlist-scan` automatically; queue `audio-delivery` as heavy work | Show pending delivery generation only if derivatives are not ready yet |
| Upload photo | `image-delivery` | Run automatically if cheap; otherwise queue quietly and finish in background | Usually no explicit build warning |
| Upload illustration | `image-delivery` | Run automatically if cheap; otherwise queue quietly and finish in background | Usually no explicit build warning |
| Upload theme/share/logo/background asset | `image-delivery` and/or `social-assets` depending on usage | Run automatically | No generic build warning; surface only direct file/validation errors |
| Edit site basics text | `manifest` when manifest-facing fields changed | Run automatically | No build warning |
| Edit social/share text fields | `social-assets`, `manifest` when affected | Run automatically | No build warning unless a referenced asset is missing |
| Change theme media paths | `social-assets` and/or `image-delivery` when relevant references changed | Run automatically when only references change; queue only if a heavy derivative task is genuinely needed | Prefer a targeted status message over a generic build badge |
| Reorder playlist | none for delivery generation; save order only | Save immediately | No build warning |
| Edit gallery entries or order | none for delivery generation in the common case | Save immediately | No build warning |
| Edit bio/pages | none | Save immediately | No build warning |
| Edit metadata in Files -> Audio | `playlist-scan`, sometimes `audio-delivery` if delivery tags/embed data must be rewritten | Run scan automatically; queue delivery rewrite only when necessary; suppress new pending work on true no-op saves | Explain exactly what changed and what is being regenerated |

### Naming guidance for admin UI

The current Files sub-panel labeled `System` should move toward `Theme` if it remains the home for install-specific branding and presentation assets.

Reasoning:

- `System` sounds internal and implementation-oriented
- these files are operator-owned and install-specific
- assets such as logo, poster/share image, and background media are better understood as theme/design inputs than as system internals

If the panel later grows to include truly technical install assets, the naming can be revisited. In the current product shape, `Theme` is the more accurate operator-facing label.

### Nondestructive naming policy

bandPromo should stop forcing operators to work directly with raw source filenames as the main visible identity for tracks and media.

The intended future rule is:

- the original uploaded filename remains preserved as the immutable source identity
- operator-facing display names and aliases may change without losing that original source identity
- future master and delivery naming may follow those operator-facing names, but only through an explicit runtime mapping layer rather than by forgetting the original source anchor

This keeps recovery and trust simple while letting the UI move away from exposing filesystem-style names and file extensions in normal operator workflows.

## Current metadata contract

The playlist generator is implemented in [scripts/makePlaylists.py](scripts/makePlaylists.py).

### Core playback fields currently read

For source files, the current reader looks for:

- `TITLE` or ID3 `TIT2` for track title
- `ARTIST` or ID3 `TPE1` for artist
- `ALBUM` or ID3 `TALB` for album
- `TRACKNUMBER` or ID3 `TRCK` for track ordering

### Lyrics currently read

The current reader looks for lyrics in this order:

- `LYRICS`
- `UNSYNCEDLYRICS`
- ID3 `USLT` frames
- a `.txt` file with the same basename as the audio file

Important detail:

- `UNSYNCEDLYRICS` is commonly seen in FLAC/Vorbis-style tags and sometimes in APEv2-tagged MP3s
- `USLT` is the current ID3 lyrics frame used on generated MP3 output

### Description / comment currently read

The player description currently reads from:

- `DESCRIPTION`
- fallback: `COMMENT`

### Cover art currently resolved from

The current cover lookup path is:

1. embedded audio artwork
2. same-basename image file in `media/img/original/`
3. configured release cover from `web-config.json` (`media.cover`)

Embedded artwork is currently read from:

- FLAC picture blocks
- MP3 ID3 `APIC` frames

When the configured release cover is used as fallback, the build copies it into `media/img/original/` as a normal generated cover asset so the rest of the pipeline can treat it like any other track cover.

## Current FLAC optimization path

The FLAC-to-MP3 optimization path is implemented in [scripts/optimizeMedia.py](scripts/optimizeMedia.py).

When the source file is FLAC, the optimizer currently reads these Vorbis-style fields:

- `title`
- `artist`
- `album`
- `date`
- `year`
- `tracknumber`
- `genre`
- `albumartist`
- `comment`
- `bpm`
- `initialkey`
- `mixartist`
- `unsyncedlyrics` or `lyrics`
- embedded picture data

This is currently the richest metadata path in the codebase.

## Current tags written to generated MP3 files

Generated MP3 delivery files are tagged with ID3v2.4.

The current writer sets:

- `TIT2` from title
- `TPE1` from artist
- `TALB` from album
- `TDRC` from date or year
- `TRCK` from track number
- `TCON` from genre
- `TPE2` from album artist
- `COMM` from comment
- `TBP` from BPM
- `TKEY` from musical key
- `TPE4` from mix artist
- `USLT` from lyrics
- `APIC` from embedded artwork

## Current validation output

Playlist generation writes `play/playlist-validation.json` with:

- supported source extensions
- skipped unsupported source files
- total track counts
- per-track warning codes such as `missing_title_tag`, `missing_artist_tag`, `missing_album_tag`, `missing_track_number`, `missing_lyrics`, and `missing_cover_art`
- cover source details (`embedded`, `sidecar`, `configured`, or `missing`)

The admin build log reads this file through `biblioteca/get-build-log.php` and appends a human-readable metadata validation summary when a build is no longer running.

## Operator-facing validation language

The admin UI should not expose raw warning-code names such as `missing_title_tag` as the primary operator message.

The first operator-facing layer should be fix-oriented and use short status labels that answer two questions immediately:

- can this release be published yet
- what should the operator do next

The preferred labels are:

- `Cannot build`: the source file or referenced asset is unusable and bandPromo cannot produce the required output
- `Fix before publish`: the release can remain in draft/admin use, but the missing information should be corrected before the operator presents it as finished
- `Recommended fix`: the release can still be published, but the package is weaker or less complete than intended
- `Can be repaired automatically`: bandPromo can safely normalize or embed the missing information once the required source input is available

These labels are the operator-facing translation layer for the underlying severity model:

- `Cannot build` -> hard blocker
- `Fix before publish` -> publish blocker
- `Recommended fix` -> warning
- `Can be repaired automatically` -> autofixable issue

The admin summary should lead with the fix-oriented label and plain-language action, with raw tag terminology treated as secondary detail.

Examples:

- `Fix before publish: add a track title for Track01.flac`
- `Recommended fix: add lyrics for Midnight City if lyric display is expected`
- `Cannot build: replace or convert unsupported source file demo.wav`
- `Can be repaired automatically: embed the approved cover into the corrected master`

### Current warning-code mapping guidance

The current `playlist-validation.json` warning codes should be interpreted in operator language like this until richer validation objects exist:

| Current warning code | Preferred operator message | Default operator label | Notes |
| --- | --- | --- | --- |
| `missing_title_tag` | Add a track title | `Fix before publish` | If title can be inferred safely, bandPromo may prefill a suggestion rather than block immediately |
| `missing_artist_tag` | Add the artist name | `Fix before publish` | May downgrade when an approved install/release default is intentionally used |
| `missing_album_tag` | Add the release/album name | `Recommended fix` | Should not block simple single-release playback on its own |
| `missing_track_number` | Confirm the track order | `Fix before publish` for multi-track releases; otherwise `Recommended fix` | Severity depends on whether ordering is already reliable from filename/order context |
| `missing_lyrics` | Add lyrics if lyric display is part of the release | `Recommended fix` | Missing lyrics should not block audio publication by themselves |
| `missing_cover_art` | Add cover art or confirm the approved fallback cover | `Fix before publish` when no approved fallback exists; otherwise `Recommended fix` | Distinguish missing track art from a valid release-cover fallback |

If multiple issues affect one track, the admin summary should show the highest-severity label first and list the remaining recommended fixes underneath it.

## Current limitations

- The admin UI now shows an operator-facing validation summary with direct actions into metadata editing and playlist order, but it still does not keep those issues visible in a persistent operator task list.
- Metadata repair now covers the first audio-master editor pass, including common text fields, lyrics, cover selection, release date, and operator-facing title/version handling, but broader packaging workflows and selective inline quick-edit are still incomplete.
- Some MP3 files tagged mainly through APEv2 may still behave inconsistently compared with FLAC or clean ID3v2-tagged files.
- Real audio metadata changes still flow through the older coarse build-required state, so the operator messaging is better for no-op saves than for task-specific follow-up after actual edits.
- The current `optimal` label is too vague; delivery targets should be defined by actual usage context rather than implied quality alone.

## Recommended direction

The next practical improvements should be:

- introduce a persistent operator task/notification surface for unresolved validation and build tasks, with automatic resolution when the underlying issue is fixed
- break the coarse build-required model into concrete task states so real metadata edits do not look like generic full-build work when only lighter follow-up is pending
- add selective quick-edit for simple metadata fields such as title, artist, release/album name, and lyrics without turning the Build tab into a second full editor
- continue expanding dedicated metadata/master tools for packaging fields and corrected-master workflows
- preserve originals while generating corrected masters and delivery derivatives separately
- redefine `optimal` into explicit delivery targets for player, mobile, cover, and lightbox contexts
- implement the intake policy matrix above as the working contract for build, admin repair tools, and future exported masters

## Offline playback and delivery architecture

Offline playback should not be treated as a standalone service-worker feature.

It depends on the delivery architecture for audio.

### Current tension

The current PHP audio endpoint is useful for one immediate concern:

- gate audio bytes behind the authenticated session

But that same design works against two future goals:

- scalable concurrent playback
- offline-capable PWA audio caching

If every playback request must stream through PHP, the browser and service worker do not get a clean cacheable media path.

### Planning implication

The offline-audio task should be interpreted as a sequence:

1. separate authorization from byte delivery
2. move audio delivery to a cacheable/static or server-assisted protected path
3. let the service worker cache that delivery path for offline playback
4. treat offline logging as a separate queue/sync concern

### Target direction

The preferred long-term architecture is:

- PHP decides whether the user may access the track
- the actual audio bytes are served by the web server or another cache-friendly protected delivery mechanism
- the player uses stable delivery URLs that a service worker can cache for offline replay
- logging and session analytics are synced separately and must not depend on the stream endpoint staying online

### Why this matters

Without this change, the product risks solving the wrong problem in the wrong order:

- adding cache logic around PHP-streamed audio is harder than necessary
- scaling playback consumes PHP workers that should be reserved for application logic
- the PWA cannot become truly offline-friendly while playback still depends on a live PHP stream endpoint

So the correct framing is:

- offline playback is an audio-delivery architecture task first
- service-worker caching is the implementation layer that comes after that architectural change

### PWA audit implication

The service worker should be treated as a maintained part of the playback architecture, not as a background utility that can drift unattended.

That means the project should explicitly audit:

- which requests are cached and which are deliberately excluded
- how installed clients receive updated player, shell, and config assets
- where stale cache behavior could make the installed app worse than the normal browser experience
- whether offline behavior on phones actually improves the listening experience or only adds complexity

Installed PWA behavior should be evaluated against a simple product standard:

- faster or more reliable startup
- predictable updates
- clear offline behavior
- offline listening that is genuinely useful, not nominally present