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

Create nine lists of models available through fal.ai: **LLM, Image and Video**, each divided into **Cheapest, Workhorse and Premium**.

Apply these criteria:

1. **Seven models per list**, with seven different suppliers within each individual list. Suppliers may appear in other lists.
2. **Each model may appear only once** across all nine lists. Different reasoning settings, resolutions or inference configurations do not constitute separate models.
3. **Avoid similar variants from the same supplier within a list.** Select the latest relevant model in the appropriate product tier. Distinct budget, workhorse and premium products from one supplier may appear in separate lists.
4. **Cheapest:** select and rank solely by price after applying all eligibility restrictions. Do not factor quality into this ranking.
5. **Workhorse:** choose practical models for regular production, balancing capability, reliability, speed and operating cost.
6. **Premium:** choose the supplier’s premium model tier. A more expensive hosting or throughput option alone does not establish a distinct premium model.
7. **Image models must accept reference images.** State whether references support general editing, subject identity, composition or style only.
8. **Video models must support both a first frame and a last frame in the same request.** General reference-image support alone is insufficient.
9. **Exclude models approaching retirement.** Treat an announced retirement, removal or deprecation within 90 days as grounds for replacement. The 90-day threshold is an explicit working assumption.
10. **Verify current API availability.** Distinguish callable API products from announcements, playground-only access and forthcoming endpoints. Check individual LLM availability through fal’s router.
11. **Compare prices transparently:**
    - LLM: use the cost of one million input tokens plus one million output tokens, excluding caching and batch discounts.
    - Image: use approximately one-megapixel or 1K output with one reference image, including reference-processing charges.
    - Video: use the lowest-priced configuration supporting both endpoint frames; compare cost per generated second and disable optional paid audio.
12. **Include promotional prices**, but provide expiry dates and standard rates. Identify estimates, planned tariffs and incomparable billing methods.
13. **Use current primary sources**, preferably fal’s endpoint documentation and official supplier information. Provide supporting links and the verification date.
14. **State uncertainty explicitly.** Finding no retirement notice does not guarantee future availability. Do not describe a shortlist as the absolute cheapest without checking the full eligible catalogue.
15. **Do not invent entries to reach seven.** If a list cannot be completed under all restrictions, identify the unfilled slot and explain which requirement prevents completion.
16. **Return the results as valid JSON in British English**, including model names, suppliers, endpoint identifiers, capabilities, prices and units, lifecycle findings, replacements, sources and outstanding limitations.

---

## bandPromo mapping

| Catalogue tier | Preferences toggle |
|----------------|--------------------|
| `cheapest`     | Cheap              |
| `workhorse`    | Good               |
| `premium`      | Premium            |

LLM rows are offered through fal’s OpenRouter router (`openrouter/router`). Image and video rows use the fal `endpoint` id. Unfilled slots (`model` / `endpoint` null) are omitted from the UI.
