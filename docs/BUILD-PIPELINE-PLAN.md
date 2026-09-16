# Site health plan (v0.8 — critical)

**Status:** **mostly built** (2026-09-16). Doctor Status UI + Check/Treat/Force spine are live. Legacy stage port, SFX register-in-place, Quick/Full duplicate-master dedupe (audio full demux-hash), Site update → auto Quick check, and deliverable **built / kept / failed** Activity copy are shipped. **Still open:** cover extract; fleet re-smoke after latest publish; finish proving audio register tag-fill on HITZ.

v0.8 is the **management machine**. Operators must trust that bandPromo **knows the host’s condition** before we ask them to change anything. A pipeline that burns disk, empties Files, or claims “ready” while the catalogue is broken is a product-ending defect at scale.

Companion: [BUILD-PIPELINE-AUDIT.md](BUILD-PIPELINE-AUDIT.md) (history + terminology), [MEDIA-HANDLING.md](MEDIA-HANDLING.md), [ADMIN-UI.md](ADMIN-UI.md), [SESSION-HANDOFF.md](SESSION-HANDOFF.md), [TODO.md](TODO.md) → Site health.

## Product surface: new System → Status

This is **not** a rename of “Refresh site files,” and **not** a partial retrofit.

**We replace the entire current System → Status page content** with a dedicated **site health** page whose job is operator trust. The old Refresh-centric console (summary + Refresh primary + Peek/Repair as the main story) goes away as the operator-facing layout; its capabilities fold into Check / Treat / Force.

1. **Check** — cheap, routine health checks (always safe to run); **Full check** for deeper read-only probes
2. **Investigate** — deeper checks only where something sticks out (Full always enters this phase)
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
4. **Treatment is never silent.** Preview/diagnosis first; Apply is explicit. Status UI: **Review treatment** (read-only) → **Apply treatment** (mutates). Verbose evidence stays in Activity.
5. **Follow-up is mandatory after treatment.** Never claim “site ready” while findings persist.
6. **New scripts replace legacy stages.** Port behaviour from old build scripts into a new `scripts/site_health/` family with **one logging contract**. Do not keep wrapping `optimizeMedia.py` / `makePlaylists.py` / etc. as the long-term path. (ffmpeg/Pillow stay; our orchestration and log voice change.) **Done (2026-09-16):** Treat/Force no longer uses `stage_exec`; in-process `delivery_*` / `chrome_*` / `php_stage` own Status. Legacy `scripts/*.py` remain thin CLIs for any remaining `build.py` callers.
7. **Python is the engine room.** Long Check/Treat loops, plan, delivery, and health logging run in Python. PHP remains for browser/admin endpoints. Retire long PHP CLIs (`build-catalog-cli`, `publish-prep-cli`, autofix CLI as Status engine). Registry treat mutations are ported to Python against the same JSON files admin PHP already uses.
8. **Register captures content, not only identity.** Audio (and later visual) register-in-place must read embedded master tags into registry `display` (**registry ← master**). A row that is only `ast_*` + empty/`Untitled` display is a **bad import**. Treat must never sync empty registry display onto masters (**never registry → master** unless the operator explicitly saves in the track editor).

## Operator modes

| Mode | Mutates? | Role |
|------|----------|------|
| **Quick health check** | No | Routine exam. Cache-aware fingerprint baseline. This **is** the default dry-run. |
| **Full health check** | No | Deeper read-only verify: ignores fingerprint cache, always probes delivery existence, Files index undercount, non-`ast_*` masters; rebuilds baseline after. Never mutates. |
| **Treat preview** | No | **Review treatment** — show proposed treatments/counts before Apply (file lists in Activity). |
| **Treat recommended** | Yes | **Apply treatment** — apply plan only, then follow-up verify. |
| **Force full rebuild** | Yes | Bypass healthy short-circuit; rebuild all listener deliverables + playlists + site chrome. Does **not** mint masters or skip catalogue findings — Force is blocked while critical catalogue issues remain (treat register-in-place first). |

## New script family

```
scripts/site_health/
  log.py           # sole writer of log/site-health.log (timestamped HEALTH_* contract)
  runner.py        # Check / Treat / Force entry (Python engine room)
  triage.py
  investigate.py
  plan.py
  registry.py      # load/save/register-in-place (ported domain ops; same JSON as PHP admin)
  audio_display.py # registry ← master tag fill (bad-import heal)
  treat_audio.py   # register-in-place + tag fill (+ later: covers)
  treat_visual.py
  treat_sfx.py       # PHP CLI via php_stage
  treat_links.py
  treat_playlists.py # PHP playlist publish via php_stage
  treat_chrome.py    # chrome_social + chrome_pwa
  treat_delivery.py  # delivery_audio / delivery_visual / delivery_video in-process
  delivery_audio.py / delivery_visual.py / delivery_video.py
  chrome_pwa.py / chrome_social.py
  php_stage.py
  followup.py
```

PHP admin endpoints only **launch** the job and **read** the Activity log / plan. They must not append operator Activity lines (process EXITCODE may use a private runner sidecar).

Legacy stage scripts and long PHP CLIs are **reference during port**, then retired from the operator Status path.

## Target phases

```
Triage → Investigate (if needed) → Diagnose
  → Treat (gated): audio → visual → dedupe → sfx → links → playlists → chrome
  → Follow-up re-triage
```

Demo PCF ensure stays on Site update / setup. After a successful Site update, Status **auto-starts Quick health check** (no redundant Notifications “tune-up” nag). Dedupe/prune of duplicate masters is gated through Site health Review → Apply (Quick file-hash + Full demux/RGB content fingerprint) — never silent auto-prune.

## Implementation order (v0.8, incremental)

1. Logging contract + runner shell — **done**
2. Planner spine (triage/investigate → plan; healthy early exit) — **done**
3. Status health UI v1 (findings + Check / Treat + Activity) — **done**
4. Treat modules: audio/visual register-in-place; `treat_sfx` / `treat_links`; listener delivery + playlists + chrome; Force rebuild — **done** (Status path native / php_stage / in-process delivery)
5. Follow-up verify — **done**
5b. Files index rebuild in Python (Treat) — **done**
5c. Audio register fills display from master tags + heal bare Untitled rows — **code present**; fleet prove on HITZ after publish
5d. Duplicate masters Quick/Full + SFX register-in-place — **done** (2026-09-16)
5e. Site update → auto Quick health check; drop redundant `package_update` Notifications nag — **done** (2026-09-16)
5f. Operator deliverable counts **built / kept / failed** (internal BUILD_STATS key still `fresh`) — **done** (2026-09-16)
6. **Port** delivery / playlists / chrome / sfx into `site_health`; retire `stage_exec` from Status Treat/Force — **done** (2026-09-16; `stage_exec.py` removed)
7. Fold Refresh/Repair labels into Status — **done** (operator copy uses Check / Review / Apply / Force)
8. Cut over Status off `build.py` stage manifest (launcher only) — **done**
9. Fleet acceptance (HITZ / Vanilla / Spandexual) — **partial** (local + Vanilla + Spandexual Force passed build 509; re-smoke after 525+ for auto Quick check / SFX / Full demux)

## Explicit non-goals

- v0.9 access tiers / anonymous entry
- Replacing ffmpeg/Pillow binaries
- Auto-prune of duplicate masters without operator confirm
- Keeping “Refresh site files” as the primary mental model
- Indefinitely wrapping legacy stage scripts behind a new UI

## Acceptance (fleet)

| Host | Must prove |
|------|------------|
| Local operator checkout | Quick + Full Check + Force + player — **passed** (2026-09-16, build 509) |
| bandpromo.site | Healthy Check + Full + Force + player — **passed** (2026-09-16, build 509) |
| spandexualtension.com | Force full rebuild on healthy catalogue + follow-up healthy — **passed** (2026-09-16, build 509; Python 3.6.9) |
| hitz.no | Diagnose names empty Files vs disk masters; Treat registers SFX + audio **with tag fill**; Full check catches dual-tag audio clones; Follow-up honest; one log voice — **re-smoke after latest Site update** |

---

_Last updated: 2026-09-16_
