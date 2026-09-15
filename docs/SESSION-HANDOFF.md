# Session Handoff

## Resume point

**Repair Apply timeout fix ready** (local `v0.8.58`) — HITZ Apply aborted in `seed_containers` at `asset-registry.php` `hash_file` under php.ini **30s CPU** (wall clock looked like minutes because hashing is I/O-bound).

### Fix

- Apply seed uses **light** migrate only (no heavy SHA-256 of all visuals).
- Visual content hash: prefer **xxh3**; SHA-256 only for files ≤12 MB.
- Hash backfill is time-budgeted; re-run Apply if warnings say pending remain.
- `set_time_limit(600)` re-armed each step (hosts that ignore it still benefit from less work).

### On HITZ after publish

1. Site update to the new build.
2. Repair Apply again (may need 2 passes if hash backfill pauses).
3. Refresh site files for covers.
4. Confirm Files → Audio / Brand assets / player covers.

### Active fleet

| Host | Persona |
|------|---------|
| bandpromo.site | Vanilla |
| hitz.no | HITZ |
| spandexualtension.com | Band / release sequence |

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here.
