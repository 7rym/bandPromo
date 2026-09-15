#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
Catalogue Repair supervisor (background).

Launched like Publish (build-runner → this script). Owns the long-lived process so
Repair does not depend on an open browser tab. Resolves PHP CLI and runs
biblioteca/content-autofix-cli.php, streaming progress into the repair log.

Cooperative Stop: admin writes log/catalog-repair.stop; the CLI pipeline finishes
the current step then exits.
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


def utc_stamp():
    return datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S UTC')


def log(message):
    print('[{0}] {1}'.format(utc_stamp(), message))
    sys.stdout.flush()


def resolve_php_cli():
    env_php = (os.environ.get('BUILD_PHP') or os.environ.get('BANDPROMO_PHP') or '').strip()
    candidates = []
    if env_php:
        candidates.append(env_php)

    # Common shared-host / Plesk layouts (same idea as bandpromo_resolve_php_cli).
    for major_minor in ('8.3', '8.2', '8.1', '8.0', '7.4'):
        candidates.append('/opt/plesk/php/{0}/bin/php'.format(major_minor))
        candidates.append('/usr/bin/php{0}'.format(major_minor))
    candidates.extend([
        '/usr/bin/php',
        '/usr/local/bin/php',
        'php',
    ])
    if os.name == 'nt':
        candidates.extend(['php.exe', 'C:\\php\\php.exe'])

    for candidate in candidates:
        if not candidate:
            continue
        if candidate not in ('php', 'php.exe') and not os.path.isfile(candidate):
            continue
        try:
            proc = subprocess.Popen(
                [candidate, '-r', 'echo "php-cli-ok";'],
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                universal_newlines=True,
            )
            out, _err = proc.communicate()
            if proc.returncode == 0 and 'php-cli-ok' in (out or ''):
                return candidate
        except Exception:
            continue
    return ''


def stop_requested():
    return os.path.isfile(STOP_FLAG)


def main():
    log('Python catalogue Repair supervisor starting')
    log('Root: {0}'.format(ROOT_DIR))
    if not os.path.isfile(CLI_SCRIPT):
        log('FAILED Missing {0}'.format(CLI_SCRIPT))
        return 1

    php = resolve_php_cli()
    if not php:
        log('FAILED Could not resolve PHP CLI for Repair')
        return 1
    log('PHP CLI: {0}'.format(php))

    if stop_requested():
        try:
            os.remove(STOP_FLAG)
        except Exception:
            pass

    env = os.environ.copy()
    env['BANDPROMO_REPAIR_CLI'] = '1'

    log('Launching content-autofix-cli.php (background Apply)')
    try:
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
        log('FAILED Could not start PHP CLI Repair: {0}'.format(exc))
        return 1

    # Stream child output (also written to catalog-repair.log by PHP helpers).
    if proc.stdout is not None:
        for line in proc.stdout:
            line = line.rstrip('\r\n')
            if line:
                print(line)
                sys.stdout.flush()

    code = proc.wait()
    if code == 0:
        if stop_requested():
            try:
                os.remove(STOP_FLAG)
            except Exception:
                pass
            log('Repair stopped by operator (clean)')
            return 0
        log('Repair finished successfully')
        return 0

    log('FAILED Repair CLI exited with code {0}'.format(code))
    return 1 if code else 1


if __name__ == '__main__':
    # Small settle so the start endpoint can finish writing the lock/log header.
    time.sleep(0.05)
    sys.exit(main())
