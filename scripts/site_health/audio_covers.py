# -*- coding: utf-8 -*-
"""
Extract embedded audio cover art into Visual masters (Site health Treat).

When a registered track has no display.cover but the master carries APIC /
FLAC pictures, Treat writes media/visual/original/embedded-*.{jpg,png},
registers a track-cover Visual asset, and links display.cover (ast_*).

Reuses makePlaylists extract helpers (hash-match reuse, no stem sidecars).
Never strips operator-assigned covers. Never writes empty registry → master.
"""

from __future__ import print_function

import contextlib
import io
import os
import sys

import log
import registry as reg
from paths import AUDIO_MASTER_DIR, ROOT_DIR, SCRIPTS_DIR

try:
    from stopflag import stop_requested
except Exception:
    def stop_requested():
        return False

if SCRIPTS_DIR not in sys.path:
    sys.path.insert(0, SCRIPTS_DIR)


def _safe_text(value):
    return str(value or '').strip()


def _asset_has_cover_ref(entry):
    display = entry.get('display') if isinstance(entry.get('display'), dict) else {}
    return _safe_text(display.get('cover')) != ''


def _master_path(master_filename):
    name = os.path.basename(_safe_text(master_filename))
    if not name:
        return ''
    path = os.path.join(AUDIO_MASTER_DIR, name)
    if os.path.isfile(path):
        return path
    return ''


def _embedded_cover_present(master_filename):
    try:
        import audio_display
    except Exception:
        return False
    inspect = audio_display.inspect_master_file(master_filename)
    if not isinstance(inspect, dict):
        return False
    return bool(inspect.get('embedded_cover_present'))


def pending_cover_extract(registry):
    """
    Registered audio with empty display.cover and embedded artwork on the master.
    """
    audio, _v, _s, _o = reg.assets_by_kind(registry)
    pending = []
    for entry in audio:
        if not isinstance(entry, dict):
            continue
        if _asset_has_cover_ref(entry):
            continue
        master = os.path.basename(_safe_text(entry.get('master_filename')))
        if not master:
            continue
        if not _master_path(master):
            continue
        if not _embedded_cover_present(master):
            continue
        pending.append({
            'asset_id': _safe_text(entry.get('id')),
            'master_filename': master,
        })
    return pending


def _load_make_playlists():
    import makePlaylists as mp
    return mp


def _extract_one(master_path):
    """
    Call makePlaylists.extract_embedded_cover_to_visual with stdout captured
    so Activity stays on HEALTH_* via log.py (not raw print spam).
    """
    mp = _load_make_playlists()
    buf = io.StringIO()
    with contextlib.redirect_stdout(buf):
        cover_ref = mp.extract_embedded_cover_to_visual(master_path)
    for line in buf.getvalue().splitlines():
        text = line.strip()
        if not text:
            continue
        # Drop glyph prefixes from legacy prints; keep the substance.
        for prefix in ('✓ ', '⚠ ', '✗ '):
            if text.startswith(prefix):
                text = text[len(prefix):].strip()
                break
        if text:
            log.info(text)
    return cover_ref


def treat_audio_extract_covers():
    """
    Extract embedded covers for tracks that still lack display.cover.
    makePlaylists writes registry cover links; we rebuild Files indexes after.
    """
    log.phase('treat:audio_covers')
    registry, status = reg.load_registry()
    if status not in ('ok', 'missing'):
        log.info('Cannot extract covers: registry {0}'.format(status))
        log.treat_result('audio_extract_covers', 'failed', 0)
        return 0, 1
    if status == 'missing':
        registry = reg.empty_registry()

    pending = pending_cover_extract(registry)
    total = len(pending)
    if total == 0:
        log.info('No tracks need embedded cover extract.')
        log.treat_result('audio_extract_covers', 'ok', 0)
        return 0, 0

    log.info('Extracting embedded covers for {0} track(s)...'.format(total))
    fixed = 0
    failed = 0
    for index, item in enumerate(pending, 1):
        if stop_requested():
            log.info('Stop requested during cover extract.')
            break
        name = item.get('master_filename') or ''
        path = _master_path(name)
        log.progress('audio_extract_covers', index, total, name)
        if not path:
            failed += 1
            log.info('Missing master for cover extract: {0}'.format(name))
            continue
        try:
            cover_ref = _extract_one(path)
            if cover_ref:
                fixed += 1
                log.info('Cover linked for {0}: {1}'.format(name, cover_ref))
            else:
                failed += 1
                log.info('No cover extracted for {0}'.format(name))
        except Exception as exc:
            failed += 1
            log.info('Cover extract failed for {0}: {1}'.format(name, exc))

    if fixed > 0:
        try:
            import files_index
            log.info('Rebuilding Files indexes after cover extract...')
            files_index.rebuild_audio()
            files_index.rebuild_visual()
        except Exception as exc:
            log.info('Files index rebuild after cover extract failed: {0}'.format(exc))
            failed += 1

    log.info('Extracted covers for {0} track(s); {1} failed.'.format(fixed, failed))
    log.treat_result(
        'audio_extract_covers',
        'ok' if failed == 0 else 'partial',
        fixed,
    )
    return fixed, failed
