# bandPromo Features

bandPromo is a modern, self-hosted platform for private music releases and fan engagement. It is designed for artists and micro-labels who want full control over their content, audience, and analytics.

## Key Features

### Easy Setup
- Browser-based setup wizard after install/bootstrap
- Automatic creation of required folders and initial configuration
- Friendly license/operator-responsibility acknowledgment during setup
- First admin account creation with seeded demo content for first-run verification
- Setup checklist while installation is incomplete; switches to a **Dashboard** once all checks pass

### Admin Dashboard
- User-friendly admin panel for managing users, files, and site content
- **Notifications** operator inbox for **live** work only: media preparation, Site update, publish follow-ups, validation. The Welcome setup checklist stays on the Welcome page and is not mirrored into the bell. Hot paths stay read-only (lite by default; no catalog repair / materialize from the inbox).
- Quick actions on the completed-install dashboard (Analytics, Files, Content, live preview, Documentation)
- Built-in analytics for playback and user behavior (SQLite activity store at `data/analytics/events.sqlite`)
- Guided **Settings** forms (Basics, Support, Sharing) instead of raw JSON editing *(site shell text/SEO/provider-neutral in-flow support link; brand identity and shell media live in Content → Branding)*
- Content → Branding editor: pool, colors/typography tokens; **Readability** (backdrop dim + panel blur); **Shell media** slots (logo, poster, still/living backgrounds, welcome/logged-in **Sound effects**) use the shared media picker (same ✎ flow as covers). Accent alpha CSS vars are derived from Primary/Secondary automatically. Main/heading family stacks apply at runtime and are syntax-validated; responsive sizes and spacing remain platform-owned. Upload under Files → Brand assets / Visual / Sound effects. Live preview shows tokens plus shell chrome (logo/backdrop). Brand narrative fields (`mood` / `keywords` / `tone_notes`) stay in storage for future premade themes / AI helpers but are hidden from the editor for now. Duplicating a brand clones shell media; welcome/logged-in audio clones into Sound effects. Saving the **base** brand syncs shell paths into `web-config.json`. Old `?tab=settings&ctab=theme` redirects here.
- Player **brand shell override**: selected playlist’s release brand drives CSS tokens **and** logo / still / living backgrounds; login shell + Welcome/Logged-in SFX stay on install Base; system scrim over busy backgrounds
- **Block-based Pages editor** (v0.8 beta): Text, Picture, and List blocks; rich formatting; fraction picture widths and Flow placement; live player-styled preview; delete confirmations for pages and blocks
- Page registry in `data/pages/registry.json`: operators can add, rename, and remove optional pages; **FAQ remains system-owned and required** for login info / shared-link context (not part of PRPs)
- Content → **Playlist**, **Gallery**, **Pages**, and **Player layout** share one pool/result editor pattern: **Available content** pool on the left, active order/layout on the right, multi-select drag-and-drop, demo filter on media pools only, and amber **Save** / green **Saved** header controls (button roles: [ADMIN-UI.md](ADMIN-UI.md))
- Content → **Playlist** and **Gallery** management (multiple libraries in admin; player playlist selector when two or more catalog playlists are public; pin a default playlist; package type + play order)
- **Player** loads playlist data from prebuilt static payloads in `data/playlists/{id}.json` (`tracks`, `brand_styles`, `delivery_summary`). Publish writes these on full rebuild; **playlist save also republishes that playlist’s payload** (plus missing audio delivery) so add-track loops do not need Rebuild all deliverables. Track order in the payload follows `play_order` (`stored` or `reverse`).
- Player **page tabs** (Bio, Gallery-as-page, custom pages) are **site-wide** today via Content → Player; **target** also appends pages associated to the current track’s release ([USE-CASES.md](USE-CASES.md)). Tabs ship as empty shells; the active tab hydrates on DOMContentLoaded, others on first open — so gallery stills/posters do not contend with first paint. Gallery video files still load only in the lightbox.
- **Registry-first admin**: Files lists, playlist/release editors, and track detail read stored indexes only — `data/assets/registry.json` display fields, published playlist tracks, and the **media files index** in `data/media-library-state.json` (`files`) for size/mtime/delivery listing. No DirectoryIterator, filesize, or tag parse on GET. Index/registry updates only on upload, delete, tag save, delivery jobs, Publish, and container membership saves.
- Content → **Player layout**: **Playlists** and **Lyrics** always on; optional global page tabs; **Still / Living shell background** switch for the player surface (Living paints still first, then attaches the MP4 after load/idle); **Dropdown / Buttons / Cover flow** playlist selector when multiple playlists are available. Dedicated Gallery player tab is **removed** — use page gallery blocks.
- Files panels: **Audio | Visual | Sound effects | Brand assets**. Visual and Brand assets use thumbnail-first pools; Sound effects holds brand UI clips under `media/sfx/original/` (registry `kind=sfx`, role `sfx` only — Branding slots choose usage). Visual covers legacy `media/img|photo|video` intake with shared `ast_{ULID}` registry and delivery variants. **Brand assets** keeps `media/special/` for legacy branding visuals until Phase 3 fold (shell audio belongs in Sound effects).
- **Planned (remainders):** release-contextual player page tabs; content AI wizards; visual delivery polish — see [TODO.md](TODO.md), [PLATFORM-MODEL.md](PLATFORM-MODEL.md), [USE-CASES.md](USE-CASES.md)
- Player **text panel role**: per-track Lyrics ↔ Notes (same Lyrics storage); Notes optional tab label defaults to Tracklist
- Files list header row aligned with file items: master select-all checkbox, compact filter dropdowns (`All` / usage filters plus `User files` / `Include demo`), and labeled **Upload**, **Download**, and **Delete** bulk actions
- Per-row selection with shift-range support, ZIP bulk download, and reference-aware delete warnings
- Audio quick-edit for common tag fields plus full editor for lyrics, description, and cover work
- Cover-art badges and compact in-use/orphan indicators on illustrations, photos, and video
- Operator-facing validation actions and file-level metadata health badges for faster repair workflows
- Upload-time **background delivery automation**: audio, image, and video derivatives prepare automatically after upload; Content pools list **delivery-ready** assets only; progress and failures surface in **Notifications**
- Build actions under **System → Deliverables**: **Rebuild all deliverables**
- **System → Security**: install host-protection sanity check (managed `.htaccess` / `.user.ini` vs templates) with optional preview/repair
- Admin-panel **package updater** for hosted operators (immutable release packages)
- Separate admin audit trail under **System → Audit**
- Built-in documentation browser with operator/developer doc separation
- Listener accounts can use the player but cannot open admin surfaces

### Media Player
- High-quality audio playback with seek/next/previous navigation
- **Playlists** and **Lyrics** tabs (core player shell — not page-embedded)
- **Markdown** in player lyrics and track descriptions (rendered at display; masters unchanged)
- **Animated track covers (living cover)** — optional silent looping video on the main cover card while audio plays, when assigned and delivery MP4 exists (independent of shell Still/Living background; still cover when paused/idle or reduced motion). Publish fills empty master tags from the asset registry when needed.
- Cover delivery: **720px optimal** for the player card / lightbox; **100px thumb** for playlist rows and cover-flow (Publish / optimizeMedia)
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
- **Planned (v0.8 management slice remainders):** Brand-assets visual fold into Visual originals; living-cover `ast_*` — see [MEDIA-HANDLING.md](MEDIA-HANDLING.md), [PLATFORM-MODEL.md](PLATFORM-MODEL.md)
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
- Playlist selector in the player **Playlists** tab (dropdown, title buttons, or cover flow); default = pinned `install.pointers.default_playlist_id` when still public, else latest public catalog playlist by `publish_date`
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
