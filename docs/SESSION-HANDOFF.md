# Session Handoff

## Resume point

**Build 513** shipped dedupe but HITZ Quick/Full finished in ~1s with false “healthy” — `dedupe.py` never bootstrapped `scripts/vendor`, so **xxhash was None** and both probes no-op’d. Fix: vendor bootstrap in runner + dedupe; Activity now logs work counts; `dedupe_unavailable` finding if xxhash still missing.

Next: publish this fix, Site update HITZ, re-run Full check — expect multi-second/minute content scan + real clone findings (or honest work counts).

### Still open

1. **Cover extract** into `treat_audio`.
2. Keep-newest + registry↔tags sync on survivor after Apply.
3. Visual Brand-asset titles still Untitled (IPTC fill later).

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here.
