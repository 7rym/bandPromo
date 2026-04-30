# bandPromo Metadata Notes

This document describes the metadata contract the current build pipeline actually uses.

It is intentionally practical. It does not describe every audio tagging standard in general. It describes what `bandPromo` reads, what it writes, and where format mismatches can still hurt operators.

Current state: `v0.7`

## Why this matters

The platform currently depends heavily on audio metadata.

If a source file arrives as something like `take4.wav` with no usable tags, the current system does not have enough information to build a trustworthy playlist and player experience automatically.

Recent work added validation warnings during playlist generation and displays those warnings in the admin build log after a completed build, but the metadata contract is still strict.

## Current pipeline model

The current pipeline is split into two main metadata paths:

- source audio reading for playlist generation
- FLAC to MP3 conversion with ID3 tag writing for delivery files

That means the platform is not using one single universal metadata format internally.

## Source formats currently supported

Current supported source audio formats for playlist generation:

- `FLAC`
- `MP3`

Known but currently unsupported source audio formats are now surfaced as skipped during build validation, including formats like:

- `WAV`
- `AIFF`
- `M4A`
- `AAC`
- `OGG`
- `WMA`

This is an operator-safety improvement, not full support.

## Tags currently read by playlist generation

The playlist generator is implemented in [scripts/makePlaylists.py](scripts/makePlaylists.py).

### Core playback fields

For source files, the current reader looks for:

- `TITLE` or ID3 `TIT2` for track title
- `ARTIST` or ID3 `TPE1` for artist
- `ALBUM` or ID3 `TALB` for album
- `TRACKNUMBER` or ID3 `TRCK` for track ordering

### Lyrics

The current reader looks for lyrics in this order:

- `LYRICS`
- `UNSYNCEDLYRICS`
- ID3 `USLT` frames
- a `.txt` file with the same basename as the audio file

Important detail:

- `UNSYNCEDLYRICS` is commonly seen in FLAC/Vorbis-style tags and sometimes in APEv2-tagged MP3s
- `USLT` is the current ID3 lyrics frame used on generated MP3 output

### Description / comment

The player description currently reads from:

- `DESCRIPTION`
- fallback: `COMMENT`

### Cover art

The current cover lookup path is:

1. embedded audio artwork
2. same-basename image file in `media/img/original/`
3. configured release cover from `web-config.json` (`media.cover`)

Embedded artwork is currently read from:

- FLAC picture blocks
- MP3 ID3 `APIC` frames

When the configured release cover is used as fallback, the build copies it into `media/img/original/` as a normal generated cover asset so the rest of the pipeline can treat it like any other track cover.

## Tags currently read from FLAC during optimization

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

This is the richest metadata path in the current codebase.

## Tags currently written to generated MP3 files

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

## Where the format struggle comes from

This is the practical mismatch that caused pain before:

- FLAC source files use Vorbis-style field names
- generated MP3 delivery files use ID3v2 frame names
- some third-party MP3 files may carry APEv2 data instead of the ID3 layout the pipeline prefers

Current reality:

- FLAC and Vorbis-style source tagging is the best-supported input path
- ID3v2 is the intended output tagging path for generated MP3 files
- APEv2 is only partially tolerated during read-time, mainly around generic lyric access and debugging

In other words: APEv2 is not a reliable primary contract for the platform right now.

## Example from bundled demo content

Running the current inspector against the bundled sample FLAC showed fields like:

- `album`
- `albumartist`
- `artist`
- `comment`
- `date`
- `genre`
- `mixartist`
- `title`
- `tracknumber`
- `unsyncedlyrics`
- embedded FLAC picture data

That matches the current “best-supported FLAC source” story much more than an “ID3 everywhere” story.

## Current operator guidance

For the most reliable current workflow, source audio should include at least:

- title
- artist
- album
- track number

Strongly recommended:

- lyrics
- comment or description
- embedded artwork

## Current validation output

Playlist generation writes `play/playlist-validation.json` with:

- supported source extensions
- skipped unsupported source files
- total track counts
- per-track warning codes such as `missing_title_tag`, `missing_artist_tag`, `missing_album_tag`, `missing_track_number`, `missing_lyrics`, and `missing_cover_art`
- cover source details (`embedded`, `sidecar`, `configured`, or `missing`)

The admin build log reads this file through `biblioteca/get-build-log.php` and appends a human-readable metadata validation summary when a build is no longer running.

## Current limitations

- The admin UI still does not provide metadata editing tools yet.
- Metadata warnings are visible in the admin build log, but they should become more prominent and actionable outside the raw log view.
- `WAV` is not yet a supported source format, even though it is a desired future operator path.
- Some MP3 files tagged mainly through APEv2 may still behave inconsistently compared with FLAC or clean ID3v2-tagged files.

## Recommended direction

The next practical improvements should be:

- make metadata validation warnings more prominent in the admin UI
- add file-manager tools for editing core tags
- define a proper `WAV` intake path that normalizes to the platform's supported metadata model
- reduce dependence on one perfect tagging style by validating and repairing metadata during build