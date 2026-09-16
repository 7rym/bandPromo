# Session Handoff

## Resume point

Hotfix **v0.8.62 build 502** (or next) for HITZ after 501 Refresh crash.

On **hitz.no** after Site update:

1. Do **not** Refresh while Files → Audio is empty / Status says Repair.
2. System → Status → Peek under the hood → **Repair catalogue** → Preview → **Apply**.
3. Confirm **Files → Audio** lists tracks.
4. Only then **Refresh site files**.

### Fixed this session

- Dry-run / inventory honesty; Refresh inventory-only; register-in-place Repair
- Upload replace-in-place; Repair never mints from originals
- **501 bug:** `run_publish_stage` returned 2 values, main expected 3 — fixed
- Status CTA for uncatalogued audio → Repair Apply (not Refresh)
- Refresh stops early when catalogue inventory finds Repair work

### Still follow-on

- Safe prune of duplicate masters after register
- Content-hash dedupe for audio
- Full Status page redesign
- Cursor `state.vscdb` GC (operator: Developer → GC Agent KV Blobs)

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here.
