# Master-tier audit and completion plan

_Date: 2026-08-13 — after a fresh Demo PRP install on a live host (v0.8.15)._

Source of truth for **original → master → deliverables** across **audio, Visual, Sound effects, and Brand assets**. Implementation checkboxes live here; [TODO.md](TODO.md) points at this file. Related: [MEDIA-HANDLING.md](MEDIA-HANDLING.md), [PLATFORM-MODEL.md](PLATFORM-MODEL.md), [PORTABILITY.md](PORTABILITY.md).

This is not the v0.8-exit [LEGACY-AUDIT.md](LEGACY-AUDIT.md) (runtime fallbacks / removed artifacts). This slice is the media working-copy contract.

---

## Why this exists

A fresh Demo PRP install played audio (playlist + masters) but:

- **Files → Audio** showed **No files yet** because the Files index scanned `media/audio/original/` (PRP ships masters only).
- **`/play` covers 404’d** because the player still asked for legacy `/media/img/{optimal|original}/{stem}.*` instead of `/media/visual/delivery/{ast_*}/`.

Build 380 patched those two symptoms. The same class of bug remains in cover extract, living cover, video delivery, login SFX, Brand `media/special/`, stem sidecars, and several Publish/Python paths. This document inventories them and orders the rewrite.

---

## Policy (locked 2026-08-13)

One rule for every family (audio, Visual stills, Visual video, Sound effects, Brand assets):

1. **Original** — write-once at upload/replace, **original filename preserved**, never rewritten, never used as a working copy after the master exists.
2. **Master** — canonical working file, **`ast_{ULID}.{ext}`**, preferred format (below). All metadata edits, cover assignment, living-cover assignment, and regeneration read/write this file.
3. **Deliverables** — generated **from the master**, named by asset id (audio/SFX: `ast_{ULID}.mp3`; visual: `media/visual/delivery/{ast_*}/{variant}`). Public playback and UI use delivery only. Missing delivery is pending, not “play the original.”

### Legal original I/O (not violations)

- Write-once on upload and same-name replace (replace keeps the same `ast_*`).
- Operator **Download original**.
- Unlink original **as part of asset delete** (also unlink master + delivery).
- Registry `original_filename` / `by_original_filename` as **display and provenance** only.

Download original must 404 when the original is absent (PRP / masters-only). Do not stream the master and label it original.

### Preferred master formats

| Family | Master | Delivery |
|--------|--------|----------|
| Audio | Tagged FLAC when source is WAV; otherwise tagged copy in the source codec (`ast_*.flac` / `ast_*.mp3`) — never misrepresent lossy as lossless | `media/audio/optimal/{ast_*}.mp3` (tagless) |
| Visual still | Same codec as intake (`jpg` / `png` / `webp`) at `media/visual/master/ast_*.{ext}`; IPTC Core via XMP on the master | `media/visual/delivery/{ast_*}/thumb\|card` |
| Visual video | Stream-copy remux to **MKV** + Matroska tags (`ast_*.mkv`) | `standard-stream.mp4` (+ `poster`); silent except `role=gallery` |
| Sound effects | Same three-tier as audio (`media/sfx/{original,master,optimal}`) | `media/sfx/optimal/{ast_*}.mp3` |
| Brand assets | Same Visual (or SFX) pipeline — **no parallel `media/special/` working copies** | Visual delivery / SFX optimal |

### Public vs operator URLs

| Audience | Allowed |
|----------|---------|
| `/play`, login, pages, OG, gallery | Delivery URLs (and resolved `cover_url` / `asset_ids`) |
| Admin Files listing / editor | Master as the working file; original filename as a label |
| Admin Download original | Original bytes only |

---

## Classification

| Class | Meaning |
|-------|---------|
| **Breach** | After intake, code still *works from* originals, human stems, or sidecars |
| **Dual-read leftover** | Master/delivery preferred, but original/stem/`img|photo|video|special` still accepted |
| **Known exception (now in this plan)** | Previously scheduled: Brand `special/`, living-cover filename tags, video not yet MKV, cover extract to `{stem}.*` |
| **Legal intake** | Upload materialize, download original, delete unlink, provenance fields |

---

## Already aligned (do not redo)

- Audio/visual/SFX **materialize master from original** at upload (`audio-master-helpers.php`, `visual-master-helpers.php`, `sfx-helpers.php`).
- Visual image delivery for **registered** assets writes `media/visual/delivery/{ast_*}/` (`optimizeMedia.process_visual_image_asset`). Registered video skips `video/optimal` dual-write (`optimizeVideo.process_one_video`).
- PRP **audio/visual export is masters-only**; import rebuilds the Files index (build 380).
- Playlist enrich can emit `cover_url` via `bandpromo_visual_resolve_url`; player tries `/media/visual/delivery/{ast_*}/` when the cover stem is an asset id (build 380).
- Files index **second pass** indexes registry masters when originals are missing (build 380).
- `scripts/visualMasterMetadata.py` already reads/writes visual **masters** only (but is not Python 3.6-safe: `from __future__ import annotations` — fix when touching).

---

## Findings by cluster

Key files are listed; the PHP/Python inventories behind this pass are in the 2026-08-13 agent session, not duplicated line-by-line.

### C1 — Stem sidecars and cover extract (breach)

Policy already says **no stem guessing**. Code still pairs `{audioStem}.jpg` in `media/img/original/`.

| Area | Evidence |
|------|----------|
| Publish extract | `scripts/makePlaylists.py` `extract_embedded_cover_to_stem` / `get_cover` write and read `img/original/{stem}.*` |
| Configured cover | `get_configured_cover_filename` mints `configured_release_cover.*` into `img/original` |
| Upload guess | `upload-media.php` `image_matches_audio_basename` |
| Cover-art helpers | `cover-art-helpers.php` stem maps, `infer_role`, `resolve_linked_cover_basename`, register-extracted keeps `display.cover` on the **pool original basename** |
| Admin detail | `audio-master-detail-helpers.php` sidecar stem scan; `audioMasterMetadata.py` `get_sidecar_cover` |
| Stem dual-write | `optimizeMedia.py` `process_track_cover` still writes `img/optimal/{stem}.jpg` and `img/thumb/{stem}.jpg` from originals |

**Fix:** Embedded art → Visual original (write-once, human or extract name) → Visual `ast_*` master → `display.cover` = **asset id**. Delete stem maps and `process_track_cover`.

### C2 — Refs still store original filenames (known exception → breach)

| Area | Evidence |
|------|----------|
| Living cover tags | `BANDPROMO_LIVING_COVER` = video **original basename**; helpers require `media/video/original/`; enrich **rewrites asset ids back to original_filename** |
| Playlist payload | `cover` / `living_cover` = basenames; player then guesses stems |
| Brand clone | `theme_clone_asset_file` writes `{brand}_{slot}.ext` into `media/special/` and `sfx/original/` |
| SFX / shell defaults | Brand seeds `/media/sfx/original/bandPromo_welcome.flac` and `/media/special/bandPromo_*.png` |

**Fix:** Registry and master tags store **`ast_*`**. Player payload stores asset id + resolved delivery URL. Autofix remaining filename refs once, then refuse them.

### C3 — Working copy still original (breach / dual-read)

| Area | Evidence |
|------|----------|
| Playback | `bandpromo_resolve_source_audio_file` prefers original; `audio.php` can stream original; demo fallback serves original FLAC; playlist playability uses `variant=original` |
| Files identity | Index still **scans original dirs first**; listing `name` is often the original; notifications **drop rows unless original exists** |
| Delivery jobs | `videoSourceDelivery.py` always reads `media/video/original/`; `bandpromo_list_videos_needing_delivery` scans that folder; `optimizeMedia` / `audioSourceDelivery` fall back to originals |
| SFX play | `bandpromo_sfx_resolve_play_url` / `bandpromo_sfx_web_path` fall through to original (login plays FLAC originals) |
| Brand optimize | `optimize_shell_brand_media_images` **resizes `/media/special/*` in place** (mutates originals) |
| Visual resolver | `bandpromo_visual_resolve_url` / `bandpromo_visual_working_path` still return original URLs for public/admin preview |
| Gallery | `gallery_resolve_image_src`, `gallery_video_delivery_relative_path` stem `photo/optimal` and `video/optimal` |

**Fix:** After materialize, every resolve/index/Publish/play path takes **master** (work) or **delivery** (public). Original is download/delete only.

### C4 — Brand assets and leftover intake folders (known exception)

| Area | Evidence |
|------|----------|
| Files targets | `bandpromo_media_target_dir` is still `img/photo/video/original` + `special` |
| Setup dirs | `setup.php` still creates legacy `img/photo/.../original|optimal|thumb` and `media/special` |
| Shell / OG | `theme_resolve_shell_slot_url`, `config-loader.php`, `index.php`, `play/index.php` fall back to `/media/special/` and `/media/video/original/` |
| Page allowlist | `page-blocks.php` allows `/media/img\|photo/optimal/` and `/media/special/` |
| PRP SFX | Export falls back to `media/sfx/original/` when the master is not `ast_*` |
| Player bases | `play/index.php` sets `MEDIA_IMG_BASE='/media/img'` |

**Fix:** Fold Brand visuals into Visual original/master/delivery. Brand slots are `asset_ids` only. SFX PRP is master-or-refuse.

### C5 — Preferred formats not finished (known exception)

| Area | Evidence |
|------|----------|
| Video masters | Locked as MKV; on-disk masters are still intake remux/copy, often MP4; `TODO` item **Video remux-to-MKV** open |
| Still masters | Locked IPTC/XMP write-through; **Still IPTC/XMP** open |
| Living cover player | Still documents `media/video/optimal/{stem}.mp4` as the ready check |

**Fix:** Ship MKV remux + Matroska tags; IPTC/XMP on still masters; living-cover ready = Visual `standard-stream` delivery.

---

## Implementation plan

Order is dependency-strict: **identity (`ast_*` refs) → working copy = master → delivery from master → fold Brand/special → preferred formats → delete dual-read**.

Python under `scripts/` stays CPython **3.6.9** compatible. Do not mint `ast_*` in Python unless a 3.6-safe helper exists; prefer PHP register + Python consume `master_filename`.

### T0 — Policy lock

- [x] Lock original → master → delivery for **all families**, including Brand assets and SFX.
- [x] Lock legal original I/O (upload/replace, Download original, delete unlink, provenance).
- [x] Lock living-cover tag value = **visual asset id** (revises 2026-07-15 filename lock).
- [x] This document.

### T1 — Identity: `ast_*` refs, no stem guessing

Stop teaching the stack that covers and living covers are original basenames.

- [x] Remove `image_matches_audio_basename` and all `cover-art-helpers` stem maps / sidecar infer.
- [x] Rewrite `extract_embedded_cover_to_stem` / `get_cover`: hash-match existing Visual → link `ast_*`; else write Visual original once, materialize Visual master, set `display.cover` to asset id. Never write `img/original/{audioStem}.*`.
- [x] Stop minting `configured_release_cover.*`; point tracks at the release poster `ast_*`.
- [x] Persist living cover as visual **asset id** in registry `display.living_cover` **and** `BANDPROMO_LIVING_COVER` master tags; stop rewriting ids back to original filenames; picker must not require `media/video/original/` on disk.
- [x] Playlist player payload: `cover` / `living_cover` are asset ids; `cover_url` / `animated_cover` are delivery URLs only.
- [ ] Autofix remaining filename refs → asset ids (covers, living, brand slots, gallery `src`); then fail loud on bare filenames.

### T2 — Working copy is the master

- [x] Files index rebuild/list/delete/reference **by `master_filename` / asset id**; original is a label. Notifications use master existence (not `media/audio/original/{file}`).
- [x] `bandpromo_resolve_source_audio_file` / `audio.php` / playlist playability: delivery for public, master for operator listen; **drop original as a playable variant** (keep Download original).
- [x] Drop demo original-FLAC playback fallback.
- [x] Publish/Python working paths (`resolve_audio_working_path`, `visual_working_path_for_asset`, `visual_video_source_path`): **master or fail**. Do not scan original dirs to enumerate work.
- [x] Video delivery queue from Visual video **masters**, not `media/video/original/` (`videoSourceDelivery.py`, `bandpromo_list_videos_needing_delivery`).
- [x] SFX public URL = optimal MP3 only (`bandpromo_sfx_resolve_play_url`); login/player never get `/media/sfx/original/`.
- [x] Download `variant=original` 404 if original missing (no silent master substitute). Delete by asset id (original + master + delivery).

### T3 — Deliverables from masters; kill stem dual-write/read

- [x] Delete `process_track_cover` stem `img/optimal|thumb` dual-write; covers go through Visual `process_visual_image_asset`.
- [x] Retire `videoSourceDelivery.py` stem `video/optimal` + `video/poster`; write `media/visual/delivery/{ast_*}/` only.
- [x] Stop in-place resize of `/media/special/*`.
- [x] `bandpromo_visual_resolve_url` public path: delivery only. Admin preview: master file URL or a dedicated original-download endpoint — not original as a page `<img src>`.
- [x] Gallery / page allowlist / `admin.js` `videoPosterPathFromSrc`: Visual delivery variants only.
- [x] `play/index.php`: drop `MEDIA_IMG_BASE='/media/img'` stem contract; `player.js` uses server `cover_url` (no `img/original|optimal/{stem}` candidates).
- [x] Playlist cover helper: no `/media/img|photo/{thumb|optimal}/{stem}.jpg` fallback.

### T4 — Brand assets and leftover folders

Absorbs the old “Phase 3 Brand-assets fold” and PRP SFX original fallback.

- [x] Relocate `media/special/` brand visuals into Visual original (write-once) + `ast_*` master + delivery; brand slots are `asset_ids` only.
- [x] Brand duplicate clones **new `ast_*` masters**, not `{brand}_{slot}` files in `special/` / `sfx/original/`.
- [x] Setup/runtime dirs: `media/visual/{original,master,delivery}` and `media/sfx/{original,master,optimal}`; stop creating `img/photo` optimal/thumb trees as product paths.
- [x] Config / OG / login / player shell fallbacks resolve Base brand `asset_ids` → delivery (no hardcoded `/media/special/bandPromo_*.png`).
- [x] PRP SFX: export master or refuse the row; never pack `sfx/original`.
- [x] Files → Brand assets becomes a **filter/role** on Visual (or SFX), not a parallel intake tree.

### T5 — Preferred master formats

Absorbs open Visual naming tasks.

- [x] Video materialize: remux to `media/visual/master/ast_*.mkv` + Matroska tags; delivery stays MP4.
- [x] Still masters: EXIF read for `captured_at`; IPTC Core via XMP write-through; heal empty `display` from embeds.
- [x] Confirm WAV → FLAC audio master and SFX master/delivery naming match the table above.
- [x] Living-cover “ready” = Visual `standard-stream` delivery exists (not `video/optimal/{stem}.mp4`).

### T6 — Fail loud and delete shims

- [ ] Remove dual-read branches listed in C3/C4 once T1–T5 callers are gone.
- [ ] Welcome starter-pack file list: drop `media/audio/original/bandPromo_*.flac` existence checks (Demo PRP marker / demo release doc only).
- [x] `scripts/visualMasterMetadata.py`: drop `from __future__ import annotations` / `capture_output` / `text=` (Python 3.6.9) when that file is touched.
- [ ] `initialSiteSeed.py` gallery `src`: Visual delivery / asset id, not `/media/photo|video/original/`.
- [ ] Content autofix: keep one-shot original→master **repair** only; do not add new runtime original scans.

### T7 — Verify

Fresh **Demo PRP** install (masters-only) plus an operator upload:

- [ ] Files → Audio / Visual / Sound effects list **masters**; titles from registry; original name is secondary.
- [ ] `/play` covers and living covers load `/media/visual/delivery/{ast_*}/…` only (no `/media/img/` or `/media/video/optimal/` 404s).
- [ ] Login welcome/logged-in SFX is `media/sfx/optimal/{ast_*}.mp3`.
- [ ] Publish extract does **not** create `img/original/{stem}.*`; new covers are Visual `ast_*`.
- [ ] PRP export includes track-cover and living-cover **visual masters** and SFX masters; import does not need originals.
- [ ] Operator Download original works when original exists; 404 on PRP rows. Delete removes original+master+delivery.
- [ ] Brand logo/poster/backgrounds resolve from `asset_ids` → visual delivery.

---

## Mapping from older TODO items

These open/partial items are **owned by this plan** (check them off here, not as parallel tracks):

| Former TODO | Plan step |
|-------------|-----------|
| Living cover `filename → ast_*` (tags) | T1, T5 |
| Brand-assets fold / `media/special/` | T4 |
| Video remux-to-MKV | T5 |
| Still IPTC/XMP | T5 |
| M4 leftover stem optimal dual-read (resolver, gallery, player) | T3 |
| PRP SFX original fallback | T4 |
| Cover extract / sidecar / `process_track_cover` | T1, T3 |
| Files index originals-first (partially patched in build 380) | T2 |

---

## Exit criteria

The slice is done when a masters-only PRP install and a normal operator upload both obey the three-tier rule **without original/stem dual-read**, and the evidence files in C1–C5 no longer treat originals as working copies.

_Last updated: 2026-08-13 (T3 done: deliverables from masters; stem img/video dual-write/read removed. Next: T4 Brand/special fold.)_
