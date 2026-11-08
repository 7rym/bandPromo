#!/usr/bin/env python3
"""Verify Create catalogue prices against fal pricing API + OpenRouter models."""
from typing import Any, Dict, List, Optional, Tuple
import json
import os
import re
import sys
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CATALOG = ROOT / "biblioteca" / "create-model-catalog.json"
OUT = ROOT / "log" / "create-catalog-price-verify.json"


def load_dotenv_key(name: str) -> str:
    env_path = ROOT / ".env"
    if not env_path.is_file():
        return ""
    for raw in env_path.read_text(encoding="utf-8", errors="replace").splitlines():
        line = raw.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        k, v = line.split("=", 1)
        if k.strip() == name:
            return v.strip().strip('"').strip("'")
    return os.environ.get(name, "").strip()


def http_json(method: str, url: str, headers: Optional[Dict[str, str]] = None, timeout: int = 45):
    req = urllib.request.Request(url, method=method)
    for k, v in (headers or {}).items():
        req.add_header(k, v)
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            body = resp.read().decode("utf-8", errors="replace")
            return resp.status, json.loads(body) if body else {}
    except urllib.error.HTTPError as e:
        body = e.read().decode("utf-8", errors="replace")
        try:
            decoded = json.loads(body) if body else {}
        except json.JSONDecodeError:
            decoded = {"raw": body[:500]}
        return e.code, decoded
    except Exception as e:  # noqa: BLE001
        return 0, {"error": str(e)}


def fal_pricing(api_key: str, endpoint_ids: List[str]) -> Dict[str, dict]:
    """Return endpoint_id -> price row from fal."""
    out: Dict[str, dict] = {}
    # API accepts up to 50; keep batches small for clarity.
    for i in range(0, len(endpoint_ids), 20):
        batch = endpoint_ids[i : i + 20]
        qs = "&".join("endpoint_id=" + urllib.parse.quote(eid, safe="") for eid in batch)
        url = f"https://api.fal.ai/v1/models/pricing?{qs}"
        status, data = http_json(
            "GET",
            url,
            {"Authorization": f"Key {api_key}", "Accept": "application/json"},
        )
        if status != 200 or not isinstance(data, dict):
            for eid in batch:
                out[eid] = {"ok": False, "status": status, "error": data}
            continue
        found = {p.get("endpoint_id"): p for p in (data.get("prices") or []) if isinstance(p, dict)}
        for eid in batch:
            if eid in found:
                row = found[eid]
                out[eid] = {
                    "ok": True,
                    "unit_price": row.get("unit_price"),
                    "unit": row.get("unit"),
                    "currency": row.get("currency"),
                }
            else:
                out[eid] = {"ok": False, "status": status, "error": "not in pricing response"}
    return out


def openrouter_models() -> Dict[str, dict]:
    status, data = http_json("GET", "https://openrouter.ai/api/v1/models", {"Accept": "application/json"})
    if status != 200 or not isinstance(data, dict):
        return {"__error__": {"ok": False, "status": status, "error": data}}
    by_id: Dict[str, dict] = {}
    for row in data.get("data") or []:
        if not isinstance(row, dict):
            continue
        mid = str(row.get("id") or "")
        if not mid:
            continue
        pricing = row.get("pricing") if isinstance(row.get("pricing"), dict) else {}
        # OpenRouter pricing is usually per-token strings in USD.
        try:
            prompt = float(pricing.get("prompt") or 0)
            completion = float(pricing.get("completion") or 0)
        except (TypeError, ValueError):
            prompt = completion = 0.0
        by_id[mid] = {
            "id": mid,
            "name": row.get("name"),
            "prompt_per_token": prompt,
            "completion_per_token": completion,
            "input_per_mtok": prompt * 1_000_000,
            "output_per_mtok": completion * 1_000_000,
            "estimate_1m_in_out": (prompt + completion) * 1_000_000,
        }
    return by_id


def slugify_token(value: str) -> str:
    value = value.strip().lower()
    value = re.sub(r"[^a-z0-9.]+", "-", value)
    return value.strip("-.")


def guess_openrouter_ids(supplier: str, model: str, source: str) -> List[str]:
    """Generate candidate OpenRouter ids from catalogue fields."""
    candidates: List[str] = []
    src = (source or "").strip()
    m = re.search(r"openrouter\.ai/([^/?#]+(?:/[^/?#]+)?)", src, re.I)
    if m:
        path = m.group(1).strip("/")
        if path and "/" in path:
            candidates.append(path.lower())
        elif path:
            # supplier page only — keep for prefix search
            pass

    vendor_map = {
        "openai": "openai",
        "anthropic": "anthropic",
        "google": "google",
        "deepseek": "deepseek",
        "minimax": "minimax",
        "mistral ai": "mistralai",
        "moonshot ai": "moonshotai",
        "alibaba": "qwen",
        "z.ai": "z-ai",
        "inclusionai": "inclusionai",
        "liquid ai": "liquid",
        "xai": "x-ai",
    }
    vendor = vendor_map.get(supplier.strip().lower(), slugify_token(supplier))
    model_slug = slugify_token(model)
    candidates.append(f"{vendor}/{model_slug}")
    # Common variants
    candidates.append(f"{vendor}/{model_slug.replace('gpt-6', 'gpt-5')}")
    candidates.append(f"{vendor}/{model_slug.replace('claude-haiku-5-5', 'claude-3.5-haiku')}")
    # Deduplicate preserving order
    seen = set()
    out = []
    for c in candidates:
        if c and c not in seen:
            seen.add(c)
            out.append(c)
    return out


def match_openrouter(or_models: Dict[str, dict], supplier: str, model: str, source: str) -> dict:
    if "__error__" in or_models:
        return {"ok": False, "error": or_models["__error__"]}
    candidates = guess_openrouter_ids(supplier, model, source)
    for cid in candidates:
        if cid in or_models:
            row = or_models[cid]
            return {"ok": True, "matched_id": cid, "match": "exact", **row}

    # Fuzzy: same vendor prefix, model tokens overlapping
    vendor = candidates[0].split("/", 1)[0] if candidates else ""
    model_tokens = set(slugify_token(model).split("-"))
    best = None
    best_score = 0
    for mid, row in or_models.items():
        if vendor and not mid.startswith(vendor + "/"):
            continue
        mid_tokens = set(slugify_token(mid.split("/", 1)[-1]).split("-"))
        score = len(model_tokens & mid_tokens)
        if score > best_score:
            best_score = score
            best = row
            best_id = mid
    if best and best_score >= max(2, len(model_tokens) // 2):
        return {"ok": True, "matched_id": best_id, "match": f"fuzzy_score_{best_score}", **best}
    return {"ok": False, "candidates": candidates, "error": "no openrouter match"}


def main() -> int:
    api_key = load_dotenv_key("FAL_KEY")
    if not api_key:
        print("ERROR: FAL_KEY missing from .env", file=sys.stderr)
        return 1

    catalog = json.loads(CATALOG.read_text(encoding="utf-8"))
    lists = catalog.get("lists") or {}

    image_eps: List[Tuple[str, str, dict]] = []
    video_eps: List[Tuple[str, str, dict]] = []
    llm_rows: List[Tuple[str, dict]] = []

    for tier, rows in (lists.get("image") or {}).items():
        for row in rows or []:
            if isinstance(row, dict) and row.get("endpoint"):
                image_eps.append((tier, str(row["endpoint"]), row))
    for tier, rows in (lists.get("video") or {}).items():
        for row in rows or []:
            if isinstance(row, dict) and row.get("endpoint"):
                video_eps.append((tier, str(row["endpoint"]), row))
    for tier, rows in (lists.get("llm") or {}).items():
        for row in rows or []:
            if isinstance(row, dict):
                llm_rows.append((tier, row))

    all_eps = [e for _, e, _ in image_eps] + [e for _, e, _ in video_eps]
    # Also probe openrouter router for completeness
    all_eps_unique = list(dict.fromkeys(all_eps + ["openrouter/router"]))
    print(f"Querying fal pricing for {len(all_eps_unique)} endpoints...")
    fal_prices = fal_pricing(api_key, all_eps_unique)

    print("Fetching OpenRouter model list...")
    or_models = openrouter_models()
    or_count = 0 if "__error__" in or_models else len(or_models)
    print(f"OpenRouter models loaded: {or_count}")

    report = {
        "checked_on": catalog.get("checked_on"),
        "fal_endpoints": {},
        "image": [],
        "video": [],
        "llm": [],
        "summary": {},
    }

    def classify_media(kind: str, tier: str, row: dict, endpoint: str) -> dict:
        price = fal_prices.get(endpoint) or {"ok": False, "error": "missing"}
        catalog_est = row.get("estimate_usd")
        entry = {
            "kind": kind,
            "tier": tier,
            "supplier": row.get("supplier"),
            "model": row.get("model"),
            "endpoint": endpoint,
            "catalog_estimate_usd": catalog_est,
            "fal": price,
        }
        if price.get("ok"):
            unit = str(price.get("unit") or "")
            unit_price = float(price.get("unit_price") or 0)
            entry["verified_unit_price"] = unit_price
            entry["verified_unit"] = unit
            # Map to our estimate basis as best-effort.
            if kind == "image":
                # Prefer per-image; megapixel approx 1 MP = 1 unit.
                entry["verified_estimate_usd"] = unit_price
                entry["estimate_basis_note"] = f"fal unit_price per {unit}"
            else:
                # video: second / video
                entry["verified_estimate_usd"] = unit_price
                entry["estimate_basis_note"] = f"fal unit_price per {unit}"
            try:
                cat = float(catalog_est) if catalog_est is not None else None
            except (TypeError, ValueError):
                cat = None
            if cat is not None and unit_price > 0:
                entry["catalog_delta_pct"] = round(((cat - unit_price) / unit_price) * 100.0, 1)
        return entry

    for tier, endpoint, row in image_eps:
        report["image"].append(classify_media("image", tier, row, endpoint))
    for tier, endpoint, row in video_eps:
        report["video"].append(classify_media("video", tier, row, endpoint))

    for tier, row in llm_rows:
        match = match_openrouter(
            or_models,
            str(row.get("supplier") or ""),
            str(row.get("model") or ""),
            str(row.get("source") or ""),
        )
        entry = {
            "kind": "llm",
            "tier": tier,
            "supplier": row.get("supplier"),
            "model": row.get("model"),
            "catalog_input": row.get("input_price"),
            "catalog_output": row.get("output_price"),
            "catalog_estimate_usd": row.get("estimate_usd"),
            "openrouter": match,
        }
        if match.get("ok"):
            entry["verified_estimate_usd"] = match.get("estimate_1m_in_out")
            entry["verified_input_per_mtok"] = match.get("input_per_mtok")
            entry["verified_output_per_mtok"] = match.get("output_per_mtok")
            try:
                cat = float(row.get("estimate_usd") or 0)
                ver = float(match.get("estimate_1m_in_out") or 0)
                if ver > 0:
                    entry["catalog_delta_pct"] = round(((cat - ver) / ver) * 100.0, 1)
            except (TypeError, ValueError):
                pass
        report["llm"].append(entry)

    report["fal_endpoints"] = fal_prices

    def summarise(rows: List[Dict[str, Any]]) -> dict:
        ok = sum(1 for r in rows if r.get("fal", r.get("openrouter", {})).get("ok"))
        return {"total": len(rows), "verified": ok, "unverified": len(rows) - ok}

    report["summary"] = {
        "image": summarise(report["image"]),
        "video": summarise(
            [{**r, "fal": r.get("fal")} for r in report["video"]]
        ),
        "llm": {
            "total": len(report["llm"]),
            "verified": sum(1 for r in report["llm"] if r.get("openrouter", {}).get("ok")),
            "unverified": sum(1 for r in report["llm"] if not r.get("openrouter", {}).get("ok")),
        },
    }

    OUT.parent.mkdir(parents=True, exist_ok=True)
    OUT.write_text(json.dumps(report, indent=2, ensure_ascii=False) + "\n", encoding="utf-8", newline="\n")

    print("\n=== SUMMARY ===")
    for kind, s in report["summary"].items():
        print(f"{kind}: verified {s['verified']}/{s['total']} unverified {s['unverified']}")

    print("\n=== IMAGE ===")
    for r in report["image"]:
        if r["fal"].get("ok"):
            print(
                f"OK  [{r['tier']}] {r['endpoint']} "
                f"fal={r['verified_unit_price']}/{r['verified_unit']} "
                f"catalog={r['catalog_estimate_usd']} delta={r.get('catalog_delta_pct')}%"
            )
        else:
            print(f"MISS[{r['tier']}] {r['endpoint']} err={r['fal'].get('error')}")

    print("\n=== VIDEO ===")
    for r in report["video"]:
        if r["fal"].get("ok"):
            print(
                f"OK  [{r['tier']}] {r['endpoint']} "
                f"fal={r['verified_unit_price']}/{r['verified_unit']} "
                f"catalog={r['catalog_estimate_usd']} delta={r.get('catalog_delta_pct')}%"
            )
        else:
            print(f"MISS[{r['tier']}] {r['endpoint']} err={r['fal'].get('error')}")

    print("\n=== LLM ===")
    for r in report["llm"]:
        orr = r.get("openrouter") or {}
        if orr.get("ok"):
            print(
                f"OK  [{r['tier']}] {r['supplier']}/{r['model']} -> {orr.get('matched_id')} "
                f"({orr.get('match')}) est={r.get('verified_estimate_usd')} "
                f"catalog={r['catalog_estimate_usd']} delta={r.get('catalog_delta_pct')}%"
            )
        else:
            print(
                f"MISS[{r['tier']}] {r['supplier']}/{r['model']} "
                f"candidates={orr.get('candidates')} err={orr.get('error')}"
            )

    print(f"\nWrote {OUT}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
