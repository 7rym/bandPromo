# -*- coding: utf-8 -*-
"""Audio treatments: register disk masters in place (no mint / no copy)."""

from __future__ import print_function

import os
import subprocess
import sys

import log
import registry as reg

try:
    from stopflag import stop_requested
except Exception:
    def stop_requested():
        return False

SCRIPTS_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ROOT_DIR = os.path.dirname(SCRIPTS_DIR)
if SCRIPTS_DIR not in sys.path:
    sys.path.insert(0, SCRIPTS_DIR)


def _rebuild_audio_index():
    try:
        import files_index
        log.info('Rebuilding Files → Audio index...')
        count = files_index.rebuild_audio()
        log.info('INDEX_REBUILT:audio ({0} rows)'.format(count))
        return
    except Exception as exc:
        log.info('Python Files index rebuild failed ({0}); trying PHP CLI.'.format(exc))

    try:
        from php_cli import resolve_php_cli
    except Exception as exc:
        log.info('Audio index rebuild skipped: {0}'.format(exc))
        return
    php = resolve_php_cli()
    cli = os.path.join(ROOT_DIR, 'biblioteca', 'site-health-rebuild-index-cli.php')
    if not php or not os.path.isfile(cli):
        log.info('Skipped Files index rebuild (PHP CLI or script missing).')
        return
    log.info('Rebuilding Files → Audio index via PHP...')
    try:
        proc = subprocess.Popen(
            [php, cli, 'audio'],
            cwd=ROOT_DIR,
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            universal_newlines=True,
        )
        out, _unused = proc.communicate()
        if out:
            for line in str(out).splitlines():
                if line.strip():
                    log.info(line.strip())
        if proc.returncode != 0:
            log.info('Audio index rebuild exited {0}'.format(proc.returncode))
    except Exception as exc:
        log.info('Audio index rebuild skipped: {0}'.format(exc))


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
        _rebuild_audio_index()
    return fixed, failed
