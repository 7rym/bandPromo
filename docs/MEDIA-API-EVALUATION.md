# External media APIs for content AI wizards

**Status:** research note. Not a product commitment and not an integration. Provider settings for content AI wizards remain unlocked until v0.9+ ([TODO.md](TODO.md)).

**Recorded:** 3 November 2026 (Europe/Oslo).

**Read this again when** locking **Settings → AI** / operator API settings for content AI wizards — the v0.9+ work that fills missing release and brand fields and optional image briefs, with drafts confirmed before Visual-pool save (`origin: ai-generated`). Treat every model name and price below as stale until rechecked. Use the checklist at the end; do not implement from this file alone.

Prompt and product policy stay in [PLATFORM-MODEL.md](PLATFORM-MODEL.md) and [ROADMAP.md](ROADMAP.md). This note is only about **which external helpers** can host the generation.

---

## Why Replicate felt stale

bandPromo had considered Replicate as the external helper for creating media files. Marketing and older bookmarks still push **Imagen 4** and **FLUX.2 Pro** as flagships. That wording is behind the market:

| Model people still cite | Status on 3 November 2026 |
|-------------------------|---------------------------|
| Google Imagen 4 | Legacy. Google’s Gemini API docs say Imagen models are shut down / shutting down and point new work at the **Nano Banana** Gemini image family (`gemini-nano-banana-2.1` as the workhorse; Nano Banana Pro for premium). |
| Black Forest Labs FLUX.2 Pro | Still useful and widely hosted, but **FLUX 3** is already on production APIs (including Replicate’s recommended list and fal). Treating Flux 2 as “the” default is a generation behind. |

So the stale feeling is mostly about **default model IDs**, not about Replicate being abandoned. Replicate’s live catalogue on the same day also carried FLUX 3, Kling 4.0, Seedance 2.x, GPT Image 2 / 2.5, Grok Imagine, and MiniMax H3 — alongside older Flux 2 and Imagen 4 listings. A wizard that hard-codes `google/imagen-4` or `flux-2-pro` will look outdated on any host; a wizard that stores the model id in Settings will not.

---

## What bandPromo actually needs from a provider

Content AI wizards (v0.9+):

- Operator-configured external model/API settings (provider, keys, limits, disclosure).
- Optional image briefs from brand style refs; generated files enter the Visual pool only after confirm.
- Self-hosted PHP install: the key lives on the install, same class of secret as any other integration.
- No central bandPromo media account. Core product works with AI helpers off.
- Modest volume: cover briefs, shell stills, living-cover seeds — not a content mill.

That prefers a **REST API with a bearer or key header**, prepaid or pay-per-output billing the operator owns, and the ability to change model ids without rewriting bandPromo.

It does **not** prefer a creator studio that only talks MCP/OAuth to desktop agents, or a site that sells web credits separately from an undocumented API.

---

## Candidates compared

| Provider | Shape for a PHP install | Model freshness (3 Nov 2026) | Fit |
|----------|-------------------------|------------------------------|-----|
| **Replicate** | REST + token. Huge catalogue, Cog/custom deploys, fine-tunes. Cloudflare-owned. | Has current models (Flux 3, Kling 4, Seedance, GPT Image 2.5) **and** still surfaces older Imagen 4 / Flux 2 marketing. Freshness is operator-chosen, not automatic. | **Still viable.** Good default if the team already knows it. Do not treat collection landing pages as the model picker. |
| **fal.ai** | REST Model APIs with API key; queue endpoints; 1,000+ hosted media models. Separate Serverless product for own code. | Docs and pricing already centre Nano Banana 2.1, GPT Image 2.5, Flux 2/3, Kling v3 / O3, Seedance 2.x, MiniMax H3, Wan 3. Often cited as the media-first Replicate alternative. | **Strongest peer to Replicate** for bandPromo’s media job. Prefer over Replicate if speed/cost and closed-model freshness matter more than community Cog breadth. |
| **Higgsfield API** | Real REST (`Authorization: Key id:secret`), estimate endpoint, webhooks, pay-as-you-go USD balance separate from higgsfield.ai website plans. Outputs retained ~7 days then deleted. Funds expire after one year. | 50+ frontier image/video models (Kling, Seedance, Wan, PixVerse, MiniMax, Soul, etc.). | **Optional video-heavy adapter**, not the only default. Good when an operator already uses Higgsfield creatively; balance expiry and dual product (web vs API) need clear operator copy. |
| **OpenArt** | Hosted **MCP** at `https://mcp.openart.ai/mcp` with OAuth (PKCE). CLI for agents. Independent profiles report **no public REST API-key programme**. | Creator studio aggregates Seedance, Veo, Sora, GPT Image, Kling, etc., aimed at chat agents and the OpenArt app. | **Wrong shape** for Settings → AI on a self-hosted install. Fine as a human/agent studio operators use *outside* bandPromo; do not make it the install’s generation backend. |
| **Runware** | REST `https://api.runware.ai/v1`, Bearer API key, image and video inference, async + webhooks. Community PHP wrappers exist. | Cost-focused catalogue (Flux, Kling, Seedance, Veo/Wan-class models depending on listing). | **Worth a second look** if price-per-image dominates. Narrower “platform story” than fal/Replicate; still a valid optional provider. |
| **Direct lab APIs** (Google Gemini / Nano Banana, OpenAI GPT Image, xAI Grok Imagine, BFL, …) | Each its own key and schema. Same REST/key pattern as specialty hosts. | Newest models appear here first. Same account often already pays for text. | **First-class path, not an afterthought.** Operators who already have OpenAI / Gemini / Grok API keys should use those for stills (and text drafts) without buying a media-only host. |
| **Specialty media hosts** (Replicate, fal, Higgsfield, Runware) | One key, many third-party models. | Useful when the operator wants Flux/Kling/Seedance without a lab account per brand. | **Optional add-on**, not the only allowed path. Do not force every install onto one of these. |

Creator-facing websites that look like OpenArt or the Higgsfield **app** (templates, characters, credit packs, MCP for Claude) solve a different problem: helping a person make media in a studio. bandPromo’s problem is: an install calls a key, gets bytes back, operator confirms into the Visual pool.

---

## Design rule: many keys, many models — not one forced host

If bandPromo adds API-key solutions for external services, it must **not** force operators onto a single specialty media host or a single model.

Operators often already pay for **general** AI accounts that can do both text and images:

| Operator already has | Use it for | Avoid assuming |
|----------------------|------------|----------------|
| OpenAI API key | GPT Image stills + text blurbs / alt text on the same bill | ChatGPT Plus / Team alone (consumer chat ≠ API image billing) |
| Google AI Studio / Gemini API key | Nano Banana stills + Gemini text on the same bill | Gemini app / AI Plus alone (app plan ≠ paid API image rates) |
| xAI API key | Grok Imagine stills (+ Grok text if used) | Grok consumer subscription alone |

Specialty hosts (Replicate, fal, Higgsfield, Runware) remain useful when the operator wants Flux, Kling, Seedance, or fine-tunes without opening a second lab account — or when a model only appears there. They are **one row in Settings**, not the product identity.

**Settings → AI shape (recorded intent):**

- Zero or more **provider slots** on the install (e.g. `openai`, `google`, `xai`, `fal`, `replicate`, later others).
- Each slot: enabled flag, API key (secret), optional org/project, default image model id, optional default text model id, spend/limit hints.
- Wizard UI: pick which configured provider + model to use for *this* draft (cover brief vs blurb), with a sensible default from Settings.
- bandPromo ships with helpers off. No key means no external call.
- bandPromo never holds a shared fleet key and never resells generations.

That lets an operator burn leftover OpenAI or Gemini credit for ten cover drafts instead of topping up fal only for images — which was the explicit product goal recorded with this pricing pass.

---

## Cost sketch: 10 album covers at 2K

**Scenario (3 November 2026):** generate **10 square album-cover stills** at about **2K** (2048×2048 where the host uses megapixels; the host’s `2k` / `2K` tier where it uses resolution menus). One successful text-to-image per cover. No reference images, no edits, no retries, no video. USD list rates before tax. Token-priced models omit long-prompt / thinking extras unless noted.

Megapixel hosts (Flux 2 family on fal/Replicate): **1 MP = 1024×1024**. A 2048×2048 cover is **exactly 4 MP**.

### Specialty media hosts — three popular still models each

| Host | Model | Basis for one 2K cover | ×10 covers |
|------|-------|------------------------|------------|
| **Replicate** | FLUX 3 Image (`2k` tier) | $0.05 / image (tier price; Replicate undercuts BFL’s $0.10 list at 2K) | **$0.50** |
| **Replicate** | FLUX.2 Pro | $0.015 run + $0.015×4 MP = **$0.075** | **$0.75** |
| **Replicate** | FLUX.2 Flex | $0.06 × 4 MP = **$0.24** | **$2.40** |
| **fal.ai** | Nano Banana 2.1 (medium/minimal thinking) | ~**$0.059** measured 2K on fal (token bill; short prompt) | **~$0.59** |
| **fal.ai** | FLUX 3 Image | **$0.10** / 2K image (published resolution tier; also listed $0.048 / MP) | **$1.00** |
| **fal.ai** | FLUX.2 Pro | $0.03 first MP + $0.015×3 = **$0.075** | **$0.75** |
| **Higgsfield API** | Soul 2 | API max **1080p**, not true 2K — $0.0057 at 1080p | **$0.057** for 10×1080p (not the brief) |
| **Higgsfield API** | Ads Studio | **$0.084** / image at 2K | **$0.84** |
| **Higgsfield API** | Marketing Studio Image 2.0 / 2.5 | From ~$0.01–$0.014 at low tiers up toward **~$0.61** at 4K; treat 2K as mid-band until rechecked | **roughly $0.20–$2.00** — recheck estimate endpoint |

Higgsfield’s cheapest portrait model (Soul 2) does **not** meet a 2K cover brief on the API. For this scenario, Ads Studio is the honest 2K row; Soul 2 is only a cheap 1080p fallback.

### BYO general APIs — same 10×2K brief

| Provider | Model | Basis for one 2K cover | ×10 covers |
|----------|-------|------------------------|------------|
| **Google Gemini API** | Nano Banana 2.1 (`gemini-nano-banana-2.1`) | **$0.0504** image-output at 2K (1680 image tokens × $30 / 1M). Plus small input/thinking — live calls often add ~$0.006–$0.01 | **~$0.50–$0.60** |
| **Google Gemini API** | Nano Banana Pro | **$0.134** at 1K or 2K (image output) | **$1.34** |
| **OpenAI API** | GPT Image 2.5 (Flare or Sunburst) | Token rates $5 / $8 / $30 per 1M (text in / image in / image out). No fixed 2K card; fal/third-party sketches put a ~2K-class high image around **~$0.05–$0.08**, max quality much higher (~$0.21 at 1024² max) | **budget ~$0.50–$0.80** at high; confirm with a real `usage` receipt |
| **OpenAI API** | GPT Image 2 (high, ~2560×1440 sketch) | fal comparison table ~**$0.22** at that size | **~$2.20** if that quality/size is required |
| **xAI API** | Grok Imagine Image 2.0 | 2K · Low **$0.06**; 2K · Medium **$0.08** | **$0.60** / **$0.80** |
| **xAI API** | Grok Imagine Image (older) | Flat **$0.02** even at 2K | **$0.20** |

### What the numbers say for bandPromo

- Ten 2K covers are **pocket change** on current list prices: often **well under $1**, rarely above a few dollars unless someone picks Flex, GPT Image max, or Nano Banana Pro for every shot.
- **BYO Gemini Nano Banana 2.1** (~$0.50–$0.60) matches or beats specialty-host Flux 2 Pro (~$0.75) and undercuts fal’s Flux 3 tier ($1.00) for this brief.
- **BYO Grok Imagine Image (v1)** at $0.20 for ten 2K covers is the cheapest named general-API row in this pass — quality must still pass operator taste.
- Specialty hosts win when the operator wants **Flux / Kling / fine-tunes** without a lab signup, or already keeps a fal/Replicate balance for other tools — not because ten covers are expensive.
- Consumer chat subscriptions (ChatGPT Plus, Gemini app, Grok app) are **not** the same as API keys. Operator docs must say “paste an API key that can bill image generations,” not “you have ChatGPT so it works.”

Retries, brand-reference edits, and living-cover video are separate and can dwarf still costs (video is cents per second). This table is stills only.

---

## Recommendation recorded with the note

**Do not force one specialty media host.** Prefer a **multi-provider Settings → AI** where OpenAI, Gemini, and xAI sit beside optional fal/Replicate/Higgsfield/Runware slots. Operators reuse accounts they already pay for text; media-only hosts stay optional.

**Do not abandon Replicate because of Imagen 4 / Flux 2 branding.** Abandon those **model ids** as defaults. Recheck when wizards ship.

**Do not pick OpenArt as the install provider.** MCP/OAuth-to-agent is the wrong contract for a PHP admin panel that stores an operator key.

**Between Replicate and fal as optional media hubs:** either is fine. Prefer **fal** for media-first defaults; **Replicate** for catalogue/fine-tune breadth. Neither should be required if the operator only configures Gemini or OpenAI.

**Cost honesty for operators:** a batch of ten 2K cover drafts is usually **sub-dollar to low single-digit dollars** on current APIs. The expensive mistake is locking them into a second subscription when their existing OpenAI/Gemini/xAI key already covers the job.

**Replicate as a primary choice:** weak for bandPromo. It is still a usable optional slot, but it is a poor default next to BYO lab keys and media-first hubs (fal, Runware). Prefer not to centre docs or Settings defaults on it.

**Out of scope for the wizard spike:** Blotato-style AI video templates, viral coaches, and credit-burning remix mills. Same boundary as [BLOTATO-EVALUATION.md](BLOTATO-EVALUATION.md) when that note is present: generate drafts for *this* release’s missing pieces, then stop.

---

## Other alternatives worth naming

Beyond Replicate / fal / Higgsfield / Runware / OpenArt and the BYO labs:

| Option | What it is | For bandPromo |
|--------|------------|---------------|
| **Black Forest Labs API** | Direct Flux / Flux 3 from the maker | Optional when an operator wants Flux without a hub markup |
| **OpenRouter** | Multi-model gateway (mostly LLMs; some image routes) | Possible text+image convenience key; check current image catalogue before promising covers |
| **WaveSpeed / EachLabs / Atlas Cloud–class Flux resellers** | Often mirror BFL list prices | Only if an operator already uses them; do not make them first-class without a recheck |
| **BytePlus / Alibaba / Kling direct** | Lab video APIs | Living-cover experiments; heavier onboarding than a hub |
| **Together AI / Fireworks / DeepInfra** | Strong on open LLMs; some image/video | Better as text backends than as the cover studio |
| **RunPod / Modal** | Rent GPUs or deploy your own workers | Overkill for wizard drafts; relevant only if someone self-hosts open weights |
| **Consumer apps** (ChatGPT, Gemini app, Grok app, Midjourney, etc.) | Human-in-the-loop studios | Operators can make art there and **upload** into Files — no API required. Keep that path obvious in UI copy |

The durable product split: **BYO general API** for most installs, **optional media hub** for Flux/Kling shoppers, **manual upload** always works.

---

## Kickbacks and “send them clients”

Checked 3 November 2026. Treat affiliate claims as stale until the live programme page is opened again.

| Provider | Public cash kickback for sending customers? | Notes |
|----------|-----------------------------------------------|-------|
| **Higgsfield (website / subscriptions)** | **Yes — affiliate.** Up to **25%** of qualifying plan price for up to **12 months**; cash, not credits; referrals can get up to **50% off for three hours**. Paid ads and coupon sites forbidden. | Pays on **consumer subscriptions**, not on documenting a path to the separate **API** balance. Useful only if bandPromo docs/links send people to higgsfield.ai plans. |
| **Higgsfield API** | No public reseller/affiliate cut found. Terms forbid resale / service-bureau use of the API without prior written consent. | Do not build a credits shop on top of their API without a signed exception. |
| **fal.ai** | **No verified public affiliate page** (`/affiliate`, `/referral`, `/partners` reported 404). Community registries list unverified terms — ignore those. Startup/Builder **grants** and VC referral codes exist; those are credits for startups, not bandPromo revenue. | Not a kickback engine. |
| **Runware** | **No cash referral programme** found. Creator Program pays platform credits (+ discretionary cash for content), not % of referred spend. | Creator money is for making content *about* Runware, not for routing API customers. |
| **Replicate** | Independent listings say any old affiliate programme **ended** / is unverified. Do not budget on it. | Matches “worst commercial fit” as well as product fit. |
| **OpenAI** | **No public affiliate** for ChatGPT/API. Partner Network is enterprise co-sell, not a blog commission. API key trading banned; intermediaries need prior approval. | Cannot skim OpenAI usage by sitting in the middle without a deal. |
| **Google Gemini API** | No public cash affiliate for API spend. Terms aim at forbidding clients that are “substantially the same as the APIs” for third parties. | Same: product-on-top yes; raw resale no. |
| **xAI** | Partner Network / marketplace exist; public % kickback for API spend not the self-serve story. End users expected as part of a **bundled service**, not a raw key shop. | Bundled product ≠ selling Grok tokens. |
| **RunPod** | **Yes — referral/affiliate** on GPU/serverless spend (credits, then cash at affiliate tier). | Wrong product surface for bandPromo wizards (infra, not cover API). |

**Practical reading:** the only clear, self-serve cash affiliate among the media names above is **Higgsfield’s website subscription programme**. It does not fund a bandPromo-shaped “we sell you Flux credits” business, and it does not apply cleanly to API metered usage.

---

## Selling bandPromo credits on top of these APIs — viable income?

**Short answer: no, not as a serious or strategy-aligned income line.** Recorded as a reject for the default product path.

### Why the economics are thin

- Ten 2K covers cost **well under a dollar** on BYO Gemini or cheap hub rows. Markup on that is noise, not a business.
- Operators who already have OpenAI/Gemini/xAI keys have **no reason** to buy bandPromo credits for the same pixels.
- Specialty-hub margins are competed down by Replicate/fal/Runware/BFL price wars; a thin AGPL music-site project will not out-negotiate them.

### Why the contracts push back

- Higgsfield API: explicit **no resale / no service-bureau** without written consent.
- OpenAI / Gemini / xAI / several inference hosts: **raw API resale** forbidden or approval-gated; building your own product that *calls* the API for your users is the allowed shape — selling metered “OpenAI tokens” under a bandPromo skin is the forbidden shape.
- Sitting in the middle means bandPromo holds the master key, pays the upstream bill, invoices operators, handles refunds, abuse, and chargebacks.

### Why it fails bandPromo’s own roadmap test

[ROADMAP.md](ROADMAP.md) already asks whether a feature helps the operator from their install or turns the project into a **centralised intermediary / payment processor**. Prefer external payment links and operator-owned accounts; avoid holding funds unless the project intentionally accepts that legal burden.

A credits top-layer would mean:

- central billing and KYC/tax exposure
- abuse when one leaked bandPromo key drains the shared upstream balance
- operators dependent on bandPromo uptime for generation even when their own OpenAI key works
- AGPL self-hosted software drifting into a hosted SaaS wallet

That is the opposite of the BYO multi-key design recorded above.

### What *can* be income without becoming a wallet

Aligned with existing direction (paid modules / themes / services as a separate decision):

- Optional **install-locked add-ons** (themes, modules) — already sketched as a future entitlement boundary, with core staying free to run.
- **Services** (release prep, PCF handoff, branding) sold person-to-person — already in the marketing docs.
- Optional **documented affiliate links** only where a real programme exists (today: Higgsfield website affiliate), disclosed, never required, never the generation backend.
- Never: prepaid “bandPromo AI credits” that proxy OpenAI/fal/Higgsfield unless a future version deliberately accepts payment-processor scope and signed reseller terms.

---

## Suggested first defaults when the work starts (recheck, do not hard-code from here)

These are starting guesses for 3 November 2026, not locks:

| Job | Sensible class of model | Why |
|-----|-------------------------|-----|
| Still cover / poster brief | Prefer the operator’s configured BYO default (Nano Banana 2.1, GPT Image, or Grok Imagine); else Flux 3 / Flux 2 Pro on an optional media host | Reuse an existing bill first; specialty host only when needed |
| Edit existing brand still | Nano Banana edit, GPT Image edit, or Flux Kontext-class edit | Brand refs already in the Visual pool |
| Living-cover seed clip | Kling 3 / image-to-video, or MiniMax H3 / Seedance class on a media host (or Grok video if the operator already has xAI) | Short silent loop; keep duration low; download into Visual as `ai-generated` |
| Text blurbs / alt text | Same BYO OpenAI / Gemini / Grok text model as the install already uses | One key for words and pictures when possible |

Always download the finished file into the install’s media tree. Do not hot-link ephemeral provider URLs in the player (Higgsfield’s ~7-day retention is the clearest warning; others expire too).

---

## Facts to recheck before locking Settings → AI

| Check | Why it matters |
|-------|----------------|
| Whether Imagen endpoints still exist on the chosen host | Avoid documenting a shut-down Google model as a default |
| Whether Flux 3 (or successor) is billed and stable on Replicate and fal | Optional media-host default |
| Nano Banana 2.1 2K image-output price on Gemini API vs via fal | BYO vs hub cost |
| GPT Image 2.5 real `usage` for a 2048² high cover | OpenAI has no fixed 2K card |
| Grok Imagine Image 2.0 2K low/medium rates | Cheap BYO still path |
| OpenArt still MCP-only vs any new REST key programme | Only reconsider OpenArt if a real install-held key appears |
| Higgsfield Soul 2 still capped at 1080p on the API | Do not advertise it as 2K covers |
| fal / Replicate output URL lifetime | Download-before-expiry |
| Runware current still prices at 2K | Optional cheap hub |
| That ChatGPT / Gemini / Grok **app** plans still do not equal API keys | Operator messaging |
| Higgsfield affiliate page still cash / subscription-scoped (not API resale) | Only honest kickback path found |
| fal / Replicate / Runware still without a verified cash affiliate for API spend | Do not promise referral income |
| Upstream terms still ban raw API resale / service-bureau use | Credits top-layer stays rejected |
| Commercial / rights terms for AI outputs the operator will publish | [OPERATOR-RESPONSIBILITY.md](OPERATOR-RESPONSIBILITY.md) and disclosure fields |
| Repeat the **10×2K cover** table against live rate cards | Numbers in this note will drift |

---

## Sources read on 3 November 2026

- https://replicate.com/collections/flux — Flux 2 marketed as “latest generation”; Flux 3 also listed among recommended models
- https://replicate.com/collections/official — live official catalogue (Kling 4, Seedance, GPT Image 2.5, MiniMax H3, …)
- https://replicate.com/blog/run-flux-2-on-replicate — Flux 2 Pro / Flex megapixel rates
- FLUX 3 Image API price comparisons (Replicate $0.05 at 2K; BFL/fal often $0.10 at 2K) — recheck vendor pages before locking
- https://fal.ai/docs/documentation/model-apis/models — current fal model picks
- https://fal.ai/pricing and https://fal.ai/models/fal-ai/flux-2-pro/llms.txt — Flux 2 Pro megapixel billing
- https://fal.ai/learn/devs/nano-banana-2-1-vs-nano-banana-2 — fal-measured Nano Banana 2.1 2K cost
- https://ai.google.dev/gemini-api/docs/pricing — Nano Banana 2.1 / Pro image-output equivalents
- https://ai.google.dev/gemini-api/docs/image-generation — Nano Banana replaces Imagen
- https://developers.openai.com/api/docs/pricing — GPT Image 2 / 2.5 token rates
- https://docs.x.ai/developers/pricing — Grok Imagine Image / 2.0 per-image rates
- https://higgsfield.ai/blog/higgsfield-api — API vs website product split
- https://docs.higgsfield.ai/docs/concepts/billing-and-retention — estimate, refunds, 7-day retention, 1-year balance expiry
- https://open.higgsfield.ai/models/higgsfield-ai/soul/v2/standard/playground — Soul 2 720p/1080p only; Ads Studio 2K
- https://openart.ai/mcp/ and https://openart.ai/llms-full.txt — MCP/CLI, no API-key REST story
- https://runware.ai/docs/models-api/authentication — Bearer REST for image/video
- https://higgsfield.ai/affiliate and https://higgsfield.ai/blog/higgsfield-affiliate-program-2026 — subscription affiliate (up to 25% / 12 months)
- https://open.higgsfield.ai/terms-of-service — API no-resale / no service-bureau clause
- https://fal.ai/startup-program — grants/referral codes, not a public cash affiliate
- https://runware.ai/creator-program — creator credits, not API referral commission
- https://www.runpod.io/referral-and-affiliate-program — GPU referral (wrong surface for wizards)
- OpenAI Partner Network / public commentary: no self-serve API affiliate; API key resale restricted
- Cross-checks: aivideoapis.com provider profiles (fal/Runware affiliate status); independent “affiliate ended” notes for Replicate

bandPromo side: [ROADMAP.md](ROADMAP.md) Content AI wizards and v2+ payment/intermediary boundaries, [TODO.md](TODO.md) Settings → AI, [PLATFORM-MODEL.md](PLATFORM-MODEL.md) `origin: ai-generated`, [MEDIA-HANDLING.md](MEDIA-HANDLING.md), [OPERATOR-RESPONSIBILITY.md](OPERATOR-RESPONSIBILITY.md).
