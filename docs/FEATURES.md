# bandPromo Features

bandPromo is a modern, self-hosted platform for private music releases and fan engagement. It is designed for artists and micro-labels who want full control over their content, audience, and analytics.

## Key Features

### Easy Setup
- Browser-based setup wizard after install/bootstrap
- Automatic creation of required folders and initial configuration
- Friendly license/operator-responsibility acknowledgment during setup
- First admin account creation with seeded demo content for first-run verification
- Setup checklist while installation is incomplete (demo catalog from `bandPromo-demo.prp`, successful full build); switches to a **Dashboard** once blocking checks pass. FAQ personalization and adding your own catalog stay as advice. Hide demo catalog is offered only after an operator release with a track is on a playlist; deleting that catalog shows the demo again.

### Admin Dashboard
- User-friendly admin panel for managing users, files, and site content
- **Notifications** operator inbox for **live** work only: media preparation, Site update, publish follow-ups, validation. The Welcome setup checklist stays on the Welcome page and is not mirrored into the bell. Hot paths stay read-only (lite by default; no catalog repair / materialize from the inbox).
- Quick actions on the completed-install dashboard (Analytics, Files, Content, live preview, Documentation)
- Built-in analytics for playback and user behavior (SQLite activity store at `data/analytics/events.sqlite`)
- Guided **Settings** forms (Basics, Support, Sharing) instead of raw JSON editing *(site shell text/SEO/provider-neutral in-flow support link; brand identity and shell media live in Content → Branding)*
- Content → Branding editor: pool, colors/typography tokens; **Readability** (backdrop dim + panel blur); **Shell media** slots (logo, poster, still/living backgrounds, welcome/logged-in **Sound effects**) use the shared media picker (same ✎ flow as covers) and store Visual/SFX **delivery** URLs (`card` / stream / optimal), not `/media/special/` originals; **Playlist selector** style (Dropdown / Buttons / Cover flow) is brand-owned — Base brand drives `/play`. Accent alpha CSS vars are derived from Primary/Secondary automatically. Main/heading family stacks apply at runtime and are syntax-validated; responsive sizes and spacing remain platform-owned. Upload under Files → Brand assets / Visual / Sound effects. Live preview shows tokens plus mobile-style player chrome and shell backdrop (living delivery stream when that slot is assigned, else still). The Branding preview plays living video even when the OS asks to reduce motion, so operators can judge the assignment; `/play` still falls back to still for reduced-motion visitors. Brand narrative fields (`mood` / `keywords` / `tone_notes`) stay in storage for future premade themes / AI helpers but are hidden from the editor for now. Duplicating a brand copies its containers and shares stable library media IDs. Saving the **base** brand syncs shell paths into `web-config.json`. Old `?tab=settings&ctab=theme` redirects here.
- Player **brand shell override**: selected playlist’s release brand drives CSS tokens **and** logo / still / living backgrounds; login shell + Welcome/Logged-in SFX stay on install Base; system scrim over busy backgrounds. Living shell video is preferred automatically when assigned (no Still|Living install toggle).
- **Block-based Pages editor** (v0.8 beta): Text, Picture, and List blocks; rich formatting; fraction picture widths and Flow placement; live player-styled preview; delete confirmations for pages and blocks
- Page registry in `data/pages/registry.json`: operators can add, rename, and remove optional pages; **FAQ remains system-owned and required** for login info / shared-link context (not part of PRPs)
- Content → **Playlist**, **Gallery**, and **Pages** share one pool/result editor pattern: **Available content** pool on the left, active order/layout on the right, multi-select drag-and-drop, demo filter on media pools only, and amber **Save** / green **Saved** header controls (button roles: [ADMIN-UI.md](ADMIN-UI.md)). Catalogue release associations use the same pool pattern for playlists, galleries, and pages.
- Content → **Playlist** and **Gallery** management (multiple libraries in admin; player playlist selector when two or more catalog playlists are public; pin a default playlist; package type + play order)
- **Player** loads playlist data from prebuilt static payloads in `data/playlists/{id}.json` (`tracks`, `brand_styles`, `delivery_summary`). Publish writes these on full rebuild; **playlist save also republishes that playlist’s payload** (plus missing audio delivery) so add-track loops do not need Rebuild all deliverables. Track order in the payload follows `play_order` (`stored` or `reverse`).
- Player **page tabs** (Bio, Gallery-as-page, custom pages) come from pages associated to the **playing playlist’s campaign** (Catalogue → Campaign editor → Pages; association order is tab order). Playlist and Lyrics stay fixed. Legacy site-wide `player.tab_order` / `show_in_player` remains a fallback when a playlist has no campaign-owned pages. Tabs ship as empty shells; the active tab hydrates on DOMContentLoaded, others on first open — so gallery stills/posters do not contend with first paint. Gallery video files still load only in the lightbox.
- **Registry-first admin**: Files lists, playlist/release editors, and track detail read stored indexes only — `data/assets/registry.json` display fields, published playlist tracks, and the **media files index** in `data/media-library-state.json` (`files`) for size/mtime/delivery listing. No DirectoryIterator, filesize, or tag parse on GET. Index/registry updates only on upload, delete, tag save, delivery jobs, Publish, PRP import, and container membership saves. Masters-only PRP audio (no `media/audio/original`) is indexed from the registry + `media/audio/master`. Files → Audio ignores non-audio leftovers in `media/audio/original` (covers belong in Visual).
- Content → **Branding** owns player chrome toggles (playlist selector, Beggars banquet, cover reflection) and shell media. The retired Content → Player layout tab redirects to Catalogue.
- Fresh install catalogues show only campaigns (typically `bandpromo-demo`). The `primary` orphan/upload bucket stays on disk but is **not** listed. Base brand stays locked **bandPromo Default** until the operator duplicates it — setup no longer auto-creates “Your own brand”.
- Files panels: **Audio | Visual | Sound effects | Brand assets**. Visual and Sound effects are global warehouses. Still/video originals live under `media/visual/original/` only (legacy `img`/`photo`/`video` intake is dual-read leftovers). Files → Visual **Catalogue** is every campaign that uses the file (gallery / cover / poster / page, or Brand visual shell those campaigns play, including Base-brand fallback for empty slots). Brand-library members with no campaign use list that Brand rather than Orphan. **In use / Unused** is live assignment (track cover, gallery, page, poster, or brand shell slot), not Catalogue and not Brand-library membership. Usage identity is the Visual `ast_*` id (titles and stems never match). List view thumbnail size is **S / M / L** (70 / 100 / 125 px; default M). Brand assets is the selected Brand's curated cross-media library (`library_asset_ids`) with upload, add-existing, and remove-membership actions; removal does not delete global media. **Add existing** is shown only when a Brand is selected (not All brands / Orphans); it is multi-select and hides assets already in that Brand library. Branding shell pickers are strict to compatible assets in that library. Sound effects use three-tier `media/sfx/{original,master,optimal}` storage; Visual uses shared `ast_{ULID}` registry identity and delivery variants. Files details modal previews those delivery variants (stills via `card`/`thumb`, living via poster/stream, SFX via optimal play URL).
- **Planned (remainders):** content AI wizards; visual delivery polish — see [TODO.md](TODO.md), [PLATFORM-MODEL.md](PLATFORM-MODEL.md), [USE-CASES.md](USE-CASES.md)
- Player **text panel role**: per-track Lyrics ↔ Notes (same Lyrics storage); Notes optional tab label defaults to Tracklist
- Files list header row aligned with file items: master select-all checkbox, compact filter dropdowns (`All` / usage filters plus `User files` / `Include demo`), and labeled **Upload**, **Download**, and **Delete** bulk actions
- Per-row selection with shift-range support, ZIP bulk download, and reference-aware delete warnings
- Audio quick-edit for common tag fields plus full editor for lyrics, description, and cover work
- Cover-art badges and compact in-use/orphan indicators on illustrations, photos, and video
- Operator-facing validation actions and file-level metadata health badges for faster repair workflows
- Upload-time **background delivery automation**: audio, image, and video derivatives prepare automatically after upload; Content pools list **delivery-ready** assets only; progress and failures surface in **Notifications**
- Build actions under **System → Status**: **Refresh site files** (catalog → audio/image optimize → **SFX delivery** → video → playlists → visual catch-up → social → PWA). **Repair catalog** is developer-only recovery, not an operator health check.
- **System → Security**: install host-protection sanity check (managed `.htaccess` / `.user.ini` vs templates) with optional preview/repair
- Admin-panel **package updater** for hosted operators (immutable release packages); after install, refreshes the locked platform Demo PRP when the published `demo-content` SHA is newer
- Separate admin audit trail under **System → Audit**
- Built-in documentation browser with operator/developer doc separation
- Listener accounts can use the player but cannot open admin surfaces

### Media Player
- High-quality audio playback with seek/next/previous navigation
- **Playlists** and **Lyrics** tabs (core player shell — not page-embedded)
- **Markdown** in player lyrics and track descriptions (rendered at display; masters unchanged)
- **Animated track covers (living cover)** — silent looping video on the main cover card while audio plays whenever a living cover is assigned and delivery MP4 exists (no player toggle; still cover when paused/idle or reduced motion). Publish fills empty master tags from the asset registry when needed.
- Cover delivery: player card / lightbox use visual **`card`**; playlist rows and cover-flow use **`thumb`** (`/media/visual/delivery/{asset_id}/…`, Publish). Playlist payloads include `cover_url`; `/play` also resolves `ast_*` cover filenames to visual delivery/master when `cover_url` is absent.
- Platform-owned responsive shell: stacked portrait flow, dimension-aware split mode, height-aware cover sizing, and consistent panel scrolling
- Content-specific width policies: centered readable Lyrics/Notes and prose, wider playlists, full-canvas galleries/media, and horizontally scrollable narrow tab navigation
- In-flow support CTA below playback controls with validated contrast and a reduced-motion-safe intermittent attention halo
- Artwork and lightbox support (including page images)
- Post-login splash shows the install logo with **Preparing your experience…** before entering the player
- Shell **background image/video** from brand settings on both **login and player** (still paints first; living video attaches after load/idle; still-only when reduced motion / slow link / Still mode)
- Player brand colors resolve **live** from the selected playlist’s owning release brand (Content → Branding edits apply without waiting for a full Publish); tracks do not carry player brand
- Player audio uses `preload="none"` until Play so large MP3 range GETs do not stall first-paint thumbs

### Build & Delivery
- Automated build pipeline for optimized audio and images
- Upload-time background tasks for audio delivery, image delivery, and video delivery (`biblioteca/auto-build-tasks.php` plus focused Python runners); audio upload also refreshes registry display from master tags
- Playlist save prepares missing delivery MP3s and republishes that playlist’s player payload (full Deliverables rebuild remains for site-wide/PWA recovery)
- **Tagless delivery audio** — `media/audio/optimal/*.mp3` ships without ID3/APIC; player, Media Session, and future Cast use registry/playlist metadata (masters stay fully tagged)
- **Shipped (master-tier T0–T7):** original → master `ast_*` → delivery for audio, Visual, SFX, and Brand; living-cover `ast_*`; video MKV masters + still IPTC/XMP heal; stem dual-read removed — see [MASTER-TIER-AUDIT.md](MASTER-TIER-AUDIT.md)
- Validation-only playlist scan after audio upload; setup runs **initial site seed** (`scripts/initialSiteSeed.py`) for empty playlist/gallery containers and player tab order
- Automatic lightweight playlist/validation refresh after audio metadata edits
- Original / master / delivery media workflow for safer repair and publish handling
- Social sharing metadata and web manifest generation

### Security & Privacy
- Session-based authentication with admin vs listener role separation on admin surfaces
- Client-side session watchdog on admin and player surfaces redirects expired sessions back to login with a clear message
- HTTPS enforcement and CSRF protection on selected mutation endpoints
- Password hashing migration to modern algorithms is planned; see `docs/SECURITY-AUDIT.md`

### Performance
- Fast page loads and player initialization
- Optimized for FLAC, MP3, and WAV source files

---

## Planned (see ROADMAP.md for timing)

These are **directional** — betatesters should check [ROADMAP.md](ROADMAP.md) → **Beta tester expectations** and [USE-CASES.md](USE-CASES.md) for shipped vs coming, matched to Vanilla / Twisted Chronicles / HITZ installs.

### v0.8 beta (active)

**Shipped in v0.8 so far:** package updater; block-based Pages editor; unified Content editors with pool/result UX; upload-time background delivery; delivery-ready pool gates; platform `data/` containers (assets, releases, playlists, galleries, themes).

**v0.8.3 (next):** invisible maintenance (config auto-repair, content preparation inside Publish), backup/export, playlist `kind` fix, Release editor, container marketing metadata, Content editor header UX parity.

**Still in progress (implementation only — policy complete):**
- Asset registry (`ast_{ULID}`) and `data/` containers for releases, playlists, galleries, themes
- Multiple playlist and gallery libraries in admin
- Playlist selector in the player **Playlists** tab (dropdown, title buttons, or cover flow) follows the **Base brand** `player.playlist_selector`; default = pinned `install.pointers.default_playlist_id` when still public, else latest public catalog playlist by `publish_date`
- Playlist **package type** (`single` / `ep` / `album` / `show` / `podcast` / `live` / `compilation` / `other`) and **play order** (`stored` / `reverse`); shows and podcasts default to newest-first playback while the admin edit list stays append-at-bottom
- Path deep links: `/play/{playlist}/{release-slug}/{track-slug}` and `/pages/{page-id}`
- Gallery **module blocks** on pages (grid, carousel, parallax, etc.) — dedicated Gallery player tab already removed
- Release-contextual player page tabs (Player editor globals + pages associated to the playing track’s release)
- Per-track Lyrics ↔ Notes nav label (same text field; optional Notes label default Tracklist — see [USE-CASES.md](USE-CASES.md) HITZ)
- Release locking; playlist reorder no longer mutates master tags
- Playback/delivery architecture for scale (protected media path, PWA cache contract)
- Stable **definitions** for access tiers, login/FAQ/shared-link flow, and Chromecast/cast architecture

Platform and policy docs: [PLATFORM-MODEL.md](PLATFORM-MODEL.md), [USE-CASES.md](USE-CASES.md), [ACCESS-MODEL.md](ACCESS-MODEL.md), [DELIVERY-ARCHITECTURE.md](DELIVERY-ARCHITECTURE.md), [PORTABILITY.md](PORTABILITY.md)

### v0.9
- **Portable release packages (PRP / `.prp`)** — move one finished campaign (masters, brand, playlists, galleries, pages, registry subset) between installs; setup imports `bandPromo-demo.prp` ([PORTABILITY.md](PORTABILITY.md))
- Catalogue **delete release**: Entire campaign (default) removes owned containers + unreferenced media; Release only keeps Files media. Shared duplicate media is retained on purge.
- **Implement** access tiers: admin/dev, VIP pre-access, registered fan, anonymous (released-only)
- Login page with **restricted anonymous entry**; shared URLs → login + FAQ
- Chromecast / cast send on the new delivery stack

### v1+
- Fan credits and engagement rewards
- News module with timed release and social push
- Fanboard, feeds, and richer module blocks

---

For full milestone structure and beta expectations, see [ROADMAP.md](ROADMAP.md) and [TODO.md](TODO.md).

For a practical guide to promoting your site without relying on paid social ads, see [MARKETING-STRATEGY.md](MARKETING-STRATEGY.md).
