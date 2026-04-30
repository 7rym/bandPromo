# bandPromo Media Handling

This document describes how bandPromo should handle source media, canonical masters, and publish-ready delivery files.

It replaces the older narrow metadata-only framing. Metadata is still a core concern, but it now sits inside a broader media-handling policy that covers intake scenarios, packaging, delivery targets, and what the platform can realistically improve for non-technical operators.

Current state: `v0.7 build 209`

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

### Delivery tier

The delivery tier is for publishing, not archival purity.

Delivery assets should be optimized for actual player and UI needs rather than simply mirroring the largest source asset.

Examples:

- image sizes based on actual cover/card/lightbox dimensions
- audio delivery profiles based on practical listening contexts
- JPEG/WebP for non-transparent artwork where that is the real best-fit delivery format

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

## Current limitations

- The admin UI does not yet provide metadata repair or master-building tools.
- Metadata warnings are visible in the admin build log, but they should become more prominent and actionable outside the raw log view.
- `WAV` is not yet a supported source format, even though it is a desired operator path.
- Some MP3 files tagged mainly through APEv2 may still behave inconsistently compared with FLAC or clean ID3v2-tagged files.
- The current `optimal` label is too vague; delivery targets should be defined by actual usage context rather than implied quality alone.

## Recommended direction

The next practical improvements should be:

- make media validation warnings more prominent in the admin UI
- classify issues as hard blockers, publish blockers, warnings, or autofixable issues
- add tools for editing core tags and packaging fields in admin
- define a proper WAV intake path that can produce a corrected FLAC master
- preserve originals while generating corrected masters and delivery derivatives separately
- redefine `optimal` into explicit delivery targets for player, mobile, cover, and lightbox contexts
- implement the intake policy matrix above as the working contract for build, admin repair tools, and future exported masters