# Admin UI design system

Operator chrome for `admin.php` (and shared Content editor CSS). Public player branding tokens are separate (`docs/PLATFORM-MODEL.md`).

## Primary navigation

Main tabs (Dashboard, Analytics, Users, Files, Content, Settings, System, Documentation) remember the last used **sub-tab** in `localStorage` (`bandpromo_admin_nav_memory`). Switching Files → Content → Files returns to Visual (or whichever Files panel you left), not always Audio. Deep links that already name a sub-tab (`fpanel`, `cntab`, `ctab`, `stab`, `atab`, `doc_scope`) are unchanged.

## Page headings under the nav bar (preferred)

For admin surfaces that sit under the main tab / Content sub-nav (especially Content editors), prefer a **breadcrumb heading** over a plain `h2` card title:

| View | Crumb |
|------|--------|
| Pool / list | `{emoji} {Section} > Pool` |
| Editor | `{emoji} {Section} > Editor` |

### Breadcrumb line layout

One row under the Content sub-nav (`.content-editor-card-head`, min-height matches Editor chrome so Pool does not jump):

| Slot | Placement | Use |
|------|-----------|-----|
| Crumb | Left | Section root (underlined link → Pool, same leave/unsaved path as ← Back) + `> Pool\|Editor` |
| `trailing` | Immediately after the crumb | Optional editor section chips (Catalogue: Base info \| Extended info \| …; Pages: Base info \| Page builder; Branding: Common \| Player \| Content) |
| `actions` | Right edge (`.content-editor-card-head-actions`) | ← Back + Save\|Saved (and ★ Set as default / ★ Set as base when that editor has them). Hidden while not `.is-editing` |

- Markup: `bandpromo_admin_render_content_breadcrumb()` in `biblioteca/admin-helpers.php`.
- Behaviour: `bandpromoContentEditorBreadcrumb.attach()` — `setView('pool'|'edit')` on lifecycle show hooks.
- Entity name stays in the left edit header under the breadcrumb row; preview headers are titles only (no Save strip).

Shipped on Catalogue, Playlists, Galleries, Pages, and Branding.

Do not invent a second under-nav title pattern for new Content editors unless the surface is not a pool→editor flow. Do not leave ← Back / Save only in the split-editor headers when the breadcrumb row is present.

## System tab and roles (2026-08-31)

| Sub-tab | `admin` role | `developer` role |
|---------|--------------|------------------|
| Status (site health) | Yes | Yes |
| Backup, export & import | Yes | Yes |
| Audit | No | Yes |
| Environment | No | Yes |

**Status** is the operator **site health** page (triage → diagnose → treat → follow-up) — see [BUILD-PIPELINE-PLAN.md](BUILD-PIPELINE-PLAN.md). Legacy Refresh / Repair catalogue / Peek under the hood are folded into Check / Treat / Force; Status no longer surfaces those as primary actions.

**Role policy (Status):** `admin` and `developer` may run Quick/Full check, Review → Apply treatment (including register-in-place), and Force full rebuild. Destructive future treatments (e.g. prune duplicates) stay developer-only when added. Direct `?stab=audit` or `?stab=environment` (legacy `?stab=security`) redirects operators to Status.

Operator feedback uses toasts today; unified toast → inbox is planned for v0.9 — [OPERATOR-MESSAGING.md](OPERATOR-MESSAGING.md).

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
- **Grey quiet** (`.media-action-danger`): remove from a working set / dismiss without “panic” colour.  
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
| Toolbar | Shared `.audio-pool-toolbar` density; type chips (Visual: All + Images/Video icons; Brand assets: All + Still/Living/Sound-effects icons) + catalogue/brand filter (**All campaigns** / **All brands** / **Orphans**) + title search + Grid/List. Brand assets **Orphans** is brand-eligible non-members only (shell roles, special/SFX intake, brand provenance) — not every Visual track cover. Brand assets **Add existing** is hidden until a Brand is selected. The Add-existing picker has its own **Campaign** filter on Visual and **Brand** filter on Sound effects (not the Files toolbar). List view adds an **S / M / L** thumbnail-size toggle (70 / 100 / 125 px; default M). Preference is stored in `localStorage` (`bandpromo_pool_thumb_size`). List rows keep title, In use/Unused, and row actions on one line. |
| Selection | All/None `.audio-select-chip` in `.visual-pool-col-headers` (not a toolbar checkbox); checkbox click updates selection on Visual, Brand assets, and Sound effects pools |
| List mode | Title / Catalogue\|Brand\|Warehouse / Dimensions / Size. Visual Catalogue is every campaign that uses the file (owned gallery, track cover/living cover, release/playlist poster, press photo, page picture, or Brand visual shell those campaigns play). Empty Brand slots inherit the Base brand, so site-wide logos/backgrounds list every inheriting release rather than Orphan. Brand-library members with no campaign use list that Brand, not Orphan. Shared files list every matching release, each on its own line. Catalogue is not Brand ownership on the asset and not library membership. The In use / Unused pill is live assignment (track cover, gallery, page, poster, or brand shell slot), not Catalogue. Usage identity is the Visual `ast_*` id after resolving stored refs; titles and filename stems never match. Brand assets Warehouse is Visual or Sound effects (the global pool the file lives in), not Brand membership — library members are never listed as Orphan. Dimensions are the master pixel size; audio Brand-asset rows put Listen in that column. Preview pane uses Visual `card`/`thumb` (or video poster/stream / SFX play URL). Brand-asset modal footer is Download + Remove (membership), not permanent Delete. |
| Grid mode | Thumbnails + caption under each card; column labels hidden, All/None kept |
| Search | “Filter by title…” (same haystack: display title, operator title, references) |

## Markdown help (prose textareas)

Long player-facing prose textareas (track description, lyrics/Notes, release/playlist long description) show **Markdown** plus a **?** control (`.markdown-help-open`) that opens `#markdownHelpModal`. Short descriptions, titles, and page richtext stay plain / toolbar HTML.

Helpers: `bandpromo_admin_markdown_help_trigger()` / `bandpromo_admin_markdown_help_note()` in `biblioteca/player-markdown.php`.

## Content editor sections

Pages and Branding edit views group fields in `.content-editor-section` cards:

- Chrome header: `.content-editor-section-head` with `--border2` fill (same bar as Page/Branding Back/name and Live preview headers, and block card headers)
- Body: `.content-editor-section-body`

Pages: breadcrumb chips **Base info** \| **Page builder**; each section is a tab panel (builder toolbar + blocks only on Page builder). Breadcrumb actions hold ← Back + Save|Saved. Playlists: **Base info** (Artwork, publish date, package type, play order As listed|Newest first toggle, slug, descriptions) with inline `Label:` chrome; Pool preview mirrors Catalogue (cover + blurb + Playlist details: tracks / package / campaign / play order / URL); track order + available pool only while editing. **★ Set as default** / Branding **★ Set as base** sit on the breadcrumb actions row with ← Back and Save (not a checkbox). Catalogue (Campaign): name in the edit header; breadcrumb row holds section chips (Base info | Extended info | Tracks | …) and ← Back + Save|Saved on the right while editing; **Base info** (start / slug / press / branding / blurb) + **Media assets** (Artwork); **Extended info** holds **Press kit** (long description Markdown); Pool preview shows cover + brand + owned-content summary (Tracks / Playlists / Galleries / Pages); Base|Extended edit preview keeps cover + brand + long-description readout. Branding: Common | Player | Content chips on the breadcrumb row; (Player: **Cover** [Size Full|Medium|Half, Reflection, Side covers], **Controls** [full panel chrome], **User area** [Login / status, Beggars banquet]; Content: **Buttons**, **Playlist selector**, **Panels**, **Typography**). Prefer `Label:` + control on one line; segmented toggles over checkboxes; toggles over dropdowns when ≤5 alternatives; inline `Label:` + value sliders for continuous scales (see [AGENTS.md](AGENTS.md)).

**Player colour contract:** `#mediaplayer` keeps platform layout (scene, transport, scrubber) but paints from brand colours. `#content-container` shares the palette; **Buttons** and **Typography** use role swatches (outline/fill, headings/body/blockquote) from that palette. Soft fill = 50% of the Fill role. **Panels** (fill, blur, corners Square/Shaved, border width + colour role, density) drive the frosted boxes — content sits inside that one surface.

## Drag-and-drop rows

| Context | Look |
|---------|------|
| Available pools (tracks, playlists, galleries, pages) | `.playlist-editor-row` card: border, surface fill, drag handle |
| Associated tracks (unordered membership) | Flat `.release-associated-track-row` under `#releaseActiveList` only |
| Associated playlists / galleries / pages | Same card row as Available (border + ✕) |

Do not put `release-associated-track-row` on association pool rows.

## Gallery membership (v0.8)

**Shipped today:** Available↔Associated drag-and-drop (same pool pattern as playlists).

**Locked target** (not the current primary flow):

1. **Searchable multi-select picker** — shared media-picker pattern; filters for type, role, brand/release, date, keyword; show **human title** + larger thumb (not tiny `ast_*`-only chips).
2. **Ordered selected list** — explicit reorder; multi-select add/remove.

Available↔Associated drag-and-drop of small thumbs is not the intended primary assembly path for concert-scale galleries.

## Copy

House style is **UK English** (see [AGENTS.md](AGENTS.md) Language). Operator labels use **catalogue**, not catalog. Do not rename CSS tokens, file names, or JSON keys to match.

## Related docs

- Content editor layout: `docs/PLATFORM-MODEL.md` (Editor UX pattern)  
- Amber/green save mention: `docs/FEATURES.md`
