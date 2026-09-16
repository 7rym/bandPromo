# -*- coding: utf-8 -*-
"""Asset registry helpers for site health (same JSON shape as PHP admin)."""

from __future__ import print_function

import json
import os
import re
import sys
from datetime import datetime

from paths import (
    AUDIO_EXTS,
    AUDIO_MASTER_DIR,
    AUDIO_OPTIMAL_DIR,
    AUDIO_ORIGINAL_DIR,
    REGISTRY_PATH,
    ROOT_DIR,
    SFX_MASTER_DIR,
    SFX_OPTIMAL_DIR,
    SFX_ORIGINAL_DIR,
    VISUAL_DELIVERY_DIR,
    VISUAL_EXTS,
    VISUAL_MASTER_DIR,
)

# bandPromo asset ids are ast_ + 20 Crockford chars (see bandpromo_asset_is_asset_id).
# Not a full 26-char ULID — matching {26} silently ignores every real master on disk.
_ASSET_ID_RE = re.compile(r'^ast_[0-9A-HJKMNP-TV-Z]{20}$', re.IGNORECASE)


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


def list_sfx_masters_on_disk():
    return _list_files(SFX_MASTER_DIR, AUDIO_EXTS)


def list_sfx_originals_on_disk():
    return _list_files(SFX_ORIGINAL_DIR, AUDIO_EXTS)


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


def uncatalogued_sfx_masters(registry):
    """Disk ast_* SFX masters missing from registry (register-in-place candidates)."""
    by_master = registry.get('by_master_filename') if isinstance(registry, dict) else {}
    if not isinstance(by_master, dict):
        by_master = {}
    assets = registry.get('assets') if isinstance(registry, dict) else {}
    if not isinstance(assets, dict):
        assets = {}

    pending = []
    for name in list_sfx_masters_on_disk():
        stem = os.path.splitext(name)[0]
        if not is_asset_id(stem):
            continue
        existing_id = str(by_master.get(name) or '').strip()
        if existing_id and existing_id in assets:
            asset = assets.get(existing_id) or {}
            if str(asset.get('kind') or '').strip().lower() == 'sfx':
                continue
        by_id = assets.get(stem)
        if isinstance(by_id, dict):
            kind = str(by_id.get('kind') or '').strip().lower()
            if kind == 'sfx':
                existing_master = os.path.basename(str(by_id.get('master_filename') or '').strip())
                if existing_master == name:
                    continue
                if existing_master:
                    other_path = os.path.join(SFX_MASTER_DIR, existing_master)
                    if os.path.isfile(other_path):
                        continue
            elif kind not in ('',):
                # Do not steal an audio/visual row that shares this id.
                continue
        ext = os.path.splitext(name)[1].lstrip('.').lower() or 'mp3'
        pending.append({
            'master_filename': name,
            'asset_id': stem,
            'master_format': ext,
        })
    return pending


def _guess_sfx_original_filename(master_filename):
    """Match sfx/original by unique byte size when present."""
    master_filename = os.path.basename(str(master_filename or '').strip())
    if not master_filename:
        return ''
    master_path = os.path.join(SFX_MASTER_DIR, master_filename)
    if not os.path.isfile(master_path):
        return ''
    try:
        master_size = int(os.path.getsize(master_path))
    except Exception:
        return ''
    matches = []
    for name in list_sfx_originals_on_disk():
        path = os.path.join(SFX_ORIGINAL_DIR, name)
        try:
            if int(os.path.getsize(path)) == master_size:
                matches.append(name)
        except Exception:
            continue
    if len(matches) == 1:
        return matches[0]
    return ''


def _sfx_title_from_master(master_filename, original_filename=''):
    """Best-effort display title from embedded tags or cleaned original name."""
    master_filename = os.path.basename(str(master_filename or '').strip())
    path = os.path.join(SFX_MASTER_DIR, master_filename) if master_filename else ''
    if path and os.path.isfile(path):
        try:
            scripts_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
            if scripts_dir not in sys.path:
                sys.path.insert(0, scripts_dir)
            import audioMasterMetadata as amm
            from pathlib import Path
            try_fn = getattr(amm, 'try_inspect_master', None)
            if try_fn is not None:
                inspect = try_fn(Path(path))
                if isinstance(inspect, dict):
                    title = str(inspect.get('title') or '').strip()
                    if title:
                        return title
        except Exception:
            pass
    label = os.path.basename(str(original_filename or '').strip()) or master_filename
    stem = os.path.splitext(label)[0]
    cleaned = stem.replace('_', ' ').replace('-', ' ').strip()
    return cleaned or stem or 'Sound effect'


def _guess_sfx_brand_id(asset_id):
    """If a brand JSON already references this asset id, use that brand."""
    asset_id = str(asset_id or '').strip()
    if not is_asset_id(asset_id):
        return ''
    brands_dir = os.path.join(ROOT_DIR, 'data', 'brands')
    if not os.path.isdir(brands_dir):
        return ''
    try:
        names = os.listdir(brands_dir)
    except Exception:
        return ''
    for name in names:
        if not name.lower().endswith('.json') or name.lower() == 'registry.json':
            continue
        path = os.path.join(brands_dir, name)
        try:
            with open(path, 'r', encoding='utf-8') as handle:
                text = handle.read()
        except Exception:
            continue
        if asset_id not in text:
            continue
        try:
            payload = json.loads(text)
        except Exception:
            continue
        brand_id = str(payload.get('id') or '').strip()
        if brand_id:
            return brand_id
        # Fallback: filename stem for legacy brand files.
        stem = os.path.splitext(name)[0]
        if stem:
            return stem
    return ''


def register_audio_master(registry, master_filename, master_format, asset_id, original_filename=''):
    master_filename = os.path.basename(str(master_filename or '').strip())
    asset_id = str(asset_id or '').strip()
    original_filename = os.path.basename(str(original_filename or '').strip())
    if master_filename == '' or not is_asset_id(asset_id):
        raise ValueError('Invalid master or asset id')

    by_master = registry.setdefault('by_master_filename', {})
    existing = str(by_master.get(master_filename) or '').strip()
    if existing and existing in registry.get('assets', {}):
        entry = registry['assets'][existing]
        # Bare rows (empty / Untitled display) still need tag fill.
        try:
            import audio_display
            if audio_display.display_needs_tag_fill(entry):
                audio_display.fill_entry_from_master_tags(entry)
        except Exception:
            pass
        return entry

    # Register-in-place masters have no separate original upload name — use the
    # master filename so PHP normalize / indexes stay consistent.
    if not original_filename:
        original_filename = master_filename

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
    # Capture content from the master (registry ← tags). Never invent empty shells
    # that Files shows as Untitled while masters still hold real ID3/Vorbis data.
    try:
        import audio_display
        audio_display.fill_entry_from_master_tags(entry)
    except Exception:
        pass
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
    # Register-in-place masters have no separate original upload name — use the
    # master filename so PHP normalize keeps the row (empty original_filename
    # would drop the visual on the next registry write-back).
    original_filename = master_filename
    entry = {
        'id': asset_id,
        'kind': 'visual',
        'media_type': media_type,
        'intake_bucket': intake,
        'brand_id': '',
        'role': 'unassigned',
        'has_alpha': False,
        'original_filename': original_filename,
        'master_filename': master_filename,
        'master_format': master_format,
        'release_id': '',
        'slug': '',
        'display': {
            'title': 'Untitled video' if media_type == 'video' else 'Untitled image',
        },
        'tags': ['unassigned'],
        'delivery': {},
        'content_sha256': '',
        'created_at': datetime.utcnow().strftime('%Y-%m-%dT%H:%M:%SZ'),
    }
    registry.setdefault('assets', {})[asset_id] = entry
    by_master[master_filename] = asset_id
    registry.setdefault('by_original_filename', {})[original_filename] = asset_id
    return entry


def register_sfx_master(registry, master_filename, master_format, asset_id, original_filename=''):
    """Register an existing media/sfx/master/ast_* file (no mint / no copy)."""
    master_filename = os.path.basename(str(master_filename or '').strip())
    asset_id = str(asset_id or '').strip()
    original_filename = os.path.basename(str(original_filename or '').strip())
    master_format = str(master_format or '').strip().lower() or 'mp3'
    if master_filename == '' or not is_asset_id(asset_id):
        raise ValueError('Invalid master or asset id')

    by_master = registry.setdefault('by_master_filename', {})
    existing = str(by_master.get(master_filename) or '').strip()
    if existing and existing in registry.get('assets', {}):
        entry = registry['assets'][existing]
        if str(entry.get('kind') or '').strip().lower() == 'sfx':
            return entry

    if not original_filename:
        original_filename = _guess_sfx_original_filename(master_filename) or master_filename

    brand_id = _guess_sfx_brand_id(asset_id)
    optimal = os.path.join(SFX_OPTIMAL_DIR, asset_id + '.mp3')
    delivery_ready = os.path.isfile(optimal)
    title = _sfx_title_from_master(master_filename, original_filename)

    entry = {
        'id': asset_id,
        'kind': 'sfx',
        'media_type': 'audio',
        'intake_bucket': 'sfx',
        'brand_id': brand_id,
        'role': 'sfx',
        'original_filename': original_filename,
        'master_filename': master_filename,
        'master_format': master_format,
        'release_id': '',
        'slug': '',
        'display': {
            'title': title,
            'description': '',
            'captured_at': '',
            'keywords': [],
        },
        'tags': ['sfx'],
        'delivery': {
            'ready': delivery_ready,
            'audio_optimal': delivery_ready,
            'source': 'master',
        },
        'created_at': datetime.utcnow().strftime('%Y-%m-%dT%H:%M:%SZ'),
    }
    registry.setdefault('assets', {})[asset_id] = entry
    by_master[master_filename] = asset_id
    if original_filename:
        registry.setdefault('by_original_filename', {})[original_filename] = asset_id
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


def missing_sfx_deliverables(registry):
    """Registered SFX assets whose optimal MP3 is missing."""
    _audio, _visual, sfx, _other = assets_by_kind(registry)
    missing = []
    for asset in sfx:
        asset_id = str(asset.get('id') or asset.get('asset_id') or '').strip()
        master = os.path.basename(str(asset.get('master_filename') or '').strip())
        stem = ''
        if is_asset_id(asset_id):
            stem = asset_id
        elif master:
            stem = os.path.splitext(master)[0]
        if stem == '' or not is_asset_id(stem):
            continue
        optimal = os.path.join(SFX_OPTIMAL_DIR, stem + '.mp3')
        if not os.path.isfile(optimal):
            missing.append({
                'asset_id': stem,
                'master_filename': master,
                'optimal': optimal,
            })
    return missing


def _delivery_has_variant(lower_names, variant, exts):
    variant = str(variant or '').strip().lower()
    if not variant:
        return False
    for name in lower_names:
        stem, ext = os.path.splitext(name)
        if stem == variant and ext in exts:
            return True
    return False


def missing_visual_deliveries(registry):
    """
    Registered visual assets missing required delivery files (list-check only).

    A non-empty delivery folder is not enough: an earlier encode can leave orphan
    folders after a registry wipe/re-register, so Check must require the role's
    expected variants (images) or a stream file (video).
    """
    _audio, visual, _sfx, _other = assets_by_kind(registry)
    image_exts = ('.png', '.jpg', '.jpeg', '.webp')
    missing = []
    for asset in visual:
        asset_id = str(asset.get('id') or asset.get('asset_id') or '').strip()
        if not is_asset_id(asset_id):
            continue
        folder = os.path.join(VISUAL_DELIVERY_DIR, asset_id)
        master_name = os.path.basename(str(asset.get('master_filename') or ''))
        media_type = str(asset.get('media_type') or '').strip().lower()
        if media_type not in ('image', 'video'):
            fmt = str(asset.get('master_format') or '').strip().lower()
            media_type = 'video' if fmt in ('mkv', 'mp4', 'webm') else 'image'

        row = {
            'asset_id': asset_id,
            'master_filename': master_name,
        }
        if not os.path.isdir(folder):
            missing.append(row)
            continue

        try:
            lower_names = [
                n.lower()
                for n in os.listdir(folder)
                if n not in ('.', '..') and os.path.isfile(os.path.join(folder, n))
            ]
        except Exception:
            lower_names = []
        if not lower_names:
            missing.append(row)
            continue

        if media_type == 'video':
            has_stream = any(
                n.startswith('standard-stream.') and n.endswith(('.mp4', '.webm'))
                for n in lower_names
            )
            if not has_stream:
                missing.append(row)
            continue

        role = str(asset.get('role') or 'unassigned').strip().lower() or 'unassigned'
        required = ('logo', 'thumb') if role == 'brand-logo' else ('thumb', 'card')
        if any(not _delivery_has_variant(lower_names, variant, image_exts) for variant in required):
            missing.append(row)
    return missing
