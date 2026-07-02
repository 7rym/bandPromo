"""Increment the build number in the VERSION file.

Usage:
  python scripts/bump_version.py

Format:
  v<major>.<minor>.<session> build <number>
  Example: v0.8.4 build 303
"""

from pathlib import Path
import re
import sys


ROOT_DIR = Path(__file__).resolve().parent.parent
VERSION_FILE = ROOT_DIR / 'VERSION'
VERSION_PATTERN = re.compile(r'^(v\d+\.\d+\.\d+) build (\d+)$')


def main() -> int:
    if not VERSION_FILE.exists():
        print('VERSION file not found.', file=sys.stderr)
        return 1

    current = VERSION_FILE.read_text(encoding='utf-8').strip()
    match = VERSION_PATTERN.fullmatch(current)
    if match is None:
        print(
            'VERSION file format invalid. Expected: v<major>.<minor>.<session> build <number>',
            file=sys.stderr,
        )
        return 1

    version, build_number = match.groups()
    next_version = f'{version} build {int(build_number) + 1}'
    VERSION_FILE.write_text(next_version + '\n', encoding='utf-8')
    print(next_version)
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
