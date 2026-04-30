import io
import sys

# Force UTF-8 output - compatible with Python 3.6+
if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8', errors='replace', line_buffering=True)
else:
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace', line_buffering=True)

import os
import json
from pathlib import Path
from mutagen import File

# Supported audio file extensions
SUPPORTED_EXTENSIONS = ('.flac', '.mp3')
KNOWN_AUDIO_EXTENSIONS = SUPPORTED_EXTENSIONS + ('.wav', '.aif', '.aiff', '.m4a', '.aac', '.ogg', '.wma')

# Find the root directory (scripts/..)
SCRIPT_DIR    = Path(__file__).parent
ROOT_DIR      = SCRIPT_DIR.parent
AUDIO_ORIG_DIR  = ROOT_DIR / 'media' / 'audio' / 'original'
IMG_ORIG_DIR    = ROOT_DIR / 'media' / 'img'   / 'original'
OUTPUT_FILE   = ROOT_DIR / 'play' / 'playlist.json'
VALIDATION_FILE = ROOT_DIR / 'play' / 'playlist-validation.json'
CONFIG_FILE = ROOT_DIR / 'web-config.json'
CONFIG_COVER_BASENAME = 'configured_release_cover'


def normalize_title_fallback(filename):
    stem = Path(filename).stem
    cleaned = stem.replace('_', ' ').replace('-', ' ').strip()
    return cleaned or stem or filename


def collect_audio_source_files():
    supported = []
    unsupported = []

    if not AUDIO_ORIG_DIR.exists():
        return supported, unsupported

    for entry in sorted(AUDIO_ORIG_DIR.iterdir(), key=lambda item: item.name.lower()):
        if not entry.is_file():
            continue

        suffix = entry.suffix.lower()
        if suffix in SUPPORTED_EXTENSIONS:
            supported.append(entry)
        elif suffix in KNOWN_AUDIO_EXTENSIONS:
            unsupported.append(entry)

    return supported, unsupported


def build_metadata_warnings(filename, info):
    warnings = []
    title = str(info.get('title') or '').strip()
    title_fallback = normalize_title_fallback(filename)
    artist = str(info.get('artist') or '').strip()
    album = str(info.get('album') or '').strip()
    track = info.get('track', 999)
    lyrics = str(info.get('lyrics') or '')
    cover = str(info.get('cover') or '').strip()

    if not title or title == filename or title == title_fallback:
        warnings.append('missing_title_tag')
    if not artist or artist == 'Unknown Artist':
        warnings.append('missing_artist_tag')
    if not album or album == 'Unknown Album':
        warnings.append('missing_album_tag')
    if track == 999:
        warnings.append('missing_track_number')
    if lyrics.startswith('No lyrics found.'):
        warnings.append('missing_lyrics')
    if not cover:
        warnings.append('missing_cover_art')

    return warnings


def write_validation_report(report):
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
    
    return "No lyrics found.\n(Add lyrics to the ID3 tag or a .txt file with the same name.)"

def get_description(filename):
    """
    Reads DESCRIPTION or COMMENT tag from audio file.
    Used to display track description in the player.
    """
    try:
        audio = File(filename)
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


def get_cover(filename):
    """
    Priority order:
    1) Check embedded image in file tags and extract it if found
    2) File-specific image with the same basename (song.jpg/png)
    3) Configured release cover from web-config.json
    Returns (filename, source) where source is one of:
    embedded, sidecar, configured, missing
    """
    base = os.path.splitext(filename)[0]
    base_filename = os.path.basename(base)  # Extract just filename without full path
    
    # 1) Extract embedded if exists (but don't return yet)
    try:
        audio = File(filename)
        if audio is not None:
            embedded_found = False
            
            # MP3 / ID3 APIC frames
            if audio.tags:
                for key in audio.tags.keys():
                    if key.startswith('APIC') or key.startswith('APIC:'):
                        try:
                            apic = audio.tags[key]
                            data = getattr(apic, 'data', None)
                            mime = getattr(apic, 'mime', 'image/jpeg')
                            if data:
                                ext = '.png' if 'png' in mime.lower() else '.jpg'
                                outname_full = IMG_ORIG_DIR / (base_filename + ext)
                                outname_filename = base_filename + ext
                                if not outname_full.exists():
                                    IMG_ORIG_DIR.mkdir(parents=True, exist_ok=True)
                                    with open(str(outname_full), 'wb') as imgf:
                                        imgf.write(data)
                                    print(f"✓ Extracted ID3 APIC: {outname_filename}")
                                embedded_found = True
                                break
                        except Exception as e:
                            print(f"✗ Error extracting ID3 APIC: {e}")

            # FLAC pictures
            if not embedded_found:
                pics = getattr(audio, 'pictures', None)
                if pics and len(pics) > 0:
                    try:
                        pic = pics[0]
                        data = getattr(pic, 'data', None)
                        mime = getattr(pic, 'mime', 'image/jpeg')
                        if data:
                            ext = '.png' if 'png' in mime.lower() else '.jpg'
                            outname_full = IMG_ORIG_DIR / (base_filename + ext)
                            outname_filename = base_filename + ext
                            if not outname_full.exists():
                                IMG_ORIG_DIR.mkdir(parents=True, exist_ok=True)
                                with open(str(outname_full), 'wb') as imgf:
                                    imgf.write(data)
                                print(f"✓ Extracted FLAC picture: {outname_filename}")
                            embedded_found = True
                    except Exception as e:
                        print(f"✗ Error extracting FLAC picture: {e}")

    except Exception as e:
        print(f"✗ Error reading file for embedded cover: {e}")

    # 2) file-specific (will now find the image file if it was extracted)
    for ext in ('.jpg', '.jpeg', '.png'):
        candidate_full = IMG_ORIG_DIR / (base_filename + ext)
        if candidate_full.exists():
            return (base_filename + ext, 'embedded' if embedded_found else 'sidecar')

    # 3) configured release cover fallback
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
        'lyrics': 'No lyrics found.\n(Add lyrics to the ID3 tag or a .txt file with the same name.)',
        'description': '',
        'track': 999,
        'cover': None,
        'cover_source': 'missing'
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
            if 'ARTIST' in tags and tags['ARTIST']:
                info['artist'] = tags['ARTIST'][0]
            if 'ALBUM' in tags and tags['ALBUM']:
                info['album'] = tags['ALBUM'][0]

            # MP3 ID3 frames
            if 'TIT2' in tags:
                info['title'] = str(tags['TIT2'])
            if 'TPE1' in tags:
                info['artist'] = str(tags['TPE1'])
            if 'TALB' in tags:
                info['album'] = str(tags['TALB'])

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

            # embedded images (only if we haven't already set cover from file/folder)
            if not info['cover']:
                # ID3 APIC frames
                try:
                    for key in tags.keys():
                        if key.startswith('APIC') or key.startswith('APIC:'):
                            apic = tags[key]
                            data = getattr(apic, 'data', None)
                            mime = getattr(apic, 'mime', 'image/jpeg')
                            if data:
                                ext = '.png' if 'png' in mime.lower() else '.jpg'
                                outname_full = IMG_ORIG_DIR / (base_filename + ext)
                                outname_filename = base_filename + ext
                                if not outname_full.exists():
                                    IMG_ORIG_DIR.mkdir(parents=True, exist_ok=True)
                                    with open(str(outname_full), 'wb') as imgf:
                                        imgf.write(data)
                                info['cover'] = outname_filename
                                break
                except Exception:
                    pass

                # FLAC pictures
                if not info['cover']:
                    pics = getattr(audio, 'pictures', None)
                    if pics and len(pics) > 0:
                        pic = pics[0]
                        data = getattr(pic, 'data', None)
                        mime = getattr(pic, 'mime', 'image/jpeg')
                        if data:
                            ext = '.png' if 'png' in mime.lower() else '.jpg'
                            outname_full = IMG_ORIG_DIR / (base_filename + ext)
                            outname_filename = base_filename + ext
                            if not outname_full.exists():
                                try:
                                    IMG_ORIG_DIR.mkdir(parents=True, exist_ok=True)
                                    with open(str(outname_full), 'wb') as imgf:
                                        imgf.write(data)
                                except Exception:
                                    pass
                            info['cover'] = outname_filename

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
    OUTPUT_FILE.parent.mkdir(parents=True, exist_ok=True)

    # Collect supported source files and flag known-but-unsupported ones.
    files, unsupported_files = collect_audio_source_files()
    
    # Sort by track number, then filename as tiebreaker (default order)
    files.sort(key=lambda f: (get_track_number(str(f)), f.name))

    # Apply saved admin order if data/playlist-order.json exists
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
        _order_index = {name: i for i, name in enumerate(saved_order)}
        # Known tracks first (in saved order), then new tracks (original sort key) appended
        files.sort(key=lambda f: (_order_index.get(f.name, len(saved_order)), get_track_number(str(f)), f.name))

    if not files:
        if unsupported_files:
            unsupported_names = ', '.join(file.name for file in unsupported_files)
            print(f"❌ No supported source audio found in {AUDIO_ORIG_DIR}")
            print(f"   Unsupported audio files present: {unsupported_names}")
            print("   Current supported source formats: FLAC and MP3")
        else:
            print(f"No .flac or .mp3 files found in {AUDIO_ORIG_DIR}")
        return

    print(f"Found {len(files)} files. Generating playlist...")
    if unsupported_files:
        print(f"⚠️  Skipping unsupported audio source files: {', '.join(file.name for file in unsupported_files)}")

    for filepath in files:
        filename = filepath.name
        info = parse_audio_file(str(filepath))
        metadata_warnings = build_metadata_warnings(filename, info)
        
        # Ensure cover is just the filename, not full path
        cover_file = info['cover']
        if cover_file:
            cover_file = os.path.basename(cover_file)
        else:
            cover_file = ""
        
        entry = {
            "file": filename,
            "title": info['title'],
            "artist": info['artist'],
            "album": info['album'],
            "duration": info['duration'],
            "lyrics": info['lyrics'],
            "description": info['description'],
            "cover": cover_file
        }
        playlist.append(entry)
        validation_entries.append({
            'file': filename,
            'title': info['title'],
            'cover': cover_file,
            'coverSource': info.get('cover_source', 'missing'),
            'warnings': metadata_warnings,
        })

        disp_track = str(info['track']) if info['track'] != 999 else "-"
        warning_suffix = f" [metadata warnings: {', '.join(metadata_warnings)}]" if metadata_warnings else ''
        print(f"Track {disp_track}: {info['title']}{warning_suffix}")

    # Save to JSON
    try:
        with open(str(OUTPUT_FILE), 'w', encoding='utf-8') as f:
            json.dump(playlist, f, indent=4, ensure_ascii=False)
        print(f"\nSuccess! Playlist saved to {OUTPUT_FILE}")
    except Exception as e:
        print(f"Error saving file: {e}")

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
    if unsupported_files:
        print(f"⚠️  Unsupported source files were skipped. Current supported source formats: {', '.join(ext.upper().lstrip('.') for ext in SUPPORTED_EXTENSIONS)}")

if __name__ == "__main__":
    generate_playlist()
