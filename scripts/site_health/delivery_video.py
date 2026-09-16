# -*- coding: utf-8 -*-
"""In-process video delivery (registry video masters -> media/visual/delivery)."""

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


def _load_optimize_video():
    import optimizeVideo as ov
    return ov


def _heartbeat(message):
    try:
        touch_heartbeat(ROOT_DIR, stage='treat', message=message, name=META_NAME)
    except Exception:
        pass


def run_video_delivery(force=False):
    """
    Rebuild stale/missing (or all, if force) registered visual video deliverables.
    Returns True on success (including empty queue).
    """
    ov = _load_optimize_video()
    if force:
        os.environ['BANDPROMO_FORCE_VIDEO_DELIVERY'] = '1'
        log.info('Force video delivery rebuild.')
    else:
        os.environ.pop('BANDPROMO_FORCE_VIDEO_DELIVERY', None)
        log.info('Video delivery rebuild (stale or missing only).')

    ov.ensure_directories()

    if not ov.check_ffmpeg():
        log.info('FAILED ffmpeg not found for video delivery.')
        return False

    if ov.xxhash is None:
        try:
            ov.warn_xxhash_once()
        except Exception:
            pass

    visual_queue = ov.load_registry_visual_video_queue()
    if not visual_queue:
        log.info('No registered visual video assets — skipping video delivery.')
        return True

    log.info('Found {0} registered visual video asset(s).'.format(len(visual_queue)))
    built = 0
    skipped = 0
    failed = 0
    posters_ready = 0
    total = len(visual_queue)
    processed_names = set()

    for index, asset in enumerate(visual_queue, start=1):
        if stop_requested():
            log.info('Stop requested — video delivery interrupted.')
            return False
        source = ov.visual_video_source_path(asset)
        if source is None:
            failed += 1
            log.info(
                'Missing video source for {0}: {1}'.format(
                    asset.get('id'), asset.get('original_filename')
                )
            )
            continue
        result = ov.process_one_video(
            source,
            asset_id=str(asset.get('id') or ''),
            asset=asset,
        )
        processed_names.add(source.name.lower())
        original_name = ov.basename_lower(asset.get('original_filename'))
        if original_name:
            processed_names.add(original_name)
        master_name = ov.basename_lower(asset.get('master_filename'))
        if master_name:
            processed_names.add(master_name)
        if result.get('failed'):
            failed += 1
        elif result.get('built'):
            built += 1
        else:
            skipped += 1
        if result.get('poster'):
            posters_ready += 1
        if index % 5 == 0 or index == total:
            log.progress(
                'video_delivery',
                index,
                total,
                '{0} built, {1} fresh, {2} failed'.format(built, skipped, failed),
            )
            _heartbeat('Video delivery {0}/{1}'.format(index, total))

    # Report unregistered intake (register-or-fail) without processing.
    unregistered = 0
    if ov.VIDEO_ORIG_DIR.exists():
        source_files = [
            path for path in sorted(ov.VIDEO_ORIG_DIR.iterdir())
            if path.is_file()
            and path.suffix.lower() in ov.SUPPORTED_VIDEO_EXTENSIONS
            and path.name.lower() not in processed_names
        ]
        unregistered = len(source_files)
        if unregistered:
            log.info(
                '{0} unregistered video source(s) skipped (register before Treat).'.format(
                    unregistered
                )
            )

    log.info(
        'Video delivery done: {0} built, {1} fresh, {2} failed, {3} poster(s).'.format(
            built, skipped, failed, posters_ready
        )
    )
    return failed == 0
