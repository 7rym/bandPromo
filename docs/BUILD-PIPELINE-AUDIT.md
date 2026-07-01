# Build pipeline audit (v0.8)

Status: **policy draft** — 2026-07-01. Implementation tracked in [TODO.md](TODO.md) after this document is agreed.

Companion policy: [PLATFORM-MODEL.md](PLATFORM-MODEL.md), [MEDIA-HANDLING.md](MEDIA-HANDLING.md).

## Why this audit exists

The admin **Publish** flow mixes three different concerns:

1. **Environment readiness** (tools, templates, theme pack)
2. **Silent catalog repair** (`content-autofix` mutating releases/playlists/registry)
3. **Delivery + player artifacts** (Python pipeline)

The Python pipeline is still **playlist-first**: `makePlaylists.py` runs before `optimizeMedia.py`, and audio deliverables are scoped to `play/playlist.json`, not the full Files pool. That contradicts the platform model (pool → master → deliverable → containers).

Operators see uploads in **Files** but builds only process **playlist membership** — the system feels broken even when individual scripts work.

## Terminology (build stages)

| Term | Meaning |
|------|---------|
| **Original** | Operator upload in `media/*/original/` |
| **Master** | Canonical packaged file (`ast_{ULID}` + registry) |
| **Deliverable** | Playback/display derivative (`media/*/optimal/`, etc.) |
| **Container** | Operator document: release, playlist, gallery, page, theme |
| **Artifact** | Generated runtime file consumed by the site (`play/playlist.json`, `site.webmanifest`, share JPGs) |
| **Compose** | See below — not delivery, not catalog repair |

### What “Compose” means

**Compose** is **first-run site layout bootstrap**, implemented today as `scripts/setupCompose.py` (build step 6/6).

It runs **once** (marker file: `data/initial-site-compose.json`). If the marker exists, it skips.

When it does run, it **guesses** an initial layout from whatever is on disk:

- Writes `data/playlist-order.json` from **all visible audio originals** (folder scan, not Content editor truth)
- Regenerates `play/playlist.json` via `makePlaylists.py` full scan
- Writes `data/gallery.json` from **all** photos/videos in original folders
- Patches `web-config.json` player modules/tab order (enable gallery + pages)

Compose is **not**:

- Transcoding or optimizing media (that is **delivery**)
- Creating masters or registry entries (that is **catalog**)
- Honouring release membership or playlist documents the operator configured (it overwrites with folder scans on first run)

Compose **is**:

- A convenience “wire up a new install” step from the pre–Content-editor era
- Something that should eventually move to **setup** or an explicit **“Initial layout wizard”**, not run on every publish

**Target policy:** Compose becomes an optional, idempotent **layout seed** stage — run after containers and deliverables exist, only when the operator requests it or on first setup — never silently on routine publish.

## Target build order (agreed direction)

Publish should run **staged**, **logged**, and **skippable** stages. Default full publish runs all required stages in this order:

### Stage 0 — Preflight (tools & runtime)

**Purpose:** Fail fast before touching operator content.

- Python deps, `ffmpeg`, writable `log/`
- Required runtime templates seeded if missing (`web-config.json` structure, page registry shells)
- Starter/demo theme **package** present (current `bandpromo_ensure_default_theme_package`)
- **No** `content-autofix`
- **No** media processing

### Stage 1 — Site shell (theme, config, social, PWA prerequisites)

**Purpose:** Validate and apply non-media site identity before media pipelines.

- Theme document + theme asset references resolve (logo, cover, backgrounds, welcome audio paths)
- `web-config.json` / scoped config structure valid
- Social/share settings consistent (share image path, OG fields)
- PWA prerequisites: icons present, manifest **inputs** valid (actual `site.webmanifest` generation may stay in Stage 4)

**Does not** transcode uploads. May **read** deliverable paths if they already exist.

### Stage 2 — Catalog (masters & registry)

**Purpose:** Every supported original upload is registered and has a master.

- Register uncatalogued audio/visual originals in `data/assets/registry.json`
- Materialize missing audio masters (`original → master`)
- Canonicalize master filenames (`ast_{ULID}`)
- Update registry display metadata from masters where safe

**Does not:**

- Change release membership or playlist order (operator Content editors own that)
- Build deliverables
- Run silently unless operator opted into **Repair catalog** (separate action)

### Stage 3 — Deliverables (from masters/registry)

**Purpose:** Generate playback/display files from **catalog scope**, not playlist scope.

- **Audio:** MP3 (or configured delivery) for **every registered audio asset** that has a master
- **Visual:** illustrations, photos, video — **every registered visual asset** with a master/original
- **Prune:** remove deliverable files **only when the asset is deleted** from the registry (or original+master removed). Removing a track from a playlist or release **must not** delete deliverables.

Scope rules (locked):

- **In scope:** all assets in `data/assets/registry.json` (per kind), regardless of release/playlist membership
- **Out of scope:** using `play/playlist.json` or `data/playlists/main.json` as the primary filter for whether delivery runs

Playlist membership must **not** be the driver for whether an uploaded song gets a deliverable.

### Stage 4 — Artifacts (containers → runtime files)

**Purpose:** Export operator container documents to player/site runtime JSON.

- `play/playlist.json` from **playlist documents** (`data/playlists/*.json`), not folder scan
- `play/playlist-validation.json` (validation report; playlist-scoped is OK here)
- Social share renditions (`makeSocial.py`) from config + deliverable sources
- `site.webmanifest` (`makePWA.py`)
- Release/catalog JSON is already in `data/releases/` — build validates, does not rewrite membership

### Stage 5 — Initial layout seed (optional; not “Compose” on publish)

**Purpose:** One-time or **recovery-only** wiring when container documents are missing or operator requests disaster recovery.

- **Setup:** run once near end of `setup.php` when playlist/gallery documents are empty (replaces publish step 6)
- **Recovery:** explicit System action **Recover layout from disk** — same script, strong confirmation, audit log
- **Never** on routine publish

Must not override non-empty playlist, release, or gallery documents unless recovery mode is explicitly confirmed.

## Current implementation map

### Admin buttons today

| Button | Entry | Python | Notes |
|--------|-------|--------|-------|
| Run Publish Build | `biblioteca/build.php` `mode=full` | `scripts/build.py` (5 steps) | Config-structure preflight only; **no** catalog repair |
| Refresh Image Files | `biblioteca/build.php` `mode=optimize` | `scripts/optimizeMedia.py` only | `BANDPROMO_OPTIMIZE_MODE=image-only` |

There is **no** stage picker. `build-required` tasks (`playlist-scan`, `audio-delivery`, …) are notification labels only.

### PHP before Python (full publish)

| Step | Code | Problem |
|------|------|---------|
| Lock + log | `build.php` | OK |
| Preflight | `publish-preflight-helpers.php` → config repair only | Site settings structure check; catalog repair is explicit **Repair catalog** |
| Theme pack | `bandpromo_ensure_default_theme_package` | OK for empty installs; should be Stage 0/1 |
| Launch | `build-runner.php` → `build.py` | OK |

### Python `build.py` today (fixed chain)

| Order | Script | Scope | Problem vs target |
|-------|--------|-------|-------------------|
| Preflight | inline | tools + “any audio original exists” | Does not verify masters for all uploads |
| **1** | `makePlaylists.py` | playlist document → `play/playlist.json` | **Before delivery**; defines audio scope for step 2 |
| **2** | `optimizeMedia.py` full | reads `play/playlist.json` for audio + covers | **Playlist-scoped** audio; deletes non-playlist MP3s |
| **3** | `optimizeVideo.py` | video pool / jobs | OK as delivery sub-stage |
| **4** | `makeSocial.py` | config | Should be Stage 4 after media deliverables |
| **5** | `makePWA.py` | manifest | Should be Stage 1/4 before or after media per inputs |
| *(setup only)* | `setupCompose.py` via `run-layout-seed.php` | folder scan bootstrap | **Not** in publish chain; setup + disaster recovery |

### Side channels (not the main publish button)

| Trigger | What runs |
|---------|-----------|
| Audio upload | validation-only `makePlaylists.py`, `audioSourceDelivery.py` per file |
| Image upload | `optimizeMedia.py` image-only |
| Video upload | background `optimizeVideo.py` |
| Playlist save | master materialization (PHP), not full build |
| `content-autofix` API (Repair catalog) | explicit catalog repair pipeline |

These **helpers** assume playlist-first truth and fight a corrected build order.

## Gap summary (#1 findings)

1. **Wrong order:** playlist artifacts before media deliverables.
2. **Wrong audio scope:** deliverables driven by playlist, not registry/pool.
3. **Autofix inside publish:** silent release/playlist rewrites (`bandpromo_release_sync_primary_audio_assets`, etc.).
4. **No stage isolation:** cannot run catalog-only or deliverables-only.
5. **Compose mislabeled:** bundled as publish step 6 though it is first-run layout seed.
6. **Validation UX:** “N tracks checked” reads as “build processed N uploads” — it is playlist validation only.

## Recommended refactor sequence

Work in policy order; do not add more helpers until Stage 0–3 exist.

### Phase A — Stop the bleeding (small, safe)

- [x] Remove `content-autofix` from publish preflight; expose as explicit **Repair catalog** action with dry-run preview.
- [x] Document current behaviour in admin Publish help (playlist vs pool scope).
- [x] Fix operator messaging: validation block titled **Playlist validation**, not “build complete”. *(Superseded: Publish tab now uses site-wide **Publish status**; playlist metadata stays in notifications/Files.)*

### Phase B — Stage runner skeleton

- [x] Replace monolithic `build.py` with a stage manifest (`scripts/build-stages.json` + `biblioteca/build-stages.php`): id, label, script, `requires_ffmpeg`, `skippable`.
- [x] Log each stage start/end + exit code in `log/build.log` (`STAGE_START:` / `STAGE_END:` lines).
- [x] `build.php` accepts `profile` or `stages[]` presets: `full`, `deliverables-only`, `artifacts-only` (written to `log/build.meta.json`).

### Phase C — Catalog stage (master-first)

- [x] New `scripts/buildCatalog.py` + `biblioteca/build-catalog-helpers.php`: register + materialize + canonicalize for **all** catalogued originals (no playlist/release rewrites).
- [x] Upload path calls catalog finalize after audio master preparation (`bandpromo_build_catalog_finalize_audio_upload`).

### Phase D — Deliverables stage (registry-scoped)

- [ ] Refactor `optimizeMedia.py` / `audioSourceDelivery.py` to take asset IDs or registry query, not `play/playlist.json`.
- [ ] Playlist JSON becomes input only for **cover linkage**, not “which tracks exist”.

### Phase E — Artifacts stage

- [ ] `makePlaylists.py` runs **after** deliverables; only exports playlist documents.
- [ ] Move `makeSocial.py` / `makePWA.py` to Stage 1/4 per dependency audit (theme deliverables ready before share crops).

### Phase F — Compose

- [x] Remove `setupCompose.py` from default publish profile.
- [x] Run from setup completion (`run-layout-seed.php`) or explicit disaster recovery (`force: true`).

## Open questions (operator decisions)

1. **Deliverable scope:** ~~all registry audio assets, or only those referenced by a release/playlist?~~ **Locked (2026-07-01):** deliverables for **every registered asset**, independent of release or playlist membership.
2. **Prune policy:** ~~when a track is removed from playlist, delete its MP3 deliverable or keep until registry delete?~~ **Locked (2026-07-01):** prune deliverables **only when the asset is deleted** from the catalog/registry — not when removed from a playlist or release.
3. **Compose:** ~~delete auto-compose entirely vs keep as setup-only wizard?~~ **Locked (2026-07-01):** **setup-only** plus an explicit **disaster recovery** path (operator-confirmed, documented as destructive/rebuild-from-disk). **Not** part of routine publish. Rename in docs/UI away from “compose” toward **initial layout seed** or **recover site layout**.

### Compose — scope clarification (locked narrative)

Compose (`setupCompose.py`) is **not** demo-content installation. Demo/starter content comes from the **starter theme package**, tracked **templates**, and setup seeding (`setup.php`, template bootstrap).

Compose only:

- Guesses an initial **playlist order** from audio originals on disk
- Regenerates `play/playlist.json` once via folder-oriented scan
- Builds a flat `data/gallery.json` from photo/video originals on disk
- Enables default player modules/tab order in `web-config.json`

That is useful when:

- **First setup** — operator has files but empty or missing container documents
- **Total recovery** — operator explicitly chooses “rebuild layout from files on disk” after catastrophic document loss (with warning that Content editor truth will be overwritten)

It is **not** useful when:

- The site already has playlist, release, and gallery documents the operator manages in Content
- Routine publish after uploads/edits
- Substituting for catalog repair or deliverable generation

**Rename target:** retire “Compose” as a publish step label; call it **Initial layout seed** (setup) or **Recover layout from disk** (recovery).

## Success criteria

- Uploading 20 audio files and publishing processes **catalog + deliverables** for the agreed scope — not only the six tracks on `main` playlist.
- Publish log shows **stage names** in target order; each stage can be skipped in dev/CLI.
- No release/playlist membership changes during publish unless operator ran **Repair catalog**.
- Compose never runs on routine publish for established sites.
