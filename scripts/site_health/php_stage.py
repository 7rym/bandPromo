# -*- coding: utf-8 -*-
"""Run biblioteca PHP CLIs from site_health with HEALTH_* logging."""

from __future__ import print_function

import os
import subprocess
import sys
import threading

import log
from paths import META_NAME, ROOT_DIR, SCRIPTS_DIR

if SCRIPTS_DIR not in sys.path:
    sys.path.insert(0, SCRIPTS_DIR)

try:
    from php_cli import resolve_php_cli
except Exception:
    def resolve_php_cli():
        return ''

try:
    from job_heartbeat import touch_heartbeat
except Exception:
    def touch_heartbeat(root, stage='', message='', name=META_NAME):
        return {}

try:
    from stopflag import stop_requested
except Exception:
    def stop_requested():
        return False


def run_php_cli(relative_script, label='', stage='treat', env_extra=None):
    """
    Subprocess a biblioteca/*.php CLI; stream lines to Activity.
    Returns (ok: bool, exit_code: int).
    """
    script_path = os.path.join(ROOT_DIR, relative_script.replace('/', os.sep))
    if not os.path.isfile(script_path):
        log.info('FAILED PHP CLI missing: {0}'.format(relative_script))
        return False, 1

    php = resolve_php_cli()
    if php == '':
        log.info('FAILED Could not resolve PHP CLI for {0}.'.format(label or relative_script))
        return False, 1

    label = label or relative_script
    log.info('Starting: {0}'.format(label))

    env = os.environ.copy()
    env['PYTHONIOENCODING'] = 'utf-8:replace'
    if isinstance(env_extra, dict):
        for key, value in env_extra.items():
            env[str(key)] = str(value)

    try:
        proc = subprocess.Popen(
            [php, '-f', script_path],
            cwd=ROOT_DIR,
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            env=env,
        )
    except Exception as exc:
        log.info('FAILED Could not start {0}: {1}'.format(label, exc))
        return False, 1

    stop_heartbeat = threading.Event()

    def _idle_heartbeat():
        elapsed = 0
        while not stop_heartbeat.wait(15):
            elapsed += 15
            message = '{0} still working... ({1}s)'.format(label, elapsed)
            log.info(message)
            try:
                touch_heartbeat(
                    ROOT_DIR,
                    stage=stage,
                    message=message,
                    name=META_NAME,
                )
            except Exception:
                pass

    heartbeat = threading.Thread(target=_idle_heartbeat)
    heartbeat.daemon = True
    heartbeat.start()

    assert proc.stdout is not None
    try:
        for raw_line in iter(proc.stdout.readline, b''):
            if stop_requested():
                try:
                    proc.terminate()
                except Exception:
                    pass
                log.info('Stop requested — terminating {0}.'.format(label))
                try:
                    proc.wait()
                except Exception:
                    pass
                return False, 130
            line = raw_line.decode('utf-8', errors='replace').rstrip('\n')
            if line.strip():
                log.info(line)
                try:
                    touch_heartbeat(
                        ROOT_DIR,
                        stage=stage,
                        message=line.strip()[:120],
                        name=META_NAME,
                    )
                except Exception:
                    pass
    finally:
        stop_heartbeat.set()
        heartbeat.join(timeout=2)
        try:
            proc.stdout.close()
        except Exception:
            pass

    proc.wait()
    code = int(proc.returncode or 0)
    if code != 0:
        log.info('FAILED {0} (exit {1}).'.format(label, code))
        return False, code
    log.info('Finished: {0}'.format(label))
    return True, 0
