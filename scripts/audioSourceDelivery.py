import json
import os
import sys
from pathlib import Path

import stdio_utf8
stdio_utf8.configure()

import makePlaylists
import optimizeMedia as om


def emit_json(payload):
    text = json.dumps(payload, ensure_ascii=False)
    streams = []
    for candidate in (sys.stdout, getattr(sys, '__stdout__', None), sys.stderr):
        if candidate is not None and candidate not in streams:
            streams.append(candidate)
    for stream in streams:
        try:
            stream.write(text + '\n')
            stream.flush()
            return
        except Exception:
            continue


def read_payload():
    raw = sys.stdin.read()
    if not raw.strip():
        return {}
    payload = json.loads(raw)
    return payload if isinstance(payload, dict) else {}


def needs_ffmpeg(filenames):
    for filename in filenames:
        source_path, _source_tier = om.resolve_audio_working_path(filename)
        if not Path(source_path).exists():
            continue
        if om.audio_delivery_mode(source_path) == 'transcode':
            return True
    return False


def process_filename(filename):
    source_path, source_tier = om.resolve_audio_working_path(filename)
    source = Path(source_path)
    if not source.exists() or not source.is_file():
        return False, 'source_audio_not_found'

    mp3_filename = Path(filename).stem + '.mp3'
    mp3_path = om.AUDIO_OPT_DIR / mp3_filename
    delivery_mode = om.audio_delivery_mode(source_path)
    tags = om.get_audio_tags(str(source_path))

    if delivery_mode == 'copy':
        converted_ok = om.copy_audio_to_mp3(str(source_path), str(mp3_path))
    else:
        converted_ok = om.convert_audio_to_mp3(str(source_path), str(mp3_path))

    if not converted_ok:
        return False, 'delivery_conversion_failed'

    om.set_id3_tags(str(mp3_path), tags)

    info = makePlaylists.parse_audio_file(str(source_path))
    cover_file = os.path.basename(str(info.get('cover') or '').strip())
    if cover_file:
        orig_cover_path = om.IMG_ORIG_DIR / cover_file
        lq_cover_path = om.IMG_OPT_DIR / (Path(cover_file).stem + '.jpg')
        if orig_cover_path.is_file():
            om.convert_cover_to_jpeg(str(orig_cover_path), str(lq_cover_path), quality=75)

    return True, source_tier


def main():
    payload = read_payload()
    filenames = payload.get('filenames')
    if not isinstance(filenames, list) or not filenames:
        emit_json({'ok': False, 'error': 'Expected non-empty filenames array'})
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
        emit_json({'ok': False, 'error': 'No valid filenames to prepare'})
        return

    om.AUDIO_OPT_DIR.mkdir(parents=True, exist_ok=True)
    om.IMG_OPT_DIR.mkdir(parents=True, exist_ok=True)

    if needs_ffmpeg(requested) and not om.check_ffmpeg():
        emit_json({
            'ok': False,
            'error': 'ffmpeg is required to prepare delivery files for one or more tracks',
        })
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
        delivery_name = Path(name).stem + '.mp3'
        if not (om.AUDIO_OPT_DIR / delivery_name).is_file():
            still_missing.append(name)

    emit_json({
        'ok': len(still_missing) == 0,
        'prepared': prepared,
        'failed': failed,
        'still_missing': still_missing,
    })


if __name__ == '__main__':
    main()
