"""Shared VERSION file parsing and formatting."""

from __future__ import annotations

from pathlib import Path
import re
import sys

VERSION_PATTERN = re.compile(
    r'^v(?P<major>\d+)\.(?P<minor>\d+)\.(?P<session>\d+) build (?P<build>\d+)$'
)


def parse_version_line(value: str) -> dict[str, int | str]:
    match = VERSION_PATTERN.fullmatch(value.strip())
    if match is None:
        raise ValueError(
            'VERSION file format invalid. Expected: v<major>.<minor>.<session> build <number>'
        )

    groups = match.groupdict()
    return {
        'prefix': f'v{groups["major"]}.{groups["minor"]}.{groups["session"]}',
        'major': int(groups['major']),
        'minor': int(groups['minor']),
        'session': int(groups['session']),
        'build': int(groups['build']),
    }


def format_version_line(major: int, minor: int, session: int, build: int) -> str:
    return f'v{major}.{minor}.{session} build {build}'


def read_version_file(version_file: Path) -> dict[str, int | str]:
    if not version_file.exists():
        raise FileNotFoundError('VERSION file not found.')
    return parse_version_line(version_file.read_text(encoding='utf-8'))


def write_version_file(version_file: Path, major: int, minor: int, session: int, build: int) -> str:
    line = format_version_line(major, minor, session, build)
    version_file.write_text(line + '\n', encoding='utf-8')
    return line


def version_to_release_tag(version_line: str) -> str:
    parsed = parse_version_line(version_line)
    prefix = str(parsed['prefix']).lower()
    build = int(parsed['build'])
    return f'{prefix}-build-{build}'


def main() -> int:
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

    print(f'Unknown command: {command}', file=sys.stderr)
    return 1


if __name__ == '__main__':
    raise SystemExit(main())
