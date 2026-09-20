# Admin UI design system

Operator chrome for `admin.php` (and shared Content editor CSS). Public player branding tokens are separate (`docs/PLATFORM-MODEL.md`).

## Primary navigation

Main tabs (Dashboard, Analytics, Users, Files, Content, Settings, System, Documentation) remember the last used **sub-tab** in `localStorage` (`bandpromo_admin_nav_memory`). Switching Files → Content → Files returns to Visual (or whichever Files panel you left), not always Audio. Deep links that already name a sub-tab (`fpanel`, `cntab`, `ctab`, `stab`, `atab`, `doc_scope`) are unchanged.

## Page headings under the nav bar (preferred)

For admin surfaces that sit under the main tab / Content sub-nav (especially Content **pool → editor** flows), prefer a **breadcrumb heading** plus an **inline-editable entity title** — not a plain `h2` and not a separate “Title:” field buried in Base info for the entity name.

| View | Crumb |
|------|--------|
| Pool / list | `{emoji} {Section} > Pool` |
| Editor | `{emoji} {Section} > Editor` |
| System Status | Default: `📊 Status` landing (Site health tool card only — **no Activity**). Open Site health → **Action** hub (Quick / Full / Force; no Activity). While a job runs → Action checklist + Stop (mode in the crumb) with **Activity** visible underneath; no Good/Bad/Ugly. When finished → **Diagnosis** (Good/Bad/Ugly + Review when findings exist; Activity). **Review** → `… > Diagnosis > Proposed treatment`. **Apply** → Action checklist + Activity, then `… > Treatment result`. Hub resume opens last Diagnosis. Post–Site update / stale auto-start opens the Quick check checklist directly. |

### Breadcrumb line layout

One row under the Content sub-nav (`.content-editor-card-head`, min-height matches Editor chrome so Pool does not jump):

| Slot | Placement | Use |
|------|-----------|-----|
| Crumb | Left | Section root as an **underlined navigation control** (button or link — even when it lands on the same page) + `> Pool\|Editor\|Site health\|…` |
| `trailing` | Immediately after the crumb | Optional editor section chips (Catalogue: Base info \| …) or muted meta (Status: last check · version via `.content-editor-breadcrumb-meta` — **not** the check mode; mode lives in the crumb). |
| `actions` | Right edge (`.content-editor-card-head-actions`) | ← Back + Save\|Saved (and ★ Set as default / ★ Set as base when that editor has them). Hidden while not `.is-editing`. Status puts the overall health badge here — on the Status / Site health Action chooser a prior healthy plan is labelled **Last check healthy** (not a live clean bill); green **Healthy** appears after you open Diagnosis or finish a check. |

- Markup: `bandpromo_admin_render_content_breadcrumb()` in `biblioteca/admin-helpers.php`.
- Options: `current` (default `Pool`), `root_navigable` (default true), `root_href` (optional — render an `<a>` instead of the Pool button; use for page-level crumbs such as Status).
- **Preference:** breadcrumb roots are always visually links (underlined). Do not render a plain muted span for the section root unless there is truly no destination.
- Behaviour: `bandpromoContentEditorBreadcrumb.attach()` — `setView('pool'|'edit')` on lifecycle show hooks (Content editors). Status roots use `root_href` to System → Status.

### Inline entity title (Content editors)

While editing, the **entity name is an inline text field in the left edit header** under the breadcrumb row — not a static `h3` and not duplicated as a “Title:” row in the settings body.

| Pattern | Detail |
|---------|--------|
| Control | `.content-editor-name-input` (Branding also uses `.brand-editor-name-input`) |
| Placement | Left edit header, under `.content-editor-card-head` |
| Behaviour | Edit in place; dirty state feeds the head Save\|Saved machine / leave Discard path |
| Preview | Pool / Live preview headers stay **title-only** (readout, no Save strip, no second name field) |

Shipped on Catalogue, Playlists, Galleries, Pages, and Branding. Prefer this whenever a Content (or Content-like) editor has a single primary display name.

Do **not**:

- Invent a second under-nav title pattern for new Content pool→editor flows
- Leave ← Back / Save only in split-editor headers when the breadcrumb row is present
- Put the canonical entity name only inside Base info / settings while the header shows a non-editable label
- Use a plain muted span for the breadcrumb root when a destination exists

Files modals (track / visual drilldowns) are not breadcrumb surfaces; still prefer an **editable title in the modal header** over a static heading plus a redundant Title field in the body when aligning those editors.

## Sticky toolbars

**Sticky** means: stay in normal flow while visible; pin only when scroll would push the chrome off the top of the viewport. Not permanently floating chrome.

| Surface | When it sticks |
|---------|----------------|
| Files / Content / System sub-tab bars | Page scroll would hide them (`top: 0`) |
| Files pool filter/actions + column headers (`.media-pool-sticky-chrome`) | Would leave the viewport under the Files sub-tab bar |
| Content / Status breadcrumb head (`.content-editor-card-head`) | Would leave under the section sub-tab bar |
| Split-editor column headers / gallery picker toolbar | Would leave under the breadcrumb head |

Do not nest sticky headers inside an already-sticky column (Branding Live preview sticks as a column; its title stays in normal flow). Page builder keeps its own sticky section / richtext pattern. New pool or editor toolbars follow the same “stick only when leaving the viewport” rule.

## System tab and roles (2026-08-31)

| Sub-tab | `admin` role | `developer` role |
|---------|--------------|------------------|
| Status (site health) | Yes | Yes |
| Backup, export & import | Yes | Yes |
| Audit | No | Yes |
| Environment | No | Yes |

**Status** is the operator **site health** page (triage → diagnose → treat → follow-up) — see [BUILD-PIPELINE-PLAN.md](BUILD-PIPELINE-PLAN.md). Primary actions: **Quick health check** / **Full health check**, **Review treatment** → **Apply treatment**, **Force full rebuild**. After **Site update**, Status auto-starts Quick health check. Legacy Refresh / Repair catalogue / Peek under the hood are folded into Check / Treat / Force; Status no longer surfaces those as primary actions.

Check results use a three-panel summary:

| Panel | Colour | Contents |
|-------|--------|----------|
| **The good** | Green | Registered masters present on disk (`ok/total`) plus Campaigns / Playlists / Galleries / Pages / Brands whose registry entry has a document on disk. Container checks are presence/parse — not a full content audit. |
| **The bad** | Amber (critical → red cards) | Unregistered masters, missing delivery/tags/covers, duplicates, JSON drift, and other treatment findings. |
| **The ugly** | Grey | Janitor leftovers (orphan delivery, empty dirs, non-media junk). |

**Role policy (Status):** `admin` and `developer` may run Quick/Full check, Review → Apply treatment (including register-in-place), and Force full rebuild. Destructive future treatments (e.g. prune duplicates) stay developer-only when added. Direct `?stab=audit` or `?stab=environment` (legacy `?stab=security`) redirects operators to Status.

Operator feedback uses toasts today; unified toast → inbox is planned for v0.9 — [OPERATOR-MESSAGING.md](OPERATOR-MESSAGING.md).

## Palette (canonical)

Defined on `:root` in `biblioteca/admin.css`:

| Token | Role |
|-------|------|
| `--accent` / `--primary` | Affirmative coral (legacy safe-form confirm) — **prefer amber Save / green Saved / grey secondary**; see Colour caution below |
| `--success` | Positive completion and the **recommended next step** |
| `--warn` | Attention / dirty / important information |
| `--error` | Hard destructive / validation failure / critical findings |
| `--muted` | Secondary text and quiet controls |
| `--intent-good-*` | Green constructive icon actions |
| `--intent-warn-*` | Amber caution / preview icon actions |
| `--intent-quiet-*` | Grey quiet dismiss/delete (not alarm red) |

### Colour caution (coral vs red)

`--accent` / `--primary` (`#FF6B6B`) sits next to `--error` (`#f44336`). Operators (including colourblind ones) easily read coral as “danger.” **Do not** use coral as a general “important button” colour beside Delete, and do not rely on hue alone to separate safe vs destructive.

Prefer:

| Intent | Control |
|--------|---------|
| Dirty / needs save | Amber **Save** (`.btn-amber`) |
| Saved / idle | Green disabled **Saved** (`.btn-saved`) |
| Recommended next step | One green (`.btn-good`) — Status ladder |
| Secondary / Download / Close | Grey (`.btn`) |
| Irreversible | Red (`.btn-danger`) only |

Legacy coral `.btn-primary` remains for older safe confirms until those screens are migrated; new editor chrome should not introduce more coral primaries next to danger actions.

### Compactness (spacing)

Admin chrome is **dense**. Prefer less air over padded “dashboard cards.” When touching a surface, bring it onto this scale — do not invent a new inset for one page.

| Token (target) | Size | Use |
|----------------|------|-----|
| none | `0` | **Prefer this** when a border, hairline, or sibling gap already separates content |
| tight | `4px` | Chips, icon buttons, dense toolbar clusters, label↔control nudge |
| default | `8px` | **Absolute max layout padding** for cards, section bodies, modal side panes, form stacks, list rows, head/actions gutters |

Rules:

1. **Layout padding ≤ 8px.** No `12` / `14` / `16` / `18` / `20` / `24` insets on new or retouched admin panels. If it feels cramped, tighten typography or use a hairline — do not add padding.
2. **Prefer none** inside nested boxes that already have an outer border (avoid padding-on-padding).
3. **Gaps** between siblings in a row or stack: `4px` or `8px` only (same cap). Section-to-section separation: hairline / `--border2` bar, not a large spacer.
4. **Control internals** (`.btn`, text inputs, chips): horizontal padding may exceed 8px for hit targets; keep vertical padding ≤ 8px where practical. Do not use control padding as an excuse for loose card insets.
5. **Page shell:** `.container` and tab panes stay tight (today’s ~4px is fine). Do not reintroduce large page gutters.
6. **Migration:** existing `admin.css` still has many 12–24px insets — treat them as debt. Opportunistic when editing a surface; no big-bang restyle unless asked.

Optional future tokens on `:root` (when first refactoring a shared surface): `--space-0: 0`, `--space-1: 4px`, `--space-2: 8px`. Until then, use the literal values above.

### Operator colour roles (toolbar / Status)

| Colour | Use | Rule of thumb |
|--------|-----|----------------|
| **Green** | Suggested next step / constructive apply / saved | **At most one** green text button visible on a toolbar |
| **Amber** | Needs attention (findings, dirty save, caution) | Catch the eye; not the click path |
| **Red** | Errors and irreversible confirms | Critical findings, delete confirms |
| **Grey** (`.btn`) | Optional alternate paths | Full check, Force, Not now, Copy log |
| **Coral** (`.btn-primary`) | Legacy affirmative outside Status | Prefer amber Save / grey secondary; see Colour caution |

## Text buttons

Prefer **one class ladder**. Unstyled `button` elements without a `class` keep the coral default; classed controls use the ladder below.

| Class | Meaning | When to use |
|-------|---------|-------------|
| `.btn` | Neutral secondary | Cancel, alternate / optional actions |
| `.btn.btn-secondary` | Alias of `.btn` | Legacy markup |
| `.btn.btn-primary` | Affirmative coral (legacy) | Prefer amber Save / grey secondary on new chrome; see Colour caution |
| `.btn.btn-good` | Recommended next step | Exactly one on Site health (and similar toolbars) |
| `.btn.btn-amber` | Dirty / needs attention | Save controls while unsaved (`content-save-ui.js`) |
| `.btn.btn-saved` | Saved / idle success | Save controls after successful save |
| `.btn.btn-danger` | Destructive confirm | Delete / irreversible confirms |
| `.btn.btn-danger-outline` | Soft destructive / discard | Leave without saving |
| `.btn-sm` | Compact size (12px; base `.btn` is 13px) | Dense toolbars, inline resume / secondary actions |

Legacy standalone `.btn-primary` (without `.btn`) remains for older markup; new code should use `.btn.btn-primary`.

### Site health action ladder

On **System → Status**:

0. **Status** landing → enter **Site health** (only Status tool for now). No Activity on this page.  
1. **Action** hub (`Status > Site health`) → each guide panel is headed by its action button (Quick / Full / Force); **Quick health check** is the single green recommended step; Full and Force stay grey. No Good/Bad/Ugly and no Activity here.  
2. **Action running** (`… > Quick check` / `Full check` / `Force rebuild`) → phase checklist (driven by Activity `HEALTH_PHASE` tokens) + Stop, with **Activity** visible underneath for the live detail. Hide hub guides, summary, and findings. Force checklist splits delivery into **Rebuild streams** then **Rebuild artwork**, then video / links / icons / follow-up.  
3. **Diagnosis** (`… > Diagnosis`) → always open after a finished Quick / Full / Force (even when healthy). Good/Bad/Ugly + meta; **Review treatment** is the green next step when findings exist. Activity stays visible (plain **Activity** heading). Hub **Open last Diagnosis** resumes here.  
4. **Proposed treatment** (`… > Diagnosis > Proposed treatment`) → auto-fixable findings are **Yes** by default (Apply only those selected); **Apply treatment** (green) + **Not now** + **Back up first…** sit under the panel. Amber assurance carries the how-to line; the grey note under it is backup-only (no duplicate guidance). Multi-campaign orphan homes and data-container orphans stay Manual: each row shows a choice strip (campaign chips, or a dropdown when there are more than five). The selected chip keeps a clear green border; peers stay muted. Container rows end with **or Delete** in the same strip (no separate Adopt/Set row buttons). Once every row has a choice, the finding’s **Include** Yes|Skip enables (same greenlit pattern as autofix); Apply runs the chosen adopts / deletes / catalogue-home stamps, then any Yes autofix treatments. Manual findings omit the “Suggested treatment” footer (Found this + choices are enough). Row cards use hairline separators only — not amber-nested. Ephemeral `data/` leftovers and unambiguous container registry fixes stay Yes for Apply.  
5. **Apply** returns to Action running (treat checklist), then **Treatment result**; Continue lands on Diagnosis (or remaining findings Review).  
6. Checks older than **1 hour** are out of date: do not show Healthy or Good/Bad/Ugly from that plan — badge is **Out of date**, Diagnosis shows a short notice, and Status **auto-starts Quick health check** (Action checklist). Review/Apply stay refused until the fresh check finishes.  
7. After **Site update**, Status also auto-starts Quick health check directly (skips the Status / Action hubs). Stale auto-start runs only from the **Status** landing — not while the operator is on the Action hub choosing Full/Force, and never as a banner over an already-running job.  
8. Attention findings use amber cards; critical findings use red cards.  
9. **Proposed treatment** uses `Include:` **Yes** \| **Skip** segmented toggles (not checkboxes); campaign Adopt choices stay chip toggles (dropdown only when more than 5 campaigns).

### Save-state machine

`biblioteca/content-save-ui.js` toggles:

1. hidden or neutral when clean and never saved this session  
2. `.btn-amber` when dirty  
3. “Saving…” while in flight  
4. `.btn-saved` (disabled) after success  

Do **not** invent a second amber/green save pattern.

## Same-factory editor chrome

Editors that can change install data should look like they came off one line — Content pool→editor, Files track/visual drilldowns, and similar modals. Prefer shared classes and the placements below over one-off footers or mid-form Save buttons.

**Content pool→editor reference:** breadcrumb head + **inline entity title** (`.content-editor-name-input`) + Save\|Saved / ← Back on the right. New Content editors follow that factory; do not invent a static title + separate name field pattern.

### Working chrome (top right)

While the operator is editing, **status and save state live on the right edge of the working head** — the place they are already looking:

| Slot | Placement | Contents |
|------|-----------|----------|
| Head **actions** (right) | Breadcrumb `.content-editor-card-head-actions`, or the equivalent modal/header actions row | ← Back (page editors) · **Save \| Saved** via `content-save-ui.js` · ★ Set as default/base when present · Status health badge on System → Status |
| Head **status** (near actions) | Same row or immediately beside it | Short working line when useful (“Close to save”, “Unsaved changes”, “Saving…”) — not a second Save button in the form body |

Do **not** bury the only Save control halfway down a form while Download / Delete / Close live in a disconnected footer. Form fields stay in the body; chrome owns leave/save.

### Footer / modal action row

Footer `.modal-actions` (and track-editor `.audio-master-modal-actions`) hold **leave and secondary** actions, left-to-right preference:

1. **Abort** (or soft destructive discard) — discard without writing (`.btn-danger-outline` or quiet grey when not alarming)
2. Neutral secondaries — **Download**, optional tools (`.btn`)
3. Affirmative leave — **Done** / **Close** when that path saves (see Close vs Abort)
4. Hard **Delete** when the surface owns irreversible remove (`.btn-danger`) — keep visually distinct from Abort/Done

Page editors use ← Back on the head instead of Done; destructive pool deletes stay on list toolbars / confirm modals, not mixed into Save chrome.

## Close vs Abort (every mutating editor)

Any surface that can modify registry, masters, containers, brands, or config must make **leave-with-save** and **leave-without-save** obvious. Prefer **save on close** so routine Done / ✕ / backdrop / ← Back does not strand dirty work — but never without an explicit discard path.

| Control | Meaning | Must write? |
|---------|---------|-------------|
| **Done** / **Close** / ✕ / backdrop / ← Back (clean or after save-on-close) | Leave the editor; **persist** dirty edits (save-on-close or flush queued autosave) | Yes, when dirty |
| **Abort** | Leave **without** writing. Local edits discarded. Synonym on page leave prompts: **Discard** (`#contentUnsavedModal`) | No |
| **Cancel** (on the unsaved leave modal only) | Stay in the editor — neither save nor leave | No |
| Explicit **Save** / **Saved** (head) | Manual flush while staying; still use the amber→Saved machine | Yes |

Rules:

1. **If it can modify, it must offer Abort (or Discard on leave).** A lone **Close** that always saves (or never saves) without a discard path is not allowed.
2. **Save-on-close is preferred** for modal editors (reference: Files → Audio track editor — Done / ✕ / backdrop save; **Abort** discards). Status may say “Close to save” / “Unsaved changes.”
3. **Page editors** (Catalogue, Playlists, Galleries, Pages, Branding): ← Back / root crumb use `#contentUnsavedModal` — **Save** / **Discard** / **Cancel**. Field-level blur autosave is optional for settings; membership drops may autosave immediately — still expose Discard when a dirty batch exists.
4. **Vocabulary:** use **Abort** on modal footers; use **Discard** on the shared unsaved-leave modal. Same intent (no write). Do not label a save-on-close control “Abort.”
5. Validation or save errors keep the editor open; do not silently discard on failed close-save.
6. Read-only or non-mutating dialogs (help, pickers with no pending write) may use plain **Close** / **Got it** without Abort.

### Compliance snapshot

| Surface | Save-on-close / head Save | Abort / Discard | Notes |
|---------|---------------------------|-----------------|-------|
| Files → Audio track editor | Done / ✕ / backdrop | **Abort** | Reference modal |
| Content editors | Head Save\|Saved + leave modal | **Discard** on leave | `#contentUnsavedModal` |
| Files → Visual / SFX drilldown | Mid-form Save today | Missing Abort; Close does not save-on-close | **Target:** align with track editor (head Save\|Saved or save-on-close + Abort; footer Download grey · Delete danger · Done/Close) |

## Confirmation modals (`bandpromoConfirm`)

Shared markup: `#adminConfirmModal` with `.modal-box`, body `.card-note`, and footer `.modal-actions` (not page-unsaved helpers).

| Tone | Confirm button | Use |
|------|----------------|-----|
| `default` | `.btn.btn-primary` (coral) | Safe affirmative confirms outside the Status ladder |
| `good` | `.btn.btn-good` (green) | Recommended Status step (e.g. Apply treatment) |
| `quiet` | `.btn` (grey) | Optional / alternate Status paths (e.g. Force full rebuild) |
| `danger` | `.btn.btn-danger` (red) | Irreversible deletes |

Cancel stays `.btn`. Keep clear space above the button row (`.modal-actions` top margin). Prefer UK English body copy; use → in “Check → Review → Apply”.

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
| Save / Abort | Footer `.audio-master-modal-actions`: **Done** / ✕ / backdrop = save on close; **Abort** = discard (see Close vs Abort). Reference implementation for mutating Files modals. |
| Audio list columns | Compact `.audio-pool-toolbar` + shared grid; All/None `.audio-select-chip` in header; `[data-audio-sort]` for client sort |

Edits stay local until close (or Abort). Validation or save errors keep the modal open.

## Visual / Sound effects lists (Files → Visual, Sound effects)

Same operator patterns as Audio, plus Grid/List on Visual:

| Pattern | Behavior |
|---------|----------|
| Toolbar | Shared `.audio-pool-toolbar` density. Visual / SFX: two-row chrome, both rows **left-aligned** — **View:** / **Filter:** (Visual) or filters (SFX) on row 1; **Actions:** clusters on row 2 (not `margin-left: auto`). Visual View: Grid/List · S/M/L; Filter: type · campaign · brand · title. Actions: **Upload** · catalogue **Assign**/**Remove** (💿) · brand **Use in brand**/**From brand** (🎨) · **Use in gallery** (🖼️) · **Download**/**Delete**. Icons match Content → Catalogue / Branding / Galleries / Playlists (🎵). Audio keeps a single row with actions trailing when space allows. No permanent “no undo” banner. Visual thumbs: ▶ on videos (hides on hover). Files → Visual tab uses 🎞️. Sticky under the Files sub-tab bar. |
| Selection | All/None `.audio-select-chip` in `.visual-pool-col-headers`; checkbox click updates selection. Pool summary is overlaid on the actions track of `.media-pool-headers-bar` (two lines: `n files` / `x MB total`) so List sort columns keep the same grid tracks as the rows. |
| List mode | Title / Catalogue\|Brand / Dimensions / Size. Visual **Catalogue** is campaign home. Brand-library members with no home list that Brand, not Orphan. In use / Unused is live assignment (cover, gallery, page, poster, or brand shell slot). |
| Grid mode | Thumbnails + caption under each card; column labels stay as sort chips with All/None; **S / M / L** scales grid tile density (same control as List) |
| Search | “Filter by title…” |

The Files → Brand assets tab is retired (`fpanel=special` → Visual). Branding shell pickers browse Visual / Sound effects.

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
