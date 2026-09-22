# -*- coding: utf-8 -*-
"""
Site health media janitor — remove homeless derived / legacy / leftover intake under media/.

Policy (operator-locked):
- IGNORE everything under any ``icons`` path segment.
- Leftover files under ``original/`` (and legacy intake originals) are reclaimable
  (``leftover_original``) on Review → Apply — not ignored forever.
- Unknown ``ast_*`` masters are NOT deleted here (register-in-place Treat).
- Stray ``.zip`` under ``media/`` is reclaimable junk.
- Stale ``temp/media-intake`` files older than 24h are reclaimable (crashed uploads).
- Orphan delivery, unreferenced legacy leftovers, empty dirs, and non-media junk
  may be deleted on Review → Apply.

Probe is read-only; Treat mutates.
"""

from __future__ import print_function

import os
import re
import shutil

import log
import registry as reg
from paths import ROOT_DIR

try:
    from stopflag import stop_requested
except Exception:
    def stop_requested():
        return False

_ASSET_ID_RE = re.compile(r'^ast_[0-9A-HJKMNP-TV-Z]{20}$', re.IGNORECASE)
_JUNK_NAMES = frozenset({
    'desktop.ini',
    'thumbs.db',
    '.ds_store',
    '.gitkeep',
})
_MEDIA_INTAKE_MAX_AGE_SEC = 24 * 60 * 60
_LEGACY_INTAKE_ROOTS = (
    'img',
    'photo',
    'video',
    'special',
)
# Product + legacy durable intake dirs (files only; never masters).
_ORIGINAL_SCAN_RELS = (
    ('audio', os.path.join('audio', 'original')),
    ('visual', os.path.join('visual', 'original')),
    ('sfx', os.path.join('sfx', 'original')),
    ('visual', os.path.join('img', 'original')),
    ('visual', os.path.join('photo', 'original')),
    ('visual', os.path.join('video', 'original')),
    ('visual', 'special'),
)
_MEDIA_FILE_EXTS = frozenset({
    '.flac', '.mp3', '.wav', '.aif', '.aiff', '.m4a', '.aac', '.ogg',
    '.jpg', '.jpeg', '.png', '.webp', '.gif', '.mkv', '.mp4', '.webm',
})


def _safe_text(value):
    return str(value or '').strip()


def _rel_media(abs_path):
    abs_path = os.path.normpath(abs_path)
    media_root = os.path.normpath(os.path.join(ROOT_DIR, 'media'))
    if abs_path == media_root:
        return 'media'
    prefix = media_root + os.sep
    if abs_path.startswith(prefix):
        return ('media/' + abs_path[len(prefix):].replace('\\', '/')).rstrip('/')
    return abs_path.replace('\\', '/')


def _path_parts(abs_path):
    rel = _rel_media(abs_path)
    return [p for p in rel.split('/') if p]


def is_ignored_path(abs_path):
    """True when any path segment is icons (leave alone). Leftover original/ is reclaimable."""
    for part in _path_parts(abs_path):
        if part.lower() == 'icons':
            return True
    return False


def _filter_walk_dirnames(dirnames):
    """Mutate os.walk dirnames: skip icons and package scratch workdirs."""
    kept = []
    for name in dirnames:
        low = name.lower()
        if low == 'icons':
            continue
        if low.startswith('.bandpromo-'):
            continue
        kept.append(name)
    dirnames[:] = kept


def _is_asset_id(value):
    return bool(_ASSET_ID_RE.match(_safe_text(value)))


def _claimed_basenames(registry):
    """Basenames claimed by any registry asset (original or master filename)."""
    claimed = set()
    assets = registry.get('assets') if isinstance(registry, dict) else None
    if not isinstance(assets, dict):
        return claimed
    for asset in assets.values():
        if not isinstance(asset, dict):
            continue
        for key in ('original_filename', 'master_filename'):
            name = os.path.basename(_safe_text(asset.get(key)))
            if name:
                claimed.add(name.lower())
        asset_id = _safe_text(asset.get('id'))
        if asset_id:
            claimed.add(asset_id.lower())
    return claimed


def _original_basename_assets(registry):
    """Map original_filename.lower() -> list of asset dicts."""
    index = {}
    assets = registry.get('assets') if isinstance(registry, dict) else None
    if not isinstance(assets, dict):
        return index
    for asset in assets.values():
        if not isinstance(asset, dict):
            continue
        name = os.path.basename(_safe_text(asset.get('original_filename')))
        if not name:
            continue
        index.setdefault(name.lower(), []).append(asset)
    return index


def _asset_master_on_disk(asset):
    """True when the asset's master file exists (disposable intake may be reclaimed)."""
    if not isinstance(asset, dict):
        return False
    kind = _safe_text(asset.get('kind')).lower()
    master_name = os.path.basename(_safe_text(asset.get('master_filename')))
    asset_id = _safe_text(asset.get('id'))
    if kind == 'audio':
        if not master_name:
            return False
        path = os.path.join(ROOT_DIR, 'media', 'audio', 'master', master_name)
        return os.path.isfile(path)
    if kind == 'sfx':
        if not master_name:
            return False
        path = os.path.join(ROOT_DIR, 'media', 'sfx', 'master', master_name)
        return os.path.isfile(path)
    if kind == 'visual':
        fmt = _safe_text(asset.get('master_format')).lower()
        if not fmt and master_name:
            fmt = os.path.splitext(master_name)[1].lstrip('.').lower()
        if asset_id and fmt and _is_asset_id(asset_id):
            path = os.path.join(
                ROOT_DIR, 'media', 'visual', 'master',
                '{0}.{1}'.format(asset_id, fmt),
            )
            if os.path.isfile(path):
                return True
        if master_name:
            path = os.path.join(ROOT_DIR, 'media', 'visual', 'master', master_name)
            return os.path.isfile(path)
        return False
    return False


def _audio_ids(registry):
    ids = set()
    audio, _v, _s, _o = reg.assets_by_kind(registry)
    for entry in audio:
        aid = _safe_text(entry.get('id'))
        if _is_asset_id(aid):
            ids.add(aid.lower())
    return ids


def _visual_ids(registry):
    ids = set()
    _a, visual, _s, _o = reg.assets_by_kind(registry)
    for entry in visual:
        aid = _safe_text(entry.get('id'))
        if _is_asset_id(aid):
            ids.add(aid.lower())
    return ids


def _sfx_ids(registry):
    ids = set()
    _a, _v, sfx, _o = reg.assets_by_kind(registry)
    for entry in sfx:
        aid = _safe_text(entry.get('id'))
        if _is_asset_id(aid):
            ids.add(aid.lower())
    return ids


def _add_candidate(out, abs_path, kind, reason):
    if not abs_path or is_ignored_path(abs_path):
        return
    if not os.path.exists(abs_path):
        return
    rel = _rel_media(abs_path)
    for existing in out:
        if existing.get('path') == rel:
            return
    out.append({
        'path': rel,
        'abs': abs_path,
        'kind': kind,
        'reason': reason,
        'is_dir': os.path.isdir(abs_path),
    })


def _master_ids_on_disk(kind):
    """Asset ids that still have a master file on disk (protect delivery until register)."""
    if kind == 'audio':
        names = reg.list_audio_masters_on_disk()
    elif kind == 'visual':
        names = reg.list_visual_masters_on_disk()
    elif kind == 'sfx':
        names = reg.list_sfx_masters_on_disk()
    else:
        return set()
    ids = set()
    for name in names or []:
        stem = os.path.splitext(os.path.basename(_safe_text(name)))[0]
        if _is_asset_id(stem):
            ids.add(stem.lower())
    return ids


def _probe_optimal_mp3(out, folder, allowed_ids, kind, label, master_ids=None):
    if not os.path.isdir(folder):
        return
    if master_ids is None:
        master_ids = set()
    try:
        names = os.listdir(folder)
    except Exception:
        return
    for name in names:
        if name in ('.', '..'):
            continue
        path = os.path.join(folder, name)
        if is_ignored_path(path):
            continue
        if os.path.isdir(path):
            # unexpected nested dir under flat optimal — orphan if empty or junk later
            continue
        low = name.lower()
        if low in _JUNK_NAMES:
            _add_candidate(out, path, 'junk', 'non-media junk under {0}'.format(label))
            continue
        stem, ext = os.path.splitext(name)
        if ext.lower() != '.mp3':
            _add_candidate(out, path, 'orphan_delivery', 'unexpected file under {0}'.format(label))
            continue
        stem_l = stem.lower()
        if _is_asset_id(stem) and stem_l in allowed_ids:
            continue
        # Master still on disk → register-in-place, not janitor.
        if _is_asset_id(stem) and stem_l in master_ids:
            continue
        _add_candidate(
            out, path, 'orphan_delivery',
            'delivery MP3 with no matching {0} registry asset'.format(kind),
        )


def _probe_visual_delivery(out, registry):
    folder = os.path.join(ROOT_DIR, 'media', 'visual', 'delivery')
    if not os.path.isdir(folder):
        return
    visual_ids = _visual_ids(registry)
    master_ids = _master_ids_on_disk('visual')
    try:
        names = os.listdir(folder)
    except Exception:
        return
    for name in names:
        if name in ('.', '..'):
            continue
        path = os.path.join(folder, name)
        if is_ignored_path(path):
            continue
        if os.path.isfile(path):
            low = name.lower()
            if low in _JUNK_NAMES:
                _add_candidate(out, path, 'junk', 'non-media junk under visual/delivery')
            else:
                _add_candidate(out, path, 'orphan_delivery', 'loose file under visual/delivery')
            continue
        if not os.path.isdir(path):
            continue
        name_l = name.lower()
        if _is_asset_id(name) and name_l in visual_ids:
            continue
        if _is_asset_id(name) and name_l in master_ids:
            continue
        _add_candidate(
            out, path, 'orphan_delivery',
            'visual delivery folder with no matching visual registry asset',
        )


def _probe_legacy_trees(out, claimed):
    for root_name in _LEGACY_INTAKE_ROOTS:
        legacy_root = os.path.join(ROOT_DIR, 'media', root_name)
        if not os.path.isdir(legacy_root):
            continue
        for dirpath, dirnames, filenames in os.walk(legacy_root, topdown=True):
            # Leftover originals are handled by _probe_leftover_originals.
            if is_ignored_path(dirpath):
                dirnames[:] = []
                continue
            _filter_walk_dirnames(dirnames)
            dirnames[:] = [d for d in dirnames if d.lower() != 'original']
            for name in filenames:
                path = os.path.join(dirpath, name)
                if is_ignored_path(path):
                    continue
                # Skip files directly under */original/ (leftover probe owns those).
                parent = os.path.basename(os.path.normpath(dirpath)).lower()
                if parent == 'original':
                    continue
                low = name.lower()
                if low in _JUNK_NAMES:
                    _add_candidate(out, path, 'junk', 'non-media junk in legacy media/{0}'.format(root_name))
                    continue
                if low not in claimed:
                    _add_candidate(
                        out, path, 'legacy_orphan',
                        'legacy media/{0} file not claimed by registry'.format(root_name),
                    )


def _probe_legacy_optimal(out, claimed):
    """Stem-based legacy optimal/poster leftovers."""
    legacy_delivery = (
        ('img', 'optimal'),
        ('photo', 'optimal'),
        ('video', 'optimal'),
        ('video', 'poster'),
    )
    for family, bucket in legacy_delivery:
        folder = os.path.join(ROOT_DIR, 'media', family, bucket)
        if not os.path.isdir(folder):
            continue
        try:
            names = os.listdir(folder)
        except Exception:
            continue
        for name in names:
            path = os.path.join(folder, name)
            if is_ignored_path(path) or not os.path.isfile(path):
                continue
            low = name.lower()
            if low in _JUNK_NAMES:
                _add_candidate(out, path, 'junk', 'non-media junk in legacy {0}/{1}'.format(family, bucket))
                continue
            stem = os.path.splitext(name)[0].lower()
            # Claimed if basename or stem matches registry id / filename stem.
            claimed_hit = (
                low in claimed
                or stem in claimed
                or any(c.startswith(stem + '.') for c in claimed)
            )
            if not claimed_hit:
                _add_candidate(
                    out, path, 'orphan_delivery',
                    'legacy {0}/{1} file not claimed by registry'.format(family, bucket),
                )


def _probe_leftover_originals(out, registry):
    """
    Durable leftover intake under product/legacy original dirs.
    Unregistered files, or registry-linked originals whose master already exists.
    Never touches masters.
    """
    by_original = _original_basename_assets(registry)
    for _family, rel in _ORIGINAL_SCAN_RELS:
        folder = os.path.join(ROOT_DIR, 'media', rel)
        if not os.path.isdir(folder):
            continue
        try:
            names = os.listdir(folder)
        except Exception:
            continue
        for name in names:
            if name in ('.', '..'):
                continue
            path = os.path.join(folder, name)
            if is_ignored_path(path) or not os.path.isfile(path):
                continue
            low = name.lower()
            if low in _JUNK_NAMES:
                _add_candidate(out, path, 'junk', 'non-media junk under leftover intake')
                continue
            assets = by_original.get(low) or []
            if not assets:
                _add_candidate(
                    out, path, 'leftover_original',
                    'unregistered leftover intake under media/{0}'.format(rel.replace('\\', '/')),
                )
                continue
            if any(_asset_master_on_disk(asset) for asset in assets):
                _add_candidate(
                    out, path, 'leftover_original',
                    'disposable intake — master already on disk (media/{0})'.format(
                        rel.replace('\\', '/')
                    ),
                )


def _probe_stray_zips(out):
    """Stray .zip / .ZIP anywhere under media/ (icons and package workdirs skipped)."""
    media_root = os.path.join(ROOT_DIR, 'media')
    if not os.path.isdir(media_root):
        return
    for dirpath, dirnames, filenames in os.walk(media_root, topdown=True):
        if is_ignored_path(dirpath):
            dirnames[:] = []
            continue
        _filter_walk_dirnames(dirnames)
        for name in filenames:
            if not name.lower().endswith('.zip'):
                continue
            path = os.path.join(dirpath, name)
            if is_ignored_path(path):
                continue
            _add_candidate(
                out, path, 'stray_zip',
                'stray ZIP archive under media/ (not part of the catalogue)',
            )


def _probe_stale_media_intake(out):
    """Crash leftovers under temp/media-intake older than 24h."""
    intake_root = os.path.join(ROOT_DIR, 'temp', 'media-intake')
    if not os.path.isdir(intake_root):
        return
    now = 0
    try:
        import time
        now = time.time()
    except Exception:
        return
    for dirpath, dirnames, filenames in os.walk(intake_root, topdown=True):
        for name in filenames:
            path = os.path.join(dirpath, name)
            if not os.path.isfile(path):
                continue
            try:
                age = now - os.path.getmtime(path)
            except Exception:
                continue
            if age < _MEDIA_INTAKE_MAX_AGE_SEC:
                continue
            _add_candidate(
                out, path, 'stale_intake_temp',
                'temp/media-intake older than 24h (crashed or abandoned upload)',
            )


def _probe_media_junk(out):
    media_root = os.path.join(ROOT_DIR, 'media')
    if not os.path.isdir(media_root):
        return
    for dirpath, dirnames, filenames in os.walk(media_root, topdown=True):
        if is_ignored_path(dirpath):
            dirnames[:] = []
            continue
        _filter_walk_dirnames(dirnames)
        for name in filenames:
            path = os.path.join(dirpath, name)
            if is_ignored_path(path):
                continue
            if name.lower() in _JUNK_NAMES:
                _add_candidate(out, path, 'junk', 'non-media junk under media/')


def _probe_empty_dirs(out):
    """Empty directories under media/ (never icons; never media root)."""
    media_root = os.path.normpath(os.path.join(ROOT_DIR, 'media'))
    if not os.path.isdir(media_root):
        return
    empty = []
    for dirpath, dirnames, filenames in os.walk(media_root, topdown=False):
        dirpath = os.path.normpath(dirpath)
        if dirpath == media_root:
            continue
        if is_ignored_path(dirpath):
            continue
        try:
            entries = [e for e in os.listdir(dirpath) if e not in ('.', '..')]
        except Exception:
            continue
        # After file deletes, Treat re-runs empty pass; probe marks currently empty.
        if entries:
            continue
        empty.append(dirpath)
    for path in empty:
        _add_candidate(out, path, 'empty_dir', 'empty folder under media/')


def probe_janitor_targets(registry=None):
    """
    Return orphan/junk/empty/leftover-original candidates (read-only).
    Each item: path (media-relative), abs, kind, reason, is_dir.
    """
    if registry is None:
        registry, status = reg.load_registry()
        if status not in ('ok', 'missing'):
            return []
        if status == 'missing':
            registry = reg.empty_registry()

    out = []
    claimed = _claimed_basenames(registry)
    audio_masters = _master_ids_on_disk('audio')
    sfx_masters = _master_ids_on_disk('sfx')
    _probe_optimal_mp3(
        out,
        os.path.join(ROOT_DIR, 'media', 'audio', 'optimal'),
        _audio_ids(registry),
        'audio',
        'audio/optimal',
        master_ids=audio_masters,
    )
    _probe_optimal_mp3(
        out,
        os.path.join(ROOT_DIR, 'media', 'sfx', 'optimal'),
        _sfx_ids(registry),
        'sfx',
        'sfx/optimal',
        master_ids=sfx_masters,
    )
    _probe_visual_delivery(out, registry)
    _probe_legacy_optimal(out, claimed)
    _probe_legacy_trees(out, claimed)
    _probe_leftover_originals(out, registry)
    _probe_stray_zips(out)
    _probe_stale_media_intake(out)
    _probe_media_junk(out)
    _probe_empty_dirs(out)
    return out


def _clear_readonly(path):
    """Clear read-only bit so Windows can rmdir/unlink empty leftovers."""
    path = str(path or '')
    if not path or not os.path.exists(path):
        return
    try:
        import stat as stat_mod
        mode = os.stat(path).st_mode
        os.chmod(path, mode | stat_mod.S_IWRITE)
    except Exception:
        pass
    if os.name == 'nt':
        try:
            import ctypes
            # FILE_ATTRIBUTE_NORMAL — drop READONLY / hidden clutter that blocks rmdir.
            ctypes.windll.kernel32.SetFileAttributesW(ctypes.c_wchar_p(path), 0x80)
        except Exception:
            pass


def _rmtree_onerror(func, path, _exc_info):
    _clear_readonly(path)
    try:
        func(path)
    except Exception:
        raise


def _rm_path(abs_path, is_dir):
    _clear_readonly(abs_path)
    if is_dir:
        # Empty dirs: prefer rmdir; fall back to rmtree for stubborn trees.
        try:
            os.rmdir(abs_path)
            return
        except OSError:
            pass
        shutil.rmtree(abs_path, ignore_errors=False, onerror=_rmtree_onerror)
    else:
        os.remove(abs_path)


def apply_janitor(candidates=None, registry=None):
    """
    Delete probe targets. Returns (removed_count, failed_count).
    Re-probes empty dirs after file deletes.
    """
    log.phase('treat:media_janitor')
    if candidates is None:
        candidates = probe_janitor_targets(registry)

    files = [c for c in candidates if not c.get('is_dir')]
    dirs = [c for c in candidates if c.get('is_dir')]
    dirs.sort(key=lambda c: c.get('path') or '', reverse=True)
    ordered = files + dirs

    removed = 0
    failed = 0
    total = len(ordered)

    if total == 0:
        # Still sweep empties that may already exist.
        pass
    else:
        log.info('Pruning {0} homeless media item(s)...'.format(total))

    for index, item in enumerate(ordered, 1):
        if stop_requested():
            log.info('Stop requested during media janitor.')
            break
        rel = item.get('path') or ''
        abs_path = item.get('abs') or ''
        log.progress('media_janitor_prune', index, max(total, 1), rel)
        if not abs_path or is_ignored_path(abs_path):
            continue
        if not os.path.exists(abs_path):
            continue
        try:
            _rm_path(abs_path, bool(item.get('is_dir')))
            removed += 1
            log.info('Removed {0} ({1})'.format(rel, item.get('kind') or 'orphan'))
        except Exception as exc:
            failed += 1
            log.info('Failed to remove {0}: {1}'.format(rel, exc))
            if os.name == 'nt' and 'access is denied' in str(exc).lower():
                log.info(
                    '  Windows still has a lock or read-only flag on that path. '
                    'Close Explorer windows looking at media/, then Apply again.'
                )

    if not stop_requested():
        empties = []
        _probe_empty_dirs(empties)
        empties.sort(key=lambda c: c.get('path') or '', reverse=True)
        for item in empties:
            abs_path = item.get('abs') or ''
            if not abs_path or is_ignored_path(abs_path) or not os.path.isdir(abs_path):
                continue
            try:
                entries = [e for e in os.listdir(abs_path) if e not in ('.', '..')]
            except Exception:
                continue
            if entries:
                continue
            try:
                _clear_readonly(abs_path)
                os.rmdir(abs_path)
                removed += 1
                log.info('Removed empty {0}'.format(item.get('path') or abs_path))
            except Exception as exc:
                failed += 1
                log.info('Failed to remove empty {0}: {1}'.format(item.get('path'), exc))
                if os.name == 'nt' and 'access is denied' in str(exc).lower():
                    log.info(
                        '  Windows still has a lock or read-only flag on that path. '
                        'Close Explorer windows looking at media/, then Apply again.'
                    )

    if total == 0 and removed == 0:
        log.info('No media janitor targets.')
        log.treat_result('media_janitor_prune', 'ok', 0)
        return 0, 0

    log.info('Media janitor removed {0} item(s); {1} failed.'.format(removed, failed))
    log.treat_result(
        'media_janitor_prune',
        'ok' if failed == 0 else 'partial',
        removed,
    )
    return removed, failed
