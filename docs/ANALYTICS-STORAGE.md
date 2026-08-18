# Analytics and activity log storage (v0.8)

Source of truth for how bandPromo stores, queries, and retains listener activity and admin audit events.

**Status:** rollup maintainer, client batching, admin export, and retention shipped (2026-07-13). Drill-down tabs (patterns, raw log) still scan raw events where rollups are insufficient. Analytics → Quality (Original vs Optimized) is retired — public playback is delivery-optimal only.

Related: [ACCESS-MODEL.md](ACCESS-MODEL.md) (time semantics), [DELIVERY-ARCHITECTURE.md](DELIVERY-ARCHITECTURE.md) (offline log sync), [PORTABILITY.md](PORTABILITY.md) (backup/export).

## Why v0.8

v0.8 is the **management machine** milestone: catalogue, media, brands, containers, and **data foundations** operators can trust before scale changes.

v0.9 opens wider access (anonymous entry, tier enforcement, more concurrent listeners). Beta sites must not reach v0.9 still on a single-user JSONL scan model. **Storage and query strategy ship in v0.8**; v0.9 adds features and traffic on top of that foundation.

## Principles

- **Local to the install** — no central bandPromo telemetry SaaS.
- **UTC at rest** — all event timestamps stored as Unix UTC + ISO `Z` (see [ACCESS-MODEL.md](ACCESS-MODEL.md)).
- **Append-only events** — raw events are never mutated; corrections are new rows or rollup rebuilds.
- **Query from rollups** — admin dashboards read pre-aggregated tables for normal date ranges; raw events are for drill-down and export only.
- **Portable** — operators can export JSONL/CSV; backup includes the analytics database under `data/`.

## Previous state (pre-upgrade)

| Store | Path | Problem at scale |
|-------|------|------------------|
| Listener activity | `log/YYYY-MM-DD.log` | Per-request `LOCK_EX` append; full-file scan + `json_decode` per admin view |
| Admin audit | `log/admin-audit/YYYY-MM-DD.log` | Same pattern, lower volume |
| Analytics engine | `PlaybackAnalytics` | O(events in range) on every dashboard load; unused in-memory cache |

Legacy daily files are imported once on upgrade, then deleted. New writes go only to SQLite. `log/` still holds build/dev logs and other non-activity files.

## Target architecture (v0.8)

### 1. Activity store (`biblioteca/activity-store.php`)

Central ingest/query module used by logging endpoints, analytics, and audit readers:

- `bandpromo_activity_store_append_listener()`
- `bandpromo_activity_store_append_audit()`
- `bandpromo_activity_store_fetch_listener_entries()`
- `bandpromo_activity_store_fetch_audit_entries()`
- `bandpromo_activity_store_hourly_distribution()`

### 2. SQLite event store

**Path:** `data/analytics/events.sqlite` (WAL mode).

**Tables (minimum):**

- `listener_events` — listener activity (`ts_utc`, `username`, `activity`, `data_json`, …)
- `audit_events` — admin audit (separate table)
- Indexes on `(ts_utc)`, `(username, ts_utc)`, `(activity, ts_utc)`

Admin audit volume is low; listener `events` is the scaling concern.

### 3. Rollups (materialized aggregates)

Maintained by a lightweight maintenance job (CLI or post-append batch):

| Rollup | Grain | Feeds |
|--------|-------|-------|
| `rollup_hourly` | hour × activity (UTC or operator display TZ at read) | Hourly chart |
| `rollup_daily_user` | day × user | Active users, listening time |
| `rollup_daily_track` | day × track | Hitlist |
| `rollup_daily_device` | day × device | Device breakdown |

Admin analytics **must** read rollups for dashboard cards and charts. Raw `events` only for Log tab, user drill-down, and export.

### 4. Ingestion hygiene

- **Client batching** (v0.8): flush buffered events every few seconds and on `session_end`, not one HTTP POST per micro-event.
- **Event tiers:** hot (play/session boundaries) vs warm (pause, environment) — warm events batch first.
- **Same session store as login/player** — `log.php` uses `bandpromo_ensure_session_started()` (Windows: `%LOCALAPPDATA%/bandPromo/php-sessions`). A 401 from logging must not expire the player UI.
- **Rate limiting** on the log ingest endpoint.
- **Burst-safe writes:** SQLite transactions with multi-row INSERT preferred over thousands of single-row file locks.

### 5. Retention and export

Default policy (operator-configurable later):

- Raw `events`: 90 days (rollup data kept longer)
- Rollups: indefinite
- Export: JSONL/CSV from **Analytics → Log** tab (`biblioteca/export-activity-log.php`); `data/analytics/events.sqlite` included in Data backup component

## Migration

**Policy:** no dual-write period. Legacy daily JSONL files are a one-time import source, not a parallel store.

1. On first use after upgrade (admin load, ingest, or analytics read), detect legacy `log/*.log` and `log/admin-audit/*.log`.
2. **Import immediately** into SQLite in a single maintenance transaction.
3. **Verify** row counts / checksum sample against source files.
4. **Delete** imported legacy files (or move to `log/_imported/` once, then delete on next successful admin session — prefer delete when verify passes).
5. All new events write **only** to SQLite.

Operators never choose a migration mode. Beta installs accumulate data once in SQLite going forward.

If import fails, block analytics with a plain-language admin notice and keep legacy files until repair succeeds — do not silently dual-write indefinitely.

## Deferred (not v0.8 storage core)

| Item | Milestone | Notes |
|------|-----------|-------|
| Offline log queue + sync | v0.9 | Client queues when offline; sync uses same ingest API → SQLite |
| Drop-moment funnel events | v2 | `release_gate_opened`, concurrent listener peaks — store is ready in v0.8 |
| External analytics (GA, etc.) | v2+ | Optional operator integration; does not replace local store |
| Chat message storage | v2 | Separate messages store; presence/peaks may use rollups |

## Success criteria (v0.8)

- [x] New listener and audit events write only through `activity-store.php` → SQLite.
- [x] Existing installs can import legacy daily JSONL logs without data loss (one-shot import + delete).
- [x] Bootstrap and setup preflight require `pdo_sqlite`.
- [x] Admin dashboard for a 30-day range reads rollups for hot-path cards (dashboard, hitlist, activities); patterns/log tabs still use raw events where needed.
- [x] Documented retention and export path for operators.
