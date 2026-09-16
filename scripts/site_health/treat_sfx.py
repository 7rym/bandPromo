# -*- coding: utf-8 -*-
"""SFX treatments: rebuild listener sound-effect deliverables."""

from __future__ import print_function

import log
from php_stage import run_php_cli


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
