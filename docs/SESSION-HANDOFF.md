# Session Handoff

## Resume point

**HITZ Treat was alive** — Activity paste reached Visual **550/645** at 14:05:28Z. Status looked dead because heartbeat wrote the wrong meta file and Activity flooded the poller. Fixes in this checkout (heartbeat + log tail + quieter summary). Still need publish for HITZ.

Also open: plan honesty (legacy stage port); bare audio **Untitled** tag-fill (registry ← master).

### Plan honesty (2026-09-16)

Doctor Status UI + Check/Treat/Force spine shipped, but these were marked done too early:

1. **Legacy stage port** — delivery / playlists / chrome / sfx still run via `stage_exec` → `optimizeMedia.py` / `makePlaylists.py` / etc. Rule 6 and the non-goal “do not wrap forever” remain **open**.
2. **`docs/TODO.md`** now has an explicit **Site health** open checklist (was missing).
3. Cursor plan todos reopened: `new-treat-scripts`, `retire-legacy-stages`, `fleet-accept`; added `audio-tag-fill`.

### Bad import (Untitled Files → Audio)

Treat register-in-place wrote registry rows with empty `display` → Files shows **Untitled** and red C/A/T/D/L chips. Masters on disk may still hold real tags.

- **Direction locked:** registry ← master only during Treat. Do **not** sync empty registry onto masters.
- **Delivery** strips tags from listener `optimal` MP3s only (intentional); it does not rewrite master tags from empty display.
- **Fix in this checkout:** `audio_display.py` + register/treat/triage fill display from mutagen tags; finding `audio_display_missing_tags` → treatment `audio_fill_display_from_tags`.

### HITZ wipe (still needs publish)

1. Visual register `original_filename` empty → PHP normalize dropped rows (fixed in registry + asset-registry heal).
2. Busy Status UI: Stop + live status only while running (fixed in `site-health-admin.js`).

### After publish

1. Site update.
2. Quick check — expect `audio_display_missing_tags` if Untitled rows remain.
3. Review → Apply — tag fill + any remaining register; follow-up must keep visuals registered and Files titles from tags.
4. Continue legacy stage **port** work (not just wrappers).

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here.
