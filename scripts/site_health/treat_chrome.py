# -*- coding: utf-8 -*-
"""Site chrome treatments: share images + PWA manifest."""

from __future__ import print_function

import log
from chrome_pwa import generate_manifest
from chrome_social import generate_share_images


def treat_chrome():
    log.phase('treat:chrome')
    log.info('Updating share images and PWA manifest.')
    ok_social = generate_share_images()
    ok_pwa = generate_manifest()
    ok = ok_social and ok_pwa
    log.treat_result('site_chrome', 'ok' if ok else 'failed', 0 if ok else 1)
    return ok
