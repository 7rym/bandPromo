"""
optimizeMedia — Web-Optimized Media Generator
Converts source content into bandwidth-efficient web variants:
- Audio delivery → MP3 files with full ID3 tags when readable
    - MP3 sources are copied directly into delivery output
    - FLAC/WAV sources are transcoded to MP3 320kbps
- Covers → JPEG (optimized quality) for bandwidth savings
- Photos → JPEG (optimized quality) for bandwidth savings

Reads registered audio assets from data/assets/registry.json for delivery scope.
play/playlist.json is used only for track-cover linkage, not which tracks get MP3 deliverables.
Social/OG share image is defined in web-config.json (social.share_image).
"""

import os
import json
import subprocess
import sys
import shutil
from pathlib import Path
from mutagen import File
from mutagen.flac import FLAC
from mutagen.id3 import ID3, TIT2, TPE1, TALB, TDRC, TRCK, COMM, APIC, TCON, TPE2, TBP, TKEY, TPE4, USLT, TXXX

import io

# Force UTF-8 output - compatible with Python 3.6+
if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8', errors='replace', line_buffering=True)
else:
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace', line_buffering=True)

# Find the root directory (scripts/..)
SCRIPT_DIR   = Path(__file__).parent
ROOT_DIR     = SCRIPT_DIR.parent
AUDIO_ORIG_DIR = ROOT_DIR / 'media' / 'audio' / 'original'
AUDIO_MASTER_DIR = ROOT_DIR / 'media' / 'audio' / 'master'
AUDIO_OPT_DIR  = ROOT_DIR / 'media' / 'audio' / 'optimal'
IMG_ORIG_DIR   = ROOT_DIR / 'media' / 'img'   / 'original'
IMG_OPT_DIR    = ROOT_DIR / 'media' / 'img'   / 'optimal'
PHOTO_ORIG_DIR = ROOT_DIR / 'media' / 'photo' / 'original'
PHOTO_OPT_DIR  = ROOT_DIR / 'media' / 'photo' / 'optimal'
PLAY_CONFIG  = ROOT_DIR / 'play' / 'playlist.json'
ASSET_REGISTRY_FILE = ROOT_DIR / 'data' / 'assets' / 'registry.json'
MEDIA_DIR    = ROOT_DIR / 'media'
OPTIMIZE_MODE = os.environ.get('BANDPROMO_OPTIMIZE_MODE', '').strip().lower() or 'image-only'

try:
    from PIL import Image
except ImportError:
    print("❌ Error: Pillow (PIL) is required for image conversion")
    print("   Install with: pip install Pillow")
    sys.exit(1)


def load_orig_config_if_present():
    """Load play/playlist.json when available, otherwise return an empty track list."""
    if not PLAY_CONFIG.exists():
        return []

    try:
        with open(str(PLAY_CONFIG), 'r', encoding='utf-8') as f:
            loaded = json.load(f)
    except Exception as e:
        print(f"⚠️  Could not read play/playlist.json for image-only refresh: {e}")
        return []

    return loaded if isinstance(loaded, list) else []


def optimized_audio_name(source_name):
    """Map any supported source-audio filename to its optimized MP3 filename."""
    return Path(source_name).stem + '.mp3'


def convert_cover_to_jpeg(source_path, dest_path, quality=75):
    """
    Convert cover image to JPEG with medium quality.
    JPEG quality 75 provides good balance between size and appearance.
    """
    try:
        # Handle case where source doesn't exist (e.g., default_cover.png)
        if not os.path.exists(source_path):
            print(f"    ⚠️  Source cover not found: {source_path}")
            return None
        
        img = Image.open(source_path)
        
        # Convert RGBA/other modes to RGB for JPEG
        if img.mode in ('RGBA', 'LA', 'P'):
            # Create white background
            background = Image.new('RGB', img.size, (255, 255, 255))
            if img.mode == 'P':
                img = img.convert('RGBA')
            background.paste(img, mask=img.split()[-1] if img.mode == 'RGBA' else None)
            img = background
        elif img.mode != 'RGB':
            img = img.convert('RGB')
        
        # Save as JPEG
        img.save(dest_path, 'JPEG', quality=quality, optimize=True)
        
        # Calculate compression ratio for info
        source_size = os.path.getsize(source_path)
        dest_size = os.path.getsize(dest_path)
        ratio = (1 - dest_size / source_size) * 100 if source_size > 0 else 0
        
        print(f"    ✓ Converted cover: {source_path} → {os.path.basename(dest_path)} ({ratio:.0f}% smaller)")
        return os.path.basename(dest_path)
    except Exception as e:
        print(f"    ❌ Error converting cover: {e}")
        return None


def check_ffmpeg():
    """Check if ffmpeg is accessible (env var FFMPEG_PATH takes priority)."""
    ffmpeg = os.environ.get('FFMPEG_PATH', 'ffmpeg')
    try:
        subprocess.run([ffmpeg, '-version'],
                      stdout=subprocess.DEVNULL,
                      stderr=subprocess.DEVNULL)
        return True
    except FileNotFoundError:
        return False


def get_ffmpeg_path():
    """Return the ffmpeg executable path from env or default."""
    return os.environ.get('FFMPEG_PATH', 'ffmpeg')


def load_asset_registry():
    if not ASSET_REGISTRY_FILE.exists():
        return {}

    try:
        with open(str(ASSET_REGISTRY_FILE), 'r', encoding='utf-8') as handle:
            payload = json.load(handle)
    except Exception:
        return {}

    return payload if isinstance(payload, dict) else {}


def load_registry_audio_delivery_queue():
    """Return registered audio assets queued for delivery file generation."""
    payload = load_asset_registry()
    assets = payload.get('assets') if isinstance(payload.get('assets'), dict) else {}
    queue = []

    for asset_id, asset in assets.items():
        if not isinstance(asset, dict):
            continue
        if str(asset.get('kind') or 'audio').strip().lower() != 'audio':
            continue

        master_filename = os.path.basename(str(asset.get('master_filename') or '').strip())
        if not master_filename:
            continue

        queue.append({
            'asset_id': str(asset_id),
            'master_filename': master_filename,
            'original_filename': os.path.basename(str(asset.get('original_filename') or '').strip()),
            'delivery_filename': Path(master_filename).stem + '.mp3',
        })

    queue.sort(key=lambda item: str(item.get('master_filename') or '').lower())
    return queue


def build_playlist_cover_lookup(playlist_entries):
    """Map playlist master filenames to cover artwork filenames."""
    lookup = {}
    for entry in playlist_entries:
        if not isinstance(entry, dict):
            continue

        file_name = os.path.basename(str(entry.get('file') or '').strip())
        cover = entry.get('cover')
        if not file_name:
            continue

        lookup[file_name] = cover
        lookup[Path(file_name).stem] = cover

    return lookup


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


def resolve_audio_working_path(filename):
    asset = load_asset_for_filename(filename)
    if isinstance(asset, dict):
        master_name = os.path.basename(str(asset.get('master_filename') or '').strip())
        if master_name:
            path = AUDIO_MASTER_DIR / master_name
            if path.exists() and path.is_file():
                return path, 'master'

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
            return candidate, 'master'

    return AUDIO_ORIG_DIR / filename, 'original'


def get_audio_tags(source_path):
    """Extract tags from supported source audio files for MP3 delivery output."""
    tags = {}
    try:
        source_path = str(source_path)
        source_suffix = Path(source_path).suffix.lower()

        if source_suffix == '.flac':
            audio = FLAC(source_path)
        else:
            audio = File(source_path)
            if audio is None:
                return tags
        
        if audio.get('title'):
            tags['title'] = audio['title'][0]
        if audio.get('artist'):
            tags['artist'] = audio['artist'][0]
        if audio.get('album'):
            tags['album'] = audio['album'][0]
        if audio.get('date'):
            tags['date'] = audio['date'][0]
        if audio.get('year'):
            tags['year'] = audio['year'][0]
        if audio.get('tracknumber'):
            tags['tracknumber'] = audio['tracknumber'][0]
        if audio.get('genre'):
            tags['genre'] = audio['genre'][0]
        if audio.get('albumartist'):
            tags['albumartist'] = audio['albumartist'][0]
        if audio.get('comment'):
            tags['comment'] = audio['comment'][0]
        if audio.get('bpm'):
            tags['bpm'] = audio['bpm'][0]
        
        # Key - use initialkey
        if audio.get('initialkey'):
            tags['key'] = audio['initialkey'][0]
        
        # Mix artist - lowercase
        if audio.get('mixartist'):
            tags['mixartist'] = audio['mixartist'][0]
        
        # Lyrics - handle as list and join properly
        if audio.get('unsyncedlyrics'):
            lyrics_data = audio['unsyncedlyrics']
            if isinstance(lyrics_data, list):
                lyrics_text = '\n'.join(lyrics_data)
            else:
                lyrics_text = str(lyrics_data)
            # Remove everything before || in the first line if present
            lines = lyrics_text.split('\n')
            if lines and '||' in lines[0]:
                lines[0] = lines[0].split('||', 1)[1]
            tags['lyrics'] = '\n'.join(lines)
        elif audio.get('lyrics'):
            lyrics_data = audio['lyrics']
            if isinstance(lyrics_data, list):
                lyrics_text = '\n'.join(lyrics_data)
            else:
                lyrics_text = str(lyrics_data)
            # Remove everything before || in the first line if present
            lines = lyrics_text.split('\n')
            if lines and '||' in lines[0]:
                lines[0] = lines[0].split('||', 1)[1]
            tags['lyrics'] = '\n'.join(lines)
        
        # Try to extract cover art (first picture)
        if getattr(audio, 'pictures', None):
            tags['picture'] = audio.pictures[0]
    except Exception as e:
        print(f"  Warning: Could not read source audio tags: {e}")
    
    return tags


def set_id3_tags(mp3_path, tags):
    """Apply tags to MP3 file using ID3."""
    try:
        # Remove existing tags
        try:
            id3 = ID3(mp3_path)
            id3.delete()
        except:
            pass
        
        # Create new ID3 tag
        id3 = ID3()
        
        if 'title' in tags:
            id3.add(TIT2(encoding=3, text=[tags['title']]))
        if 'artist' in tags:
            id3.add(TPE1(encoding=3, text=[tags['artist']]))
        if 'album' in tags:
            id3.add(TALB(encoding=3, text=[tags['album']]))
        if 'date' in tags:
            id3.add(TDRC(encoding=3, text=[tags['date']]))
        if 'year' in tags:
            id3.add(TDRC(encoding=3, text=[tags['year']]))
        if 'tracknumber' in tags:
            id3.add(TRCK(encoding=3, text=[tags['tracknumber']]))
        if 'genre' in tags:
            id3.add(TCON(encoding=3, text=[tags['genre']]))
        if 'albumartist' in tags:
            id3.add(TPE2(encoding=3, text=[tags['albumartist']]))
        if 'comment' in tags:
            id3.add(COMM(encoding=3, lang='eng', desc='', text=[tags['comment']]))
        if 'bpm' in tags:
            id3.add(TBP(encoding=3, text=[tags['bpm']]))
        if 'key' in tags:
            id3.add(TKEY(encoding=3, text=[tags['key']]))
        if 'mixartist' in tags:
            # Use TPE4 (Interpreted by) for Mix Artist
            id3.add(TPE4(encoding=3, text=[tags['mixartist']]))
        if 'lyrics' in tags:
            # Ensure lyrics is a clean string without encoding artifacts
            lyrics_text = tags['lyrics']
            if isinstance(lyrics_text, list):
                lyrics_text = '\n'.join(str(line) for line in lyrics_text)
            id3.add(USLT(encoding=3, lang='eng', desc='', text=lyrics_text))
        
        # Add cover art if available
        if 'picture' in tags:
            picture = tags['picture']
            id3.add(APIC(encoding=3, 
                        mime=picture.mime,
                        type=3,  # Cover front
                        desc='',
                        data=picture.data))
        
        id3.save(mp3_path, v2_version=4)
    except Exception as e:
        print(f"  Warning: Could not set ID3 tags: {e}")


def convert_audio_to_mp3(source_path, mp3_path):
    """Convert a supported source audio file to MP3 using ffmpeg."""
    ffmpeg = get_ffmpeg_path()
    try:
        cmd = [
            ffmpeg,
            '-i', str(source_path),
            '-b:a', '320k',          # CBR 320kbps
            '-y',                    # Overwrite output file
            str(mp3_path)
        ]
        subprocess.run(cmd,
                      stdout=subprocess.DEVNULL,
                      stderr=subprocess.DEVNULL,
                      check=True)
        return True
    except subprocess.CalledProcessError as e:
        print(f"  Error: FFmpeg conversion failed: {e}")
        return False
    except Exception as e:
        print(f"  Error: {e}")
        return False


def copy_audio_to_mp3(source_path, mp3_path):
    """Copy an MP3 source directly into delivery output without re-encoding."""
    try:
        shutil.copy2(str(source_path), str(mp3_path))
        return True
    except Exception as e:
        print(f"  Error: Could not copy MP3 source: {e}")
        return False


def audio_delivery_mode(source_path):
    """Choose delivery route based on source format."""
    return 'copy' if Path(source_path).suffix.lower() == '.mp3' else 'transcode'


def playlist_needs_ffmpeg(orig_config):
    """Only require ffmpeg when at least one track needs transcoding."""
    for entry in orig_config:
        filename = entry.get('file')
        if not filename:
            continue
        source_path, _source_tier = resolve_audio_working_path(filename)
        if audio_delivery_mode(source_path) == 'transcode':
            return True
    return False


def delivery_queue_needs_ffmpeg(queue):
    """Only require ffmpeg when at least one registry asset needs transcoding."""
    for item in queue:
        master_filename = item.get('master_filename')
        if not master_filename:
            continue
        source_path, _source_tier = resolve_audio_working_path(master_filename)
        if not Path(source_path).exists():
            continue
        if audio_delivery_mode(source_path) == 'transcode':
            return True
    return False


def process_track_cover(cover_filename):
    if not cover_filename:
        return

    orig_cover_path = IMG_ORIG_DIR / cover_filename
    lq_cover_path = IMG_OPT_DIR / (Path(cover_filename).stem + '.jpg')
    print(f"  → Processing cover: {cover_filename}")
    convert_cover_to_jpeg(str(orig_cover_path), str(lq_cover_path), quality=75)


def process_audio_delivery(master_filename, cover_filename=None):
    """Convert one registry audio asset to a delivery MP3."""
    source_path, source_tier = resolve_audio_working_path(master_filename)
    source = Path(source_path)
    if not source.exists() or not source.is_file():
        print(f"  ❌ Source audio not found for {master_filename}")
        return False

    mp3_filename = Path(master_filename).stem + '.mp3'
    mp3_path = AUDIO_OPT_DIR / mp3_filename
    delivery_mode = audio_delivery_mode(source_path)

    print(f"\n🎵 Processing: {master_filename}")
    print(f"  → Source tier: {source_tier}")
    print(f"  → Delivery route: {'MP3 copy (source-aware)' if delivery_mode == 'copy' else 'Transcode to MP3 320kbps'}")
    print("  → Reading source audio tags...")
    tags = get_audio_tags(str(source_path))

    if delivery_mode == 'copy':
        print("  → Copying MP3 source to delivery tier...")
        converted_ok = copy_audio_to_mp3(str(source_path), str(mp3_path))
    else:
        print("  → Converting to MP3 (320kbps)...")
        converted_ok = convert_audio_to_mp3(str(source_path), str(mp3_path))

    if not converted_ok:
        print("  ❌ Failed to convert audio")
        return False

    print("  → Applying ID3 tags...")
    set_id3_tags(str(mp3_path), tags)

    if cover_filename:
        process_track_cover(cover_filename)
    else:
        print("  → No playlist-linked cover for this asset")

    return True


def main():
    """Main media optimization function."""
    # Verify source directories exist
    include_audio = OPTIMIZE_MODE == 'full'

    if include_audio and not AUDIO_ORIG_DIR.exists():
        print(f"❌ Error: Audio original directory not found at {AUDIO_ORIG_DIR}")
        sys.exit(1)

    # Create output directories if they don't exist
    AUDIO_OPT_DIR.mkdir(parents=True, exist_ok=True)
    IMG_OPT_DIR.mkdir(parents=True, exist_ok=True)
    PHOTO_OPT_DIR.mkdir(parents=True, exist_ok=True)

    print(f"🧭 Optimize mode: {OPTIMIZE_MODE}")
    if include_audio:
        print(f"📁 Audio original: {AUDIO_ORIG_DIR}")
        if AUDIO_MASTER_DIR.exists():
            print(f"📁 Audio master: {AUDIO_MASTER_DIR}")
        print(f"📁 Audio (optimized): {AUDIO_OPT_DIR}")
    print(f"📁 Image original: {IMG_ORIG_DIR}")
    print(f"📁 Image (optimized): {IMG_OPT_DIR}")
    print(f"📁 Photo original: {PHOTO_ORIG_DIR}")
    print(f"📁 Photo (optimized): {PHOTO_OPT_DIR}")
    if include_audio:
        print("ℹ️  This full optimize pass refreshes audio delivery files plus track covers, photos, and illustrations.")
        print("ℹ️  Theme/share assets in media/special are used directly; social share variants are generated by makeSocial.py.")
    else:
        print("ℹ️  This image-only pass refreshes track covers, photos, and illustrations.")
        print("ℹ️  Theme/share assets in media/special are used directly; social share variants are generated by makeSocial.py.")

    audio_queue = []
    cover_lookup = {}
    orig_config = load_orig_config_if_present()

    if include_audio:
        print("\n📖 Loading asset registry for audio delivery...")
        audio_queue = load_registry_audio_delivery_queue()
        if not audio_queue:
            print("❌ No registered audio assets found in data/assets/registry.json")
            print("   Run Repair catalog or upload audio via Files first.")
            sys.exit(1)
        print(f"✓ Found {len(audio_queue)} registered audio assets")

        if orig_config:
            cover_lookup = build_playlist_cover_lookup(orig_config)
            print(f"✓ Playlist cover linkage available for {len(orig_config)} tracks")
        else:
            print("ℹ️  No play/playlist.json — audio delivery continues without playlist cover linkage")
    else:
        print("\n📖 Loading play/playlist.json for cover refresh...")
        if orig_config:
            cover_lookup = build_playlist_cover_lookup(orig_config)
            print(f"✓ Found {len(orig_config)} tracks for cover refresh")
        else:
            print("ℹ️  No playlist data found; image-only refresh will skip track-cover-specific work and continue with photos/illustrations.")
    print("=" * 70)

    if include_audio and delivery_queue_needs_ffmpeg(audio_queue):
        if not check_ffmpeg():
            ffmpeg_name = os.environ.get('FFMPEG_PATH', 'ffmpeg')
            print(f"\n❌ Error: ffmpeg not found ({ffmpeg_name})")
            print("   Run build.py to auto-install ffmpeg, or install manually.")
            sys.exit(1)

    converted = 0
    failed = 0

    if include_audio:
        print("\n🎵 Processing registered audio delivery...")
        for item in audio_queue:
            master_filename = item.get('master_filename')
            cover_filename = cover_lookup.get(master_filename) or cover_lookup.get(Path(master_filename).stem)
            if process_audio_delivery(master_filename, cover_filename):
                converted += 1
            else:
                failed += 1
    elif orig_config:
        print("\n🖼️  Processing track cover images from playlist...")
        for entry in orig_config:
            filename = entry.get('file')
            if not filename:
                continue
            print(f"\n🖼️  Track artwork pass: {filename}")
            process_track_cover(entry.get('cover'))
    else:
        print("\n🖼️  Processing track cover images...")
        print("  ✓ No track-cover-specific work queued")

    # ── Photos ──────────────────────────────────────────────────────────────────────
    print("\n📷 Processing photos...")
    photo_exts = {'.png', '.jpg', '.jpeg', '.webp'}
    photo_count = 0
    if PHOTO_ORIG_DIR.exists():
        for src in sorted(PHOTO_ORIG_DIR.iterdir()):
            if src.is_file() and src.suffix.lower() in photo_exts:
                dest = PHOTO_OPT_DIR / (src.stem + '.jpg')
                print(f"  Processing: {src.name} → {dest.name}")
                convert_cover_to_jpeg(str(src), str(dest), quality=80)
                photo_count += 1
        if photo_count == 0:
            print("  ✓ No photos found in original/")
    else:
        print(f"  ⚠️  Photo original directory not found: {PHOTO_ORIG_DIR}")

    # ── User illustrations (img/original/ → img/optimal/) ────────────────────────
    # These are user-supplied images for use in Bio HTML and other custom content.
    print("\n🖼️  Processing user illustrations (img/original/)...")
    IMG_OPT_DIR.mkdir(parents=True, exist_ok=True)
    img_illus_count = 0
    # Skip cover art filenames that belong to tracks (already handled above)
    track_covers = {entry.get('cover') for entry in orig_config if entry.get('cover')}
    if IMG_ORIG_DIR.exists():
        for src in sorted(IMG_ORIG_DIR.iterdir()):
            if src.is_file() and src.suffix.lower() in photo_exts and src.name not in track_covers:
                dest = IMG_OPT_DIR / (src.stem + '.jpg')
                print(f"  Processing: {src.name} → {dest.name}")
                convert_cover_to_jpeg(str(src), str(dest), quality=80)
                img_illus_count += 1
        if img_illus_count == 0:
            print("  ✓ No user illustrations found in img/original/")
    else:
        print(f"  ⚠️  img/original/ directory not found: {IMG_ORIG_DIR}")

    # ── Cleanup old files in optimized dirs ──────────────────────────────────────────
    print("\n🧹 Cleaning up optimized directories...")

    removed = 0
    if include_audio:
        allowed_audio = {item['delivery_filename'] for item in audio_queue if item.get('delivery_filename')}
        for item in AUDIO_OPT_DIR.iterdir():
            if item.is_file() and item.name not in allowed_audio:
                try:
                    item.unlink()
                    print(f"  🗑️  Removed audio: {item.name}")
                    removed += 1
                except Exception as e:
                    print(f"  ⚠️  Could not remove {item.name}: {e}")

    # Keep all jpg/jpeg in IMG_OPT_DIR (they're all valid covers)

    # Clean up photos whose originals no longer exist
    if PHOTO_ORIG_DIR.exists():
        allowed_photos = {src.stem + '.jpg'
                          for src in PHOTO_ORIG_DIR.iterdir()
                          if src.is_file() and src.suffix.lower() in photo_exts}
        for item in PHOTO_OPT_DIR.iterdir():
            if item.is_file() and item.name not in allowed_photos:
                try:
                    item.unlink()
                    print(f"  🗑️  Removed photo: {item.name}")
                    removed += 1
                except Exception as e:
                    print(f"  ⚠️  Could not remove {item.name}: {e}")

    if removed == 0:
        print("  ✓ Optimized directories are clean")

    # Summary
    print("\n" + "=" * 70)
    print(f"✅ Optimization complete!")
    if include_audio:
        print(f"   Converted tracks : {converted}")
        print(f"   Failed           : {failed}")
    else:
        print("   Converted tracks : skipped (image-only mode)")
        print("   Failed           : 0")
    print(f"   Photos optimized       : {photo_count}")
    print(f"   Illustrations optimized: {img_illus_count}")
    print(f"   Cleaned up files       : {removed}")
    if include_audio:
        print(f"   Audio output     : {AUDIO_OPT_DIR}")
    print(f"   Image output     : {IMG_OPT_DIR}")
    print(f"   Photo output     : {PHOTO_OPT_DIR}")

    if (MEDIA_DIR / 'share.jpg').exists():
        print(f"\n   ⚠️  Legacy media/share.jpg found — safe to delete (now handled by makeSocial.py)")


if __name__ == '__main__':
    main()
