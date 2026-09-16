# -*- coding: utf-8 -*-
"""
Listener delivery treatments (audio stills + video).

Algorithms live in optimizeMedia.py / optimizeVideo.py; Status Treat owns the
phase order and HEALTH_* logging via stage_exec (cutover bridge — not long PHP).
SFX lives in treat_sfx.py.
"""

from __future__ import print_function

import log
from stage_exec import run_stage_script


def treat_audio_visual_delivery(force=False):
    """Rebuild audio + still-image deliverables via optimizeMedia.py."""
    log.phase('treat:delivery')
    env = {
        'BANDPROMO_OPTIMIZE_MODE': 'full',
    }
    if force:
        env['BANDPROMO_FORCE_AUDIO_DELIVERY'] = '1'
        env['BANDPROMO_FORCE_VISUAL_DELIVERY'] = '1'
        log.info('Force delivery rebuild (audio + still images).')
    else:
        log.info('Delivery rebuild (stale/missing audio + still images only).')
    ok, _code = run_stage_script(
        'optimizeMedia.py',
        env_extra=env,
        label='Audio and still-image delivery',
    )
    log.treat_result('audio_visual_delivery', 'ok' if ok else 'failed', 0 if ok else 1)
    return ok


def treat_video_delivery(force=False):
    log.phase('treat:video')
    env = {}
    if force:
        env['BANDPROMO_FORCE_VIDEO_DELIVERY'] = '1'
        log.info('Force video delivery rebuild.')
    else:
        log.info('Video delivery rebuild.')
    ok, _code = run_stage_script(
        'optimizeVideo.py',
        env_extra=env,
        label='Video delivery',
    )
    log.treat_result('video_delivery', 'ok' if ok else 'failed', 0 if ok else 1)
    return ok


def treat_all_listener_delivery(force=False):
    """
    Full listener media delivery chain used by Force and delivery Treat.
    Order: audio/stills → video → sfx (sfx module owns its phase).
    """
    try:
        from stopflag import stop_requested
    except Exception:
        def stop_requested():
            return False

    ok = treat_audio_visual_delivery(force=force)
    if not ok or stop_requested():
        return False
    ok = treat_video_delivery(force=force)
    if not ok or stop_requested():
        return False
    import treat_sfx
    ok = treat_sfx.treat_sfx(force=force)
    return ok
