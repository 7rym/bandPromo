# Admin UI design system

Operator chrome for `admin.php` (and shared Content editor CSS). Public player branding tokens are separate (`docs/PLATFORM-MODEL.md`).

## Primary navigation

Main tabs (Dashboard, Analytics, Users, Files, Content, Settings, System, Documentation) remember the last used **sub-tab** in `localStorage` (`bandpromo_admin_nav_memory`). Switching Files → Content → Files returns to Visual (or whichever Files panel you left), not always Audio. Deep links that already name a sub-tab (`fpanel`, `cntab`, `ctab`, `stab`, `atab`, `doc_scope`) are unchanged.

## Page headings under the nav bar (required)

**Every operator page view** under the main tabs gets a **breadcrumb heading**. That includes Dashboard, Analytics, Users, Files and Content pools, Settings, System (Status / Audit / Backup / Environment), Documentation, and **mutating editor modals** (Audio track, Visual/SFX drilldown, and the same class of “edit this entity” dialogs).

**Exempt:** info / help dialogs, confirmation prompts (`bandpromoConfirm`, unsaved-leave, delete confirms), and other non-editing overlays with no place in the nav hierarchy.

Prefer breadcrumb + **inline-editable entity title** on editors — not a plain `h2` and not a separate “Title:” field buried in Base info for the entity name.

| View | Crumb |
|------|--------|
| Dashboard / Welcome | `📊 Dashboard` or `🌍 Welcome` (self-linked root) |
| Analytics | `📊 Analytics > Dash\|Hitlist\|Activities\|Patterns\|Log` |
| Users | `👥 Users` (account count in meta; Add User in actions) |
| Files pool | `📁 Files > 🎵 Audio\|🎞️ Visual\|🔊 Sound effects > Pool` |
| Files editor modal | `📁 Files > {panel} > Editor > [title]` (title input at the tail when editable) |
| Content pool / list | `📄 Content > {emoji} {Section} > Pool` |
| Content editor | `📄 Content > {emoji} {Section} > Editor` + title input at the crumb tail; section chips (when present) in the **left column header** |
| Settings | `⚙️ Settings > Basics\|Support\|Sharing` |
| System Status | Default: `🛠️ System > 📊 Status` landing (Site health **and** Storage tool cards — **no Activity**). Open Site health → `… > Status > Site health` Action hub (Quick / Full / Force; no Activity). Open Storage → `… > Status > Storage` (Chart.js host gauge + install/reclaim doughnuts and media-tier bar; discard eligible archival uploads; ← Back / Status crumb returns to landing). While a Site health job runs → Action checklist + Stop (mode in the crumb) with **Activity** visible underneath; no Good/Bad/Ugly. When finished → **Diagnosis** (Good/Bad/Ugly + Review when findings exist; Activity). **Review** → `… > Diagnosis > Proposed treatment`. **Apply** → Action checklist + Activity, then `… > Treatment result`. Hub resume opens last Diagnosis. Post–Site update / stale auto-start opens the Quick check checklist directly. |
| System Audit / Backup / Environment | `🛠️ System > 🛡️ Audit` · `🛠️ System > 💾 Backup, export & import` · `🛠️ System > 🖥️ Environment` |
| Documentation | `📚 Documentation > Operator docs\|Developer docs\|All docs` |

### Breadcrumb line layout

One row under the Content sub-nav (`.content-editor-card-head`): **height is always 33px** (locked), whether ← Back / Save are shown or hidden on Pool. Do not let the row collapse to the bare crumb line-height.

| Slot | Placement | Use |
|------|-----------|-----|
| Crumb | Left | `📄 Content` + section (`📄 Pages`, …) as **underlined navigation controls** (buttons — same leave/unsaved path as ← Back) + `> Pool\|Editor` |
| `trailing` | Immediately after the crumb | Optional muted meta only. Section chips live in the **left column header** (Pages Base info\|Page builder; Catalogue; Branding). |
| `actions` | Right edge (`.content-editor-card-head-actions`) | ← Back + Save\|Saved (and ★ Set as default / ★ Set as base when that editor has them). Hidden while not `.is-editing`. Status puts the overall health badge here — on the Status / Site health Action chooser a prior healthy plan is labelled **Last check healthy** (not a live clean bill); green **Healthy** appears after you open Diagnosis or finish a check. |
| `after_path` | Tail of the crumb | Inline entity title (`.content-editor-name-input`) while editing — all Content pool→editors and Files Visual/SFX modals. **Exception:** Files → Audio track editor keeps a read-only compound `Artist · Title` readout (Artist and Title stay separate fields in Details). Hidden on Pool. |

- Markup: `bandpromo_admin_render_content_breadcrumb()` in `biblioteca/admin-helpers.php`.
- **Row height:** `.content-editor-card-head` / `.campaign-editor-card-head` = **33px** everywhere (box-sizing border-box; nowrap; overflow hidden). Pool without actions must not shrink.
- Preferred API: `path` — ordered crumb items (`text`, optional `href` / `button` / `current` / `id` / `hidden`, plus `type => sep|slot`).
- Presets: `bandpromo_admin_breadcrumb_preset_page()`, `_files_pool()`, `_content_section()`, `_system()`.
- Legacy `emoji` / `label` / `segments` / `current` still normalise into a path.
- Options: `wrap_head` (default true), `tag` (`h2`|`nav` for modals), `trailing`, `actions`, `after_path`.
- **Preference:** breadcrumb roots are always visually links (underlined). Do not render a plain muted span for the section root unless there is truly no destination.
- Behaviour: `bandpromoContentEditorBreadcrumb.attach()` — `setView('pool'|'edit')` on lifecycle show hooks (Content editors). Page-level and System roots use `href` on path items.

### Inline entity title (Content editors)

While editing, the **entity name is an inline text field at the breadcrumb tail** (`after_path`). Not a static `h3` and not duplicated as a “Title:” row in the settings body. **Pages** is the canon for split pool\|editor + Live preview; Catalogue, Playlists, Galleries, and Branding follow the same factory.

| Pattern | Detail |
|---------|--------|
| Control | `.content-editor-name-input` (Branding also uses `.brand-editor-name-input`) |
| Placement | Crumb tail (`after_path`) while editing |
| Behaviour | Edit in place; dirty state feeds the head Save\|Saved machine / leave Discard path |
| Preview | Pool / Live preview column headers use the same in-flow `--admin-slate-wash` as Available content — title-only readout, no Save strip, no second name field |

**Split pool \| editor + Live preview:** both column headers share that slate wash and **48px** height (`padding: 8px` max — compactness). Left pool title is always **Available content**. Section chips sit in the left column header on **one row** (nowrap; thin horizontal scroll if the column is narrower than the chip strip — never wrap under the locked 48px). Chip buttons use tight `4×8` padding. Pool row tools and Add / Create / Browse / Change-media actions use **Available** muted green (`.media-action-good` / `.btn.btn-available`); grey only when disabled. Branding **+ Add brand** duplicates the current base brand. Undeletable / locked / required / protected rows keep a disabled 🔒 in the Delete slot (`bandpromoRegistryList.protectedButton()`) so action columns stay aligned. Catalogue / Playlists pool right column is **Live preview** (edit may switch to a working list title such as Associated tracks / Playlist / Gallery order). Branding / Playlists status chips (**Base** / **Default**) sit next to the name; **★ Set as base** / **★ Set as default** appear in actions only when that entity is not already base/default.

Do **not**:

- Invent a second under-nav title pattern for new Content pool→editor flows
- Leave ← Back / Save only in split-editor headers when the breadcrumb row is present
- Put the canonical entity name only inside Base info / settings while the header shows a non-editable label
- Use a plain muted span for the breadcrumb root when a destination exists

Files modals (Visual / SFX drilldowns) use a full **Files > panel > Editor > [title]** breadcrumb. Files pools must show **Files > panel > Pool** (same factory). Files / panel crumbs leave via the same save-if-dirty path as ✕. Footer affirmative leave is **Save** (always write). Do not put a second Title field in the Details body.

## Action colour model (intent × emphasis)

Operator actions use **two axes**. Hue says *what kind* of act at the moment it **matters**; fill weight says *how hard we push*.

**Warn at commitment, not on every trigger.** Delete and Abort are normal viable actions in working chrome — we always confirm before irreversible delete, and we confirm discard when there are unsaved changes. Do not paint every Delete/Abort as danger/caution just because a later confirm might.

### Intent (hue) — mostly on confirms

| Intent | Hue | Where it belongs |
|--------|-----|------------------|
| **Constructive** | Green | Working chrome: upload, assign, download, play, edit, save, delete trigger, abort trigger |
| **Caution** | Amber | **Confirm** discard when dirty; dirty head Save; attention chips / findings — not the idle Abort control |
| **Danger** | Red | **Confirm** irreversible delete / hard commit — not every Delete trigger in a toolbar or footer |
| **Unavailable** | Grey | Disabled only |

### Emphasis (fill)

| Emphasis | Fill | Meaning |
|----------|------|---------|
| **Proposed** | **Solid** green | The suggested next step — **at most one** solid green on the surface |
| **Available** | **Muted** green + light outline | Viable actions (including Delete and Abort triggers); **several OK** |
| **Unavailable** | Muted grey | Disabled — not “secondary but clickable” |
| **Caution commit** | **Solid** amber | Discard / leave-without-save **confirm** when there are changes |
| **Danger commit** | **Solid** red | Delete **confirm** (and similar irreversible commits) |

**Grey means unavailable.** Enabled Download / Delete / Abort / Assign stay **available green** (muted). Do not grey them to “soften” the UI.

### Combined roles (canonical)

| Role | Look | Examples |
|------|------|----------|
| **Proposed** | Solid green | Footer **Save**; Status **Quick health check** / **Apply treatment**; when nothing is selected in a Files pool, **Upload** is often the only available action and may keep today’s muted-green look (or solid if we want it proposed) |
| **Available** | Muted green | Assign, Use in brand, Download, Listen, Edit, **Delete** trigger, **Abort** trigger, Full/Force beside a solid proposed step |
| **Unavailable** | Muted grey | Toolbar actions that need a selection (disabled Download / Delete / Assign…) |
| **Caution commit** | Solid amber | Unsaved-leave **Discard**; dirty-abort confirm |
| **Danger commit** | Solid red | Delete confirm dialog |

Status ladder: one **proposed** solid green; alternates are **available** muted green when enabled.

### Files pool with no selection

You are not wrong: with an empty selection, **Upload** is the only enabled action in the Actions cluster. Assign / Use in … / Download / Delete stay **unavailable** (grey). Upload already reads as the path forward in muted green — that is correct. When a selection exists, those peers flip to **available** muted green; Upload stays available too (several muted greens OK). Only promote one control to **solid** proposed when the surface has a single suggested commit (e.g. modal **Save**, Status **Apply**).

### Class mapping (current → target)

| Role | Prefer today | Notes |
|------|--------------|-------|
| Proposed green | `.btn.btn-good` | Strengthen toward solid when implementing the solid tier |
| Available green | `.media-action-good` | Dimmed green + outline — includes Delete/Abort triggers |
| Unavailable | `[disabled]` + grey muted | Only when the action cannot run |
| Caution commit | `.btn.btn-amber` on **confirms** | Not the idle Abort button |
| Danger commit | `.btn.btn-danger` on **confirms** | Not every trash icon |
| Idle Abort | Available muted green (or neutral available) | Not solid amber |
| Idle Delete | Available muted green | Not muted red / not grey-when-enabled |
| `.media-action-danger` (grey quiet delete) | Retire for enabled deletes | Grey = unavailable only |

### Colour caution (accent vs red)

`--accent` / `--primary` (`#3a6a94`) is a deep steel blue for dark admin chrome; `--error` stays red (`#f44336`). Prefer intent greens / amber / grey for actions. **Do not** use accent as a general “important button” colour beside Delete, and do not rely on hue alone to separate safe vs destructive.

## Sticky toolbars

**Sticky** means: stay in normal flow while visible; pin only when scroll would push the chrome off the top of the viewport. Not permanently floating chrome.

| Surface | When it sticks |
|---------|----------------|
| Files / Content / System sub-tab bars | Page scroll would hide them (`top: 0`) |
| Files pool breadcrumb + filter/actions + column headers (`.media-pool-sticky-chrome`) | Would leave the viewport under the Files sub-tab bar |
| Content / Status breadcrumb head (`.content-editor-card-head`) | Would leave under the section sub-tab bar |
| Split-editor column titles (Available / Active) | **Not sticky** — stay in normal flow (nesting under the breadcrumb + `overflow: hidden` panels pulled list rows under the titles) |

Do not nest sticky headers under another sticky on the same scroll axis (Content breadcrumb, Branding Live preview column, page rich-text under the builder add-block head).

**Opaque sticky chrome (locked):** every sticky toolbar/header that sits over scrolling content uses `--admin-sticky-chrome-bg` (`#0f172a`) — Files pool (`.media-pool-sticky-chrome`), Content / Status breadcrumb heads (`.content-editor-card-head`), and the page-builder add-block head (`.page-editor-panel-head`, under `--page-builder-sticky-top`). Do **not** use the translucent slate wash on sticky chrome (list rows show through). In-flow (non-sticky) split-editor column headers, Files modal headers, and page rich-text formatting bars may keep wash / stay with their block. Sub-tab bars stay opaque `--accent`.

**Page builder stack:** the add-block sticky head uses `--page-builder-sticky-top` (sub-tabs + breadcrumb height), never `top: 0` (that overlapped the accent Content bar). Rich-text formatting bars stay **in-flow with their block** — do not nest a second sticky under the add-block head.

## Form field grids (locked lessons)

Prefer **flat rows** of peer fields. Do **not** stack several fields inside one grid cell beside siblings (e.g. Artist + Featured + Remix as a vertical stack next to Title|Version) — that reads as chaos once labels sit above inputs.

| Prefer | Avoid |
|--------|--------|
| One logical group per row (`Artist \| Featured \| Remix`) | Multi-field vertical stack in a single grid cell |
| Narrow fixed-width inputs for short codes (BPM ≤3ch, Key ≤4ch) with a flex sibling filling the rest (Genre) | Stretching BPM/Key to fill leftover column width |
| Compact ISO date on its own row when it is not part of a short inline pair | Parking Release date under BPM in a nested stack |

Force `#audioMasterForm .playlist-settings-field` (and peers) to `display: flex; flex-direction: column` so every field keeps **Label:** above the control. Narrow fixed-width inputs otherwise sit **inline** with their label and misalign the row.

## Modal long textareas (single scroller)

Mutating Files modals (Audio track, and peers with long prose) autosize blurb / Lyrics|Notes (and similar) textareas to content (`overflow: hidden`, JS height from `scrollHeight`, schedule after layout). The **modal body** is the only vertical scroller — never nest a second scrollbar inside a textarea while the modal also scrolls.

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

### Admin toasts (`showAdminToast`)

```js
showAdminToast(message, type /* success|warning|error */, durationOrOptions)
// durationOrOptions: number (seconds) | { durationSeconds, manualClose }
```

| Default | Behaviour |
|---------|-----------|
| `success` (and other non-error) | Auto-dismiss after **10 seconds** (dismiss × still available) |
| `error` / `warning` | Stay until the operator dismisses (`manualClose` equivalent) unless a duration is passed |
| `{ durationSeconds: N }` | Auto-dismiss after N seconds (`0` = manual only) |
| `{ manualClose: true }` | Never auto-dismiss |

Use short lifetimes for routine save confirmations; keep errors manual so they are not missed.

## Palette (canonical)

Defined on `:root` in `biblioteca/admin.css`:

| Token | Role |
|-------|------|
| `--accent` / `--primary` | Deep steel blue (`#3a6a94`) — sub-tabs, links, focus, help tint; **prefer amber Save / green Saved / grey secondary** for actions |
| `--accent-h` | Deeper hover (`#2f5780`) |
| `--accent-rgb` | Channel form of accent for translucent `rgba(var(--accent-rgb), …)` washes |
| `--admin-help-bg` / `--admin-help-border` / `--admin-help-marker` | Sub-tab help panel (derived from `--accent-rgb`) |
| `--admin-help-text` / `--admin-help-strong` | Help body / bold tip text |
| `--success` | Positive completion and the **recommended next step** |
| `--warn` | Attention / dirty / important information |
| `--error` | Hard destructive / validation failure / critical findings |
| `--muted` | Secondary text and quiet controls |
| `--intent-good-*` | Green constructive icon actions |
| `--intent-warn-*` | Amber caution / preview icon actions |
| `--intent-quiet-*` | Unavailable / muted grey shells (not “quiet delete”) |

### Colour caution (accent vs red)

`--accent` / `--primary` (`#3a6a94`) is a deep steel blue for dark admin chrome; `--error` stays red (`#f44336`). Prefer intent greens / amber / grey for actions. **Do not** use accent as a general “important button” colour beside Delete, and do not rely on hue alone to separate safe vs destructive.

Legacy `.btn-primary` (now accent blue) remains for older safe confirms until those screens are migrated; new chrome uses the **intent × emphasis** table above. Modal footers: solid green **Save** (proposed); muted-green **Download** / **Delete** / **Abort** when available; solid red / solid amber only on **confirms**.

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

See **Action colour model** above. Short form:

| Colour + fill | Use |
|---------------|-----|
| **Solid green** | Exactly one **proposed** next step (Save, Apply, Quick check) |
| **Muted green** | Any number of **available** actions — including Delete and Abort triggers |
| **Solid amber** | Caution **commit** (discard confirm when dirty; dirty head Save) |
| **Solid red** | Danger **commit** (delete confirm) |
| **Muted grey** | **Unavailable** only |
| **Accent** | Legacy primary — do not extend for new actions |

## Text buttons

Prefer **one class ladder**. Unstyled `button` elements without a `class` keep the accent default; classed controls use the ladder below.

| Class | Meaning | When to use |
|-------|---------|-------------|
| `.btn` | Neutral shell (prefer intent classes) | Rare — prefer available green / muted red / amber |
| `.btn.btn-secondary` | Alias of `.btn` | Legacy markup |
| `.btn.btn-primary` | Affirmative accent blue (legacy) | Do not extend; see Colour caution |
| `.btn.btn-available` | **Available** muted green | Download / Delete / Abort triggers; several OK |
| `.btn.btn-good` | **Proposed** constructive (solid green tier) | Exactly one proposed step (Save, Apply, Quick check) |
| `.btn.btn-amber` | **Caution commit** | Discard confirm when dirty; dirty head Save — not idle Abort |
| `.btn.btn-saved` | Saved / idle success | After successful save |
| `.btn.btn-danger` | **Danger commit** (solid red) | Confirm dialogs only |
| `.btn.btn-danger-outline` | Legacy soft danger | Prefer available muted green for idle Delete; keep outline only if a surface still needs a red hint |
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
| Head **status** (near actions) | Same row or immediately beside it | Short working line when useful (“Unsaved changes”, “Saving…”) — not a second Save button in the form body |

Do **not** bury the only Save control halfway down a form while Download / Delete / Close live in a disconnected footer. Form fields stay in the body; chrome owns leave/save.

### Footer / modal action row

Footer `.modal-actions` (and track-editor `.audio-master-modal-actions`) hold leave and secondary actions. **Visual / SFX reference order** (left → right):

1. **Download** — available muted green when enabled; grey only when disabled
2. **Delete** — available muted green (confirm is solid red)
3. **Abort** — available muted green (dirty discard confirm is solid amber)
4. **Save** — proposed solid green — always write then close (even when clean)

Page editors use ← **Back** on the head instead of Save-in-footer.

## Leave control vocabulary (canonical)

Use one label per intent. Do not invent synonyms on new screens.

| Label | Where | Meaning |
|-------|-------|---------|
| **Save** / **Saved** | Content page editor head (amber → green disabled) | Write while **staying** in the editor |
| **Save** | Files → Visual / SFX / Audio modal footer (green) | Always write (even if clean), then leave |
| ← **Back** | Content page editor head / breadcrumb root | Leave the page editor (may open unsaved modal) |
| **Abort** | Mutating modal footers (available muted green) | Leave **without** writing; if dirty, confirm with solid amber |
| **Discard** | Shared unsaved-leave modal only (`#contentUnsavedModal`) | Same as Abort — page-editor wording |
| **Cancel** | Unsaved-leave modal only | Stay — neither save nor leave |
| **Close** / **Got it** | Read-only or non-mutating dialogs only | Dismiss with **no** write path |

Rules of thumb:

1. Mutating Visual/SFX modals → solid green **Save** + available muted-green **Abort** / **Delete** (confirms: solid amber discard if dirty, solid red delete).
2. Page editors → head **Save\|Saved** + ← **Back**; leave prompt uses **Save** / **Discard** / **Cancel**.
3. Help, pickers, confirms with no pending write → **Close** or **Got it**.
4. Do **not** label a write-then-leave control **Close**. Prefer **Save** on Files mutating modals (explicit write).
5. ✕ / backdrop / crumb leave on Visual/SFX stay **save-if-dirty** (no forced empty write); the footer **Save** button always writes.

## Close vs Abort (every mutating editor)

Any surface that can modify registry, masters, containers, brands, or config must make **leave-with-save** and **leave-without-save** obvious. Prefer **save on close** so routine leave does not strand dirty work — but never without an explicit discard path.

| Control | Meaning | Must write? |
|---------|---------|-------------|
| **Save** (Visual/SFX footer) | Leave after writing | Always (when editable) |
| ✕ / backdrop / crumb / **Done** (Audio) | Leave; persist dirty edits | Yes, when dirty |
| **Abort** | Leave **without** writing. Synonym on page leave prompts: **Discard** | No |
| **Cancel** (unsaved leave modal only) | Stay — neither save nor leave | No |
| Explicit head **Save** / **Saved** | Manual flush while staying | Yes |

Rules:

1. **If it can modify, it must offer Abort (or Discard on leave).**
2. **Save-on-close / save-if-dirty** for passive leave (✕, backdrop, crumb). Visual/SFX footer **Save** always writes.
3. **Page editors:** ← Back / root crumb use `#contentUnsavedModal` — **Save** / **Discard** / **Cancel**.
4. **Vocabulary:** **Abort** on modal footers; **Discard** on the unsaved-leave modal. Affirmative Visual/SFX leave is **Save**, not **Close**.
5. Validation or save errors keep the editor open.
6. Read-only dialogs may use **Close** / **Got it** without Abort.

### Compliance snapshot

| Surface | Affirmative leave | Abort / Discard | Notes |
|---------|-------------------|-----------------|-------|
| Files → Audio track editor | Footer **Save** (force write) / ✕ / backdrop / crumb | Available **Abort** (dirty → warn confirm) | Full crumb; **read-only** compound `Artist · Title` at crumb tail (exception — not inline-editable); Visual-style **File** / **Details** grouping; footer Download → Delete → Abort → Save |
| Content editors | Head Save\|Saved + leave modal | **Discard** on leave (amber commit) | `#contentUnsavedModal` |
| Files → Visual / SFX drilldown | Footer **Save** (always write); ✕ / crumb = save-if-dirty | Available **Abort** (dirty → amber confirm) | Full crumb; available Delete; Captured ISO picker |

## Date fields (ISO picker)

Every admin field that stores a calendar date uses the shared **ISO date** control — not a bare `<input type="text">`, not a decorative calendar icon without a picker, and not a locale-dependent free-text box.

| Piece | Contract |
|-------|----------|
| Markup | `.iso-date-field` (filter bars) or `.date-input-shell.iso-date-field` (forms) via `bandpromo_admin_render_iso_date_field()` when rendering from PHP |
| Script | `biblioteca/iso-date.js` — auto-binds; call `bandpromoSyncIsoDateField()` after setting the value from JS |
| Value | Canonical `YYYY-MM-DD`; year-only `YYYY` allowed when the field opts in (`allow_year_only` / form variant — track Release date, Visual **Captured**, etc.) |
| Chrome | Text input + 📅 button that opens the native date picker |
| Layout | Prefer inline `Label:` + control (same row) when the panel width allows |

### Canonical look (forms / editors)

Locked operator preference — **Files → Visual / SFX Captured** is the exemplar. All form/editor dates should match:

1. **Calendar on the left** of the field (not the right).
2. **Padding clears the icon** — left padding (~44px) so `YYYY-MM-DD` never sits under the 📅 button.
3. **Compact width** — sized to icon + ISO string (`calc(44px + 12ch)`), not stretched to the full column or a wide shell when inline with `Label:`.
4. Monospace ISO digits; placeholder / validation stay ISO.

Shared styles live on `.date-input-shell` in `biblioteca/admin.css`. Do not invent a second date widget. Analytics filters, Catalogue/Playlist publish dates, track Release date, and Visual Captured all share this contract. Dense filter-bar dates may stay slightly smaller, but keep the same value/script rules; prefer left-calendar clearance when reshaping them.

## Confirmation modals (`bandpromoConfirm`)

Shared markup: `#adminConfirmModal` with `.modal-box`, body `.card-note`, and footer `.modal-actions` (not page-unsaved helpers).

**Stacking (locked):** `#adminConfirmModal` z-index sits **above** Files editors and the media picker (`#audioMasterModal` / `#poolAssetModal` / `#mediaPickerModal`) so Abort discard and delete confirms are never buried under the editor overlay.

| Tone | Confirm button | Use |
|------|----------------|-----|
| `default` | `.btn.btn-primary` (accent) | Safe affirmative confirms outside the Status ladder |
| `good` | `.btn.btn-good` (solid green) | Recommended Status step (e.g. Apply treatment) |
| `warn` / `amber` | `.btn.btn-amber` (solid amber) | Dirty discard / leave-without-save confirm |
| `quiet` | `.btn` (grey) | Optional / alternate Status paths (e.g. Force full rebuild) |
| `danger` | `.btn.btn-danger` (solid red) | Irreversible deletes |

Cancel stays `.btn`. Keep clear space above the button row (`.modal-actions` top margin). Prefer UK English body copy; use → in “Check → Review → Apply”.

## Icon / compact actions

| Class | Meaning |
|-------|---------|
| `.icon-btn` | Default compact control (neutral border) |
| `.icon-btn.icon-btn--danger` (or `.icon-btn.danger`) | Hard destructive icon — red |
| `.icon-btn.icon-btn--pool` | 28×28 pool-row tool |
| `.icon-btn.icon-btn--pool.icon-btn--danger` | Pool delete — red |
| `.icon-btn.icon-btn--pool.icon-btn--active` | Active lock / selected tool |
| `.media-action-btn.media-action-good` | **Available** (muted green) — including Delete/Abort triggers when enabled |
| `.media-action-btn.media-action-amber` | Caution / preview chips — not idle Abort |
| `.media-action-btn.media-action-danger` | **Retire** for enabled deletes — grey is unavailable only |
| `.player-layout-remove-btn` | In-row ✕ remove (muted → red on hover) |
| `.gallery-remove-btn` | Alias of `.player-layout-remove-btn` |

Semantic hooks for event delegation (also carry the `icon-btn` classes above):

- `.page-pool-edit-btn` / `.page-pool-lock-btn` / `.page-pool-duplicate-btn` / `.page-pool-delete-btn`

### Danger vs quiet delete

- **Solid red** (`.btn-danger`): delete **confirm** only.  
- **Available muted green**: Delete **trigger** in toolbars / footers / rows / Content pool icons (confirm always follows for registry deletes).  
- **Muted grey**: **unavailable** only (disabled), or non-destructive dismissals.  
- **In-row ✕** (`.player-layout-remove-btn`): muted until hover — membership remove, not registry delete.

Do not warn with red/amber on every trigger. Modal footer: solid green **Save**, muted-green **Abort** / **Delete** / **Download**. Pool `.registry-btn--delete` / `.icon-btn--danger` triggers use available green tokens.

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
| Identity row | Artist · Featured artist · Remix artist (`.audio-master-form-grid-artists`) |
| Title row | Title · Version (`.audio-master-form-grid-title`) |
| Music row | Genre (flex) · BPM (3ch) · Key (4ch) (`.audio-master-form-grid-music`) |
| Date row | Release date alone (`.audio-master-form-grid-date`) |
| Blurb | Label trails Markdown hint; textarea autosizes to content |
| Lyrics / Notes | Compact pill `.audio-master-text-role-toggle` / `.audio-master-text-role-btn`; Restricted Markdown hint trails the toggler; textarea autosizes (modal scrolls — no inner scrollbar) |
| Listen preview | Compact `<audio>` under Master audio asset (`.audio-master-listen-bar`); Files rows use ▶ → `#adminAudioListenDock` via `audio.php` (`.media-action-good`) |
| Save / Abort | Footer: **Abort** (available muted green; dirty → warn confirm) · **Save** (solid green — always write then leave) / ✕ / backdrop / crumb = save-if-dirty |
| Head status | Quiet — no “Saving…” / “Saved” / “Unsaved changes” in the crumb; dirty shows as amber **Save**; errors only in the head when needed |
| Audio list columns | Compact `.audio-pool-toolbar` + shared grid; All/None `.audio-select-chip` in header; `[data-audio-sort]` for client sort |

Edits stay local until close (or Abort). Validation or save errors keep the modal open.

## Visual / SFX asset drilldown (Files → Visual, Sound effects)

Same leave factory as the Audio track editor (`#poolAssetModal`):

| Pattern | Behaviour |
|---------|-----------|
| Breadcrumb | `📁 Files > 🎞️ Visual > Editor > [title]` / `… > 🔊 Sound effects > Editor > [title]` — title is the final segment; Files / panel crumbs leave (save-if-dirty) |
| Title | `#poolAssetDisplayTitle` in the crumb when editable; otherwise static `#poolAssetTitle` after Editor (no Title field in Details) |
| Head status | Quiet — save success uses toast (10s); failures toast until dismissed |
| Preview | Aspect-aware stage (`portrait` / `square` / `landscape`) |
| File (static) | Chips: type · size · dimensions · Alpha · Preparing/Queued/Waiting · In use / Unused · In brand (library membership) |
| Details (dynamic) | Description (stacked); Keywords inline; **Captured** = shared ISO date picker; shared `--card` field backgrounds |
| Links | Catalogue\|Brand · Role address (when meaningful) · References |
| Footer | **Download** · **Delete** · **Abort** (all available muted green when enabled) · **Save** (solid green proposed) |
| Leave | Footer **Save** = always write then close; ✕ / backdrop / crumb = save-if-dirty; **Abort** discards |

Compactness: layout gaps and chrome margins stay within **0 / 4 / 8px**.

## Visual / Sound effects lists (Files → Visual, Sound effects)

Same operator patterns as Audio, plus Grid/List on Visual:

| Pattern | Behavior |
|---------|----------|
| Toolbar | Shared `.audio-pool-toolbar` density. Visual / SFX: two-row chrome, both rows **left-aligned** — **View:** / **Filter:** (Visual) or filters (SFX) on row 1; **Actions:** clusters on row 2 (not `margin-left: auto`). Visual View: Grid/List · S/M/L; Filter: type · campaign · brand · title. Actions: **Upload** · catalogue **Assign**/**Remove** (💿) · brand **Use in brand**/**From brand** (🎨) · **Use in gallery** (🖼️) · **Download**/**Delete**. Icons match Content → Catalogue / Branding / Galleries / Playlists (🎵). Audio keeps a single row with actions trailing when space allows. No permanent “no undo” banner. Visual thumbs: ▶ on videos (hides on hover). Files → Visual tab uses 🎞️. Sticky under the Files sub-tab bar. |
| Masters only | Files lists, previews, pickers, and downloads are **masters only**. There is no Original\|Master list toggle. Archival uploads are disposable intake; reclaim them from **System → Status → Storage**. |
| Selection | All/None `.audio-select-chip` in `.visual-pool-col-headers`; checkbox click updates selection. Pool summary is overlaid on the actions track of `.media-pool-headers-bar` (two lines: `n files` / `x MB total`) so List sort columns keep the same grid tracks as the rows. |
| List mode | Title / Catalogue\|Brand / Dimensions / Size. Visual **Catalogue** is campaign home. Brand-library members with no home list that Brand, not Orphan. In use / Unused is live assignment (cover, gallery, page, poster, or brand shell slot). **In brand** marks library membership (separate from assignment). **From brand** lists only brands the selection belongs to (plus every brand in the selection when spanned). |
| Grid mode | Thumbnails + caption under each card; column labels stay as sort chips with All/None; **S / M / L** scales grid tile density (same control as List) |
| Search | “Filter by title…” |

The Files → Brand assets tab is retired (`fpanel=special` → Visual). Branding shell pickers browse Visual / Sound effects.

## Markdown help (prose textareas)

Long player-facing prose textareas (track description, lyrics/Notes, release/playlist long description) show **Markdown** plus a **?** control (`.markdown-help-open`) that opens `#markdownHelpModal`. Short descriptions, titles, and page richtext stay plain / toolbar HTML.

**Placement (locked):** the Markdown hint **trails** its control’s label or toggler on the same line — e.g. `Track description / blurb: Markdown in playlist view (?)` and `Lyrics|Notes` toggler then `Restricted Markdown (?) …`. Do not park the hint under the textarea or above the toggler as a separate block.

Helpers: `bandpromo_admin_markdown_help_trigger()` / `bandpromo_admin_markdown_help_note()` in `biblioteca/player-markdown.php`.

## Sub-tab help (ⓘ) — canon: Files → Audio → Pool

One help chrome for every main tab (Dashboard, Analytics, Users, Files, Content, Settings, System, Documentation).

### Structure (locked)

1. **`.tabs.sub-tabs`** accent bar (`var(--accent)`) — sub-links when the tab has them; empty bar is fine for single-pane tabs such as Dashboard / Users / Docs.
2. **ⓘ** (`.help-toggle-btn`) on the **right** of that bar (`margin-left: auto`) — never inside the help panel.
3. **`.admin-help-box`** directly under the bar when open: `var(--admin-help-bg)` / `var(--admin-help-border)`, **`border-top: none`** so it attaches to the sub-tabs, rounded bottom corners only.

### Body (locked)

- Body is always a **`<ul>` of short operator tips** (usually 2–3 `<li>`), never a bare paragraph, never `<br><br>` blocks, never a solid accent-coloured banner for the tip body.
- List markers use `var(--admin-help-marker)`; body text `var(--admin-help-text)`; `<strong>` uses `var(--admin-help-strong)`.
- No per-tab CSS exceptions (do not restyle System Status / `#help-build` as a slate wash or detached card).
- Do **not** hardcode accent hex/rgba in rules — use `:root` tokens (`--accent`, `--accent-rgb`, `--admin-help-*`).

### Behaviour

- Toggle opens/closes the box; state in `localStorage` (`adminHelp_{key}`); default closed.
- Do **not** add a “Click here to toggle help…” hint strip or a second help chrome on Dashboard / Welcome.
- Section notes under card titles (e.g. Settings field explanations, Analytics chart captions) are **not** this pattern — keep those as ordinary muted prose under the heading.

## Dashboard (Welcome when setup is complete)

Order under the breadcrumb (locked):

1. Catalogue attention card (when Site health needs the operator) — optional
2. **Site update** strip (`#packageUpdateCard`)
3. Demo campaign hide suggest (when eligible) — optional
4. Quick actions
5. What to do next (when steps exist)

Compactness: dashboard cards / update strip use **8px** padding and margins (not the legacy 12–20px `.card` default). Primary CTAs use solid green `.btn.btn-good` (Hide demo, Install update, Open Site health); secondary stays plain `.btn` (Open Settings, Check again). Quick-action tiles use Available muted green hover — not accent primary and not a separate hover wash.

## Media picker campaign filter

Shared `#mediaPickerModal` filters by campaign with the query/body param **`campaign`** (not legacy `release`). Sending `release` is ignored and shows the unfiltered pool — a common foot-gun when wiring Pages / gallery / cover pickers.

## Content editor sections

Pages and Branding edit views group fields in `.content-editor-section` cards:

- Chrome header: `.content-editor-section-head` with `--border2` fill (nested section / block card bars — **not** the split column headers)
- Column headers (Available content / Live preview / Pages section chips): in-flow `--admin-slate-wash` (not sticky)
- Body: `.content-editor-section-body`

Pages: crumb `📄 Content > 📄 Pages > Pool|Editor` with title input at the crumb tail while editing; **Base info** \| **Page builder** chips in the left column header; add-block buttons use `.btn.btn-available`. Breadcrumb actions hold ← Back + Save|Saved. Playlists: **Base info** (Artwork, publish date, package type, play order As listed|Newest first toggle, Show artist Show|Hide toggle, slug, descriptions) with inline `Label:` chrome; Pool preview mirrors Catalogue (cover + blurb + Playlist details: tracks / package / campaign / play order / artist / URL); track order + available pool only while editing. **★ Set as default** / Branding **★ Set as base** sit on the breadcrumb actions row with ← Back and Save when the entity is not already default/base (status chips **Default** / **Base** stay next to the name). Catalogue (Campaign): name in the edit header; section chips (Base info | Extended info | Tracks | …) in the left column header; ← Back + Save|Saved on the right while editing; **Base info** (start / slug / press / branding / blurb) + **Media assets** (Artwork); **Extended info** holds **Press kit** (long description Markdown); Pool preview shows cover + brand + owned-content summary (Tracks / Playlists / Galleries / Pages); Base|Extended edit preview keeps cover + brand + long-description readout. Branding: Common | Player | Content chips in the left column header; (Player: **Cover** [Size Full|Medium|Half, Reflection, Side covers], **Controls** [full panel chrome], **User area** [Login / status, Beggars banquet]; Content: **Buttons**, **Playlist selector**, **Panels**, **Typography**). Prefer `Label:` + control on one line; segmented toggles over checkboxes; toggles over dropdowns when ≤5 alternatives; inline `Label:` + value sliders for continuous scales (see [AGENTS.md](AGENTS.md)).

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
