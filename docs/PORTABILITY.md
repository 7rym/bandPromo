# bandPromo Portability: Backup, Export, and Moved Sites

Source of truth for operator backup, data export/import, and host migration.

**Status:** policy locked for v0.8 (2026-06-15). **Implementation:** v0.8+ operator feature (after core `data/` model lands). **v0.8.3 priority:** betatesters need in-app backup before trusting large updates — see [TODO.md](TODO.md) → v0.8.3.

Related: [INSTALL-UPDATE.md](INSTALL-UPDATE.md), [PLATFORM-MODEL.md](PLATFORM-MODEL.md), [ROADMAP.md](ROADMAP.md).

## Two operator services

bandPromo offers **two distinct portability services**, not one combined ZIP:

### 1. Full site backup

**Purpose:** disaster recovery — restore the **same installation** on the same or replacement host as fast as possible.

**Includes:**

- `web-config.json`
- `.env` (if present)
- entire `data/` tree (assets registry, releases, playlists, galleries, pages, themes, users, install state)
- entire `media/` tree (original, master, delivery, icons, special)
- `log/` (optional toggle; default include for support continuity)
- install identity (`data/install/identity.json` or equivalent runtime identity state)

**Excludes:**

- tracked application PHP/JS (reinstalled from release package)
- `vendor/` (regenerated or shipped with package)

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

## Moved-site recovery

When restored or copied runtime data references a different host than the live request:

1. Detect mismatch: `install.site.url` vs current origin.
2. Admin banner + setup repair wizard:
   - offer to update `install.site.url` and derived absolute URLs in themes/pages where stored
   - do **not** rewrite `asset_id` or media paths
3. Preserve **install identity** across legitimate moves (see ROADMAP installation-identity model).

## Preservation contract (updates vs backup)

Package updater and bootstrap already preserve:

`web-config.json`, `.env`, `data/`, `media/`, `log/`

Full backup is a **superset** operators control on demand. Data export is a **selective** portability tool.

## Operator UX (target)

| Action | Location | Output |
|--------|----------|--------|
| Create full backup | Admin → System (or Dashboard) | Timestamped `.zip` download |
| Create data export | Admin → System | Smaller `.zip` with manifest |
| Import data export | Setup or Admin (post-install) | Guided merge + URL repair |
| Restore full backup | Documented restore + optional admin upload | Replace runtime paths |

## Security

- Backup/export zips contain sensitive config and media — download over HTTPS; warn operator to store safely.
- Import validates archive structure; reject unexpected paths (zip slip).
- No export of raw password hashes in operator-readable sidecar files.

## Versioning

Export manifest includes:

- `export_version`
- bandPromo `VERSION` at export time
- `exported_at`
- optional `install_id` (non-secret reference)

Import refuses incompatible major schema versions with plain-language upgrade instructions.
