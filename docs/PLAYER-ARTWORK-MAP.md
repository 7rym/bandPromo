# Player Artwork map (discussion base)

**Audience:** closed-beta testers comparing Preferred types in Branding → Player → Artwork.  
**Status:** working map of **what ships today** — not a locked design decision. Use it to ground the “what goes where / why” discussion; propose changes against this baseline.

Related product notes: [PLATFORM-MODEL.md](PLATFORM-MODEL.md) (Player chrome + living covers), [FEATURES.md](FEATURES.md).

---

## What the Artwork slot is

On `/play`, the large cover card in the media player is the **Artwork slot**. Branding → **Preferred type** chooses which picture (or living video) that slot prefers.

It is **not** the shell backdrop (still/living background behind the player), the campaign navigator strip, or the playlist selector thumbs. Those are separate surfaces.

---

## Preferred types available today

| Preferred type | What the Artwork slot tries to show | Typical ratio | Living video? |
| --- | --- | --- | --- |
| **Still** | The **track** still cover | Square (track art) | No — even if the track has a living cover |
| **Living** | The **track** living cover while audio is playing; track still when paused / reduced motion / no living assigned | Square | Yes, when the track has a living cover and delivery video exists |
| **Playlist** | The **active playlist** cover | Usually square | No |
| **Campaign** | The **active campaign** poster, else campaign logo | Often wide / non-square | No |

**Later (roadmap, not shipped):** a **Video** Preferred type for music videos — do not invent behaviour for that here yet.

---

## Resolution order (what wins)

For a given Preferred type, the player resolves one still URL and optionally one living URL, then paints the Artwork slot.

### 1. Soft brand first (which brand’s Preferred type?)

1. The **playlist’s release brand** (when the playlist owns a brand) supplies Preferred type, Size / Peeks CSS, and shell media.  
2. If the playlist has **no** brand, the install **Base** brand is used.  
3. Branding Live preview always shows the **brand you are editing** — that may not be Base, and may not be the playlist brand you are listening under on `/play`. Save + which brand owns the playlist both matter when comparing preview vs live.

Login form, Support banner, and transport Animation still read install **Base** until those soft-apply too (separate from Artwork).

### 2. Inside the Artwork slot (per Preferred type)

**Still**

1. Track still cover  
2. If missing → empty / fallback handling (no playlist or campaign art is substituted)

**Living**

1. Track living cover **while audio is actively playing** (and tab visible; not reduced-motion)  
2. Otherwise → track still  
3. Tracks without a living cover always show still

**Playlist**

1. Active playlist cover  
2. If missing → track still

**Campaign**

1. Active campaign **poster**  
2. Else campaign **logo**  
3. If both missing → track still

Clicking the Artwork opens the lightbox with the **same** visual the card is showing (living video when living is on screen; otherwise the Preferred still, including playlist/campaign art).

---

## Forced companions (Playlist & Campaign)

When Preferred type is **Playlist** or **Campaign**:

| Setting | Forced behaviour | Why (current product stance) |
| --- | --- | --- |
| **Artwork reflection** | Off | Shared identity art is not a spinning track card; mirror looks wrong on wide posters |
| **Peeks** (and Fill / Spread / Colour) | Off | Neighbour peeks are previous/next **tracks**; they do not match a single playlist or campaign image |

Same idea as Size **Large** already forcing Peeks off for geometry. Switching Preferred back to Still or Living restores control of reflection / peeks (re-enable them if a save under Playlist/Campaign cleared them — same pattern as Large).

**Campaign fit:** campaign art often is not square. The slot stays the same footprint, but the image uses **contain** (full image visible, letterboxed) instead of **cover** (cropped to fill). Still / Living / Playlist keep cover-crop for square track/playlist art.

---

## What does *not* change with Preferred type

These stay on their own controls / surfaces:

- Shell **still / living backdrop** behind the player  
- **Size** (Large / Normal / Small) — footprint of the control surface  
- Transport **Controls** glass (Fill, Blur, Corners, Border, Density) and **Animation**  
- **User area** (Login form, Support banner)  
- Campaign navigator logos and playlist selector presentation  

Living **shell** video (backdrop) is independent of Preferred type **Living** (Artwork slot). You can have a living backdrop with Still Preferred art, or Living Preferred art with a still backdrop.

---

## Easy sources of “overwrite” confusion

1. **Preview brand ≠ listening brand** — editing Remixes brand Living while `/play` is on a playlist that still soft-applies another brand (or Base).  
2. **Preferred Still vs track living cover** — living covers exist on the track but Still Preferred never promotes them into the Artwork slot.  
3. **Living only while playing** — paused / background tab shows still even when Preferred is Living.  
4. **Playlist/Campaign vs peeks** — peeks look “broken” or missing because they are forced off; that is intentional today.  
5. **Campaign crop** — older builds cropped wide posters; current rule is contain (see full width of the art, possible bars above/below).  
6. **Shell logo vs campaign poster** — Campaign Preferred prefers campaign poster, then logo; brand shell logo is a different slot.  
7. **Soft brand vs Base** — Size and Preferred soft-apply with the playlist brand; some User area / Animation toggles still follow Base.

---

## Suggested discussion prompts (not decisions)

- Should Preferred **Living** mean “always allow track living when assigned” even under a future multi-source mix, or stay an exclusive Preferred type?  
- Should Playlist/Campaign keep forcing peeks off, or should peeks show adjacent playlists/campaigns instead of tracks?  
- Should Campaign change the **card aspect** (e.g. wide frame) instead of letterboxing inside a square?  
- Should Soft brand also remirror Login / Support / Animation so one brand document always matches `/play`?  
- Is “Preferred type” the right name, or do testers think of this as “Artwork source”?

When the group locks a change, update [PLATFORM-MODEL.md](PLATFORM-MODEL.md) and this map in the same pass so the discussion base does not drift.
