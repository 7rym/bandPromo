# Site health plan (v0.8 — critical)

**Status:** locked direction (2026-09-16). **Not deferred to v0.9.**

v0.8 is the **management machine**. Operators must trust that bandPromo **knows the host’s condition** before we ask them to change anything. A pipeline that burns disk, empties Files, or claims “ready” while the catalogue is broken is a product-ending defect at scale.

Companion: [BUILD-PIPELINE-AUDIT.md](BUILD-PIPELINE-AUDIT.md) (history + terminology), [MEDIA-HANDLING.md](MEDIA-HANDLING.md), [ADMIN-UI.md](ADMIN-UI.md), [SESSION-HANDOFF.md](SESSION-HANDOFF.md).

## Product surface: new System → Status

This is **not** a rename of “Refresh site files,” and **not** a partial retrofit.

**We replace the entire current System → Status page content** with a dedicated **site health** page whose job is operator trust. The old Refresh-centric console (summary + Refresh primary + Peek/Repair as the main story) goes away as the operator-facing layout; its capabilities fold into Check / Treat / Force.

1. **Check** — cheap, routine health checks (always safe to run)
2. **Investigate** — deeper checks only where something sticks out
3. **Diagnose** — plain-language findings + a worklist (what is wrong, what we would do)
4. **Treat** — operator-confirmed apply (catalogue register, delivery rebuild, links, playlists, site chrome)
5. **Verify** — re-check after treatment; confirm improvement or **warn** what remains

Think **doctor’s office**, not “run a build script.”

Legacy **Refresh site files**, **Repair catalogue**, and **Peek under the hood** fold into this flow. Destination UX is one Status health page.

## Clinical model

| Phase | Cost | Mutates? | Operator sees |
|-------|------|----------|----------------|
| **Triage** | Cheap | No | Environment OK? Registry present? Disk vs registry counts? JSON fingerprints vs last run? |
| **Investigation** | Medium | No | Only for flagged areas — orphans, missing masters/deliverables, broken container refs |
| **Diagnosis** | Cheap | No | Findings + ranked worklist |
| **Treatment** | Expensive as needed | Yes, gated | Apply selected treatments |
| **Follow-up** | Cheap → medium | No | Re-triage; green if clear; amber/red with remaining items |

Rules:

1. **No speculative full-tree work.** Deep checks and treatment only for what triage flagged (unless Force — see modes).
2. **Empty worklist after triage → healthy.** Exit in seconds.
3. **Register in place.** Existing disk masters are linked; never mint duplicate `ast_*` as a side effect.
4. **Treatment is never silent.** Preview/diagnosis first; Apply is explicit.
5. **Follow-up is mandatory after treatment.** Never claim “site ready” while findings persist.
6. **New scripts replace legacy stages.** Port behaviour from old build scripts into a new `scripts/site_health/` family with **one logging contract**. Do not keep wrapping `optimizeMedia.py` / `makePlaylists.py` / etc. as the long-term path. (ffmpeg/Pillow stay; our orchestration and log voice change.)
7. **Python is the engine room.** Long Check/Treat loops, plan, delivery, and health logging run in Python. PHP remains for browser/admin endpoints. Retire long PHP CLIs (`build-catalog-cli`, `publish-prep-cli`, autofix CLI as Status engine). Registry treat mutations are ported to Python against the same JSON files admin PHP already uses.

## Operator modes

| Mode | Mutates? | Role |
|------|----------|------|
| **Check site health** | No | Default exam. This **is** the dry-run — diagnose catastrophes and thrash risk before any write. |
| **Treat preview** | No | Show planned treatments/counts before Apply. |
| **Treat recommended** | Yes | Apply plan only, then follow-up verify. |
| **Force full rebuild** | Yes | Bypass healthy short-circuit; rebuild all listener deliverables + playlists + site chrome. Does **not** mint masters or skip catalogue findings — Force is blocked while critical catalogue issues remain (treat register-in-place first). |

## New script family

```
scripts/site_health/
  log.py           # shared HEALTH_* log contract
  runner.py        # Check / Treat / Force entry (Python engine room)
  triage.py
  investigate.py
  plan.py
  registry.py      # load/save/register-in-place (ported domain ops; same JSON as PHP admin)
  treat_audio.py
  treat_visual.py
  treat_sfx.py
  treat_links.py
  treat_playlists.py
  treat_chrome.py
  followup.py
```

Legacy stage scripts and long PHP CLIs are **reference during port**, then retired from the operator Status path.

## Target phases

```
Triage → Investigate (if needed) → Diagnose
  → Treat (gated): audio → visual → sfx → links → playlists → chrome
  → Follow-up re-triage
```

Demo PCF ensure stays on Site update / setup. Dedupe/prune stays later operator-confirm.

## Implementation order (v0.8, incremental)

1. Logging contract + runner shell — **done**  
2. Planner spine (triage/investigate → plan; healthy early exit) — **done**  
3. Status health UI v1 (findings + Check / Treat + Activity) — **done**  
4. Treat modules: audio/visual register-in-place; listener delivery + playlists + chrome; Force rebuild — **done** (stages still subprocess legacy scripts)  
5. Follow-up verify — **done**  
5b. Files index rebuild in Python (Treat) — **done** (PHP CLI fallback)  
6. Cut over Status off `build.py` stage manifest; retire legacy stage entrypoints  
7. Fold Refresh/Repair labels into Status  
8. Fleet acceptance (HITZ / Vanilla / Spandexual)

## Explicit non-goals

- v0.9 access tiers / anonymous entry  
- Replacing ffmpeg/Pillow binaries  
- Auto-prune of duplicate masters without operator confirm  
- Keeping “Refresh site files” as the primary mental model  
- Indefinitely wrapping legacy stage scripts behind a new UI  

## Acceptance (fleet)

| Host | Must prove |
|------|------------|
| hitz.no | Diagnose names empty Files vs disk masters; Treat registers in place via new treat_audio; Follow-up honest; one log voice |
| bandpromo.site | Healthy Check exits in seconds |
| spandexualtension.com | Targeted treatment, not full-site thrash |

---

_Last updated: 2026-09-16_
