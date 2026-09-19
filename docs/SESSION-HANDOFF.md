# Session Handoff

## Resume point

**PBF download integrity fix published** — truncated Jobs downloads caused zip status 19 on HITZ import; local export was valid.

### Shipped recently

1. Jobs PBF/PCF: verified download (fetch→SHA→save) for archives ≤64 MB; hard fread stream; Ready zip probe.
2. Site health Manual Apply CSRF fix.
3. Plans audit + runtime path hygiene docs.

### Next

1. Site update to build with the PBF fix → re-export HITZ brand → download until “integrity verified” → import on HITZ.
2. Continue brand portability testing.
3. Site health fleet quiet → Shell preview parity.

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here.

### Fleet testing note

Operator is testing the fleet — treat “check and publish” as the default checkpoint for Status/Site health / portability work in this stretch.
