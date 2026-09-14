"""
Catalog stage — register uncatalogued audio, materialize masters, canonicalize filenames.

Invokes biblioteca/build-catalog-cli.php so catalog rules stay in PHP with the asset registry.
"""

import os
import subprocess
import sys
import threading
from pathlib import Path

ROOT_DIR = Path(__file__).parent.parent
CLI_SCRIPT = ROOT_DIR / 'biblioteca' / 'build-catalog-cli.php'

from php_cli import resolve_php_cli


def _heartbeat(stop_event, interval_seconds=30):
    """Keep the publish log mtime fresh while the PHP catalogue CLI runs silently."""
    elapsed = 0
    while not stop_event.wait(interval_seconds):
        elapsed += interval_seconds
        print('Catalog: still working... ({0}s)'.format(elapsed))
        sys.stdout.flush()


def main():
    if not CLI_SCRIPT.is_file():
        print('FAILED Catalog CLI not found: ' + str(CLI_SCRIPT))
        sys.stdout.flush()
        return 1

    print('Catalog: resolving PHP CLI...')
    sys.stdout.flush()
    php = resolve_php_cli()
    if php == '':
        print('FAILED Could not resolve PHP CLI for catalog stage')
        sys.stdout.flush()
        return 1

    print('Catalog: using PHP CLI ' + php)
    print('Catalog: starting build-catalog-cli.php...')
    sys.stdout.flush()

    env = os.environ.copy()
    env['PYTHONIOENCODING'] = 'utf-8:replace'

    proc = subprocess.Popen(
        [php, '-f', str(CLI_SCRIPT)],
        cwd=str(ROOT_DIR),
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        env=env,
    )

    stop_heartbeat = threading.Event()
    heartbeat = threading.Thread(target=_heartbeat, args=(stop_heartbeat,))
    heartbeat.daemon = True
    heartbeat.start()

    assert proc.stdout is not None
    try:
        for raw_line in iter(proc.stdout.readline, b''):
            line = raw_line.decode('utf-8', errors='replace').rstrip('\n')
            print(line)
            sys.stdout.flush()
    finally:
        stop_heartbeat.set()
        heartbeat.join(timeout=2)

    proc.stdout.close()
    proc.wait()
    if proc.returncode != 0:
        print('FAILED Catalog stage exited with code ' + str(proc.returncode))
        sys.stdout.flush()
        return proc.returncode or 1

    return 0


if __name__ == '__main__':
    sys.exit(main())
