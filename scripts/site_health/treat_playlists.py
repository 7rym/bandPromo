# -*- coding: utf-8 -*-
"""Playlist publish treatment."""

from __future__ import print_function

import log
from stage_exec import run_stage_script


def treat_playlists():
    log.phase('treat:playlists')
    log.info('Publishing player playlist payloads for all playlists.')
    ok, code = run_stage_script(
        'makePlaylists.py',
        env_extra={},
        label='Playlist publish',
    )
    log.treat_result('playlists', 'ok' if ok else 'failed', 0 if ok else 1)
    return ok
