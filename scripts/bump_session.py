"""Increment the session number in the VERSION file.

Usage:
  python scripts/bump_session.py

Format:
  v<major>.<minor>.<session> build <number>
  Example: v0.8.4 build 303 -> v0.8.5 build 303
"""

from pathlib import Path
import sys

SCRIPT_DIR = Path(__file__).resolve().parent
sys.path.insert(0, str(SCRIPT_DIR))

from version_format import read_version_file, write_version_file


ROOT_DIR = SCRIPT_DIR.parent
VERSION_FILE = ROOT_DIR / 'VERSION'


def main() -> int:
    try:
        current = read_version_file(VERSION_FILE)
    except (FileNotFoundError, ValueError) as error:
        print(str(error), file=sys.stderr)
        return 1

    next_line = write_version_file(
        VERSION_FILE,
        int(current['major']),
        int(current['minor']),
        int(current['session']) + 1,
        int(current['build']),
    )
    print(next_line)
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
