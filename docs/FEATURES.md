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
- **Notifications** operator inbox modal with plain-language tasks (header bell is the primary entry point on completed installs)
- Quick actions on the completed-install dashboard (Analytics, Files, Content, live preview, Documentation)
- Built-in analytics for playback and user behavior (SQLite activity store at `data/analytics/events.sqlite`)
- Guided **Settings** forms (Basics, Theme, Support, Sharing) instead of raw JSON editing *(site shell settings; brand identity lives in Content → Brands — legacy Settings “Theme” tab rename still open)*
- **Content → Themes** editor *(transitional — becomes **Content → Brands**: pool, tokens, mood narrative, live preview, duplicate/delete, Set active)*
- **Block-based Pages editor** (v0.8 beta): Text, Picture, and List blocks; rich formatting; fraction picture widths and Flow placement; live player-styled preview; delete confirmations for pages and blocks
- Page registry in `data/pages/registry.json`: operators can add, rename, and remove optional pages; **FAQ remains required** for login info / shared-link context
- Content → **Playlist**, **Gallery**, **Pages**, and **Player layout** share one pool/result editor pattern: **Available content** pool on the left, active order/layout on the right, multi-select drag-and-drop, demo filter on media pools only, and amber **Save** / green **Saved** header controls
- Content → **Playlist** and **Gallery** management (multiple libraries in admin; player playlist selector when two or more **system** playlists exist)
- Content → **Player layout**: **Playlists** and **Lyrics** always on; static page tabs optional; **Gallery player tab is transitional** and will be replaced by gallery blocks on pages
- Files panels for Audio and legacy visual buckets (Photos, Video, Illustrations, Theme/`special`) — **planned:** single **Visual** pool with brand + role filters
- **Planned (v0.8 management slice):** **Brand** containers (replace Theme), release `brand_id` links (many releases per era), explicit role tags on visuals, **content AI wizards**, unified Visual pool — see [TODO.md](TODO.md), [PLATFORM-MODEL.md](PLATFORM-MODEL.md)
- Files list header row aligned with file items: master select-all checkbox, compact filter dropdowns (`All` / usage filters plus `User files` / `Include demo`), and labeled **Upload**, **Download**, and **Delete** bulk actions
- Per-row selection with shift-range support, ZIP bulk download, and reference-aware delete warnings
- Audio quick-edit for common tag fields plus full editor for lyrics, description, and cover work
- Cover-art badges and compact in-use/orphan indicators on illustrations, photos, and video
- Operator-facing validation actions and file-level metadata health badges for faster repair workflows
- Upload-time **background delivery automation**: audio, image, and video derivatives prepare automatically after upload; Content pools list **delivery-ready** assets only; progress and failures surface in **Notifications**
- Build actions under **System → Deliverables**: **Rebuild all deliverables**
- Admin-panel **package updater** for hosted operators (immutable release packages)
- Separate admin audit trail under **System → Audit**
- Built-in documentation browser with operator/developer doc separation
- Listener accounts can use the player but cannot open admin surfaces

### Media Player
- High-quality audio playback with seek/next/previous navigation
- **Playlists** and **Lyrics** tabs (core player shell — not page-embedded)
- Responsive design for mobile, tablet, and desktop
- Compact two-column landscape layout for installed/mobile PWA playback
- Artwork and lightbox support (including page images)
- Post-login splash shows the install logo with **Preparing your experience…** before entering the player

### Build & Delivery
- Automated build pipeline for optimized audio and images
- Upload-time background tasks for audio delivery, image delivery, and video delivery (`biblioteca/auto-build-tasks.php` plus focused Python runners)
- **Planned (v0.8 management slice):** unified **Visual** media pool (images + video, `ast_{ULID}` identity), brand filter, explicit role tags, context-sized delivery variants, format-by-content (preserve PNG/WebP alpha) — see [MEDIA-HANDLING.md](MEDIA-HANDLING.md), [PLATFORM-MODEL.md](PLATFORM-MODEL.md)
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

These are **directional** — betatesters should check `ROADMAP.md` → **Beta tester expectations** for what is shipped vs coming.

### v0.8 beta (active)

**Shipped in v0.8 so far:** package updater; block-based Pages editor; unified Content editors with pool/result UX; upload-time background delivery; delivery-ready pool gates; platform `data/` containers (assets, releases, playlists, galleries, themes).

**v0.8.3 (next):** invisible maintenance (config auto-repair, content preparation inside Publish), backup/export, playlist `kind` fix, Release editor, container marketing metadata, Content editor header UX parity.

**Still in progress (implementation only — policy complete):**
- Asset registry (`ast_{ULID}`) and `data/` containers for releases, playlists, galleries, themes
- Multiple playlist and gallery libraries in admin
- Playlist selector in the player **Playlists** tab; default = latest public system playlist by `publish_date`
- Path deep links: `/play/{playlist}/{release-slug}/{track-slug}` and `/pages/{page-id}`
- Gallery **module blocks** on pages (grid, carousel, parallax, etc.)
- Release locking; playlist reorder no longer mutates master tags
- Remove dedicated Gallery player tab when gallery blocks ship
- Playback/delivery architecture for scale (protected media path, PWA cache contract)
- Stable **definitions** for access tiers, login/FAQ/shared-link flow, and Chromecast/cast architecture

Platform and policy docs: [PLATFORM-MODEL.md](PLATFORM-MODEL.md), [ACCESS-MODEL.md](ACCESS-MODEL.md), [DELIVERY-ARCHITECTURE.md](DELIVERY-ARCHITECTURE.md), [PORTABILITY.md](PORTABILITY.md)

### v0.9
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
