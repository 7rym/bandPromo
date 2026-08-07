# bandPromo Portability: Backup, Export, and Moved Sites

Source of truth for operator backup, data export/import, host migration, and **portable release packages (PRP)**.

**Status:** policy locked for v0.8 (2026-06-15); **PRP product lock 2026-08-06** (see §3). **Implementation:** Admin → System → **Backup & export** ships component picker export, ZIP import (restore + cross-site migrate), ready/job list with download/delete (2026-07-13). Campaign import/export builders exist; completing **PRP-only** setup (`.prp`, demo as locked import, no parallel content ZIPs) is the active v0.8 management gate.

Related: [INSTALL-UPDATE.md](INSTALL-UPDATE.md), [PLATFORM-MODEL.md](PLATFORM-MODEL.md), [ACCESS-MODEL.md](ACCESS-MODEL.md), [ROADMAP.md](ROADMAP.md).

## Three operator services

bandPromo offers **three distinct portability services**, not one combined ZIP:

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

**Follow-up (priority TODO):** rewrite full backup export/import for the **UID-only asset model** once PRPs are the sole campaign handoff path.

**Restore flow:**

1. Deploy current bandPromo **application** release package to target folder (bootstrap or updater).
2. Upload/extract **full backup** over preserved runtime paths.
3. Run moved-site URL repair if host changed (below).
4. Admin post-restore checklist: version match, build-required recalc, smoke playback.

### 2. Data export / import

**Purpose:** **fresh install on a new domain** or clean host — operator content and configuration without carrying logs, stale build state, or full media weight unless chosen.

**Export tiers:**

| Export type | Contents | Typical use |
|-------------|----------|-------------|
| **Config + containers** | `web-config.json`, full `data/` | New install, same content model, re-link or re-upload media |
| **Config + containers + media manifest** | above + asset registry with storage paths | Validates media presence on import |
| **Config + containers + media bundle** | above + `media/` subset (masters + required delivery) | One-step migration to empty host |

Prefer **PRP round-trips** for one-campaign moves. Use data export when moving an entire install’s config/containers.

**Import flow (fresh install):**

1. Bootstrap/install application package on new host.
2. Complete setup (imports `bandPromo-demo.prp` unless a full data import replaces that path).
3. Admin → **Import data package** → validates structure, merges `data/`, applies `web-config` install pointers.
4. If media bundle included: extract to `media/`; if not: operator uploads or copies media separately using manifest checklist.
5. **Repair site URL** wizard runs automatically when `install.site.url` disagrees with current host.
6. Build-required recalc; operator verifies playback.

**Import must not:**

- overwrite application code from export package
- silently merge with wrong schema version (require compatible `export_version`)

### 3. Portable release package (PRP) export / import

**Purpose:** move **one release campaign** between installs without a full site backup. Setup installs **`bandPromo-demo.prp`** with this same importer. Operators may rename `.prp` to `.zip`; the archive is a normal ZIP with a bandPromo-specific layout (not a DistroKid/distributor format).

**Status:** product lock **2026-08-06**. Partial import/export code shipped earlier; completing PRP-only vanilla setup and operator UX is the active gate before v0.9.

#### What a PRP is

| Rule | Decision |
|------|----------|
| **Unit** | One campaign (Release umbrella): brand, tracks, playlists, galleries, owned pages, masters, registry subset |
| **File** | ZIP; preferred extension `.prp` |
| **IDs** | **Keep** `ast_*`, release, playlist, gallery, page, brand ids across export/import |
| **Media** | **Masters only** — no upload `original/` tier, no `optimal/` / delivery; target builds deliverables on import or Publish. Registry may still record `original_filename` as metadata. Sound effects follow the same rule (`media/sfx/master/`; delivery under `media/sfx/optimal/` is rebuilt on the target). |
| **Not included** | Analytics / play-logs, unrelated releases, `web-config.json` as portable truth, install FAQ (system-owned) |
| **Data packages** | **PRPs only** for campaign content — no parallel default-theme / demo-release **content** ZIPs |

#### What travels in a PRP

| Layer | Included | Notes |
|-------|----------|-------|
| **Release document** | `data/releases/{id}.json` | Title, dates, EPK, `poster_asset_id`, `brand_id`, `tracks[]` |
| **Identity (brand)** | `data/brands/{id}.json` + shell **masters** | Owned by the release; slots address Visual/SFX by **`asset_id`** |
| **Track masters** | `media/audio/master/*` | Canonical tagged masters; originals stay on the source host |
| **Playlists** | Docs with `release_id` | Listening products |
| **Galleries / pages** | Docs with `release_id` | Demo PRP: **Bio** + **Gallery** page (gallery block → demo gallery). Not FAQ. |
| **Linked visuals / SFX** | `media/visual/master/*`; `media/sfx/master/*`; **asset registry subset** | No upload originals or delivery in the package; SFX delivery rebuilt as `media/sfx/optimal/{ast_*}.mp3` |
| **Manifest** | `release-package-manifest.json` | `release_export_version`, title, paths, flags (`platform_demo`, locked), bandPromo `VERSION` |

#### Demo PRP (`bandPromo-demo.prp`)

- Imported at **setup** first; becomes the locked **base shell** brand (secure fallback until the operator selects another base).
- **Read-only** for operators; optional **hide** when they want only their own catalog; cannot delete.
- Operators may **duplicate** as a template: **new container ids**, **shared** media `ast_*`; delete blocked while an asset is multi-referenced.
- Only a **localhost developer** may change the demo PRP source and re-export it.
- System re-import of demo defaults to **overwrite** so delivery can rebuild.

#### Operator import collisions

When imported ids already exist, the operator chooses (refuse / overwrite / skip-existing). Labels are implementation detail; silent overwrite of operator campaigns is forbidden.

#### Duplicate vs import

| Operation | Container ids | Media `ast_*` |
|-----------|---------------|---------------|
| **Export → import** (between installs) | **Keep** | **Keep** |
| **Duplicate** (same install, template) | **New** | **Shared** (same files); multi-ref delete guard |

#### Import flow

1. Validate `release_export_version` and bandPromo compatibility.
2. Apply collision policy (operator UI or system overwrite for demo).
3. Extract masters + campaign docs; **merge** asset + container registries (**keep ids**).
4. Setup: set install base brand to demo brand when importing demo PRP (first-run / system path).
5. Build deliverables (Publish / post-import delivery).
6. Smoke-check playback, shell, Bio/Gallery contextual tabs.

#### Ambassador and services model

bandPromo does not operate a marketplace or take a cut. Ambassadors and release preparers hand off real PRPs; commercial terms stay between people.

#### Security and integrity

- Same zip-slip and path validation bar as site backup import.
- PRPs contain masters — treat downloads like backups (HTTPS, store safely).
- Import refuses incompatible schema versions with plain-language upgrade instructions.

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

Full backup is a **superset** operators control on demand. Data export is a **selective** site portability tool. **PRP** export is a **release-scoped** handoff tool. Application code updates use GitHub **application** Release ZIPs (not PRPs).

## Operator UX (target)

Archives are written to `backups/` on the server (HTTP-blocked, gitignored, excluded from backup ZIP contents). Operators choose components in one **Create backup** panel, queue the archive, wait until status is **Ready**, then **download** separately.

| Component | ZIP contents |
|-----------|----------------|
| **bandPromo platform** | `web-config.json`, optional `.env` |
| **Data** | `data/` (containers, users, activity SQLite) |
| **Media** | `media/` (originals, masters, delivery) |
| **Logs** | `log/` (build and support logs) |
| **Full** | All four (master checkbox) |

Presets: all four = full site backup; platform + data = legacy data export tier.

| Action | Location | Output | Status |
|--------|----------|--------|--------|
| Queue backup (component picker) | Admin → System → Backup & export | Job in `backups/` | **Shipped** |
| Import backup ZIP | Admin → System → Backup & export | Restore or migrate selected components | **Shipped** |
| Download ready archive | Admin → System → Backup & export | `.zip` download | **Shipped** |
| Delete server archive | Admin → System → Backup & export | Removes `backups/{id}.zip` | **Shipped** |
| Import during setup | Setup wizard | Guided merge + URL repair | Planned |
| Restore full backup | Manual extract or admin import | Replace runtime paths | **Shipped** (admin import) |
| Export PRP (`.prp`) | Admin → Catalogue / Release | Campaign ZIP | **In progress** (builder exists; PRP-only product path active) |
| Import PRP | Setup + Admin | New or refreshed release slot | **In progress** (demo PRP = setup gate) |

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

- `export_version` (site backup) or `release_export_version` (PRP)
- bandPromo `VERSION` at export time
- `exported_at`
- optional `install_id` (non-secret reference)
- for PRPs: human-readable release title, path checklist, optional `platform_demo` / lock flags

Import refuses incompatible major schema versions with plain-language upgrade instructions.
