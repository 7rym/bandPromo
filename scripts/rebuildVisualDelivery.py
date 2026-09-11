#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""Rebuild Visual image delivery variants for one or more asset ids (stdin JSON)."""
from __future__ import print_function

import json
import os
import sys
from pathlib import Path

SCRIPT_DIR = Path(__file__).resolve().parent
sys.path.insert(0, str(SCRIPT_DIR))

try:
    import bandpromo_python_path
    bandpromo_python_path.ensure_vendor_on_sys_path()
except Exception:
    pass

import optimizeMedia as optimize_media


def main():
    raw = sys.stdin.read()
    try:
        payload = json.loads(raw) if raw.strip() else {}
    except Exception:
        print(json.dumps({'ok': False, 'error': 'Invalid JSON'}, ensure_ascii=False))
        return 1
    if not isinstance(payload, dict):
        print(json.dumps({'ok': False, 'error': 'Expected object'}, ensure_ascii=False))
        return 1

    asset_ids = payload.get('asset_ids')
    if not isinstance(asset_ids, list):
        single = str(payload.get('asset_id') or '').strip()
        asset_ids = [single] if single else []
    force = bool(payload.get('force'))
    if force:
        os.environ['BANDPROMO_FORCE_VISUAL_DELIVERY'] = '1'

    registry = optimize_media.load_asset_registry()
    assets = registry.get('assets') if isinstance(registry.get('assets'), dict) else {}
    rebuilt = []
    skipped = []
    failed = []

    for raw_id in asset_ids:
        asset_id = str(raw_id or '').strip()
        if not asset_id:
            continue
        asset = assets.get(asset_id)
        if not isinstance(asset, dict) or str(asset.get('kind') or '') != 'visual':
            failed.append({'asset_id': asset_id, 'error': 'not a visual asset'})
            continue
        if str(asset.get('media_type') or 'image') not in ('image', ''):
            skipped.append(asset_id)
            continue
        item = dict(asset)
        item['id'] = asset_id
        try:
            result = optimize_media.process_visual_image_asset(item)
        except Exception as exc:
            failed.append({'asset_id': asset_id, 'error': str(exc)})
            continue
        if result is True:
            rebuilt.append(asset_id)
        elif result == 'skipped':
            skipped.append(asset_id)
        else:
            failed.append({'asset_id': asset_id, 'error': 'no source or convert failed'})

    print(json.dumps({
        'ok': True,
        'rebuilt': rebuilt,
        'skipped': skipped,
        'failed': failed,
    }, ensure_ascii=False))
    return 0


if __name__ == '__main__':
    sys.exit(main() or 0)
