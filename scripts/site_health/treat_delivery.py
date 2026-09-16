# -*- coding: utf-8 -*-
"""Delivery treatments — reuse optimizeMedia / optimizeVideo / SFX stages."""

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
    ok, code = run_stage_script(
        'optimizeMedia.py',
        env_extra=env,
        label='Audio and still-image delivery',
    )
    log.treat_result('audio_visual_delivery', 'ok' if ok else 'failed', 0 if ok else 1)
    return ok


def treat_video_delivery():
    log.phase('treat:video')
    log.info('Video delivery rebuild.')
    ok, code = run_stage_script(
        'optimizeVideo.py',
        env_extra={},
        label='Video delivery',
    )
    log.treat_result('video_delivery', 'ok' if ok else 'failed', 0 if ok else 1)
    return ok


def treat_sfx_delivery():
    log.phase('treat:sfx')
    log.info('SFX delivery rebuild.')
    ok, code = run_stage_script(
        'buildSfxDelivery.py',
        env_extra={},
        label='SFX delivery',
    )
    log.treat_result('sfx_delivery', 'ok' if ok else 'failed', 0 if ok else 1)
    return ok


def treat_all_listener_delivery(force=False):
    """Full listener media delivery chain used by Force and delivery Treat."""
    try:
        from stopflag import stop_requested
    except Exception:
        def stop_requested():
            return False

    ok = treat_audio_visual_delivery(force=force)
    if not ok or stop_requested():
        return False
    ok = treat_video_delivery()
    if not ok or stop_requested():
        return False
    ok = treat_sfx_delivery()
    return ok
