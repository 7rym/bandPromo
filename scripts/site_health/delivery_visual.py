# -*- coding: utf-8 -*-
"""In-process visual still delivery (registry masters -> media/visual/delivery)."""

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


def run_visual_still_delivery(force=False):
    """
    Rebuild stale/missing (or all, if force) visual image delivery variants.
    Returns True on success.
    """
    om = _load_optimize_media()
    if force:
        os.environ['BANDPROMO_FORCE_VISUAL_DELIVERY'] = '1'
        log.info('Force visual still delivery rebuild.')
    else:
        os.environ.pop('BANDPROMO_FORCE_VISUAL_DELIVERY', None)
        log.info('Visual still delivery rebuild (stale or missing only).')

    om.VISUAL_DELIVERY_ROOT.mkdir(parents=True, exist_ok=True)
    visual_queue = om.load_registry_visual_image_queue()
    deduped = []
    seen_names = set()
    for asset in visual_queue:
        label = os.path.basename(str(asset.get('original_filename') or asset.get('id') or ''))
        key = label.lower()
        if key in seen_names:
            continue
        seen_names.add(key)
        deduped.append(asset)
    visual_queue = deduped

    if not visual_queue:
        log.info('No registered visual image assets — skipping still delivery.')
        return True

    log.info('Found {0} visual image asset(s) after basename dedupe.'.format(len(visual_queue)))
    if not om.pillow_is_available():
        log.info('FAILED {0}'.format(om.pillow_missing_message()))
        log.info(
            'Visual still delivery done: 0 built, 0 kept, {0} failed.'.format(len(visual_queue))
        )
        return False

    built = 0
    skipped = 0
    failed = 0
    total = len(visual_queue)
    for index, asset in enumerate(visual_queue, start=1):
        if stop_requested():
            log.info('Stop requested — visual delivery interrupted.')
            return False
        label = asset.get('original_filename') or asset.get('id') or ''
        log.info('Visual still delivery {0}/{1}: {2}'.format(index, total, label))
        _heartbeat('Visual delivery {0}/{1}'.format(index, total))
        result = None
        try:
            result = om.process_visual_image_asset(asset, quiet_skip=True)
        except ValueError as exc:
            # HITZ Py 3.6: closed stdout after a legacy TextIOWrapper double-wrap.
            if 'closed file' in str(exc).lower():
                try:
                    import stdio_utf8
                    stdio_utf8.repair()
                    result = om.process_visual_image_asset(asset, quiet_skip=True)
                except Exception as retry_exc:
                    log.info('Visual delivery I/O error on {0}: {1}'.format(label, retry_exc))
                    result = False
            else:
                log.info('Visual delivery error on {0}: {1}'.format(label, exc))
                result = False
        except Exception as exc:
            log.info('Visual delivery error on {0}: {1}'.format(label, exc))
            result = False
        if result == 'skipped':
            skipped += 1
        elif result:
            built += 1
        else:
            failed += 1
            log.info('Skipped or failed visual: {0}'.format(label))
        if index % 5 == 0 or index == total:
            log.progress(
                'visual_delivery',
                index,
                total,
                '{0} built, {1} kept, {2} failed'.format(built, skipped, failed),
            )

    log.info(
        'Visual still delivery done: {0} built, {1} kept, {2} failed.'.format(
            built, skipped, failed
        )
    )
    return failed == 0
