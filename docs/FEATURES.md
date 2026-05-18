# bandPromo Features

bandPromo is a modern, self-hosted platform for private music releases and fan engagement. It is designed for artists and micro-labels who want full control over their content, audience, and analytics.

## Key Features

### Easy Setup
- Browser-based setup wizard after install/bootstrap
- Automatic creation of required folders and initial configuration
- Friendly license/operator-responsibility acknowledgment during setup
- First admin account creation with seeded demo content for first-run verification
- Admin-first post-setup flow with a clear next-step checklist

### Admin Dashboard
- User-friendly admin panel for managing users, files, and site content
- Built-in analytics for playback and user behavior
- Pages editor for public text pages such as Bio and FAQ, with rich text tools and safe server-side sanitization
- Playlist and gallery management
- Audio-master editing in Files -> Audio for common track metadata, lyrics, release date, title/version handling, and track-cover selection
- Operator-facing validation actions and file-level metadata health badges for faster repair workflows
- Separate admin audit trail for management actions
- Built-in documentation browser with operator/developer doc separation

### Media Player
- High-quality audio playback with seek/next/previous navigation
- Full lyrics display and enhanced playlist browsing
- Responsive design for mobile, tablet, and desktop
- Compact two-column landscape layout for installed/mobile PWA playback, including safer top-edge spacing in standalone mode
- Artwork and lightbox support

### Build & Delivery
- Automated build pipeline for optimized audio and images
- Automatic lightweight playlist/validation refresh after audio metadata edits
- Original / master / delivery media workflow for safer repair and publish handling
- Social sharing metadata and web manifest generation

### Security & Privacy
- Session-based authentication with role separation
- HTTPS enforcement and CSRF protection
- All passwords stored as secure hashes

### Performance
- Fast page loads and player initialization
- Optimized for both FLAC and MP3 source files

---

For future features, limitations, and roadmap, see [ROADMAP.md](ROADMAP.md) and [TODO.md](TODO.md).