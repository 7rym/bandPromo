"""
initialSiteSeed — first-run layout bootstrap (setup + disaster recovery only).

Not part of routine publish. Invoked via biblioteca/run-layout-seed.php after the
first setup build, or explicitly when recovering layout from files on disk.

Creates when container documents are still empty:
- data/playlists/{id}.json entries for the active publish playlist
- data/galleries/{default-id}.json entries from Visual registry assets (delivery / asset id)
- player tab order with bio page + gallery enabled

Does not write play/playlist.json, data/playlist-order.json, or data/gallery.json.

After Demo PRP import, playlist/gallery docs are usually already populated.
In that case this script only finishes player layout + records the seed marker.
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
ASSET_REGISTRY_FILE = ROOT_DIR / 'data' / 'assets' / 'registry.json'
VISUAL_DELIVERY_DIR = ROOT_DIR / 'media' / 'visual' / 'delivery'
PAGE_REGISTRY_FILE = ROOT_DIR / 'data' / 'pages' / 'registry.json'
PLAYLIST_REGISTRY_FILE = ROOT_DIR / 'data' / 'playlists' / 'registry.json'
PLAYLIST_REGISTRY_TEMPLATE = ROOT_DIR / 'biblioteca' / 'templates' / 'playlists.registry.template.json'
PLAYLISTS_DIR = ROOT_DIR / 'data' / 'playlists'
GALLERIES_DIR = ROOT_DIR / 'data' / 'galleries'
GALLERY_REGISTRY_FILE = GALLERIES_DIR / 'registry.json'
# Keep in sync with BANDPROMO_GALLERY_DEMO_ID in biblioteca/gallery-storage.php
GALLERY_DEFAULT_ID = 'bandpromo-demo'

# Skip brand/shell and track-cover roles when seeding the demo gallery.
_GALLERY_SKIP_ROLES = {
    'brand-logo',
    'brand-portrait',
    'shell-background-image',
    'shell-background-video',
    'track-cover',
}


def prettify_name(stem):
    cleaned = re.sub(r'[_\-]+', ' ', stem).strip()
    return cleaned or stem


def force_mode():
    return os.environ.get('BANDPROMO_LAYOUT_SEED_FORCE', '').strip().lower() in {'1', 'true', 'yes'}


def marker_exists():
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


def load_json_file(path):
    if not path.is_file():
        return None
    try:
        with open(str(path), 'r', encoding='utf-8') as handle:
            return json.load(handle)
    except Exception:
        return None


def write_json_file(path, payload):
    path.parent.mkdir(parents=True, exist_ok=True)
    with open(str(path), 'w', encoding='utf-8') as handle:
        json.dump(payload, handle, indent=4, ensure_ascii=False)
        handle.write('\n')


def collect_audio_filenames():
    files, unsupported_files, hidden_bundled_files = makePlaylists.collect_audio_source_files()
    if not files:
        # Masters-only installs (Demo PRP): fall back to playlist document masters,
        # then to files present under media/audio/master.
        document_order = makePlaylists.load_playlist_document_master_order()
        recovered = []
        for name in document_order or []:
            path = makePlaylists.resolve_audio_working_path(name)
            if path.is_file():
                recovered.append(path)
        if not recovered and makePlaylists.AUDIO_MASTER_DIR.is_dir():
            for entry in sorted(
                makePlaylists.AUDIO_MASTER_DIR.iterdir(),
                key=lambda item: item.name.lower(),
            ):
                if not entry.is_file():
                    continue
                if entry.name.lower() == 'desktop.ini':
                    continue
                if entry.suffix.lower() not in makePlaylists.SUPPORTED_EXTENSIONS:
                    continue
                recovered.append(entry)
        files = recovered

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


def playlist_registry_title(playlist_id):
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


def playlist_document_is_empty(playlist_id):
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
        print('ℹ️  Playlist {0} already has entries; skipping playlist seed.'.format(playlist_id))
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
    print('Seeded playlist {0} with {1} track(s).'.format(playlist_id, len(entries)))
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


def gallery_document_is_empty(gallery_id):
    doc_path = GALLERIES_DIR / '{0}.json'.format(gallery_id)
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


def containers_already_seeded():
    """True when Demo PRP / prior import already filled playlist + gallery docs."""
    playlist_id = makePlaylists.resolve_build_playlist_id()
    playlist_ready = bool(playlist_id) and (not playlist_document_is_empty(playlist_id))
    gallery_ready = not gallery_document_is_empty(GALLERY_DEFAULT_ID)
    return playlist_ready, gallery_ready


def _visual_display_title(asset, asset_id):
    display = asset.get('display') if isinstance(asset.get('display'), dict) else {}
    title = str(display.get('title') or '').strip()
    if title:
        return title
    original = str(asset.get('original_filename') or '').strip()
    if original:
        return prettify_name(Path(original).stem)
    master = str(asset.get('master_filename') or '').strip()
    if master:
        return prettify_name(Path(master).stem)
    return asset_id


def _visual_delivery_src(asset_id, media_type):
    delivery_dir = VISUAL_DELIVERY_DIR / asset_id
    if media_type == 'video':
        stream = delivery_dir / 'standard-stream.mp4'
        if stream.is_file():
            return '/media/visual/delivery/{0}/standard-stream.mp4'.format(asset_id)
        return asset_id
    for name in ('card.jpg', 'card.jpeg', 'card.png', 'card.webp'):
        candidate = delivery_dir / name
        if candidate.is_file():
            return '/media/visual/delivery/{0}/{1}'.format(asset_id, name)
    return asset_id


def build_gallery_items():
    """Build gallery seed entries from Visual registry assets (not photo/video original dirs)."""
    items = []
    registry = load_json_file(ASSET_REGISTRY_FILE) or {}
    assets = registry.get('assets') if isinstance(registry.get('assets'), dict) else {}
    for asset_id, asset in sorted(assets.items()):
        if not isinstance(asset, dict):
            continue
        if str(asset.get('kind') or '').strip().lower() != 'visual':
            continue
        media_type = str(asset.get('media_type') or '').strip().lower()
        if media_type not in ('image', 'video'):
            continue
        role = str(asset.get('role') or '').strip().lower()
        if role in _GALLERY_SKIP_ROLES:
            continue
        title = _visual_display_title(asset, asset_id)
        src = _visual_delivery_src(asset_id, media_type)
        items.append({
            'name': title,
            'src': src,
            'asset_id': asset_id,
            'alt': title,
            'type': 'video' if media_type == 'video' else 'image',
        })
    return items


def seed_gallery_if_empty(items):
    ensure_gallery_registry()
    doc_path = GALLERIES_DIR / '{0}.json'.format(GALLERY_DEFAULT_ID)

    if not gallery_document_is_empty(GALLERY_DEFAULT_ID):
        print('ℹ️  Gallery {0} already has entries; skipping gallery seed.'.format(GALLERY_DEFAULT_ID))
        return False

    entries = []
    for item in items:
        src = str(item.get('src') or '').strip()
        asset_id = str(item.get('asset_id') or '').strip()
        if not src and not asset_id:
            continue
        entry = {
            'src': src if src else asset_id,
            'type': str(item.get('type') or 'image'),
            'name': str(item.get('name') or ''),
            'alt': str(item.get('alt') or ''),
        }
        if asset_id:
            entry['asset_id'] = asset_id
        entries.append(entry)

    document = {
        'version': 1,
        'id': GALLERY_DEFAULT_ID,
        'title': 'bandPromo demo',
        'kind': 'system',
        'entries': entries,
    }
    write_json_file(doc_path, document)
    print('Seeded gallery {0} with {1} item(s).'.format(GALLERY_DEFAULT_ID, len(entries)))
    return True


def ensure_player_layout():
    if not CONFIG_FILE.exists():
        print('⚠️  Missing {0}; skipping player layout seed.'.format(CONFIG_FILE))
        return

    with open(str(CONFIG_FILE), 'r', encoding='utf-8') as handle:
        config = json.load(handle)
    if not isinstance(config, dict):
        print('⚠️  Invalid {0}; skipping player layout seed.'.format(CONFIG_FILE))
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
                        tab_order.append('page:{0}'.format(page_id))
        except Exception as exc:
            print('⚠️  Could not read page registry for player layout: {0}'.format(exc))

    if 'module:gallery' not in tab_order:
        tab_order.append('module:gallery')

    player['tab_order'] = tab_order

    with open(str(CONFIG_FILE), 'w', encoding='utf-8') as handle:
        json.dump(config, handle, indent=2, ensure_ascii=False)
        handle.write('\n')


def main():
    print('\n🌱 Initial site seed')
    print('Root: {0}'.format(ROOT_DIR))

    if marker_exists() and not force_mode():
        print('ℹ️  Initial site seed already recorded. Skipping.')
        return 0

    playlist_ready, gallery_ready = containers_already_seeded()
    filenames, unsupported_files, hidden_bundled_files = collect_audio_filenames()

    if not filenames and not playlist_ready:
        print('❌ No supported source audio found for initial playlist seed.')
        print('   Checked media/audio/original, playlist document masters, and media/audio/master.')
        if unsupported_files:
            print('   Unsupported audio files present: ' + ', '.join(file.name for file in unsupported_files))
        return 1

    if filenames:
        print('Found {0} track(s) for initial playlist seed.'.format(len(filenames)))
        if hidden_bundled_files:
            print('ℹ️  Hidden bundled demo tracks skipped: ' + ', '.join(file.name for file in hidden_bundled_files))
        seed_playlist_if_empty(filenames)
    elif playlist_ready:
        print('ℹ️  Playlist containers already populated (Demo PRP / prior import); skipping playlist seed.')

    gallery_items = build_gallery_items()
    if gallery_items:
        seed_gallery_if_empty(gallery_items)
    elif gallery_ready:
        print('ℹ️  Gallery already populated; skipping gallery seed.')
    else:
        seed_gallery_if_empty([])

    ensure_player_layout()
    print('Updated player layout modules and tab order.')

    write_marker()
    print('✅ Initial site seed complete ({0}).'.format(MARKER_FILE.name))
    return 0


if __name__ == '__main__':
    sys.exit(main())
