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


def run_triage(plan, deep=False, suppress_json_drift=False):
    log.phase('triage')
    deep = bool(deep)
    app_version = plan_mod.read_app_version()
    plan['app_version'] = app_version
    fp = host_fingerprint(app_version)
    plan['host_fingerprint'] = fp
    cache = load_fingerprint_cache()
    cache_hit = (
        not deep
        and str(cache.get('host_fingerprint') or '') == fp
        and str(cache.get('app_version') or '') == app_version
    )
    if deep:
        log.info('Host fingerprint: {0} (full check — cache ignored)'.format(fp))
        # Bust trust: do not compare against prior JSON digests.
        previous = {}
        cache = {}
    else:
        log.info('Host fingerprint: {0} ({1})'.format(
            fp, 'cache hit' if cache_hit else 'full environment check'
        ))
        previous = (
            cache.get('json_fingerprints')
            if isinstance(cache.get('json_fingerprints'), dict)
            else {}
        )

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
        disk_audio_other = [
            n for n in disk_audio if not reg.is_asset_id(os.path.splitext(n)[0])
        ]
        disk_originals = reg.list_audio_originals_on_disk()
        disk_visual = reg.list_visual_masters_on_disk()
        log.info('Disk audio masters: {0} (ast_* {1}), originals: {2}'.format(
            len(disk_audio), len(disk_audio_ast), len(disk_originals)
        ))
        log.info('Disk visual masters: {0}'.format(len(disk_visual)))
        if deep and disk_audio_other:
            log.info('Non-ast_* audio masters on disk: {0}'.format(len(disk_audio_other)))
            plan_mod.add_finding(
                plan, 'non_ast_audio_masters', 'attention',
                'Some audio masters are not named as asset ids', len(disk_audio_other),
                '',
                sample=disk_audio_other[:12],
                body=(
                    '{0} file(s) under media/audio/master are not ast_* masters. '
                    'Register-in-place cannot claim them automatically — see Activity.'
                ).format(len(disk_audio_other)),
            )

        pending_audio = reg.uncatalogued_audio_masters(registry)
        pending_visual = reg.uncatalogued_visual_masters(registry)

        # Hard safety net: never call an empty Files catalogue "healthy" when
        # masters exist on disk (covers id-format drift and non-ast_* names).
        if len(audio) == 0 and len(disk_audio) > 0 and not pending_audio:
            plan_mod.add_finding(
                plan, 'empty_audio_registry_with_disk_masters', 'critical',
                'Songs on disk are not in Files yet', len(disk_audio),
                'audio_register_in_place',
                sample=disk_audio[:12],
                body=(
                    'Registry has 0 audio assets but {0} master file(s) exist under '
                    'media/audio/master. Files → Audio will look empty.'
                ).format(len(disk_audio)),
            )

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

        # Bare register (id only, no tag fill) is a bad import — Files shows Untitled.
        if not pending_audio and len(audio) > 0:
            try:
                import audio_display
                bare = audio_display.incomplete_audio_masters(registry)
            except Exception as exc:
                log.info('Audio display completeness probe skipped: {0}'.format(exc))
                bare = []
            if bare:
                plan_mod.add_finding(
                    plan, 'audio_display_missing_tags', 'attention',
                    'Some tracks are in Files without tag data', len(bare),
                    'audio_fill_display_from_tags',
                    sample=[p.get('master_filename') for p in bare],
                    body=(
                        '{0} registered audio asset(s) have empty or Untitled display. '
                        'Treat reads embedded master tags into the registry '
                        '(registry ← master only; master tags are not rewritten).'
                    ).format(len(bare)),
                )

        if pending_visual:
            plan_mod.add_finding(
                plan, 'uncatalogued_visual_masters', 'attention',
                'Visual masters on disk are not registered', len(pending_visual),
                'visual_register_in_place',
                sample=[p['master_filename'] for p in pending_visual],
                body='Register existing visual masters in place (no copy).',
            )

        # Cheap delivery existence checks (no checksums).
        # Always probe registered assets — independent of register-pending findings.
        # (Orphan delivery folders after wipe/re-register must still be scored.)
        missing_audio = reg.missing_audio_deliverables(registry)
        if missing_audio:
            plan_mod.add_finding(
                plan, 'missing_audio_delivery', 'attention',
                'Some tracks are not stream-ready yet', len(missing_audio),
                'listener_delivery',
                sample=[m.get('asset_id') or m.get('master_filename') for m in missing_audio],
                body=(
                    '{0} registered audio asset(s) lack an optimal MP3 under media/audio/optimal.'
                ).format(len(missing_audio)),
            )
        missing_visual = reg.missing_visual_deliveries(registry)
        if missing_visual:
            plan_mod.add_finding(
                plan, 'missing_visual_delivery', 'attention',
                'Some visuals are missing delivery files', len(missing_visual),
                'listener_delivery',
                sample=[m.get('asset_id') for m in missing_visual],
                body=(
                    '{0} registered visual asset(s) lack required files under '
                    'media/visual/delivery (folder alone is not enough).'
                ).format(len(missing_visual)),
            )

        # Quick duplicate masters: same size → whole-file XXH3.
        try:
            import dedupe
            if not dedupe.xxhash_available():
                log.info(
                    'Duplicate master probe unavailable: xxhash not importable '
                    '(scripts/vendor bootstrap missing?).'
                )
                plan_mod.add_finding(
                    plan, 'dedupe_unavailable', 'attention',
                    'Duplicate master check could not run', 1,
                    '',
                    body=(
                        'xxhash is not available to Site health, so Quick/Full cannot '
                        'fingerprint masters. Delivery may still work if other stages '
                        'bootstrapped vendor separately — repair scripts/vendor or re-run '
                        'dependency bootstrap.'
                    ),
                )
            else:
                log.info('Scanning for duplicate masters (same size + file hash)...')
                file_clusters = dedupe.find_file_hash_clusters(registry)
                stats = getattr(dedupe.find_file_hash_clusters, 'last_stats', {}) or {}
                log.info(
                    'File-hash scan: {0} candidates, {1} size bucket(s), '
                    '{2} multi-member bucket(s), hashed {3} file(s).'.format(
                        stats.get('candidates', 0),
                        stats.get('size_buckets', 0),
                        stats.get('multi_size_buckets', 0),
                        stats.get('hashed', 0),
                    )
                )
                ref_index = dedupe.build_reference_index(registry)
                safe_file, conflict_file = dedupe.annotate_clusters(file_clusters, ref_index)
                remove_count = sum(len(c.get('remove_ids') or []) for c in safe_file)
                if safe_file:
                    plan_mod.add_finding(
                        plan, 'duplicate_masters_file', 'attention',
                        'Duplicate masters with identical file bytes', remove_count,
                        'dedupe_retarget_and_remove',
                        sample=dedupe.cluster_sample_lines(safe_file),
                        body=(
                            '{0} cluster(s) share the same file hash within a size bucket. '
                            'Treat keeps the campaign/playlist-linked asset and removes '
                            '{1} unreferenced clone(s).'
                        ).format(len(safe_file), remove_count),
                    )
                    log.items(
                        'Duplicate file-hash clusters (safe to remove)',
                        dedupe.cluster_sample_lines(safe_file, limit=20),
                    )
                if conflict_file:
                    plan_mod.add_finding(
                        plan, 'duplicate_masters_conflict', 'attention',
                        'Duplicate masters both linked to campaigns', len(conflict_file),
                        '',
                        sample=dedupe.cluster_sample_lines(conflict_file),
                        body=(
                            '{0} cluster(s) look identical on disk but more than one member '
                            'is linked to a campaign or playlist — not auto-removed.'
                        ).format(len(conflict_file)),
                    )
                    log.items(
                        'Duplicate file-hash conflicts (manual)',
                        dedupe.cluster_sample_lines(conflict_file, limit=20),
                    )
                if not safe_file and not conflict_file:
                    if int(stats.get('multi_size_buckets') or 0) == 0:
                        log.info('No same-size duplicate master files found.')
                    else:
                        log.info(
                            'Hashed {0} same-size candidate(s); no identical file hashes.'
                            .format(stats.get('hashed', 0))
                        )
        except Exception as exc:
            log.info('Duplicate file-hash probe skipped: {0}'.format(exc))

        if deep and len(audio) > 0:
            try:
                import files_index
                indexed = files_index.count_target_rows('audio')
            except Exception as exc:
                log.info('Files index probe skipped: {0}'.format(exc))
                indexed = -1
            if indexed >= 0 and indexed < len(audio):
                plan_mod.add_finding(
                    plan, 'files_index_audio_undercount', 'attention',
                    'Files → Audio index is behind the registry', len(audio) - indexed,
                    'files_index_rebuild',
                    sample=[],
                    body=(
                        'Files index has {0} audio row(s) but the registry lists {1}. '
                        'Treat can rebuild the index without minting masters.'
                    ).format(indexed, len(audio)),
                )
                log.info('Files index audio rows: {0} (registry {1})'.format(indexed, len(audio)))

    current_fps = json_fingerprints()
    changed = []
    for key, meta in current_fps.items():
        prev = previous.get(key) if isinstance(previous.get(key), dict) else {}
        if not meta.get('exists'):
            continue
        if prev.get('sha256') and prev.get('sha256') != meta.get('sha256'):
            changed.append(key)
    if deep:
        log.info('JSON fingerprints recomputed ({0} keys) — baseline rebuilt.'.format(
            len(current_fps)
        ))
    elif suppress_json_drift:
        if changed:
            log.info(
                'JSON changed during Treat (expected) — refreshing baseline: {0}'.format(
                    ', '.join(changed)
                )
            )
        else:
            log.info('JSON fingerprints unchanged.')
    elif changed and previous:
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
        'last_check_deep': bool(deep),
    })
    save_fingerprint_cache(cache)
    return plan
