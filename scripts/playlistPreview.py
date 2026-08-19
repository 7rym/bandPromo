import json
import sys
from pathlib import Path

from scripts import makePlaylists


ROOT_DIR = Path(__file__).parent.parent
ORDER_FILE = ROOT_DIR / 'data' / 'playlist-order.json'
AUDIO_OPT_DIR = ROOT_DIR / 'media' / 'audio' / 'optimal'
PLAYLISTS_DIR = ROOT_DIR / 'data' / 'playlists'


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


def load_playlist_document_active_files(playlist_id):
    playlist_id = makePlaylists.normalize_playlist_id(playlist_id)
    if not playlist_id:
        return []

    doc_path = PLAYLISTS_DIR / f'{playlist_id}.json'
    if not doc_path.exists():
        return []

    try:
        with open(str(doc_path), 'r', encoding='utf-8') as handle:
            payload = json.load(handle)
    except Exception:
        return []

    if not isinstance(payload, dict):
        return []

    active_files = []
    entries = payload.get('entries')
    if not isinstance(entries, list):
        return active_files

    for entry in entries:
        if not isinstance(entry, dict):
            continue
        filename = makePlaylists.resolve_playlist_file_name(
            str(entry.get('master_file') or entry.get('file') or '').strip()
        )
        if filename:
            active_files.append(filename)

    return active_files


def load_playlist_by_file():
    # Legacy play/playlist.json has been removed. Preview should come from playlist documents
    # (data/playlists/*.json), the saved order, or current pool scan.
    return {}


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


def split_active_available(pool_track_map, saved_order, playlist_by_file, document_active_files):
    if document_active_files:
        active_files = [name for name in document_active_files if isinstance(name, str) and name]
    elif saved_order:
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
    playlist_id = str(payload.get('playlistId') or payload.get('playlist_id') or '').strip()
    release_filter = str(payload.get('campaign') or payload.get('campaignId') or payload.get('release') or payload.get('releaseId') or '').strip()
    if release_filter in ('', 'all'):
        release_filter = ''

    files, unsupported_files, hidden_bundled_files = makePlaylists.collect_audio_source_files(campaign_filter=release_filter)
    files.sort(key=lambda item: (makePlaylists.get_track_number(str(item)), item.name.lower()))

    saved_order = load_saved_order()
    if saved_order and not playlist_id:
        order_index = {name: idx for idx, name in enumerate(saved_order)}
        files.sort(key=lambda item: (order_index.get(item.name, len(saved_order)), makePlaylists.get_track_number(str(item)), item.name.lower()))

    campaign_map = makePlaylists.load_asset_campaign_map()
    pool_track_map = {}
    for filepath in files:
        filename = filepath.name
        ready = audio_delivery_ready(filename)
        working_path = makePlaylists.resolve_audio_working_path(filename)
        info = makePlaylists.parse_audio_file(str(working_path))
        campaign_id = makePlaylists.resolve_audio_campaign_id(filename, campaign_map)
        pool_track_map[filename] = {
            'file': filename,
            'title': info.get('title') or filename,
            'artist': info.get('artist') or '',
            'album': info.get('album') or '',
            'duration': int(info.get('duration') or 0),
            'origin': 'bundled-placeholder' if makePlaylists.is_bundled_placeholder(filename) else 'user-upload',
            'sourceTier': 'master' if Path(working_path).parent == makePlaylists.AUDIO_MASTER_DIR else 'original',
            'deliveryReady': ready or makePlaylists.is_bundled_placeholder(filename),
            'campaign_id': campaign_id,
            'release_id': campaign_id,
        }

    document_active_files = load_playlist_document_active_files(playlist_id) if playlist_id else []
    playlist_by_file = load_playlist_by_file() if not document_active_files else {}
    active_tracks, available_tracks = split_active_available(
        pool_track_map,
        saved_order,
        playlist_by_file,
        document_active_files,
    )

    print(json.dumps({
        'ok': True,
        'tracks': active_tracks,
        'activeTracks': active_tracks,
        'availableTracks': available_tracks,
        'hiddenBundledSourceFiles': [entry.name for entry in hidden_bundled_files],
        'unsupportedSourceFiles': [entry.name for entry in unsupported_files],
        'campaign_filter': release_filter or 'all',
        'release_filter': release_filter or 'all',
        'playlist_id': makePlaylists.normalize_playlist_id(playlist_id),
    }, ensure_ascii=False))


if __name__ == '__main__':
    main()
