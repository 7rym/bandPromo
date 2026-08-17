# Session handoff — resume here

_Paused: 2026-08-17 (after checkpoint v0.8.28 build 402)._

## Exact resume point

**Do not wipe, replace, or “re-seed” local `data/`, `media/`, or `log/`.** `log/` holds analytics/audit test data. This Google Drive folder is the operator working copy.

Fresh-install tests always run on **https://bandpromo.site** (Vanilla). The other remote test sites are **Twisted Chronicles** and **HITZ**. Never this working copy.

| Item | Value |
|------|--------|
| Git | `main` — checkpoint **v0.8.28 build 402** (shell cleanup + player bg fix + layout plan) |
| App tester package | Publish **`v0.8.28-build-402`** after push |
| Demo package | Durable tag **`demo-content`** — unchanged unless demo content edits |
| Local runtime | Operator working copy — **never wipe** |

## Next

1. Confirm GitHub Release **`v0.8.28-build-402`** published; **Site update** on bandpromo.site offers build 402.
2. Fresh-install smoke on **bandpromo.site**: Catalogue = demo only; Branding = bandPromo Default only; no Player / Analytics Quality tabs; player Bio/Gallery work; playlist switch clears living background when brand has none.
3. Continue from [TODO.md](TODO.md) / operator priority.
4. **Deferred (v0.9 candidate):** code layout refactor — [CODE-LAYOUT-REFACTOR.md](CODE-LAYOUT-REFACTOR.md).

## Shipped in build 402 (do not redo)

- Hide invisible `primary` from Catalogue / operator release lists.
- Stop setup/Welcome auto “Your own brand”; Welcome nudges Duplicate when Base is locked demo.
- Release Pages associations drive player tab order; Content → Player layout removed.
- Analytics → Quality removed.
- Player shell living background no longer sticks after playlist/brand switch.
- v0.9 `/lib` + `/admin/` layout plan documented (no implementation).

## Constraints

- Windows + PowerShell. Python **3.6.9** floor.
- Unqualified “checkpoint” = bump + commit + push + GitHub Release.
- Unqualified “fresh install” = **bandpromo.site only**. Never delete local `data/` / `media/` / `log/`.
- Browser automation: do not hang on long waits — ask the operator.
