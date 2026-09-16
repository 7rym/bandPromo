# -*- coding: utf-8 -*-
"""Site chrome treatments: share images + PWA manifest."""

from __future__ import print_function

import log
from stage_exec import run_stage_script


def treat_chrome():
    log.phase('treat:chrome')
    log.info('Updating share images and PWA manifest.')
    ok_social, _code = run_stage_script(
        'makeSocial.py',
        env_extra={},
        label='Share images',
    )
    ok_pwa, _code2 = run_stage_script(
        'makePWA.py',
        env_extra={},
        label='PWA manifest',
    )
    ok = ok_social and ok_pwa
    log.treat_result('site_chrome', 'ok' if ok else 'failed', 0 if ok else 1)
    return ok
