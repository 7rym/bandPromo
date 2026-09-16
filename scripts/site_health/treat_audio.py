# -*- coding: utf-8 -*-
"""Audio treatments: register disk masters in place (no mint / no copy)."""

from __future__ import print_function

import log
import registry as reg

try:
    from stopflag import stop_requested
except Exception:
    def stop_requested():
        return False


def _rebuild_audio_index():
    import files_index
    log.info('Rebuilding Files → Audio index...')
    count = files_index.rebuild_audio()
    log.info('INDEX_REBUILT:audio ({0} rows)'.format(count))
    return count


def treat_audio_register_in_place():
    """Register uncatalogued ast_* audio masters into the registry."""
    log.phase('treat:audio')
    registry, status = reg.load_registry()
    if status not in ('ok', 'missing'):
        log.info('Cannot register audio masters: registry {0}'.format(status))
        log.treat_result('audio_register_in_place', 'failed', 0)
        return 0, 1

    if status == 'missing':
        registry = reg.empty_registry()

    pending = reg.uncatalogued_audio_masters(registry)
    total = len(pending)
    if total == 0:
        log.info('No uncatalogued audio masters to register.')
        log.treat_result('audio_register_in_place', 'ok', 0)
        return 0, 0

    log.info('Registering {0} audio master(s) in place...'.format(total))
    fixed = 0
    failed = 0
    for index, item in enumerate(pending, 1):
        if stop_requested():
            log.info('Stop requested — finishing after current audio registrations written.')
            break
        name = item.get('master_filename') or ''
        asset_id = item.get('asset_id') or ''
        fmt = item.get('master_format') or 'mp3'
        log.progress('audio_register_in_place', index, total, name)
        try:
            existing = registry.get('assets', {}).get(asset_id)
            if isinstance(existing, dict) and str(existing.get('kind') or '') in ('', 'audio'):
                existing['master_filename'] = name
                existing['master_format'] = fmt
                registry['assets'][asset_id] = existing
                registry.setdefault('by_master_filename', {})[name] = asset_id
            else:
                reg.register_audio_master(registry, name, fmt, asset_id, '')
            fixed += 1
        except Exception as exc:
            failed += 1
            log.info('Failed {0}: {1}'.format(name, exc))

    try:
        reg.write_registry(registry)
    except Exception as exc:
        log.info('Could not write registry: {0}'.format(exc))
        log.treat_result('audio_register_in_place', 'failed', fixed)
        return fixed, failed + 1

    log.info('Registered {0} audio master(s); {1} failed.'.format(fixed, failed))
    log.treat_result('audio_register_in_place', 'ok' if failed == 0 else 'partial', fixed)
    if fixed > 0:
        try:
            _rebuild_audio_index()
        except Exception as exc:
            log.info('Files → Audio index rebuild failed: {0}'.format(exc))
            failed += 1
    return fixed, failed
