import io
import json
import os
import shutil
import sys
from pathlib import Path

SCRIPT_DIR = Path(__file__).parent
sys.path.insert(0, str(SCRIPT_DIR))
try:
    import bandpromo_python_path
    bandpromo_python_path.ensure_vendor_on_sys_path()
except Exception:
    pass

from mutagen import File
from mutagen.apev2 import APEv2, APENoHeaderError
from mutagen.flac import FLAC, Picture
from mutagen.id3 import APIC, COMM, ID3, ID3NoHeaderError, TALB, TBPM, TCON, TDRC, TIT2, TKEY, TPE1, TRCK, TXXX, USLT


if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8', errors='replace', line_buffering=True)
else:
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace', line_buffering=True)


ROOT_DIR = SCRIPT_DIR.parent
AUDIO_ORIG_DIR = ROOT_DIR / 'media' / 'audio' / 'original'
AUDIO_MASTER_DIR = ROOT_DIR / 'media' / 'audio' / 'master'
ASSET_REGISTRY_FILE = ROOT_DIR / 'data' / 'assets' / 'registry.json'
LIVING_COVER_TAG = 'BANDPROMO_LIVING_COVER'


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


def load_asset_for_filename(filename):
    safe_name = os.path.basename(str(filename or '').strip())
    if not safe_name or not ASSET_REGISTRY_FILE.exists():
        return None

    try:
        with open(str(ASSET_REGISTRY_FILE), 'r', encoding='utf-8') as handle:
            payload = json.load(handle)
    except Exception:
        return None

    if not isinstance(payload, dict):
        return None

    assets = payload.get('assets') if isinstance(payload.get('assets'), dict) else {}
    for asset in assets.values():
        if not isinstance(asset, dict):
            continue
        original_name = os.path.basename(str(asset.get('original_filename') or '').strip())
        master_name = os.path.basename(str(asset.get('master_filename') or '').strip())
        if safe_name in {original_name, master_name}:
            return asset

    return None


def master_path_for(filename):
    asset = load_asset_for_filename(filename)
    if isinstance(asset, dict):
        master_name = os.path.basename(str(asset.get('master_filename') or '').strip())
        if master_name:
            path = AUDIO_MASTER_DIR / master_name
            if path.exists() and path.is_file():
                return path

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
    target_name = filename
    if isinstance(asset, dict):
        registry_master = os.path.basename(str(asset.get('master_filename') or '').strip())
        if registry_master:
            target_name = registry_master
    shutil.copy2(str(original_path), str(AUDIO_MASTER_DIR / target_name))
    return AUDIO_MASTER_DIR / target_name


def get_sidecar_cover(filename):
    """Return registry display.cover (visual asset id). Do not guess {stem}.* sidecars."""
    asset = load_asset_for_filename(filename)
    if not isinstance(asset, dict):
        return None
    display = asset.get('display') if isinstance(asset.get('display'), dict) else {}
    cover = os.path.basename(str(display.get('cover') or '').strip())
    if not cover:
        return None
    stem = os.path.splitext(cover)[0]
    if stem != cover and stem.startswith('ast_'):
        return stem
    return cover


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


def read_living_cover_value(tags, audio=None):
    if audio is not None:
        text = read_text_tag(audio, LIVING_COVER_TAG)
        if text != '':
            return text

    for key in tags.keys():
        if not str(key).startswith('TXXX'):
            continue
        frame = tags[key]
        desc = str(getattr(frame, 'desc', '') or '').strip()
        if desc != LIVING_COVER_TAG:
            continue
        text = getattr(frame, 'text', [])
        if isinstance(text, list) and text:
            return str(text[0]).strip()
        return str(text).strip()
    return ''


def set_living_cover_tag(tags, audio, value):
    normalized = str(value or '').strip()
    if audio is not None:
        if normalized == '':
            if LIVING_COVER_TAG in audio:
                del audio[LIVING_COVER_TAG]
        else:
            audio[LIVING_COVER_TAG] = [normalized]

    for key in list(tags.keys()):
        if not str(key).startswith('TXXX'):
            continue
        frame = tags[key]
        desc = str(getattr(frame, 'desc', '') or '').strip()
        if desc == LIVING_COVER_TAG:
            del tags[key]

    if normalized != '':
        tags.add(TXXX(encoding=3, desc=LIVING_COVER_TAG, text=[normalized]))


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
        'living_cover': read_text_tag(audio, LIVING_COVER_TAG),
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
        'living_cover': read_living_cover_value(tags),
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
    living_cover = normalize_field_text(fields, 'living_cover')

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

    if living_cover == '':
        if LIVING_COVER_TAG in audio:
            del audio[LIVING_COVER_TAG]
    else:
        audio[LIVING_COVER_TAG] = [living_cover]

    audio.save()


def set_id3_text_frame(tags, frame_id, frame_class, value):
    tags.delall(frame_id)
    if value != '':
        tags.add(frame_class(encoding=3, text=[value]))


def strip_ape_tags(path):
    """Remove leftover APEv2 blocks so ID3 is the only MP3 tag source."""
    try:
        ape = APEv2(str(path))
        ape.delete()
    except APENoHeaderError:
        return
    except Exception:
        return


def update_mp3(path, fields):
    strip_ape_tags(path)
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
    living_cover = normalize_field_text(fields, 'living_cover')

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

    set_living_cover_tag(tags, None, living_cover)

    tags.save(str(path), v2_version=3)


def clear_embedded_cover(path):
    suffix = path.suffix.lower()
    if suffix == '.flac':
        audio = FLAC(str(path))
        audio.clear_pictures()
        audio.save()
        return

    if suffix == '.mp3':
        try:
            tags = ID3(str(path))
        except ID3NoHeaderError:
            return
        tags.delall('APIC')
        tags.save(str(path), v2_version=3)


def sync_embedded_cover(path, image_path):
    image_path = str(image_path or '').strip()
    if image_path == '':
        clear_embedded_cover(path)
        return

    source = Path(image_path)
    if not source.is_file():
        respond({'ok': False, 'error': 'Cover image file not found'}, 1)

    data = source.read_bytes()
    mime = 'image/png' if source.suffix.lower() == '.png' else 'image/jpeg'
    suffix = path.suffix.lower()

    if suffix == '.flac':
        audio = FLAC(str(path))
        audio.clear_pictures()
        picture = Picture()
        picture.type = 3
        picture.mime = mime
        picture.desc = 'Cover'
        picture.data = data
        audio.add_picture(picture)
        audio.save()
        return

    if suffix == '.mp3':
        try:
            tags = ID3(str(path))
        except ID3NoHeaderError:
            tags = ID3()
        tags.delall('APIC')
        tags.add(APIC(encoding=3, mime=mime, type=3, desc='Cover', data=data))
        tags.save(str(path), v2_version=3)
        return

    respond({'ok': False, 'error': 'Unsupported audio master format'}, 1)


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

    if action == 'sync_cover':
        image_path = str(payload.get('image_path') or '').strip()
        sync_embedded_cover(path, image_path)
        respond({'ok': True, 'filename': path.name})

    if action == 'sync_delivery_tags':
        # Ensure existing optimal MP3 stays tagless (no ID3/APIC rewrite after master edits).
        import optimizeMedia as om

        delivery_name = path.stem + '.mp3'
        delivery_path = om.AUDIO_OPT_DIR / delivery_name
        if not delivery_path.is_file():
            respond({
                'ok': False,
                'error': 'delivery_mp3_missing',
                'filename': path.name,
                'delivery': delivery_name,
            }, 0)

        om.strip_delivery_audio_tags(str(delivery_path))
        respond({
            'ok': True,
            'filename': path.name,
            'delivery': delivery_name,
            'synced': True,
            'stripped': True,
        })

    respond({'ok': False, 'error': 'Unsupported action'}, 1)


if __name__ == '__main__':
    try:
        main()
    except Exception as exc:
        respond({'ok': False, 'error': str(exc)}, 1)