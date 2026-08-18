# Session handoff — resume here

_Paused: 2026-08-18 after checkpoint **v0.8.29 build 409**._

## Exact resume point

**Do not wipe, replace, or “re-seed” local `data/`, `media/`, or `log/`.** `log/` holds analytics and admin-audit test data. This Google Drive folder is the operator working copy.

Fresh-install tests always run on **https://bandpromo.site** (Vanilla). The other remote test sites are **Twisted Chronicles** and **HITZ**. Never this working copy.

**Next work:** Gallery **multi-select picker** as the primary membership flow (policy locked; Available drag-and-drop is still what ships). Do not reopen Grid / List / Carousel / Animated unless a bug appears.

| Item | Value |
|------|--------|
| Git | `main` — last checkpoint **v0.8.29 build 409** |
| App tester package | Published tag **`v0.8.29-build-409`** (confirm Site update on bandpromo.site) |
| Demo package | Durable tag **`demo-content`** — `bandPromo-demo.pcf` (SHA256 `3b24420a1e52e58723093e2bbd873876ac0d4db5758f185c412395e557bcbd35`) |
| Local runtime | Operator working copy — **never wipe** |

## Next

1. Confirm **Site update** on bandpromo.site offers build 409 and that a fresh Vanilla install imports the new Demo PCF.
2. Gallery membership: searchable multi-select picker + ordered list ([TODO.md](TODO.md) Visual naming + gallery pickers).
3. **Deferred (v0.9 candidate):** code layout refactor — [CODE-LAYOUT-REFACTOR.md](CODE-LAYOUT-REFACTOR.md). Content AI wizards remain defined, not built.

## Shipped in build 409 (do not redo)

- Campaign handoff is **PCF** / **`.pcf`** in operator copy, export filenames, and current docs. Never describe it as a ZIP. Import still accepts legacy `.prp`.
- Demo content published as `bandPromo-demo.pcf` on durable `demo-content` (setup prefers `.pcf`, falls back to `.prp`).
- Playlist editor **Base info**; **★ Set as default** matches Branding **★ Set as base**.
- House style **UK English**; operator copy uses **catalogue** / **colour**.
- Campaign editor helpers, Pages/Branding/Playlist `--border2` chrome, gallery **Animated** (legacy Parallax migrates here).

## Shipped in build 408 (do not redo)

- Page **Video** blocks (Audio/Loop chips, Width/Flow including Full row).
- Gallery page blocks: **Grid** (native ratios, Max across), **List** rows, **Carousel** (snap, peek, dots, optional in-view autorotate + Speed).
- Page editor chrome: sticky Page builder / Live preview, `--border2` headers, Page building blocks, removed redundant field labels.
- Video delivery keeps soundtrack by default; living shell/cover stays silent.
- `/play/{playlist}/…` path deep links work on php -S (not only `?playlist=`).
- Admin main tabs remember the last used sub-tab.

## Shipped in build 407 (do not redo)

- Admin header **Open player** links to `/play/` (was Open site).
- Hide demo catalogue no longer treats demo-owned track covers / posters as external blockers. Real blockers name the track, playlist, gallery, page, or campaign to fix.

## Constraints

- Windows + PowerShell. Python **3.6.9** floor.
- Repository-authored copy is **UK English**.
- Unqualified “checkpoint” = bump + commit + push + GitHub Release.
- Unqualified “fresh install” = **bandpromo.site only**. Never delete local `data/` / `media/` / `log/`.
- Browser automation: do not hang on long waits — ask the operator.
