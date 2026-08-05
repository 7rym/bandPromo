import sys

import stdio_utf8
stdio_utf8.configure()

import hashlib
import os
import json
import re
import subprocess
from datetime import datetime, timezone
from pathlib import Path
from mutagen import File

from php_cli import resolve_php_cli

# Supported audio file extensions
SUPPORTED_EXTENSIONS = ('.flac', '.mp3', '.wav')
KNOWN_AUDIO_EXTENSIONS = SUPPORTED_EXTENSIONS + ('.wav', '.aif', '.aiff', '.m4a', '.aac', '.ogg', '.wma')

# Find the root directory (scripts/..)
SCRIPT_DIR    = Path(__file__).parent
ROOT_DIR      = SCRIPT_DIR.parent
AUDIO_ORIG_DIR  = ROOT_DIR / 'media' / 'audio' / 'original'
AUDIO_MASTER_DIR = ROOT_DIR / 'media' / 'audio' / 'master'
IMG_ORIG_DIR    = ROOT_DIR / 'media' / 'img'   / 'original'
PHOTO_ORIG_DIR  = ROOT_DIR / 'media' / 'photo' / 'original'
SPECIAL_DIR     = ROOT_DIR / 'media' / 'special'
VALIDATION_FILE = ROOT_DIR / 'data' / 'validation' / 'playlist-validation.json'
MEDIA_LIBRARY_STATE_FILE = ROOT_DIR / 'data' / 'media-library-state.json'
ASSET_REGISTRY_FILE = ROOT_DIR / 'data' / 'assets' / 'registry.json'
PLAYLIST_REGISTRY_FILE = ROOT_DIR / 'data' / 'playlists' / 'registry.json'
PLAYLISTS_DIR = ROOT_DIR / 'data' / 'playlists'
CONFIG_FILE = ROOT_DIR / 'web-config.json'
CONFIG_COVER_BASENAME = 'configured_release_cover'
BANDPROMO_RELEASE_DEMO_ID = 'bandpromo-demo'
BANDPROMO_RELEASE_DEFAULT_ID = 'primary'
BANDPROMO_PLAYLIST_DEMO_ID = 'bandpromo-demo'


def normalize_playlist_id(value):
    slug = str(value or '').strip().lower().replace('_', '-')
    if not slug or not slug[0].isalpha():
        return ''
    if not re.match(r'^[a-z][a-z0-9-]{0,47}$', slug):
        return ''
    return slug


def load_playlist_registry():
    if not PLAYLIST_REGISTRY_FILE.exists():
        return []

    try:
        with open(str(PLAYLIST_REGISTRY_FILE), 'r', encoding='utf-8') as handle:
            payload = json.load(handle)
    except Exception:
        return []

    playlists = payload.get('playlists') if isinstance(payload, dict) else None
    if not isinstance(playlists, list):
        return []

    return [entry for entry in playlists if isinstance(entry, dict)]


def publish_date_sort_value(publish_date):
    publish_date = str(publish_date or '').strip()
    if re.match(r'^\d{4}$', publish_date):
        return int(publish_date + '0101')

    try:
        parsed = datetime.strptime(publish_date, '%Y-%m-%d')
        return int(parsed.strftime('%Y%m%d'))
    except Exception:
        return 0


def resolve_build_playlist_id():
    env_id = normalize_playlist_id(os.environ.get('BANDPROMO_PLAYLIST_ID', ''))
    if env_id:
        return env_id

    now = int(datetime.now(timezone.utc).strftime('%Y%m%d'))
    candidates = []
    for entry in load_playlist_registry():
        playlist_id = normalize_playlist_id(entry.get('id'))
        if not playlist_id:
            continue
        publish_value = publish_date_sort_value(entry.get('publish_date'))
        if publish_value <= 0 or publish_value > now:
            continue
        candidates.append((publish_value, playlist_id))

    if candidates:
        candidates.sort(reverse=True)
        return candidates[0][1]

    for entry in load_playlist_registry():
        playlist_id = normalize_playlist_id(entry.get('id'))
        if playlist_id:
            return playlist_id

    return BANDPROMO_PLAYLIST_DEMO_ID


def playlist_document_path(playlist_id):
    normalized = normalize_playlist_id(playlist_id)
    if not normalized:
        return None
    return PLAYLISTS_DIR / f'{normalized}.json'


def normalize_title_fallback(filename):
    stem = Path(filename).stem
    cleaned = stem.replace('_', ' ').replace('-', ' ').strip()
    return cleaned or stem or filename


def is_bundled_placeholder(filename):
    return str(filename).startswith('bandPromo_')


def load_media_library_state():
    if not MEDIA_LIBRARY_STATE_FILE.exists():
        return {'hidden': {}, 'assets': {}}

    try:
        with open(str(MEDIA_LIBRARY_STATE_FILE), 'r', encoding='utf-8') as f:
            payload = json.load(f)
    except Exception as e:
        print(f"Warning: Could not read media-library-state.json: {e}")
        return {'hidden': {}, 'assets': {}}

    if not isinstance(payload, dict):
        return {'hidden': {}, 'assets': {}}

    hidden = payload.get('hidden') if isinstance(payload.get('hidden'), dict) else {}
    assets = payload.get('assets') if isinstance(payload.get('assets'), dict) else {}
    return {'hidden': hidden, 'assets': assets}


def save_media_library_state(state):
    try:
        MEDIA_LIBRARY_STATE_FILE.parent.mkdir(parents=True, exist_ok=True)
        with open(str(MEDIA_LIBRARY_STATE_FILE), 'w', encoding='utf-8') as f:
            json.dump(state, f, indent=4, ensure_ascii=False)
            f.write('\n')
    except Exception as e:
        print(f"Warning: Could not write media-library-state.json: {e}")


def cleanup_stale_configured_release_covers(keep_filename=None):
    keep_name = os.path.basename(str(keep_filename or '').strip())
    removed = []
    if not IMG_ORIG_DIR.exists():
        return removed

    for entry in IMG_ORIG_DIR.iterdir():
        if not entry.is_file():
            continue
        if entry.name.lower() == 'desktop.ini':
            continue
        if Path(entry.name).stem != CONFIG_COVER_BASENAME:
            continue
        if keep_name and entry.name == keep_name:
            continue
        removed.append(entry.name)
        try:
            entry.unlink()
        except Exception as e:
            print(f"Warning: Could not remove stale configured release cover {entry.name}: {e}")

    if removed:
        print(f"ℹ️  Removed stale configured release cover variant(s): {', '.join(removed)}")

    return removed


def record_cover_asset(filename, role, origin, linked_audio=None, linked_config=None):
    safe_name = os.path.basename(str(filename or '').strip())
    if not safe_name:
        return

    state = load_media_library_state()
    assets = state.setdefault('assets', {})
    key = f'illustrations/{safe_name}'
    record = assets.get(key) if isinstance(assets.get(key), dict) else {}
    existing_origin = str(record.get('origin') or '').strip()
    build_origins = {'build-extracted', 'build-configured', 'build-sidecar-copy'}
    if existing_origin in build_origins and origin == 'user-upload':
        origin = existing_origin
    record.update({
        'role': role,
        'origin': origin,
        'recorded_at': datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S'),
    })
    if linked_audio:
        record['linked_audio'] = linked_audio
    if linked_config:
        record['linked_config'] = linked_config
    assets[key] = record
    save_media_library_state(state)


def load_hidden_media_keys():
    state = load_media_library_state()
    hidden = state.get('hidden') if isinstance(state.get('hidden'), dict) else {}
    return {str(key) for key, value in hidden.items() if value}


def has_visible_user_audio_uploads(hidden_keys):
    if not AUDIO_ORIG_DIR.exists():
        return False

    for entry in AUDIO_ORIG_DIR.iterdir():
        if not entry.is_file():
            continue
        if entry.name.lower() == 'desktop.ini':
            continue
        if is_bundled_placeholder(entry.name):
            continue
        if f'audio/{entry.name}' in hidden_keys:
            continue
        return True

    return False


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


def resolve_playlist_file_name(filename):
    """Return the canonical playlist/build file identity (master filename when catalogued)."""
    safe_name = os.path.basename(str(filename or '').strip())
    if safe_name == '':
        return ''

    asset = load_asset_for_filename(safe_name)
    if isinstance(asset, dict):
        master_name = os.path.basename(str(asset.get('master_filename') or '').strip())
        if master_name:
            return master_name

    return safe_name


def load_playlist_document_master_order(playlist_id=None):
    playlist_id = normalize_playlist_id(playlist_id) or resolve_build_playlist_id()
    doc_path = playlist_document_path(playlist_id)
    if doc_path is None or not doc_path.exists():
        return []

    try:
        with open(str(doc_path), 'r', encoding='utf-8') as handle:
            payload = json.load(handle)
    except Exception:
        return []

    if not isinstance(payload, dict):
        return []

    order = []
    entries = payload.get('entries')
    if not isinstance(entries, list):
        return order

    for entry in entries:
        if not isinstance(entry, dict):
            continue
        master_name = os.path.basename(str(entry.get('master_file') or entry.get('file') or '').strip())
        if master_name:
            order.append(master_name)

    return order


def resolve_audio_working_path(filename):
    safe_name = os.path.basename(str(filename or '').strip())
    asset = load_asset_for_filename(safe_name)
    if isinstance(asset, dict):
        master_name = os.path.basename(str(asset.get('master_filename') or '').strip())
        if master_name:
            path = AUDIO_MASTER_DIR / master_name
            if path.exists() and path.is_file():
                return path

    stem = Path(safe_name).stem
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

    return AUDIO_ORIG_DIR / safe_name


def load_asset_release_map():
    if not ASSET_REGISTRY_FILE.exists():
        return {}

    try:
        with open(str(ASSET_REGISTRY_FILE), 'r', encoding='utf-8') as handle:
            payload = json.load(handle)
    except Exception:
        return {}

    if not isinstance(payload, dict):
        return {}

    release_map = {}
    assets = payload.get('assets') if isinstance(payload.get('assets'), dict) else {}
    for asset in assets.values():
        if not isinstance(asset, dict):
            continue
        if str(asset.get('kind') or '').strip() != 'audio':
            continue
        master_filename = os.path.basename(str(asset.get('master_filename') or '').strip())
        release_id = str(asset.get('release_id') or '').strip()
        if master_filename and release_id:
            release_map[master_filename] = release_id

    return release_map


def resolve_audio_release_id(filename, release_map):
    release_id = str(release_map.get(filename) or '').strip()
    if release_id:
        return release_id
    if is_bundled_placeholder(filename):
        return BANDPROMO_RELEASE_DEMO_ID
    return BANDPROMO_RELEASE_DEFAULT_ID


def collect_audio_source_files(release_filter=None):
    supported = []
    unsupported = []
    hidden_bundled = []
    hidden_keys = load_hidden_media_keys()
    release_filter = str(release_filter or '').strip()
    if release_filter in ('', 'all'):
        release_filter = ''
    release_map = load_asset_release_map()

    if not AUDIO_ORIG_DIR.exists():
        return supported, unsupported, hidden_bundled

    for entry in sorted(AUDIO_ORIG_DIR.iterdir(), key=lambda item: item.name.lower()):
        if not entry.is_file():
            continue

        release_id = resolve_audio_release_id(entry.name, release_map)
        if release_filter and release_id != release_filter:
            continue

        if is_bundled_placeholder(entry.name) and f'audio/{entry.name}' in hidden_keys:
            hidden_bundled.append(entry)
            continue

        suffix = entry.suffix.lower()
        if suffix in SUPPORTED_EXTENSIONS:
            supported.append(entry)
        elif suffix in KNOWN_AUDIO_EXTENSIONS:
            unsupported.append(entry)

    return supported, unsupported, hidden_bundled


def build_metadata_warnings(filename, info):
    warnings = []
    title = str(info.get('title') or '').strip()
    title_fallback = normalize_title_fallback(filename)
    artist = str(info.get('artist') or '').strip()
    album = str(info.get('album') or '').strip()
    track = info.get('track', 999)
    lyrics = str(info.get('lyrics') or '')
    cover = str(info.get('cover') or '').strip()

    if not title:
        warnings.append('missing_title_tag')
    elif not info.get('title_from_tag') and (title == filename or title == title_fallback):
        warnings.append('missing_title_tag')
    if not artist or artist == 'Unknown Artist':
        if not info.get('artist_from_tag'):
            warnings.append('missing_artist_tag')
    if not album or album == 'Unknown Album':
        if not info.get('album_from_tag'):
            warnings.append('missing_album_tag')
    if track == 999 and not info.get('track_from_tag'):
        warnings.append('missing_track_number')
    if not lyrics.strip():
        warnings.append('missing_lyrics')
    if not cover:
        warnings.append('missing_cover_art')

    return warnings


def write_validation_report(report):
    if not isinstance(report, dict):
        report = {}
    report['generated_at'] = datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%S+00:00')
    try:
        with open(str(VALIDATION_FILE), 'w', encoding='utf-8') as f:
            json.dump(report, f, indent=4, ensure_ascii=False)
    except Exception as e:
        print(f"Warning: Could not save validation report: {e}")


def load_web_config():
    if not CONFIG_FILE.exists():
        return {}

    try:
        with open(str(CONFIG_FILE), 'r', encoding='utf-8') as f:
            return json.load(f)
    except Exception as e:
        print(f"Warning: Could not read web-config.json: {e}")
        return {}


def get_configured_cover_filename():
    config = load_web_config()
    raw_cover_path = (((config.get('media') or {}).get('cover')) or '').strip()
    if not raw_cover_path:
        return None

    if raw_cover_path.startswith(('http://', 'https://')):
        print(f"Warning: Configured media.cover must be a local file path, got URL: {raw_cover_path}")
        return None

    relative_cover_path = raw_cover_path.lstrip('/\\')
    source_path = ROOT_DIR / Path(relative_cover_path)
    if not source_path.exists() or not source_path.is_file():
        print(f"Warning: Configured media.cover not found: {source_path}")
        print("         Fix: Admin → Content → Branding → base brand → Shell media → Poster / share image")
        print("         (Saving Base syncs media.cover / social.share_image in web-config.json.)")
        if source_path.name.lower().startswith('bandpromo_'):
            print("         Note: bandPromo_* is a bundled demo filename — restore seed media or retarget the poster.")
        return None

    suffix = source_path.suffix.lower()
    if suffix not in ('.jpg', '.jpeg', '.png', '.webp'):
        print(f"Warning: Configured media.cover must be an image file, got: {source_path.name}")
        return None

    target_name = f"{CONFIG_COVER_BASENAME}{suffix}"
    target_path = IMG_ORIG_DIR / target_name

    try:
        IMG_ORIG_DIR.mkdir(parents=True, exist_ok=True)
        source_bytes = source_path.read_bytes()
        target_needs_write = (not target_path.exists()) or (target_path.read_bytes() != source_bytes)
        if target_needs_write:
            target_path.write_bytes(source_bytes)
        record_cover_asset(
            target_name,
            'release-fallback',
            'build-configured',
            linked_config='media.cover',
        )
    except Exception as e:
        print(f"Warning: Could not prepare configured cover fallback: {e}")
        return None

    return target_name

def get_lyrics(filename):
    """
    Looks for lyrics in ID3/Vorbis tags first (USLT/LYRICS).
    Falls back to a .txt file with the same name if not found.
    """
    # 1. Check embedded metadata
    try:
        audio = File(filename)
        if audio and audio.tags:
            tags = audio.tags
            
            # Check direct keys first (FLAC/Vorbis/APE commonly use these)
            # UNSYNCEDLYRICS is typical for APEv2 tags on MP3
            for tag_name in ['LYRICS', 'UNSYNCEDLYRICS']:
                if tag_name in tags:
                    val = tags[tag_name]
                    # Mutagen can return either a list or a string depending on the audio format.
                    return val[0] if isinstance(val, list) else str(val)
            
            # MP3 ID3 (USLT frames)
            # USLT keys are dynamic and look like 'USLT::eng', 'USLT::XXX'
            for key in tags.keys():
                if key.startswith('USLT'):
                    return str(tags[key])

    except Exception as e:
        # Only log error to console if we can't read tags; fall back to text file
        print(f"Could not read lyrics tags from {filename}: {e}")

    # 2. Fallback to text file
    base_name = os.path.splitext(filename)[0]
    txt_filename = base_name + ".txt"
    
    if os.path.exists(txt_filename):
        try:
            with open(txt_filename, 'r', encoding='utf-8') as f:
                return f.read()
        except Exception as e:
            return f"Error reading text file: {str(e)}"
    
    return ""

def get_description(filename):
    """
    Reads DESCRIPTION or COMMENT tag from audio file.
    Used to display track description in the player.
    """
    try:
        path = Path(str(filename))
        suffix = path.suffix.lower()

        if suffix == '.mp3':
            from mutagen.id3 import ID3, ID3NoHeaderError

            try:
                tags = ID3(str(path))
            except ID3NoHeaderError:
                return ''

            for key in tags.keys():
                if str(key).startswith('COMM'):
                    text = str(tags[key]).strip()
                    if text:
                        return text
            return ''

        audio = File(str(path))
        if audio and audio.tags:
            tags = audio.tags

            # Check DESCRIPTION first (standard for FLAC/Vorbis)
            if 'DESCRIPTION' in tags:
                val = tags['DESCRIPTION']
                return val[0] if isinstance(val, list) else str(val)

            # Fallback to COMMENT
            if 'COMMENT' in tags:
                val = tags['COMMENT']
                return val[0] if isinstance(val, list) else str(val)

    except Exception as e:
        print(f"Could not read description tag from {filename}: {e}")

    return ""

LIVING_COVER_TAG = 'BANDPROMO_LIVING_COVER'

def get_living_cover(filename):
    """
    Reads the BANDPROMO_LIVING_COVER tag from the audio master.
    Value is the video original filename assigned in the track editor.
    """
    try:
        path = Path(str(filename))
        suffix = path.suffix.lower()

        if suffix == '.mp3':
            from mutagen.id3 import ID3, ID3NoHeaderError

            try:
                tags = ID3(str(path))
            except ID3NoHeaderError:
                return ''

            for key in tags.keys():
                if not str(key).startswith('TXXX'):
                    continue
                frame = tags[key]
                desc = str(getattr(frame, 'desc', '') or '').strip()
                if desc != LIVING_COVER_TAG:
                    continue
                text = getattr(frame, 'text', [])
                if isinstance(text, list) and text:
                    return os.path.basename(str(text[0]).strip())
                return os.path.basename(str(text).strip())
            return ''

        audio = File(str(path))
        if audio and audio.tags and LIVING_COVER_TAG in audio.tags:
            val = audio.tags[LIVING_COVER_TAG]
            text = val[0] if isinstance(val, list) else str(val)
            return os.path.basename(str(text).strip())
    except Exception as e:
        print(f"Could not read living cover tag from {filename}: {e}")

    return ''

def get_track_number(filename):
    """
    Helper function to find track number for sorting.
    Returns 999 if not found so those files sort to the end.
    """
    try:
        audio = File(filename)
        if audio is None: return 999
        
        track = "999"
        
        # Check FLAC/Vorbis (TRACKNUMBER)
        if 'TRACKNUMBER' in audio:
            track = audio['TRACKNUMBER'][0]
        # Check MP3 ID3 (TRCK)
        elif audio.tags and 'TRCK' in audio.tags:
            track = str(audio.tags['TRCK'])
            
        # Handle format like "1/10" (track 1 of 10)
        if '/' in track:
            track = track.split('/')[0]
            
        if track.isdigit():
            return int(track)
            
    except:
        pass
        
    return 999

def get_metadata(filename):
    """
    Reads title, artist, album and duration from the file.
    """
    try:
        audio = File(filename)
        if audio is None:
            return {"title": filename, "artist": "Unknown", "album": "Unknown", "duration": 0}

        # Default values
        title = filename
        artist = "Unknown Artist"
        album = "Unknown Album"
        duration = get_duration_accurate(filename)

        # Read tags (handles both ID3 and Vorbis Comments)
        tags = audio.tags
        
        if tags:
            # FLAC / Vorbis comments
            if 'TITLE' in tags: title = tags['TITLE'][0]
            if 'ARTIST' in tags: artist = tags['ARTIST'][0]
            if 'ALBUM' in tags: album = tags['ALBUM'][0]
            
            # MP3 ID3 (TIT2, TPE1, TALB)
            if 'TIT2' in tags: title = str(tags['TIT2'])
            if 'TPE1' in tags: artist = str(tags['TPE1'])
            if 'TALB' in tags: album = str(tags['TALB'])

        return {
            "title": title,
            "artist": artist,
            "album": album,
            "duration": duration
        }

    except Exception as e:
        print(f"Warning: Could not read metadata for {filename}: {e}")
        return {"title": filename, "artist": "Unknown", "album": "Unknown", "duration": 0}


def resolve_pool_cover_filename(cover_name):
    """Return basename if the cover exists in any visual intake folder."""
    cover_name = os.path.basename(str(cover_name or '').strip())
    if not cover_name:
        return None
    for folder in (IMG_ORIG_DIR, PHOTO_ORIG_DIR, SPECIAL_DIR):
        if (folder / cover_name).exists():
            return cover_name
    return None


def get_assigned_cover_from_registry(audio_filename):
    """Operator-assigned pool cover from asset registry display.cover."""
    asset = load_asset_for_filename(audio_filename)
    if not isinstance(asset, dict):
        return None
    display = asset.get('display') if isinstance(asset.get('display'), dict) else {}
    cover = resolve_pool_cover_filename(display.get('cover'))
    return cover


def load_asset_registry_payload():
    if not ASSET_REGISTRY_FILE.exists():
        return {'assets': {}}
    try:
        with open(str(ASSET_REGISTRY_FILE), 'r', encoding='utf-8') as handle:
            payload = json.load(handle)
    except Exception:
        return {'assets': {}}
    return payload if isinstance(payload, dict) else {'assets': {}}


def save_asset_registry_payload(payload):
    ASSET_REGISTRY_FILE.parent.mkdir(parents=True, exist_ok=True)
    with open(str(ASSET_REGISTRY_FILE), 'w', encoding='utf-8') as handle:
        json.dump(payload, handle, indent=2, ensure_ascii=False)
        handle.write('\n')


def find_visual_original_by_content_sha256(digest):
    digest = str(digest or '').strip().lower()
    if not digest:
        return None
    payload = load_asset_registry_payload()
    assets = payload.get('assets') if isinstance(payload.get('assets'), dict) else {}
    for asset in assets.values():
        if not isinstance(asset, dict) or asset.get('kind') != 'visual':
            continue
        if str(asset.get('media_type') or '') != 'image':
            continue
        if str(asset.get('content_sha256') or '').strip().lower() == digest:
            name = os.path.basename(str(asset.get('original_filename') or '').strip())
            if name:
                return name
    return None


def set_audio_display_cover(audio_filename, cover_filename):
    """Point audio asset display.cover at an existing pool original (no file copy)."""
    audio_name = os.path.basename(str(audio_filename or '').strip())
    cover_name = os.path.basename(str(cover_filename or '').strip())
    if not audio_name or not cover_name:
        return False

    payload = load_asset_registry_payload()
    assets = payload.get('assets') if isinstance(payload.get('assets'), dict) else {}
    for asset_id, asset in list(assets.items()):
        if not isinstance(asset, dict) or asset.get('kind') != 'audio':
            continue
        original_name = os.path.basename(str(asset.get('original_filename') or '').strip())
        master_name = os.path.basename(str(asset.get('master_filename') or '').strip())
        if audio_name not in {original_name, master_name}:
            continue
        display = asset.get('display') if isinstance(asset.get('display'), dict) else {}
        display['cover'] = cover_name
        asset['display'] = display
        assets[asset_id] = asset
        payload['assets'] = assets
        save_asset_registry_payload(payload)
        return True
    return False


def ensure_visual_content_sha256_on_file(filename, digest, intake_bucket='img'):
    """Store content_sha256 on the visual registry row for this original filename."""
    safe_name = os.path.basename(str(filename or '').strip())
    digest = str(digest or '').strip().lower()
    if not safe_name or not digest:
        return
    payload = load_asset_registry_payload()
    assets = payload.get('assets') if isinstance(payload.get('assets'), dict) else {}
    for asset_id, asset in list(assets.items()):
        if not isinstance(asset, dict) or asset.get('kind') != 'visual':
            continue
        original_name = os.path.basename(str(asset.get('original_filename') or '').strip())
        if original_name != safe_name:
            continue
        if str(asset.get('content_sha256') or '').strip().lower() == digest:
            return
        asset['content_sha256'] = digest
        if not str(asset.get('intake_bucket') or '').strip():
            asset['intake_bucket'] = intake_bucket
        assets[asset_id] = asset
        payload['assets'] = assets
        save_asset_registry_payload(payload)
        return


def read_embedded_cover_bytes(filename):
    """Return (bytes, ext) for the first embedded picture, or None."""
    try:
        audio = File(filename)
        if audio is None:
            return None

        if audio.tags:
            for key in audio.tags.keys():
                if key.startswith('APIC') or key.startswith('APIC:'):
                    try:
                        apic = audio.tags[key]
                        data = getattr(apic, 'data', None)
                        mime = getattr(apic, 'mime', 'image/jpeg')
                        if data:
                            ext = '.png' if 'png' in str(mime).lower() else '.jpg'
                            return (data, ext)
                    except Exception as e:
                        print(f"✗ Error reading ID3 APIC: {e}")

        pics = getattr(audio, 'pictures', None)
        if pics and len(pics) > 0:
            try:
                pic = pics[0]
                data = getattr(pic, 'data', None)
                mime = getattr(pic, 'mime', 'image/jpeg')
                if data:
                    ext = '.png' if 'png' in str(mime).lower() else '.jpg'
                    return (data, ext)
            except Exception as e:
                print(f"✗ Error reading FLAC picture: {e}")
    except Exception as e:
        print(f"✗ Error reading file for embedded cover: {e}")

    return None


def extract_embedded_cover_to_stem(filename, base_filename):
    """
    Legacy fallback: reuse an existing Visual original with the same content hash,
    or write embedded art to media/img/original/{stem}.ext once.
    Returns basename or None.
    """
    embedded = read_embedded_cover_bytes(filename)
    if not embedded:
        return None

    data, ext = embedded
    digest = hashlib.sha256(data).hexdigest()
    existing = find_visual_original_by_content_sha256(digest)
    if existing:
        set_audio_display_cover(filename, existing)
        print(f"✓ Reused pool cover for embedded art (hash match): {existing}")
        return existing

    # Also match any existing pool original with identical bytes (hash not yet stored).
    for folder in (IMG_ORIG_DIR, PHOTO_ORIG_DIR):
        if not folder.exists():
            continue
        for entry in folder.iterdir():
            if not entry.is_file():
                continue
            if entry.name.lower() == 'desktop.ini':
                continue
            try:
                if hashlib.sha256(entry.read_bytes()).hexdigest() == digest:
                    ensure_visual_content_sha256_on_file(
                        entry.name,
                        digest,
                        'photo' if folder == PHOTO_ORIG_DIR else 'img',
                    )
                    set_audio_display_cover(filename, entry.name)
                    print(f"✓ Linked embedded art to existing pool file: {entry.name}")
                    return entry.name
            except Exception:
                continue

    outname_full = IMG_ORIG_DIR / (base_filename + ext)
    outname_filename = base_filename + ext
    if not outname_full.exists():
        IMG_ORIG_DIR.mkdir(parents=True, exist_ok=True)
        with open(str(outname_full), 'wb') as imgf:
            imgf.write(data)
        print(f"✓ Extracted embedded cover (legacy fallback): {outname_filename}")
    ensure_visual_content_sha256_on_file(outname_filename, digest, 'img')
    set_audio_display_cover(filename, outname_filename)
    return outname_filename


def get_cover(filename):
    """
    Priority order:
    1) Operator-assigned Visual pool cover (asset registry display.cover)
    2) Legacy stem sidecar in media/img/original/{stem}.ext (no extract)
    3) Extract embedded art only when no assigned/sidecar cover exists
       (or link to an existing pool file with identical bytes)
    4) Configured release cover from web-config.json
    Returns (filename, source) where source is one of:
    assigned, sidecar, embedded, configured, missing
    """
    base = os.path.splitext(filename)[0]
    base_filename = os.path.basename(base)

    assigned = get_assigned_cover_from_registry(filename)
    if assigned:
        return (assigned, 'assigned')

    for ext in ('.jpg', '.jpeg', '.png', '.webp'):
        candidate_full = IMG_ORIG_DIR / (base_filename + ext)
        if candidate_full.exists():
            return (base_filename + ext, 'sidecar')

    extracted = extract_embedded_cover_to_stem(filename, base_filename)
    if extracted:
        return (extracted, 'embedded')

    configured_cover = get_configured_cover_filename()
    if configured_cover:
        return (configured_cover, 'configured')

    return (None, 'missing')


def get_mp3_duration_by_frames(filename):
    """Return the duration of an MP3 by parsing its frames manually.
    This ignores trailing garbage or tags and is accurate even if the headers
    report the wrong length/bitrate. The result is an integer number of seconds.
    """
    try:
        with open(filename, 'rb') as f:
            data = f.read()
    except Exception:
        return 0

    pos = 0
    # skip ID3v2 header if present
    if data.startswith(b'ID3') and len(data) >= 10:
        # size stored in syncsafe ints at 6-9
        size_bytes = data[6:10]
        size = ((size_bytes[0] & 0x7f) << 21) | ((size_bytes[1] & 0x7f) << 14) | ((size_bytes[2] & 0x7f) << 7) | (size_bytes[3] & 0x7f)
        pos = 10 + size

    total_duration = 0.0
    length = len(data)

    # lookup tables
    bitrate_table = {
        # version: {layer: [bitrates_indexed_by_value]}
        '1': {
            '3': [None,32,40,48,56,64,80,96,112,128,160,192,224,256,320,None],
            '2': [None,32,48,56,64,80,96,112,128,160,192,224,256,320,384,None],
            '1': [None,32,64,96,128,160,192,224,256,288,320,352,384,416,448,None],
        },
        '2': {
            '3': [None,32,40,48,56,64,80,96,112,128,160,192,224,256,320,None],
            '2': [None,8,16,24,32,40,48,56,64,80,96,112,128,144,160,None],
            '1': [None,32,48,56,64,80,96,112,128,144,160,176,192,224,256,None],
        }
    }
    sample_rate_table = {
        '1': [44100,48000,32000,None],
        '2': [22050,24000,16000,None],
        '2.5': [11025,12000,8000,None]
    }

    while pos + 4 <= length:
        header = data[pos:pos+4]
        if len(header) < 4:
            break
        b1, b2, b3, b4 = header
        # sync bits
        if b1 != 0xff or (b2 & 0xe0) != 0xe0:
            pos += 1
            continue
        version_bits = (b2 >> 3) & 0x03
        layer_bits = (b2 >> 1) & 0x03
        bitrate_idx = (b3 >> 4) & 0x0f
        rate_idx = (b3 >> 2) & 0x03
        padding = (b3 >> 1) & 0x01

        if version_bits == 0:
            version = '2.5'
        elif version_bits == 2:
            version = '2'
        elif version_bits == 3:
            version = '1'
        else:
            pos += 1
            continue

        layer = str(4 - layer_bits)  # layer_bits 01->3, 10->2, 11->1
        if layer not in ('1','2','3'):
            pos += 1
            continue

        try:
            bitrate_kbps = bitrate_table[version][layer][bitrate_idx]
        except KeyError:
            break
        if bitrate_kbps is None:
            pos += 1
            continue
        try:
            sample_rate = sample_rate_table[version][rate_idx]
        except KeyError:
            break
        if sample_rate is None:
            pos += 1
            continue

        if layer == '1':
            frame_length = int((12 * bitrate_kbps * 1000 / sample_rate + padding) * 4)
            samples_per_frame = 384
        else:
            frame_length = int(144 * bitrate_kbps * 1000 / sample_rate + padding)
            samples_per_frame = 1152

        total_duration += samples_per_frame / sample_rate
        if frame_length <= 0:
            pos += 1
        else:
            pos += frame_length
    return int(total_duration)


def get_duration_accurate(filename):
    """
    Gets accurate duration for both MP3 and FLAC.
    Tries multiple methods to find the most accurate value.
    """
    ext = os.path.splitext(filename)[1].lower()
    if ext == '.mp3':
        dur = get_mp3_duration_by_frames(filename)
        if dur > 0:
            return dur
        # fallback to mutagen if frame parse failed
    try:
        audio = File(filename)
        if audio is None or not audio.info:
            return 0
        
        # Try first: total_samples and sample_rate (best for FLAC)
        if hasattr(audio.info, 'total_samples') and hasattr(audio.info, 'sample_rate'):
            if audio.info.total_samples and audio.info.sample_rate:
                duration = int(audio.info.total_samples / audio.info.sample_rate)
                return duration
        
        # Try: length (works best for MP3)
        if hasattr(audio.info, 'length') and audio.info.length:
            return int(audio.info.length)
        
        # Debug: print what we actually have
        print(f"  DEBUG {filename}: Available attributes: {dir(audio.info)}")
        
        return 0
    except Exception as e:
        print(f"  DEBUG {filename}: Exception - {e}")
        return 0

def parse_audio_file(filename):
    """
    Reads the file once and returns a dict with all required values:
    title, artist, album, duration, lyrics, description, track, cover
    """
    info = {
        'title': normalize_title_fallback(filename),
        'artist': 'Unknown Artist',
        'album': 'Unknown Album',
        'duration': 0,
        'lyrics': '',
        'description': '',
        'track': 999,
        'cover': None,
        'cover_source': 'missing',
        'living_cover': '',
        'title_from_tag': False,
        'artist_from_tag': False,
        'album_from_tag': False,
        'track_from_tag': False,
    }

    # Base filename for cover extraction
    base = os.path.splitext(filename)[0]
    base_filename = os.path.basename(base)  # Extract just filename without full path

    # Cover priority: embedded -> file-specific sidecar -> configured release cover
    info['cover'], info['cover_source'] = get_cover(filename)

    try:
        audio = File(filename)
        if audio is None:
            return info

        # duration
        try:
            info['duration'] = get_duration_accurate(filename)
        except Exception:
            info['duration'] = 0

        tags = audio.tags
        if tags:
            # title/artist/album (Vorbis / FLAC)
            if 'TITLE' in tags and tags['TITLE']:
                info['title'] = tags['TITLE'][0]
                info['title_from_tag'] = True
            if 'ARTIST' in tags and tags['ARTIST']:
                info['artist'] = tags['ARTIST'][0]
                info['artist_from_tag'] = True
            if 'ALBUM' in tags and tags['ALBUM']:
                info['album'] = tags['ALBUM'][0]
                info['album_from_tag'] = True

            # MP3 ID3 frames
            if 'TIT2' in tags:
                info['title'] = str(tags['TIT2'])
                info['title_from_tag'] = True
            if 'TPE1' in tags:
                info['artist'] = str(tags['TPE1'])
                info['artist_from_tag'] = True
            if 'TALB' in tags:
                info['album'] = str(tags['TALB'])
                info['album_from_tag'] = True

            # track number
            if 'TRACKNUMBER' in tags and tags['TRACKNUMBER']:
                track = tags['TRACKNUMBER'][0]
            elif 'TRCK' in tags:
                track = str(tags['TRCK'])
            else:
                track = None

            if track:
                try:
                    if '/' in track:
                        track = track.split('/')[0]
                    info['track'] = int(track) if str(track).isdigit() else 999
                    if info['track'] != 999:
                        info['track_from_tag'] = True
                except Exception:
                    info['track'] = 999

            # lyrics — look for USLT / LYRICS / UNSYNCEDLYRICS
            for tag_name in ['LYRICS', 'UNSYNCEDLYRICS']:
                if tag_name in tags:
                    val = tags[tag_name]
                    info['lyrics'] = val[0] if isinstance(val, list) else str(val)
                    break

            # MP3 USLT frames
            if info['lyrics'].startswith('No lyrics'):
                for key in tags.keys():
                    if key.startswith('USLT'):
                        info['lyrics'] = str(tags[key])
                        break

            # Cover extraction is handled exclusively by get_cover() (assigned → sidecar → embed fallback).

    except Exception:
        # on error return what we have
        pass

    # cosmetic: insert line break before first '[' in title if present
    try:
        t = info.get('title', '')
        idx = t.find('[')
        if idx > 0:
            info['title'] = t[:idx].rstrip() + '\n' + t[idx:].lstrip()
    except Exception:
        pass

    # Get lyrics and description from tags
    info['lyrics'] = get_lyrics(filename)
    info['description'] = get_description(filename)
    info['living_cover'] = get_living_cover(filename)
    
    # fallback: if still None, leave as None
    return info

def generate_playlist():
    playlist = []
    validation_entries = []

    # Check that the original audio directory exists
    if not AUDIO_ORIG_DIR.exists():
        print(f"❌ Original audio directory not found at {AUDIO_ORIG_DIR}")
        return

    # Ensure output directories exist
    IMG_ORIG_DIR.mkdir(parents=True, exist_ok=True)
    VALIDATION_FILE.parent.mkdir(parents=True, exist_ok=True)

    # Collect playlist work items from the playlist document when available.
    unsupported_files = []
    hidden_bundled_files = []
    work_items = []
    document_order = load_playlist_document_master_order()

    if document_order:
        build_playlist_id = resolve_build_playlist_id()
        print(f"Using playlist document order for {build_playlist_id} ({len(document_order)} track(s))...")
        for playlist_file in document_order:
            working_path = resolve_audio_working_path(playlist_file)
            if not working_path.exists() or not working_path.is_file():
                print(f"⚠️  Skipping missing playlist track: {playlist_file}")
                continue
            work_items.append({
                'playlist_file': playlist_file,
                'working_path': working_path,
                'source_label': playlist_file,
            })
    else:
        files, unsupported_files, hidden_bundled_files = collect_audio_source_files()
        files.sort(key=lambda f: (get_track_number(str(f)), f.name.lower()))

        ORDER_FILE = ROOT_DIR / 'data' / 'playlist-order.json'
        saved_order = []
        if ORDER_FILE.exists():
            try:
                with open(str(ORDER_FILE), 'r', encoding='utf-8') as _f:
                    saved_order = json.load(_f)
            except Exception as _e:
                print(f"⚠️  Could not read playlist order file, using default sort: {_e}")
                saved_order = []

        if saved_order and isinstance(saved_order, list):
            saved_set = {str(name) for name in saved_order}
            expanded_set = set(saved_set)
            for name in saved_set:
                expanded_set.add(resolve_playlist_file_name(name))
            files = [
                f for f in files
                if f.name in expanded_set or resolve_playlist_file_name(f.name) in expanded_set
            ]
            order_index = {}
            for index, name in enumerate(saved_order):
                order_index[str(name)] = index
                canonical = resolve_playlist_file_name(name)
                if canonical:
                    order_index[canonical] = index
            files.sort(key=lambda f: (
                order_index.get(f.name, order_index.get(resolve_playlist_file_name(f.name), len(saved_order))),
                get_track_number(str(f)),
                f.name.lower(),
            ))

        for filepath in files:
            work_items.append({
                'playlist_file': resolve_playlist_file_name(filepath.name),
                'working_path': resolve_audio_working_path(filepath.name),
                'source_label': filepath.name,
            })

    if not work_items:
        if unsupported_files:
            unsupported_names = ', '.join(file.name for file in unsupported_files)
            print(f"❌ No supported source audio found in {AUDIO_ORIG_DIR}")
            print(f"   Unsupported audio files present: {unsupported_names}")
            print("   Current supported source formats: FLAC and MP3")
        elif hidden_bundled_files:
            print(f"No playable source audio remains after hiding bundled demo tracks in {AUDIO_ORIG_DIR}")
        else:
            print(f"No playable playlist tracks found in {AUDIO_ORIG_DIR}")
        return

    print(f"Found {len(work_items)} playlist track(s). Generating playlist...")
    if hidden_bundled_files:
        print(f"ℹ️  Hidden bundled demo tracks skipped: {', '.join(file.name for file in hidden_bundled_files)}")
    if unsupported_files:
        print(f"⚠️  Skipping unsupported audio source files: {', '.join(file.name for file in unsupported_files)}")

    for item in work_items:
        playlist_file = item['playlist_file']
        working_path = item['working_path']
        source_label = item['source_label']
        info = parse_audio_file(str(working_path))
        metadata_warnings = build_metadata_warnings(source_label, info)
        
        # Ensure cover is just the filename, not full path
        cover_file = info['cover']
        if cover_file:
            cover_file = os.path.basename(cover_file)
        else:
            cover_file = ""
        
        entry = {
            "file": playlist_file,
            "title": info['title'],
            "artist": info['artist'],
            "album": info['album'],
            "duration": info['duration'],
            "lyrics": info['lyrics'],
            "description": info['description'],
            "cover": cover_file,
            "living_cover": info.get('living_cover') or '',
        }
        playlist.append(entry)
        validation_entries.append({
            'file': playlist_file,
            'title': info['title'],
            'cover': cover_file,
            'coverSource': info.get('cover_source', 'missing'),
            'sourceTier': 'master' if working_path.parent == AUDIO_MASTER_DIR else 'original',
            'warnings': metadata_warnings,
        })

        cover_source = info.get('cover_source', 'missing')
        if cover_file:
            if cover_source == 'configured':
                record_cover_asset(
                    cover_file,
                    'release-fallback',
                    'build-configured',
                    linked_config='media.cover',
                )
            elif cover_source == 'assigned':
                record_cover_asset(
                    cover_file,
                    'track-cover',
                    'operator-assigned',
                    linked_audio=playlist_file,
                )
            elif cover_source == 'embedded':
                record_cover_asset(
                    cover_file,
                    'track-cover',
                    'build-extracted',
                    linked_audio=playlist_file,
                )
            elif cover_source == 'sidecar':
                record_cover_asset(
                    cover_file,
                    'track-cover',
                    'build-sidecar-copy',
                    linked_audio=playlist_file,
                )

        disp_track = str(info['track']) if info['track'] != 999 else "-"
        warning_suffix = f" [metadata warnings: {', '.join(metadata_warnings)}]" if metadata_warnings else ''
        print(f"Track {disp_track}: {info['title']}{warning_suffix}")

    # Player playlists are published into data/playlists/{id}.json at build time.
    # Runtime player endpoints read that static payload only.

    # Write/update data/playlist-order.json so future builds preserve current order
    try:
        ORDER_FILE = ROOT_DIR / 'data' / 'playlist-order.json'
        ORDER_FILE.parent.mkdir(parents=True, exist_ok=True)
        order_filenames = [entry['file'] for entry in playlist]
        with open(str(ORDER_FILE), 'w', encoding='utf-8') as f:
            json.dump(order_filenames, f, indent=4, ensure_ascii=False)
    except Exception as e:
        print(f"⚠️  Could not write playlist order file: {e}")

    metadata_warning_count = sum(1 for entry in validation_entries if entry['warnings'])
    validation_report = {
        'supportedExtensions': list(SUPPORTED_EXTENSIONS),
        'unsupportedSourceFiles': [file.name for file in unsupported_files],
        'hiddenBundledSourceFiles': [file.name for file in hidden_bundled_files],
        'summary': {
            'totalTracks': len(validation_entries),
            'tracksWithWarnings': metadata_warning_count,
            'tracksWithoutWarnings': len(validation_entries) - metadata_warning_count,
        },
        'tracks': validation_entries,
    }
    write_validation_report(validation_report)

    active_configured_cover = get_configured_cover_filename()
    if active_configured_cover:
        cleanup_stale_configured_release_covers(active_configured_cover)

    # Drop legacy stem-named cover copies when registry already points at a pool file.
    try:
        php = resolve_php_cli()
        prune_result = subprocess.run(
            [php, str(SCRIPT_DIR / 'prune_cover_sidecars.php')],
            cwd=str(ROOT_DIR),
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            universal_newlines=True,
            check=False,
        )
        if prune_result.returncode == 0:
            pruned_count = int((prune_result.stdout or '0').strip() or '0')
            if pruned_count > 0:
                print(f"🧹 Pruned {pruned_count} redundant stem cover sidecar(s).")
        elif prune_result.stderr:
            print(f"⚠️  Cover sidecar prune skipped: {prune_result.stderr.strip().splitlines()[-1]}")
    except Exception as e:
        print(f"⚠️  Cover sidecar prune skipped: {e}")

    if metadata_warning_count:
        print(f"⚠️  Metadata warnings found for {metadata_warning_count} track(s).")
        print(f"   Validation report saved to {VALIDATION_FILE}")
    if unsupported_files:
        print(f"⚠️  Unsupported source files were skipped. Current supported source formats: {', '.join(ext.upper().lstrip('.') for ext in SUPPORTED_EXTENSIONS)}")

    publish_player_playlist_payloads()


def publish_player_playlist_payloads():
    script = ROOT_DIR / 'biblioteca' / 'build-player-playlists.php'
    if not script.exists():
        print(f"⚠️  Player playlist publish script not found: {script}")
        return

    php = resolve_php_cli()
    if php == '':
        print('❌ Could not resolve PHP CLI for player playlist publish.')
        sys.exit(1)

    print('Publishing static player playlist payloads...')
    try:
        result = subprocess.run(
            [php, str(script)],
            cwd=str(ROOT_DIR),
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            universal_newlines=True,
            check=False,
        )
    except Exception as exc:
        print(f"❌ Could not publish player playlist payloads: {exc}")
        sys.exit(1)

    if result.stdout:
        for line in result.stdout.splitlines():
            if line.strip():
                print(line)
    if result.returncode != 0:
        if result.stderr:
            for line in result.stderr.splitlines():
                if line.strip():
                    print(line)
        print('❌ Player playlist publish failed.')
        sys.exit(1)

    print('✓ Player playlist payloads published.')


def generate_validation_scan():
    """Refresh playlist validation for all visible source files without mutating playlist.json."""
    if not AUDIO_ORIG_DIR.exists():
        print(f"❌ Original audio directory not found at {AUDIO_ORIG_DIR}")
        return

    files, unsupported_files, hidden_bundled_files = collect_audio_source_files()
    files.sort(key=lambda f: (get_track_number(str(f)), f.name.lower()))

    if not files:
        if unsupported_files:
            unsupported_names = ', '.join(file.name for file in unsupported_files)
            print(f"❌ No supported source audio found in {AUDIO_ORIG_DIR}")
            print(f"   Unsupported audio files present: {unsupported_names}")
        elif hidden_bundled_files:
            print(f"No playable source audio remains after hiding bundled demo tracks in {AUDIO_ORIG_DIR}")
        else:
            print(f"No supported audio files found in {AUDIO_ORIG_DIR}")
        return

    print(f"Validation scan for {len(files)} source file(s)...")
    if hidden_bundled_files:
        print(f"ℹ️  Hidden bundled demo tracks skipped: {', '.join(file.name for file in hidden_bundled_files)}")
    if unsupported_files:
        print(f"⚠️  Skipping unsupported audio source files: {', '.join(file.name for file in unsupported_files)}")

    validation_entries = []
    for filepath in files:
        filename = filepath.name
        playlist_file = resolve_playlist_file_name(filename)
        working_path = resolve_audio_working_path(filename)
        info = parse_audio_file(str(working_path))
        metadata_warnings = build_metadata_warnings(filename, info)
        cover_file = info['cover']
        if cover_file:
            cover_file = os.path.basename(cover_file)
        else:
            cover_file = ""

        validation_entries.append({
            'file': playlist_file,
            'title': info['title'],
            'cover': cover_file,
            'coverSource': info.get('cover_source', 'missing'),
            'sourceTier': 'master' if working_path.parent == AUDIO_MASTER_DIR else 'original',
            'warnings': metadata_warnings,
        })

    metadata_warning_count = sum(1 for entry in validation_entries if entry['warnings'])
    validation_report = {
        'supportedExtensions': list(SUPPORTED_EXTENSIONS),
        'unsupportedSourceFiles': [file.name for file in unsupported_files],
        'hiddenBundledSourceFiles': [file.name for file in hidden_bundled_files],
        'summary': {
            'totalTracks': len(validation_entries),
            'tracksWithWarnings': metadata_warning_count,
            'tracksWithoutWarnings': len(validation_entries) - metadata_warning_count,
        },
        'tracks': validation_entries,
    }
    write_validation_report(validation_report)

    if metadata_warning_count:
        print(f"⚠️  Metadata warnings found for {metadata_warning_count} track(s).")
        print(f"   Validation report saved to {VALIDATION_FILE}")


if __name__ == "__main__":
    scan_mode = os.environ.get('BANDPROMO_PLAYLIST_SCAN_MODE', 'full').strip().lower()
    if scan_mode == 'validation-only':
        generate_validation_scan()
    else:
        generate_playlist()
