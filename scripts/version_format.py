"""Shared VERSION file parsing and formatting.

Must stay importable on CPython 3.6.9+ (hard floor for all scripts/).
"""

from pathlib import Path
import re
import sys
from typing import Dict, Union

VERSION_PATTERN = re.compile(
    r'^v(?P<major>\d+)\.(?P<minor>\d+)\.(?P<session>\d+) build (?P<build>\d+)$'
)


def parse_version_line(value):
    # type: (str) -> Dict[str, Union[int, str]]
    match = VERSION_PATTERN.fullmatch(value.strip())
    if match is None:
        raise ValueError(
            'VERSION file format invalid. Expected: v<major>.<minor>.<session> build <number>'
        )

    groups = match.groupdict()
    return {
        'prefix': 'v{0}.{1}.{2}'.format(groups['major'], groups['minor'], groups['session']),
        'major': int(groups['major']),
        'minor': int(groups['minor']),
        'session': int(groups['session']),
        'build': int(groups['build']),
    }


def format_version_line(major, minor, session, build):
    # type: (int, int, int, int) -> str
    return 'v{0}.{1}.{2} build {3}'.format(major, minor, session, build)


def read_version_file(version_file):
    # type: (Path) -> Dict[str, Union[int, str]]
    if not version_file.exists():
        raise FileNotFoundError('VERSION file not found.')
    return parse_version_line(version_file.read_text(encoding='utf-8'))


def write_version_file(version_file, major, minor, session, build):
    # type: (Path, int, int, int, int) -> str
    line = format_version_line(major, minor, session, build)
    version_file.write_text(line + '\n', encoding='utf-8')
    return line


def version_to_release_tag(version_line):
    # type: (str) -> str
    parsed = parse_version_line(version_line)
    prefix = str(parsed['prefix']).lower()
    build = int(parsed['build'])
    return '{0}-build-{1}'.format(prefix, build)


def main():
    # type: () -> int
    if len(sys.argv) < 2:
        print('Usage: python scripts/version_format.py tag', file=sys.stderr)
        return 1

    command = sys.argv[1]
    root_dir = Path(__file__).resolve().parent.parent
    version_file = root_dir / 'VERSION'

    if command == 'tag':
        current = read_version_file(version_file)
        line = format_version_line(
            int(current['major']),
            int(current['minor']),
            int(current['session']),
            int(current['build']),
        )
        print(version_to_release_tag(line))
        return 0

    print('Unknown command: {0}'.format(command), file=sys.stderr)
    return 1


if __name__ == '__main__':
    raise SystemExit(main())
