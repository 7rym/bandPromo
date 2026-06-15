"""
setupCompose — one-time initial site composition during the first full build.

Creates:
- playlist-order.json with all visible source tracks
- play/playlist.json via makePlaylists full scan
- data/gallery.json with all photo/video assets
- player tab order with bio page + gallery enabled
"""

import json
import re
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

import makePlaylists


SCRIPT_DIR = Path(__file__).parent
ROOT_DIR = SCRIPT_DIR.parent
MARKER_FILE = ROOT_DIR / 'data' / 'initial-site-compose.json'
GALLERY_FILE = ROOT_DIR / 'data' / 'gallery.json'
CONFIG_FILE = ROOT_DIR / 'web-config.json'
PHOTO_ORIG_DIR = ROOT_DIR / 'media' / 'photo' / 'original'
VIDEO_ORIG_DIR = ROOT_DIR / 'media' / 'video' / 'original'
PAGE_REGISTRY_FILE = ROOT_DIR / 'data' / 'pages' / 'registry.json'


def prettify_name(stem: str) -> str:
    cleaned = re.sub(r'[_\-]+', ' ', stem).strip()
    return cleaned or stem


def marker_exists() -> bool:
    return MARKER_FILE.is_file()


def write_marker():
    payload = {
        'composed_at_utc': datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ'),
        'version': 1,
    }
    MARKER_FILE.parent.mkdir(parents=True, exist_ok=True)
    with open(str(MARKER_FILE), 'w', encoding='utf-8') as handle:
        json.dump(payload, handle, indent=4, ensure_ascii=False)
        handle.write('\n')


def collect_audio_filenames():
    files, unsupported_files, hidden_bundled_files = makePlaylists.collect_audio_source_files()
    files.sort(key=lambda item: (makePlaylists.get_track_number(str(item)), item.name.lower()))
    return [item.name for item in files], unsupported_files, hidden_bundled_files


def write_playlist_order(filenames):
    order_file = ROOT_DIR / 'data' / 'playlist-order.json'
    order_file.parent.mkdir(parents=True, exist_ok=True)
    with open(str(order_file), 'w', encoding='utf-8') as handle:
        json.dump(filenames, handle, indent=4, ensure_ascii=False)
        handle.write('\n')


def run_make_playlists():
    env = dict(**{key: value for key, value in dict(**__import__('os').environ).items()})
    env['BANDPROMO_PLAYLIST_SCAN_MODE'] = 'full'
    result = subprocess.run(
        [sys.executable, '-u', str(SCRIPT_DIR / 'makePlaylists.py')],
        cwd=str(ROOT_DIR),
        env=env,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        encoding='utf-8',
        errors='replace',
    )
    if result.stdout:
        print(result.stdout)
    return result.returncode == 0


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


def write_gallery(items):
    GALLERY_FILE.parent.mkdir(parents=True, exist_ok=True)
    with open(str(GALLERY_FILE), 'w', encoding='utf-8') as handle:
        json.dump(items, handle, indent=4, ensure_ascii=False)
        handle.write('\n')


def ensure_player_layout():
    if not CONFIG_FILE.exists():
        print(f'⚠️  Missing {CONFIG_FILE}; skipping player layout compose.')
        return

    with open(str(CONFIG_FILE), 'r', encoding='utf-8') as handle:
        config = json.load(handle)
    if not isinstance(config, dict):
        print(f'⚠️  Invalid {CONFIG_FILE}; skipping player layout compose.')
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
    print('\n🧩 Initial site composition')
    print(f'Root: {ROOT_DIR}')

    if marker_exists():
        print('ℹ️  Initial site composition already recorded. Skipping.')
        return 0

    filenames, unsupported_files, hidden_bundled_files = collect_audio_filenames()
    if not filenames:
        print('❌ No supported source audio found for initial playlist composition.')
        if unsupported_files:
            print('   Unsupported audio files present: ' + ', '.join(file.name for file in unsupported_files))
        return 1

    print(f'Found {len(filenames)} track(s) for initial playlist.')
    if hidden_bundled_files:
        print('ℹ️  Hidden bundled demo tracks skipped: ' + ', '.join(file.name for file in hidden_bundled_files))

    write_playlist_order(filenames)
    if not run_make_playlists():
        print('❌ Initial playlist generation failed.')
        return 1

    gallery_items = build_gallery_items()
    write_gallery(gallery_items)
    print(f'Wrote gallery with {len(gallery_items)} item(s).')

    ensure_player_layout()
    print('Updated player layout modules and tab order.')

    write_marker()
    print(f'✅ Initial site composition complete ({MARKER_FILE.name}).')
    return 0


if __name__ == '__main__':
    sys.exit(main())
