# -*- coding: utf-8 -*-
"""
Rebuild Files index rows in data/media-library-state.json (registry-backed).

Mirrors bandpromo_media_files_index_rebuild_target for audio and visual
targets without orphan disk walks — enough for site-health Treat after
register-in-place. PHP CLI remains a fallback.
"""

from __future__ import print_function

import json
import os
import time
from datetime import datetime

from paths import (
    AUDIO_EXTS,
    AUDIO_MASTER_DIR,
    AUDIO_OPTIMAL_DIR,
    AUDIO_ORIGINAL_DIR,
    MEDIA_LIBRARY_LOCK_PATH,
    MEDIA_LIBRARY_STATE_PATH,
    VISUAL_DELIVERY_DIR,
    VISUAL_EXTS,
    VISUAL_MASTER_DIR,
)
import registry as reg

try:
    import log
except Exception:
    class _Log(object):
        def info(self, message):
            pass

    log = _Log()


AUDIO_TARGETS = ('audio',)
VISUAL_TARGETS = ('illustrations', 'photos', 'video')
ALL_TREAT_TARGETS = AUDIO_TARGETS + VISUAL_TARGETS


class IndexLock(object):
    """Exclusive lock matching PHP flock on media-library-state.lock."""

    def __init__(self, path):
        self.path = path
        self.handle = None

    def __enter__(self):
        folder = os.path.dirname(self.path)
        if not os.path.isdir(folder):
            os.makedirs(folder)
        self.handle = open(self.path, 'a+b')
        if os.name == 'nt':
            import msvcrt
            self.handle.seek(0)
            if self.handle.read(1) == b'':
                self.handle.write(b'0')
                self.handle.flush()
            self.handle.seek(0)
            while True:
                try:
                    msvcrt.locking(self.handle.fileno(), msvcrt.LK_LOCK, 1)
                    break
                except (OSError, IOError):
                    time.sleep(0.05)
        else:
            import fcntl
            fcntl.flock(self.handle.fileno(), fcntl.LOCK_EX)
        return self

    def __exit__(self, exc_type, exc, tb):
        if self.handle is None:
            return False
        try:
            if os.name == 'nt':
                import msvcrt
                self.handle.seek(0)
                msvcrt.locking(self.handle.fileno(), msvcrt.LK_UNLCK, 1)
            else:
                import fcntl
                fcntl.flock(self.handle.fileno(), fcntl.LOCK_UN)
        except Exception:
            pass
        try:
            self.handle.close()
        except Exception:
            pass
        self.handle = None
        return False


def _utc_now_iso():
    return datetime.utcnow().strftime('%Y-%m-%dT%H:%M:%S+00:00')


def _index_key(target, filename):
    return '{0}/{1}'.format(target, os.path.basename(str(filename or '').strip()))


def _is_audio_name(filename):
    ext = os.path.splitext(filename)[1].lower()
    return ext in AUDIO_EXTS


def _is_visual_name(filename):
    ext = os.path.splitext(filename)[1].lower()
    return ext in VISUAL_EXTS


def _origin_for(filename, existing_entry):
    if isinstance(existing_entry, dict):
        prior = str(existing_entry.get('origin') or '').strip()
        if prior == 'user-upload':
            return 'user-upload'
    name = os.path.basename(str(filename or ''))
    if name.startswith('bandPromo_'):
        return 'bundled-placeholder'
    return 'user-upload'


def _normalize_intake(bucket):
    bucket = str(bucket or '').strip().lower()
    aliases = {
        'illustrations': 'img',
        'images': 'img',
        'img': 'img',
        'photos': 'photo',
        'photo': 'photo',
        'video': 'video',
        'videos': 'video',
        'special': 'special',
    }
    return aliases.get(bucket, '')


def _target_for_visual_asset(asset):
    media_type = str(asset.get('media_type') or 'image').strip().lower()
    intake = _normalize_intake(asset.get('intake_bucket'))
    mapped = ''
    if intake == 'img':
        mapped = 'illustrations'
    elif intake == 'photo':
        mapped = 'photos'
    elif intake == 'video':
        mapped = 'video'
    elif intake == 'special':
        mapped = ''
    else:
        mapped = intake
    if mapped == '' and media_type == 'image':
        mapped = 'illustrations'
    if mapped == '' and media_type == 'video':
        mapped = 'video'
    return mapped


def _load_state():
    default = {'hidden': {}, 'assets': {}, 'files': {}}
    if not os.path.isfile(MEDIA_LIBRARY_STATE_PATH):
        return default
    try:
        with open(MEDIA_LIBRARY_STATE_PATH, 'r', encoding='utf-8') as handle:
            payload = json.load(handle)
    except Exception:
        return default
    if not isinstance(payload, dict):
        return default
    state = {
        'hidden': payload.get('hidden') if isinstance(payload.get('hidden'), (dict, list)) else {},
        'assets': payload.get('assets') if isinstance(payload.get('assets'), dict) else {},
        'files': payload.get('files') if isinstance(payload.get('files'), dict) else {},
    }
    return state


def _save_state(state):
    folder = os.path.dirname(MEDIA_LIBRARY_STATE_PATH)
    if not os.path.isdir(folder):
        os.makedirs(folder)
    tmp = MEDIA_LIBRARY_STATE_PATH + '.tmp'
    with open(tmp, 'w', encoding='utf-8', newline='\n') as handle:
        json.dump(state, handle, ensure_ascii=False, indent=4)
        handle.write('\n')
    if os.path.isfile(MEDIA_LIBRARY_STATE_PATH):
        os.replace(tmp, MEDIA_LIBRARY_STATE_PATH)
    else:
        os.rename(tmp, MEDIA_LIBRARY_STATE_PATH)


def _audio_pool_ready(listing_name):
    stem = os.path.splitext(listing_name)[0]
    return os.path.isfile(os.path.join(AUDIO_OPTIMAL_DIR, stem + '.mp3'))


def _visual_pool_ready(asset_id, media_type, target):
    # illustrations always true in PHP sync_file; photos/video check delivery.
    if target == 'illustrations':
        return True
    if not reg.is_asset_id(asset_id):
        return False
    folder = os.path.join(VISUAL_DELIVERY_DIR, asset_id)
    if not os.path.isdir(folder):
        return False
    if media_type == 'video':
        return (
            os.path.isfile(os.path.join(folder, 'poster.jpg'))
            and os.path.isfile(os.path.join(folder, 'standard-stream.mp4'))
        )
    for variant in ('thumb', 'card'):
        found = False
        for ext in ('webp', 'png', 'jpg', 'jpeg'):
            if os.path.isfile(os.path.join(folder, '{0}.{1}'.format(variant, ext))):
                found = True
                break
        if not found:
            return False
    return True


def _resolve_audio_source(asset):
    master = os.path.basename(str(asset.get('master_filename') or '').strip())
    original = os.path.basename(str(asset.get('original_filename') or '').strip())
    listing = master or original
    if listing == '' or not _is_audio_name(listing):
        return None
    if master:
        path = os.path.join(AUDIO_MASTER_DIR, master)
        if os.path.isfile(path):
            return {
                'path': path,
                'name': master,
                'original_filename': original,
                'master_filename': master,
            }
    if original:
        path = os.path.join(AUDIO_ORIGINAL_DIR, original)
        if os.path.isfile(path):
            return {
                'path': path,
                'name': original,
                'original_filename': original,
                'master_filename': master,
            }
        # Masters-only: listing may be original label but bytes live as master.
        if master == '' and reg.is_asset_id(os.path.splitext(original)[0]):
            # unlikely
            pass
    if master == '' and original:
        # Prefer master path named after asset id + ext from original
        asset_id = str(asset.get('id') or '').strip()
        if reg.is_asset_id(asset_id):
            guess = asset_id + os.path.splitext(original)[1].lower()
            path = os.path.join(AUDIO_MASTER_DIR, guess)
            if os.path.isfile(path):
                return {
                    'path': path,
                    'name': guess,
                    'original_filename': original,
                    'master_filename': guess,
                }
    return None


def _resolve_visual_source(asset):
    master = os.path.basename(str(asset.get('master_filename') or '').strip())
    original = os.path.basename(str(asset.get('original_filename') or '').strip())
    listing = master or original
    if listing == '' or not _is_visual_name(listing):
        return None
    if master:
        path = os.path.join(VISUAL_MASTER_DIR, master)
        if os.path.isfile(path):
            return {
                'path': path,
                'name': master,
                'original_filename': original,
            }
    return None


def _build_audio_entry(asset, origin_snapshot):
    source = _resolve_audio_source(asset)
    if source is None:
        return None
    listing = source['name']
    path = source['path']
    try:
        size = int(os.path.getsize(path))
        modified = int(os.path.getmtime(path))
    except Exception:
        return None
    original_label = source.get('original_filename') or ''
    extension = os.path.splitext(original_label or listing)[1].lower().lstrip('.')
    key = _index_key('audio', listing)
    existing = origin_snapshot.get(key) if isinstance(origin_snapshot.get(key), dict) else None
    origin_name = original_label or listing
    entry = {
        'target': 'audio',
        'name': listing,
        'size': size,
        'modified': modified,
        'origin': _origin_for(origin_name, existing),
        'original_format': extension,
        'original_filename': original_label,
        'pool_ready': _audio_pool_ready(listing),
        'indexed_at': _utc_now_iso(),
    }
    master_name = source.get('master_filename') or ''
    master_path = os.path.join(AUDIO_MASTER_DIR, master_name) if master_name else ''
    master_exists = master_name != '' and os.path.isfile(master_path)
    entry['audio_master'] = {
        'exists': master_exists,
        'filename': master_name if master_exists else '',
        'editable': master_exists or extension in ('flac', 'mp3', 'wav'),
        'needs_materialize': (not master_exists) and extension in ('flac', 'mp3', 'wav'),
        'size': int(os.path.getsize(master_path)) if master_exists else 0,
        'modified': int(os.path.getmtime(master_path)) if master_exists else 0,
    }
    return entry


def _build_visual_entry(asset, target, origin_snapshot):
    source = _resolve_visual_source(asset)
    if source is None:
        return None
    listing = source['name']
    path = source['path']
    try:
        size = int(os.path.getsize(path))
        modified = int(os.path.getmtime(path))
    except Exception:
        return None
    original_label = source.get('original_filename') or ''
    extension = os.path.splitext(original_label or listing)[1].lower().lstrip('.')
    key = _index_key(target, listing)
    existing = origin_snapshot.get(key) if isinstance(origin_snapshot.get(key), dict) else None
    asset_id = str(asset.get('id') or '').strip()
    media_type = str(asset.get('media_type') or 'image').strip().lower()
    entry = {
        'target': target,
        'name': listing,
        'size': size,
        'modified': modified,
        'origin': _origin_for(original_label or listing, existing),
        'original_format': extension,
        'original_filename': original_label,
        'pool_ready': _visual_pool_ready(asset_id, media_type, target),
        'indexed_at': _utc_now_iso(),
    }
    if target == 'video':
        entry['video_meta'] = {}
        entry['poster_url'] = ''
        entry['preview_url'] = ''
        entry['delivery_pending'] = not _visual_pool_ready(asset_id, 'video', 'video')
    return entry


def rebuild_target(target, registry=None):
    """
    Strip and rebuild one Files target from the asset registry.
    Returns number of rows written.
    """
    target = str(target or '').strip().lower()
    if target not in ALL_TREAT_TARGETS:
        raise ValueError('Unsupported Files index target: {0}'.format(target))

    if registry is None:
        registry, status = reg.load_registry()
        if status not in ('ok', 'missing'):
            log.info('Files index rebuild skipped: registry {0}'.format(status))
            return 0

    with IndexLock(MEDIA_LIBRARY_LOCK_PATH):
        state = _load_state()
        origin_snapshot = dict(state.get('files') or {})
        files = dict(origin_snapshot)
        # Always strip this target then rebuild from registry so deleted masters
        # (e.g. after dedupe) do not leave broken Unused / broken-thumb rows.
        prefix = target + '/'
        for key in list(files.keys()):
            if str(key).startswith(prefix):
                del files[key]

        count = 0
        assets = registry.get('assets') if isinstance(registry.get('assets'), dict) else {}
        for asset in assets.values():
            if not isinstance(asset, dict):
                continue
            if target == 'audio':
                if str(asset.get('kind') or '') != 'audio':
                    continue
                entry = _build_audio_entry(asset, origin_snapshot)
            else:
                if str(asset.get('kind') or '') != 'visual':
                    continue
                mapped = _target_for_visual_asset(asset)
                if mapped != target:
                    continue
                entry = _build_visual_entry(asset, target, origin_snapshot)
            if entry is None:
                continue
            listing = entry['name']
            key = _index_key(target, listing)
            original_label = os.path.basename(str(entry.get('original_filename') or ''))
            if original_label and original_label != listing:
                files.pop(_index_key(target, original_label), None)
            files[key] = entry
            count += 1

        state['files'] = files
        _save_state(state)
        return count


def rebuild_targets(targets):
    """Rebuild multiple targets; returns {target: count}."""
    registry, status = reg.load_registry()
    if status not in ('ok', 'missing'):
        log.info('Files index rebuild skipped: registry {0}'.format(status))
        return {}
    if status == 'missing':
        registry = reg.empty_registry()
    results = {}
    for target in targets:
        results[target] = rebuild_target(target, registry=registry)
    return results


def rebuild_audio():
    return rebuild_target('audio')


def count_target_rows(target):
    """Count Files index rows for one target (read-only)."""
    target = str(target or '').strip().lower()
    state = _load_state()
    files = state.get('files') if isinstance(state.get('files'), dict) else {}
    prefix = target + '/'
    return sum(1 for key in files if str(key).startswith(prefix))


def rebuild_visual():
    return rebuild_targets(['illustrations', 'photos', 'video'])
