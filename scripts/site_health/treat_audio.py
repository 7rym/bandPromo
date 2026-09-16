# -*- coding: utf-8 -*-
"""Audio treatments: register disk masters in place + fill display from tags."""

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
    """
    Register uncatalogued ast_* audio masters into the registry and fill
    display from embedded master tags (registry ← master only).
    """
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
    fixed = 0
    failed = 0

    if total == 0:
        log.info('No uncatalogued audio masters to register.')
        log.treat_result('audio_register_in_place', 'ok', 0)
    else:
        log.info('Registering {0} audio master(s) in place...'.format(total))
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
                    if not str(existing.get('original_filename') or '').strip():
                        existing['original_filename'] = name
                    registry['assets'][asset_id] = existing
                    registry.setdefault('by_master_filename', {})[name] = asset_id
                    try:
                        import audio_display
                        audio_display.fill_entry_from_master_tags(existing)
                    except Exception:
                        pass
                else:
                    reg.register_audio_master(registry, name, fmt, asset_id, '')
                fixed += 1
            except Exception as exc:
                failed += 1
                log.info('Failed {0}: {1}'.format(name, exc))

        log.info('Registered {0} audio master(s); {1} failed.'.format(fixed, failed))
        log.treat_result('audio_register_in_place', 'ok' if failed == 0 else 'partial', fixed)

    # Always heal bare "Untitled" rows left by earlier bad imports (same Treat).
    import audio_display
    fill_fixed, fill_failed = audio_display.treat_audio_fill_display_from_tags(registry)
    failed += fill_failed

    if fixed > 0 or fill_fixed > 0:
        try:
            reg.write_registry(registry)
        except Exception as exc:
            log.info('Could not write registry: {0}'.format(exc))
            log.treat_result('audio_register_in_place', 'failed', fixed)
            return fixed, failed + 1
        try:
            _rebuild_audio_index()
        except Exception as exc:
            log.info('Files → Audio index rebuild failed: {0}'.format(exc))
            failed += 1
    return fixed + fill_fixed, failed


def treat_audio_fill_display_from_tags():
    """Standalone treatment id for incomplete display without new registers."""
    return treat_audio_register_in_place()
