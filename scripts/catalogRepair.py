#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
Catalogue Repair background job.

Owns the long-lived process (browser-independent). Apply work still runs via
PHP CLI (content-autofix-cli.php) until the autofix pipeline is fully ported;
this supervisor writes heartbeats so Status can show honest progress.
"""
from __future__ import print_function

import os
import subprocess
import sys
import time
from datetime import datetime, timezone

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
ROOT_DIR = os.path.dirname(SCRIPT_DIR)
CLI_SCRIPT = os.path.join(ROOT_DIR, 'biblioteca', 'content-autofix-cli.php')
STOP_FLAG = os.path.join(ROOT_DIR, 'log', 'catalog-repair.stop')
META_NAME = 'catalog-repair.meta.json'

sys.path.insert(0, SCRIPT_DIR)
try:
    import stdio_utf8
    stdio_utf8.configure()
except Exception:
    pass
from php_cli import resolve_php_cli  # noqa: E402
from job_heartbeat import touch_heartbeat  # noqa: E402


def utc_stamp():
    return datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S UTC')


def log(message):
    try:
        print('[{0}] {1}'.format(utc_stamp(), message))
        sys.stdout.flush()
    except Exception:
        pass


def stop_requested():
    return os.path.isfile(STOP_FLAG)


def main():
    log('Catalogue Repair starting in the background')
    log('Root: {0}'.format(ROOT_DIR))
    touch_heartbeat(
        ROOT_DIR,
        stage='starting',
        message='Starting catalogue Repair...',
        name=META_NAME,
    )

    if not os.path.isfile(CLI_SCRIPT):
        log('FAILED Missing {0}'.format(CLI_SCRIPT))
        touch_heartbeat(ROOT_DIR, stage='failed', message='Repair script missing.', name=META_NAME)
        return 1

    php = resolve_php_cli()
    if not php:
        log('FAILED Could not resolve PHP CLI for Repair')
        touch_heartbeat(ROOT_DIR, stage='failed', message='Could not find PHP CLI.', name=META_NAME)
        return 1
    log('PHP CLI: {0}'.format(php))

    if stop_requested():
        try:
            os.remove(STOP_FLAG)
        except Exception:
            pass

    env = os.environ.copy()
    env['BANDPROMO_REPAIR_CLI'] = '1'
    env['PYTHONIOENCODING'] = 'utf-8:replace'
    env['LANG'] = env.get('LANG') or 'C.UTF-8'
    env['LC_ALL'] = env.get('LC_ALL') or 'C.UTF-8'

    touch_heartbeat(
        ROOT_DIR,
        stage='apply',
        message='Applying catalogue repairs...',
        name=META_NAME,
    )
    log('Launching Repair Apply (safe to leave this page)')
    try:
        proc = subprocess.Popen(
            [php, '-d', 'max_execution_time=0', CLI_SCRIPT],
            cwd=ROOT_DIR,
            env=env,
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            encoding='utf-8',
            errors='replace',
            universal_newlines=True,
            bufsize=1,
        )
    except TypeError:
        proc = subprocess.Popen(
            [php, '-d', 'max_execution_time=0', CLI_SCRIPT],
            cwd=ROOT_DIR,
            env=env,
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            universal_newlines=True,
            bufsize=1,
        )
    except Exception as exc:
        log('FAILED Could not start Repair: {0}'.format(exc))
        touch_heartbeat(ROOT_DIR, stage='failed', message='Could not start Repair.', name=META_NAME)
        return 1

    if proc.stdout is not None:
        for line in proc.stdout:
            try:
                line = line.rstrip('\r\n')
            except Exception:
                continue
            if line:
                try:
                    print(line)
                    sys.stdout.flush()
                except Exception:
                    pass
                touch_heartbeat(
                    ROOT_DIR,
                    stage='apply',
                    message='Applying catalogue repairs...',
                    name=META_NAME,
                )

    code = proc.wait()
    if code == 0:
        if stop_requested():
            try:
                os.remove(STOP_FLAG)
            except Exception:
                pass
            log('Repair stopped by operator (clean)')
            touch_heartbeat(ROOT_DIR, stage='stopped', message='Repair stopped.', name=META_NAME)
            return 0
        log('Repair finished successfully')
        touch_heartbeat(ROOT_DIR, stage='done', message='Repair finished.', name=META_NAME)
        return 0

    log('FAILED Repair exited with code {0}'.format(code))
    touch_heartbeat(ROOT_DIR, stage='failed', message='Repair did not finish.', name=META_NAME)
    return 1 if code else 1


if __name__ == '__main__':
    time.sleep(0.05)
    sys.exit(main())
