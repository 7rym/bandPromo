# bandPromo Portability: Backup, Export, and Moved Sites

Source of truth for operator backup, data export/import, and host migration.

**Status:** policy locked for v0.8 (2026-06-15). **Implementation:** Admin → System → **Backup & export** ships component picker export, ZIP import (restore + cross-site migrate), ready/job list with download/delete (2026-07-13). **Release package** export/import policy locked (2026-07-15); implementation planned v0.9. Setup-time import and richer URL repair remain planned.

Related: [INSTALL-UPDATE.md](INSTALL-UPDATE.md), [PLATFORM-MODEL.md](PLATFORM-MODEL.md), [ROADMAP.md](ROADMAP.md).

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

- tracked application PHP/JS (reinstalled from release package)
- `vendor/` (regenerated or shipped with package)
- `backups/` (operator archive staging area; never packed into another backup)

**Restore flow:**

1. Deploy current bandPromo **release package** to target folder (bootstrap or updater).
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

**Import flow (fresh install):**

1. Bootstrap/install release package on new host.
2. Complete setup wizard (or skip demo seed when import detected).
3. Admin → **Import data package** → validates structure, merges `data/`, applies `web-config` install pointers.
4. If media bundle included: extract to `media/`; if not: operator uploads or copies media separately using manifest checklist.
5. **Repair site URL** wizard runs automatically when `install.site.url` disagrees with current host.
6. Build-required recalc; operator verifies playback.

**Import must not:**

- overwrite application code from export package
- silently merge with wrong schema version (require compatible `export_version`)

### 3. Release package export / import

**Purpose:** move **one release** — masters, packaging, visuals, and release metadata — between installs without a full site backup. This is the portability path for **demo handoffs**, **ambassador workflows**, and **paid release-prep services**.

**Status:** policy locked (2026-07-15). Implementation planned v0.9.

**Why a third service**

Full backup moves an entire install. Data export moves config and containers (optionally all media). Operators and ambassadors often need something narrower: a **finished release** prepared on one site, imported on another, with all track extras intact — ready for Publish on the target host.

That supports closed-beta motivation: experienced testers prepare real releases on their own installs, export them, and seed demo or prospect installs so new operators see a polished player experience (Markdown lyrics, living covers, EPK copy, embedded artwork) without rebuilding every asset by hand.

**What travels in a release package**

| Layer | Included | Notes |
|-------|----------|-------|
| **Release document** | `data/releases/{id}.json` | Title, dates, EPK, `poster_asset_id`, `brand_id` reference, credits |
| **Track masters** | `media/audio/master/*` for release tracks | Canonical packaging tier |
| **Master tags** | Inside FLAC/MP3 | Title, artist, album, lyrics, description (Markdown source), embedded cover art, `BANDPROMO_LIVING_COVER` |
| **Linked visuals** | Covers, living-cover videos, release poster | Originals + registry entries; target runs **Publish** to regenerate delivery MP4s/JPGs |
| **Asset registry** | Subset in `data/assets/registry.json` | Only entries referenced by the release |
| **Export manifest** | `release-manifest.json` in ZIP | Human-readable release title, `release_export_version`, `exported_at`, file checklist |

Human-readable names (release title, track titles in manifest) are generated at **export time** only. On-disk paths stay `ast_{ULID}` — same nondestructive naming contract as the rest of the platform ([PLATFORM-MODEL.md](PLATFORM-MODEL.md)).

**What does not travel**

- Unrelated playlists, pages, galleries, brands (unless explicitly added in a later scope)
- Listener accounts, analytics, or admin audit history
- Whole-site `web-config.json` (target keeps its own install shell)
- Delivery-tier files as the source of truth — target regenerates from masters on Publish

**Export flow (source install)**

1. Operator or ambassador finishes the release on **their** install: masters tagged, covers embedded, living covers assigned, release/EPK fields complete.
2. Admin → **Releases** → **Export release package** (or equivalent entry under Backup & export).
3. Platform validates completeness (masters present, registry consistent, referenced media exists).
4. Download ZIP; store or send to target operator.

**Import flow (target install)**

1. Admin → **Import release package**.
2. Platform validates `release_export_version` and bandPromo version compatibility.
3. Import creates a **new release slot** on the target (no silent overwrite without explicit confirm).
4. Extract masters and linked visuals; merge registry entries; remap `brand_id` to target active brand or prompt operator to choose.
5. Operator runs **Publish** on the target for streaming MP3s, cover delivery, and living-cover video delivery.
6. Smoke-check: player playback, living cover swap on play, Markdown lyrics/descriptions.

**Ambassador and services model**

bandPromo does not operate a marketplace or take a cut. The product enables a practical workflow:

| Actor | Role |
|-------|------|
| **Ambassador** | Experienced beta tester who demos bandPromo to prospective operators using **real prepared releases** |
| **Release preparer** | Anyone who packages metadata, artwork, living covers, and EPK copy on a source install |
| **Target operator** | Imports the package, publishes on their domain, owns the live site |

Ambassadors can advance from tester to demo helper by maintaining showcase releases on their own sites and transferring them to prospect demo installs. Operators who are skilled at release packaging may **sell preparation as a service** (freelance, label services, agency work) — bandPromo supplies the portable handoff; commercial terms stay between people.

**Security and integrity**

- Same zip-slip and path validation bar as site backup import.
- Release packages contain masters and media — treat downloads like full backups (HTTPS, store safely).
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

Full backup is a **superset** operators control on demand. Data export is a **selective** site portability tool. Release package export is a **release-scoped** handoff tool.

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
| Export release package | Admin → Releases (planned) | Release-scoped ZIP | Planned |
| Import release package | Admin → Releases or Backup & export (planned) | New release slot on target | Planned |

Listener and admin-audit SQLite live under **Data** (`data/`). Include that component (or **Full**) to back them up with the rest of site content.

**Import modes**

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

- `export_version` (site backup) or `release_export_version` (release package)
- bandPromo `VERSION` at export time
- `exported_at`
- optional `install_id` (non-secret reference)
- for release packages: human-readable release title and per-track checklist (export-time labels only)

Import refuses incompatible major schema versions with plain-language upgrade instructions.
