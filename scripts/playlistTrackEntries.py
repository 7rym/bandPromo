import json
import os
import sys
from pathlib import Path

import makePlaylists


def read_payload():
    raw = sys.stdin.read()
    if not raw.strip():
        return {}
    payload = json.loads(raw)
    return payload if isinstance(payload, dict) else {}


def build_playlist_entry(filename):
    working_path = makePlaylists.resolve_audio_working_path(filename)
    path = Path(working_path)
    if not path.exists() or not path.is_file():
        return None

    info = makePlaylists.parse_audio_file(str(path))
    cover_file = info.get('cover') or ''
    if cover_file:
        cover_file = os.path.basename(str(cover_file))
    else:
        cover_file = ''

    return {
        'file': filename,
        'title': info.get('title') or filename,
        'artist': info.get('artist') or '',
        'album': info.get('album') or '',
        'duration': int(info.get('duration') or 0),
        'lyrics': info.get('lyrics') or '',
        'description': info.get('description') or '',
        'cover': cover_file,
        'living_cover': info.get('living_cover') or '',
    }


def main():
    payload = read_payload()
    filenames = payload.get('filenames')
    if not isinstance(filenames, list):
        print(json.dumps({'ok': False, 'error': 'Expected filenames array'}, ensure_ascii=False))
        return

    entries = []
    missing = []
    for raw_name in filenames:
        filename = str(raw_name or '').strip()
        if not filename or '/' in filename or '\\' in filename:
            missing.append(filename or str(raw_name))
            continue

        entry = build_playlist_entry(filename)
        if entry is None:
            missing.append(filename)
            continue

        entries.append(entry)

    print(json.dumps({
        'ok': True,
        'entries': entries,
        'missing': missing,
    }, ensure_ascii=False))


if __name__ == '__main__':
    main()
