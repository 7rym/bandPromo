# -*- coding: utf-8 -*-
"""
Listener delivery treatments (audio stills + video).

Algorithms run in-process via delivery_audio / delivery_visual / delivery_video
(shared encode helpers remain in optimizeMedia.py / optimizeVideo.py).
"""

from __future__ import print_function

import log
from delivery_audio import run_audio_delivery
from delivery_video import run_video_delivery
from delivery_visual import run_visual_still_delivery


def treat_audio_visual_delivery(force=False):
    """Rebuild audio + still-image deliverables in-process."""
    log.phase('treat:delivery')
    if force:
        log.info('Force delivery rebuild (audio + still images).')
    else:
        log.info('Delivery rebuild (stale/missing audio + still images only).')

    ok_audio = run_audio_delivery(force=force)
    if not ok_audio:
        log.treat_result('audio_visual_delivery', 'failed', 1)
        return False

    try:
        from stopflag import stop_requested
    except Exception:
        def stop_requested():
            return False

    if stop_requested():
        log.treat_result('audio_visual_delivery', 'failed', 1)
        return False

    ok_visual = run_visual_still_delivery(force=force)
    ok = ok_audio and ok_visual
    log.treat_result('audio_visual_delivery', 'ok' if ok else 'failed', 0 if ok else 1)
    return ok


def treat_video_delivery(force=False):
    log.phase('treat:video')
    ok = run_video_delivery(force=force)
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
