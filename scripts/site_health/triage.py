# -*- coding: utf-8 -*-
"""Cheap triage checks for site health."""

from __future__ import print_function

import hashlib
import json
import os
import platform
import shutil
import sys

import log
import plan as plan_mod
import registry as reg
from paths import (
    FINGERPRINT_CACHE_PATH,
    JSON_FINGERPRINT_PATHS,
    ROOT_DIR,
)


def _file_sha256(path):
    if not os.path.isfile(path):
        return ''
    hasher = hashlib.sha256()
    try:
        with open(path, 'rb') as handle:
            while True:
                chunk = handle.read(1024 * 256)
                if not chunk:
                    break
                hasher.update(chunk)
    except Exception:
        return ''
    return hasher.hexdigest()


def host_fingerprint(app_version):
    parts = [
        platform.system(),
        platform.release(),
        platform.machine(),
        sys.version.split()[0],
        app_version or '',
        ROOT_DIR,
    ]
    raw = '|'.join(parts)
    return hashlib.sha256(raw.encode('utf-8', 'replace')).hexdigest()[:16]


def ffmpeg_available():
    bundled = os.path.join(ROOT_DIR, 'scripts', 'bin', 'ffmpeg')
    if os.name == 'nt':
        bundled += '.exe'
    if os.path.isfile(bundled):
        return bundled
    return shutil.which('ffmpeg') or ''


def load_fingerprint_cache():
    if not os.path.isfile(FINGERPRINT_CACHE_PATH):
        return {}
    try:
        with open(FINGERPRINT_CACHE_PATH, 'r', encoding='utf-8') as handle:
            payload = json.load(handle)
        return payload if isinstance(payload, dict) else {}
    except Exception:
        return {}


def save_fingerprint_cache(cache):
    folder = os.path.dirname(FINGERPRINT_CACHE_PATH)
    if not os.path.isdir(folder):
        os.makedirs(folder)
    tmp = FINGERPRINT_CACHE_PATH + '.tmp'
    with open(tmp, 'w', encoding='utf-8', newline='\n') as handle:
        json.dump(cache, handle, ensure_ascii=False, indent=2)
        handle.write('\n')
    if os.path.isfile(FINGERPRINT_CACHE_PATH):
        os.replace(tmp, FINGERPRINT_CACHE_PATH)
    else:
        os.rename(tmp, FINGERPRINT_CACHE_PATH)


def json_fingerprints():
    result = {}
    for key, path in JSON_FINGERPRINT_PATHS:
        if not os.path.isfile(path):
            result[key] = {'path': path, 'exists': False, 'sha256': ''}
            continue
        result[key] = {
            'path': path,
            'exists': True,
            'sha256': _file_sha256(path),
            'size': os.path.getsize(path),
        }
    return result


def run_triage(plan):
    log.phase('triage')
    app_version = plan_mod.read_app_version()
    plan['app_version'] = app_version
    fp = host_fingerprint(app_version)
    plan['host_fingerprint'] = fp
    cache = load_fingerprint_cache()
    cache_hit = (
        str(cache.get('host_fingerprint') or '') == fp
        and str(cache.get('app_version') or '') == app_version
    )
    log.info('Host fingerprint: {0} ({1})'.format(
        fp, 'cache hit' if cache_hit else 'full environment check'
    ))

    py_ok = sys.version_info >= (3, 6)
    ffmpeg = ffmpeg_available()
    log.info('Python: {0}'.format(sys.version.split()[0]))
    if not py_ok:
        plan_mod.add_finding(
            plan, 'python_too_old', 'critical',
            'Python is too old for the health engine', 1,
            '', body='Need CPython 3.6.9 or newer on this host.'
        )
    if ffmpeg:
        log.info('ffmpeg: {0}'.format(ffmpeg))
    else:
        plan_mod.add_finding(
            plan, 'ffmpeg_missing', 'attention',
            'ffmpeg not found', 1,
            '',
            body='Delivery treatments need ffmpeg. Use Environment to locate or install it.',
        )

    registry, status = reg.load_registry()
    if status == 'missing':
        plan_mod.add_finding(
            plan, 'registry_missing', 'critical',
            'Asset registry is missing', 1,
            'audio_register_in_place',
            body='data/assets/registry.json was not found.',
        )
        log.info('Registry: missing')
        disk_audio_ast = [
            n for n in reg.list_audio_masters_on_disk()
            if reg.is_asset_id(os.path.splitext(n)[0])
        ]
        if disk_audio_ast:
            plan_mod.add_finding(
                plan, 'uncatalogued_audio_masters', 'critical',
                'Songs on disk are not in Files yet', len(disk_audio_ast),
                'audio_register_in_place',
                sample=disk_audio_ast,
                body='Registry missing while {0} audio master(s) exist on disk.'.format(
                    len(disk_audio_ast)
                ),
            )
    elif status != 'ok':
        plan_mod.add_finding(
            plan, 'registry_unreadable', 'critical',
            'Asset registry could not be read', 1,
            '',
            body=status,
        )
        log.info('Registry: {0}'.format(status))
    else:
        audio, visual, sfx, other = reg.assets_by_kind(registry)
        log.info('Registry: {0} audio, {1} visual, {2} sfx, {3} other'.format(
            len(audio), len(visual), len(sfx), len(other)
        ))

        disk_audio = reg.list_audio_masters_on_disk()
        disk_audio_ast = [
            n for n in disk_audio if reg.is_asset_id(os.path.splitext(n)[0])
        ]
        disk_originals = reg.list_audio_originals_on_disk()
        disk_visual = reg.list_visual_masters_on_disk()
        log.info('Disk audio masters: {0} (ast_* {1}), originals: {2}'.format(
            len(disk_audio), len(disk_audio_ast), len(disk_originals)
        ))
        log.info('Disk visual masters: {0}'.format(len(disk_visual)))

        pending_audio = reg.uncatalogued_audio_masters(registry)
        pending_visual = reg.uncatalogued_visual_masters(registry)
        if pending_audio:
            severity = 'critical' if len(audio) == 0 else 'attention'
            plan_mod.add_finding(
                plan, 'uncatalogued_audio_masters', severity,
                'Songs on disk are not in Files yet', len(pending_audio),
                'audio_register_in_place',
                sample=[p['master_filename'] for p in pending_audio],
                body=(
                    '{0} audio master(s) on disk are missing from the registry. '
                    'Files → Audio will look empty until they are registered in place.'
                ).format(len(pending_audio)),
            )
        if pending_visual:
            plan_mod.add_finding(
                plan, 'uncatalogued_visual_masters', 'attention',
                'Visual masters on disk are not registered', len(pending_visual),
                'visual_register_in_place',
                sample=[p['master_filename'] for p in pending_visual],
                body='Register existing visual masters in place (no copy).',
            )

    current_fps = json_fingerprints()
    previous = cache.get('json_fingerprints') if isinstance(cache.get('json_fingerprints'), dict) else {}
    changed = []
    for key, meta in current_fps.items():
        prev = previous.get(key) if isinstance(previous.get(key), dict) else {}
        if not meta.get('exists'):
            continue
        if prev.get('sha256') and prev.get('sha256') != meta.get('sha256'):
            changed.append(key)
    if changed and previous:
        plan_mod.add_finding(
            plan, 'json_changed', 'attention',
            'Site data files changed since last health check', len(changed),
            'container_links',
            sample=changed,
            body='Changed: {0}'.format(', '.join(changed)),
        )
        log.info('JSON changed since last check: {0}'.format(', '.join(changed)))
    elif not previous:
        log.info('JSON fingerprint cache cold — storing baseline (no change finding).')
    else:
        log.info('JSON fingerprints unchanged.')

    cache.update({
        'host_fingerprint': fp,
        'app_version': app_version,
        'json_fingerprints': current_fps,
    })
    save_fingerprint_cache(cache)
    return plan
