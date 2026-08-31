# Closed-beta operator use cases

Real-life personas for closed-beta feedback during development. Not marketing copy.

Companion rules: [PLATFORM-MODEL.md](PLATFORM-MODEL.md). Shipped vs planned: [FEATURES.md](FEATURES.md), [ROADMAP.md](ROADMAP.md) → Beta tester expectations.

**Active closed-beta fleet (2026-08-31):** three remote installs on **v0.8.36 build 438** — one per persona below. This Google Drive working copy is **never** a wipeable test install. Fresh installs always use **https://bandpromo.site**.

| Persona | Host | Operator type | Stresses |
|---------|------|---------------|----------|
| **Vanilla** | **https://bandpromo.site** | Demo-only install | Setup, demo catalogue, Site update, baseline UX |
| **Spandexual Tension** | **https://spandexualtension.com** | Band / traditional release sequence | Singles + album under Campaign umbrellas; Bio/EPK; staggered playlist street dates |
| **HITZ** | **https://hitz.no** | Record label | Short artist releases **and** long-form show episodes; Lyrics vs Tracklist |

**Paused until v0.9:** **Twisted Chronicles** (**https://twistedchronicles.eu**) — install too old for a safe Site update; tester will **reinstall** and rejoin when v0.9 opens. Until then, Spandexual Tension carries the traditional band / release-sequence persona that Twisted Chronicles previously held.

---

## Vanilla

**Host:** always **https://bandpromo.site**. Never this Google Drive working copy.

**Goal:** Prove a clean install feels finished without custom campaign content.

**Content shape:** Seeded demo release, playlist, gallery, brand (`bandpromo-default`), FAQ + demo pages. **Still demo-only** as of build 438.

**Works today:** Setup, Hide demo catalogue, Site update, Publish / Deliverables, Content editors against demo data.

**Feedback focus:** First-run clarity, broken empty states, update/rebuild friction, docs vs UI wording.

**Out of scope for this persona:** Custom marketing calendars (v2+), access tiers (v0.9).

---

## Spandexual Tension (band / traditional release sequence)

**Host:** **https://spandexualtension.com** — active band persona from 2026-08-31 (took over the role formerly exercised on Twisted Chronicles).

**Goal:** Traditional band release plan — singles and album as listening packages under campaign umbrellas, with Bio/EPK and art that belong to that campaign (and change on later campaigns when lineup or story changes).

### Content shape

- **One Campaign** per album cycle (e.g. the Violator-era umbrella).
- **Playlists** under that release: four singles (≈2 tracks each) + one album playlist (≈10 tracks). Street dates stagger (e.g. four weeks apart) via each playlist’s `publish_date`. Album and a single may go public in parallel.
- **Pages:** Bio/EPK and a page that embeds a **gallery** of art/photos for this campaign — not one eternal site-wide Bio.
- **Gallery** container(s) associated to the campaign; shown via page gallery blocks (there is no dedicated Gallery player tab).
- **Tour / concert galleries (operator story):** after each show, batch-upload stills and clips → give each asset a **title**, description, keywords, and capture date → assemble a release-scoped gallery (e.g. `Hamburg Grand Stage 2026-05-17`) via the searchable multi-select picker → embed on a campaign page. Visual naming + gallery multi-select picker ship in **v0.8** (Available drag-and-drop remains secondary).
- **Audience engagement (same story, later build):** fans comment on and share individual gallery assets to grow community around the tour. Fan accounts / comments / share stay in this use case text; **implementation is v0.9+** (access/engagement foundation) — do not strip them from the scenario.
- **Branding:** one brand document per release (`release.brand_id`).
- Later campaigns (e.g. nine months later) are **new Campaigns**, each with their own Bio reflecting lineup/story changes.
- Campaign calendar / marketing automation for the plan itself is **v2+** — operators stage playlists and pages manually in v0.8.

### Works today

- Campaign owns track pool + associations to playlists / galleries / pages (exclusive — no stealing across campaigns).
- Playlist catalogue exposure by `publish_date` (empty date = public), demo visibility, and non-empty tracks.
- Player brand **CSS tokens** and **visual shell** (logo, still/living backgrounds) follow the **selected playlist’s** owning release brand (else install base brand). Tracks do not carry player brand. Welcome/Logged-in SFX stay on Base (login).
- Content → Player can enable **global** page tabs (`show_in_player`). FAQ stays login/global (info lightbox), not a release Bio.

### Target / planned (not shipped)

- **Campaign-contextual player pages:** Playlists | Lyrics always; then pages **associated to the current track’s campaign** append to the nav. Playing a newer campaign’s track shows that campaign’s Bio/EPK instead of the previous campaign’s.
- Hard scoping of playlist/gallery/page pickers to that release’s pools only (today: soft “prefer”; association exclusivity already enforced).

### Feedback focus

- Does Catalogue → associations match how the band thinks about a campaign?
- Pain from **global** Bio/Gallery tabs until contextual pages ship.
- Staggered singles via playlist dates vs expecting embargo-only workflows.

---

## HITZ (label + long-form shows)

**Host:** **https://hitz.no**

**Goal:** Label showcases normal artist releases **and** long-form DJ mixes / radio-style episodes that promote talent and recordings — on one install.

### Content shape

- Multiple **Campaigns** (artists / shows) with normal tracks, lyrics, playlists, pages, brands as needed.
- Long-form **episodes** as tracks (often their own playlists or packages) whose “lyrics” field is really a **tracklist**.
- Operators currently paste tracklists into the Lyrics field and would like the player nav to say **Tracklist** for those items without renaming Lyrics for song singles.

### Works today

- Same catalogue / association / playlist `publish_date` model as other installs.
- One player shell panel and one master text field (`lyrics` / USLT), Markdown-rendered.
- Per-track **text panel role:** `lyrics` | `notes` (default `lyrics`). Notes mode uses optional player tab label (default **Tracklist**; e.g. Show notes).
- Files → Audio editor: Lyrics ↔ Notes toggle + optional label; health chips follow the panel label.
- Deferred: separate Tracklist field, dual tabs, timed cue tracklists.

### Feedback focus

- Mixing artist releases and long-form episodes in Catalogue / Files.
- Brand active vs per-campaign tokens when many artist brands share one install.
- **Campaign-first player navigation:** **v0.8 exit gate** — select campaign, then see that campaign’s playlists. Must ship before expanding the tester pool. Policy lock then implement ([TODO.md](TODO.md) → Player Campaign navigator).

---

## Twisted Chronicles (paused — rejoin at v0.9)

**Host:** **https://twistedchronicles.eu**

**Status (2026-08-31):** Install is too far behind for a safe Site update. Operator will **wait and reinstall** when the project opens **v0.9**, then follow the fleet again. Do not treat this host as an active v0.8 validation target.

**Historical role:** Band / traditional campaign persona — now exercised by **Spandexual Tension** for the remainder of v0.8.

---

## Shared notes for all personas

| Concern | Shipped | Target |
|---------|---------|--------|
| Playlist street date | Playlist `publish_date` | Same |
| Page tabs in player | Global `show_in_player` only | Globals + release-contextual |
| FAQ | Required login/global surface | Same (not a campaign Bio) |
| Gallery in player | Page with gallery blocks | Same (no Gallery module tab) |
| Tour gallery assemble | Visual titles + multi-select picker (v0.8) | Same |
| Fan comment/share on gallery assets | — | v0.9+ (keep in Spandexual Tension / band tour story) |
| Brand tokens in player | Playlist → owning release brand → active fallback | **v0.8 exit:** Campaign navigator (campaign → playlists) |
| Brand shell media in player | Per-release visual shell (logo/still/living); SFX stay Active/login | Same |
| Lyrics vs Notes panel label | Per-track `text_role` + optional `notes_label` (default Tracklist) | Same |

Idle / first-load contextual pages (which release’s tabs before play starts) is decided when contextual tabs are implemented — not in this policy snapshot.
