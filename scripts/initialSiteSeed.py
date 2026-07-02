"""
initialSiteSeed — first-run layout bootstrap (setup + disaster recovery only).

Not part of routine publish. Invoked via biblioteca/run-layout-seed.php after the
first setup build, or explicitly when recovering layout from files on disk.

Creates when container documents are still empty:
- data/playlists/{id}.json entries for the active publish playlist
- data/galleries/{default-id}.json entries from visible photo/video originals
- player tab order with bio page + gallery enabled

Does not write play/playlist.json, data/playlist-order.json, or data/gallery.json.
"""

import json
import os
import re
import sys
from datetime import datetime, timezone
from pathlib import Path

import makePlaylists


SCRIPT_DIR = Path(__file__).parent
ROOT_DIR = SCRIPT_DIR.parent
MARKER_FILE = ROOT_DIR / 'data' / 'initial-site-seed.json'
LEGACY_MARKER_FILE = ROOT_DIR / 'data' / 'initial-site-compose.json'
CONFIG_FILE = ROOT_DIR / 'web-config.json'
PHOTO_ORIG_DIR = ROOT_DIR / 'media' / 'photo' / 'original'
VIDEO_ORIG_DIR = ROOT_DIR / 'media' / 'video' / 'original'
PAGE_REGISTRY_FILE = ROOT_DIR / 'data' / 'pages' / 'registry.json'
PLAYLIST_REGISTRY_FILE = ROOT_DIR / 'data' / 'playlists' / 'registry.json'
PLAYLIST_REGISTRY_TEMPLATE = ROOT_DIR / 'biblioteca' / 'templates' / 'playlists.registry.template.json'
PLAYLISTS_DIR = ROOT_DIR / 'data' / 'playlists'
GALLERIES_DIR = ROOT_DIR / 'data' / 'galleries'
GALLERY_REGISTRY_FILE = GALLERIES_DIR / 'registry.json'
# Keep in sync with BANDPROMO_GALLERY_DEMO_ID in biblioteca/gallery-storage.php
GALLERY_DEFAULT_ID = 'bandpromo-demo'


def prettify_name(stem: str) -> str:
    cleaned = re.sub(r'[_\-]+', ' ', stem).strip()
    return cleaned or stem


def force_mode() -> bool:
    return os.environ.get('BANDPROMO_LAYOUT_SEED_FORCE', '').strip().lower() in {'1', 'true', 'yes'}


def marker_exists() -> bool:
    return MARKER_FILE.is_file() or LEGACY_MARKER_FILE.is_file()


def write_marker():
    payload = {
        'seeded_at_utc': datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ'),
        'version': 1,
    }
    MARKER_FILE.parent.mkdir(parents=True, exist_ok=True)
    with open(str(MARKER_FILE), 'w', encoding='utf-8') as handle:
        json.dump(payload, handle, indent=4, ensure_ascii=False)
        handle.write('\n')


def load_json_file(path: Path):
    if not path.is_file():
        return None
    try:
        with open(str(path), 'r', encoding='utf-8') as handle:
            return json.load(handle)
    except Exception:
        return None


def write_json_file(path: Path, payload):
    path.parent.mkdir(parents=True, exist_ok=True)
    with open(str(path), 'w', encoding='utf-8') as handle:
        json.dump(payload, handle, indent=4, ensure_ascii=False)
        handle.write('\n')


def collect_audio_filenames():
    files, unsupported_files, hidden_bundled_files = makePlaylists.collect_audio_source_files()
    files.sort(key=lambda item: (makePlaylists.get_track_number(str(item)), item.name.lower()))
    return [item.name for item in files], unsupported_files, hidden_bundled_files


def ensure_playlist_registry():
    PLAYLISTS_DIR.mkdir(parents=True, exist_ok=True)
    if PLAYLIST_REGISTRY_FILE.is_file():
        return

    template = load_json_file(PLAYLIST_REGISTRY_TEMPLATE)
    if isinstance(template, dict):
        write_json_file(PLAYLIST_REGISTRY_FILE, template)
        return

    write_json_file(PLAYLIST_REGISTRY_FILE, {
        'version': 1,
        'playlists': [
            {
                'id': makePlaylists.BANDPROMO_PLAYLIST_DEMO_ID,
                'title': 'bandPromo demo',
                'kind': 'system',
                'publish_date': '2026-01-01',
                'sort_order': 10,
            },
        ],
    })


def playlist_registry_title(playlist_id: str) -> str:
    registry = load_json_file(PLAYLIST_REGISTRY_FILE)
    if not isinstance(registry, dict):
        return playlist_id

    for entry in registry.get('playlists') or []:
        if not isinstance(entry, dict):
            continue
        if makePlaylists.normalize_playlist_id(entry.get('id')) == playlist_id:
            title = str(entry.get('title') or '').strip()
            if title:
                return title

    return playlist_id


def playlist_document_is_empty(playlist_id: str) -> bool:
    doc_path = makePlaylists.playlist_document_path(playlist_id)
    if doc_path is None or not doc_path.is_file():
        return True

    payload = load_json_file(doc_path)
    if not isinstance(payload, dict):
        return True

    entries = payload.get('entries')
    if not isinstance(entries, list):
        return True

    for entry in entries:
        if not isinstance(entry, dict):
            continue
        master_name = os.path.basename(str(entry.get('master_file') or entry.get('file') or '').strip())
        if master_name:
            return False

    return True


def build_playlist_entries(filenames):
    entries = []
    for filename in filenames:
        master_file = makePlaylists.resolve_playlist_file_name(filename)
        if not master_file:
            continue

        entry = {'master_file': master_file}
        asset = makePlaylists.load_asset_for_filename(filename)
        if isinstance(asset, dict):
            asset_id = str(asset.get('id') or '').strip()
            if asset_id:
                entry['asset_id'] = asset_id
            release_id = str(asset.get('release_id') or '').strip()
            if release_id:
                entry['release_id'] = release_id
        entries.append(entry)

    return entries


def seed_playlist_if_empty(filenames):
    ensure_playlist_registry()
    playlist_id = makePlaylists.resolve_build_playlist_id()
    if not playlist_id:
        print('⚠️  Could not resolve an active playlist id; skipping playlist seed.')
        return False

    if not playlist_document_is_empty(playlist_id):
        print(f'ℹ️  Playlist {playlist_id} already has entries; skipping playlist seed.')
        return False

    entries = build_playlist_entries(filenames)
    if not entries:
        print('⚠️  No catalogued tracks available for playlist seed.')
        return False

    document = {
        'version': 1,
        'id': playlist_id,
        'title': playlist_registry_title(playlist_id),
        'kind': 'system',
        'entries': entries,
    }
    doc_path = makePlaylists.playlist_document_path(playlist_id)
    if doc_path is None:
        print('⚠️  Invalid playlist document path; skipping playlist seed.')
        return False

    write_json_file(doc_path, document)
    print(f'Seeded playlist {playlist_id} with {len(entries)} track(s).')
    return True


def ensure_gallery_registry():
    GALLERIES_DIR.mkdir(parents=True, exist_ok=True)
    if GALLERY_REGISTRY_FILE.is_file():
        return

    write_json_file(GALLERY_REGISTRY_FILE, {
        'version': 1,
        'galleries': [
            {
                'id': GALLERY_DEFAULT_ID,
                'title': 'bandPromo demo',
                'kind': 'system',
                'sort_order': 10,
            },
        ],
    })


def gallery_document_is_empty(gallery_id: str) -> bool:
    doc_path = GALLERIES_DIR / f'{gallery_id}.json'
    if not doc_path.is_file():
        return True

    payload = load_json_file(doc_path)
    if not isinstance(payload, dict):
        return True

    entries = payload.get('entries')
    if not isinstance(entries, list):
        return True

    for entry in entries:
        if isinstance(entry, dict) and str(entry.get('src') or '').strip():
            return False

    return True


def build_gallery_items():
    items = []
    photo_exts = {'.png', '.jpg', '.jpeg', '.webp'}
    video_exts = {'.mp4', '.mov', '.webm'}

    if PHOTO_ORIG_DIR.exists():
        for path in sorted(PHOTO_ORIG_DIR.iterdir()):
            if not path.is_file() or path.suffix.lower() not in photo_exts:
                continue
            if path.name.lower() == 'desktop.ini':
                continue
            items.append({
                'name': prettify_name(path.stem),
                'src': f'/media/photo/original/{path.name}',
                'alt': prettify_name(path.stem),
                'type': 'image',
            })

    if VIDEO_ORIG_DIR.exists():
        for path in sorted(VIDEO_ORIG_DIR.iterdir()):
            if not path.is_file() or path.suffix.lower() not in video_exts:
                continue
            if path.name.lower() == 'desktop.ini':
                continue
            items.append({
                'name': prettify_name(path.stem),
                'src': f'/media/video/original/{path.name}',
                'alt': prettify_name(path.stem),
                'type': 'video',
            })

    return items


def seed_gallery_if_empty(items):
    ensure_gallery_registry()
    doc_path = GALLERIES_DIR / f'{GALLERY_DEFAULT_ID}.json'

    if not gallery_document_is_empty(GALLERY_DEFAULT_ID):
        print(f'ℹ️  Gallery {GALLERY_DEFAULT_ID} already has entries; skipping gallery seed.')
        return False

    entries = []
    for item in items:
        src = str(item.get('src') or '').strip()
        if not src:
            continue
        entry = {
            'src': src,
            'type': str(item.get('type') or 'image'),
            'name': str(item.get('name') or ''),
            'alt': str(item.get('alt') or ''),
        }
        entries.append(entry)

    document = {
        'version': 1,
        'id': GALLERY_DEFAULT_ID,
        'title': 'bandPromo demo',
        'kind': 'system',
        'entries': entries,
    }
    write_json_file(doc_path, document)
    print(f'Seeded gallery {GALLERY_DEFAULT_ID} with {len(entries)} item(s).')
    return True


def ensure_player_layout():
    if not CONFIG_FILE.exists():
        print(f'⚠️  Missing {CONFIG_FILE}; skipping player layout seed.')
        return

    with open(str(CONFIG_FILE), 'r', encoding='utf-8') as handle:
        config = json.load(handle)
    if not isinstance(config, dict):
        print(f'⚠️  Invalid {CONFIG_FILE}; skipping player layout seed.')
        return

    player = config.setdefault('player', {})
    modules = player.setdefault('modules', {})
    modules.setdefault('playlist', {'enabled': True})
    modules.setdefault('lyrics', {'enabled': True})
    modules.setdefault('gallery', {'enabled': True})
    modules.setdefault('pages', {'enabled': True})
    modules['gallery']['enabled'] = True
    modules['pages']['enabled'] = True

    tab_order = []
    if PAGE_REGISTRY_FILE.exists():
        try:
            with open(str(PAGE_REGISTRY_FILE), 'r', encoding='utf-8') as handle:
                registry = json.load(handle)
            pages = registry.get('pages') if isinstance(registry, dict) else []
            if isinstance(pages, list):
                visible_pages = [
                    page for page in pages
                    if isinstance(page, dict)
                    and page.get('show_in_player') is True
                    and str(page.get('surface') or 'player') != 'login'
                ]
                visible_pages.sort(key=lambda page: int(page.get('sort_order') or 0))
                for page in visible_pages:
                    page_id = str(page.get('id') or '').strip()
                    if page_id:
                        tab_order.append(f'page:{page_id}')
        except Exception as exc:
            print(f'⚠️  Could not read page registry for player layout: {exc}')

    if 'module:gallery' not in tab_order:
        tab_order.append('module:gallery')

    player['tab_order'] = tab_order

    with open(str(CONFIG_FILE), 'w', encoding='utf-8') as handle:
        json.dump(config, handle, indent=2, ensure_ascii=False)
        handle.write('\n')


def main():
    print('\n🌱 Initial site seed')
    print(f'Root: {ROOT_DIR}')

    if marker_exists() and not force_mode():
        print('ℹ️  Initial site seed already recorded. Skipping.')
        return 0

    filenames, unsupported_files, hidden_bundled_files = collect_audio_filenames()
    if not filenames:
        print('❌ No supported source audio found for initial playlist seed.')
        if unsupported_files:
            print('   Unsupported audio files present: ' + ', '.join(file.name for file in unsupported_files))
        return 1

    print(f'Found {len(filenames)} track(s) for initial playlist seed.')
    if hidden_bundled_files:
        print('ℹ️  Hidden bundled demo tracks skipped: ' + ', '.join(file.name for file in hidden_bundled_files))

    seed_playlist_if_empty(filenames)

    gallery_items = build_gallery_items()
    seed_gallery_if_empty(gallery_items)

    ensure_player_layout()
    print('Updated player layout modules and tab order.')

    write_marker()
    print(f'✅ Initial site seed complete ({MARKER_FILE.name}).')
    return 0


if __name__ == '__main__':
    sys.exit(main())
