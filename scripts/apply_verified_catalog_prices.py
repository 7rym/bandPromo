#!/usr/bin/env python3
"""
Rewrite create-model-catalog.json estimate fields from live fal + OpenRouter proof.

Sources of truth (in order):
1. OpenRouter /api/v1/models for LLM input/output rates (1M+1M estimate).
2. fal GET /v1/models/pricing when the unit maps cleanly to our basis.
3. fal model /llms.txt documented dollar examples when pricing unit is opaque
   (units / tokens / videos billed oddly).

Adds per-row:
  price_verified: true|false
  price_source: short provenance string
"""
from __future__ import annotations

import json
import os
import re
import urllib.error
import urllib.parse
import urllib.request
from datetime import date
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CATALOG = ROOT / "biblioteca" / "create-model-catalog.json"
REPORT = ROOT / "log" / "create-catalog-price-apply.json"


def load_fal_key() -> str:
    env_path = ROOT / ".env"
    if env_path.is_file():
        for raw in env_path.read_text(encoding="utf-8", errors="replace").splitlines():
            if raw.startswith("FAL_KEY="):
                return raw.split("=", 1)[1].strip().strip('"').strip("'")
    return os.environ.get("FAL_KEY", "").strip()


def http_json(method: str, url: str, headers: dict | None = None, body: dict | None = None, timeout: int = 45):
    data = None
    hdrs = dict(headers or {})
    if body is not None:
        data = json.dumps(body).encode("utf-8")
        hdrs.setdefault("Content-Type", "application/json")
    req = urllib.request.Request(url, data=data, method=method)
    for k, v in hdrs.items():
        req.add_header(k, v)
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            raw = resp.read().decode("utf-8", errors="replace")
            return resp.status, json.loads(raw) if raw else {}
    except urllib.error.HTTPError as e:
        raw = e.read().decode("utf-8", errors="replace")
        try:
            decoded = json.loads(raw) if raw else {}
        except json.JSONDecodeError:
            decoded = {"raw": raw[:800]}
        return e.code, decoded
    except Exception as e:  # noqa: BLE001
        return 0, {"error": str(e)}


def http_text(url: str, timeout: int = 30) -> str:
    req = urllib.request.Request(url, headers={"User-Agent": "bandpromo-catalog-verify"})
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        return resp.read().decode("utf-8", errors="replace")


def fal_pricing(api_key: str, endpoint_ids: list[str]) -> dict[str, dict]:
    out: dict[str, dict] = {}
    for i in range(0, len(endpoint_ids), 20):
        batch = endpoint_ids[i : i + 20]
        qs = "&".join("endpoint_id=" + urllib.parse.quote(eid, safe="") for eid in batch)
        status, data = http_json(
            "GET",
            f"https://api.fal.ai/v1/models/pricing?{qs}",
            {"Authorization": f"Key {api_key}", "Accept": "application/json"},
        )
        found = {}
        if status == 200 and isinstance(data, dict):
            found = {p.get("endpoint_id"): p for p in (data.get("prices") or []) if isinstance(p, dict)}
        for eid in batch:
            if eid in found:
                row = found[eid]
                out[eid] = {
                    "ok": True,
                    "unit_price": float(row.get("unit_price") or 0),
                    "unit": str(row.get("unit") or ""),
                    "currency": str(row.get("currency") or "USD"),
                }
            else:
                out[eid] = {"ok": False, "status": status, "error": data}
    return out


def openrouter_by_id() -> dict[str, dict]:
    status, data = http_json("GET", "https://openrouter.ai/api/v1/models", {"Accept": "application/json"})
    if status != 200 or not isinstance(data, dict):
        raise RuntimeError(f"OpenRouter models failed: {status} {data}")
    out = {}
    for row in data.get("data") or []:
        if not isinstance(row, dict):
            continue
        mid = str(row.get("id") or "")
        pricing = row.get("pricing") if isinstance(row.get("pricing"), dict) else {}
        try:
            prompt = float(pricing.get("prompt") or 0)
            completion = float(pricing.get("completion") or 0)
        except (TypeError, ValueError):
            prompt = completion = 0.0
        out[mid] = {
            "input_per_mtok": round(prompt * 1_000_000, 6),
            "output_per_mtok": round(completion * 1_000_000, 6),
            "estimate_usd": round((prompt + completion) * 1_000_000, 6),
            "name": row.get("name"),
        }
    return out


def slugify_token(value: str) -> str:
    value = value.strip().lower()
    value = re.sub(r"[^a-z0-9.]+", "-", value)
    return value.strip("-.")


SUPPLIER_SLUG = {
    "z.ai": "z-ai",
    "inclusionai": "inclusionai",
    "liquid ai": "liquid",
    "openai": "openai",
    "anthropic": "anthropic",
    "alibaba": "qwen",
    "google": "google",
    "deepseek": "deepseek",
    "minimax": "minimax",
    "mistral ai": "mistralai",
    "moonshot ai": "moonshotai",
    "xai": "x-ai",
}

# Explicit OpenRouter id overrides when slugify would miss (Liquid hyphenation, etc.).
LLM_OPENROUTER_ID = {
    ("Liquid AI", "LFM3-1.7B"): "liquid/lfm-3-1.7b",
    ("Mistral AI", "Mistral Medium 3.5"): "mistralai/mistral-medium-3-5",
}


def resolve_openrouter_id(supplier: str, model: str, source: str, or_models: dict[str, dict]) -> str | None:
    key = (supplier, model)
    if key in LLM_OPENROUTER_ID and LLM_OPENROUTER_ID[key] in or_models:
        return LLM_OPENROUTER_ID[key]
    m = re.search(r"openrouter\.ai/([^?\s\#]+)", source or "", re.I)
    if m:
        path = m.group(1).strip("/").lower()
        if "/" in path and path in or_models:
            return path
    vendor = SUPPLIER_SLUG.get(supplier.strip().lower(), slugify_token(supplier))
    exact = f"{vendor}/{slugify_token(model)}"
    if exact in or_models:
        return exact
    # Mistral Medium 3.5 fuzzy
    if supplier == "Mistral AI" and "Medium" in model:
        for mid in ("mistralai/mistral-medium-3.1", "mistralai/mistral-medium-3", "mistralai/mistral-medium-3-5"):
            if mid in or_models:
                return mid
    return None if exact not in or_models else exact


def parse_llms_txt_estimate(kind: str, endpoint: str, text: str) -> dict | None:
    """Extract a catalogue-basis estimate from fal llms.txt prose."""
    # Normalize whitespace for regex
    flat = re.sub(r"\s+", " ", text)

    if kind == "image":
        # Luma: "1 source image costs $0.045"
        m = re.search(r"1 source image costs\s*\*\*\$([0-9.]+)\*\*", flat, re.I)
        if m:
            return {"estimate_usd": float(m.group(1)), "note": "llms.txt 1 source image example"}

        # "Each edit costs $0.042, plus $0.003 per reference" — compute 1 ref
        m = re.search(
            r"Each edit costs\s*\*\*\$([0-9.]+)\*\*.*?\$([0-9.]+)\s*per reference",
            flat,
            re.I,
        )
        if m:
            return {
                "estimate_usd": float(m.group(1)) + float(m.group(2)),
                "note": "llms.txt edit + one reference",
            }

        # Qwen: "$0.04 per generated image at 1K"
        m = re.search(r"\$([0-9.]+)\*\*\s*per generated image at 1K", flat, re.I)
        if m:
            return {"estimate_usd": float(m.group(1)), "note": "llms.txt 1K image"}

        # Grok v2: "$0.04 (low) ... for 1K" + "$0.01 per input image" → low+ref
        m = re.search(
            r"\$([0-9.]+)\*\*\s*\(low\).*?per image for 1K.*?\$([0-9.]+)\*\*\s*per input image",
            flat,
            re.I,
        )
        if m:
            return {
                "estimate_usd": float(m.group(1)) + float(m.group(2)),
                "note": "llms.txt 1K low + one input image",
            }

        # Grok quality: "$0.05 per output image for 1K" + "$0.01 per input"
        m = re.search(
            r"\$([0-9.]+)\s*per output image\*\*.*?for 1K.*?\$([0-9.]+)\s*per input image",
            flat,
            re.I,
        )
        if not m:
            m = re.search(
                r"\$([0-9.]+)\*\*\s*per output image.*?for 1K.*?\$([0-9.]+)\*\*\s*per input image",
                flat,
                re.I,
            )
        if m:
            return {
                "estimate_usd": float(m.group(1)) + float(m.group(2)),
                "note": "llms.txt 1K output + one input",
            }

        # Recraft: "$0.035 when a style_id is provided" + "$0.005 extra" if generating style
        # Our basis: one still + one reference → prefer style_id path $0.035 (first style setup extra once)
        m = re.search(r"Each image costs\s*\*\*\$([0-9.]+)\*\*\s*when a\s*\*\*style_id\*\*", flat, re.I)
        if m:
            return {"estimate_usd": float(m.group(1)), "note": "llms.txt with style_id"}

        # Krea: "$0.025/MP" counting output + each reference → ~2 MP for 1K+1ref
        m = re.search(r"\$([0-9.]+)\*\*/MP", flat, re.I)
        if m and "krea" in endpoint:
            per_mp = float(m.group(1))
            return {
                "estimate_usd": round(per_mp * 2.0, 6),
                "note": "llms.txt $ /MP × (1K output + 1 ref ≈ 2 MP)",
            }

        # Nano Banana / GPT Image token models — look for explicit 1K examples later in page
        m = re.search(
            r"(?:1K|1024).*?(?:costs?|price|about|approximately|=)\s*\$([0-9.]+)",
            flat,
            re.I,
        )
        if m and ("nano-banana" in endpoint or "gpt-image" in endpoint):
            return {"estimate_usd": float(m.group(1)), "note": "llms.txt 1K example (token model)"}

        # Tables like "| 1024x1024 | $0.xxx |"
        m = re.search(r"1024\s*[x×]\s*1024[^\n|$]*?[\$]([0-9.]+)", text, re.I)
        if m:
            return {"estimate_usd": float(m.group(1)), "note": "llms.txt 1024x1024 table"}

    if kind == "video":
        # Prefer explicit lowest-res $/second without audio when stated
        # PixVerse pattern: "360p: $0.025 / $0.035"
        m = re.search(
            r"360p:\s*\$([0-9.]+)\s*/\s*\$([0-9.]+)",
            flat,
            re.I,
        )
        if m:
            return {
                "estimate_usd": float(m.group(1)),
                "estimate_resolution": "360p",
                "note": "llms.txt 360p without audio",
            }

        # Seedance mini: "$0.0721/second for 480p"
        m = re.search(r"\$([0-9.]+)\*\*/?second for\s*\*\*([0-9]+p)\*\*", flat, re.I)
        if not m:
            m = re.search(r"\$([0-9.]+)/second for\s*\*\*([0-9]+p)\*\*", flat, re.I)
        if m:
            return {
                "estimate_usd": float(m.group(1)),
                "estimate_resolution": m.group(2),
                "note": "llms.txt documented $/second",
            }

        # "roughly $0.2205 per second ... 480p"
        m = re.search(
            r"(?:roughly\s+)?\$([0-9.]+)\s+per second.*?([0-9]+p)",
            flat,
            re.I,
        )
        if m and "seedance-2.5" in endpoint:
            # First number is 480p in the seedance 2.5 sentence order
            m2 = re.search(
                r"For 480p.*?\$([0-9.]+)\s+per second",
                flat,
                re.I,
            )
            if m2:
                return {
                    "estimate_usd": float(m2.group(1)),
                    "estimate_resolution": "480p",
                    "note": "llms.txt 480p $/second",
                }

        # LTX: "$0.09 at 720p"
        m = re.search(r"\$([0-9.]+)\s+at\s+720p", flat, re.I)
        if m and "ltx" in endpoint:
            return {
                "estimate_usd": float(m.group(1)),
                "estimate_resolution": "720p",
                "note": "llms.txt 720p $/second",
            }

        # LTX pro: "$0.12 per second for 720p"
        m = re.search(r"\$([0-9.]+)\*\*\s*per second for 720p", flat, re.I)
        if not m:
            m = re.search(r"\$([0-9.]+)\s+per second for 720p", flat, re.I)
        if m and "ltx" in endpoint:
            return {
                "estimate_usd": float(m.group(1)),
                "estimate_resolution": "720p",
                "note": "llms.txt 720p $/second",
            }

        # Vidu: "$0.035 per video second for 360p and 540"
        m = re.search(
            r"\$([0-9.]+)\*\*\s*per video second for\s*([0-9]+p)",
            flat,
            re.I,
        )
        if not m:
            m = re.search(
                r"\$([0-9.]+)\s+per video second for\s*([0-9]+p)",
                flat,
                re.I,
            )
        if m:
            return {
                "estimate_usd": float(m.group(1)),
                "estimate_resolution": m.group(2),
                "note": "llms.txt per video second",
            }

        # FLUX max promo: "$0.0425 per second at 720p"
        m = re.search(r"\$([0-9.]+)\*\*\s*per second at\s*\*\*([0-9]+p)\*\*", flat, re.I)
        if not m:
            m = re.search(r"\$([0-9.]+)\s+per second at\s*\*\*([0-9]+p)\*\*", flat, re.I)
        if m:
            return {
                "estimate_usd": float(m.group(1)),
                "estimate_resolution": m.group(2),
                "note": "llms.txt $/second (may include promo)",
            }

        # Seedance fast: "720p ... $0.2419/second" — our basis wants lowest res; if only 720p listed use it
        m = re.search(r"\$([0-9.]+)/second", flat, re.I)
        if m and "seedance-2.0/fast" in endpoint:
            return {
                "estimate_usd": float(m.group(1)),
                "estimate_resolution": "720p",
                "note": "llms.txt documented $/second (720p stated)",
            }

    return None


def map_fal_unit_to_estimate(kind: str, unit: str, unit_price: float) -> dict | None:
    u = unit.strip().lower()
    if kind == "image":
        if u in ("image", "images"):
            return {"estimate_usd": unit_price, "note": f"fal pricing API per {unit}"}
        if u in ("megapixel", "megapixels", "processed megapixel", "processed megapixels"):
            # Basis ≈ 1 MP output; reference often billed separately — keep 1× unit as floor,
            # except krea handled via llms.txt.
            return {"estimate_usd": unit_price, "note": f"fal pricing API per {unit} (~1 MP)"}
        return None
    if kind == "video":
        if u in ("second", "seconds"):
            return {"estimate_usd": unit_price, "note": f"fal pricing API per {unit}"}
        # "videos" without duration is not our per-second basis
        return None
    return None


def round_money(value: float) -> float:
    # Keep up to 6 decimals, strip float noise
    return float(f"{value:.6f}".rstrip("0").rstrip(".") if "." in f"{value:.6f}" else f"{value:.6f}")


def fal_historical_call_cost(api_key: str, endpoint: str) -> float | None:
    """Average USD per successful call from fal historical pricing estimate."""
    import time

    for attempt in range(4):
        status, data = http_json(
            "POST",
            "https://api.fal.ai/v1/models/pricing/estimate",
            {"Authorization": f"Key {api_key}", "Accept": "application/json"},
            {
                "estimate_type": "historical_api_price",
                "endpoints": {endpoint: {"call_quantity": 1}},
            },
        )
        if status == 429:
            time.sleep(2.5 * (attempt + 1))
            continue
        if status == 200 and isinstance(data, dict) and data.get("total_cost") is not None:
            try:
                cost = float(data["total_cost"])
            except (TypeError, ValueError):
                return None
            return cost if cost > 0 else None
        return None
    return None


def main() -> int:
    api_key = load_fal_key()
    if not api_key:
        raise SystemExit("FAL_KEY missing")

    catalog = json.loads(CATALOG.read_text(encoding="utf-8"))
    lists = catalog["lists"]
    today = date.today().isoformat()
    catalog["checked_on"] = today

    endpoints: list[tuple[str, str, dict]] = []
    for kind in ("image", "video"):
        for tier, rows in (lists.get(kind) or {}).items():
            for row in rows or []:
                if isinstance(row, dict) and row.get("endpoint"):
                    endpoints.append((kind, str(row["endpoint"]), row))

    unique_eps = list(dict.fromkeys(e for _, e, _ in endpoints))
    print(f"fal pricing {len(unique_eps)} endpoints...")
    prices = fal_pricing(api_key, unique_eps)

    print("OpenRouter models...")
    or_models = openrouter_by_id()

    llms_cache: dict[str, str] = {}
    changes = []

    # --- LLM ---
    for tier, rows in (lists.get("llm") or {}).items():
        for row in rows or []:
            if not isinstance(row, dict):
                continue
            supplier = str(row.get("supplier") or "")
            model = str(row.get("model") or "")
            source = str(row.get("source") or "")
            oid = resolve_openrouter_id(supplier, model, source, or_models)
            before = row.get("estimate_usd")
            if oid and oid in or_models:
                info = or_models[oid]
                row["input_price"] = round_money(info["input_per_mtok"])
                row["output_price"] = round_money(info["output_per_mtok"])
                row["estimate_usd"] = round_money(info["estimate_usd"])
                row["price_verified"] = True
                row["price_source"] = f"openrouter:{oid}"
                # Keep source URL concrete when we matched a model id
                if "openrouter.ai/" not in source or source.rstrip("/").count("/") < 3:
                    row["source"] = f"https://openrouter.ai/{oid}"
                changes.append(
                    {
                        "kind": "llm",
                        "tier": tier,
                        "model": model,
                        "before": before,
                        "after": row["estimate_usd"],
                        "source": row["price_source"],
                    }
                )
            else:
                row["price_verified"] = False
                row["price_source"] = "unverified:no_openrouter_match"
                changes.append(
                    {
                        "kind": "llm",
                        "tier": tier,
                        "model": model,
                        "before": before,
                        "after": before,
                        "source": row["price_source"],
                    }
                )

    # --- Image / Video ---
    # Priority: documented llms.txt basis → clear fal unit price → fal historical avg/call.
    always_docs = {
        "krea/v3/edit",
        "luma/agent/uni-1/v1/edit",
        "luma/agent/uni-1/v1/max/edit",
        "xai/grok-imagine-image/v2.0/edit",
        "xai/grok-imagine-image/quality/edit",
        "alibaba/qwen-image-3/edit",
        "recraft/v4/style/text-to-image",
        "recraft/v4/style/pro/text-to-image",
        "bytedance/seedance-2.0/mini/image-to-video",
        "bytedance/seedance-2.0/fast/image-to-video",
        "bytedance/seedance-2.5/us/image-to-video",
        "lightricks/ltx-2.5/image-to-video/fast",
        "lightricks/ltx-2.5/image-to-video/pro",
        "fal-ai/pixverse/v6/transition",
        "fal-ai/pixverse/c1/transition",
        "fal-ai/vidu/q3/image-to-video/turbo",
        "fal-ai/vidu/q3/image-to-video",
        "blackforestlabs/flux-3-max/image-to-video",
        "google/nano-banana-2.1/edit",
        "openai/gpt-image-2.6/flare/edit",
        "openai/gpt-image-2.6/sunburst/edit",
    }

    for kind, endpoint, row in endpoints:
        before = row.get("estimate_usd")
        fal = prices.get(endpoint) or {}
        applied = None
        unit = str(fal.get("unit") or "").lower()
        unit_price = float(fal.get("unit_price") or 0)
        opaque_units = fal.get("ok") and unit == "units" and unit_price >= 1.0

        # 1) Clear fal pricing units (skip placeholder unit_price=1 "units").
        if fal.get("ok") and not opaque_units:
            mapped = map_fal_unit_to_estimate(kind, unit, unit_price)
            if mapped:
                applied = {
                    "estimate_usd": mapped["estimate_usd"],
                    "price_source": f"fal_pricing:{endpoint}:{fal.get('unit')}={fal.get('unit_price')}",
                }
            elif unit == "units" and 0 < unit_price < 1.0:
                # Some endpoints bill a small per-call "unit" fee (e.g. Phota $0.09).
                applied = {
                    "estimate_usd": unit_price,
                    "price_source": f"fal_pricing:{endpoint}:units={unit_price}",
                }

        # 2) Documented catalogue-basis price from llms.txt (refs, promo seconds, etc.).
        if endpoint in always_docs or applied is None or opaque_units:
            if endpoint not in llms_cache:
                try:
                    llms_cache[endpoint] = http_text(f"https://fal.ai/models/{endpoint}/llms.txt")
                except Exception as exc:  # noqa: BLE001
                    llms_cache[endpoint] = ""
                    print(f"llms.txt fail {endpoint}: {exc}")
            parsed = parse_llms_txt_estimate(kind, endpoint, llms_cache[endpoint]) if llms_cache[endpoint] else None
            if parsed:
                # Prefer docs when listed in always_docs or when API unit was opaque/unmapped.
                if endpoint in always_docs or applied is None or opaque_units:
                    applied = {
                        "estimate_usd": parsed["estimate_usd"],
                        "estimate_resolution": parsed.get("estimate_resolution"),
                        "price_source": f"fal_llms_txt:{endpoint}:{parsed.get('note')}",
                    }

        # 3) Historical average per call for remaining opaque token models.
        if applied is None or opaque_units:
            hist = fal_historical_call_cost(api_key, endpoint)
            if hist is not None and (applied is None or opaque_units):
                applied = {
                    "estimate_usd": hist,
                    "price_source": f"fal_historical_api_price:{endpoint}:1_call={hist}",
                }

        if applied and applied.get("estimate_usd"):
            row["estimate_usd"] = round_money(float(applied["estimate_usd"]))
            if applied.get("estimate_resolution"):
                row["estimate_resolution"] = applied["estimate_resolution"]
            elif kind == "video" and not row.get("estimate_resolution"):
                pass
            row["price_verified"] = True
            row["price_source"] = applied["price_source"]
            changes.append(
                {
                    "kind": kind,
                    "model": row.get("model"),
                    "endpoint": endpoint,
                    "before": before,
                    "after": row["estimate_usd"],
                    "source": row["price_source"],
                    "fal_unit": fal.get("unit"),
                    "fal_unit_price": fal.get("unit_price"),
                }
            )
        else:
            row["price_verified"] = False
            row["price_source"] = (
                f"unverified:fal_unit={fal.get('unit')}={fal.get('unit_price')}"
                if fal.get("ok")
                else "unverified:no_fal_price"
            )
            changes.append(
                {
                    "kind": kind,
                    "model": row.get("model"),
                    "endpoint": endpoint,
                    "before": before,
                    "after": before,
                    "source": row["price_source"],
                    "fal": fal,
                }
            )

    CATALOG.write_text(json.dumps(catalog, indent=2, ensure_ascii=False) + "\n", encoding="utf-8", newline="\n")

    verified = sum(1 for c in changes if isinstance(c.get("source"), str) and not str(c.get("source")).startswith("unverified"))
    unverified_rows = []
    for kind in ("llm", "image", "video"):
        for tier, rows in (lists.get(kind) or {}).items():
            for row in rows or []:
                if isinstance(row, dict) and not row.get("price_verified"):
                    unverified_rows.append(f"{kind}/{tier}/{row.get('model')}")

    report = {
        "checked_on": today,
        "changes": changes,
        "unverified_rows": unverified_rows,
        "verified_change_entries": verified,
    }
    REPORT.parent.mkdir(parents=True, exist_ok=True)
    REPORT.write_text(json.dumps(report, indent=2, ensure_ascii=False) + "\n", encoding="utf-8", newline="\n")

    print(f"Unverified rows ({len(unverified_rows)}):")
    for u in unverified_rows:
        print(" ", u)
    print(f"Wrote {CATALOG}")
    print(f"Wrote {REPORT}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
