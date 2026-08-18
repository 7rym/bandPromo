# bandPromo Media Handling

This document describes how bandPromo should handle source media, canonical masters, and publish-ready delivery files.

It replaces the older narrow metadata-only framing. Metadata is still a core concern, but it now sits inside a broader media-handling policy that covers intake scenarios, packaging, delivery targets, and what the platform can realistically improve for non-technical operators.

Current state: `v0.8`

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

- `original`: the exact user upload, preserved untouched (**write-once**; legal I/O after intake is download/delete/provenance only)
- `master`: a bandPromo-authored canonical asset (`ast_{ULID}`) — **the working copy**
- `delivery`: publish-ready derivatives generated **from the master** for playback and display

This applies to **audio, Visual, Sound effects, and Brand assets**. Findings and the completion plan: [MASTER-TIER-AUDIT.md](MASTER-TIER-AUDIT.md).

bandPromo should also distinguish media by role and scope, not only by file type or storage folder.

This distinction becomes mandatory once the platform supports multiple releases.

## Media roles and scopes

The platform should stop using `cover` as a loose catch-all term. A file type such as PNG or JPG is not enough to describe how an asset behaves in the platform.

The important distinction is:

- file type: audio, image, video, text
- media role: what the asset is for
- scope: whether the asset belongs to the install, a release, a track, or a page/module

### Install scope

Assets that belong to the whole install or **base brand**:

- logo and lockups
- favicon/app icons
- default poster / share image
- background image/video
- welcome audio / logged-in audio
- style reference and portrait assets curated for the brand

These live in the global **Visual** (images/video) or **Sound effects** (brand UI audio) warehouses. Each Brand document curates a cross-media `library_asset_ids` list; storage ownership (`brand_id`) is provenance, not library membership.

### Release scope

Assets and fields that belong to one release:

- **release cover** (`poster_asset_id` on the release document — picked from Visual pool filtered by the release's linked brand)
- release-level gallery media when a gallery is scoped to that release
- release-level packaging metadata and EPK fields
- **`brand_id` link** — each release has **one** identity brand (`release.brand_id` ↔ `brand.release_id`). Album vs single packages are playlists under that release, not peer releases sharing an “era” brand. See [PLATFORM-MODEL.md](PLATFORM-MODEL.md) ownership rules.

The `release cover` should be a first-class concept on the release, not stored inside the brand container.

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

The intended product concepts are expressed as **explicit role tags** on registry assets (primary) plus container references (usage validation):

| Role tag | Meaning |
|----------|---------|
| `brand-logo`, `brand-poster` | Identity lockups and default share sources on the brand |
| `brand-portrait` | Band member / lineup photos |
| `style-ref`, `typography-sample` | Mood boards and design references (may never appear on-site) |
| `shell-background-image`, `shell-background-video` | Player/login shell visuals on the brand |
| Sound effects (`role: sfx`) | Brand UI clips; welcome/logged-in (and future interaction sounds) assigned via brand **slots**, not per-file roles |
| `release-cover` | Album/EP/single art linked via release `poster_asset_id` |
| `track-cover` | Optional per-track artwork override |
| `gallery` | Gallery presentation media |
| `page-illustration` | Static page and module visuals |
| `unassigned` | Bulk pool upload until operator assigns a role |

**Brand container** holds tokens (colours, typography), narrative fields, and `asset_id` refs into the Visual pool — it does not replace per-release covers.

Storage folders do not match these roles. The admin UI, validation rules, and build logic use registry identity and explicit references, not folder tabs. **Shipped operator surface:** Files → Audio (catalogue music), Files → Visual (global image/video warehouse), Files → **Sound effects** (global brand UI audio warehouse), and Files → Brand assets (the selected Brand's curated Visual + SFX library).

### Current exposed model vs prepared internal model

Before bandPromo exposes true multi-release administration, operators should still experience the product as one branded site.

That means the current admin/UI model should remain:

- Catalogue of releases with per-release identity brands
- Install **base** brand for login / shell media baseline under **Content → Branding** (Set as base), not a separate Settings → Theme editor
- Release brand tokens overlay in the player while tracks from that release play

Under the hood, brand documents and `release.brand_id` links are already the ownership model ([PLATFORM-MODEL.md](PLATFORM-MODEL.md)).

The practical distinction is:

- exposed now: Catalogue + Branding editor; base brand pointer; player CSS tokens **and** visual shell (logo/still/living) from the selected playlist’s owning release brand; login shell + SFX stay Base
- prepared / planned: hard content-pool scoping
- do **not** plan “many releases share one era brand” as peer Releases — use playlists under one Release

Legacy **`media/special/`** may still exist on disk for migration lookup. Files → **Brand assets** is a **filter/role** on Visual (and Brand-tab audio → Sound effects), not a product intake tree.

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

Each release links to **one** brand (`brand_id`) for that campaign’s identity. Cover art stays on the release (`poster_asset_id`).

Typical release-specific fields:

- **release cover** (`poster_asset_id`) — always on the release; picked from Visual pool filtered by linked brand
- release gallery membership
- release-specific descriptive metadata and EPK

Do **not** model many catalogue SKUs as peer Releases that share one brand era — use playlists under one Release instead ([PLATFORM-MODEL.md](PLATFORM-MODEL.md)).

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

This is the maintainable path for labels, micro-labels, and artists with large catalogues.

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

- when a newly uploaded source format has a defined master path, bandPromo creates the master immediately after upload (`ast_{ULID}` in `media/audio/master/`)
- upload also fills registry **display** from embedded tags (filename stem fallback if tags cannot be read) so Files → Audio is editable without a full Rebuild
- upload-time automation prepares delivery MP3s named to the **master stem** (`ast_{ULID}.mp3`); failures stay visible in Notifications with the real error
- after that point, operator-facing repair/editing tools work against the master representation, not against the preserved original upload
- delivery generation reads from the current approved master, not directly from the original, except where the original temporarily satisfies the master contract

This keeps the operator workflow simple:

- upload source once → master + display + delivery prepare automatically
- edit/fix the canonical package and assign it to a release
- save the playlist → light delivery (if still missing) + **republish that playlist’s player payload** (no full Deliverables rebuild required for `/play`)
- use System → Deliverables (Rebuild all) for site-wide recovery, PWA/social, or when automatic preparation failed

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
- current `master` folder -> `master` tier (`ast_{ULID}` filenames)
- current `optimal` folder -> temporary legacy `delivery` bucket (MP3 playback files; names should match master stems, e.g. `ast_{ULID}.mp3`)

Operator-facing summary:

- **original/** — your upload filename, never rewritten
- **master/** — internal canonical file (`ast_…`); admin metadata edits target this tier
- **optimal/** — generated MP3s the player serves by default; not meant for manual browsing

Files that exist only in **original/** (for example a failed upload that never registered a master) are not editable or playable until catalogue registration succeeds. Files that exist only as **masters** (Demo PCF / campaign import, no intake original) are listed in Files from the asset registry and are editable. Normal Files → Audio uploads create the master immediately and prepare delivery without waiting for Rebuild all deliverables. Playlist save republishes that playlist’s player payload so `/play` can load without a full site rebuild.

Bundled demo audio is **not** git-tracked. The entire `/media` tree is ignored. Demo assets arrive via setup import of `bandPromo-demo.pcf` (or local seed) and are built into the normal three-tier layout on the host. Admin Publish does not re-download demo packages. Sound effects use the same three-tier idea under `media/sfx/{original,master,optimal}` (login plays delivery MP3 when ready). **Rebuild all deliverables** runs an `sfx-delivery` stage that materializes masters and builds tagless optimal MP3s when missing or stale (same helper as upload/import backfill); already-fresh deliveries are skipped.

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

- `media/audio/…` (catalogue music — unchanged family)
- `media/sfx/original/` — Sound effects (brand UI clips; not release tracks)
- legacy visual split leftovers: `media/img/`, `media/photo/`, `media/video/`, `media/special/` (dual-read only)
- flat `media/*/optimal/` delivery buckets

to:

- `media/audio/original/`, `media/audio/master/`, `media/audio/optimal/` — **audio files only**
- `media/visual/original/`, `media/visual/master/`, `media/visual/delivery/<asset-id>/<variant>/` — **one visual family** for stills and video
- `media/sfx/{original,master,optimal}/` — Sound effects

**Shipped:** new Visual uploads (stills + video, including Brand) write only to `media/visual/original/`. Relocate moves leftover `img`/`photo`/`video`/`special` originals into that tree and deletes the legacy copy. **Publish** and **Site update** cheaply test for those legacy folders and, when present, run a one-shot relocate of every registered Visual original (then remove empty legacy dirs). Setup no longer creates `media/img|photo|video` trees. Dual-read remains only for unregistered leftovers until gone.

### Delivery naming guidance by media type

The future delivery variants should be named by user context, not by vague quality words such as `optimal`.

Examples:

- audio delivery variants: `standard-stream`, `mobile-stream`, `lossless-download` when genuinely supported
- image delivery variants: `thumb`, `card`, `huge`, `lightbox`, `share`
- video delivery variants: `poster`, `standard-stream`, `mobile-stream`
- **Video soundtrack policy:** Brand shell living backgrounds (`role=shell-background-video`) and **living covers** (role or track assignment) build **silent** `standard-stream` (video track only). Gallery, page, and other visual videos **keep soundtrack**. Player still mutes living covers and shell video at playback.

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
- **Metadata (locked):** keep camera **EXIF** as read-only provenance (`DateTimeOriginal` / GPS → registry `captured_at`). Write operator **title / description / keywords** as **IPTC Core via XMP** on the master only (not into EXIF editorial fields). Autofix heals empty registry `display` from embedded IPTC/XMP + EXIF dates. Formats in scope: JPG/JPEG, PNG, WebP. Registry `master_width` / `master_height` are the master pixel size (Files list Dimensions); delivery variant sizes stay on `delivery.variants`.

Video master:

- corrected canonical source for future poster/transcode generation
- may include normalized naming, poster association, and packaging metadata
- should not be prematurely flattened into one streaming format if the canonical edited source should remain richer
- **Container (locked):** remux intake → `media/visual/master/ast_*.mkv` with **stream copy** (no re-encode). Matroska tags hold title / description / keywords / date. Original intake preserved. **Delivery stays MP4** (`standard-stream.mp4`; silent for brand `role=shell-background-video` and living covers). Upload allowlist may expand to MKV once masters are MKV; browsers never load master MKV.

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
- heavy delivery work such as full video transcoding should run as a background task with visible progress/state in Notifications, not as a blocking upload-time step that can make the operator think the upload has stalled

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

The current `bandPromo_*` naming convention may be used as a temporary implementation hint for **seed** files, but filename prefixes alone must not keep an operator re-upload marked as bundled. Files index `origin` is authoritative after upload. Deletion is always a real unlink (subject to locked-demo and reference guards) — there is no delete-as-hide path.

### Admin visibility rule

**Campaign demo media** (Audio / Visual pools owned by the install’s protected demo release — see `demo_release_id` / `demo_release_hidden` in `data/install-preferences.json`) follows **Hide demo catalogue**. When the demo release is hidden, those owned campaign files stay out of normal browsing and pickers. Filename prefixes such as `bandPromo_*` are **not** the hide gate.

**Hide blockers:** if a demo-owned campaign asset is still referenced by a non-demo playlist, gallery, page, or release, hide is refused and the operator is shown what/where.

**Brand shell media** (Files → Brand assets / Sound effects: logo, poster, still/living backgrounds, welcome/logged-in SFX) stays visible while brands still reference it. Do not auto-hide those files just because the operator uploaded other Brand assets, and do not fold them into “Hide demo catalogue.” Per-file soft-hide is retired.

**Locked demo delete:** deleting campaign media that belongs to the locked demo release is denied until the demo release is unlocked on localhost.

Generated social crops (`*_facebook` / `*_twitter`) remain provenance-marked and are not treated as operator uploads.

### Deletion rule

Deleting media from Admin is a real delete (unlink), subject to:

- locked demo campaign ownership guards
- in-use / multi-reference detach requirements

Demo catalogue visibility is **release-level only** (`demo_release_hidden`). Do not soft-hide individual files (including Brand assets / Sound effects or legacy `bandPromo_*` names) as a substitute for that toggle. Registry identity is `ast_*`; filename prefixes are provenance/display hints only.

### Recommended first implementation shape

Per-install soft-hide maps for bundled placeholders are retired. Prefer:

- release ownership + lock for campaign demo media
- `demo_release_hidden` for operator hide of that campaign
- registry `origin` for provenance badges (not hide/delete policy)

That keeps Files pools and media pickers consistent without a second hide system.

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
- `huge`: fullscreen stills and shell still backgrounds contained within **1920×1080px** without cropping, stretching, or upscaling
- `lightbox`: enlarged artwork for the current largest practical UI view
- `share`: social sharing derivative sized for the platform target

#### Share / social derivatives (locked 2026-08-08)

Two different products must not be conflated:

| Kind | Consumer | Status | Dimensions |
|------|----------|--------|------------|
| **Link preview (OG)** | Site meta tags when a URL is pasted into Facebook, X, Slack, etc. | **Shipped** via `makeSocial.py` | **1200×630** (≈1.91:1) for Facebook and Twitter/X |
| **Native social posts** | Instagram / TikTok (and similar) posts created through **operator APIs**, not a share button on the public site | **Deferred** to v2+ marketing machine | Register targets now; **do not** generate until a publish job consumes them |

Locked native-post targets (still images / covers when that path ships):

- Instagram feed: **1080×1350** (4:5); square **1080×1080** (1:1) as optional alternate
- Instagram Stories / Reels and TikTok: **1080×1920** (9:16)

Policy until API publishing exists:

- Do **not** extend `makeSocial.py` / `social-assets` to emit Instagram or TikTok crops.
- Keep generating only OG Facebook/Twitter derivatives from the brand **poster / share** Visual (master → original → delivery card last resort). Write those JPEGs to `media/share/`, never next to Visual originals or masters.
- Masters must stay large enough for later 1080-class crops; never treat the 720px `card` delivery as the long-term share source.
- When social publish lands, derivatives are generated **on demand for that job** (and may include vertical **video**, captions, and covers — not poster stills alone).

Guidance:

- do not serve 2048px PNGs when the UI never presents them near that size
- choose delivery **format by content need**, not by habit: opaque photos and track artwork may use high-quality JPEG or WebP; assets with alpha (logos, icons, overlays) must keep transparency (PNG or WebP with alpha) — never flatten to white without operator consent
- choose delivery **dimensions by display context**, not by source upload size: resize and compress to the largest size each UI surface actually needs, plus a sensible retina margin
- keep the original upload and any corrected master artwork separately from delivery derivatives

### Current implementation (Visual delivery, master-tier complete)

What ships today for still Visual delivery (from Visual masters):

- `scripts/optimizeMedia.py` writes role-based variants under `media/visual/delivery/{ast_*}/` (e.g. `thumb`, `card`, `huge`, `logo`) from `media/visual/master/`
- Audio delivery is tagless MP3 under `media/audio/optimal/{ast_*}.mp3`
- Video delivery is Visual-only: `media/visual/delivery/{ast_*}/standard-stream.mp4` (+ poster); stem `media/video/optimal/` dual-write/read is removed
- Brand shell media uses Visual delivery via `asset_ids` (T4); leftover `media/special/` on disk is migration/heal lookup only
- Player covers and living covers resolve Visual delivery URLs only
- Rebuild is incremental (XXH3 skip-if-fresh where available)
- Publish success log summarizes media / player playlists / share images / site manifest separately (not a path dump)

Remaining debt from the older visual-identity track is closed under [MASTER-TIER-AUDIT.md](MASTER-TIER-AUDIT.md) T0–T7.

Physical `media/special/` retirement is complete for new writes (T4); leftover files on disk remain migration/heal lookups only.

### Visual media rework plan (v0.8.4)

Goal: (1) optimize **size and dimensions** for real UI contexts; (2) pick **codec/format** from content requirements; (3) extend **`ast_{ULID}` asset identity** to all visual uploads; (4) **unify** Illustrations, Photos, and Video into one Visual pool with tags and picker filters. Audio remains a separate family.

#### Phase 0 — display-context audit (policy, before coding)

Before changing the optimizer, inventory every surface that loads a delivery image and record the **maximum rendered width/height** at common breakpoints (phone portrait, phone landscape/PWA, tablet, desktop).

Seed matrix from current CSS (to be verified on real devices and updated in this doc):

| Context | Where | Approx max display (CSS) | Notes |
|---------|--------|--------------------------|-------|
| `logo` | Player header `.content-logo-img` | 320px wide (+ 2× retina → ~640px delivery cap) | Often PNG with alpha; theme asset |
| `thumb` | Playlist row `.playlist-track-cover`, cover-flow, bio track list | 70–100px (delivery max edge **100px**) | Square-ish; shipped |
| `card` / `optimal` | Player flip cover `.cover-art` inside `--card-size` (max 600px) | delivery max edge **720px** | Shipped |
| `huge` | Player lightbox fullscreen stills; login/player shell still backgrounds | contain inside **1920×1080px** | Shipped; lightbox + shell still prefer `huge`, fall back to `card` |
| `card` | Admin/media file list `.media-file-thumb` | list 70 / 100 / 125 px (S / M / L; default **M** 100px, matching delivery `thumb`) | Admin Files → Visual / Brand assets list; Grid view uses larger cards |
| `grid` | Page gallery block `.page-gallery-item img` | Grid: natural ratio, column cap 2–6; Carousel: ~78% pane width, max-height ~520px contain; Animated: frame sized to the photo (contain, no crop) | List thumbs 168px square |
| `picture` | Page picture blocks | fraction of content column (½, ¾, full) | Derive max from page layout + viewport |
| `lightbox` | Player/page lightbox enlarged view | aliases **`huge`** (≈96vw / 94vh frame) | Falls back to `card` when huge is missing |
| `share` | `makeSocial.py` OG Facebook/Twitter crops | **1200×630** shipped | Instagram/TikTok native-post sizes registered only — generate with v2+ API publish, not site share |

Deliverable: a checked-in **delivery context registry** (JSON or markdown table) that maps each context → max pixel box → default variant name. All future resizers read from this registry, not ad hoc magic numbers in Python.

#### Phase 0b — unified visual pool policy (lock before coding)

**Shipped 2026-07-21** alongside display-context registry:

- **Two families only:** `audio` and `visual` (images + video). Drop the product distinction between Illustrations, Photos, and Video — those become legacy intake paths, not operator mental models.
- **Registry for all visual uploads:** assign `ast_{ULID}` at intake; store `media_type`, `has_alpha`, `original_filename`, master/delivery paths in `data/assets/registry.json` (same registry as audio, discriminated by type).
- **Role from explicit tags (primary):** each visual asset carries a `role` tag and optional `brand_id`; container references validate allowed roles for pickers.
- **Picker filter contract:** each admin picker declares allowed `media_type`, `brand_id`, role tags, delivery-ready requirement, and optional facets (alpha, square); document in [PLATFORM-MODEL.md](PLATFORM-MODEL.md).
- **Upload tagging:** contextual uploads inherit role + brand from picker; bulk Visual pool uploads default to `role: unassigned` and install active `brand_id` — never block upload on role selection.
- **Migration rule:** dual-read legacy paths (`/media/img/original/…`, `/media/photo/original/…`, `/media/video/original/…`, `/media/special/…`); Publish/autofix registers existing files and assigns provisional role tags; retire folder split after backfill.

#### Phase 1 — format-aware delivery + sanity sizes

**Shipped 2026-07-21:** context sizes from `scripts/delivery-contexts.json`; opaque JPEG **card max 720px** + **thumb max 100px**; alpha sources emit PNG (no white flatten). Shell in-place special resize remains as a transitional first-paint aid.

Brand/logos: Visual pool assets with `brand-logo` (or contextual upload from brand editor); until Brand-assets fold, `media/special/` direct references remain a legacy workaround.

#### Upload role tagging (locked 2026-07-11)

| Upload path | Default `brand_id` | Default `role` |
|-------------|-------------------|----------------|
| Picker in context (release cover, brand slot, page picture) | Release's brand or base brand | Role implied by picker |
| Bulk drop into Visual pool | Install base brand | `unassigned` |
| AI wizard output | Release/brand from wizard | Target role + `origin: ai-generated` |

Operators may change role and brand after upload in Files → Visual (single or batch). Notifications nudge when assets remain `unassigned`; delivery is not blocked for bulk uploads unless a specific picker requires a role.

#### Phase 2 — multi-variant delivery + visual storage migration

**Shipped 2026-07-21 (delivery half):** variants under `media/visual/delivery/{asset_id}/` with registry `delivery.variants` manifest. Legacy `media/*/optimal` and `thumb` trees remain dual-read fallbacks. Originals stay in legacy intake buckets until Phase 3 Brand-assets fold relocates them under `media/visual/original/`.

Image variants for v0.8: `thumb`, `card`, `huge` (player lightbox and shell still backgrounds prefer `huge`, fall back to `card`). Video: `poster`, `standard-stream`.

Rules:

- generate only the variants contexts require for that asset **role** (track cover needs `thumb` + `card`; a page grid photo may skip unused variants later)
- store width/height/format in a small delivery manifest keyed by asset id (extends asset registry)
- resolver helpers in PHP choose variant URL for each render site; later: `srcset`/`sizes` for responsive markup

Transition: keep reading legacy `optimal/*.jpg` during migration; Publish regenerates variants; autofix/backfill pass queues missing variants.

#### Phase 3 — resolver + UI wiring

- extend asset registry helpers and `media-delivery-helpers.php` for visual `asset_id` + variant resolution
- [x] replace Files → Illustrations / Photos / Video with **Files → Visual** (operator UX 2026-07-15; thumbnail-first + type filters 2026-07-16)
- [x] Files → **Brand assets** label for legacy `media/special/` (2026-07-16)
- [x] Files → Brand assets Visual-like manager: thumbnail cards, type chips (image/video/audio), usage filters, shared drilldown; theme/config `reference_info` for In use / Orphans (2026-07-16) — storage remains `media/special/` until migration
- [x] Content pickers query Visual with brand filter chips (`get-themes.php` → All + brands) on Files → Visual and shared media pickers (2026-07-21)
- [x] Content pools gate on **required variants present** (`thumb`+`card` / `poster`+`standard-stream`); UI names missing variants; undelivered tiles disabled in pickers and non-draggable in gallery (2026-07-21)
- [x] Track-cover assign stores pool filename ref + embeds into the master; does **not** copy to `{audio_stem}.ext`. Build `get_cover()` prefers assigned registry cover; extract-to-pool only for legacy masters with no assignment (2026-07-21)

#### Track cover source of truth (2026-07-21, shared-cover update 2026-08-03)

1. Operator-assigned Visual pool file (`display.cover` / registry) — preferred
2. Legacy same-basename sidecar in `media/img/original/` (if still present)
3. Embedded art: if bytes match an existing Visual original (`content_sha256` / exact SHA-256 of intake or embedded blob), **link only** — do not extract a new `{stem}.ext`, and do not re-seed keywords/captured from the linking track.
4. Extract embedded art only when no assigned/sidecar/hash match exists. On that extract/register, one-time fill empty Visual `display.title` as `Track cover: {track title}`, empty `keywords` with role + artist, and empty `captured_at` from the audio date when available. Later builds never overwrite operator (or previously seeded) values.
5. Configured release cover fallback

Assigned pool files are not duplicated as stem-named sidecars. Many tracks may share one Visual `asset_id`. A migrate step collapses identical intake originals by content hash, re-points audio `display.cover`, and removes redundant files + delivery dirs. Files listing falls back to `Track cover: {linked track title}` when `display.title` is empty.

**Delete Visual (safety):** deleting a Visual detaches site/registry cover and living-cover links by default and **does not** strip embedded art from audio masters. Operators may opt in (delete modal checkbox) to also clear embedded still covers from linked masters. A later Publish/rebuild may re-extract embedded art into a new Visual; delivery thumb/card are built in the playlist stage (and a catch-up optimizeMedia pass) so Files does not show a blank tile.

#### Non-goals for v0.8.4

- merging **audio** into the Visual pool (families stay separate)
- arbitrary operator-chosen export formats (ZIP of masters remains separate)
- on-the-fly dynamic resizing CDN (all variants remain pre-generated publish artifacts for predictable hosting)
- new intake formats beyond current PNG/JPG/WebP/MOV/MP4 until master/delivery contract is stable

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

Current supported source audio formats for playlist generation and master intake:

- `FLAC`
- `MP3`
- `WAV` (converted or packaged into corrected masters where supported)

Known but currently unsupported source audio formats are surfaced as skipped during build validation, including:

- `AIFF`
- `M4A`
- `AAC`
- `OGG`
- `WMA`

Unsupported files should be replaced with FLAC/MP3/WAV or removed from the audio folder.

## Current pipeline model

The current pipeline is split into two main metadata paths:

- source audio reading for playlist generation
- FLAC-to-MP3 conversion with **tagless** delivery output (strip ID3/APEv2 after build)

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

- `playlist-scan`: read source audio, infer ordering, and update `data/validation/playlist-validation.json`
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

- Files -> Audio metadata saves already run `playlist-scan` automatically after a real change so `data/validation/playlist-validation.json` refreshes immediately.
- The save flow now preserves an existing embedded `tracknumber` or backfills it from the current playlist position when the master tag is blank.
- A true no-op metadata save is treated as a no-op and does not create a fresh build-required reason.
- Build-required state now also records concrete task units (`playlist-scan`, `audio-delivery`, `image-delivery`, `social-assets`, `manifest`) alongside the legacy `full` / `optimize` action so the operator inbox and save/upload feedback can speak in task terms even before the manual build controls are fully split by task.
- The Build tab now speaks in task-oriented operator language (`Run Publish Build` and `Refresh Image Files`) instead of the older vague `Full Build` / `Optimize Media` pairing, while still routing those buttons through the same current heavy-runner endpoints.
- `Refresh Image Files` now truly runs the image-delivery path only: it regenerates track cover JPEGs, photos, and illustration derivatives without re-encoding audio delivery files. The full publish build still runs the optimizer in full mode so audio delivery regeneration remains part of the full publish pipeline.
- Audio + visual image delivery skip-if-fresh via master **XXH3** (`assets[].delivery.source_xxh3`). Visual variants compare their expected contain dimensions against `delivery-contexts.json` (`max_edge` or `max_width` + `max_height`) so policy changes rebuild without a master change. Legacy audio `source_mtime` remains as a one-build migration fallback until XXH3 is recorded. Force with `BANDPROMO_FORCE_AUDIO_DELIVERY=1` / `BANDPROMO_FORCE_VISUAL_DELIVERY=1`. Requires Python `xxhash` (`pip install -r scripts/requirements.txt`).
- Publish log vocabulary: operator title + product variants (`card` / `thumb` / poster / stream); skip lines say “already up to date (master XXH3 match)”.
- Share/OG crops write to `media/share/{stem}_{facebook|twitter}.jpg` (not beside Visual originals/masters). They are not Files → Visual rows.
- Theme-cover changes and image-only uploads now auto-run the image-delivery path in the background when that cheap refresh succeeds, so those safe cases no longer have to leave a manual image-refresh task behind just to regenerate derived JPEG assets.
- Real metadata changes still rely on the older coarse manual build controls, so task-level follow-up remains only partially complete until those controls are split beyond `full` / `optimize`.

### Action matrix

This matrix defines the preferred future behavior.

| Admin action | Task units | Default behavior | Operator message |
| --- | --- | --- | --- |
| Upload audio source | `playlist-scan`, `audio-delivery`; `image-delivery` when embedded covers are extracted | Run `playlist-scan` automatically in validation-only mode; run `audio-delivery` automatically for uploaded files; register extracted covers as Visual track-covers and run `image-delivery` | Show pending delivery generation only if derivatives are not ready yet; surface failures in Notifications |
| Upload video source | `video-delivery` | Spawn async background delivery for all uploaded videos (MP4 copy, MOV/WEBM transcode, poster generation) | Show running/completed/failed state in Notifications; hide from gallery pool until delivery is ready |
| Upload photo | `image-delivery` | Run automatically if cheap; otherwise queue quietly and finish in background | Usually no explicit notification |
| Upload illustration | `image-delivery` | Run automatically if cheap; otherwise queue quietly and finish in background | Usually no explicit notification |
| Upload theme/share/logo/background asset | `image-delivery` and/or `social-assets` depending on usage | Run automatically | No generic build warning; surface only direct file/validation errors |
| Edit site basics text | `manifest` when manifest-facing fields changed | Run automatically | No build warning |
| Edit social/share text fields | `social-assets`, `manifest` when affected | Run automatically | No build warning unless a referenced asset is missing |
| Change theme media paths | `social-assets` and/or `image-delivery` when relevant references changed | Run automatically when only references change; queue only if a heavy derivative task is genuinely needed | Prefer a targeted status message over a generic build badge |
| Reorder playlist | none for delivery generation; save order only | Save immediately | No build warning |
| Edit gallery entries or order | none for delivery generation in the common case | Save immediately | No build warning |
| Edit bio/pages | none | Save immediately | No build warning |
| Edit metadata in Files -> Audio | `playlist-scan` only when needed; `audio-delivery` only if the optimal MP3 is missing or cover/art bytes changed | Keep **last-good** player playlist payload (never wipe to empty); keep delivery MP3 **tagless** when present; quiet republish playlists that include the track; clear “Prepare your songs” when tag-only sync succeeds | `/play` stays up after a typo fix; explain only when delivery must regenerate |

### Naming guidance for admin UI

Files → **Brand assets** manages the selected Brand document's `library_asset_ids`, spanning registered Visual and SFX assets. Upload adds directly to that library; **Add existing** is a multi-select picker from the global Visual/SFX warehouses and hides members already in that Brand library; removing membership never deletes the global asset. In **Content → Branding**, **Shell media** holds assignment slots only and strict pickers show compatible assets from that Brand library. A pick writes the public Visual `card` / stream or SFX optimal URL into `assets[]` (with `asset_ids[]`); living background picks require the video `standard-stream` URL (still posters are not stored). Loading a brand document resolves those delivery URLs again when ids are set. Saving the base brand syncs resolved delivery URLs into `web-config.json` (`media.*`, `release.theme.*`, share image keys). Settings → Theme has been retired; Sharing keeps SEO/social text and points poster edits to Branding.

### Nondestructive naming policy

bandPromo should stop forcing operators to work directly with raw source filenames as the main visible identity for tracks and media.

**Locked rule (v0.8):** see [PLATFORM-MODEL.md](PLATFORM-MODEL.md) for the full contract.

- on-disk master and delivery files use stable IDs: `ast_{ULID}` (for example `ast_01HY8K3M2P9XQ4R5S6T7V8W.flac`)
- `data/assets` registry holds display fields, tags, slugs, release membership, and `original_filename`
- operators see titles and metadata in pools; they do not manage path names
- public URLs use per-release track slugs, not filenames
- human-readable names for distributor export ZIPs are generated at export time, not used as storage paths
- the original upload name remains recorded in the registry for trust and recovery

Playlist reorder must not rename files or rewrite embedded track numbers. Release membership is unordered; an embedded track number remains independent source metadata.

Files → Audio **orphan** means the asset is not on any release document (not “unprocessed”). After re-upload/re-register under a new `ast_*` id, Content autofix sync-releases rebinds stale release tracks and playlist `entries` onto the live registry row by artist/title identity (including common suffixes such as `FINAL` / `NEWER WIP`). Leftover master files for deleted ids may remain on disk until cleaned separately.

Registry `display{}` is what Files → Audio titles and the metadata health badges (C/A/T/R/D/L) read. Upload and Content autofix fill it from master tags; full Publish preflight also fills **incomplete** rows only (does not overwrite complete operator-saved display). A media rebuild alone does not invent titles when `display` is empty and tags were never copied into the registry.

When re-upload leaves rich tags on unregistered leftover masters, Publish/autofix can copy empty description/lyrics/cover onto the matching live asset (and rewrite those tags onto the live master).

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

Build cover lookup (`scripts/makePlaylists.py` `get_cover()`):

1. operator-assigned Visual cover from the asset registry (`display.cover` as `ast_*`)
2. extract embedded audio artwork to `media/visual/original/embedded-{hash}.*`, register a Visual master, and store the asset id (hash-match an existing Visual first; never write `img/original/{audioStem}.*`)
3. configured release poster Visual asset from `web-config.json` (`media.cover`) when it already resolves to `ast_*`

`/play` prefers playlist `cover_url` (visual delivery `card`/`thumb`). Playlist payload `cover` / `living_cover` are visual asset ids; `animated_cover` is Visual `standard-stream` delivery.

Assigning a pool image to a track embeds it into the audio master and stores the **visual asset id** as the cover reference. It does not copy the pool file to `{audio_stem}.ext`.

Embedded artwork is currently read from:

- FLAC picture blocks
- MP3 ID3 `APIC` frames

When the configured release poster is used as fallback, tracks point at that Visual asset. The build does **not** mint `configured_release_cover.*` into `media/img/original/`.

### Cover art roles and reference index

Visual stills (and leftover `media/img/original/` files until T4) can serve different product roles:

- `track-cover`: assigned to one or more audio assets via `display.cover` = visual `ast_*`
- `release-fallback`: Base brand poster / `media.cover` Visual asset (no `configured_release_cover.*` mint)
- `illustration`: general artwork used by gallery items or media pickers

Origins are tracked separately from roles:

- `user-upload`
- `build-extracted`
- `build-configured`
- `build-sidecar-copy`
- `bundled-placeholder`

The runtime manifest in `data/media-library-state.json` records advisory `assets` metadata, but live references are recomputed from:

- track covers and living covers (asset registry display)
- published playlist cover payloads (legacy/supplemental)
- gallery containers
- brand shell slots (`asset_ids` on brand documents: logo, poster, still/living backgrounds, welcome/logged-in SFX)
- install theme / share-image config (`web-config.json`)
- page editor picture blocks (`data/pages/*.json`) — Visual `asset_id` and `/media/visual/delivery/{id}/…` as well as legacy `/media/img|photo/…` paths; page posters too
- release posters and press photos (`data/releases/*.json`)
- playlist posters (`data/playlists/*.json`)

Visual usage identity is the registry `ast_*` id. Stored paths, delivery URLs, original names, and master names are resolvers that map onto that id. Titles, operator titles, and filename stems are never compared. Unregistered leftovers with no id do not match a registered asset.

`biblioteca/list-media.php` returns this as `cover_info` / `reference_info` for Files → Visual, including role, origin, references, and an `orphan` flag for unreferenced non-demo files. The **In use / Unused** chip follows that live reference index (track cover, gallery, page picture/poster, release/playlist poster, press photo, or brand shell slot). Brand **library** membership alone does not count as used. Files → Visual **Catalogue** names every campaign that uses the file: owned gallery, track cover / living cover, release or playlist poster, press photo, page picture, **or** the Brand visual shell those campaigns play (logo, poster, still/living). Empty Brand slots inherit the install Base brand (login / player fallback), so a site-wide background lists every release that still inherits it. Shared files list each matching release on its own line. Brand-library members with no campaign use list that Brand rather than Orphan. Catalogue must not infer the campaign from Brand ownership on the asset. Unused-but-filed assets may still show a release from `assets[].release_id`. The invisible `primary` bucket is never catalogue membership.

Files -> Illustrations now surfaces that metadata in the admin UI with role/origin badges, compact list-header filter dropdowns (`All`, `Track covers`, `Orphans`, `Build-generated`, plus `User files` / `Include demo`), and delete-preview hints for theme references and regenerable build artifacts. Detailed per-row `Used by:` reference text stays out of the normal operator list view; badges and filters are the primary signal. After playlist regeneration, stale `configured_release_cover.*` variants are removed when they are no longer the active fallback copy.

Files -> Photos and Files -> Video now use the same shared reference index through `biblioteca/media-reference-helpers.php`. Each row exposes `reference_info` with gallery, theme, page, release/playlist poster, and track-visual references, an `orphan` flag, and the same list-header filter pattern (`All`, `In use`, `Orphans`, plus demo-source filtering). Gallery matching resolves stored `asset_id` or `/media/visual/delivery/{asset_id}/…` (and leftover `/media/{photo|img|video}/…` paths) to the same Visual id as the Files row.

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

## Delivery MP3 tags (locked: tagless)

Generated MP3 delivery files are **tagless**: after copy or transcode, the optimizer strips all ID3 and APEv2 blocks (including APIC cover art).

Listener-facing identity (title, artist, album, lyrics, covers) comes from the asset registry and published playlist JSON. Media Session and future Chromecast metadata use those sources — not embedded tags. Masters remain fully tagged for editing and Portable Campaign Files.

See [DELIVERY-ARCHITECTURE.md](DELIVERY-ARCHITECTURE.md) § Tagless audio delivery.

## Current validation output

Playlist generation writes `data/validation/playlist-validation.json` with:

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

The shipped operator-facing labels in the admin inbox and build summary are:

- `Blocked`: the source file or referenced asset is unusable and bandPromo cannot produce the required output
- `Fix first`: the site can stay in admin use, but the issue should be corrected before presenting the release as finished
- `Nice to have`: the site can still be updated, but the package is weaker or less complete than intended
- `Can be fixed automatically`: bandPromo can safely normalize or embed the missing information once the required source input is available

These map to the internal severity keys:

- `cannot-build` -> `Blocked`
- `fix-before-publish` -> `Fix first`
- `recommended-fix` -> `Nice to have`
- `can-be-repaired-automatically` -> `Can be fixed automatically`

The admin summary should lead with the fix-oriented label and plain-language action, with raw tag terminology treated as secondary detail.

Examples:

- `Fix before publish: add a track title for Track01.flac`
- `Recommended fix: add lyrics for Midnight City if lyric display is expected`
- `Blocked: replace unsupported source file demo.aiff with FLAC or MP3`
- `Can be repaired automatically: embed the approved cover into the corrected master`

### Current warning-code mapping guidance

The current `playlist-validation.json` warning codes should be interpreted in operator language like this until richer validation objects exist:

| Current warning code | Preferred operator message | Default operator label | Notes |
| --- | --- | --- | --- |
| `missing_title_tag` | Add a track title | `Fix before publish` | If title can be inferred safely, bandPromo may prefill a suggestion rather than block immediately |
| `missing_artist_tag` | Add the artist name | `Fix before publish` | May downgrade when an approved install/release default is intentionally used |
| `missing_album_tag` | Add the release/album name | `Recommended fix` | Should not block simple single-release playback on its own |
| `missing_track_number` | Add it only when useful for external metadata handoff | `Recommended fix` | Release membership and playlist position do not depend on embedded track numbers |
| `missing_lyrics` | Add lyrics if lyric display is part of the release | `Recommended fix` | Missing lyrics should not block audio publication by themselves |
| `missing_cover_art` | Add cover art or confirm the approved fallback cover | `Fix before publish` when no approved fallback exists; otherwise `Recommended fix` | Distinguish missing track art from a valid release-cover fallback |

If multiple issues affect one track, the admin summary should show the highest-severity label first and list the remaining recommended fixes underneath it.

## Current limitations

- The admin UI keeps unresolved publish/build follow-up and validation issues visible in a persistent **What needs your attention** inbox modal (header bell and dashboard summary). The completed-install dashboard shows a short status line rather than the full task list inline.
- Metadata repair now covers the first audio-master editor pass, including common text fields, lyrics, cover selection, release date, and operator-facing title/version handling, plus tag-bullet quick-edit for short fields from Files -> Audio: artist, title, version, release/album name, track, release date, genre, BPM, and key. Larger fields such as description and lyrics stay in the full editor. Broader packaging workflows are still incomplete.
- Some MP3 files tagged mainly through APEv2 may still behave inconsistently compared with FLAC or clean ID3v2-tagged files.
- Real audio metadata changes still flow through the older coarse build-required state, so the operator messaging is better for no-op saves than for task-specific follow-up after actual edits.
- The current `optimal` label is too vague; delivery targets should be defined by actual usage context rather than implied quality alone.

## Recommended direction

The next practical improvements should be:

- break the coarse build-required model into concrete task states so real metadata edits do not look like generic full-build work when only lighter follow-up is pending
- continue tightening task-specific follow-up after real metadata edits so operators see lighter refresh work instead of generic full-build messaging
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