# Session Handoff

## Resume point

**Brand-assets Phase A/B cleanup shipped** — dead `special` admin UI paths removed; thin remaps kept.

### Shipped (this session)

1. Page/campaign picker defaults: `'illustrations,photos,special'` / `'video,special'` → `'visual'`.
2. `admin.css`: all `#panel-special` rules removed.
3. `admin.js`: strip Brand-assets panel state, helpers (`openBrandLibraryPicker`, remove-from-library bulk, etc.), and `loadMediaList('special')` paths. Keep `normalizeFilesPanel` / `SUBTAB_ALIASES` / `normalizeMediaPickerTargets` remaps and Visual+SFX Use in brand / From brand.
4. Docs: FEATURES / MEDIA-HANDLING no longer describe Brand assets as a live Files tab.
5. Backend `list-media` / upload / delete `target=special` dual-read left intact for disk leftovers.

### Earlier (still true)

1. Files pools: Audio | Visual | Sound effects only; `fpanel=special` → Visual.
2. Visual/SFX: **Use in brand** / **From brand**; Visual **Use in gallery**; Audio **Use in playlist**.

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here.
