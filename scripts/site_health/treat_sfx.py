# -*- coding: utf-8 -*-
"""SFX treatments: rebuild listener sound-effect deliverables."""

from __future__ import print_function

import log
from stage_exec import run_stage_script


def treat_sfx(force=False):
    """
    Rebuild SFX optimal MP3s for registered sound effects.

    Encode rules currently live in biblioteca/sfx-helpers.php; this module owns
    the Status Treat phase and HEALTH_* logging. The PHP CLI is a short
    backfill, not a multi-minute catalogue loop.
    """
    log.phase('treat:sfx')
    if force:
        log.info('Force SFX delivery rebuild.')
    else:
        log.info('SFX delivery rebuild (stale or missing only).')
    env = {}
    if force:
        env['BANDPROMO_FORCE_SFX_DELIVERY'] = '1'
    ok, _code = run_stage_script(
        'buildSfxDelivery.py',
        env_extra=env,
        label='SFX delivery',
    )
    log.treat_result('sfx_delivery', 'ok' if ok else 'failed', 0 if ok else 1)
    return ok
