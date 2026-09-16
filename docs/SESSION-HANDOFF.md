# Session Handoff

## Resume point

**Site health Status — implementation started (v0.8 critical).**

Plan: [BUILD-PIPELINE-PLAN.md](BUILD-PIPELINE-PLAN.md).

### Shipped this slice

- New Python engine room: `scripts/site_health/` + entries `siteHealthCheck|Treat|Force.py`
- Check triage/investigate → `data/site-health-plan.json` + `HEALTH_*` log
- Treat: `audio_register_in_place` + thin PHP Files audio index rebuild
- Force: blocked when critical catalogue findings remain (delivery port next)
- System → Status **page replaced** with Check / Treat / Force + Activity (`site-health-admin.js`)

### HITZ ops (until Treat proven on fleet)

1. Site update when this build is published
2. **Check site health** — should report empty Files vs disk masters
3. **Treat recommended** — register in place
4. Confirm Files → Audio, then Force/delivery when that slice lands

### Next slices

- Port visual register + delivery/playlist/chrome treat modules
- Files index rebuild in Python (retire thin PHP verb)
- Stop button + job-stop for site-health
- Cut over remaining Dashboard nudges from “Refresh site files”
- Publish tester build when Status Check/Treat feels solid locally

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here.
