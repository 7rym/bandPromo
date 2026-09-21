# Session Handoff

## Resume point

Session closed after documenting admin UI lessons (opaque sticky, flat field grids, modal single-scroller, Markdown hint placement, confirm z-index, media picker `campaign` param) in ADMIN-UI + AGENTS + FEATURES.

**Next (optional):** Files pool compactness debt; smoke save featured/remix round-trip; player display of featured/remix (not yet — primary `artist` only in playlist payloads).

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

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here.
