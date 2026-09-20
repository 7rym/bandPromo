# Session Handoff

## Resume point

Files modal polish **done** this session (Visual/SFX + Audio crumb parity). Canonical ISO date look registered (Captured exemplar → shared `.date-input-shell`, ADMIN-UI, AGENTS, local `.cursor/rules/iso-date-fields.mdc`). Checkpoint published.

**Next (optional):** breadcrumbs on remaining non-Files tabs; smoke filter-bar dates vs form dates if dense toolbar still uses right-side calendar; Content pool→editor polish only if something still drifts from ADMIN-UI.

### Shipped this session

- Full crumbs on Files pools + Visual/SFX/Audio editor modals
- Action colours: proposed solid green / available muted green / unavailable grey; amber|red only on confirms
- Visual/SFX: title in crumb after `Editor >` (`<nav>` so the browser keeps the input inline); Captured ISO + left calendar padding; Save force + toast (10s success); Abort/Delete available
- Toast API: `durationSeconds` / `manualClose`
- Date fields: compact left-calendar ISO shell is the default form look

### Policy locked (do not reopen)

- Breadcrumbs on every page view and mutating editor modals (not info/confirm)
- Solid green = proposed; muted green = available; grey = unavailable; solid amber/red = confirms only
- Success toasts default 10s; error/warning toasts stay until dismissed
- Compactness **0 / 4 / 8px**
- Form/editor dates: left calendar, padding clears icon, compact `YYYY-MM-DD` width (Captured exemplar)

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here.
