# -*- coding: utf-8 -*-
"""Asset registry helpers for site health (same JSON shape as PHP admin)."""

from __future__ import print_function

import json
import os
import re
from datetime import datetime

from paths import (
    AUDIO_EXTS,
    AUDIO_MASTER_DIR,
    AUDIO_OPTIMAL_DIR,
    AUDIO_ORIGINAL_DIR,
    REGISTRY_PATH,
    ROOT_DIR,
    VISUAL_DELIVERY_DIR,
    VISUAL_EXTS,
    VISUAL_MASTER_DIR,
)

_ASSET_ID_RE = re.compile(r'^ast_[0-9A-HJKMNP-TV-Z]{26}$', re.IGNORECASE)


def is_asset_id(value):
    text = str(value or '').strip()
    return bool(_ASSET_ID_RE.match(text))


def empty_registry():
    return {
        'version': 1,
        'assets': {},
        'by_master_filename': {},
        'by_original_filename': {},
    }


def load_registry():
    if not os.path.isfile(REGISTRY_PATH):
        return empty_registry(), 'missing'
    try:
        with open(REGISTRY_PATH, 'r', encoding='utf-8') as handle:
            payload = json.load(handle)
    except Exception as exc:
        return empty_registry(), 'unreadable:{0}'.format(exc)
    if not isinstance(payload, dict):
        return empty_registry(), 'invalid'
    assets = payload.get('assets')
    if not isinstance(assets, dict):
        payload['assets'] = {}
    if not isinstance(payload.get('by_master_filename'), dict):
        payload['by_master_filename'] = {}
    if not isinstance(payload.get('by_original_filename'), dict):
        payload['by_original_filename'] = {}
    return payload, 'ok'


def write_registry(registry):
    folder = os.path.dirname(REGISTRY_PATH)
    if not os.path.isdir(folder):
        os.makedirs(folder)
    tmp = REGISTRY_PATH + '.tmp'
    with open(tmp, 'w', encoding='utf-8', newline='\n') as handle:
        json.dump(registry, handle, ensure_ascii=False, indent=2)
        handle.write('\n')
    if os.path.isfile(REGISTRY_PATH):
        os.replace(tmp, REGISTRY_PATH)
    else:
        os.rename(tmp, REGISTRY_PATH)


def _list_files(directory, allowed_exts):
    if not os.path.isdir(directory):
        return []
    found = []
    try:
        names = os.listdir(directory)
    except Exception:
        return []
    for name in names:
        if name in ('.', '..') or name.lower() == 'desktop.ini':
            continue
        path = os.path.join(directory, name)
        if not os.path.isfile(path):
            continue
        ext = os.path.splitext(name)[1].lower()
        if ext not in allowed_exts:
            continue
        found.append(name)
    found.sort()
    return found


def list_audio_masters_on_disk():
    return _list_files(AUDIO_MASTER_DIR, AUDIO_EXTS)


def list_audio_originals_on_disk():
    return _list_files(AUDIO_ORIGINAL_DIR, AUDIO_EXTS)


def list_visual_masters_on_disk():
    return _list_files(VISUAL_MASTER_DIR, VISUAL_EXTS)


def assets_by_kind(registry):
    audio = []
    visual = []
    sfx = []
    other = []
    assets = registry.get('assets') if isinstance(registry, dict) else {}
    if not isinstance(assets, dict):
        return audio, visual, sfx, other
    for asset_id, raw in assets.items():
        if not isinstance(raw, dict):
            continue
        item = dict(raw)
        item.setdefault('id', asset_id)
        item.setdefault('asset_id', asset_id)
        kind = str(item.get('kind') or '').strip().lower()
        if kind in ('', 'audio'):
            audio.append(item)
        elif kind == 'visual':
            visual.append(item)
        elif kind == 'sfx':
            sfx.append(item)
        else:
            other.append(item)
    return audio, visual, sfx, other


def uncatalogued_audio_masters(registry):
    """Disk ast_* masters missing from registry (register-in-place candidates)."""
    by_master = registry.get('by_master_filename') if isinstance(registry, dict) else {}
    if not isinstance(by_master, dict):
        by_master = {}
    assets = registry.get('assets') if isinstance(registry, dict) else {}
    if not isinstance(assets, dict):
        assets = {}

    pending = []
    for name in list_audio_masters_on_disk():
        stem = os.path.splitext(name)[0]
        if not is_asset_id(stem):
            continue
        existing_id = str(by_master.get(name) or '').strip()
        if existing_id and existing_id in assets:
            asset = assets.get(existing_id) or {}
            if str(asset.get('kind') or '').strip().lower() in ('', 'audio'):
                continue
        # Id may exist with wrong/missing master pointer
        by_id = assets.get(stem)
        if isinstance(by_id, dict):
            kind = str(by_id.get('kind') or '').strip().lower()
            if kind not in ('', 'audio') and kind != '':
                continue
            existing_master = os.path.basename(str(by_id.get('master_filename') or '').strip())
            if existing_master == name:
                continue
            if existing_master:
                other_path = os.path.join(AUDIO_MASTER_DIR, existing_master)
                if os.path.isfile(other_path):
                    continue
        ext = os.path.splitext(name)[1].lstrip('.').lower() or 'mp3'
        pending.append({
            'master_filename': name,
            'asset_id': stem,
            'master_format': ext,
        })
    return pending


def uncatalogued_visual_masters(registry):
    by_master = registry.get('by_master_filename') if isinstance(registry, dict) else {}
    if not isinstance(by_master, dict):
        by_master = {}
    assets = registry.get('assets') if isinstance(registry, dict) else {}
    if not isinstance(assets, dict):
        assets = {}

    pending = []
    for name in list_visual_masters_on_disk():
        stem = os.path.splitext(name)[0]
        if not is_asset_id(stem):
            continue
        existing_id = str(by_master.get(name) or '').strip()
        if existing_id and existing_id in assets:
            asset = assets.get(existing_id) or {}
            if str(asset.get('kind') or '').strip().lower() == 'visual':
                continue
        ext = os.path.splitext(name)[1].lstrip('.').lower() or 'jpg'
        pending.append({
            'master_filename': name,
            'asset_id': stem,
            'master_format': ext,
            'media_type': 'video' if ext in ('mkv', 'mp4', 'webm') else 'image',
        })
    return pending


def register_audio_master(registry, master_filename, master_format, asset_id, original_filename=''):
    master_filename = os.path.basename(str(master_filename or '').strip())
    asset_id = str(asset_id or '').strip()
    original_filename = os.path.basename(str(original_filename or '').strip())
    if master_filename == '' or not is_asset_id(asset_id):
        raise ValueError('Invalid master or asset id')

    by_master = registry.setdefault('by_master_filename', {})
    existing = str(by_master.get(master_filename) or '').strip()
    if existing and existing in registry.get('assets', {}):
        return registry['assets'][existing]

    entry = {
        'id': asset_id,
        'kind': 'audio',
        'original_filename': original_filename,
        'master_filename': master_filename,
        'master_format': str(master_format or '').strip().lower() or 'mp3',
        'release_id': '',
        'slug': '',
        'display': {},
        'tags': [],
        'created_at': datetime.utcnow().strftime('%Y-%m-%dT%H:%M:%SZ'),
    }
    registry.setdefault('assets', {})[asset_id] = entry
    by_master[master_filename] = asset_id
    if original_filename:
        registry.setdefault('by_original_filename', {})[original_filename] = asset_id
    return entry


def register_visual_master(registry, master_filename, master_format, asset_id, media_type='image'):
    master_filename = os.path.basename(str(master_filename or '').strip())
    asset_id = str(asset_id or '').strip()
    master_format = str(master_format or '').strip().lower() or 'jpg'
    media_type = str(media_type or 'image').strip().lower()
    if media_type not in ('image', 'video'):
        media_type = 'video' if master_format in ('mkv', 'mp4', 'webm') else 'image'
    if master_filename == '' or not is_asset_id(asset_id):
        raise ValueError('Invalid master or asset id')

    by_master = registry.setdefault('by_master_filename', {})
    existing = str(by_master.get(master_filename) or '').strip()
    if existing and existing in registry.get('assets', {}):
        return registry['assets'][existing]

    intake = 'video' if media_type == 'video' else 'img'
    entry = {
        'id': asset_id,
        'kind': 'visual',
        'media_type': media_type,
        'intake_bucket': intake,
        'brand_id': '',
        'role': 'unassigned',
        'has_alpha': False,
        'original_filename': '',
        'master_filename': master_filename,
        'master_format': master_format,
        'release_id': '',
        'slug': '',
        'display': {
            'title': 'Untitled video' if media_type == 'video' else 'Untitled image',
        },
        'tags': ['unassigned'],
        'delivery': [],
        'content_sha256': '',
        'created_at': datetime.utcnow().strftime('%Y-%m-%dT%H:%M:%SZ'),
    }
    registry.setdefault('assets', {})[asset_id] = entry
    by_master[master_filename] = asset_id
    return entry


def root_dir():
    return ROOT_DIR


def missing_audio_deliverables(registry):
    """Registered audio assets whose optimal MP3 is missing (list-check only)."""
    audio, _visual, _sfx, _other = assets_by_kind(registry)
    missing = []
    for asset in audio:
        master = os.path.basename(str(asset.get('master_filename') or '').strip())
        asset_id = str(asset.get('id') or asset.get('asset_id') or '').strip()
        stem = ''
        if master:
            stem = os.path.splitext(master)[0]
        elif is_asset_id(asset_id):
            stem = asset_id
        if stem == '':
            continue
        optimal = os.path.join(AUDIO_OPTIMAL_DIR, stem + '.mp3')
        if not os.path.isfile(optimal):
            missing.append({
                'asset_id': asset_id or stem,
                'master_filename': master,
                'optimal': optimal,
            })
    return missing


def missing_visual_deliveries(registry):
    """Registered visual assets with no delivery folder (list-check only)."""
    _audio, visual, _sfx, _other = assets_by_kind(registry)
    missing = []
    for asset in visual:
        asset_id = str(asset.get('id') or asset.get('asset_id') or '').strip()
        if not is_asset_id(asset_id):
            continue
        folder = os.path.join(VISUAL_DELIVERY_DIR, asset_id)
        if not os.path.isdir(folder):
            missing.append({
                'asset_id': asset_id,
                'master_filename': os.path.basename(str(asset.get('master_filename') or '')),
            })
            continue
        # Empty folder counts as missing.
        try:
            names = [n for n in os.listdir(folder) if n not in ('.', '..')]
        except Exception:
            names = []
        if not names:
            missing.append({
                'asset_id': asset_id,
                'master_filename': os.path.basename(str(asset.get('master_filename') or '')),
            })
    return missing
