# bandPromo Platform Model (v0.8)

Source of truth for the v0.8 beta platform contract: releases, assets, containers, pages, themes, URLs, and legacy behaviour to remove.

Status: **policy complete** (2026-06-15). Implementation slices in [TODO.md](TODO.md).

Companion policy docs:

- [ACCESS-MODEL.md](ACCESS-MODEL.md) — tiers, login, FAQ, shared links, VIP overrides
- [DELIVERY-ARCHITECTURE.md](DELIVERY-ARCHITECTURE.md) — protected delivery, PWA, cast scope
- [PORTABILITY.md](PORTABILITY.md) — full backup vs data export/import, moved-site repair

## Terminology

| Term | Meaning |
|------|---------|
| **Asset** | One stored media file or inline content fragment (audio, image, video, richtext HTML). Identified by `asset_id`. |
| **Release** | Catalog entity: a marketed album/EP/single. Owns track membership, release date, and release-level metadata. |
| **Container** | Operator-managed document in `data/`: playlist, gallery, page, theme, release. |
| **Block** | One composition unit inside a page container. |
| **Module** | Editor + renderer for a block or container type. |
| **Pool** | Available items when adding to a container (Content editor left column). |
| **Registry** | Index listing containers or assets of one type. |

**Container-in-container** means **reference**, not folder nesting. Example: a page `gallery` block references a gallery container ID and a layout preset.

Admin UI uses friendly names (Playlist, Gallery, Page, Theme). Docs and code use the terms above.

## Asset identity and filenames

Operators must not depend on raw filenames. Disk names are stable IDs; human meaning lives in the asset registry.

### Three storage layers

| Layer | Purpose | On-disk name | Operator-visible |
|-------|---------|--------------|------------------|
| **Original** | Trust, recovery, audit | upload filename preserved; registry holds `original_filename` | Upload history only |
| **Master** | Canonical packaged file | `ast_{ULID}.{ext}` | No |
| **Delivery** | Playback/display derivatives | `ast_{ULID}` + variant (e.g. `/thumb.webp`, `/standard-stream.mp4`) | No |

Applies to **audio and visual** assets. Legacy visual files may still use human stems under `media/img/`, `media/photo/`, and `media/video/` until v0.8.4 migration completes.

### Asset ID format

- Prefix: `ast_`
- Body: **ULID** (Crockford base32, 26 characters)
- Example: `ast_01HY8K3M2P9XQ4R5S6T7V8W`

All containers, registries, playlists, releases, and deep links reference **`asset_id`**, never master filenames.

### Registry

`data/assets/registry.json` (or sharded `data/assets/{asset_id}.json`) maps each asset to:

- `display`: title, artist, version, alt text, etc.
- `slug`: unique **per release** for audio tracks (see URLs)
- `release_id` + `release_track_number` for audio in a release
- `original_filename`: exact upload name
- `storage`: paths to original, master, delivery tiers
- `tags`: filter facets for unified media pools
- `locked`: inherited from release lock (see below)

Human-readable export names (for future distributor handover ZIPs) are generated at **export time** from registry fields, not used as on-disk paths.

### Unified media pools

Two **media families** at the platform level — because intake, packaging, and delivery pipelines differ materially:

| Family | Contents | Operator pool |
|--------|----------|-----------------|
| **Audio** | Music, spoken word, theme welcome audio | Files → Audio; playlist/release references |
| **Visual** | Still images **and** video | Files → Visual (single pool); gallery/page/theme references |

**Scheduled v0.8.4:** collapse today's constructed split — **Illustrations** (`media/img/`), **Photos** (`media/photo/`), and **Video** (`media/video/`) — into one **Visual** library. The old folder names are legacy intake buckets, not product categories. Anything visual can share one pool as long as assets are tagged and pickers apply context filters.

**Audio stays separate** — masters, metadata repair, playlist coupling, and MP3 delivery are a different pipeline from visual scaling and transcode.

### Asset identity applies to visual media too

Audio already uses `ast_{ULID}` on disk and in `data/assets/registry.json`. Visual assets should follow the same contract in v0.8.4:

- **Original** preserved under the upload name (audit/recovery) with `original_filename` in registry
- **Master** stored as `ast_{ULID}.{ext}` (canonical regeneration source)
- **Delivery** variants under `ast_{ULID}/` or `ast_{ULID}_{variant}.{ext}` — not human upload stems

Containers, galleries, pages, themes, and track covers reference **`asset_id`**, not `/media/img/original/my-logo.png`.

### Tags and roles (not folders)

Registry **`tags`** and derived facets replace folder location as the operator/filter model:

| Facet | Purpose | Examples |
|-------|---------|----------|
| `media_type` | Intake/delivery pipeline branch | `image`, `video` |
| `has_alpha` | Format/delivery policy | `true` for logos, overlays |
| `origin` | Bundled vs operator content | `user-upload`, `bundled-placeholder`, `generated` |
| `delivery_ready` | Pool gating | computed from variant manifest |

**Role** (track cover, gallery item, page picture, theme logo, share source) comes from **references** in containers/blocks/config — not from which legacy folder the file was uploaded to.

### Picker and admin filter contract

Media pickers declare a **context**; the backend returns assets from the Visual pool filtered for that context:

| Picker context | Typical filters |
|----------------|-----------------|
| Track cover | `media_type=image`, delivery-ready, square-friendly |
| Gallery item | `media_type` image or video, delivery-ready |
| Page picture | `media_type=image`, delivery-ready |
| Theme logo | `media_type=image`, prefer `has_alpha` |
| Background video | `media_type=video`, delivery-ready |
| Share / poster source | `media_type=image`, large enough for share variant |

Admin **Files → Visual** exposes the same pool with operator filters: type (image/video), in-use/orphan, alpha, delivery-ready, references — replacing separate Illustrations/Photos/Video tabs.

`media/special/` install identity assets (**logos, share sources**) migrate into the Visual pool with tags such as `theme-identity` during v0.8.4; until then they remain a workaround path that bypasses the JPEG optimizer.

## Releases

A release is a traditional album/EP/single release: recorded tracks, marketing plan, and release date.

### Rules

- Every audio track must belong to **exactly one** release (explicit `release_id` **and** required album/release tag on the master for builds).
- `release_date` is the primary availability threshold (soft by user role in v0.9; operators always bypass).
- ISRC / ISWC: not in v0.8; reserve per-track fields for future distributor handover.
- **`catalog_id`**: optional operator-defined release reference (for example `CD001`, `EP002`, `CD Mute 142`) — your internal or label catalog scheme, not a distributor ISRC.
- Future **distribution handoff lock**: master immutable for long-term preservation after handover to external distributors.

### Storage

```
data/releases/registry.json
data/releases/{release-id}.json
```

Release document (sketch):

```json
{
  "version": 1,
  "id": "twisted-chronicles-ep",
  "slug": "twisted-chronicles-ep",
  "title": "Twisted Chronicles (EP)",
  "release_date": "2026-09-01",
  "locked": false,
  "catalog_id": "EP002",
  "short_description": "One-line summary for cards and previews.",
  "description": "Press-ready blurb for this release.",
  "poster_asset_id": "ast_01HY8K3M2P9XQ4R5S6T7V8W",
  "epk": {
    "tagline": "Short hook for press kits",
    "genre": "Alternative rock",
    "credits": "Produced by …",
    "press_contact": "7rym <7rym@7rym.net>",
    "streaming_links": [
      { "label": "Spotify", "url": "https://open.spotify.com/album/…" }
    ],
    "press_photo_asset_ids": ["ast_01HY8K3M2P9XQ4R5S6T7V8X"]
  },
  "tracks": [
    {
      "asset_id": "ast_01HY8K3M2P9XQ4R5S6T7V8W",
      "slug": "belief-radio-version",
      "track_number": 3
    }
  ]
}
```

### Release locking

When `locked: true`:

- Member track metadata and tags are **not editable**.
- Release membership and per-release track numbers are **not editable**.
- Masters must not be deleted or re-encoded.
- Tracks remain playable and assignable to playlists.
- Playlist reorder does **not** mutate files (see Legacy removal).

Future: `distribution_handoff_locked` for post-DistroKid/TuneCore-style handover.

## Playlists

Playlists are **containers** of track references. They are independent of releases.

### Rules

- Entries reference `asset_id` (and carry `release_id` for analytics context).
- A track may appear in multiple playlists.
- Playlist has its own `publish_date` for marketing / default-selection eligibility.
- Playlist is shown **in full**; embargoed tracks appear but are **not playable** for the current user tier.
- Analytics bind plays to **track → release**, not to playlist.
- v0.8: **system playlists only** (`kind: "system"`). User/VIP playlists later (v0.9+).
- Operator-created playlists in admin use `kind: "system"` until user playlists ship (v0.9+).
- Player playlist selector appears only when **two or more** system playlists exist in the registry.

### Presentation metadata (v0.8.3+)

Shareable containers should carry operator-authored marketing fields in addition to title and dates:

| Field | Playlists | Pages | Releases |
|-------|-----------|-------|----------|
| `catalog_id` | — | — | Operator catalog reference (for example `CD001`, `CD Mute 142`) |
| `description` | Campaign blurb for share cards | Share/summary text | Press / EPK blurb |
| `poster_asset_id` | Share/OG image | Share/OG image (fallback: first image block) | Release cover (album/EP/single art) |

**Release EPK (extended metadata, v0.8.3+):** releases are the catalog and press hub — tagline, genre, credits, contact/press email, external streaming/store links, and optional press photo set references. Playlists remain listening campaigns; releases hold long-lived catalog and EPK truth.

**Contact / email storage (v0.8.4+):** operator and release contacts use RFC 5322 strings (for example `7rym <7rym@7rym.net>`). Values are validated and canonicalized on save: control characters stripped, mailbox domains lowercased, display names trimmed. Empty contact is allowed when no valid mailbox can be derived (for example localhost dev installs). Outbound mail is not implemented in v0.8; this layer prepares consistent contact data for future press-reply and notification features and improves deliverability hygiene before any SMTP work lands.

### Default playlist

Among system playlists where `publish_date <= now`, select the one with the **latest** `publish_date`. Player opens that playlist on first visit. There is no special `main` playlist id — clean installs seed the system playlist `bandpromo-demo` (demo release tracks); operator campaigns use their own ids.

### Storage

```
data/playlists/registry.json
data/playlists/{playlist-id}.json
```

Playlist position is **only** in the playlist container order. It must never write track numbers into audio masters.

## Galleries

Same container pattern as playlists, for image/video `asset_id` entries. Exposure is via page **gallery** blocks, not a permanent player tab.

```
data/galleries/registry.json
data/galleries/{gallery-id}.json
```

**Default system gallery:** clean installs seed the protected system gallery `bandpromo-demo` (`BANDPROMO_GALLERY_DEMO_ID`). Legacy id `main` is migrated into `bandpromo-demo` on seed (same pattern as playlist `main` removal).

## Pages and blocks

Canonical storage: `data/pages/*.json` + `data/pages/registry.json` (shipped).

### Block types

| Block | Content |
|-------|---------|
| `richtext` | Sanitized HTML body |
| `picture` | Image `asset_id` or `src`, layout, optional **plain-text** `caption` only |
| `picture_richtext` | Image + optional sanitized richtext `body` |
| `list` | Ordered/unordered items |
| `gallery` | `gallery_id` + `preset` (`grid`, `list`, `carousel`, `parallax`, …) |

All block types are implemented as **modules** (editor + renderer). Playlists and lyrics stay in the **player shell**; pages link in via deep links, not embedded players.

## Themes

Theme = container of style definitions (colors, fonts, typography tokens, brand asset paths).

```
data/themes/registry.json
data/themes/{theme-id}.json
```

- Setup seeds `setup-default` — complete, `system: true`, `locked: true` (fail-safe).
- Operators **duplicate** to customize; they do not edit the locked seed in place.
- Active theme: `install.pointers.active_theme_id` in `web-config.json` (or `data/install/state.json`).
- Content → Themes uses the pool/preview editor pattern; **Set active** updates the install pointer.
- Config → Theme becomes a shortcut to active theme brand paths during migration (see **Settings → Theme**).
- Release/theme overrides (per-release visual identity) are **post-basics**; v0.8 ships one active install theme.

### Semantic color and layout tokens

Theme containers expose tokens that map to CSS custom properties on `:root` (player, pages, login share one contract). Theme packs and GitHub ZIP distributions reuse this schema.

**Color tokens (required v0.8):**

| Token | CSS variable | Purpose |
|-------|--------------|---------|
| `color.primary` | `--primary-color` | Accent, active controls, progress |
| `color.secondary` | `--secondary-color` | Secondary accent, visited links |
| `color.background` | `--bg-color` | Page background |
| `color.text` | `--text-color` | Primary text |
| `color.text_muted` | `--color-text-muted` | Hints, secondary copy |
| `color.surface_mid` | `--color-surface-mid` | Gradient mid tone |
| `color.surface_deep` | `--color-surface-deep` | Gradient deep tone |
| `color.link` | `--color-link` | Default links |
| `color.link_hover` | `--color-link-hover` | Link hover |
| `color.link_visited` | `--color-link-visited` | Visited links on dark backgrounds |

**Layout:** Player cover art size (`--card-size`) is **not** a theme token. Responsive breakpoints and orientation rules live in `biblioteca/style.css`.

**Brand asset refs (paths to `asset_id` or delivery URL):**

- `brand.logo`, `brand.poster`, `brand.background_image`, `brand.background_video`
- `brand.welcome_audio`, `brand.loggedin_audio`
- `brand.favicon_package` (optional; icons under `media/icons/`)

**Typography (v0.8 minimum):**

- `typography.font_family_base` — body/UI stack
- `typography.font_family_heading` — optional; falls back to base

Renderer injects tokens as `:root` overrides when a theme is active. Page blocks and player chrome read the same variables.

### Theme document sketch

```json
{
  "version": 1,
  "id": "setup-default",
  "title": "Setup Default",
  "system": true,
  "locked": true,
  "tokens": {
    "color": { "primary": "#00d2ff", "secondary": "#3a7bd5", "background": "#121212" },
    "typography": { "font_family_base": "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif" }
  },
  "assets": {
    "logo": "ast_…",
    "poster": "ast_…"
  }
}
```

## `data/` layout (source of truth)

All operator/user containers live under `data/`. Build output under `play/` is generated or deprecated — not the edit surface.

```
data/
  assets/registry.json
  releases/registry.json + {id}.json
  playlists/registry.json + {id}.json
  galleries/registry.json + {id}.json
  pages/registry.json + {id}.json      # shipped
  themes/registry.json + {id}.json
  player/layout.json                   # tab order (from web-config player branch)
```

Legacy paths (`play/playlist.json`, `data/playlist-order.json`, `data/gallery.json`) are **publish/build artifacts or repair outputs** only. Admin save paths and runtime readers use `data/playlists/`, `data/galleries/`, and the asset registry instead.

## URLs and deep links

Path-based URLs (no query strings for core navigation).

### Player

```
/play/{playlist-id}
/play/{playlist-id}/{release-slug}/{track-slug}
```

- `release-slug` and `track-slug` come from the release container and release membership.
- Track `slug` is unique **per release**, not globally.
- OG/share metadata for track links: track + release identity from containers/registry.

### Pages

```
/pages/{page-id}
```

Page share previews use page title plus first meaningful image block or release poster fallback.

## Track numbers (two concepts)

| Concept | Stored in | Updated when |
|---------|-----------|--------------|
| **Release track number** | `data/releases/{id}.json` + master tags via **Release** editor | Reordering within release (if unlocked) |
| **Playlist position** | `data/playlists/{id}.json` `entries[]` order | Reordering playlist freely |

Playlist position must **never** sync into ID3/FLAC `TRCK` tags.

## Editor UX pattern

Content editors use one pattern (shipped for playlist/gallery/pages):

1. Left: pool of **containers** (playlists, galleries, pages, themes).
2. Right: preview of selected container.
3. **Add** or **edit** replaces the container list with a pool of **assets** (or block types) to insert.

## Module registry

Every block type and optional container feature is a **module** with a stable `module_id`, editor, and renderer.

### Core modules (always present)

Shipped in every install; may not be disabled in v0.8:

| module_id | Kind | Notes |
|-----------|------|-------|
| `block.richtext` | page block | Shipped |
| `block.picture` | page block | Plain caption variant |
| `block.picture_richtext` | page block | Image + richtext body |
| `block.list` | page block | Shipped |
| `container.playlist` | container editor | System playlists |
| `container.gallery` | container editor | System galleries |
| `container.page` | container editor | Shipped |
| `container.theme` | container editor | Theme pool editor |
| `container.release` | container editor | Release catalog |
| `player.playlists` | player shell | Not a page block |
| `player.lyrics` | player shell | Not a page block |

### Optional modules (operator toggles)

Enabled per install via **Admin → Settings → Modules** (v0.8 policy: simple toggles UI).

| module_id | Default v0.8 | Notes |
|-----------|--------------|-------|
| `block.gallery` | on | Gallery block on pages |
| `player.tab.gallery` | on until module blocks ship | Transitional; off when gallery blocks cover workflow |
| `block.news` | off | v1+ |
| `block.fanboard` | off | v1+ |
| `quiz` | off | Non-core; modular assets |

### Registry storage

```
data/install/modules.json
```

```json
{
  "version": 1,
  "modules": {
    "block.gallery": { "enabled": true },
    "player.tab.gallery": { "enabled": true },
    "quiz": { "enabled": false }
  }
}
```

Disabled modules: hide editor entry points; renderer skips block type with admin-only placeholder in preview; player tabs omitted from layout.

## Access, delivery, portability (policy complete)

- **Access:** [ACCESS-MODEL.md](ACCESS-MODEL.md) — VIP per-release default + per-track override; anonymous sees embargoed tracks locked, not hidden.
- **Delivery / PWA / cast:** [DELIVERY-ARCHITECTURE.md](DELIVERY-ARCHITECTURE.md) — PHP authorizes, static delivery serves bytes; cast scope = full playable/viewable media (v0.9+).
- **Backup / export:** [PORTABILITY.md](PORTABILITY.md) — separate full backup (DR) and data export (fresh install import).

## `web-config.json` target shape (install shell)

After migration, `web-config.json` holds **install shell + pointers only**:

```json
{
  "install": {
    "site": { "url": "", "language": "en", "author": "" },
    "social": { },
    "pointers": {
      "active_theme_id": "setup-default",
      "active_release_id": null
    }
  },
  "support": { },
  "player": { }
}
```

Release identity, theme tokens, and brand assets move to `data/releases/` and `data/themes/`. Compatibility reads from legacy `site` / `media` / `release.*` branches continue until dual-write migration completes (see [MEDIA-HANDLING.md](MEDIA-HANDLING.md)).

## Legacy behaviour to remove

These behaviours come from the old single-playlist / filename-key model and must not survive the migration:

1. **`bandpromo_sync_playlist_order_to_audio_masters()`** in `biblioteca/save-playlist-order.php` — playlist save must not rewrite master tags.
2. **`playlist_tracknumber`** as a metadata source in audio master detail/save — track number comes from release membership only.
3. **Filename-keyed** playlist order arrays — use `asset_id` in containers.
4. **Inferring release order from playlist position** in build/validation — release container owns order.

## Implementation order (v0.8)

1. Asset registry + ULID intake for new uploads; migrate existing masters to `asset_id`.
2. `data/releases` + required track membership; release locking.
3. `data/playlists` + remove playlist→master sync; migrate off `play/playlist.json`.
4. Player: playlist selector, default-by-`publish_date`, path URLs with per-release slugs.
5. Embargoed tracks visible but non-playable in playlist UI.
6. `data/galleries` + page `gallery` block (grid preset minimum).
7. `data/themes` + setup protected seed + duplicate + active pointer.
8. Split `picture` / `picture_richtext` blocks.

## Related docs

- [ROADMAP.md](ROADMAP.md) — milestone structure and beta expectations
- [TODO.md](TODO.md) — implementation checklist
- [ACCESS-MODEL.md](ACCESS-MODEL.md) — access tiers and login
- [DELIVERY-ARCHITECTURE.md](DELIVERY-ARCHITECTURE.md) — playback delivery and PWA
- [PORTABILITY.md](PORTABILITY.md) — backup and migration
- [MEDIA-HANDLING.md](MEDIA-HANDLING.md) — original/master/delivery tiers and validation
- [FEATURES.md](FEATURES.md) — operator-facing feature list
