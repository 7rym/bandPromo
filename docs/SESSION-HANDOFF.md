# Session Handoff

## Resume point

Catalogue Live preview cover bleed fixed: switching campaigns no longer reuses the previous artwork (`pendingCampaignCoverPreviewUrl` matched every `card.jpg`). Data was never cross-written — each campaign’s `poster_asset_id` stayed distinct.

Files → Visual **Assign** still registers uncatalogued orphans before setting catalogue home.

Admin accent remains **deep steel blue** (`--accent: #3a6a94`, hover `#2f5780`).

**Next (optional):** Files pool compactness debt; smoke save featured/remix round-trip; player display of featured/remix (not yet). Checkpoint/publish when ready.

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

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here.
