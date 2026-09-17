# -*- coding: utf-8 -*-
"""
Extract embedded covers for named audio masters after Files → Audio upload.

Files-owned ingest step: not playlist-scan, not whole-site image-delivery.
Reuses makePlaylists.extract_embedded_cover_to_visual (hash-match, Visual
register, display.cover link, per-cover thumb/card delivery).

Input (stdin JSON): { "filenames": ["ast_….flac", ...] }
Output (stdout JSON): {
  "ok": true/false,
  "extracted": [...],
  "linked": [...],
  "already_linked": [...],
  "skipped_no_art": [...],
  "failed": [{"file": "...", "error": "..."}, ...]
}
"""

from __future__ import print_function

import contextlib
import io
import json
import os
import sys
from pathlib import Path

import stdio_utf8
stdio_utf8.configure()

# Keep machine-readable JSON on the real stdout pipe; library prints go to stderr.
_JSON_OUT = sys.stdout
if sys.stderr is not None and sys.stderr is not _JSON_OUT:
    sys.stdout = sys.stderr

SCRIPT_DIR = Path(__file__).resolve().parent
ROOT_DIR = SCRIPT_DIR.parent
AUDIO_MASTER_DIR = ROOT_DIR / 'media' / 'audio' / 'master'
ASSET_REGISTRY_FILE = ROOT_DIR / 'data' / 'assets' / 'registry.json'

if str(SCRIPT_DIR) not in sys.path:
    sys.path.insert(0, str(SCRIPT_DIR))

try:
    import bandpromo_python_path
    bandpromo_python_path.ensure_vendor_on_sys_path()
except Exception:
    pass


def emit_json(payload):
    text = json.dumps(payload, ensure_ascii=False)
    streams = []
    for candidate in (_JSON_OUT, getattr(sys, '__stdout__', None), sys.stderr):
        if candidate is not None and candidate not in streams:
            streams.append(candidate)
    for stream in streams:
        try:
            stream.write(text + '\n')
            stream.flush()
            return
        except Exception:
            continue


def read_payload():
    raw = sys.stdin.read()
    if not raw.strip():
        return {}
    try:
        payload = json.loads(raw)
    except Exception:
        return {}
    return payload if isinstance(payload, dict) else {}


def _safe_text(value):
    return str(value or '').strip()


def _master_path(filename):
    name = os.path.basename(_safe_text(filename))
    if not name or '/' in name or '\\' in name:
        return None
    path = AUDIO_MASTER_DIR / name
    if path.is_file():
        return path
    return None


def _load_registry_assets():
    if not ASSET_REGISTRY_FILE.is_file():
        return {}
    try:
        with open(str(ASSET_REGISTRY_FILE), 'r', encoding='utf-8') as handle:
            payload = json.load(handle)
    except Exception:
        return {}
    assets = payload.get('assets') if isinstance(payload, dict) else None
    return assets if isinstance(assets, dict) else {}


def _find_audio_entry(assets, master_name):
    master_name = os.path.basename(_safe_text(master_name))
    for asset in assets.values():
        if not isinstance(asset, dict):
            continue
        if str(asset.get('kind') or '').strip() != 'audio':
            continue
        master = os.path.basename(_safe_text(asset.get('master_filename')))
        original = os.path.basename(_safe_text(asset.get('original_filename')))
        asset_id = _safe_text(asset.get('id'))
        if master_name in (master, original, asset_id):
            return asset
        stem = Path(master_name).stem
        if stem and stem == asset_id:
            return asset
    return None


def _existing_cover_ref(entry):
    if not isinstance(entry, dict):
        return ''
    display = entry.get('display') if isinstance(entry.get('display'), dict) else {}
    return _safe_text(display.get('cover'))


def _embedded_cover_present(path):
    try:
        import audioMasterMetadata as amm
    except Exception:
        return False
    try_fn = getattr(amm, 'try_inspect_master', None)
    if try_fn is None:
        return False
    try:
        inspect = try_fn(Path(path))
    except Exception:
        return False
    if not isinstance(inspect, dict):
        return False
    return bool(inspect.get('embedded_cover_present'))


def _extract_one(path):
    import makePlaylists as mp
    buf = io.StringIO()
    with contextlib.redirect_stdout(buf):
        cover_ref = mp.extract_embedded_cover_to_visual(str(path))
    # Progress/detail lines stay on stderr via redirected stdout; ignore buf.
    return _safe_text(cover_ref)


def _ensure_delivery(cover_ref):
    if not cover_ref:
        return False
    import makePlaylists as mp
    buf = io.StringIO()
    with contextlib.redirect_stdout(buf):
        return bool(mp.ensure_visual_image_delivery_for_cover(cover_ref, force=False))


def process_filename(filename, assets):
    path = _master_path(filename)
    if path is None:
        return 'failed', {'file': filename, 'error': 'master_not_found'}

    master_name = path.name
    entry = _find_audio_entry(assets, master_name)
    existing = _existing_cover_ref(entry)
    if existing:
        try:
            _ensure_delivery(existing)
        except Exception:
            pass
        return 'already_linked', {
            'file': master_name,
            'cover': existing,
        }

    try:
        cover_ref = _extract_one(path)
    except Exception as exc:
        return 'failed', {'file': master_name, 'error': 'extract_exception:{0}'.format(exc)}

    if cover_ref:
        return 'extracted', {
            'file': master_name,
            'cover': cover_ref,
        }

    if _embedded_cover_present(path):
        return 'failed', {
            'file': master_name,
            'error': 'embedded_art_present_but_extract_returned_none',
        }

    return 'skipped_no_art', {'file': master_name}


def main():
    payload = read_payload()
    filenames = payload.get('filenames')
    if not isinstance(filenames, list) or not filenames:
        emit_json({'ok': False, 'error': 'Expected non-empty filenames array'})
        return

    requested = []
    seen = set()
    for raw_name in filenames:
        name = os.path.basename(_safe_text(raw_name))
        if not name or name in seen:
            continue
        seen.add(name)
        requested.append(name)

    if not requested:
        emit_json({'ok': False, 'error': 'No valid filenames to process'})
        return

    assets = _load_registry_assets()
    extracted = []
    linked = []
    already_linked = []
    skipped_no_art = []
    failed = []

    for name in requested:
        status, detail = process_filename(name, assets)
        if status == 'extracted':
            extracted.append(detail.get('file') or name)
            linked.append(detail.get('file') or name)
        elif status == 'already_linked':
            already_linked.append(detail.get('file') or name)
            linked.append(detail.get('file') or name)
        elif status == 'skipped_no_art':
            skipped_no_art.append(detail.get('file') or name)
        else:
            failed.append(detail if isinstance(detail, dict) else {
                'file': name,
                'error': 'unknown',
            })

    # Refresh asset map after extracts so subsequent checks see new covers.
    # (Not required for response; useful if PHP re-probes.)

    emit_json({
        'ok': len(failed) == 0,
        'extracted': extracted,
        'linked': linked,
        'already_linked': already_linked,
        'skipped_no_art': skipped_no_art,
        'failed': failed,
    })


if __name__ == '__main__':
    main()
