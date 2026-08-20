# Build pipeline audit (v0.8)

Status: **implemented** — stages/profiles shipped; master-tier media contract complete (see [MASTER-TIER-AUDIT.md](MASTER-TIER-AUDIT.md)). Opening narrative below retains the original problem statement; current stage order is in “Python `build.py` today”.

Companion policy: [PLATFORM-MODEL.md](PLATFORM-MODEL.md), [MEDIA-HANDLING.md](MEDIA-HANDLING.md).

## Why this audit exists

The admin **Publish** flow mixes three different concerns:

1. **Environment readiness** (tools, templates, theme pack)
2. **Silent catalogue repair** (`content-autofix` mutating releases/playlists/registry)
3. **Delivery + player artifacts** (Python pipeline)

Historically the Python pipeline was **playlist-first** (`makePlaylists.py` before `optimizeMedia.py`), which contradicted the platform model (pool → master → deliverable → containers). That order is fixed: deliverables run before playlist/social/PWA artifacts (see stage table below). Successful rebuilds end with a scoped summary (media / playlists / share images / manifest) plus elapsed time.

## Terminology (build stages)

| Term | Meaning |
|------|---------|
| **Original** | Operator upload in `media/*/original/` |
| **Master** | Canonical packaged file (`ast_{ULID}` + registry) |
| **Deliverable** | Playback/display derivative (`media/*/optimal/`, etc.) |
| **Container** | Operator document: release, playlist, gallery, page, theme |
| **Artifact** | Generated runtime file consumed by the site (`site.webmanifest`, share JPGs) |
| **Initial site seed** | See below — not delivery, not catalogue repair |

### What “initial site seed” means (formerly “compose”)

**Initial site seed** is **first-run layout bootstrap**, implemented as `scripts/initialSiteSeed.py` and invoked from setup via `biblioteca/run-layout-seed.php`.

It runs **once** (marker file: `data/initial-site-seed.json`; legacy `data/initial-site-compose.json` still counts as completed). If the marker exists, it skips.

When it does run, it **only fills empty container documents** from disk:

- Seeds `data/playlists/{active-id}.json` when that playlist document has no entries
- Seeds `data/galleries/bandpromo-demo.json` when the demo gallery document has no entries
- Patches `web-config.json` player modules/tab order (enable gallery + pages)

It does **not** write legacy playlist artifacts like `play/playlist.json`, `data/playlist-order.json`, or seed `data/gallery.json` (galleries use `data/galleries/` containers).

Initial site seed is **not**:

- Transcoding or optimizing media (that is **delivery**)
- Creating masters or registry entries (that is **catalogue**)
- Honouring release membership or playlist documents the operator already configured (non-empty containers are left alone)

Initial site seed **is**:

- A convenience “wire up a new install” step from the pre–Content-editor era
- Something that should eventually move to **setup** or an explicit **“Initial layout wizard”**, not run on every publish

**Target policy:** Initial site seed is an idempotent **setup/recovery** step — run after containers and deliverables exist, only on first setup or explicit disaster recovery — never on routine publish.

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

### Stage 2 — Catalogue (masters & registry)

**Purpose:** Every supported original upload is registered and has a master.

- Register uncatalogued audio/visual originals in `data/assets/registry.json`
- Materialize missing audio masters (`original → master`)
- Canonicalize master filenames (`ast_{ULID}`)
- Update registry display metadata from masters where safe

**Does not:**

- Change release membership or playlist order (operator Content editors own that)
- Build deliverables
- Run silently unless operator opted into **Repair catalogue** (separate action)

### Stage 3 — Deliverables (from masters/registry)

**Purpose:** Generate playback/display files from **catalogue scope**, not playlist scope.

- **Audio:** MP3 (or configured delivery) for **every registered audio asset** that has a master
- **Visual:** illustrations, photos, video — **every registered visual asset** with a master/original
- **Prune:** remove deliverable files **only when the asset is deleted** from the registry (or original+master removed). Removing a track from a playlist or release **must not** delete deliverables.

Scope rules (locked):

- **In scope:** all assets in `data/assets/registry.json` (per kind), regardless of release/playlist membership
- **Out of scope:** using legacy playlist artifacts or `data/playlists/*.json` as the primary filter for whether delivery runs

Playlist membership must **not** be the driver for whether an uploaded song gets a deliverable.

### Stage 4 — Artifacts (containers → runtime files)

**Purpose:** Export operator container documents to player/site runtime JSON.

- Playlist validation from **playlist documents** (`data/playlists/*.json`), not folder scan
- `data/validation/playlist-validation.json` (validation report; playlist-scoped is OK here)
- Social share renditions (`makeSocial.py`) from config + deliverable sources
- `site.webmanifest` (`makePWA.py`)
- Release/catalogue JSON is already in `data/releases/` — build validates, does not rewrite membership

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
| Run Publish Build | `biblioteca/build.php` `mode=full` | `scripts/build.py` (5 steps) | Config-structure preflight only; **no** catalogue repair |
| Refresh Image Files | `biblioteca/build.php` `mode=optimize` | `scripts/optimizeMedia.py` only | `BANDPROMO_OPTIMIZE_MODE=image-only` |

There is **no** stage picker. `build-required` tasks (`playlist-scan`, `audio-delivery`, …) are notification labels only.

### PHP before Python (full publish)

| Step | Code | Problem |
|------|------|---------|
| Lock + log | `build.php` | OK |
| Preflight | `publish-preflight-helpers.php` → config repair only | Site settings structure check; catalogue repair is explicit **Repair catalogue** |
| Theme pack | `bandpromo_ensure_default_theme_package` | OK for empty installs; should be Stage 0/1 |
| Launch | `build-runner.php` → `build.py` | OK |

### Python `build.py` today (stage manifest)

| Order | Script | Group | Notes |
|-------|--------|-------|-------|
| Preflight | inline | preflight | tools + runtime templates |
| 1 | `buildCatalog.py` | catalog | register + masters |
| 2 | `optimizeMedia.py` full | deliverables | registry-scoped audio + image delivery |
| 3 | `buildSfxDelivery.py` | deliverables | SFX master + `media/sfx/optimal/{ast_*}.mp3` |
| 4 | `optimizeVideo.py` | deliverables | video delivery |
| 5 | `makePlaylists.py` | artifacts | publish player payloads for all playlists (no full-build validation walk) |
| 6 | `optimizeMedia.py` image-only | artifacts | visual-delivery catch-up for late covers |
| 7 | `makeSocial.py` | artifacts | share crops after deliverables |
| 8 | `makePWA.py` | artifacts | manifest |
| *(setup only)* | `initialSiteSeed.py` via `run-layout-seed.php` | initial site seed | not in publish chain |

### Side channels (not the main publish button)

| Trigger | What runs |
|---------|-----------|
| Audio upload | validation-only `makePlaylists.py`, `audioSourceDelivery.py` per file |
| Image upload | `optimizeMedia.py` image-only |
| Video upload | background `optimizeVideo.py` |
| Playlist save | master materialization (PHP), not full build |
| `content-autofix` API (Repair catalogue) | explicit catalogue repair pipeline |

These **helpers** follow registry/pool delivery prep; they are not a second playlist-first publish path.

## Gap summary (remaining)

1. ~~**Wrong order:** playlist artifacts before media deliverables.~~ **Fixed (Phase E).**
2. ~~**Wrong audio scope:** deliverables driven by playlist, not registry/pool.~~ **Fixed (Phase D).**
3. ~~**Autofix inside publish:** silent release/playlist rewrites.~~ **Fixed (Phase A).**
4. ~~**No stage isolation:** cannot run catalogue-only or deliverables-only.~~ **Fixed (Phase B profiles).**
5. ~~**Compose mislabeled:** bundled as publish step 6.~~ **Fixed (Phase F).**
6. ~~**Validation UX:** playlist-only card under System.~~ **Fixed (Publish status card).**
7. **Site shell stage:** theme/config/social prerequisites still split between PHP preflight and artifact stages (no dedicated Stage 1 runner yet).
8. **Visual deliverables:** still folder-based; registry-scoped visual delivery is v0.8.4 work.

## Recommended refactor sequence

Work in policy order; do not add more helpers until Stage 0–3 exist.

### Phase A — Stop the bleeding (small, safe)

- [x] Remove `content-autofix` from publish preflight; expose as explicit **Repair catalogue** action with dry-run preview.
- [x] Document current behaviour in admin Publish help (playlist vs pool scope).
- [x] Fix operator messaging: validation block titled **Playlist validation**, not “build complete”. *(Superseded: Publish tab now uses site-wide **Publish status**; playlist metadata stays in notifications/Files.)*

### Phase B — Stage runner skeleton

- [x] Replace monolithic `build.py` with a stage manifest (`scripts/build-stages.json` + `biblioteca/build-stages.php`): id, label, script, `requires_ffmpeg`, `skippable`.
- [x] Log each stage start/end + exit code in `log/build.log` (`STAGE_START:` / `STAGE_END:` lines).
- [x] `build.php` accepts `profile` or `stages[]` presets: `full`, `deliverables-only`, `artifacts-only` (written to `log/build.meta.json`).

### Phase C — Catalogue stage (master-first)

- [x] New `scripts/buildCatalog.py` + `biblioteca/build-catalog-helpers.php`: register + materialize + canonicalize for **all** catalogued originals (no playlist/release rewrites).
- [x] Upload path calls catalogue finalize after audio master preparation (`bandpromo_build_catalog_finalize_audio_upload`).

### Phase D — Deliverables stage (registry-scoped)

- [x] Refactor `optimizeMedia.py` full-mode audio delivery to use the asset registry queue, not legacy playlist artifacts.
- [x] Playlist JSON used only for track-cover linkage during delivery; `playlistAudioDelivery.py` resolves registry assets instead of requiring playlist membership.
- [ ] `audioSourceDelivery.py` per-upload path already filename/registry-based; keep aligned as registry helpers evolve.

### Phase E — Artifacts stage

- [x] Reorder publish stages: deliverables (`optimizeMedia`, `optimizeVideo`) before artifacts (`makePlaylists`, `makeSocial`, `makePWA`).
- [x] `makePlaylists.py` runs after deliverables; exports playlist documents and validation only (no ffmpeg dependency on that stage).
- [x] Social and PWA generation remain in the artifacts group after media deliverables (share crops read delivery-ready sources).

### Phase F — Compose

- [x] Remove layout seed from default publish profile (`initialSiteSeed.py` is setup-only).
- [x] Run from setup completion (`run-layout-seed.php`) or explicit disaster recovery (`force: true`).

## Open questions (operator decisions)

1. **Deliverable scope:** ~~all registry audio assets, or only those referenced by a release/playlist?~~ **Locked (2026-07-01):** deliverables for **every registered asset**, independent of release or playlist membership.
2. **Prune policy:** ~~when a track is removed from playlist, delete its MP3 deliverable or keep until registry delete?~~ **Locked (2026-07-01):** prune deliverables **only when the asset is deleted** from the catalogue/registry — not when removed from a playlist or release.
3. **Initial site seed:** ~~delete auto-compose entirely vs keep as setup-only wizard?~~ **Locked (2026-07-01):** **setup-only** plus an explicit **disaster recovery** path (`force: true`). **Not** part of routine publish. Renamed in code/docs to **initial site seed** (`initialSiteSeed.py`).

### Initial site seed — scope clarification (locked narrative)

Initial site seed (`initialSiteSeed.py`) is **not** demo-content installation. Demo/starter content comes from the **starter theme package**, tracked **templates**, and setup seeding (`setup.php`, template bootstrap).

Initial site seed only:

- Seeds **empty** playlist container entries from catalogued audio on disk
- Seeds **empty** default gallery container entries from photo/video originals on disk
- Enables default player modules/tab order in `web-config.json`

That is useful when:

- **First setup** — operator has files but empty or missing container documents
- **Total recovery** — operator explicitly chooses “rebuild layout from files on disk” after catastrophic document loss (with warning that Content editor truth will be overwritten)

It is **not** useful when:

- The site already has playlist, release, and gallery documents the operator manages in Content
- Routine publish after uploads/edits
- Substituting for catalogue repair or deliverable generation

**Naming:** use **Initial site seed** (setup) or **Recover layout from disk** (`force: true` recovery).

## Success criteria

- Uploading 20 audio files and publishing processes **catalogue + deliverables** for the agreed scope — not only the six tracks on `main` playlist.
- Publish log shows **stage names** in target order; each stage can be skipped in dev/CLI.
- No release/playlist membership changes during publish unless operator ran **Repair catalogue**.
- Initial site seed never runs on routine publish for established sites.
