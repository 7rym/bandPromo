# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased] - 2026-04-30

### Documentation
- **2026-04-30 — Updated AGENTS.md with desktop.ini guidance**
  - Expanded documentation on desktop.ini files (Windows + Google Drive artifacts).
  - Noted that they corrupt `.git/refs/` if accidentally committed and require cleanup if they appear in remote refs.
  - Added desktop.ini issues to Common Pitfalls section.

### Changed
- **2026-04-30 — Admin: new Content primary tab**
  - Added a dedicated "📄 Content" primary tab between Files and Config in the admin panel.
  - Moved Playlist, Gallery, and Bio management out of Config into Content sub-tabs (`?tab=content&cntab=playlist|gallery|bio`).
  - Config tab now contains only Basics and Sharing sub-tabs, reducing clutter.
  - Content tab uses its own URL parameter `cntab` to avoid collision with Config's `ctab`.

### Fixed
- **2026-04-30 — CSS fixes for small screens (≤430px)**
  - Added `display: flex; flex-direction: column; align-items: center` to `#mediaplayer` in the `max-width: 430px` breakpoint so player content centers correctly on small phones.
  - Hidden `.reflection` at `max-width: 430px` to reduce visual clutter on small screens.
  - Added `align-self: center` to `.playlist-track-cover` so cover art aligns vertically center within playlist items.
  - Fixed `.vscode/tasks.json` dev server task: wrapped `${workspaceFolder}` path in single quotes in `Set-Location` to handle spaces in the path correctly on Windows PowerShell.

## [Unreleased] - 2026-04-20

### Added
- **2026-04-30 — Admin playlist ordering**
  - New drag-and-drop playlist editor in Admin → Config → Playlist tab.
  - Tracks can be reordered by dragging; numbers update live.
  - Save button posts to new `biblioteca/save-playlist-order.php` which rewrites `play/playlist.json` immediately (no rebuild needed) and persists `data/playlist-order.json`.
  - `scripts/makePlaylists.py` now reads `data/playlist-order.json` on build: known tracks appear in saved order, newly added tracks are appended at the end. Build also writes/updates the order file to keep it consistent.

### Changed
- **2026-04-30 — Player UI terminology and layout refactoring**
  - Renamed `#main-content` → `#content-container` and `.lyrics-toggle` → `.content-toggle` across PHP, JS, and CSS.
  - Elevated Gallery to a top-level content tab alongside Lyrics, Playlist, and Bio.
  - Moved band logo out of the Bio section into a new `.content-logo` area displayed above the tab bar.
  - Simplified Bio to a static page: removed inner "The Band" / "The Visuals" sub-tabs and all `toggleBioTab()` logic.
  - Gallery tab now owns `#visualsGallery` and triggers `loadVisualsGallery()` on first activation.
  - Removed `.bio-header`, `.bio-logo`, `.bio-tabs`, `.bio-tab-btn`, `.bio-tab-content` CSS; added `.content-logo`, `.content-logo-img`, `.gallery-box` styles.

- **2026-04-30 12:56 local — Stability hardening and config cleanup**
  - Fixed login-page bootstrap failure caused by FAQ include hard-exiting when `data/faq.html` was missing.
  - Added same-origin speed-test fallback endpoint (`biblioteca/speed-test.php`) and improved login speed-test diagnostics.
  - Removed duplicate invalid service-worker registration path from `biblioteca/login.js`.
  - Added localhost canonicalization in `biblioteca/https.php`: loopback hosts now redirect to `http://localhost` (port/path/query preserved).
  - Hardened build preflight to seed required runtime files from templates.
  - Updated build JSON validation to accept both object and array roots (fixes `gallery.template.json` preflight failure).
  - Added repository `.editorconfig` UTF-8 defaults.
  - Removed deprecated `build.generate_lq` from template/default/current config and loader defaults.
  - Updated `docs/AGENTS.md` with UTF-8, English-only, and timestamped changelog-note conventions.
- **Documentation review for v0.7 build 185**
  - Updated roadmap build number to match `VERSION`.
  - Updated feature and metadata docs to reflect admin build-log metadata validation output.
  - Rewrote `SECURITY-AUDIT.md` from an old vulnerability list into a current-state audit with remaining findings.
  - Cleaned one non-English code comment in `scripts/makePlaylists.py`.
- **Refactor: naming cleanup** (Phases 1–5)
  - `scripts/makeLQ.py` → `scripts/optimizeMedia.py`
  - `LQ/` player directories → `optimal/` throughout; `media/audio/LQ/` → `media/audio/optimal/`; `media/img/LQ/` → `media/img/optimal/`
  - `scripts/makeConfig.py` → `scripts/makePlaylists.py`; output `play/config.json` → `play/playlist.json`
  - `pwa-debug.php` → `biblioteca/pwa-debug.php`
  - `web-config.example.json` → `biblioteca/web-config.example.json`
  - `generate_manifest()` inline function extracted to `scripts/makePWA.py`
  - All path references, exclusion lists, and docs updated to match

---

## [0.7] - 2026-04-19 (build 60)

### Added
- **Setup wizard** — full 4-step first-run wizard (account → site config → upload → build)
  - Auto-creates required directory structure on load
  - Step 1: Creates admin account (seeds `data/terces`)
  - Step 2: Site info form, writes `web-config.json`
  - Step 3: Media upload with chunked upload (2 MB/chunk, bypasses PHP limits)
  - Step 3: Detects previously uploaded files and offers to reuse them
  - Step 4: Triggers build pipeline with live log streaming
  - Redirects to admin panel when `data/.setup_complete` exists
- **Build pipeline** (`scripts/build.py`)
  - Sub-scripts (`makePlaylists.py`, `optimizeMedia.py`) stream output line-by-line via `Popen`
  - Build runs fully in background via `nohup` — no more gateway timeouts
  - Exit code written to log as `EXITCODE:N`; polling endpoint returns `success` bool
  - pip uses `--only-binary` to skip source compilation; falls back to importability check
  - `PYTHONIOENCODING=utf-8:replace` propagated to sub-scripts
- **UTF-8 stdout redirect** in all three build scripts for Python 3.6 ASCII locale
- `biblioteca/check-uploads.php` — lists existing HQ media files (no auth required)
- `VERSION` file introduced; format: `MAJOR.MINOR+BUILD`

### Fixed
- `Pillow>=9.0.0` → `>=8.0.0`, `python-dotenv>=1.0.0` → `>=0.19.0` for Python 3.6
- `Image.Resampling.LANCZOS` fallback for Pillow < 9.1
- `build.php` and `get-build-log.php` accept setup session (`$_SESSION['user']`)
- `setup-init.php` handles page-refresh by re-authenticating existing user

## [Unreleased]

### Security
- **Phase 5: Rate limiting protection** [PHASE5]
  - Created biblioteca/rate-limit.php - Rate limiting system
    - Per-user: Max 5 quiz submissions per minute
    - Per-IP: Max 100 total requests per minute
    - Uses session-based request tracking with 60-second rolling windows
    - Functions: check_submission_rate_limit(), check_ip_rate_limit()
    - Automatic cleanup of expired request timestamps
    - Handles proxied requests (Cloudflare, X-Forwarded-For)
  - Integrated rate limiting into save-score.php
    - Rate checks happen after CSRF validation
    - Returns 429 Too Many Requests when limits exceeded
    - Response includes retry_after and reset_at timestamps
    - Per-user limit prevents submission spam
    - Per-IP limit prevents brute force attacks from single source
  - Prevents: Quiz submission spam, brute force score guessing, DDoS

- **Phase 4: Completion verification & server-side scoring** [PHASE4]
  - Created biblioteca/quiz-validator.php - Server-side answer validation
    - calculate_quiz_score($quizType, $userAnswers) - Calculates score from answers
    - verify_score_integrity($quizType, $userAnswers, $submittedScore) - Validates integrity
    - Loads quiz data server-side to verify all user answers
    - Prevents client-side score tampering
  - Updated quiz.js to collect and transmit user answers
    - quizState.userAnswers[] - Tracks all answered questions
    - selectAnswer() now stores answer details (question index, user answer, question ID)
    - saveScore() now includes full answers array in POST data
    - Example payload: { quizType, score, answers: [{questionIndex, answer, questionId}], csrf_token }
  - Integrated validation into save-score.php
    - If answers provided, server-side calculates expected score
    - Compares submitted score vs calculated score
    - Returns 400 Bad Request if mismatch detected (score tampering)
    - Prevents: Fake score submissions, modified answers, impossible high scores
  - Prevents: All forms of answer/score tampering

- **Phase 3: CSRF token protection** [542a75f]
  - Created biblioteca/csrf.php - CSRF token management helper
    - generate_csrf_token() - Creates and stores token in session (1 hour validity)
    - validate_csrf_token() - Validates token from request data
    - Tokens use cryptographically secure random_bytes (32 bytes)
    - Hash comparison with hash_equals() to prevent timing attacks
  - Added CSRF token validation to /biblioteca/save-score.php
    - Rejects requests without valid token → 403 Forbidden
    - Prevents cross-site request forgery attacks
  - Updated index.php to generate and expose CSRF token
    - Token made available as JavaScript variable
    - Stored in sessionStorage for use on other pages
  - Updated quiz.js to include CSRF token with score submissions
    - Automatically reads token from sessionStorage
    - Sends with every scoring request
  - Prevents: Cross-site request forgery

- **Phase 2.5: PHP API self-protection** [051e198]
  - Added HTTP Accept header validation to all API endpoints
  - `/biblioteca/quiz.php` now blocks direct browser requests → 403 Forbidden
  - `/biblioteca/save-score.php` now blocks direct browser requests → 403 Forbidden
  - `/biblioteca/get-highscores.php` already has header validation
  - `/biblioteca/get-gallery-items.php` already has header validation
  - Detection logic: If Accept header has text/html WITHOUT application/json → browser request → reject
  - Legitimate API calls (accept: application/json) still work → 200 OK
  - Prevents accidental data exposure if PHP files are accessed directly

- **Phase 2: Score validation & integrity checks** [57abe79]
  - Added score validation in /biblioteca/save-score.php
    - Loads quiz structure to calculate maximum possible score
    - Rejects scores > maximum quiz score (prevents impossible high scores)
    - Rejects negative scores (score >= 0)
    - Prevents leaderboard manipulation via fake score submissions
  - Scores now validated against actual quiz content
    - Each question = 1 point
    - Max score = number of questions in quiz
    - Example: 10 questions = max score 10
  - Fake score submissions now return 400 Bad Request with error details

- **Phase 1: Closed data exposure vulnerabilities** [472ec46]
  - Removed quiz answer exposure from /biblioteca/quiz.php API
    - Historical note: the current `quiz.php` implementation again includes `correct` for client-side feedback while keeping server-side scoring authoritative.
    - If quiz-answer secrecy matters, remove `correct` from the API response again and move feedback behind server validation.
  - Created /biblioteca/get-highscores.php - Secure leaderboard API
    - Requires session authentication
    - Blocks direct browser access (403 Forbidden)
    - Allows JavaScript API calls (200 OK)
    - Replaces deprecated get-top-scores.php (now redirects)
  - Created /biblioteca/get-gallery-items.php - Secure gallery API
    - Same security model as get-highscores.php
    - Protects gallery data from direct access
  - Updated .htaccess to route all data files through secure APIs
    - quizbase-*.json → /biblioteca/quiz.php
    - highscores.json → /biblioteca/get-highscores.php
    - gallery.json → /biblioteca/get-gallery-items.php
    - web-config.json → /biblioteca/get-config.php
  - SECURITY-AUDIT.md created with full vulnerability analysis

- **Protected web-config.json from direct browser access** [ec4d09e]
  - Created biblioteca/get-config.php - Secure configuration API controller
  - HTTP header validation: Blocks direct browser requests (Accept: text/html)
  - Allows legitimate JavaScript/API requests (XMLHttpRequest, application/json)
  - Returns appropriate HTTP status codes (403 for browser, 200 for API)
  - .htaccess redirects direct web-config.json access through security controller
  - Prevents accidental exposure of sensitive configuration data

### Added
- **Info lightbox extraction** - Moved login page info/about content to config-driven biblioteca/info-display.php
  - Replaces hardcoded HTML with configuration-based sections
  - Supports dynamic Q&A format with "heading" + "content" fields
  - Integrates with web-config.json for easy customization per project
- **--media-only flag** to deploy.py for uploading only media assets without code changes
- **Auto-play next song** when current track ends naturally
- **Null checks** in login.js functions to prevent errors on non-login pages
- **Dynamic gallery system** with JSON-based image management (biblioteca/gallery.js, gallery.php, gallery.json)
- **Error detection** on login to prevent audio playback on failed authentication
- **Comprehensive favicon setup** with all browser/device sizes (16x16, 32x32, 96x96, SVG, ICO) [7e520d6]
- **Apple mobile web app support** with home screen icon, app title, and status bar styling [7e520d6]
- **PWA manifest enhancements** with maskable icon support for Android 12+ adaptive icons [7e520d6]
- **web-config.json system** for flexible multi-project deployment [9dc25f6]
  - Site configuration (name, description, URL, author, language)
  - Branding settings (theme colors, backgrounds, accents)
  - Social media configuration (Twitter, Facebook, Instagram handles)
  - Content categories and keywords
  - Build options (LQ generation, speed test threshold)
- **biblioteca/config-loader.php** for centralized configuration management [9dc25f6]
  - Loads web-config.json with graceful defaults
  - Provides get_config() helper for dot notation access
- **biblioteca/share-tools.php** for centralized meta tag generation [9dc25f6]
  - generate_og_tags() - Creates Open Graph meta tags
  - generate_twitter_tags() - Creates Twitter Card tags
  - generate_standard_meta_tags() - Common meta tags
- **Automatic manifest generation** in build.py [9dc25f6]
  - Generates site.webmanifest from web-config.json
  - Populates PWA manifest with site configuration
  - Replaces hardcoded values

### Changed
- **Background media selection**: Changed from quality button choice to actual speedtest result [ef2a602]
  - Background image shown only for slow connections (< 5 Mbps 🐌)
  - Background video shown for faster connections (≥5 Mbps)
  - Makes background choice independent of manual quality selection
- **Unified lightbox system**: Consolidated three separate lightbox functions (openLightbox, openPromoLightbox, openGalleryImage) into single openLightbox() function for all image types
- **Speed test timing**: Changed from DOMContentLoaded to window.load event for accurate connection measurements
- **Pulse guide removal**: Now listens to audio player 'play' event instead of UI clicks for more reliable triggering
- **Playlist highlighting**: Now updates correctly when tracks change via triggerSongChange() animation
- **Meta tag generation**: Moved from hardcoded HTML to dynamic generation via share-tools.php [9dc25f6]
  - All index.php files now use generate_og_tags() and generate_twitter_tags()
  - Easier to customize per page/track
- **Icon organization**: Reorganized media/icons/ folder structure [9dc25f6]
  - Consolidated all icon files into media/icons/
  - Cleaner media folder structure

### Removed
- **Duplicate speed test** from player.js (testDownloadSpeed function removed)
- **Hardcoded gallery data** from player.js (now loaded from JSON)
- **All debug console.log statements** - kept only console.error for actual errors
- **Old band bio content** from index.php files (extracted to biblioteca/bio.php)
- **Hardcoded meta tags** from index.php files (replaced with config-driven generation) [9dc25f6]

### Fixed
- Speed test running before page fully loaded (media file not ready)
- **Keywords handling in share-tools.php** - Now supports both string and array formats [d64688b]
  - web-config.json uses strings for keywords, system now handles both transparently
  - Prevents PHP implode() error when keywords is a string
- Lightbox not working for gallery images (was using old CSS approach)
- Pulse animation not stopping when music started from hardware controls
- Playlist item not highlighting when song auto-advances
- Console errors on player page from login.js trying to access missing DOM elements
- Audio playback starting before authentication completed on login failure

### Technical Improvements
- Extracted static content to reusable includes (bio.php)
- Created modular gallery system matching quiz pattern
- Added proper authentication checks in gallery.php
- Improved code organization and reduced duplication
- Better separation of concerns (content vs logic)

---

## How to Update This File

When making changes, add them to the [Unreleased] section in the appropriate category:
- **Added**: New features or functionality
- **Changed**: Changes to existing functionality
- **Removed**: Removed features or code
- **Fixed**: Bug fixes
- **Technical Improvements**: Refactoring, optimization, code quality

When releasing a version, change [Unreleased] to the version number with date, e.g. [1.0.0] - 2026-03-31
