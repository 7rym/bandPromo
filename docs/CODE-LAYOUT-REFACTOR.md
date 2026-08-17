# Code layout refactor (v0.9 candidate)

**Status:** Planned — do **not** start during v0.8 beta polish. Evaluate for scheduling at the **v0.9 milestone opening** (after v0.8 exit gate).

**Related:** [ROADMAP.md](ROADMAP.md) → v0.9 goals, [TODO.md](TODO.md) → v0.9 candidate slice, [PLATFORM-MODEL.md](PLATFORM-MODEL.md) → platform vs operator boundaries.

---

## Problem

Application code is spread across several top-level folders with overlapping names:

| Path | Role today |
|------|------------|
| `biblioteca/` | PHP helpers, HTTP APIs, admin/player JS & CSS, seed templates (~200 tracked files) |
| `scripts/` | Python build pipeline, PowerShell dev tooling, 3 PHP CLI scripts |
| `scripts/vendor-wheels/` | Committed offline Python wheels |
| `scripts/vendor/` | Host-local Python site-packages (gitignored) |
| `scripts/bin/` | ffmpeg/ffprobe (downloaded on host) |
| `vendor/` | Committed third-party PHP (HTMLPurifier) + JS (Chart.js) |
| `admin.php` | Operator UI entrypoint at web root |
| `play/index.php` | Listener UI entrypoint under `/play/` |

Pain points:

- **`biblioteca`** was an early name for “library”; the original `/lib` idea was never applied.
- **Three different “vendor” meanings** (PHP committed, Python wheels, Python runtime).
- **`biblioteca/` is flat** — include-only PHP, web APIs, and static assets share one directory.
- **`admin.php` at root** while **`play/` is namespaced** — the two main product surfaces do not follow the same pattern.

---

## Goals

1. **Consolidate application code under `/lib`** with clear subfolders (PHP, public web surface, templates, build tooling, third-party libs).
2. **Move operator UI to `/admin/`** mirroring `/play/` — thin entry folder, heavy logic in `lib/`.
3. **Separate the two product bodies** at the URL level:
   - `/play/` — listener experience
   - `/admin/` — operator experience
4. **Unambiguous vendor naming** — no two folders both called `vendor` with different semantics.
5. **Backward-compatible migration** — published installs, service worker caches, bookmarks, and docs must not break without redirects/shims.

---

## Target layout

```
lib/
├── php/                 # require-only modules (auth, storage, helpers, guards)
├── public/              # web-served PHP endpoints + JS/CSS (successor to flat biblioteca/)
├── templates/           # seed JSON, runtime Apache/PHP stubs, demo package, icons zip
├── vendor/
│   ├── php/             # HTMLPurifier (from /vendor/htmlpurifier)
│   └── js/              # Chart.js (from /vendor/chart.js)
└── build/
    ├── python/          # build.py, optimizeMedia.py, …
    ├── wheels/          # offline Python wheels (was scripts/vendor-wheels/)
    ├── site-packages/   # host-local deps (gitignored; was scripts/vendor/)
    └── bin/             # ffmpeg/ffprobe (was scripts/bin/)

admin/
└── index.php            # operator entry (was root admin.php)

play/
└── index.php            # unchanged pattern

scripts/                 # optional thin launchers → lib/build/python/ (keep CLI paths stable)
```

Root stays thin: `index.php` (login), `setup.php`, `bootstrap.php`, `docs/`, `.github/`, runtime roots (`data/`, `media/`, `log/`).

### Internal split inside `lib/public/`

Today’s flat `biblioteca/` mixes concerns. Even before a rename, group by responsibility:

| Kind | Examples today | Target |
|------|----------------|--------|
| Include-only PHP | `auth.php`, `release-storage.php` | `lib/php/` |
| HTTP APIs | `save-theme.php`, `upload-media.php` | `lib/public/` (or `lib/public/api/`) |
| Static front-end | `admin.js`, `player.css` | `lib/public/assets/` (or flat under `lib/public/`) |
| Seeds / stubs | `templates/` | `lib/templates/` |

---

## URL and compatibility strategy

### Public HTTP paths (breaking if changed blindly)

These are baked into HTML, JS `fetch`, the service worker, and Apache rewrites:

- `/biblioteca/*.js`, `*.css`
- `/biblioteca/*.php` API endpoints
- `/admin.php` (+ query tabs)
- Rewrites in `biblioteca/templates/runtime/root.htaccess` (e.g. `web-config.json` → `/biblioteca/get-config.php`)

**Policy:** keep old URLs working for at least one major release via Apache/nginx rewrites or thin shim files. Deprecate in docs; remove only after fleet sync confirms no reliance.

| Old | New (target) | Compatibility |
|-----|--------------|---------------|
| `/admin.php` | `/admin/` | 301/rewrite + root `admin.php` forwarder |
| `/biblioteca/*` | `/lib/public/*` | rewrite alias **or** keep serving from `lib/public/` but expose as `/biblioteca/` indefinitely |
| `require …/biblioteca/` | `require …/lib/php/` | code-only; no HTTP exposure |

**Recommendation:** treat `/biblioteca/` as a **stable public alias** for `lib/public/` long term (like many frameworks keep legacy asset paths). Rename the **internal** tree to `lib/`; do not force operators to update cached service workers for a cosmetic URL change.

### `/admin/` entry pattern

Mirror `play/index.php`:

```
admin/index.php
  require lib/php/https.php, auth.php, …
  render operator shell (body moved from admin.php)
```

Root `admin.php` becomes a one-line redirect to `/admin/` (or Apache rewrite). Update:

- `setup.php`, `bootstrap.php`, `play/index.php` admin links
- `admin.js` / inline `loginUrl`
- operator docs (`INSTALL-UPDATE.md`, checklists)

---

## Phased migration (when approved)

Do **not** combine with unrelated v0.8 feature work in the same checkpoint.

| Phase | Scope | Risk |
|-------|--------|------|
| **0 — Plan lock** | This doc + ROADMAP/TODO pointers; inventory grep counts | None |
| **1 — Docs + naming** | Rename `scripts/vendor-wheels` → `scripts/python-wheels` (or move under `lib/build/wheels/`) in docs first | Low |
| **2 — Internal split** | Move include-only PHP to `lib/php/`; keep HTTP paths at `/biblioteca/` | Medium |
| **3 — Vendor consolidation** | `/vendor` → `lib/vendor/php` + `lib/vendor/js`; Python wheels/site-packages under `lib/build/` | Medium |
| **4 — Build relocation** | Python + bin under `lib/build/`; `scripts/` as launchers | Medium |
| **5 — `/admin/` move** | `admin/index.php`; redirects from `admin.php` | Medium–high (bookmarks, docs) |
| **6 — Optional public rename** | `/biblioteca/` → `/lib/public/` HTTP alias decision | High — defer unless strong reason |

Each phase: PHP syntax check, release package build, smoke on **bandpromo.site** (Vanilla fresh-install host), update `CHANGELOG.md`.

---

## Fit in v0.9

v0.9 product theme is **access and engagement** (tiers, anonymous entry, roles, cast). Code layout is **infrastructure**, not user-visible feature work.

### Why v0.9 is a reasonable window

- **v0.8 exit gate** already calls for a legacy/fallback audit before v0.9 access-tier implementation — a layout refactor is the right class of cleanup **if** the v0.8 deliverable tail is done first.
- Access-tier work will touch auth, login, and delivery surfaces — cleaner `lib/php/` vs `lib/public/` split reduces accidental coupling before that wave.
- `/admin/` namespacing matches `/play/` before public-facing browsing grows in v0.9.

### Why it might not fit fully in v0.9

- Large reference sweep: ~200 `biblioteca` paths, ~50+ JS fetch URLs, release packager, CI, three remote beta sites, service worker.
- Competes directly with access-tier implementation bandwidth.
- Site update packages must migrate cleanly; cannot assume operators re-bootstrap.

### Suggested scheduling options

| Option | When | Trade-off |
|--------|------|-----------|
| **A — v0.9 opening sprint** | First 1–2 weeks of v0.9, before tier enforcement | Clean base; delays access features slightly |
| **B — Parallel track** | Phases 2–4 during v0.9 while tiers ship in parallel | Needs discipline; two large diffs |
| **C — v0.9 late / v1.0 prep** | After tiers ship | Safer product focus; carries structural debt longer |
| **D — Incremental** | Phase 2–3 only in v0.9; admin move in v1.0 | Smallest v0.9 bite; `/admin/` wait |

**Default recommendation if v0.9 opens on schedule:** **Option D** — internal `lib/` split + vendor consolidation in early v0.9; **`/admin/` move** once access/login URLs are stable; keep `/biblioteca/` as public alias.

Re-evaluate at v0.9 kickoff against open [TODO.md](TODO.md) access-tier items and fleet build parity.

---

## Prerequisites (hard gates)

- [ ] v0.8 exit gate complete ([TODO.md](TODO.md) → Beta fleet sync + legacy audit gate).
- [ ] Shell cleanup and current v0.8 polish checkpointed and on all beta sites.
- [ ] Legacy audit findings triaged — no silent fallbacks added during refactor.
- [ ] Release packager (`scripts/build_release_package.py`) updated in same change as path moves.
- [ ] Fresh-install smoke on **bandpromo.site** after first layout checkpoint.

---

## Out of scope

- Moving `data/`, `media/`, `log/`, `backups/` (operator runtime — unchanged).
- Composer/npm package managers (manual vendor trees stay for shared-host simplicity).
- Renaming `docs/` or `.github/`.
- Wiping or re-seeding local runtime on Google Drive working copy.

---

## Inventory (baseline 2026-08-17)

| Area | Tracked files (approx.) |
|------|-------------------------|
| `biblioteca/` | 204 |
| `scripts/` | 55 |
| `vendor/` | 386 |
| `/biblioteca/` string refs in PHP/JS | ~200+ |

---

## Decision log

| Date | Decision |
|------|----------|
| 2026-08-17 | Defer implementation to v0.9 evaluation; store plan in this doc. User preference: `/admin/` like `/play/`; consolidate under `/lib`. |
