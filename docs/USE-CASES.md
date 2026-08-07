# Closed-beta operator use cases

Real-life personas for closed-beta feedback during development. Not marketing copy.

Companion rules: [PLATFORM-MODEL.md](PLATFORM-MODEL.md). Shipped vs planned: [FEATURES.md](FEATURES.md), [ROADMAP.md](ROADMAP.md) → Beta tester expectations.

Closed-beta fleet today: **three** remote installs — one per persona below. See [TODO.md](TODO.md) → Beta fleet sync.

| Persona | Operator type | Stresses |
|---------|---------------|----------|
| **Vanilla** | Fresh demo-content install | Setup, demo catalog, Site update, baseline UX |
| **Twisted Chronicles** | Band / traditional campaign | Album + singles under one Release; per-release Bio/EPK; staggered playlist street dates |
| **HITZ** | Record label | Artist releases **and** long-form show episodes; Lyrics vs Tracklist on one install |

---

## Vanilla

**Goal:** Prove a clean install feels finished without custom campaign content.

**Content shape:** Seeded demo release, playlist, gallery, brand (`bandpromo-default`), FAQ + demo pages.

**Works today:** Setup, Hide demo catalog, Site update, Publish / Deliverables, Content editors against demo data.

**Feedback focus:** First-run clarity, broken empty states, update/rebuild friction, docs vs UI wording.

**Out of scope for this persona:** Custom marketing calendars (v2+), access tiers (v0.9).

---

## Twisted Chronicles (band campaign)

**Goal:** Traditional band release plan — singles and album as listening packages under one campaign umbrella, with Bio/EPK and art that belong to that campaign (and change on later campaigns when lineup or story changes).

### Content shape

- **One Release** per campaign (e.g. album cycle).
- **Playlists** under that release: four singles (≈2 tracks each) + one album playlist (≈10 tracks). Street dates stagger (e.g. four weeks apart) via each playlist’s `publish_date`. Album and a single may go public in parallel.
- **Pages:** Bio/EPK and a page that embeds a **gallery** of art/photos for this release — not one eternal site-wide Bio.
- **Gallery** container(s) associated to the release; shown via page gallery blocks (there is no dedicated Gallery player tab).
- **Tour / concert galleries (operator story):** after each show, batch-upload stills and clips → give each asset a **title**, description, keywords, and capture date → assemble a release-scoped gallery (e.g. `Hamburg Grand Stage 2026-05-17`) via the searchable multi-select picker → embed on a campaign page. Visual naming + picker ship in **v0.8**.
- **Audience engagement (same story, later build):** fans comment on and share individual gallery assets to grow community around the tour. Fan accounts / comments / share stay in this use case text; **implementation is v0.9+** (access/engagement foundation) — do not strip them from the scenario.
- **Branding:** one brand document per release (`release.brand_id`).
- Later campaigns (e.g. nine months later) are **new Releases**, each with their own Bio reflecting lineup/story changes.
- Campaign calendar / marketing automation for the plan itself is **v2+** — operators stage playlists and pages manually in v0.8.

### Works today

- Release owns track pool + associations to playlists / galleries / pages (exclusive — no stealing across releases).
- Playlist catalog exposure by `publish_date` (empty date = public), demo visibility, and non-empty tracks.
- Player brand **CSS tokens** and **visual shell** (logo, still/living backgrounds) follow the **selected playlist’s** owning release brand (else install base brand). Tracks do not carry player brand. Welcome/Logged-in SFX stay on Base (login).
- Content → Player can enable **global** page tabs (`show_in_player`). FAQ stays login/global (info lightbox), not a release Bio.

### Target / planned (not shipped)

- **Release-contextual player pages:** Playlists | Lyrics always; optional **global** pages from Content → Player (may be empty); then pages **associated to the current track’s release** append to the nav. Playing a newer release’s track shows that release’s Bio/EPK instead of the previous campaign’s.
- Hard scoping of playlist/gallery/page pickers to that release’s pools only (today: soft “prefer”; association exclusivity already enforced).

### Feedback focus

- Does Catalogue → associations match how the band thinks about a campaign?
- Pain from **global** Bio/Gallery tabs until contextual pages ship.
- Staggered singles via playlist dates vs expecting embargo-only workflows.

---

## HITZ (label + long-form shows)

**Goal:** Label showcases normal artist releases **and** long-form DJ mixes / radio-style episodes that promote talent and recordings — on one install.

### Content shape

- Multiple **Releases** (artists / campaigns) with normal tracks, lyrics, playlists, pages, brands as needed.
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
- Brand active vs per-release tokens when many artist brands share one install.

---

## Shared notes for all personas

| Concern | Shipped | Target |
|---------|---------|--------|
| Playlist street date | Playlist `publish_date` | Same |
| Page tabs in player | Global `show_in_player` only | Globals + release-contextual |
| FAQ | Required login/global surface | Same (not a campaign Bio) |
| Gallery in player | Page with gallery blocks | Same (no Gallery module tab) |
| Tour gallery assemble | Visual titles + multi-select picker (v0.8) | Same |
| Fan comment/share on gallery assets | — | v0.9+ (keep in Twisted Chronicles story) |
| Brand tokens in player | Playlist → owning release brand → active fallback | Same |
| Brand shell media in player | Per-release visual shell (logo/still/living); SFX stay Active/login | Same |
| Lyrics vs Notes panel label | Per-track `text_role` + optional `notes_label` (default Tracklist) | Same |

Idle / first-load contextual pages (which release’s tabs before play starts) is decided when contextual tabs are implemented — not in this policy snapshot.
