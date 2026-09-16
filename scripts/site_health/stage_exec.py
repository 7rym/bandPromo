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
META_NAME = 'site-health.meta.json'

try:
    from job_heartbeat import touch_heartbeat
except Exception:
    def touch_heartbeat(root, stage='', message='', name=META_NAME):
        return {}


def _heartbeat_from_stage_line(text, stage_label):
    """Keep Status live message in sync while a legacy stage streams."""
    if not text:
        return
    message = text.strip()
    # Prefer compact progress lines operators can read at a glance.
    lower = message.lower()
    if 'visual ' in lower and '/' in message:
        message = message.lstrip(' -').strip()
    elif 'audio ' in lower and '/' in message:
        message = message.lstrip(' -').strip()
    elif len(message) > 120:
        message = message[:117] + '...'
    try:
        touch_heartbeat(
            ROOT_DIR,
            stage='treat',
            message=message or stage_label,
            name=META_NAME,
        )
    except Exception:
        pass


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
        # Force UTF-8: legacy stages print emoji; Windows charmap locales must not crash Treat.
        popen_kwargs = {
            'cwd': ROOT_DIR,
            'env': env,
            'stdout': subprocess.PIPE,
            'stderr': subprocess.STDOUT,
            'universal_newlines': True,
        }
        # encoding= is available on CPython 3.6+; keep a bytes fallback for safety.
        try:
            proc = subprocess.Popen(
                [sys.executable, '-u', script_path],
                encoding='utf-8',
                errors='replace',
                **popen_kwargs
            )
        except TypeError:
            popen_kwargs.pop('universal_newlines', None)
            proc = subprocess.Popen(
                [sys.executable, '-u', script_path],
                **popen_kwargs
            )
    except Exception as exc:
        log.info('FAILED Could not start {0}: {1}'.format(script_name, exc))
        return False, 1

    assert proc.stdout is not None
    line_count = 0
    for line in iter(proc.stdout.readline, '' if getattr(proc, 'encoding', None) else b''):
        if isinstance(line, bytes):
            text = line.decode('utf-8', errors='replace').rstrip('\n')
        else:
            text = line.rstrip('\n')
        if text:
            # Stage scripts print plain lines; site_health log.py stamps them.
            log.info(text)
            line_count += 1
            # Heartbeat on progress milestones, or every 25 lines so long
            # image rebuilds never leave Status on a stale "Working...".
            lower = text.lower()
            is_progress = (
                'visual ' in lower
                or 'audio ' in lower
                or 'built visual' in lower
                or 'processing' in lower
                or line_count == 1
                or (line_count % 25) == 0
            )
            if is_progress:
                _heartbeat_from_stage_line(text, label)
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
