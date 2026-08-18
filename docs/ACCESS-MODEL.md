# bandPromo Access Model

Source of truth for login, FAQ, shared links, access tiers, and availability rules.

**Status:** policy locked for v0.8 (2026-06-15). **Implementation:** v0.9 for tier enforcement and restricted anonymous entry; v0.8 stores the data hooks (release dates, per-track overrides) needed later.

Related: [PLATFORM-MODEL.md](PLATFORM-MODEL.md), [ROADMAP.md](ROADMAP.md).

## Principles

- bandPromo remains a **closed authenticated site** through v0.8 beta; tier rules are defined now and enforced in v0.9.
- **FAQ** (`faq`) is a **system-owned** required page — platform/login help and shared-link context. It is **not** part of any Portable Campaign File (PCF). It survives **Hide demo catalogue**. Operators may edit copy; they cannot delete FAQ.
- Campaign pages (Bio, Gallery, News, …) belong to releases via `release_id` and travel in PCFs when owned by that campaign.
- **Release date** on each release is the primary public availability threshold.
- **Playlist publish date** controls playlist promotion and default selection; it does not override per-track release gates.
- **Operators and developers** always bypass availability gates.
- **No fan credits** in v0.8 or v0.9.

## Roles and tiers

| Tier | Account type | Playback | Pages / galleries | Notes |
|------|--------------|----------|-------------------|-------|
| **Operator / developer** | `admin`, `developer` | All tracks, all releases | All | Admin panel access |
| **VIP** | `vip` (registered) | Pre-release per VIP rules | Released + VIP-allowed content | Early access window |
| **Registered fan** | `user` (registered) | Released catalogue only | Released pages/content | May set own default playlist (v0.9+) |
| **Anonymous** | No account / restricted entry | Released catalogue only | Released static content | Login upsell for more |

Listener accounts today map to **registered fan** until VIP and anonymous paths ship.

## Availability evaluation

A track is **playable** for a user when all checks pass:

1. User tier bypass (operator/developer → always yes).
2. Track belongs to a release with a resolved `release_date`.
3. **Release date** has passed for the user tier, **unless** VIP early-access rules allow earlier play.
4. Per-track **operator override** does not block the tier (VIP force-release, embargo extension).
5. Delivery assets exist for the track.

A track may be **visible but not playable** when it appears in a playlist but fails checks 3–4. Embargoed rows stay in the list, greyed/locked, with no seek.

### VIP early access

- Each **release** defines a default VIP early-access offset (for example `vip_early_days: 7` before `release_date`).
- Each **track** in that release may override:
  - inherit release default
  - custom early-access days
  - **force public** (playable for all tiers before release date — operator marketing exception)
  - **extend embargo** (VIP also blocked until release date or later)

Storage sketch on release membership:

```json
{
  "asset_id": "ast_01HY8K3M2P9XQ4R5S6T7V8W",
  "slug": "belief-radio-version",
  "vip_early_days": null,
  "availability_override": null
}
```

`availability_override`: `null` | `force_public` | `embargo_extended`

### Anonymous / released-only

- May browse **released** static pages and galleries embedded on those pages.
- May open playlists in full; **embargoed tracks are visible but not playable** (locked state).
- Login page offers **restricted anonymous entry** (v0.9): listen/browse within released-only rules without creating an account.
- Shared links that require authentication redirect to **login + FAQ context**.

## Login, FAQ, and shared links

### FAQ page

- **System-owned** required page (`faq`); cannot be deleted; **not** included in PCFs.
- Explains the **platform** (login, player, what bandPromo does) more than campaign content.
- Seeded from a platform template at setup (not from `bandPromo-demo.pcf`).
- Surfaces: login lightbox, shared-link explanations, operator-editable site rules.
- Lives in `data/pages/faq.json` like any page container; hide-demo does not remove it.

### Login flow

```
Visitor hits protected URL
  → redirect to /login?return={encoded-path}
  → show install branding + FAQ excerpt/link
  → choices (v0.9):
       - Sign in (registered / VIP / admin)
       - Continue as guest (released-only anonymous)
```

v0.8: document the contract; existing login remains session-required until v0.9.

### Shared links

| Link type | Example | OG metadata source |
|-----------|---------|-------------------|
| Track | `/play/{playlist}/{release-slug}/{track-slug}` | Track + release registry |
| Page | `/pages/{page-id}` | Page container + fallback poster |
| Playlist | `/play/{playlist-id}` | Playlist + active release branding |

Authenticated-only shares redirect to login with `return` preserving the target path.

## Playlist and page access

- **Playlists** are not tier-gated as containers; **tracks inside** are gated individually.
- **Pages** may be marked `released_only: true` in registry (default for operator pages tied to a release).
- **Gallery blocks** inherit page access; gallery media follows asset release association where applicable.

## Registered fan preferences (v0.9+)

- Registered fans may set a **personal default playlist** stored in user data under `data/users/{user-id}.json`.
- System default playlist rule (latest public system playlist by `publish_date`) applies when no personal default is set.

## User playlists (future)

- VIP or registered **user-authored playlists** (`kind: "user"`) are out of v0.8 scope.
- Classification: `system` vs `user` in playlist container `kind` field.

## Analytics

- Play events always bind to **track → release**, not playlist.
- Tier at time of play may be recorded for reporting (v0.9).

## Time semantics (v0.8)

- **Storage:** listener activity, admin audit events, and analytics aggregation use **UTC** (`timestamp` ISO-8601 `Z` + `timestamp_unix`) in SQLite at `data/analytics/events.sqlite` (see [ANALYTICS-STORAGE.md](ANALYTICS-STORAGE.md)).
- **Admin display:** operators choose **UTC** or **local time** in Settings → Basics. Local mode uses the saved browser timezone (`operator.timezone` in `web-config.json`). Storage never changes.
- **Release / playlist dates:** date-only gates (`YYYY-MM-DD`) unlock at the **start of that UTC calendar day**. This is not a timed drop (no hour/minute).
- **Timed worldwide drops** (operator-local or UTC instant, fan countdown, pre/post-drop chat) are **v2 marketing** scope — see [ROADMAP.md](ROADMAP.md).

## Implementation notes (v0.9)

- Centralize availability in one server helper (for example `bandpromo_track_playable_for_user()`).
- Player receives per-track `playable: true|false` and `lock_reason` in playlist payload.
- Page delivery filters unreleased page tabs for anonymous users when `released_only` is set.

## Not in scope

- Fan credits, rebates, boons (v1+).
- OAuth providers (v1.1+).
- Payment-provider-linked premium access (operator integration layer, later).
