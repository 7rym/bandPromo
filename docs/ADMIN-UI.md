# Admin UI design system

Operator chrome for `admin.php` (and shared Content editor CSS). Public player branding tokens are separate (`docs/PLATFORM-MODEL.md`).

## Palette (canonical)

Defined on `:root` in `biblioteca/admin.css`:

| Token | Role |
|-------|------|
| `--accent` / `--primary` | Affirmative coral (submit, primary CTA) |
| `--success` | Positive completion (saved) |
| `--warn` | Attention / dirty / preview caution |
| `--error` | Hard destructive / validation failure |
| `--muted` | Secondary text and quiet controls |
| `--intent-good-*` | Green constructive icon actions |
| `--intent-warn-*` | Amber caution / preview icon actions |
| `--intent-quiet-*` | Grey quiet dismiss/delete (not alarm red) |

## Text buttons

Prefer **one class ladder**. Unstyled `button` elements without a `class` keep the coral default; classed controls use the ladder below.

| Class | Meaning | When to use |
|-------|---------|-------------|
| `.btn` | Neutral secondary | Cancel, alternate actions |
| `.btn.btn-secondary` | Alias of `.btn` | Legacy markup |
| `.btn.btn-primary` | Affirmative | Create, confirm safe actions |
| `.btn.btn-amber` | Dirty / needs attention | Save controls while unsaved (`content-save-ui.js`) |
| `.btn.btn-saved` | Saved / idle success | Save controls after successful save |
| `.btn.btn-danger` | Destructive confirm | Delete / irreversible confirms |
| `.btn.btn-danger-outline` | Soft destructive / discard | Leave without saving |
| `.btn-sm` | Compact size | Dense toolbars |

Legacy standalone `.btn-primary` (without `.btn`) remains for older markup; new code should use `.btn.btn-primary`.

### Save-state machine

`biblioteca/content-save-ui.js` toggles:

1. hidden or neutral when clean and never saved this session  
2. `.btn-amber` when dirty  
3. “Saving…” while in flight  
4. `.btn-saved` (disabled) after success  

Do **not** invent a second amber/green save pattern.

## Icon / compact actions

| Class | Meaning |
|-------|---------|
| `.icon-btn` | Default compact control (neutral border) |
| `.icon-btn.icon-btn--danger` (or `.icon-btn.danger`) | Hard destructive icon — red |
| `.icon-btn.icon-btn--pool` | 28×28 pool-row tool |
| `.icon-btn.icon-btn--pool.icon-btn--danger` | Pool delete — red |
| `.icon-btn.icon-btn--pool.icon-btn--active` | Active lock / selected tool |
| `.media-action-btn.media-action-good` | Constructive (upload, apply) — green tokens |
| `.media-action-btn.media-action-amber` | Caution / preview — amber tokens |
| `.media-action-btn.media-action-danger` | Quiet remove/dismiss — **grey** tokens |
| `.player-layout-remove-btn` | In-row ✕ remove (muted → red on hover) |
| `.gallery-remove-btn` | Alias of `.player-layout-remove-btn` |

Semantic hooks for event delegation (also carry the `icon-btn` classes above):

- `.page-pool-edit-btn` / `.page-pool-lock-btn` / `.page-pool-duplicate-btn` / `.page-pool-delete-btn`

### Danger vs quiet delete

- **Red** (`--error`, `.btn-danger`, `.icon-btn--danger`, pool delete): irreversible or registry deletes that need alarm.  
- **Grey quiet** (`.media-action-danger`): remove from a working set / dismiss without “panic” color.  
- **In-row ✕** (`.player-layout-remove-btn`): muted until hover, then soft red.

Do not mix coral primary + `danger` on the same confirm button — use `.btn.btn-danger`.

## Status chips

| Class | Tokens |
|-------|--------|
| `.media-file-inline-chip-good` | `--intent-good-*` |
| `.media-file-inline-chip-amber` | `--intent-warn-*` |
| `.media-file-inline-chip-danger` | error-tint red (hard health fail) |

## Track editor modal (Files → Audio)

Shares Content/Visual chrome without the pool → preview layout:

| Pattern | Classes |
|---------|---------|
| Cover + meta | `.audio-master-cover-layout`, `.release-cover-meta`, shared `.audio-master-cover-preview` |
| Field labels | `.playlist-settings-field` / `--wide` |
| Lyrics / Notes | Compact pill `.audio-master-text-role-toggle` / `.audio-master-text-role-btn` |
| Autosave status | Header `.playlist-settings-status--head` + `.visual-asset-display-status.is-success/.is-error` (“Close to save” / “Unsaved changes”) |
| Listen preview | Compact `<audio>` under Master audio asset (`.audio-master-listen-bar`); Files rows use ▶ → `#adminAudioListenDock` via `audio.php` (`.media-action-good`) |
| Save / Abort | Footer `.audio-master-modal-actions`: **Done** / ✕ / backdrop save on close; **Abort** discards |
| Audio list columns | Compact `.audio-pool-toolbar` + shared grid; All/None `.audio-select-chip` in header; `[data-audio-sort]` for client sort |

Edits stay local until close. Validation or save errors keep the modal open.

## Visual / Brand assets lists (Files → Visual, Brand assets)

Same operator patterns as Audio, plus Grid/List:

| Pattern | Behavior |
|---------|----------|
| Toolbar | Shared `.audio-pool-toolbar` density; type chips + catalogue/brand filter + title search + Grid/List. List view adds an **S / M / L** thumbnail-size toggle (70 / 100 / 125 px; default M). Preference is stored in `localStorage` (`bandpromo_pool_thumb_size`). |
| Selection | All/None `.audio-select-chip` in `.visual-pool-col-headers` (not a toolbar checkbox); checkbox click updates selection on Visual, Brand assets, and Sound effects pools |
| List mode | Title / Catalogue\|Brand\|Warehouse / Dimensions / Size. Visual Catalogue is every campaign that uses the file (owned gallery, track cover/living cover, release/playlist poster, press photo, page picture, or Brand visual shell those campaigns play). Empty Brand slots inherit the Base brand, so site-wide logos/backgrounds list every inheriting release rather than Orphan. Brand-library members with no campaign use list that Brand, not Orphan. Shared files list every matching release, each on its own line. Catalogue is not Brand ownership on the asset and not library membership. The In use / Unused pill is live assignment (track cover, gallery, page, poster, or brand shell slot), not Catalogue. Usage identity is the Visual `ast_*` id after resolving stored refs; titles and filename stems never match. Brand assets Warehouse is Visual or Sound effects (the global pool the file lives in), not Brand membership — library members are never listed as Orphan. Dimensions are the master pixel size; audio Brand-asset rows put Listen in that column. Preview pane uses Visual `card`/`thumb` (or video poster/stream / SFX play URL). Brand-asset modal footer is Download + Remove (membership), not permanent Delete. |
| Grid mode | Thumbnails + caption under each card; column labels hidden, All/None kept |
| Search | “Filter by title…” (same haystack: display title, operator title, references) |

## Markdown help (prose textareas)

Long player-facing prose textareas (track description, lyrics/Notes, release/playlist long description) show **Markdown** plus a **?** control (`.markdown-help-open`) that opens `#markdownHelpModal`. Short descriptions, titles, and page richtext stay plain / toolbar HTML.

Helpers: `bandpromo_admin_markdown_help_trigger()` / `bandpromo_admin_markdown_help_note()` in `biblioteca/player-markdown.php`.

## Drag-and-drop rows

| Context | Look |
|---------|------|
| Available pools (tracks, playlists, galleries, pages) | `.playlist-editor-row` card: border, surface fill, drag handle |
| Associated tracks (unordered membership) | Flat `.release-associated-track-row` under `#releaseActiveList` only |
| Associated playlists / galleries / pages | Same card row as Available (border + ✕) |

Do not put `release-associated-track-row` on association pool rows.

## Gallery membership (v0.8)

Primary flow for assembling gallery items:

1. **Searchable multi-select picker** — shared media-picker pattern; filters for type, role, brand/release, date, keyword; show **human title** + larger thumb (not tiny `ast_*`-only chips).
2. **Ordered selected list** — explicit reorder; multi-select add/remove.

Available↔Associated drag-and-drop of small thumbs is not the primary assembly path for concert-scale galleries.

## Related docs

- Content editor layout: `docs/PLATFORM-MODEL.md` (Editor UX pattern)  
- Amber/green save mention: `docs/FEATURES.md`
