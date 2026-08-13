import json
import sys
from pathlib import Path

import optimizeVideo as ov


def read_payload():
    raw = sys.stdin.read()
    if not raw.strip():
        return {}
    payload = json.loads(raw)
    return payload if isinstance(payload, dict) else {}


def load_asset_for_ref(ref):
    safe = Path(str(ref or '').strip()).name
    if not safe:
        return None
    payload = ov.load_asset_registry()
    assets = payload.get('assets') if isinstance(payload.get('assets'), dict) else {}
    if safe in assets and isinstance(assets[safe], dict):
        item = dict(assets[safe])
        item['id'] = str(item.get('id') or safe)
        return item
    for asset_id, asset in assets.items():
        if not isinstance(asset, dict):
            continue
        if str(asset.get('kind') or '').strip().lower() != 'visual':
            continue
        if str(asset.get('media_type') or '').strip().lower() != 'video':
            continue
        master_name = Path(str(asset.get('master_filename') or '').strip()).name
        original_name = Path(str(asset.get('original_filename') or '').strip()).name
        if safe in {asset_id, master_name, original_name, Path(master_name).stem, Path(original_name).stem}:
            item = dict(asset)
            item['id'] = str(asset.get('id') or asset_id)
            return item
    return None


def process_filename(filename):
    """Build delivery from a Visual video master when registered; else legacy original stem."""
    asset = load_asset_for_ref(filename)
    if isinstance(asset, dict):
        asset_id = str(asset.get('id') or '').strip()
        source_path = ov.visual_video_source_path(asset)
        if source_path is None or not source_path.is_file():
            return False, 'source_video_master_not_found'
        result = ov.process_one_video(source_path, asset_id=asset_id, asset=asset)
        if result.get('failed'):
            return False, 'delivery_conversion_failed'
        return True, 'visual_delivery'

    source_path = ov.VIDEO_ORIG_DIR / filename
    if not source_path.exists() or not source_path.is_file():
        return False, 'source_video_not_found'

    if source_path.suffix.lower() not in ov.SUPPORTED_VIDEO_EXTENSIONS:
        return False, 'unsupported_video_extension'

    mode = ov.delivery_mode_for(source_path)
    target_path = ov.delivery_path_for(source_path)
    poster_path = ov.poster_path_for(source_path)

    if ov.needs_refresh(source_path, target_path):
        if mode == 'copy':
            ok = ov.copy_mp4(source_path, target_path)
        else:
            ok = ov.transcode_to_mp4(source_path, target_path)
        if not ok:
            return False, 'delivery_conversion_failed'
    else:
        ok = True

    if not ov.ensure_video_poster(source_path, poster_path) or not poster_path.is_file():
        return False, 'poster_generation_failed'

    return ok and target_path.is_file(), mode


def delivery_ready_for_ref(filename):
    asset = load_asset_for_ref(filename)
    if isinstance(asset, dict):
        asset_id = str(asset.get('id') or '').strip()
        if not asset_id:
            return False
        delivery_dir = ov.VISUAL_DELIVERY_ROOT / asset_id
        return (delivery_dir / 'standard-stream.mp4').is_file() and (delivery_dir / 'poster.jpg').is_file()

    delivery_name = Path(filename).stem + '.mp4'
    poster_name = Path(filename).stem + '.jpg'
    return (ov.VIDEO_OPT_DIR / delivery_name).is_file() and (ov.VIDEO_POSTER_DIR / poster_name).is_file()


def main():
    payload = read_payload()
    filenames = payload.get('filenames')
    if not isinstance(filenames, list) or not filenames:
        print(json.dumps({'ok': False, 'error': 'Expected non-empty filenames array'}, ensure_ascii=False))
        return

    requested = []
    seen = set()
    for raw_name in filenames:
        name = str(raw_name or '').strip()
        if not name or '/' in name or '\\' in name or name in seen:
            continue
        seen.add(name)
        requested.append(name)

    if not requested:
        print(json.dumps({'ok': False, 'error': 'No valid filenames to prepare'}, ensure_ascii=False))
        return

    ov.ensure_directories()

    needs_ffmpeg = False
    for filename in requested:
        asset = load_asset_for_ref(filename)
        if isinstance(asset, dict):
            source_path = ov.visual_video_source_path(asset)
            if source_path is None or not source_path.is_file():
                continue
            if ov.delivery_mode_for(source_path, keep_audio=ov.video_keeps_audio(asset)) != 'copy':
                needs_ffmpeg = True
            continue
        source_path = ov.VIDEO_ORIG_DIR / filename
        if not source_path.exists():
            continue
        if ov.delivery_mode_for(source_path) == 'transcode':
            needs_ffmpeg = True
        poster_path = ov.poster_path_for(source_path)
        if ov.needs_refresh(source_path, poster_path):
            needs_ffmpeg = True

    if needs_ffmpeg and not ov.check_ffmpeg():
        print(json.dumps({
            'ok': False,
            'error': 'ffmpeg is required to prepare delivery files or posters for one or more videos',
        }, ensure_ascii=False))
        return

    prepared = []
    failed = []

    for filename in requested:
        ok, detail = process_filename(filename)
        if ok:
            prepared.append(filename)
        else:
            failed.append({'file': filename, 'error': detail})

    still_missing = []
    for name in requested:
        if not delivery_ready_for_ref(name):
            still_missing.append(name)

    print(json.dumps({
        'ok': len(still_missing) == 0 and not failed,
        'prepared': prepared,
        'failed': failed,
        'still_missing': still_missing,
    }, ensure_ascii=False))


if __name__ == '__main__':
    main()
