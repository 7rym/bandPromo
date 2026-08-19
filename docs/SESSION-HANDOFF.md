# Session Handoff

## Resume point

**Admin editor refactor (Phases 1–4) is complete and published.** Next session: **admin legacy compat strip** (see plan below).

## Shipped (build 425+)

- **Phase 3** — Panel chrome renames + `page-editor.css` campaign selector migration (`release-*` → `campaign-*`, `#campaignEditorLayout`).
- **Phase 4** — Shared unsaved modal, Catalogue save UI, Gallery/Branding save queue, save toasts.
- **Hotfix** — Campaign settings autosave no longer reverts title (`campaigns` vs `releases` API response).

Latest published release: check `VERSION` after checkpoint.

## Next session — Legacy compat strip

Work through in order (see audit in prior session transcript):

1. **Safe deletes** — drop `data.releases` read path in JS once PHP is canonical-only; remove duplicate `releases` JSON keys from manage/duplicate campaign endpoints.
2. **Fix migration gaps** (bugs, not compat):
   - `manage-brand.php`: accept `?brand=`, return `brands` / `active_brand_id`.
   - `list-media.php`: accept `?campaign=` (admin.js already sends it).
   - `admin.php`: alias `cntab=themes` → `branding`.
3. **Canonical internal links** — `admin-welcome-state.php`, `demo-catalog-state.php` → `cntab=campaign&campaign=`.
4. **Remove URL/query aliases** — `?release=`, `?theme=`, `cntab=release` in `admin.php` + PHP API dual-read (after one release on build 425).
5. **Remove JSON aliases** — `themes`/`active_theme_id` on get-brands, etc.
6. **Keep indefinitely** — `data/releases/` storage path, `release_id` data fields, `data/themes/` migration, `system_managed` stub until fleet clean.

Do **not** rename on-disk `release_id` or `data/releases/` — data model, not admin chrome.

## Plan document

Full refactor history: `docs/ADMIN-EDITOR-REFACTOR.md`
