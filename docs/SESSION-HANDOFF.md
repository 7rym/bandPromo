# Session Handoff

## Resume point

**Closed:** Player size + Campaign navigator (**605**). Site health Keep-choice (**607**). Branding Player remirror (**608**). Artwork / User area / slug fold / Support — **v0.8.100**. Stay signed in + Lyrics tab — **611**. Content remirror + navigator polish (Idle items Full|Soft|Grey, brand images, demo playlist rows) — **612**.

**Closed:** **v0.8.101 build 615** published — Create Test connections attaches base brand logo to fal Nano Banana 2 as a reference image.

**Closed:** **v0.8.101 build 617** published — Create Start cheapest OpenAI + fal vendor edit models (logo refs required).

**Closed:** **v0.8.101 build 619** published — Create OpenAI prompt models are GPT-6 Luna (default), Sol, Astra.

**Closed:** **v0.8.101 build 621** published — Create image prompt limit raised to 1000 characters.

**Closed:** **v0.8.101 build 623** published — Create image prompts avoid filling stills with readable brief/prompt text.

**Closed:** **v0.8.101 build 624** published — Create Start prefs: Cheap|Good|Premium LLM/image/video (fal any-llm + OpenAI), register passed tests, configured summary + Reconfigure; wizard placeholder.

**Closed:** **v0.8.101 build 626** published — Create Start restored as connection proof (quiet ready + Reconfigure); Preferences subtab (per-panel Cheap|Good|Premium + test/Result + Save); localhost SSL CA for AVG; Preferences UI polish.

**Active now:** Create tools beyond Start/Preferences later; operator may smoke Site update on a host.

**Last published:** **v0.8.101 build 626**. Prior: **624** prefs-on-Start (superseded); **623** less lettering; **621** 1000-char prompts.

**Parked / later:**
- Create setup wizard (guided prefs) if still wanted later.
- Create tool sub-tabs (blurb writer, artwork studio, living artwork) beyond Start + connection proof.
- Files pool / living-cover picker latency (undercount disk probe + full Visual list for video-only pick).
- Lyrics: density|size Branding control; timed cues / `.srt` / bidirectional anchors.
- Favicon + PWA icons from Branding; legacy audit refresh (remaining exit-gate items).
- Optional cleanup: dead Content `theme-preview-*` builders in `brand-preview.js` / unused CSS.

**Operator rule (this session):** Do **not** delete, prune, janitor-Apply, or discard any runtime files (`data/`, `media/`, `log/`, `backups/`, Ready archives, leftover `original/`) unless this user names the exact paths in the same message.

**Next:** Optional smoke Create Start → Preferences after Site update. Living-art / blurb tool UIs stay later.

### Policy locked (do not reopen)

- **Player size** Large|Normal|Small = stacked 0.62/0.52/0.42 + split 1/0.9/0.75; **short landscape = one platform size** (no preset, no art clip); User bar always on (tools left; username or guest invite); Login form None|Minimalistic|Full above Support banner (preview only); Support banner may wrap; Large disables Peeks — [PLATFORM-MODEL.md](PLATFORM-MODEL.md)
- **Stacked pass criteria:** first playlist item peeks; transport/Support banner ≥ `--player-chrome-min` (not crushed to small cover); compact selectors; selected campaign/playlist on-screen; overflow edge fades when scrollable; short cover ≥ 140; iPad portrait = stacked wide (split only ≥60rem)
- Transport **aspect ratio unlocked** for experiment (provisional 4:1 in CSS today; lock later only if needed)
- Sticky chrome over scrolling content: **opaque** `--admin-sticky-chrome-bg`; in-flow headers may keep `--admin-slate-wash`
- Page builder: add-block sticky under breadcrumb (`--page-builder-sticky-top`); do **not** nest rich-text sticky under it
- Form field grids: flat peer rows (not multi-field stacks in one cell); BPM/Key narrow, Genre flex
- Modal long textareas: autosize; modal body is the only scroller
- Markdown hints trail the label / toggler on the same line
- Files mutating modals: solid green Save; quiet Audio crumb; `#adminConfirmModal` above editors
- Audio crumb compound title is read-only by design
- Audio Details field order: artists → title/version → genre/BPM/key → release date
- Media picker campaign filter uses param `campaign` (not `release`)
- Dashboard order: Site update → demo suggest → Quick actions → What to do next
- Sub-tab help: Files canon — accent bar + ⓘ + `--admin-help-*` box with `<ul>`; no hardcoded accent hex/rgba in rules
- Admin `--accent` is deep steel blue `#3a6a94` (not coral, not fluorescent sky)
- Visual Assign may register-on-assign for orphan originals lacking `asset_id`
- Catalogue/playlist cover pending preview must match by asset id or full path — never shared basenames like `card.jpg`
- Upload toasts stay brief; do not dump Site health task lists into them
- Background video/audio prep: toast on ready/failed; do not inbox running/done; auto-delivery tasks are not a Notifications nag
- Visual upload Campaign field must post `campaign_id` like Audio
- **From brand** / library detach clears shell slots on that brand; do not re-block “clear Branding first”
- **From brand** dropdown = membership only (never every brand on the install); offer “Every brand in this selection” when the selection spans 2+ brands
- Pool **In brand** chip = library membership (`brand_ids`), separate from In use / Unused assignment
- Video MKV remux maps video + optional audio only (drop data/timecode tracks)
- ffmpeg capture must decode stderr as UTF-8 with replace (Windows charmap)
- Sticky playlist-scan Notifications heal on bell (full) or Site health Check start
- Archival original discard lives under **System → Status → Storage** only (not Files); Site health janitor also reclaims leftover intake + stray media ZIPs on Review → Apply
- Durable media model is **master + delivery**; intake is ephemeral (`temp/media-intake/`), discarded when the master succeeds
- Files pools/pickers/downloads are **masters only** — no Original|Master list toggle; originals are disposable intake
- Product resolve never falls back to archival `original/` or legacy `img`/`photo`/`video`/`special` when the master is missing (fail loud / empty)
- Never invent `original_filename` from `master_filename` on register/normalize/Treat
- VERSION **session** number bumps only when the operator explicitly starts a session (`session-start.ps1 -BumpSession` / `/bandpromo-session-start`); agent resume must not bump
- Handoff Closed / Last published / Next must be written **into the same publish build** — never a follow-up `session-end` only to refresh `SESSION-HANDOFF.md` after `-Push -Publish`
- PCF/PBF import auto-starts Site health Treat (delivery-focused selection; no silent dedupe delete / Ready-archive prune); Jobs show **Open Status**
- User shell isolated from admin chrome; Brand not Theme; casing by layer (`.cursor/rules/identifier-casing.mdc`)
- Branding preview waits until Player + Content public surfaces are correct
- Cast (v0.9+): `#mediaplayer` = sender; speakers-only first-class; display CAF receiver separate — [DELIVERY-ARCHITECTURE.md](DELIVERY-ARCHITECTURE.md) Sender vs receiver
- Campaign navigator: Content header logo only when one campaign; multi-campaign strip opaque + larger selected; campaign `navigator_logo_asset_id` ≠ brand shell logo; strip/pools sort newest-first by date
- Pages prose: `.page-prose-row` claims full flex row; measured panel centred inside; `.page-picture-body` uses Content panel fill
- Large audio upload final chunk: return after assemble + spawn finalize worker only (do not sync master move / tags / scan / delivery on the upload request — HITZ proxy 504)
- Player load-failure **Operator:** hints are admin/developer only (`BANDPROMO_IS_OPERATOR`); public listeners get title + soft detail only
- Visual master embed: non-ASCII titles via UTF-8 ffmetadata (never `-metadata title=…` argv on ASCII hosts); video tag work dir beside master + EXDEV-safe replace
- Create tab is **developer-only** until helper tools ship; AI keys + prefs are per signed-in developer; **Start** = connection proof only (hide after pass until Reconfigure, Dashboard-style); **Preferences** = separate LLM/Image/Video panels with per-panel Cheap|Good|Premium + test/Result + Save; media models must take reference images; brand logo required for image/video tests; localhost HTTPS may need `%USERPROFILE%\.bandpromo\php-ca-bundle.pem` when antivirus MITMs TLS

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here.
