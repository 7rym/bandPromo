# bandPromo Portability: Backup, Export, and Moved Sites

Source of truth for operator backup, data export/import, host migration, and **Portable Campaign Files (PCF)**.

**Status:** policy locked for v0.8 (2026-06-15); **PCF product lock 2026-08-18** (was PRP / `.prp`; see §3). **Implementation:** Admin → System → **Backup & export** ships component picker export, ZIP import for **site backup** (restore + cross-site migrate), ready/job list with download/delete (2026-07-13). Campaign import/export builders exist; **PCF-only** campaign handoff (`.pcf`, demo as locked import, no parallel campaign content packages) is the active v0.8 management gate.

Related: [INSTALL-UPDATE.md](INSTALL-UPDATE.md), [PLATFORM-MODEL.md](PLATFORM-MODEL.md), [ACCESS-MODEL.md](ACCESS-MODEL.md), [ROADMAP.md](ROADMAP.md).

## Transfer integrity (downloads and chunked uploads)

Operator file handoff uses shared transport helpers — not per-feature streamers:

| Layer | Module |
|-------|--------|
| Download stream | [`biblioteca/http-stream.php`](../biblioteca/http-stream.php) — `bandpromo_http_stream_file` (no Range for packages; `X-Checksum-SHA256`) |
| Chunked upload | [`biblioteca/chunked-upload.php`](../biblioteca/chunked-upload.php) — last-chunk assemble, `file_size` + optional `expected_sha256` |
| Admin JS | `bandpromoUploadChunked` / `bandpromoDownloadVerified` in [`biblioteca/admin.js`](../biblioteca/admin.js) |

**Archive digest:** when a Jobs export becomes Ready, the job stores `size_bytes` + `sha256` of the archive file. Verified download refuses to save if either mismatches. Headers include `X-Checksum-SHA256`.

**In-package digests:** PCF / PBF manifests include `file_digests` (per-path SHA-256 + size). Import verifies after extract. Site backup manifests include digests for non-media paths; when media is included, integrity for media bytes is the archive SHA-256 on the Jobs record (`media_integrity: archive_sha256`).

Never tell operators these archives are ZIPs. Never put the zip’s own SHA-256 inside the zip.


bandPromo offers **four distinct portability services**, not one combined ZIP. **Portable Brand File (PBF / `.pbf`)** is the brand-only sibling of PCF — do not overload `.pcf` for that unit.

### 1. Full site backup

**Purpose:** disaster recovery — restore the **same installation** on the same or replacement host as fast as possible.

**Includes:**

- `web-config.json`
- `.env` (if present)
- entire `data/` tree (assets registry, releases, playlists, galleries, pages, themes, users, install state, **`data/analytics/events.sqlite`** activity store)
- entire `media/` tree (original, master, delivery, icons, special)
- `log/` (optional toggle; default include for support continuity — build/dev logs; listener/audit activity is in `data/analytics/`)
- install identity (`data/install/identity.json` or equivalent runtime identity state)

**Excludes:**

- tracked application PHP/JS (reinstalled from the **application** GitHub Release package)
- `vendor/` (regenerated or shipped with the app package)
- `backups/` (operator archive staging area; never packed into another backup)

**Follow-up (priority TODO):** rewrite full backup export/import for the **UID-only asset model** once PCFs are the sole campaign handoff path.

**Restore flow:**

1. Deploy current bandPromo **application** release package to target folder (bootstrap or updater).
2. Upload/extract **full backup** over preserved runtime paths.
3. Run moved-site URL repair if host changed (below).
4. Admin post-restore checklist: version match, build-required recalc, smoke playback.

### 2. Data export / import

**Purpose:** **fresh install on a new domain** or clean host — operator content and configuration without carrying logs, stale build state, or full media weight unless chosen. In this repo, “clean host” / fresh install always means **https://bandpromo.site** (Vanilla), never this local working copy’s `data/` / `media/` / `log/`. The other active remote test sites are Spandexual Tension and HITZ (Twisted Chronicles paused until v0.9 reinstall).

**Export tiers:**

| Export type | Contents | Typical use |
|-------------|----------|-------------|
| **Config + containers** | `web-config.json`, full `data/` | New install, same content model, re-link or re-upload media |
| **Config + containers + media manifest** | above + asset registry with storage paths | Validates media presence on import |
| **Config + containers + media bundle** | above + `media/` subset (masters + required delivery) | One-step migration to empty host |

Prefer **PCF round-trips** for one-campaign moves. Use data export when moving an entire install’s config/containers.

**Import flow (fresh install):**

1. Bootstrap/install application package on new host.
2. Complete setup (imports `bandPromo-demo.pcf` unless a full data import replaces that path; setup still accepts a published legacy `bandPromo-demo.prp`).
3. Admin → **Import data package** → validates structure, merges `data/`, applies `web-config` install pointers.
4. If media bundle included: extract to `media/`; if not: operator uploads or copies media separately using manifest checklist.
5. **Repair site URL** wizard runs automatically when `install.site.url` disagrees with current host.
6. Build-required recalc; operator verifies playback.

**Import must not:**

- overwrite application code from export package
- silently merge with wrong schema version (require compatible `export_version`)

### 3. Portable Campaign File (PCF) export / import

**Purpose:** move **one release campaign** between installs without a full site backup. Setup installs **`bandPromo-demo.pcf`** with this same importer. Operators always see a **PCF** / **`.pcf`** — never a ZIP, and never an invitation to rename the file.

**Status:** product lock **2026-08-18** (rename from PRP / `.prp`). Import still accepts legacy `.prp` without advertising it. Completing PCF operator UX is the active gate before v0.9.

#### What a PCF is

| Rule | Decision |
|------|----------|
| **Unit** | One campaign (Release umbrella): brand, tracks, playlists, galleries, owned pages, masters, registry subset |
| **File** | Portable Campaign File; extension **`.pcf`**. Never describe it to operators as a ZIP. |
| **IDs** | **Keep** `ast_*`, release, playlist, gallery, page, brand ids across export/import |
| **Media** | **Masters only** — no upload `original/` tier, no `optimal/` / delivery; target builds deliverables on import or Publish. Registry may still record `original_filename` as metadata. Sound effects follow the same rule (`media/sfx/master/`; delivery under `media/sfx/optimal/` is rebuilt on the target). |
| **Not included** | Analytics / play-logs, unrelated releases, `web-config.json` as portable truth, install FAQ (system-owned) |
| **Data packages** | **PCFs only** for campaign content — no parallel default-theme / demo-release **content** packages |

#### What travels in a PCF

| Layer | Included | Notes |
|-------|----------|-------|
| **Release document** | `data/releases/{id}.json` | Title, dates, EPK, `poster_asset_id`, `brand_id`, `tracks[]` |
| **Identity (brand)** | `data/brands/{id}.json` + complete curated Brand library | Owned by the release; `library_asset_ids` includes Visual/SFX assets even when no shell slot currently uses them |
| **Track masters** | `media/audio/master/*` | Canonical tagged masters; originals stay on the source host |
| **Playlists** | Docs with `release_id` | Listening products |
| **Galleries / pages** | Docs with `release_id` | Demo PCF: **Bio** + **Gallery** page (gallery block → demo gallery). Not FAQ. |
| **Linked visuals / SFX** | `media/visual/master/*`; `media/sfx/master/*`; **asset registry subset** | No upload originals or delivery in the package; SFX delivery rebuilt as `media/sfx/optimal/{ast_*}.mp3`. Track `display.cover` / `living_cover` refs (bare `ast_*` or `ast_*.png`) resolve to visual asset ids so cover masters travel. Import rebuilds the Files index from masters when originals are absent. |
| **Manifest** | `release-package-manifest.json` | `release_export_version`, title, paths, flags (`platform_demo`, locked), bandPromo `VERSION` |

#### Demo PCF (`bandPromo-demo.pcf`)

- Imported at **setup**; becomes the locked **base shell** brand (secure fallback until the operator selects another base).
- Lives on the durable GitHub release tag **`demo-content`** as `bandPromo-demo.pcf` + `demo-manifest.json` — **not** re-uploaded with every application build. Until the next demo publish, that tag may still serve the legacy filename `bandPromo-demo.prp`; setup prefers `.pcf` and falls back to `.prp`.
- Application releases (`bandPromo.zip` + `release-manifest.json`) embed a pointer to that durable Demo PCF for checksum/URL; setup also falls back to `demo-content` directly when needed.
- **Site update** compares that published SHA to `data/demo-release-package.json` and re-imports (overwrite) when newer so older installs pick up demo standard/feature changes. Skipped when the demo is **unlocked on localhost**. Admin **Publish** does not re-download the Demo PCF.
- **Locked** for operators after import: optional **hide** or **duplicate** (new container ids, shared media); cannot delete the platform demo release.
- Hide is release-level (`demo_release_id` / `demo_release_hidden` in `data/install-preferences.json`): demo campaign containers (playlists / pages / galleries) and **unused** demo workspace media leave admin lists, Files pools, pickers, and the player. Demo Brand documents (including locked `bandpromo-default` when it is not Base) and Brand assets / Sound effects stay only while still referenced by the Base brand or another non-demo brand. Hide is offered only after an operator-created campaign with a track is exposed on a playlist. Shared use no longer refuses hide — in-use demo media stay visible with a soft inventory. If that operator catalogue is later deleted, the demo catalogue is shown again.
- PCF export follows every `library_asset_ids` member in the release Brand, not only current shell-slot `asset_ids`. Import preserves the curated library, and campaign duplication shares those registry asset IDs instead of duplicating the global media.
- Only a **localhost developer** may unlock/override that lock, edit the campaign like any other release, and **re-export** it as the new `bandPromo-demo.pcf` (then `python scripts/prepare_demo_content_package.py --pcf … --publish`).
- **No parallel demo content model:** after the release-ownership model, do **not** add code paths that special-case “demo” for ownership, heals, filename→release inference, or association rules. Demo is a normal campaign that arrives via PCF; lock + hide + duplicate + localhost unlock are the only demo-specific operator surfaces. Media stays out of git (`/media` ignored); PCF / published packages carry masters.
- System re-import of demo defaults to **overwrite** so delivery can rebuild.

#### Application release assets

| Asset | Role |
|-------|------|
| `bandPromo.zip` | Application + install icons + runtime stubs |
| `release-manifest.json` | Version, SHA256, `package_url`, embedded Demo PCF pointer |

Legacy `bandpromo-default-theme-*.zip` is **not** published for operators. Campaign media travels only in PCFs.

#### Operator import collisions

When imported ids already exist, the operator chooses **Refuse** / **Overwrite** / **Skip** / **AsNew** (allocate a new campaign id). Labels are implementation detail; silent overwrite of operator campaigns is forbidden.

#### Duplicate vs import

| Operation | Container ids | Media `ast_*` |
|-----------|---------------|---------------|
| **Export → import** (between installs) | **Keep** | **Keep** |
| **Duplicate** (same install, template) | **New** | **Shared** (same files); multi-ref delete guard |

#### Import flow

1. Validate `release_export_version` and bandPromo compatibility.
2. Apply collision policy (operator UI or system overwrite for demo).
3. Extract masters + campaign docs; **merge** asset + container registries (**keep ids**).
4. Setup: set install base brand to demo brand when importing the Demo PCF (first-run / system path).
5. Build deliverables (Publish / post-import delivery).
6. Smoke-check playback, shell, Bio/Gallery contextual tabs.

Large campaign PCFs (hundreds of MB of masters) must extract **to disk without loading each entry into PHP memory**. Admin Import uploads in **2 MB chunks** (same pattern as Files media uploads) so nginx/`post_max_size` body limits do not need to match the full package size—only a single chunk.

Gallery rows that only store delivery URLs must resolve `asset_id` (from `src`) so linked Visual masters travel in the PCF. Import registers campaign **pages** (not only playlists/galleries/brands) and marks deliverables rebuild; admin queues **deliverables-only** Publish after a successful import.

#### Ambassador and services model

bandPromo does not operate a marketplace or take a cut. Ambassadors and release preparers hand off real PCFs; commercial terms stay between people.

#### Security and integrity

- Same path-traversal validation bar as site backup import.
- PCFs contain masters — treat downloads like backups (HTTPS, store safely).
- Import refuses incompatible schema versions with plain-language upgrade instructions.

### 4. Portable Brand File (PBF) export / import

**Purpose:** move **one brand** (identity + curated library) between installs or campaigns without moving tracks, playlists, galleries, or pages.

**Status:** product lock **2026-08-30**. Sibling of PCF — same product family (Portable *X* File; never call it a ZIP to operators; masters + registry subset; Jobs + collision modes), different unit.

#### What a PBF is

| Rule | Decision |
|------|----------|
| **Unit** | One brand document + curated library Visual/SFX masters (slot + library asset ids) + registry subset |
| **File** | Portable Brand File; extension **`.pbf`**. Never describe it to operators as a ZIP. |
| **IDs** | **Keep** `brd_*` / brand id and `ast_*` across export/import unless **AsNew** remaps the brand id |
| **Media** | **Masters only** — same bar as PCF; target rebuilds deliverables on import |
| **Not included** | Campaign tracks, playlists, galleries, pages, analytics, `web-config.json` |
| **Ownership** | Import clears `campaign_id` / `release_id` so the brand is unowned until assigned |

#### What travels in a PBF

| Layer | Included | Notes |
|-------|----------|-------|
| **Brand document** | `data/brands/{id}.json` | Title, tokens, shell slots, curated `library_asset_ids` |
| **Linked visuals / SFX** | `media/visual/master/*`; `media/sfx/master/*`; **asset registry subset** | From brand `asset_ids` + `library_asset_ids` |
| **Manifest** | `brand-package-manifest.json` | `brand_export_version`, `format: pbf`, title, paths, bandPromo `VERSION` |

#### Operator import collisions

When the brand id already exists: **Refuse** / **Overwrite** / **Skip** / **AsNew** (allocate a new `brd_*`). Platform default (`bandpromo-default`) and locked brands cannot be overwritten via PBF; use Skip or AsNew.

#### Surfaces

Admin → System → **Backup, export & import**: Export / Import PBF cards + Jobs (**Download .pbf**). Optional Branding deep-link after import.

## Moved-site recovery

When restored or copied runtime data references a different host than the live request:

1. Detect mismatch: `install.site.url` vs current origin.
2. Admin banner + setup repair wizard:
   - offer to update `install.site.url` and derived absolute URLs in themes/pages where stored
   - do **not** rewrite `asset_id` or media paths
3. Preserve **install identity** across legitimate moves (see ROADMAP installation-identity model).

## Preservation contract (updates vs backup)

Package updater and bootstrap already preserve:

`web-config.json`, `.env`, `data/`, `media/`, `log/`, `backups/`

Full backup is a **superset** operators control on demand. Data export is a **selective** site portability tool. **PCF** export is a **release-scoped** handoff tool. **PBF** export is a **brand-scoped** handoff tool. Application code updates use GitHub **application** Release ZIPs (not PCFs or PBFs).

## Operator UX (target)

Archives are written to `backups/` on the server (HTTP-blocked, gitignored, excluded from backup ZIP contents). Operators choose components in one **Create backup** panel, queue the archive, wait until status is **Ready**, then **download** separately.

| Component | Meaning (operator) | ZIP contents |
|-----------|--------------------|--------------|
| **Site settings** | Install config | `web-config.json`, optional `.env` |
| **Catalogue & config** | Campaigns, brands, users, activity | `data/` |
| **Media library** | Originals, masters, delivery | `media/` |
| **Support logs** | Build and admin logs | `log/` |
| **Full** | All four (master checkbox) | All four |

Presets: all four = full site backup; platform + data = legacy data export tier.

| Action | Location | Output | Status |
|--------|----------|--------|--------|
| Queue backup (component picker) | Admin → System → Backup & export | Job in `backups/` | **Shipped** |
| Import backup ZIP | Admin → System → Backup & export | Restore or migrate selected components | **Shipped** |
| Download ready archive | Admin → System → Backup & export | `.zip` download | **Shipped** |
| Delete server archive | Admin → System → Backup & export | Removes `backups/{id}.zip` | **Shipped** |
| Import during setup | Setup wizard | Guided merge + URL repair | Planned |
| Restore full backup | Manual extract or admin import | Replace runtime paths | **Shipped** (admin import) |
| Export PCF (`.pcf`) | Admin → System → Backup | Portable Campaign File | **Shipped** (legacy `.prp` import still accepted) |
| Import PCF | Setup + Admin | New or refreshed release slot | **Shipped** (Demo PCF = setup gate) |
| Export PBF (`.pbf`) | Admin → System → Backup | Portable Brand File | **Shipped** |
| Import PBF | Admin → System → Backup | New or refreshed brand | **Shipped** |

Listener and admin-audit SQLite live under **Data** (`data/`). Include that component (or **Full**) to back them up with the rest of site content.

**Import modes (site backup)**

| Mode | Use when | Behaviour |
|------|----------|-----------|
| **Restore** | Same install (disaster recovery) | Overwrites selected components; keeps source install identity from archive |
| **Migrate** | Another site or new host | Overwrites selected components; **keeps this site's install identity**; logs usually skipped; optional site URL repair |

## Security

- Backup/export zips contain sensitive config and media — download over HTTPS; warn operator to store safely.
- Import validates archive structure; reject unexpected paths (zip slip).
- No export of raw password hashes in operator-readable sidecar files.

## Versioning

Export manifest includes:

- `export_version` (site backup), `release_export_version` (PCF), or `brand_export_version` (PBF)
- bandPromo `VERSION` at export time
- `exported_at`
- optional `install_id` (non-secret reference)
- for PCFs: human-readable release title, path checklist, optional `platform_demo` / lock flags
- for PBFs: human-readable brand title, path checklist, `format: pbf`

Import refuses incompatible major schema versions with plain-language upgrade instructions.
