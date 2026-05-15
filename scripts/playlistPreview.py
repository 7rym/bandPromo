import json
import sys
from pathlib import Path

import makePlaylists


ROOT_DIR = Path(__file__).parent.parent
ORDER_FILE = ROOT_DIR / 'data' / 'playlist-order.json'


def read_payload():
    raw = sys.stdin.read()
    if not raw.strip():
        return {}
    payload = json.loads(raw)
    return payload if isinstance(payload, dict) else {}


def load_saved_order():
    if not ORDER_FILE.exists():
        return []
    try:
        with open(str(ORDER_FILE), 'r', encoding='utf-8') as handle:
            payload = json.load(handle)
    except Exception:
        return []
    return payload if isinstance(payload, list) else []


def main():
    payload = read_payload()
    include_bundled = payload.get('includeBundled') is True

    files, unsupported_files, hidden_bundled_files = makePlaylists.collect_audio_source_files(include_bundled=include_bundled)
    files.sort(key=lambda item: (makePlaylists.get_track_number(str(item)), item.name.lower()))

    saved_order = load_saved_order()
    if saved_order:
        order_index = {name: idx for idx, name in enumerate(saved_order)}
        files.sort(key=lambda item: (order_index.get(item.name, len(saved_order)), makePlaylists.get_track_number(str(item)), item.name.lower()))

    tracks = []
    for filepath in files:
        filename = filepath.name
        working_path = makePlaylists.resolve_audio_working_path(filename)
        info = makePlaylists.parse_audio_file(str(working_path))
        tracks.append({
            'file': filename,
            'title': info.get('title') or filename,
            'artist': info.get('artist') or '',
            'album': info.get('album') or '',
            'duration': int(info.get('duration') or 0),
            'origin': 'bundled-placeholder' if makePlaylists.is_bundled_placeholder(filename) else 'user-upload',
            'sourceTier': 'master' if Path(working_path).parent == makePlaylists.AUDIO_MASTER_DIR else 'original',
        })

    print(json.dumps({
        'ok': True,
        'tracks': tracks,
        'hiddenBundledSourceFiles': [entry.name for entry in hidden_bundled_files],
        'unsupportedSourceFiles': [entry.name for entry in unsupported_files],
        'includeBundled': include_bundled,
    }, ensure_ascii=False))


if __name__ == '__main__':
    main()