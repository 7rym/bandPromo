"""
Catalog stage — register uncatalogued audio, materialize masters, canonicalize filenames.

Invokes biblioteca/build-catalog-cli.php so catalog rules stay in PHP with the asset registry.
"""

import subprocess
import sys
import os
from pathlib import Path

ROOT_DIR = Path(__file__).parent.parent
CLI_SCRIPT = ROOT_DIR / 'biblioteca' / 'build-catalog-cli.php'


def resolve_php_cli():
    for candidate in ('php', 'php.exe'):
        try:
            proc = subprocess.run(
                [candidate, '-v'],
                stdout=subprocess.PIPE,
                stderr=subprocess.STDOUT,
                cwd=str(ROOT_DIR),
                check=False,
            )
            if proc.returncode == 0:
                return candidate
        except OSError:
            continue
    return ''


def main():
    if not CLI_SCRIPT.is_file():
        print('FAILED Catalog CLI not found: ' + str(CLI_SCRIPT))
        sys.stdout.flush()
        return 1

    php = resolve_php_cli()
    if php == '':
        print('FAILED Could not resolve PHP CLI for catalog stage')
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
        print('FAILED Catalog stage exited with code ' + str(proc.returncode))
        sys.stdout.flush()
        return proc.returncode or 1

    return 0


if __name__ == '__main__':
    sys.exit(main())
