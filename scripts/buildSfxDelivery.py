"""
SFX delivery stage — materialize masters and build tagless optimal MP3s.

Invokes biblioteca/build-sfx-delivery-cli.php so encode rules stay with
sfx-helpers.php (same path used by upload/import backfill).
"""

import os
import subprocess
import sys
from pathlib import Path

ROOT_DIR = Path(__file__).parent.parent
CLI_SCRIPT = ROOT_DIR / 'biblioteca' / 'build-sfx-delivery-cli.php'

from php_cli import resolve_php_cli


def main():
    if not CLI_SCRIPT.is_file():
        print('FAILED SFX delivery CLI not found: ' + str(CLI_SCRIPT))
        sys.stdout.flush()
        return 1

    php = resolve_php_cli()
    if php == '':
        print('FAILED Could not resolve PHP CLI for SFX delivery stage')
        sys.stdout.flush()
        return 1

    env = os.environ.copy()
    env['PYTHONIOENCODING'] = 'utf-8:replace'

    proc = subprocess.Popen(
        [php, '-f', str(CLI_SCRIPT)],
        cwd=str(ROOT_DIR),
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        env=env,
    )

    assert proc.stdout is not None
    for raw_line in iter(proc.stdout.readline, b''):
        line = raw_line.decode('utf-8', errors='replace').rstrip('\n')
        print(line)
        sys.stdout.flush()

    proc.stdout.close()
    proc.wait()
    if proc.returncode != 0:
        print('FAILED SFX delivery stage exited with code ' + str(proc.returncode))
        sys.stdout.flush()
        return proc.returncode or 1

    return 0


if __name__ == '__main__':
    sys.exit(main())
