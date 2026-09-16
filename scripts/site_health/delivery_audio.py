# -*- coding: utf-8 -*-
"""In-process audio delivery (registry masters -> media/audio/optimal)."""

from __future__ import print_function

import os
import sys

from paths import META_NAME, ROOT_DIR, SCRIPTS_DIR

if SCRIPTS_DIR not in sys.path:
    sys.path.insert(0, SCRIPTS_DIR)

import log

try:
    from job_heartbeat import touch_heartbeat
except Exception:
    def touch_heartbeat(root, stage='', message='', name=META_NAME):
        return {}

try:
    from stopflag import stop_requested
except Exception:
    def stop_requested():
        return False


def _load_optimize_media():
    import optimizeMedia as om
    return om


def _heartbeat(message):
    try:
        touch_heartbeat(ROOT_DIR, stage='treat', message=message, name=META_NAME)
    except Exception:
        pass


def run_audio_delivery(force=False):
    """
    Rebuild stale/missing (or all, if force) audio optimal MP3s.
    Returns True on success (including empty queue).
    """
    om = _load_optimize_media()
    if force:
        os.environ['BANDPROMO_FORCE_AUDIO_DELIVERY'] = '1'
        log.info('Force audio delivery rebuild.')
    else:
        os.environ.pop('BANDPROMO_FORCE_AUDIO_DELIVERY', None)
        log.info('Audio delivery rebuild (stale or missing only).')

    queue = om.load_registry_audio_delivery_queue()
    if not queue:
        log.info('No registered audio assets — skipping audio delivery.')
        return True

    log.info('Found {0} registered audio asset(s).'.format(len(queue)))
    if om.delivery_queue_needs_ffmpeg(queue):
        if not om.check_ffmpeg():
            log.info('FAILED ffmpeg not found for audio delivery.')
            return False

    converted = 0
    skipped = 0
    failed = 0
    total = len(queue)
    for index, item in enumerate(queue, start=1):
        if stop_requested():
            log.info('Stop requested — audio delivery interrupted.')
            return False
        result = om.process_audio_delivery(
            item.get('master_filename'),
            display_title=item.get('display_title') or '',
            display_artist=item.get('display_artist') or '',
            display=item.get('display') if isinstance(item.get('display'), dict) else {},
            asset_id=item.get('asset_id') or '',
            recorded_source_mtime=item.get('recorded_source_mtime'),
            recorded_source_xxh3=item.get('recorded_source_xxh3'),
        )
        if result == 'skipped':
            skipped += 1
        elif result:
            converted += 1
        else:
            failed += 1
        if index % 10 == 0 or index == total:
            log.progress(
                'audio_delivery',
                index,
                total,
                '{0} built, {1} fresh, {2} failed'.format(converted, skipped, failed),
            )
            _heartbeat('Audio delivery {0}/{1}'.format(index, total))

    # Cleanup stale optimal files
    opt_dir = om.AUDIO_OPT_DIR
    removed = 0
    if opt_dir.is_dir():
        allowed = {
            item['delivery_filename']
            for item in queue
            if item.get('delivery_filename')
        }
        for item in opt_dir.iterdir():
            if item.is_file() and item.name not in allowed:
                try:
                    item.unlink()
                    removed += 1
                except Exception as exc:
                    log.info('Could not remove stale audio {0}: {1}'.format(item.name, exc))
    if removed:
        log.info('Removed {0} stale audio delivery file(s).'.format(removed))

    log.info(
        'Audio delivery done: {0} built, {1} fresh, {2} failed.'.format(
            converted, skipped, failed
        )
    )
    return failed == 0
