# Operator messaging (OMP)

Policy for how bandPromo informs operators in the admin panel. **Defined in v0.8; implementation opens v0.9** — see [ROADMAP.md](ROADMAP.md) and [TODO.md](TODO.md).

## Problem (v0.8 beta)

Operators currently see **three parallel channels**:

| Channel | What it is today | Operator confusion |
|---------|------------------|-------------------|
| **Toasts** | Ephemeral top-right DOM notices (~50+ call sites) | Success, errors, and “check Notifications” look the same |
| **Notifications** | Live **derived** dashboard from build state, validation, `background-tasks.json`, package manifest | Feels like an inbox but is recomputed state, not message history |
| **Inline `status-text`** | Persistent lines under cards/forms | Same outcome may also toast or appear in Notifications |

Background work is split further: video/audio delivery appears in Notifications; backup / PCF / PBF jobs appear only under System → Jobs.

Native `window.confirm` / `alert` / `prompt` (~20+ call sites) and seven bespoke delete modals add inconsistent confirmation UX.

## Target model (v0.9+)

One **Message** type; two presentation layers; optional community and support channels later.

```text
Event → bandpromoMessaging.notify() → Message store
              ↓
         Toast (short, instant)
              ↓ after TTL or dismiss
         Operator Inbox (durable history + actions)
```

### Message fields (canonical)

- `id`, `created_at`, `read_at`, `archived_at`
- `channel`: `system` | `action_required` | `community` | `support`
- `severity`: `info` | `success` | `warning` | `error` | `progress`
- `title`, `body` (short toast copy + optional longer inbox copy)
- `source` / `source_ref` (e.g. `backup-job`, task id, user id)
- `actions[]`: `{ label, href | callback | confirm }`
- `lifecycle`: `toast_ms`, `persist`, `replace_key` (update in place for progress)

### Toast vs inbox

| Layer | Role |
|-------|------|
| **Toast** | “Something just happened” — one line, non-blocking, not the system of record |
| **Inbox** | “My message history + open items” — return later, mark read, act |

**Graduation rule:** when a toast expires or is dismissed, it **writes or updates** the inbox row (unless `toast_only: true`). Errors always persist.

**Operator setting** (v0.9): toast duration; graduate-to-inbox on dismiss / always / never.

### Inline status — strict scope (v0.9 cleanup)

Keep inline text **only** for:

- Progress on the control that owns the action (“Queueing export…”, build log stream)
- Field-level validation

Stop using inline for global outcomes (“Building in background”, “Backup deleted”) — those become toast → inbox.

### Confirm dialogs — one reusable component (v0.9)

Replace native dialogs and per-feature delete modals with:

```js
await bandpromoMessaging.confirm({
  title: 'Delete backup job?',
  body: '…',
  confirmLabel: 'Delete backup',
  tone: 'danger', // default | danger
});
```

Reuse the same module for unsaved-leave (Save / Discard / Cancel), destructive delete, site update install, and “Open in Catalogue?” follow-ups. Extend `editor-unsaved-modal.js` pattern; one `#adminConfirmModal` in markup.

Native `confirm` / `alert` / `prompt` remain **dev-only fallbacks** when markup fails to load.

### Background jobs — unified adapter (v0.9 Phase 2)

- `log/background-tasks.json` (delivery) and site backup / PCF / PBF jobs emit the same message shape.
- Inbox row updates via `replace_key` while building; ready/failed toasts graduate to inbox with Download / Open actions.
- Deprecate toasts that say “check Notifications” without a matching inbox item.

## Channels beyond system (v0.9+)

### Community (`channel: community`)

Fan → operator messages (comments, contact, moderation). Same bell and inbox UI; Community filter. Aligns with v0.9 access/engagement foundation and [USE-CASES.md](USE-CASES.md) tour story.

### Support (`channel: support`)

Operator → bandPromo team reporting (install diagnostics, logs bundle). Responses arrive in the **operator inbox**. bandPromo team side lives on a future **bandPromo marketing/support site** (pre-v1) — not required for free self-hosted distribution under the license, but recommended before v1 public release.

## Developer-only admin surfaces (v0.8)

**Locked (2026-08-31):** System → **Audit** and System → **Security** are **developer role only**. Operators (`admin` role) keep Status, Backup export/import, and Refresh site files. Repair catalogue remains developer-only on Status.

Rationale: separates operator workflow from host diagnostics; pairs with OMP so system messages stay in inbox, not scattered dev tools.

## v0.8 hygiene (no OMP implementation)

- Do not add new `window.confirm` or duplicate toast helpers.
- Backup Jobs feedback uses toasts (2026-08-31); delete uses shared modal pattern until v0.9 confirm library.
- Document call-site inventory; full migration waits for v0.9 Phase 1.

## v0.9 implementation phases

| Phase | Scope |
|-------|--------|
| **1 — Foundation** | `operator-messaging.php` store; `operator-messaging.js` (`notify`, `confirm`); operator toast/inbox settings; migrate confirms then toasts |
| **2 — Background work** | Unified job messages; inbox replaces Jobs/Notifications/toast triangle |
| **3 — Community** | Fan → operator inbox; moderation actions |
| **4 — Support** | Operator report-with-logs; bandPromo team replies via future support site |

## Related docs

- [ADMIN-UI.md](ADMIN-UI.md) — button/modal patterns
- [ROADMAP.md](ROADMAP.md) — v0.9 opening sprint
- [TODO.md](TODO.md) — v0.8 exit gate vs v0.9 OMP tasks

_Last updated: 2026-08-31_
