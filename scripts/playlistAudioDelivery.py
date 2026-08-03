import json
import os
import sys
from pathlib import Path

import stdio_utf8
stdio_utf8.configure()

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


def load_playlist_entries():
    if not om.PLAY_CONFIG.exists():
        return []
    try:
        with open(str(om.PLAY_CONFIG), 'r', encoding='utf-8') as handle:
            loaded = json.load(handle)
    except Exception:
        return []
    return loaded if isinstance(loaded, list) else []


def resolve_master_filename(name):
    safe_name = os.path.basename(str(name or '').strip())
    if not safe_name:
        return ''

    asset = om.load_asset_for_filename(safe_name)
    if isinstance(asset, dict):
        master = os.path.basename(str(asset.get('master_filename') or '').strip())
        if master:
            return master

    master_path = om.AUDIO_MASTER_DIR / safe_name
    if master_path.is_file():
        return safe_name

    return safe_name


def needs_ffmpeg_for_masters(master_filenames):
    for master_filename in master_filenames:
        source_path, _source_tier = om.resolve_audio_working_path(master_filename)
        if not Path(source_path).exists():
            continue
        if om.audio_delivery_mode(source_path) == 'transcode':
            return True
    return False


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

    playlist = load_playlist_entries()
    cover_lookup = om.build_playlist_cover_lookup(playlist)

    targets = []
    missing_registry = []
    for name in requested:
        master_filename = resolve_master_filename(name)
        if not master_filename:
            missing_registry.append({'file': name, 'error': 'missing_master_filename'})
            continue
        asset = om.load_asset_for_filename(name)
        if not isinstance(asset, dict):
            missing_registry.append({'file': name, 'error': 'asset_not_registered'})
            continue
        targets.append({
            'requested': name,
            'master_filename': master_filename,
            'cover': cover_lookup.get(master_filename) or cover_lookup.get(Path(master_filename).stem),
        })

    om.AUDIO_OPT_DIR.mkdir(parents=True, exist_ok=True)
    om.IMG_OPT_DIR.mkdir(parents=True, exist_ok=True)

    master_filenames = [item['master_filename'] for item in targets]
    if needs_ffmpeg_for_masters(master_filenames) and not om.check_ffmpeg():
        emit_json({
            'ok': False,
            'error': 'ffmpeg is required to prepare delivery files for one or more tracks',
        })
        return

    prepared = []
    failed = list(missing_registry)

    for item in targets:
        master_filename = item['master_filename']
        ok = om.process_audio_delivery(master_filename, item.get('cover'))
        if ok:
            prepared.append(item['requested'])
        else:
            failed.append({'file': item['requested'], 'error': 'delivery_preparation_failed'})

    still_missing = []
    for item in targets:
        delivery_name = Path(item['master_filename']).stem + '.mp3'
        if not (om.AUDIO_OPT_DIR / delivery_name).is_file():
            still_missing.append(item['requested'])

    emit_json({
        'ok': len(still_missing) == 0 and not failed,
        'prepared': prepared,
        'failed': failed,
        'still_missing': still_missing,
    })


if __name__ == '__main__':
    main()
