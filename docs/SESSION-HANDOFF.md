# Session Handoff

## Resume point

**PBF download names use brand title** — not opaque `brd_*` ids.

### Clarified (operator model)

- Brands are **shared**: many campaigns may link one brand. `brand.campaign_id` is optional **first-claim provenance**, not exclusive ownership.
- Local **HITZ by 7rym** stamps `twisted-chronicles` as that provenance; it is still free for other campaigns to use.
- Storage ids stay internal; download filenames and pool labels should stay title-first.

### Next

1. Site update → re-export PBF; expect `bandPromo-brand-HITZ-by-7rym-….pbf`.
2. Continue brand portability testing on HITZ.

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here.
