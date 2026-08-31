# Session Handoff

## Resume point

**Decisions locked in docs (2026-08-31).** Next: cheap fixes done (developer-only Audit/Security); **checkpoint + publish** transfer integrity + backup UX when ready.

### Shipped locally (unpublished — build still 437)

- Transfer integrity library (`http-stream.php`, `chunked-upload.php`; verified Jobs download; chunked uploads; `file_digests`).
- Backup Jobs: delete confirmation modal; job feedback via toasts (not inline status line).
- System → Audit + Security: **developer role only** (UI + API guard).

### v0.8 exit gate (do before v0.9 / new testers)

1. **Player Campaign navigator** — policy lock → implement → validate Vanilla / TC / HITZ.
2. Fleet sync + PCF/PBF smoke at latest published build.
3. Favicon/PWA from Branding.
4. Legacy audit refresh at current baseline.

See [TODO.md](TODO.md) → v0.8 exit gate (2026-08-31).

### Deferred to v0.9

- Operator Messaging Platform — [OPERATOR-MESSAGING.md](OPERATOR-MESSAGING.md).
- Content AI wizards (policy locked; build later).
- Access tiers, anonymous entry, community inbox.

### Before checkpoint/publish

Smoke: re-queue PBF export → Jobs size + SHA → verified download → import AsNew.

Last published: **v0.8.35 build 437**. Session **v0.8.36**.

## Plan documents

- Transfer Integrity Library (shipped locally).
- Operator Messaging Platform — [OPERATOR-MESSAGING.md](OPERATOR-MESSAGING.md) (v0.9; docs only).
