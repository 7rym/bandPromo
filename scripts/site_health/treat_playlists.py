# -*- coding: utf-8 -*-
"""Playlist publish treatment."""

from __future__ import print_function

import log
from php_stage import run_php_cli


def treat_playlists():
    log.phase('treat:playlists')
    log.info('Publishing player playlist payloads for all playlists.')
    ok, _code = run_php_cli(
        'biblioteca/build-player-playlists.php',
        label='Playlist publish',
        stage='treat',
    )
    log.treat_result('playlists', 'ok' if ok else 'failed', 0 if ok else 1)
    return ok
