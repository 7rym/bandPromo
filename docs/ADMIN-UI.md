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

## Drag-and-drop rows

| Context | Look |
|---------|------|
| Available pools (tracks, playlists, galleries, pages) | `.playlist-editor-row` card: border, surface fill, drag handle |
| Associated tracks (unordered membership) | Flat `.release-associated-track-row` under `#releaseActiveList` only |
| Associated playlists / galleries / pages | Same card row as Available (border + ✕) |

Do not put `release-associated-track-row` on association pool rows.

## Related docs

- Content editor layout: `docs/PLATFORM-MODEL.md` (Editor UX pattern)  
- Amber/green save mention: `docs/FEATURES.md`
