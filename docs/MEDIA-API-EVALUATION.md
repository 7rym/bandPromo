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
| **Direct lab APIs** (Google Gemini / Nano Banana, BFL, OpenAI Images, Kling, …) | Each its own key, schema, and review. | Newest models appear here first. | Better as **optional advanced endpoints** once the wizard is provider-agnostic — not as the first and only integration, or every operator faces N onboarding paths. |

Creator-facing websites that look like OpenArt or the Higgsfield **app** (templates, characters, credit packs, MCP for Claude) solve a different problem: helping a person make media in a studio. bandPromo’s problem is: an install calls a key, gets bytes back, operator confirms into the Visual pool.

---

## Recommendation recorded with the note

**Do not abandon Replicate because of Imagen 4 / Flux 2 branding.** Abandon those **model ids** as defaults. Recheck the catalogue when wizards ship and pin current defaults (likely a Nano Banana-class or Flux 3-class still, and a current image-to-video model for living-cover experiments).

**Do not pick OpenArt as the install provider.** MCP/OAuth-to-agent is the wrong contract for a PHP admin panel that stores an operator key.

**Between Replicate and fal for a first media backend:** either is acceptable. Prefer **fal** if the first spike cares about media latency, clear per-output pricing, and defaults that already read as current. Prefer **Replicate** if the team wants maximum catalogue breadth, fine-tunes, or an already-familiar token. **Higgsfield** and **Runware** are optional second providers, not blockers.

**Design rule that matters more than the brand name:** Settings → AI stores `provider`, `api_key`, `default_image_model`, `default_video_model`, limits, and disclosure text. bandPromo ships with helpers off. The operator pastes their own key. Swapping Replicate for fal later is a settings and adapter change, not a product rewrite. That matches the already-locked “operator-configured external model/API settings” line in the roadmap.

**Out of scope for the wizard spike:** Blotato-style AI video templates, viral coaches, and credit-burning remix mills. Same boundary as [BLOTATO-EVALUATION.md](BLOTATO-EVALUATION.md) when that note is present: generate drafts for *this* release’s missing pieces, then stop.

---

## Suggested first defaults when the work starts (recheck, do not hard-code from here)

These are starting guesses for 3 November 2026, not locks:

| Job | Sensible class of model | Why |
|-----|-------------------------|-----|
| Still cover / poster brief | Google Nano Banana 2.1 or Flux 3 / Flux 2 Pro | Current still quality; brand/text work often better on Nano Banana Pro or GPT Image when typography matters |
| Edit existing brand still | Nano Banana edit or Flux Kontext-class edit | Brand refs already in the Visual pool |
| Living-cover seed clip | Kling 3 / image-to-video, or MiniMax H3 / Seedance class | Short silent loop; keep duration low; download into Visual as `ai-generated` |
| Text blurbs / alt text | Separate text model or the same host’s LLM if present | Not a media API decision |

Always download the finished file into the install’s media tree. Do not hot-link ephemeral provider URLs in the player (Higgsfield’s ~7-day retention is the clearest warning; others expire too).

---

## Facts to recheck before locking Settings → AI

| Check | Why it matters |
|-------|----------------|
| Whether Imagen endpoints still exist on the chosen host | Avoid documenting a shut-down Google model as a default |
| Whether Flux 3 (or successor) is billed and stable on Replicate and fal | Default still model |
| Nano Banana model ids and commercial terms on Google vs via fal/Replicate | Brand/consistency stills |
| OpenArt still MCP-only vs any new REST key programme | Only reconsider OpenArt if a real install-held key appears |
| Higgsfield API vs website plan confusion and balance expiry | Operator messaging |
| fal Model API key flow, queue polling, and output URL lifetime | Adapter design |
| Runware current video model list and async webhook behaviour | Optional cheap path |
| Price of one 1024² still and one 5s 720p image-to-video on the shortlist | Operator cost honesty |
| Commercial / rights terms for AI outputs the operator will publish | [OPERATOR-RESPONSIBILITY.md](OPERATOR-RESPONSIBILITY.md) and disclosure fields |

---

## Sources read on 3 November 2026

- https://replicate.com/collections/flux — Flux 2 marketed as “latest generation”; Flux 3 also listed among recommended models
- https://replicate.com/collections/official — live official catalogue (Kling 4, Seedance, GPT Image 2.5, MiniMax H3, …)
- https://fal.ai/docs/documentation/model-apis/models — current fal model picks
- https://fal.ai/pricing — per-output media pricing
- https://ai.google.dev/gemini-api/docs/image-generation — Nano Banana replaces Imagen
- https://ai.google.dev/gemini-api/docs/imagen — Imagen shut down / migrate
- https://higgsfield.ai/blog/higgsfield-api — API vs website product split
- https://docs.higgsfield.ai/docs/concepts/billing-and-retention — estimate, refunds, 7-day retention, 1-year balance expiry
- https://openart.ai/mcp/ and https://openart.ai/llms-full.txt — MCP/CLI, no API-key REST story
- https://runware.ai/docs/models-api/authentication — Bearer REST for image/video

bandPromo side: [ROADMAP.md](ROADMAP.md) Content AI wizards, [TODO.md](TODO.md) Settings → AI, [PLATFORM-MODEL.md](PLATFORM-MODEL.md) `origin: ai-generated`, [MEDIA-HANDLING.md](MEDIA-HANDLING.md).
