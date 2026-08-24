# Session Handoff

## Resume point

Local checkpoint done: **v0.8.35 build 433** (`6a38947`) — not pushed/published yet.

Includes:

1. **primary → Active shell fix** + Base brand fallback; publish heals `campaign_id`.
2. **`campaign_id` canonical** (migrate-and-drop `release_id`; `data/campaigns/`).
3. **Orphan visual delivery GC** in Content autofix.
4. **Legacy aliases:** `cntab=themes` → branding.
5. **Track editor tags/badges:** registry-based C/A/T/R/D/L; save writes lagging master tags; response prefers artist/title/version.

Next: push + publish release package; Site update on HITZ (and Spandexual if needed); Content autofix once; optionally re-save remaster tracks so empty master tags get embedded.

## Plan document

`docs/ADMIN-EDITOR-REFACTOR.md` + Cursor plan `close_remaining_gaps`.
