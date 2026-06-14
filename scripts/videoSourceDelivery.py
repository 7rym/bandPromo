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


def source_path_for(filename):
    return ov.VIDEO_ORIG_DIR / filename


def process_filename(filename):
    source_path = source_path_for(filename)
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

    ov.ensure_video_poster(source_path, poster_path)
    return ok, mode


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

    needs_transcode = False
    for filename in requested:
        source_path = source_path_for(filename)
        if source_path.exists() and ov.delivery_mode_for(source_path) == 'transcode':
            needs_transcode = True
            break

    if needs_transcode and not ov.check_ffmpeg():
        print(json.dumps({
            'ok': False,
            'error': 'ffmpeg is required to prepare delivery files for one or more videos',
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
        delivery_name = Path(name).stem + '.mp4'
        if not (ov.VIDEO_OPT_DIR / delivery_name).is_file():
            still_missing.append(name)

    print(json.dumps({
        'ok': len(still_missing) == 0,
        'prepared': prepared,
        'failed': failed,
        'still_missing': still_missing,
    }, ensure_ascii=False))


if __name__ == '__main__':
    main()
