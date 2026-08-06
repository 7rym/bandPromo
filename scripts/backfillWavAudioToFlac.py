import json
import os
import subprocess
import sys
from pathlib import Path


SCRIPT_DIR = Path(__file__).parent
ROOT_DIR = SCRIPT_DIR.parent
SPECIAL_DIR = ROOT_DIR / 'media' / 'special'
AUDIO_MASTER_DIR = ROOT_DIR / 'media' / 'audio' / 'master'
CONFIG_FILE = ROOT_DIR / 'web-config.json'
FFMPEG_LOCAL = SCRIPT_DIR / 'bin' / ('ffmpeg.exe' if os.name == 'nt' else 'ffmpeg')

SPECIAL_AUDIO_FIELDS = [
    ('media', 'welcome_audio'),
    ('media', 'loggedin_audio'),
    ('install', 'theme', 'welcome_audio'),
    ('install', 'theme', 'loggedin_audio'),
]


def find_ffmpeg():
    env_path = os.environ.get('FFMPEG_PATH', '').strip()
    if env_path and Path(env_path).is_file():
        return env_path
    if FFMPEG_LOCAL.is_file():
        return str(FFMPEG_LOCAL)
    return 'ffmpeg'


def convert_wav_to_flac(ffmpeg_path, source_path, target_path):
    result = subprocess.run(
        [
            ffmpeg_path,
            '-y',
            '-i',
            str(source_path),
            '-map_metadata',
            '0',
            '-vn',
            '-c:a',
            'flac',
            str(target_path),
        ],
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        universal_newlines=True,
    )
    if result.returncode != 0 or not target_path.is_file():
        if target_path.exists():
            target_path.unlink()
        raise RuntimeError(
            (result.stdout or '').strip()
            or 'ffmpeg failed for {0}'.format(source_path.name)
        )


def load_config():
    if not CONFIG_FILE.exists():
        return {}
    with open(CONFIG_FILE, 'r', encoding='utf-8') as handle:
        data = json.load(handle)
    return data if isinstance(data, dict) else {}


def save_config(config):
    with open(CONFIG_FILE, 'w', encoding='utf-8') as handle:
        json.dump(config, handle, indent=4, ensure_ascii=False)
        handle.write('\n')


def get_nested(config, path):
    cursor = config
    for key in path[:-1]:
        if not isinstance(cursor, dict):
            return None, None
        cursor = cursor.get(key)
    if not isinstance(cursor, dict):
        return None, None
    return cursor, path[-1]


def rewrite_special_audio_paths(config, replacements):
    updated = []
    for field_path in SPECIAL_AUDIO_FIELDS:
        parent, key = get_nested(config, field_path)
        if parent is None:
            continue
        raw_value = str(parent.get(key) or '').strip()
        if raw_value in replacements:
            parent[key] = replacements[raw_value]
            updated.append((field_path, replacements[raw_value]))
    return updated


def backfill_directory(ffmpeg_path, directory):
    converted = []
    for wav_path in sorted(directory.glob('*.wav')):
        flac_path = wav_path.with_suffix('.flac')
        convert_wav_to_flac(ffmpeg_path, wav_path, flac_path)
        wav_path.unlink()
        converted.append((wav_path, flac_path))
    return converted


def main():
    ffmpeg_path = find_ffmpeg()
    config = load_config()

    special_converted = backfill_directory(ffmpeg_path, SPECIAL_DIR) if SPECIAL_DIR.exists() else []
    master_converted = backfill_directory(ffmpeg_path, AUDIO_MASTER_DIR) if AUDIO_MASTER_DIR.exists() else []

    replacements = {
        '/media/special/' + source.name: '/media/special/' + target.name
        for source, target in special_converted
    }
    updated_paths = rewrite_special_audio_paths(config, replacements) if replacements else []
    if updated_paths:
        save_config(config)

    print('WAV backfill complete')
    print(f'Special WAV converted: {len(special_converted)}')
    for source, target in special_converted:
        print(f'  {source.name} -> {target.name}')
    print(f'Audio master WAV converted: {len(master_converted)}')
    for source, target in master_converted:
        print(f'  {source.name} -> {target.name}')
    print(f'Config paths updated: {len(updated_paths)}')
    for field_path, new_value in updated_paths:
        print(f"  {'.'.join(field_path)} -> {new_value}")


if __name__ == '__main__':
    try:
        main()
    except Exception as exc:
        print(f'ERROR: {exc}')
        sys.exit(1)