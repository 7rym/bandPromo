# Admin Editor Refactor Plan

## Problem

Five Content tab editors (Catalogue, Playlists, Galleries, Pages, Branding) each independently implement the same lifecycle with slight variations, producing ~13,000 lines in `admin.js`, ~7,000 lines in `admin.css`, near-duplicate logic across editors, and stale naming that obscures what is shared vs editor-specific.

Additionally, two major terminology mismatches exist between internal code names and operator-facing names:

- **`release`** in code = **Campaign** for operators (~17 PHP files, ~130 JS identifiers, URL params, filenames)
- **`theme`** in code = **Brand** for operators (~11 PHP files, ~109 JS identifiers, URL params, filenames)

## Phased approach

This is too large for a single pass. Four phases, each independently shippable. Phase 1 (terminology) runs first so later phases use the correct names from the start.

### Phase 1 — Terminology rename (release to campaign, theme to brand)

Rename internal identifiers, filenames, URL parameters, and CSS classes to match operator-facing terminology. This avoids renaming things twice during later phases.

#### release to campaign

- **PHP filenames:** rename `release-storage.php` to `campaign-storage.php`, `release-editor.js` to `campaign-editor.js`, `manage-release.php` to `manage-campaign.php`, `save-release-tracks.php` to `save-campaign-tracks.php`, `save-release-associations.php` to `save-campaign-associations.php`, `get-releases.php` to `get-campaigns.php`, `get-release-preview.php` to `get-campaign-preview.php`, `get-release-preview-section.php` to `get-campaign-preview-section.php`, `get-release-associations.php` to `get-campaign-associations.php`, `duplicate-release-campaign.php` to `duplicate-campaign.php`, `release-ownership-helpers.php` to `campaign-ownership-helpers.php`, `release-campaign-package.php` to `campaign-package.php`, `release-package.php` to `campaign-package-export.php`, `export-release-package.php` to `export-campaign-package.php`, `import-release-package.php` to `import-campaign-package.php`, `download-release-package.php` to `download-campaign-package.php`
- **JS/PHP identifiers:** `releaseId` to `campaignId`, `selectedReleaseId` to `selectedCampaignId`, `releaseEntry` to `campaignEntry`, `releaseSettings*` to `campaignSettings*`, `releasePool*` to `campaignPool*`, `isEditing` stays (generic)
- **PHP function names:** `bandpromo_release_*` to `bandpromo_campaign_*` (~170 definitions + call sites across release-storage, release-package, release-ownership-helpers, release-campaign-package, import-release-package, content-autofix-helpers)
- **Action param values:** `action=create_release` to `action=create_campaign`, `action=delete_release` to `action=delete_campaign`, etc. in both PHP handlers and JS fetch calls
- **URL params:** `?release=` to `?campaign=`, `?cntab=release` to `?cntab=campaign`
- **CSS classes:** `release-editor-*` to `campaign-editor-*`, `release-pool-row` to `campaign-registry-row`, `release-preview-*` to `campaign-preview-*`
- **HTML element IDs:** `releaseEditorCard` to `campaignEditorCard`, `releasePoolView` to `campaignPoolView`, `releaseTracksPoolView` to `campaignEditorView`, etc.
- **Keep:** `release_date` field name in data (this is the campaign's release date, the word "release" is correct here as a noun)

#### theme to brand

- **PHP filenames:** rename `theme-storage.php` to `brand-storage.php`, `theme-editor.js` to `brand-editor.js`, `theme-editor.css` to `brand-editor.css`, `theme-preview.js` to `brand-preview.js`, `manage-theme.php` to `manage-brand.php`, `save-theme.php` to `save-brand.php`, `get-theme.php` to `get-brand.php`, `get-themes.php` to `get-brands.php`, `set-active-theme.php` to `set-active-brand.php`, `duplicate-theme.php` to `duplicate-brand.php`
- **JS/PHP identifiers:** `themeId` to `brandId`, `selectedThemeId` to `selectedBrandId`, `themeEntry` to `brandEntry`, `themeSettings*` to `brandSettings*`, `editorDocument` stays (generic)
- **PHP function names:** `bandpromo_theme_*` to `bandpromo_brand_*` (~65 definitions + call sites in theme-storage)
- **Action param values:** `action=create_theme` to `action=create_brand`, `action=delete_theme` to `action=delete_brand`, etc. in both PHP handlers and JS fetch calls
- **URL params:** `?theme=` to `?brand=`, `?cntab=themes` to `?cntab=branding`
- **CSS classes:** `theme-pool-row` to `brand-registry-row`, `theme-editor-*` to `brand-editor-*`
- **HTML element IDs:** `themeEditorRoot` to `brandEditorRoot`, `themePoolView` to `brandPoolView`, `themeEditorView` to `brandEditorView`, etc.

#### UK English sweep

While touching every file, correct US English in operator-facing strings (per AGENTS.md house style). Known instances in our code (not `vendor/`):

- `customize` to `customise` — `bootstrap.php`, `theme-editor.js` (becomes `brand-editor.js`), `theme-storage.php` (becomes `brand-storage.php`)
- Any `color` in operator-facing labels/messages to `colour` (CSS properties and code identifiers stay `color` per AGENTS.md)
- Scan all touched files for other US spellings (`organize`, `favorite`, `center` in user-facing text) and correct

#### Migration safety

- Each rename is a mechanical find-and-replace. Run PHP lint on every touched file.
- Old URL params (`?release=`, `?theme=`) should be accepted as aliases for one release cycle so bookmarks and cached pages still work. Add a one-line fallback in `admin.php`:
  ```php
  if (!isset($_GET['campaign']) && isset($_GET['release'])) $_GET['campaign'] = $_GET['release'];
  if (!isset($_GET['brand']) && isset($_GET['theme'])) $_GET['brand'] = $_GET['theme'];
  ```
- Smoke-test every Content sub-tab in both pool and edit views after each rename batch.

### Phase 2 — Extract shared JS modules (biggest win)

Create four new files under `biblioteca/`:

- **`editor-lifecycle.js`** — shared `createEditorLifecycle()` factory
  - Pool/edit view toggling (`showPoolView`, `showEditView`)
  - `is-editing` class management on root element
  - URL sync (`syncEditorUrl`) — replaces `syncGalleryUrl`, `syncPlaylistUrl`, and equivalents in release/page/theme editors
  - `requestCloseEditor` with configurable unsaved-changes strategy (confirm dialog vs modal)
  - Pool list click delegation (edit/delete/select row)
  - Back button binding
  - Each editor calls `createEditorLifecycle({...hooks})` instead of reimplementing the 30-line pattern

- **`editor-drag-reorder.js`** — shared `bindDragReorder(listEl, options)` 
  - Replaces both `bindDragList` implementations (gallery L9132, playlist L10983)
  - Placeholder insertion, index calculation, drop finalisation
  - Configurable row selector and reorder callback

- **`editor-range-selection.js`** — shared `createRangeSelection(listEl, options)`
  - Replaces all four `selectAvailableRange`/`selectActiveRange` functions and their click handlers
  - Shift-click, Ctrl-click, anchor tracking
  - Configurable key extractor (`src` for gallery, `file` for playlist)

- **`editor-registry-list.js`** — shared `renderRegistryList(listEl, items, selectedId, options)`
  - Replaces `renderGalleryPoolList` and `renderPlaylistPoolList` (and could serve releases/pages/themes)
  - Configurable row template, sort order, action buttons

**Migration:** Wire Gallery and Playlist editors first (closest clones, most duplication). Then Catalogue, Pages, Branding in follow-up commits.

### Phase 3 — CSS rename (stale naming)

Rename the three overloaded prefixes using find-and-replace across `admin.css`, `admin.php`, all `.js` files:

- `playlist-editor-row` (used by all editors) becomes **`editor-row`**
- `playlist-editor-row-selected` / `-pending` / `-focus` become `editor-row--selected` / `--pending` / `--focus`
- `playlist-editor-placeholder` becomes **`editor-placeholder`**
- `playlist-drag-handle` becomes **`editor-drag-handle`**
- `page-pool-row` (used by all registries) becomes **`registry-row`**
- `page-pool-edit-btn` / `-delete-btn` / `-duplicate-btn` / `-lock-btn` become `registry-btn--edit` / `--delete` / `--duplicate` / `--lock`
- `player-layout-editor` becomes **`split-editor`**
- `player-layout-col` / `-head` / `-list` / `-panel` / `-save-row` become `split-editor__col` / `__header` / `__list` / `__panel` / `__save`
- `content-editor-card` becomes **`editor-card`**

Remove dead classes: `container-pool-layout`, `container-registry-list`, `gallery-admin-grid`, `gallery-admin-item`, `gallery-admin-label` (duplicated), and any classes confirmed unreferenced.

Editor-specific prefixes stay: `playlist-track-*`, `gallery-thumb-*`, `campaign-preview-*`, `brand-editor-*` (already renamed in Phase 1).

### Phase 4 — Unify save UX

- Add `bandpromoContentSaveUi` to the Catalogue editor (currently the only editor without it)
- Adopt the Pages-style unsaved-changes modal everywhere, replacing `window.confirm()` in Playlist, Gallery, Branding, Catalogue
- Standardise feedback: toast for manual saves, inline status text for auto-saved settings
- Add save queueing (`settingsSaveQueued` flag) to Gallery and Branding settings auto-save (Catalogue and Playlist already have it)

## What NOT to change

- Do not rename code identifiers to UK English (`catalog`, `color` in variable names stay per AGENTS.md)
- Do not move files into a `lib/` tree yet (that is the v0.9 code layout refactor)
- Do not touch PHP endpoints or data formats
- Do not change any user-facing behaviour — this is a pure internal refactor

## Risk mitigation

- Each phase ships as its own checkpoint so regressions are bisectable
- Phase 1 (terminology) and Phase 3 (CSS rename) are mechanical find-and-replace; run PHP lint + visual smoke on every Content tab before committing
- Phase 2 modules are additive — old code stays until the editor is migrated, then the duplicate is deleted
- Phase 1 URL param aliases ensure bookmarked admin URLs keep working for one release cycle
