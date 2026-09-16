# Session Handoff

## Resume point

**Site health Status — delivery/Force wired (v0.8 critical).**

Plan: [BUILD-PIPELINE-PLAN.md](BUILD-PIPELINE-PLAN.md). Local was **v0.8.62 build 503**; session is **v0.8.63 build 503**. Delivery/Force slice is uncommitted until next checkpoint.

### Shipped

- Python engine `scripts/site_health/` + Status page Check / Treat / Force + Activity
- Treat: **audio** + **visual** register-in-place + Files index rebuild (thin PHP CLI)
- Treat: **listener delivery** (optimizeMedia / optimizeVideo / buildSfxDelivery) + playlists + site chrome
- Treat chains delivery (+ playlists/chrome) after register-in-place so one “Treat recommended” heals HITZ-style gaps
- **Force**: full delivery rebuild when catalogue clear; blocked on critical catalogue findings
- Triage: missing optimal MP3 / empty visual delivery → `listener_delivery`
- Cooperative **Stop** (`site-health.stop` / request-job-stop) between stages
- Operator nudges retargeted to **Check site health**

### Next slices

- Files index rebuild in Python (retire thin PHP verb)
- Optional: deeper investigate (stale delivery checksums) before Force
- Publish tester build when ready for HITZ

### HITZ ops (after publish)

1. Site update  
2. Check site health  
3. Treat recommended  
4. Confirm Files → Audio  

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here.
