# Session handoff — resume here

_Paused: 2026-08-17 (after checkpoint v0.8.29 build 406)._

## Exact resume point

**Do not wipe, replace, or “re-seed” local `data/`, `media/`, or `log/`.** `log/` holds analytics/audit test data. This Google Drive folder is the operator working copy.

Fresh-install tests always run on **https://bandpromo.site** (Vanilla). The other remote test sites are **Twisted Chronicles** and **HITZ**. Never this working copy.

**Next work:** none queued from this pause — continue operator priority.

| Item | Value |
|------|--------|
| Git | `main` — checkpoint **v0.8.29 build 406** |
| App tester package | Publish **`v0.8.29-build-406`** after push |
| Demo package | Durable tag **`demo-content`** — unchanged unless demo content edits |
| Local runtime | Operator working copy — **never wipe** |

## Next

1. Confirm GitHub Release **`v0.8.29-build-406`** published; **Site update** on bandpromo.site offers build 406.
2. Continue from [TODO.md](TODO.md) / operator priority.
3. **Deferred (v0.9 candidate):** code layout refactor — [CODE-LAYOUT-REFACTOR.md](CODE-LAYOUT-REFACTOR.md).

## Shipped in build 406 (do not redo)

- Operator-facing **Campaign** for the Catalogue umbrella (copy/docs/UI only; `release_id` / PRP / track **Release date** unchanged). Playlist stays the listening product.
- System → Backup: PRP export/import above site backup; collision radios Refuse | Overwrite | Skip | AsNew.
- Content ⓘ help as three-bullet lists (Catalogue, Playlists, Galleries, Pages, Branding).
- Files ⓘ help: uploads become internal masters; pools/pickers use masters; delivery from masters.
- Files → Visual campaign filter **All campaigns**; Brand assets type chips (Still/Living/SFX icons); brand filter **All brands**; **Add existing** only when a Brand is selected.

## Shipped in build 405 (do not redo)

- Compact Admin chrome (identity line + Notifications on the primary tab row).
- System → **Status** (was Deliverables): Catalogue-matching counts; Repair catalog developer-only; Refresh site files.
- Local Python metadata: inherit PHP process env; bootstrap `scripts/vendor` before mutagen/Pillow; still write vendor when packages already import globally.
- Files → Visual: one ⓘ help paragraph; Images/Video icon chips; tighter toolbar padding; list title + status + actions on one line.

## Shipped in build 404 (do not redo)

- Living shell/cover loop + tail auto-next.
- Playlist switch via `/play/?playlist={id}` so demo Bio/Gallery tabs work without `play/.htaccess`.

## Constraints

- Windows + PowerShell. Python **3.6.9** floor.
- Unqualified “checkpoint” = bump + commit + push + GitHub Release.
- Unqualified “fresh install” = **bandpromo.site only**. Never delete local `data/` / `media/` / `log/`.
- Browser automation: do not hang on long waits — ask the operator.
