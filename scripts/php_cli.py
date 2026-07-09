"""
Resolve a usable PHP CLI binary for publish subprocesses.

PHP web requests export BANDPROMO_PHP_CLI (and bindir/version hints) before
starting Python so catalog stages can call biblioteca/*.php CLIs on hosts where
bare `php` is not on PATH or open_basedir hides Plesk binaries from is_file().
"""

from __future__ import annotations

import os
import subprocess
from pathlib import Path


def _php_smoke(candidate: str) -> bool:
    if candidate == '':
        return False

    try:
        proc = subprocess.run(
            [candidate, '-r', 'echo "php-cli-smoke";'],
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            check=False,
        )
    except OSError:
        return False

    if proc.returncode != 0:
        return False

    output = proc.stdout.decode('utf-8', errors='replace')
    return 'php-cli-smoke' in output


def php_cli_candidates() -> list[str]:
    candidates: list[str] = []

    for key in ('BANDPROMO_PHP_CLI', 'BUILD_PHP'):
        value = os.environ.get(key, '').strip()
        if value:
            candidates.append(value)

    version = os.environ.get('BANDPROMO_PHP_VERSION', '').strip()
    if version:
        major = version.split('.', 1)[0]
        candidates.extend([
            f'/opt/plesk/php/{version}/bin/php',
            f'/opt/plesk/php/{major}/bin/php',
            f'/usr/bin/php{version}',
            f'/usr/bin/php{major}',
        ])

    bindir = os.environ.get('BANDPROMO_PHP_BINDIR', '').strip()
    if bindir:
        candidates.append(str(Path(bindir) / 'php'))

    candidates.extend([
        '/usr/bin/php',
        '/usr/local/bin/php',
        'php',
        'php.exe',
    ])

    unique: list[str] = []
    for candidate in candidates:
        candidate = candidate.strip()
        if candidate and candidate not in unique:
            unique.append(candidate)

    return unique


def resolve_php_cli() -> str:
    for candidate in php_cli_candidates():
        if _php_smoke(candidate):
            return candidate

    return ''
