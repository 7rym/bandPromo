# bandPromo Delivery and Exposure Architecture

Source of truth for protected media delivery, PWA/offline behaviour, caching, and cast/distribution boundaries.

**Status:** policy locked for v0.8 (2026-06-15). Implementation is a v0.8 track (delivery/PWA) and v0.9+ (cast send).

Related: [PLATFORM-MODEL.md](PLATFORM-MODEL.md), [MEDIA-HANDLING.md](MEDIA-HANDLING.md).

## Core rule

**PHP authorizes; PHP does not stream long-lived audio bytes in production.**

Session/auth checks stay in PHP. Playback bytes come from a **cache-friendly protected delivery path** (web-server static handoff, signed URLs, or equivalent).

## Delivery tiers and paths

| Tier | Purpose | Served how |
|------|---------|------------|
| **Original** | Recovery, distributor export | Not web-served to listeners |
| **Master** | Operator editing, packaging | Admin/download only |
| **Delivery** | Playback, gallery, OG images | Protected static or signed URL |

Delivery files use `ast_{ULID}` names (see [PLATFORM-MODEL.md](PLATFORM-MODEL.md)). Operators never manage delivery paths directly.

### Authorization flow

```
Player requests track
  → PHP/session validates user + track availability (ACCESS-MODEL)
  → PHP issues short-lived delivery grant (signed URL token or session-scoped path)
  → Client fetches bytes from delivery path (CDN/nginx/static)
  → Range requests supported on delivery tier
```

Current `audio.php` byte streaming is **legacy**; migrate to grant + static delivery.

## Cache contract

Three cache classes:

| Class | Examples | Strategy |
|-------|----------|----------|
| **Immutable build assets** | Versioned JS/CSS, `ast_*` delivery files with content hash in URL or manifest | Cache aggressively, long TTL |
| **Revalidated runtime data** | `data/` playlists, pages JSON, registries | Short TTL or version etag; SW revalidate on activate |
| **Protected media** | Audio/video delivery | Cache only after auth grant; respect grant expiry |

### PWA / service worker

- Shell (player JS/CSS) updates via `skipWaiting` + client reload policy documented per release.
- **Audio caching in SW** only after delivery path is grant-based and update-safe.
- Bounded storage with LRU eviction for offline audio (operator-configurable cap later).
- Stale-shell risk: manifest or `VERSION` check on player boot triggers refresh prompt.

## Offline and degraded modes

| Service | Offline | Degraded |
|---------|---------|----------|
| Cached audio playback | Yes (after prior authorized cache) | — |
| Player shell | Yes (installed PWA) | Browser tab may need network |
| Page content (static) | Cached pages optional phase 2 | Show stale with banner |
| Login / auth | No | Clear message |
| Admin | No | — |
| Analytics log upload | Queue locally, sync when online | v0.9+ |
| Build / upload | No | — |

### Known limitation (documented)

Real-phone **screen-off** playback and **next-track handoff** can still fail on mobile browsers/PWA even after v0.7 player hardening. This is a **delivery/state architecture** issue, not a v0.8 beta gate blocker. Target fix: authoritative playback state in the client, presentation layer best-effort, grant-based delivery + SW cache.

### Installed PWA success criteria

Installed phone experience should beat browser tab for:

- faster repeat startup (cached shell)
- offline playback of previously authorized tracks
- reliable update propagation (no indefinite stale player)
- lock-screen metadata via Media Session API (already partial)

## Exposure / cast architecture (v0.9+ implementation)

**Scope (locked):** any media the user can **play or view** on the site may be cast — player audio, gallery videos, inline page video, future module media.

### Boundaries

- Cast uses **delivery-tier URLs** with the same availability grants as local playback.
- Cast sender lives in the **player shell** and **page media surfaces**; not a separate PHP stream.
- Receiver displays metadata from asset registry + release container (title, artist, artwork).
- No cast of unreleased/locked content for tiers that cannot play it locally.
- Chromecast is first target; architecture stays provider-agnostic (`cast_target` abstraction).

### Not in cast v1

- Multi-room sync beyond platform defaults
- Cast of admin-only or operator preview content
- Cast as a substitute for public anonymous distribution

## Logging and analytics delivery

- Playback logs post to existing endpoints; offline queue is optional follow-up.
- Delivery grants must not leak in logs beyond coarse `grant_issued` / `grant_denied`.

## Security

- Signed grants: short TTL, bound to session or install secret, single-asset scope.
- Delivery paths not guessable (ULID + optional HMAC path segment).
- `data/` and `media/audio/original/` remain non-web-readable.

## Implementation phases

### v0.8

1. Lock grant + static delivery contract.
2. Audit `service-worker.js` (strategy, exclusions, stale risks).
3. Migrate player off long-lived `audio.php` streaming where possible.
4. Document operator-facing limitation note for mobile background playback.

### v0.9

1. Service worker audio cache with eviction.
2. Cast sender for full in-scope media.
3. Offline log sync (if prioritized).

## Related removal

- No new features should assume PHP reads and streams entire FLAC/MP3 files per range request in the long-term path.
