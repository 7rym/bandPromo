import json
import sys
from pathlib import Path

import makePlaylists


ROOT_DIR = Path(__file__).parent.parent
ORDER_FILE = ROOT_DIR / 'data' / 'playlist-order.json'
AUDIO_OPT_DIR = ROOT_DIR / 'media' / 'audio' / 'optimal'


def audio_delivery_ready(filename):
    delivery_name = Path(filename).stem + '.mp3'
    return (AUDIO_OPT_DIR / delivery_name).is_file()


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


def load_playlist_by_file():
    playlist_file = ROOT_DIR / 'play' / 'playlist.json'
    if not playlist_file.exists():
        return {}
    try:
        with open(str(playlist_file), 'r', encoding='utf-8') as handle:
            payload = json.load(handle)
    except Exception:
        return {}
    if not isinstance(payload, list):
        return {}

    by_file = {}
    for entry in payload:
        if not isinstance(entry, dict):
            continue
        filename = str(entry.get('file') or '').strip()
        if not filename:
            continue
        is_bundled = makePlaylists.is_bundled_placeholder(filename)
        by_file[filename] = {
            'file': filename,
            'title': str(entry.get('title') or filename),
            'artist': str(entry.get('artist') or ''),
            'album': str(entry.get('album') or ''),
            'duration': int(entry.get('duration') or 0),
            'origin': 'bundled-placeholder' if is_bundled else 'user-upload',
            'sourceTier': 'built-playlist',
        }
    return by_file


def build_track_entry(filename):
    working_path = makePlaylists.resolve_audio_working_path(filename)
    path = Path(working_path)
    if not path.exists() or not path.is_file():
        return None

    info = makePlaylists.parse_audio_file(str(path))
    return {
        'file': filename,
        'title': info.get('title') or filename,
        'artist': info.get('artist') or '',
        'album': info.get('album') or '',
        'duration': int(info.get('duration') or 0),
        'origin': 'bundled-placeholder' if makePlaylists.is_bundled_placeholder(filename) else 'user-upload',
        'sourceTier': 'master' if path.parent == makePlaylists.AUDIO_MASTER_DIR else 'original',
    }


def track_for_active(filename, pool_track_map, playlist_by_file):
    if filename in pool_track_map:
        return pool_track_map[filename]
    if filename in playlist_by_file:
        return playlist_by_file[filename]
    return build_track_entry(filename)


def split_active_available(pool_track_map, saved_order, playlist_by_file):
    if saved_order:
        active_files = [name for name in saved_order if isinstance(name, str) and name]
    elif playlist_by_file:
        active_files = list(playlist_by_file.keys())
    else:
        active_files = sorted(
            pool_track_map.keys(),
            key=lambda name: (makePlaylists.get_track_number(name), name.lower()),
        )

    active_set = set(active_files)
    active_tracks = []
    for filename in active_files:
        track = track_for_active(filename, pool_track_map, playlist_by_file)
        if track is not None:
            active_tracks.append(track)

    available_files = sorted(
        [name for name in pool_track_map if name not in active_set],
        key=lambda name: (makePlaylists.get_track_number(name), name.lower()),
    )
    available_tracks = [pool_track_map[name] for name in available_files]
    return active_tracks, available_tracks


def main():
    payload = read_payload()
    release_filter = str(payload.get('release') or payload.get('releaseId') or '').strip()
    if release_filter in ('', 'all'):
        release_filter = ''

    files, unsupported_files, hidden_bundled_files = makePlaylists.collect_audio_source_files(release_filter=release_filter)
    files.sort(key=lambda item: (makePlaylists.get_track_number(str(item)), item.name.lower()))

    saved_order = load_saved_order()
    if saved_order:
        order_index = {name: idx for idx, name in enumerate(saved_order)}
        files.sort(key=lambda item: (order_index.get(item.name, len(saved_order)), makePlaylists.get_track_number(str(item)), item.name.lower()))

    release_map = makePlaylists.load_asset_release_map()
    pool_track_map = {}
    for filepath in files:
        filename = filepath.name
        ready = audio_delivery_ready(filename)
        if not ready and not makePlaylists.is_bundled_placeholder(filename):
            continue
        working_path = makePlaylists.resolve_audio_working_path(filename)
        info = makePlaylists.parse_audio_file(str(working_path))
        release_id = makePlaylists.resolve_audio_release_id(filename, release_map)
        pool_track_map[filename] = {
            'file': filename,
            'title': info.get('title') or filename,
            'artist': info.get('artist') or '',
            'album': info.get('album') or '',
            'duration': int(info.get('duration') or 0),
            'origin': 'bundled-placeholder' if release_id == makePlaylists.BANDPROMO_RELEASE_DEMO_ID else 'user-upload',
            'sourceTier': 'master' if Path(working_path).parent == makePlaylists.AUDIO_MASTER_DIR else 'original',
            'deliveryReady': ready,
            'release_id': release_id,
        }

    playlist_by_file = load_playlist_by_file()
    active_tracks, available_tracks = split_active_available(pool_track_map, saved_order, playlist_by_file)

    print(json.dumps({
        'ok': True,
        'tracks': active_tracks,
        'activeTracks': active_tracks,
        'availableTracks': available_tracks,
        'hiddenBundledSourceFiles': [entry.name for entry in hidden_bundled_files],
        'unsupportedSourceFiles': [entry.name for entry in unsupported_files],
        'release_filter': release_filter or 'all',
    }, ensure_ascii=False))


if __name__ == '__main__':
    main()
