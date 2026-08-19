# Session Handoff

## Resume point

Admin editor refactor — Phase 2 migration.

## What's done

### Phase 1 — Terminology rename (committed, not pushed)

- **release→campaign**: ~14 PHP files renamed, ~96 function defs + ~350 call sites, all JS/CSS/HTML IDs updated. Backwards-compat URL aliases in `admin.php`.
- **theme→brand**: ~8 PHP files renamed, ~65 function defs + call sites, all JS/CSS renamed.
- **UK English**: `customize`→`customise` in 3 files.
- Fixed pre-existing duplicate `bandpromo_brand_active_id` function.
- Exclusions preserved: `release-package.php` (app updates), `release_date` fields, `theme-preview-*` CSS (preview rendering), config keys like `release.identity.*`.

### Phase 2 — Shared JS modules (created, not yet wired)

Four new files under `biblioteca/`:
- `editor-lifecycle.js` — `window.bandpromoEditorLifecycle.create()` factory for pool/edit view toggling, URL sync, close gating
- `editor-drag-reorder.js` — `window.bandpromoDragReorder.bind()` for drag-and-drop list reorder
- `editor-range-selection.js` — `window.bandpromoRangeSelection.create()` for shift-click/ctrl-click multi-select
- `editor-registry-list.js` — `window.bandpromoRegistryList.render()` + `.row()` + `.actionButton()` for pool list rendering

Script tags added to `admin.php` inside the `<?php if ($tab === 'content'): ?>` block.

## What's next

### Phase 2 — Migration (editors → shared modules)

Wire each editor to use the shared modules, then delete the duplicated local code. Order:
1. **Gallery editor** (admin.js) — closest to the shared pattern, good proof of concept
2. **Playlist editor** (admin.js) — very similar to gallery
3. **Campaign editor** (campaign-editor.js) — has extra tab-link patching and association sections
4. **Pages editor** (page-editor.js) — simplest lifecycle, no range selection
5. **Brand editor** (brand-editor.js) — has saveUi integration

For each migration: replace local lifecycle, drag-reorder, range-selection, and pool-list rendering with shared module calls, smoke test, then delete the old code.

### Phase 3 — CSS rename

`playlist-editor-row` → `editor-row`, `player-layout-*` → `split-editor*`, `page-pool-*` → `registry-*`. Remove dead classes.

### Phase 4 — Unify save UX

Add `bandpromoContentSaveUi` to Catalogue, adopt Pages-style unsaved modal everywhere, standardise save feedback.

## Plan document

Full plan: `docs/ADMIN-EDITOR-REFACTOR.md`
