# Session Handoff

## Resume point

Admin editor refactor — **Phase 3 real cleanup** (remove legacy class names after additive alias rollout).

## What's done

### Phase 1 — Terminology rename (committed)

- release→campaign, theme→brand, UK English pass. Backwards-compat URL/API aliases retained where needed.

### Phase 2 — Shared JS modules + migration (committed, local)

- Shared modules: `editor-lifecycle.js`, `editor-drag-reorder.js`, `editor-range-selection.js`, `editor-registry-list.js`.
- Wired into Gallery, Playlist (admin.js), Campaign, Pages, Brand editors.
- Init guards on Campaign/Pages/Brand editors (duplicate load fix).
- PHP/API fixes for campaign/brand query params and response keys.

### Phase 3 — Additive CSS/JS alias rollout (committed, local, not pushed)

- Dual class emission (`playlist-editor-row` + `editor-row`, `page-pool-*` + `registry-*`, etc.).
- Parallel CSS in `admin.css` / `page-editor.css`.
- Bidirectional registry button class expansion in `editor-registry-list.js`.
- Inline style cleanup in content-editor HTML/JS → semantic classes in `admin.css`.

Latest local commit: inline style cleanup checkpoint (`v0.8.32 build 417`). **Three commits ahead of origin/main.**

## What's next

### Phase 3 — Real cleanup (start here)

Remove legacy class names and selectors now that dual aliases are in place:

1. Drop legacy classes from JS/HTML emitters (`playlist-editor-row`, `page-pool-*`, `content-editor-card`, `player-layout-editor`, etc.).
2. Remove parallel legacy CSS selectors; keep `editor-*`, `registry-*`, `split-editor`, `editor-card` only.
3. Tighten JS `closest` / `querySelectorAll` to new names only.
4. Smoke-test Gallery, Playlist, Campaign (tracks + associations), Pages, Brand editors.

### Phase 4 — Unify save UX (deferred)

Add `bandpromoContentSaveUi` to Catalogue; Pages-style unsaved modal everywhere.

## Plan document

Full plan: `docs/ADMIN-EDITOR-REFACTOR.md`
