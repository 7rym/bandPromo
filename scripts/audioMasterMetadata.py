import io
import json
import os
import shutil
import sys
from pathlib import Path

from mutagen import File
from mutagen.flac import FLAC
from mutagen.id3 import APIC, COMM, ID3, ID3NoHeaderError, TALB, TBPM, TCON, TDRC, TIT2, TKEY, TPE1, TRCK, USLT


if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8', errors='replace', line_buffering=True)
else:
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace', line_buffering=True)


SCRIPT_DIR = Path(__file__).parent
ROOT_DIR = SCRIPT_DIR.parent
AUDIO_ORIG_DIR = ROOT_DIR / 'media' / 'audio' / 'original'
AUDIO_MASTER_DIR = ROOT_DIR / 'media' / 'audio' / 'master'
IMG_ORIG_DIR = ROOT_DIR / 'media' / 'img' / 'original'


def respond(payload, exit_code=0):
    print(json.dumps(payload, ensure_ascii=False))
    sys.exit(exit_code)


def read_payload():
    raw = sys.stdin.read()
    if not raw.strip():
        respond({'ok': False, 'error': 'Empty request payload'}, 1)
    try:
        payload = json.loads(raw)
    except json.JSONDecodeError as exc:
        respond({'ok': False, 'error': f'Invalid JSON: {exc}'}, 1)
    if not isinstance(payload, dict):
        respond({'ok': False, 'error': 'Expected a JSON object payload'}, 1)
    return payload


def normalize_filename(value):
    filename = str(value or '').strip()
    if filename == '' or '/' in filename or '\\' in filename:
        respond({'ok': False, 'error': 'Invalid filename'}, 1)
    if Path(filename).suffix.lower() not in {'.flac', '.mp3', '.wav'}:
        respond({'ok': False, 'error': 'Unsupported audio filename'}, 1)
    return filename


def master_path_for(filename):
    stem = Path(filename).stem
    source_suffix = Path(filename).suffix.lower()
    preferred_suffixes = ['.flac', '.mp3', '.wav'] if source_suffix == '.wav' else [source_suffix, '.flac', '.mp3', '.wav']
    seen = set()
    for suffix in preferred_suffixes:
        candidate = AUDIO_MASTER_DIR / f'{stem}{suffix}'
        key = str(candidate).lower()
        if key in seen:
            continue
        seen.add(key)
        if candidate.exists() and candidate.is_file():
            return candidate

    path = AUDIO_MASTER_DIR / filename

    original_path = AUDIO_ORIG_DIR / filename
    if not original_path.exists() or not original_path.is_file():
        respond({'ok': False, 'error': 'Audio master file not found'}, 1)

    AUDIO_MASTER_DIR.mkdir(parents=True, exist_ok=True)
    shutil.copy2(str(original_path), str(path))
    return path


def get_sidecar_cover(filename):
    base_name = Path(filename).stem
    for ext in ('.jpg', '.jpeg', '.png'):
        candidate = IMG_ORIG_DIR / f'{base_name}{ext}'
        if candidate.exists() and candidate.is_file():
            return candidate.name
    return None


def read_text_tag(tags, *keys):
    for key in keys:
        if key not in tags:
            continue
        value = tags[key]
        if isinstance(value, list):
            return str(value[0]).strip()
        return str(value).strip()
    return ''


def read_track_value(raw_value):
    value = str(raw_value or '').strip()
    if '/' in value:
        value = value.split('/', 1)[0].strip()
    return value


def read_flac_lyrics(audio):
    for key in ('unsyncedlyrics', 'UNSYNCEDLYRICS', 'lyrics', 'LYRICS'):
        text = read_text_tag(audio, key)
        if text != '':
            return text
    return ''


def read_mp3_lyrics(tags):
    for key in tags.keys():
        if key.startswith('USLT'):
            return str(tags[key]).strip()
    return ''


def inspect_flac(path, audio):
    embedded_cover_present = bool(getattr(audio, 'pictures', None))
    return {
        'format': 'flac',
        'title': read_text_tag(audio, 'title', 'TITLE'),
        'artist': read_text_tag(audio, 'artist', 'ARTIST'),
        'album': read_text_tag(audio, 'album', 'ALBUM'),
        'date': read_text_tag(audio, 'date', 'DATE', 'year', 'YEAR'),
        'tracknumber': read_track_value(read_text_tag(audio, 'tracknumber', 'TRACKNUMBER')),
        'bpm': read_text_tag(audio, 'bpm', 'BPM', 'tempo', 'TEMPO'),
        'initialkey': read_text_tag(audio, 'initialkey', 'INITIALKEY', 'key', 'KEY'),
        'genre': read_text_tag(audio, 'genre', 'GENRE'),
        'comment': read_text_tag(audio, 'description', 'DESCRIPTION', 'comment', 'COMMENT'),
        'lyrics': read_flac_lyrics(audio),
        'embedded_cover_present': embedded_cover_present,
    }


def inspect_mp3(path):
    try:
        tags = ID3(str(path))
    except ID3NoHeaderError:
        tags = ID3()

    embedded_cover_present = any(key.startswith('APIC') for key in tags.keys())
    comment_text = ''
    for key in tags.keys():
        if key.startswith('COMM'):
            comment_text = str(tags[key])
            break

    return {
        'format': 'mp3',
        'title': read_text_tag(tags, 'TIT2'),
        'artist': read_text_tag(tags, 'TPE1'),
        'album': read_text_tag(tags, 'TALB'),
        'date': read_text_tag(tags, 'TDRC'),
        'tracknumber': read_track_value(read_text_tag(tags, 'TRCK')),
        'bpm': read_text_tag(tags, 'TBPM'),
        'initialkey': read_text_tag(tags, 'TKEY'),
        'genre': read_text_tag(tags, 'TCON'),
        'comment': comment_text,
        'lyrics': read_mp3_lyrics(tags),
        'embedded_cover_present': embedded_cover_present,
    }


def inspect_master(path):
    suffix = path.suffix.lower()
    audio = File(str(path))
    if audio is None or getattr(audio, 'info', None) is None:
        respond({'ok': False, 'error': 'Could not read audio metadata'}, 1)

    if suffix == '.flac':
        details = inspect_flac(path, FLAC(str(path)))
    elif suffix == '.mp3':
        details = inspect_mp3(path)
    else:
        respond({'ok': False, 'error': 'Unsupported audio master format'}, 1)

    duration = getattr(audio.info, 'length', 0) or 0
    bitrate = getattr(audio.info, 'bitrate', 0) or 0
    sample_rate = getattr(audio.info, 'sample_rate', 0) or 0
    bits_per_sample = getattr(audio.info, 'bits_per_sample', 0) or 0
    details.update({
        'ok': True,
        'filename': path.name,
        'duration_seconds': int(duration),
        'bitrate_kbps': int(round(bitrate / 1000)) if bitrate else 0,
        'sample_rate_hz': int(sample_rate) if sample_rate else 0,
        'bit_depth': int(bits_per_sample) if bits_per_sample else 0,
        'file_size_bytes': int(path.stat().st_size) if path.exists() else 0,
        'sidecar_cover': get_sidecar_cover(path.name),
    })
    return details


def normalize_field_text(fields, key):
    value = fields.get(key, '')
    if value is None:
        return ''
    return str(value).strip()


def update_flac(path, fields):
    audio = FLAC(str(path))

    def set_field(key, value):
        if value == '':
            if key in audio:
                del audio[key]
            return
        audio[key] = [value]

    title = normalize_field_text(fields, 'title')
    artist = normalize_field_text(fields, 'artist')
    album = normalize_field_text(fields, 'album')
    date = normalize_field_text(fields, 'date')
    tracknumber = normalize_field_text(fields, 'tracknumber')
    bpm = normalize_field_text(fields, 'bpm')
    initialkey = normalize_field_text(fields, 'initialkey')
    genre = normalize_field_text(fields, 'genre')
    comment = normalize_field_text(fields, 'comment')
    lyrics = normalize_field_text(fields, 'lyrics')

    set_field('title', title)
    set_field('artist', artist)
    set_field('album', album)
    set_field('date', date)
    set_field('tracknumber', tracknumber)
    set_field('bpm', bpm)
    set_field('initialkey', initialkey)
    set_field('genre', genre)

    if comment == '':
        for key in ('comment', 'description'):
            if key in audio:
                del audio[key]
    else:
        audio['comment'] = [comment]
        audio['description'] = [comment]

    if lyrics == '':
        for key in ('lyrics', 'unsyncedlyrics'):
            if key in audio:
                del audio[key]
    else:
        audio['lyrics'] = [lyrics]
        audio['unsyncedlyrics'] = [lyrics]

    audio.save()


def set_id3_text_frame(tags, frame_id, frame_class, value):
    tags.delall(frame_id)
    if value != '':
        tags.add(frame_class(encoding=3, text=[value]))


def update_mp3(path, fields):
    try:
        tags = ID3(str(path))
    except ID3NoHeaderError:
        tags = ID3()

    title = normalize_field_text(fields, 'title')
    artist = normalize_field_text(fields, 'artist')
    album = normalize_field_text(fields, 'album')
    date = normalize_field_text(fields, 'date')
    tracknumber = normalize_field_text(fields, 'tracknumber')
    bpm = normalize_field_text(fields, 'bpm')
    initialkey = normalize_field_text(fields, 'initialkey')
    genre = normalize_field_text(fields, 'genre')
    comment = normalize_field_text(fields, 'comment')
    lyrics = normalize_field_text(fields, 'lyrics')

    set_id3_text_frame(tags, 'TIT2', TIT2, title)
    set_id3_text_frame(tags, 'TPE1', TPE1, artist)
    set_id3_text_frame(tags, 'TALB', TALB, album)
    set_id3_text_frame(tags, 'TDRC', TDRC, date)
    set_id3_text_frame(tags, 'TRCK', TRCK, tracknumber)
    set_id3_text_frame(tags, 'TBPM', TBPM, bpm)
    set_id3_text_frame(tags, 'TKEY', TKEY, initialkey)
    set_id3_text_frame(tags, 'TCON', TCON, genre)

    tags.delall('COMM')
    if comment != '':
        tags.add(COMM(encoding=3, lang='eng', desc='', text=[comment]))

    tags.delall('USLT')
    if lyrics != '':
        tags.add(USLT(encoding=3, lang='eng', desc='', text=lyrics))

    tags.save(str(path), v2_version=3)


def main():
    payload = read_payload()
    action = str(payload.get('action') or '').strip().lower()
    filename = normalize_filename(payload.get('filename'))
    path = master_path_for(filename)

    if action == 'inspect':
        respond(inspect_master(path))

    if action == 'update':
        fields = payload.get('fields')
        if not isinstance(fields, dict):
            respond({'ok': False, 'error': 'Missing metadata fields'}, 1)

        if path.suffix.lower() == '.flac':
            update_flac(path, fields)
        elif path.suffix.lower() == '.mp3':
            update_mp3(path, fields)
        else:
            respond({'ok': False, 'error': 'Unsupported audio master format'}, 1)

        respond(inspect_master(path))

    respond({'ok': False, 'error': 'Unsupported action'}, 1)


if __name__ == '__main__':
    try:
        main()
    except Exception as exc:
        respond({'ok': False, 'error': str(exc)}, 1)