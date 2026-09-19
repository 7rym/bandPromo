# Session Handoff

## Resume point

**PBF packer double-close fix published** — export FAILED “Invalid or uninitialized Zip object” after build 546; close-only-while-open.

### Shipped recently

1. Fix ZipArchive double-close that broke PBF Ready.
2. Jobs download: one fetch→verify→save (success or fail toast — not “retry until”).
3. Earlier: truncated download caused HITZ import status 19.

### Next

1. Site update → Queue PBF export again (leave Backup open until Ready) → Download once → import on HITZ.
2. Continue brand portability testing.

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here.
