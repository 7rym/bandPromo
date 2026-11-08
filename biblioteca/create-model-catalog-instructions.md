# Create model catalogue — curation instructions

Use these instructions when refreshing the nine fal.ai model lists in
[`create-model-catalog.json`](create-model-catalog.json). Output must remain
valid JSON in British English. Create → Preferences reads that file for its
Cheap / Good / Premium choices (LLM, Image, Video).

**Do not hardcode model lists into admin HTML or page JS.** The Preferences UI
always loads whatever the install currently serves from this JSON (via the
Create integrations status API). Model vendors change quickly; expect frequent
catalogue refreshes. A dedicated catalogue-update function is planned later
(see [ROADMAP.md](../docs/ROADMAP.md) → Create Preferences model catalogue);
until then, replace this JSON in-repo and publish.

---

## File shape (slim product catalogue)

Keep only what Preferences needs. Do **not** dump lifecycle audits, validation
trees, criteria prose, summary/status blocks, or null “Unfilled” placeholder rows
into this file. Curation working notes stay in chat or a scratch pad — not in the
shipped JSON.

```json
{
  "checked_on": "YYYY-MM-DD",
  "currency": "USD",
  "defaults": {
    "llm_fal_endpoint": "openrouter/router",
    "llm_estimate_basis": "USD for 1M input tokens + 1M output tokens (no cache/batch)",
    "image_estimate_basis": "USD per ~1K/1MP image with one reference",
    "video_estimate_basis": "USD per generated second at lowest documented resolution with first+last frame"
  },
  "lists": {
    "llm": { "cheapest": [], "workhorse": [], "premium": [] },
    "image": { "cheapest": [], "workhorse": [], "premium": [] },
    "video": { "cheapest": [], "workhorse": [], "premium": [] }
  }
}
```

Instructions live in this sibling `.md` file (`instructions_file` is not stored
in the JSON).

### Uniform row fields

| Kind | Required fields | Price fields |
|------|-----------------|--------------|
| LLM | `supplier`, `model`, `recommended_for`, `source` | `input_price`, `output_price`, `estimate_usd` (= in+out for 1M+1M) |
| Image | `supplier`, `model`, `endpoint`, `recommended_for`, `source` | `estimate_usd` (one still + one ref) |
| Video | `supplier`, `model`, `endpoint`, `first_frame_parameter`, `last_frame_parameter`, `recommended_for`, `source` | `estimate_usd` (per second), optional `estimate_resolution` |

Every Preferences-visible row **must** have a positive `estimate_usd` and a short
`recommended_for` (About line). After a price refresh, also set:

- `price_verified`: `true` only when the figure came from a live source below
- `price_source`: short provenance (e.g. `openrouter:…`, `fal_pricing:…`,
  `fal_llms_txt:…`, `fal_historical_api_price:…`)

**Do not invent prices.** Prove them from:

1. OpenRouter `GET /api/v1/models` for LLM `input_price` / `output_price` /
   `estimate_usd` (1M+1M)
2. fal `GET /v1/models/pricing?endpoint_id=…` when the unit maps to our basis
   (image / megapixel / second)
3. fal model `https://fal.ai/models/<endpoint>/llms.txt` when the unit is opaque
   or the basis needs one reference / lowest resolution without audio
4. fal `POST /v1/models/pricing/estimate` with `historical_api_price` only for
   token-priced models whose list unit is a useless placeholder (e.g. `$1/units`)

Optional `note` only when it adds a product capability line the About text does
not already cover — never audit chatter, retirement prose, or validation commentary.

Drop promo schedules / nested `price` objects / `replaces` / `api_availability`
clutter unless needed to derive the estimate sentence while curating (then fold
into `estimate_usd` and discard the clutter).

### Estimate bases

- **LLM `estimate_usd`:** cost of **1M input + 1M output** tokens on OpenRouter
  (or the fal OpenRouter router), excluding cache and batch discounts.
  `input_price` + `output_price` should equal `estimate_usd`.
- **Image `estimate_usd`:** roughly **one ~1K / 1MP still with one reference**
  (include reference-processing charges when the docs separate them).
- **Video `estimate_usd`:** **USD per generated second** at the **lowest
  documented resolution** that still supports **first + last frame** in one
  request; disable optional paid audio when comparing. Put the resolution in
  `estimate_resolution` when known (e.g. `480p`).

---

## Selection criteria

Create nine lists: **LLM, Image and Video**, each divided into **Cheapest,
Workhorse and Premium**.

1. **Seven models per list**, with seven different suppliers within each
   individual list. Suppliers may appear in other lists.
2. **Each model may appear only once** across all nine lists. Different
   reasoning settings, resolutions or inference configurations do not constitute
   separate models.
3. **Avoid similar variants from the same supplier within a list.** Select the
   latest relevant model in the appropriate product tier. Distinct budget,
   workhorse and premium products from one supplier may appear in separate lists.
4. **Cheapest:** select and rank solely by price after applying all eligibility
   restrictions. Do not factor quality into this ranking.
5. **Workhorse:** choose practical models for regular production, balancing
   capability, reliability, speed and operating cost.
6. **Premium:** choose the supplier’s premium model tier. A more expensive
   hosting or throughput option alone does not establish a distinct premium model.
7. **Image models must accept reference images.**
8. **Video models must support both a first frame and a last frame in the same
   request.** General reference-image support alone is insufficient. Record the
   request parameter names in `first_frame_parameter` / `last_frame_parameter`.
9. **Exclude models approaching retirement.** Treat an announced retirement,
   removal or deprecation within 90 days as grounds for replacement.
10. **Verify current API availability.** Distinguish callable API products from
    announcements, playground-only access and forthcoming endpoints. Check
    individual LLM availability through fal’s OpenRouter router
    (`defaults.llm_fal_endpoint`).
11. **Use current primary sources**, preferably fal’s endpoint documentation and
    OpenRouter / supplier pricing pages. Put a supporting URL in `source` and set
    `checked_on` on the catalogue root.
12. **Do not invent entries to reach seven.** If a list cannot be completed under
    all restrictions, leave that slot out of the JSON (no null stubs) and note
    the gap in the curation session — not inside the catalogue file.
13. **Return valid JSON in British English.**

---

## bandPromo mapping

| Catalogue tier | Preferences toggle |
|----------------|--------------------|
| `cheapest`     | Cheap              |
| `workhorse`    | Good               |
| `premium`      | Premium            |

LLM rows are offered through fal’s OpenRouter router (`openrouter/router`),
except OpenAI-supplier rows which stay on the native OpenAI API for Start
connection proofs. Image and video rows use the fal `endpoint` id.

Preferences shows **Price:** from `estimate_usd` (via the PHP price-hint helpers)
and **About:** from `recommended_for`.
