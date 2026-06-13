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
- **Needs attention** operator inbox modal with plain-language tasks (header bell is the primary entry point on completed installs)
- Quick actions on the completed-install dashboard (Analytics, Files, Content, Update site, live preview)
- Built-in analytics for playback and user behavior
- Guided Config forms (Basics, Theme, Support, Sharing) instead of raw JSON editing
- Pages editor for public text pages such as Bio and FAQ, with rich text tools and safe server-side sanitization
- Playlist and gallery management, including drag-placeholder reorder UX in both editors
- Files panels for Audio, Photos, Video, Illustrations, and install-specific **Theme** assets (distinct from Config -> Theme presentation settings)
- Files list header row aligned with file items: master select-all checkbox, compact filter dropdowns (`All` / usage filters plus `User files` / `Include demo`), and labeled **Upload**, **Download**, and **Delete** bulk actions
- Per-row selection with shift-range support, ZIP bulk download, and reference-aware delete warnings
- Audio quick-edit for common tag fields plus full editor for lyrics, description, and cover work
- Cover-art badges and compact in-use/orphan indicators on illustrations, photos, and video (without verbose per-row reference text)
- Operator-facing validation actions and file-level metadata health badges for faster repair workflows
- Build actions named for operators: **Update the live site** and **Refresh photos & artwork**
- Separate admin audit trail for management actions
- Built-in documentation browser with operator/developer doc separation
- Listener accounts can use the player but cannot open admin surfaces

### Media Player
- High-quality audio playback with seek/next/previous navigation
- Full lyrics display and enhanced playlist browsing
- Responsive design for mobile, tablet, and desktop
- Compact two-column landscape layout for installed/mobile PWA playback, including safer top-edge spacing in standalone mode
- Artwork and lightbox support
- Post-login splash shows the install logo with **Preparing your experience…** before entering the player

### Build & Delivery
- Automated build pipeline for optimized audio and images
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

For future features, limitations, and roadmap, see [ROADMAP.md](ROADMAP.md) and [TODO.md](TODO.md).