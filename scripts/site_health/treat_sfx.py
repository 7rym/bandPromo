# -*- coding: utf-8 -*-
"""SFX treatments: register disk masters in place + rebuild listener deliverables."""

from __future__ import print_function

import log
import registry as reg
from php_stage import run_php_cli

try:
    from stopflag import stop_requested
except Exception:
    def stop_requested():
        return False


def _rebuild_sfx_indexes():
    import files_index
    log.info('Rebuilding Files → Sound effects indexes...')
    count = files_index.rebuild_sfx()
    log.info('INDEX_REBUILT:sfx ({0} rows)'.format(count))
    return count


def treat_sfx_register_in_place():
    """Register uncatalogued ast_* SFX masters into the registry."""
    log.phase('treat:sfx')
    registry, status = reg.load_registry()
    if status not in ('ok', 'missing'):
        log.info('Cannot register sound-effect masters: registry {0}'.format(status))
        log.treat_result('sfx_register_in_place', 'failed', 0)
        return 0, 1

    if status == 'missing':
        registry = reg.empty_registry()

    pending = reg.uncatalogued_sfx_masters(registry)
    total = len(pending)
    if total == 0:
        log.info('No uncatalogued sound-effect masters to register.')
        log.treat_result('sfx_register_in_place', 'ok', 0)
        return 0, 0

    log.info('Registering {0} sound-effect master(s) in place...'.format(total))
    log.items('Uncatalogued SFX masters', pending)
    fixed = 0
    failed = 0
    for index, item in enumerate(pending, 1):
        if stop_requested():
            log.info('Stop requested — finishing after current SFX registrations written.')
            break
        name = item.get('master_filename') or ''
        asset_id = item.get('asset_id') or ''
        fmt = item.get('master_format') or 'mp3'
        log.progress('sfx_register_in_place', index, total, name)
        try:
            existing = registry.get('assets', {}).get(asset_id)
            if isinstance(existing, dict) and str(existing.get('kind') or '') == 'sfx':
                existing['master_filename'] = name
                existing['master_format'] = fmt
                if not str(existing.get('original_filename') or '').strip():
                    existing['original_filename'] = (
                        reg._guess_sfx_original_filename(name) or name
                    )
                if not str(existing.get('brand_id') or '').strip():
                    existing['brand_id'] = reg._guess_sfx_brand_id(asset_id)
                registry['assets'][asset_id] = existing
                registry.setdefault('by_master_filename', {})[name] = asset_id
            else:
                reg.register_sfx_master(registry, name, fmt, asset_id)
            fixed += 1
        except Exception as exc:
            failed += 1
            log.info('Failed {0}: {1}'.format(name, exc))

    try:
        reg.write_registry(registry)
    except Exception as exc:
        log.info('Could not write registry: {0}'.format(exc))
        log.treat_result('sfx_register_in_place', 'failed', fixed)
        return fixed, failed + 1

    log.info('Registered {0} sound-effect master(s); {1} failed.'.format(fixed, failed))
    log.treat_result('sfx_register_in_place', 'ok' if failed == 0 else 'partial', fixed)
    if fixed > 0:
        try:
            _rebuild_sfx_indexes()
        except Exception as exc:
            log.info('Files → Sound effects index rebuild failed: {0}'.format(exc))
            failed += 1
    return fixed, failed


def treat_sfx(force=False):
    """
    Rebuild SFX optimal MP3s for registered sound effects.

    Encode rules live in biblioteca/sfx-helpers.php; this module owns the
    Status Treat phase and HEALTH_* logging via the PHP CLI.
    """
    log.phase('treat:sfx')
    if force:
        log.info('Force SFX delivery rebuild.')
    else:
        log.info('SFX delivery rebuild (stale or missing only).')
    env = {}
    if force:
        env['BANDPROMO_FORCE_SFX_DELIVERY'] = '1'
    ok, _code = run_php_cli(
        'biblioteca/build-sfx-delivery-cli.php',
        label='SFX delivery',
        stage='treat',
        env_extra=env,
    )
    log.treat_result('sfx_delivery', 'ok' if ok else 'failed', 0 if ok else 1)
    return ok
