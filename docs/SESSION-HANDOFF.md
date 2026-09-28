# Session Handoff

## Resume point

**Published:** **v0.8.97 build 593** (`v0.8.97-build-593`).

**Published:** **v0.8.97 build 594** (`v0.8.97-build-594`).

**Done (in 594):** Campaign strip opaque/tight; multi-campaign hides Content header logo; Catalogue **Chip artwork**; campaigns/playlists sort newest-first by date; Pages prose full-row wrap + picture-body panel fill.

**Parked:** Branding Live preview full remirror — still wait until Content rail is further along, then mirror Shell|Player|Content properly.

**Next:** Rest of Content surface → Branding preview parity.

### Policy locked (do not reopen)

- Sticky chrome over scrolling content: **opaque** `--admin-sticky-chrome-bg`; in-flow headers may keep `--admin-slate-wash`
- Page builder: add-block sticky under breadcrumb (`--page-builder-sticky-top`); do **not** nest rich-text sticky under it
- Form field grids: flat peer rows (not multi-field stacks in one cell); BPM/Key narrow, Genre flex
- Modal long textareas: autosize; modal body is the only scroller
- Markdown hints trail the label / toggler on the same line
- Files mutating modals: solid green Save; quiet Audio crumb; `#adminConfirmModal` above editors
- Audio crumb compound title is read-only by design
- Audio Details field order: artists → title/version → genre/BPM/key → release date
- Media picker campaign filter uses param `campaign` (not `release`)
- Dashboard order: Site update → demo suggest → Quick actions → What to do next
- Sub-tab help: Files canon — accent bar + ⓘ + `--admin-help-*` box with `<ul>`; no hardcoded accent hex/rgba in rules
- Admin `--accent` is deep steel blue `#3a6a94` (not coral, not fluorescent sky)
- Visual Assign may register-on-assign for orphan originals lacking `asset_id`
- Catalogue/playlist cover pending preview must match by asset id or full path — never shared basenames like `card.jpg`
- Upload toasts stay brief; do not dump Site health task lists into them
- Background video/audio prep: toast on ready/failed; do not inbox running/done; auto-delivery tasks are not a Notifications nag
- Visual upload Campaign field must post `campaign_id` like Audio
- **From brand** / library detach clears shell slots on that brand; do not re-block “clear Branding first”
- **From brand** dropdown = membership only (never every brand on the install); offer “Every brand in this selection” when the selection spans 2+ brands
- Pool **In brand** chip = library membership (`brand_ids`), separate from In use / Unused assignment
- Video MKV remux maps video + optional audio only (drop data/timecode tracks)
- ffmpeg capture must decode stderr as UTF-8 with replace (Windows charmap)
- Sticky playlist-scan Notifications heal on bell (full) or Site health Check start
- Archival original discard lives under **System → Status → Storage** only (not Files); Site health janitor also reclaims leftover intake + stray media ZIPs on Review → Apply
- Durable media model is **master + delivery**; intake is ephemeral (`temp/media-intake/`), discarded when the master succeeds
- Files pools/pickers/downloads are **masters only** — no Original|Master list toggle; originals are disposable intake
- Product resolve never falls back to archival `original/` or legacy `img`/`photo`/`video`/`special` when the master is missing (fail loud / empty)
- Never invent `original_filename` from `master_filename` on register/normalize/Treat
- VERSION **session** number bumps only when the operator explicitly starts a session (`session-start.ps1 -BumpSession` / `/bandpromo-session-start`); agent resume must not bump
- PCF/PBF import auto-starts Site health Treat (delivery-focused selection; no silent dedupe delete / Ready-archive prune); Jobs show **Open Status**
- User shell isolated from admin chrome; Brand not Theme; casing by layer (`.cursor/rules/identifier-casing.mdc`)
- Branding preview waits until Player + Content public surfaces are correct
- Cast (v0.9+): `#mediaplayer` = sender; speakers-only first-class; display CAF receiver separate — [DELIVERY-ARCHITECTURE.md](DELIVERY-ARCHITECTURE.md) Sender vs receiver
- Campaign navigator: Content header logo only when one campaign; multi-campaign strip opaque + larger selected; campaign `navigator_logo_asset_id` ≠ brand shell logo

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here.
