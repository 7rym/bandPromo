# Blotato as a social-publish candidate

**Status:** research note. Not a product commitment, not an integration, and not work for v0.8 or v0.9.

**Recorded:** 1 November 2026 (Europe/Oslo).

**Read this again when** v2+ sharing tools are actually scheduled — social publishing integrations and scheduling jobs in [ROADMAP.md](ROADMAP.md), after the destination page is worth leaving a feed for. Treat every fact below as stale until it is rechecked against Blotato’s live docs. Use the checklist at the end; do not implement from this file alone.

Operator-facing strategy stays in [MARKETING-STRATEGY.md](MARKETING-STRATEGY.md). This note is for the product team.

---

## Why it was looked at

bandPromo’s traffic model is teaser on a social platform, one bridge URL, full listen on the operator’s domain. Future helpers draft and approve posts from catalogue content. They do not autopost, and they do not replace link-in-bio, Story stickers, or the site’s own OG card.

The v2 publishing target is already locked elsewhere:

- API posts, not public-site share buttons.
- Still sizes generated on demand when publish exists: Instagram feed **1080×1350**, Stories/Reels and TikTok **1080×1920**.
- v0.8 `makeSocial.py` stays OG **1200×630** only. See [MEDIA-HANDLING.md](MEDIA-HANDLING.md) and [ROADMAP.md](ROADMAP.md).
- The operator approves anything said in public.
- Each install stays independent. bandPromo does not become the publisher, the account holder, or a central catalogue.

Blotato (blotato.com) is a hosted social API and MCP server that already covers a large part of the transport half of that job. It is a candidate adapter, not a replacement for the home-base product.

---

## What Blotato is

A paid hosted service. Publishing goes out through each platform’s official API.

| Surface | As recorded |
|---------|-------------|
| REST | `https://backend.blotato.com/v2`, header `blotato-api-key` |
| MCP | `https://mcp.blotato.com/mcp`, same key. 35 tools as of 20 October 2026 |
| Publish platforms | Instagram, TikTok, LinkedIn, Facebook, X, Threads, Bluesky, Pinterest, YouTube. Documented as exhaustive |
| Analytics | 8 of those platforms via REST. LinkedIn returned no metrics. Posts published through Blotato only |
| Comments and DMs | Instagram and Facebook only |
| Trial | 7 days, card required. API and MCP excluded. Creating an API key ends the trial and starts the paid plan |

There is no free plan and no per-post fee. Official n8n and Make.com nodes are included from Starter.

### Plans (rate card effective 10 September 2026)

| Plan | Price | Connected accounts | AI credits / month | Max upload | Queued scheduled posts | Active contacts / month |
|------|-------|--------------------|--------------------|------------|------------------------|-------------------------|
| Starter | $29 | 20 | 1,250 | 400 MB | 200 | 1,000 |
| Creator | $97 | 40 | 5,000 | 1 GB | 1,000 | 6,000 |
| Agency | $499 | 100 | 28,000 | 1 GB | 3,000 | 15,000 |

Prices are USD, monthly. Yearly billing was described as about 17% off (pay for 10 months, get 12), chosen in the billing portal rather than on the pricing page.

Connected accounts are a total across platforms. A Facebook Page counts separately from the profile it belongs to. Max upload is the largest single file, not a storage quota. Immediate posts do not count against the scheduled queue. Active contacts count people you reply to, not everyone who comments. A contact is unique per connected account.

Credits pay for Blotato’s own AI image and video generation. They are not spent on scheduling, publishing, API calls, voiceover, or turning existing content into post text. Extra credit packs were $6 for 1,000 credits, and unused credits roll over while the subscription is paid.

**Doc conflict to resolve on recheck:** the pricing rate card gives a scheduling horizon of 9 months (Starter), 1 year (Creator), and 2 years (Agency). The plan-limits help page said 9 months on every plan. A single-release calendar fits either reading. Do not design around the longer horizons until one source wins.

Starter analytics were capped at 25 metric fields and 4 checkpoints per post, collected for 30 days. Creator and Agency were described as fuller (Creator 18 checkpoints / 30 days; Agency 20 checkpoints / 90 days).

---

## Where it matches the traffic model

Checked against [MARKETING-STRATEGY.md](MARKETING-STRATEGY.md) on the recording date.

| bandPromo need | Blotato on 1 November 2026 | Reading |
|----------------|----------------------------|---------|
| Instagram / Facebook first comment with the bridge URL | `target.firstComment` on publish, including scheduled posts. Posts, carousels, and reels. Not Stories. Instagram 2,200 characters, Facebook 8,000. MCP field `firstComment` on `blotato_create_post` | Strong match for the Meta-first exit |
| Comment keyword → DM with that URL | DM automation on Instagram and Facebook only. Reply-only, no cold outreach. Meta windows apply: one private reply per comment within 7 days, and DM replies within 24 hours of the person’s last message. Automation-sent DMs count as active contacts | Strong match, easy to over-automate |
| Publish teasers to Instagram, TikTok, Facebook, X, YouTube | Those five, plus LinkedIn, Threads, Bluesky, and Pinterest | Broader than the near-term bet |
| Schedule from a playlist street date | Schedule on the publish request, or a next-free slot. API timestamps need an explicit UTC offset or `Z` | Usable only after operator approval |
| Teaser stills at the locked native sizes | Public image or video URL in `mediaUrls` with no upload step, or a presigned byte upload (`POST /v2/media/uploads`, then HTTP PUT of raw bytes). Automatic video conversion only up to 120 seconds | bandPromo still generates the files |
| OG card 1200×630 | Not their job. Link unfurls are the destination site | Stays in bandPromo |
| TikTok pinned comment or in-video link | Video publish yes. Comment and message APIs are Instagram and Facebook only | Teaser only |
| Story link sticker | Stories cannot take a first comment. No link-sticker field was documented | Do not assume |
| Playback analytics on the install | Social post metrics only, and only for posts sent through Blotato | Complement, not a replacement |
| Spotify, Bandcamp, newsletter, press | Outside the nine-platform list | Unrelated |

A public `mediaUrls` value must be fetchable without a login. Unreleased or access-gated catalogue art is not that URL. Those bytes would be a presigned upload of a **teaser still or short clip**, never a master and never delivery audio.

---

## Recommendation recorded with the note

**Do not integrate now.** No API-key field, webhook, or credit path until sharing tools are in scope.

**If it is still the right adapter when that work starts,** the only shape that passes the roadmap’s centralisation test is optional and per install:

- The operator creates their own Blotato account and pastes their own API key. No key means the feature stays off.
- bandPromo does not resell posts, hold an Agency key, or bill operators for Blotato.
- bandPromo keeps the campaign, the bridge URL (including UTM), draft copy from catalogue fields, the sized teaser, and an explicit send confirmation.
- After confirmation, Blotato receives text, media (public URL or uploaded teaser), a schedule time, and — for Instagram or Facebook — a first comment containing the bridge URL.
- Publish status is polled by submission id. Do not retry a write blindly.
- DM automation stays off until the operator turns that one automation on for that post.
- Do not use Blotato visual templates, the viral coach, faceless-video generators, or remix-from-the-open-web flows. Those spend credits and pull the product toward a content mill.

**Direct Meta remains the better spike** if the only goal is Instagram and Facebook and the project wants no vendor and no monthly floor. That path means app review, Page tokens, and Instagram professional accounts, then the same kind of work again for TikTok, YouTube, and X. Blotato’s product is that review burden already done, plus the first-comment field and the reply-only DM API. It is the better adapter once scheduling API posts beyond Meta is actually required. Neither path replaces the bio link, a Story sticker, or OG metadata on the operator’s domain.

### Who the Starter plan fits

Starter is enough for a band or a small label: 20 connected accounts and no per-post charge. A label install with many artist Pages, or more than 1,000 replied-to contacts a month, can outgrow it. Agency pricing is an operator’s own multi-artist social business, not a bandPromo hosting tier.

### What must stay true even if Blotato is rejected later

- Social publish stays v2+, after the listening destination is public enough to be the thing the teaser points at (share/OG, anonymous entry, a stable campaign URL).
- Approval before any external write.
- Integration secrets stay on the install and out of maintenance telemetry.
- Media that leaves the server for publishing is a teaser, not a master.

---

## Facts to recheck before any implementation

Open the live pages. Correct this note in the same change that starts the work, including the recorded date. If a row below has moved, the recommendation may have moved with it.

| Check | Why it matters | Where it was on 1 November 2026 |
|-------|----------------|----------------------------------|
| Product still exists and still publishes via official APIs, not browser automation | A scraper transport is not acceptable | [llms.txt](https://www.blotato.com/llms.txt), [pricing rate card](https://www.blotato.com/pricing.md) |
| Price, account cap, queue cap, upload cap, contact cap | Starter at $29 with no per-post fee is the cost assumption | [pricing.md](https://www.blotato.com/pricing.md), [billing and credits](https://help.blotato.com/settings/billing-and-credits.md) |
| API still included from the cheapest paid plan, and whether a trial can call it | Operators must not be told they can try the integration for free if creating a key starts billing | Pricing rate card; [access and authentication](https://help.blotato.com/api-and-mcp-concepts/access-and-authentication.md) |
| Publish platform list, especially Instagram, Facebook, TikTok, X, YouTube | The near-term bet is Meta; TikTok is teaser-only in the marketing note | Pricing rate card “What each plan covers” |
| First comment still exists for Instagram and Facebook posts, carousels, and reels, and still skips Stories | This is the bridge-URL placement | [First comment](https://help.blotato.com/web-app-features/first-comment.md) |
| DM automation still reply-only, Instagram and Facebook only, and still inside Meta’s messaging windows | Cold outreach would violate the approval rule | Help section “DM automation requests”; pricing rate card |
| Public URL vs presigned upload, and the 120-second auto-conversion cap | Login-gated art cannot be passed as `mediaUrls` | [Media uploads and conversion](https://help.blotato.com/api-and-mcp-concepts/media-uploads-and-conversion.md) |
| Story link stickers | Undocumented on the recording date. Do not design a Story CTA on a guess | Instagram / Facebook help |
| TikTok comment or pin APIs | Marketing note already treats TikTok comment inbox APIs as weak | Comments coverage was Instagram and Facebook only |
| Scheduling horizon (the 9-month vs tiered conflict) | Only matters for long tour calendars | Pricing rate card vs [plan and request limits](https://help.blotato.com/start-with-an-ai-agent/llm/limits) |
| Analytics coverage and Starter metric caps | Useful as a complement to install playback analytics, not a substitute | Pricing rate card; analytics help |
| Credits still excluded from ordinary publishing | Avoids a surprise usage bill for approved posts | Pricing rate card “Credits” |
| Key handling and whether media uploaded for publish is retained by Blotato | Operator responsibility and telemetry rules | Upload and account docs; [OPERATOR-RESPONSIBILITY.md](OPERATOR-RESPONSIBILITY.md) |

Also re-read the centralisation test in [ROADMAP.md](ROADMAP.md) (v2+ direction). A shared bandPromo Blotato account fails it. A per-install key the operator can omit passes it.

---

## Sources read on 1 November 2026

- https://www.blotato.com/pricing.md — rate card, effective 10 September 2026
- https://www.blotato.com/llms.txt — product summary
- https://help.blotato.com/web-app-features/first-comment.md
- https://help.blotato.com/api-and-mcp-concepts/media-uploads-and-conversion.md
- https://help.blotato.com/start-with-an-ai-agent/llm/limits — plan limits, including the horizon wording that conflicts with the rate card
- https://help.blotato.com/sitemap.md — doc index used to find the pages above

bandPromo side: [MARKETING-STRATEGY.md](MARKETING-STRATEGY.md), [ROADMAP.md](ROADMAP.md) v2+ goals and product boundaries, [MEDIA-HANDLING.md](MEDIA-HANDLING.md) share sizes, [TODO.md](TODO.md) note that v2+ stays out of the active milestone list.
