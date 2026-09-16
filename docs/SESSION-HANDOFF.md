# Session Handoff

## Resume point

HITZ optimisation plan ready to ship as this checkpoint. On **hitz.no** after **Site update**:

1. Do **not** Refresh yet while Files → Audio is empty.
2. System → Status → Peek under the hood → **Repair catalogue** → Preview → **Apply**.
   - This registers existing `media/audio/master/ast_*` into the registry **in place** (no copy, no 40‑minute rebuild).
3. Confirm **Files → Audio** lists tracks.
4. Only then run **Refresh site files** to build listener deliverables.

### Fixed this session

- Dry-run reports disk vs registry mismatch loudly; banner no longer claims “site ready”
- Refresh prep + catalog are **inventory-only** (mutations belong to Repair Apply)
- Upload no longer mints a new `ast_*` when the same original is already registered (replace-in-place)
- Repair Apply / catalogue register never mint from originals (link/register only)
- Audio upload path prefers `materialize` over bare `prepare`

### Still follow-on

- Safe prune of duplicate masters (operator confirm) after register
- Content-hash dedupe for audio (visual already has content sha)
- Full Status page redesign
- Native Python catalogue / Repair Apply bodies

### Active fleet

| Host | Persona |
|------|---------|
| bandpromo.site | Vanilla |
| hitz.no | HITZ |
| spandexualtension.com | Band / release sequence |

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here.
