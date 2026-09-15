# -*- coding: utf-8 -*-
"""
Run publish prep (PHP CLI) before Python publish stages.

Moves heavy reconcile / heal / Demo ensure off the web SAPI so shared hosts
do not kill the admin request mid-prep.
"""

from __future__ import print_function

import os
import subprocess
import sys

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
ROOT_DIR = os.path.dirname(SCRIPT_DIR)
CLI_SCRIPT = os.path.join(ROOT_DIR, 'biblioteca', 'publish-prep-cli.php')

sys.path.insert(0, SCRIPT_DIR)
from php_cli import resolve_php_cli  # noqa: E402
from job_heartbeat import touch_heartbeat  # noqa: E402


def log(message):
    print(message)
    sys.stdout.flush()


def run_publish_prep(meta_name='build.meta.json'):
    """
    Run CLI prep. Returns one of: 'ok', 'stopped', 'failed'.
    """
    touch_heartbeat(ROOT_DIR, stage='prep', message='Preparing your site for publish…', name=meta_name)
    log('[prep] Running publish preparation in the background…')

    php = resolve_php_cli()
    if not php:
        log('FAILED Could not resolve PHP CLI for publish prep')
        return 'failed'

    if not os.path.isfile(CLI_SCRIPT):
        log('FAILED publish-prep-cli.php is missing')
        return 'failed'

    log('[prep] PHP CLI: {0}'.format(php))
    env = os.environ.copy()
    env['BANDPROMO_PUBLISH_PREP_CLI'] = '1'
    env['BANDPROMO_BUILD_META'] = os.path.join(ROOT_DIR, 'log', meta_name)

    try:
        proc = subprocess.Popen(
            [php, CLI_SCRIPT],
            cwd=ROOT_DIR,
            env=env,
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            universal_newlines=True,
            bufsize=1,
        )
    except Exception as exc:
        log('FAILED Could not start publish prep: {0}'.format(exc))
        return 'failed'

    stopped = False
    failed = False
    for line in iter(proc.stdout.readline, ''):
        text = line.rstrip('\n')
        if text == 'PREP_STOPPED':
            stopped = True
            continue
        if text == 'PREP_OK':
            continue
        if text:
            print(text)
            sys.stdout.flush()
            touch_heartbeat(ROOT_DIR, stage='prep', message='Preparing your site for publish…', name=meta_name)

    proc.wait()
    if stopped:
        touch_heartbeat(ROOT_DIR, stage='prep', message='Preparation stopped.', name=meta_name)
        log('[prep] Stopped by operator.')
        return 'stopped'
    if proc.returncode != 0:
        failed = True
        log('FAILED Publish prep exited with code {0}'.format(proc.returncode))
        touch_heartbeat(ROOT_DIR, stage='prep', message='Preparation failed.', name=meta_name)
        return 'failed'

    touch_heartbeat(
        ROOT_DIR,
        stage='prep',
        message='Preparation finished — starting publish stages…',
        name=meta_name,
    )
    log('[prep] Ready for publish stages.')
    return 'ok' if not failed else 'failed'
