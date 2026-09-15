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
try:
    import stdio_utf8
    stdio_utf8.configure()
except Exception:
    pass

from php_cli import resolve_php_cli  # noqa: E402
from job_heartbeat import touch_heartbeat  # noqa: E402


def log(message):
    try:
        print(message)
    except Exception:
        try:
            sys.stdout.write(str(message).encode('ascii', 'replace').decode('ascii') + '\n')
        except Exception:
            pass
    try:
        sys.stdout.flush()
    except Exception:
        pass


def _decode_line(raw):
    if raw is None:
        return ''
    if isinstance(raw, bytes):
        return raw.decode('utf-8', 'replace').rstrip('\r\n')
    return str(raw).rstrip('\r\n')


def run_publish_prep(meta_name='build.meta.json'):
    """
    Run CLI prep. Returns one of: 'ok', 'stopped', 'failed'.
    """
    touch_heartbeat(
        ROOT_DIR,
        stage='prep',
        message='Preparing your site for publish...',
        name=meta_name,
    )
    log('[prep] Running publish preparation in the background...')

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
    env['PYTHONIOENCODING'] = 'utf-8:replace'
    env['LANG'] = env.get('LANG') or 'C.UTF-8'
    env['LC_ALL'] = env.get('LC_ALL') or 'C.UTF-8'

    popen_kwargs = {
        'cwd': ROOT_DIR,
        'env': env,
        'stdout': subprocess.PIPE,
        'stderr': subprocess.STDOUT,
        'bufsize': 1,
    }
    # Prefer explicit UTF-8 decode (HITZ locale is often ASCII-only).
    try:
        proc = subprocess.Popen(
            [php, CLI_SCRIPT],
            encoding='utf-8',
            errors='replace',
            universal_newlines=True,
            **popen_kwargs
        )
        use_text = True
    except TypeError:
        # Very old Python: binary pipe + manual decode.
        proc = subprocess.Popen([php, CLI_SCRIPT], **popen_kwargs)
        use_text = False
    except Exception as exc:
        log('FAILED Could not start publish prep: {0}'.format(exc))
        return 'failed'

    stopped = False
    failed = False
    try:
        while True:
            if use_text:
                line = proc.stdout.readline()
                if line == '' and proc.poll() is not None:
                    break
                text = _decode_line(line)
            else:
                raw = proc.stdout.readline()
                if raw == b'' and proc.poll() is not None:
                    break
                text = _decode_line(raw)
            if text == 'PREP_STOPPED':
                stopped = True
                continue
            if text == 'PREP_OK':
                continue
            if text:
                log(text)
                touch_heartbeat(
                    ROOT_DIR,
                    stage='prep',
                    message='Preparing your site for publish...',
                    name=meta_name,
                )
    except Exception as exc:
        log('FAILED Publish prep stream error: {0}'.format(exc))
        try:
            proc.kill()
        except Exception:
            pass
        return 'failed'

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
        message='Preparation finished - starting publish stages...',
        name=meta_name,
    )
    log('[prep] Ready for publish stages.')
    return 'ok' if not failed else 'failed'
