# -*- coding: utf-8 -*-
"""Run legacy stage scripts from site_health with unified logging."""

from __future__ import print_function

import os
import subprocess
import sys

import log

try:
    from stopflag import stop_requested
except Exception:
    def stop_requested():
        return False

SCRIPTS_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ROOT_DIR = os.path.dirname(SCRIPTS_DIR)


def run_stage_script(script_name, env_extra=None, label=''):
    """
    Subprocess a scripts/*.py stage, stream lines to HEALTH log.
    Returns (ok: bool, exit_code: int).
    """
    if stop_requested():
        log.info('Stop requested — skipping stage {0}.'.format(script_name))
        return False, 130

    script_path = os.path.join(SCRIPTS_DIR, script_name)
    if not os.path.isfile(script_path):
        log.info('FAILED Stage script missing: {0}'.format(script_name))
        return False, 1

    label = label or script_name
    log.info('Starting stage: {0}'.format(label))
    env = os.environ.copy()
    env['BUILD_ROOT'] = ROOT_DIR
    env['PYTHONIOENCODING'] = 'utf-8:replace'
    vendor = os.path.join(SCRIPTS_DIR, 'vendor')
    existing = str(env.get('PYTHONPATH') or '').strip()
    if existing:
        env['PYTHONPATH'] = vendor + os.pathsep + existing
    else:
        env['PYTHONPATH'] = vendor
    if isinstance(env_extra, dict):
        for key, value in env_extra.items():
            env[str(key)] = str(value)

    try:
        proc = subprocess.Popen(
            [sys.executable, '-u', script_path],
            cwd=ROOT_DIR,
            env=env,
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            universal_newlines=True,
        )
    except Exception as exc:
        log.info('FAILED Could not start {0}: {1}'.format(script_name, exc))
        return False, 1

    assert proc.stdout is not None
    for line in iter(proc.stdout.readline, ''):
        text = line.rstrip('\n')
        if text:
            # Avoid double HEALTH_ prefixes from nested health runs.
            if text.startswith('HEALTH_'):
                log.emit(text)
            else:
                log.info(text)
        if stop_requested():
            try:
                proc.terminate()
            except Exception:
                pass
            log.info('Stop requested — terminating stage {0}.'.format(script_name))
            try:
                proc.wait()
            except Exception:
                pass
            return False, 130

    proc.wait()
    code = int(proc.returncode or 0)
    if code != 0:
        log.info('Stage failed: {0} (exit {1})'.format(label, code))
        return False, code
    log.info('Stage finished: {0}'.format(label))
    return True, 0
