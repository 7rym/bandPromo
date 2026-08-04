"""
optimizeMedia — Web-Optimized Media Generator
Converts source content into bandwidth-efficient web variants:
- Audio delivery → MP3 files with full ID3 tags when readable
    - MP3 sources are copied directly into delivery output
    - FLAC/WAV sources are transcoded to MP3 320kbps
- Covers → JPEG (optimized quality) for bandwidth savings
- Photos → JPEG (optimized quality) for bandwidth savings

Reads registered audio assets from data/assets/registry.json for delivery scope.
Track cover linkage is derived from data/media-library-state.json (illustrations marked role=track-cover, linked_audio=...).
Social/OG share image is defined in web-config.json (social.share_image).
"""

import os
import json
import subprocess
import sys
import shutil
from datetime import datetime, timezone
from pathlib import Path
from mutagen import File
from mutagen.flac import FLAC
from mutagen.id3 import ID3, TIT2, TPE1, TALB, TDRC, TRCK, COMM, APIC, TCON, TPE2, TBPM, TKEY, TPE4, USLT, TXXX

try:
    import xxhash
except ImportError:
    xxhash = None

import stdio_utf8
stdio_utf8.configure()

# Find the root directory (scripts/..)
SCRIPT_DIR   = Path(__file__).parent
ROOT_DIR     = SCRIPT_DIR.parent
AUDIO_ORIG_DIR = ROOT_DIR / 'media' / 'audio' / 'original'
AUDIO_MASTER_DIR = ROOT_DIR / 'media' / 'audio' / 'master'
AUDIO_OPT_DIR  = ROOT_DIR / 'media' / 'audio' / 'optimal'
IMG_ORIG_DIR   = ROOT_DIR / 'media' / 'img'   / 'original'
IMG_OPT_DIR    = ROOT_DIR / 'media' / 'img'   / 'optimal'
IMG_THUMB_DIR  = ROOT_DIR / 'media' / 'img'   / 'thumb'
PHOTO_ORIG_DIR = ROOT_DIR / 'media' / 'photo' / 'original'
PHOTO_OPT_DIR  = ROOT_DIR / 'media' / 'photo' / 'optimal'
PHOTO_THUMB_DIR = ROOT_DIR / 'media' / 'photo' / 'thumb'
ASSET_REGISTRY_FILE = ROOT_DIR / 'data' / 'assets' / 'registry.json'
MEDIA_LIBRARY_STATE_FILE = ROOT_DIR / 'data' / 'media-library-state.json'
MEDIA_DIR    = ROOT_DIR / 'media'
OPTIMIZE_MODE = os.environ.get('BANDPROMO_OPTIMIZE_MODE', '').strip().lower() or 'image-only'

# Player card max CSS is 600px; deliver slightly above for sharpness.
COVER_OPTIMAL_MAX_EDGE = 720
# Playlist rows / cover-flow thumbs (~70–100 CSS px).
COVER_THUMB_MAX_EDGE = 100

DELIVERY_CONTEXTS_FILE = SCRIPT_DIR / 'delivery-contexts.json'
VISUAL_DELIVERY_ROOT = ROOT_DIR / 'media' / 'visual' / 'delivery'
VISUAL_ORIG_DIR = ROOT_DIR / 'media' / 'visual' / 'original'
VISUAL_MASTER_DIR = ROOT_DIR / 'media' / 'visual' / 'master'

# Shell media sanity sizes (reduce first-paint bytes).
# These are directly referenced from /media/special/* and must be kept stable URLs.
SHELL_LOGO_MAX_HEIGHT_PX = 180
SHELL_BACKGROUND_MAX_HEIGHT_PX = 1080
# If background is alpha-free PNG, we may optionally convert it to JPG for extra savings.
SHELL_BACKGROUND_JPG_QUALITY = 70
SHELL_BACKGROUND_JPG_MIN_IMPROVEMENT_RATIO = 0.10  # only switch if JPG is at least ~10% smaller


def load_delivery_contexts():
    """Load scripts/delivery-contexts.json; fall back to built-in defaults."""
    defaults = {
        'variants': {
            'thumb': {'max_edge': COVER_THUMB_MAX_EDGE},
            'card': {'max_edge': COVER_OPTIMAL_MAX_EDGE},
            'logo': {'max_edge': 640},
            'poster': {'max_edge': COVER_OPTIMAL_MAX_EDGE},
        },
        'role_variants': {
            'default_image': ['thumb', 'card'],
            'brand-logo': ['logo', 'thumb'],
            'unassigned': ['thumb', 'card'],
        },
    }
    if not DELIVERY_CONTEXTS_FILE.exists():
        return defaults
    try:
        payload = json.loads(DELIVERY_CONTEXTS_FILE.read_text(encoding='utf-8'))
    except Exception:
        return defaults
    if not isinstance(payload, dict):
        return defaults
    return payload


DELIVERY_CONTEXTS = load_delivery_contexts()


def variant_max_edge(variant_name, fallback=None):
    variants = DELIVERY_CONTEXTS.get('variants') if isinstance(DELIVERY_CONTEXTS.get('variants'), dict) else {}
    entry = variants.get(variant_name) if isinstance(variants.get(variant_name), dict) else {}
    edge = entry.get('max_edge')
    if edge is None:
        return int(fallback if fallback is not None else COVER_OPTIMAL_MAX_EDGE)
    try:
        return max(0, int(edge))
    except (TypeError, ValueError):
        return int(fallback if fallback is not None else COVER_OPTIMAL_MAX_EDGE)


# Prefer registry-driven edges when available.
COVER_OPTIMAL_MAX_EDGE = variant_max_edge('card', COVER_OPTIMAL_MAX_EDGE) or COVER_OPTIMAL_MAX_EDGE
COVER_THUMB_MAX_EDGE = variant_max_edge('thumb', COVER_THUMB_MAX_EDGE) or COVER_THUMB_MAX_EDGE

try:
    from PIL import Image
except ImportError:
    print("❌ Error: Pillow (PIL) is required for image conversion")
    print("   Install with: pip install Pillow")
    sys.exit(1)


def deep_get(config, dot_path):
    if not isinstance(dot_path, str) or dot_path.strip() == '':
        return None
    parts = [p for p in dot_path.split('.') if p]
    cur = config
    for p in parts:
        if not isinstance(cur, dict) or p not in cur:
            return None
        cur = cur[p]
    if isinstance(cur, str):
        return cur.strip()
    return None


def replace_string_values(obj, old_value, new_value):
    """Recursively replace exact string matches in JSON-like structures."""
    if isinstance(obj, str):
        return new_value if obj == old_value else obj
    if isinstance(obj, list):
        return [replace_string_values(x, old_value, new_value) for x in obj]
    if isinstance(obj, dict):
        return {k: replace_string_values(v, old_value, new_value) for k, v in obj.items()}
    return obj


def resolve_web_media_path_to_abs(root_dir, web_path):
    web_path = str(web_path or '').strip().replace('\\', '/')
    if web_path == '' or not web_path.startswith('/media/'):
        return None
    abs_path = Path(root_dir) / web_path.lstrip('/')
    return abs_path


def png_has_visible_transparency(img):
    # Detect alpha channel or palette transparency.
    if img.mode in ('RGBA', 'LA'):
        alpha = img.getchannel('A')
        mn, mx = alpha.getextrema()
        return mn < 255 and mx <= 255  # visible transparency present
    if img.mode == 'P':
        transparency = img.info.get('transparency')
        return transparency is not None
    return False


def resize_image_to_max_height(img, max_height):
    max_height = max(1, int(max_height))
    w, h = img.size
    if int(h) <= max_height:
        return img, False
    scale = float(max_height) / float(h)
    new_w = max(1, int(round(w * scale)))
    new_h = max_height
    resample = getattr(getattr(Image, 'Resampling', None), 'LANCZOS', None) or Image.LANCZOS
    return img.resize((new_w, new_h), resample=resample), True


def save_png_optimized(img, dest_path):
    tmp_path = Path(str(dest_path) + '.tmp')
    try:
        # Try best-effort compression knobs; Pillow support varies across builds.
        try:
            img.save(str(tmp_path), 'PNG', optimize=True, compress_level=9)
        except Exception:
            img.save(str(tmp_path), 'PNG', optimize=True)
        tmp_path.replace(dest_path)
    finally:
        if tmp_path.exists():
            try:
                tmp_path.unlink()
            except Exception:
                pass


def save_jpg_optimized(img, dest_path, quality):
    tmp_path = Path(str(dest_path) + '.tmp')
    try:
        # JPG cannot preserve alpha; caller must ensure image is alpha-free or intentionally flattened.
        img.save(str(tmp_path), 'JPEG', quality=int(quality), optimize=True)
        tmp_path.replace(dest_path)
    finally:
        if tmp_path.exists():
            try:
                tmp_path.unlink()
            except Exception:
                pass


def optimize_shell_brand_media_images():
    """
    Resize the active brand's shell logo/background images referenced in web-config.json.
    Keeps logo as PNG (transparency), background can switch to JPG if alpha-free.

    This reduces the multi-MB 'first paint' contention caused by /media/special/* assets.
    """
    web_cfg_path = ROOT_DIR / 'web-config.json'
    if not web_cfg_path.exists():
        print("ℹ️  web-config.json not found — skipping shell media optimization")
        return

    try:
        config = json.loads(web_cfg_path.read_text(encoding='utf-8'))
    except Exception as e:
        print(f"⚠️  Could not parse web-config.json for shell media optimization: {e}")
        return

    logo_web = (
        deep_get(config, 'install.brand.logo')
        or deep_get(config, 'install.theme.logo')
        or deep_get(config, 'media.logo')
        or ''
    )

    background_web = (
        deep_get(config, 'release.theme.background_image')
        or deep_get(config, 'media.background_image')
        or deep_get(config, 'install.theme.background_image')
        or ''
    )

    # Only touch /media/special/* files referenced by config.
    logo_abs = resolve_web_media_path_to_abs(ROOT_DIR, logo_web)
    bg_abs = resolve_web_media_path_to_abs(ROOT_DIR, background_web)

    changed_config = False

    if logo_abs and logo_abs.exists() and logo_abs.suffix.lower() == '.png':
        try:
            img = Image.open(str(logo_abs))
            resized_img, did_resize = resize_image_to_max_height(img, SHELL_LOGO_MAX_HEIGHT_PX)
            # Always re-save to reduce file size a bit (optimize=True), but do not reformat.
            save_png_optimized(resized_img, logo_abs)
            print(f"  ✓ Shell logo optimized: {logo_abs.name} (max-height {SHELL_LOGO_MAX_HEIGHT_PX}px, resized={did_resize})")
        except Exception as e:
            print(f"  ⚠️  Shell logo optimize failed: {e}")

    if bg_abs and bg_abs.exists() and bg_abs.suffix.lower() in ('.png', '.jpg', '.jpeg'):
        try:
            img = Image.open(str(bg_abs))
            resized_img, did_resize = resize_image_to_max_height(img, SHELL_BACKGROUND_MAX_HEIGHT_PX)

            # If PNG is alpha-free, we can consider switching to JPG.
            if bg_abs.suffix.lower() in ('.png',):
                has_alpha = png_has_visible_transparency(img)

                if img.size != resized_img.size or did_resize:
                    # Keep PNG as a fallback/compat: resize it in place.
                    save_png_optimized(resized_img, bg_abs)
                else:
                    # No resize needed; still optimize to reduce bytes.
                    save_png_optimized(img, bg_abs)

                if not has_alpha:
                    jpg_abs = bg_abs.with_name(bg_abs.stem + f"_bg{SHELL_BACKGROUND_MAX_HEIGHT_PX}.jpg")
                    # Convert to RGB for JPG.
                    rgb_img = resized_img.convert('RGB')
                    save_jpg_optimized(rgb_img, jpg_abs, quality=SHELL_BACKGROUND_JPG_QUALITY)

                    png_size = bg_abs.stat().st_size
                    jpg_size = jpg_abs.stat().st_size
                    if png_size > 0 and (png_size - jpg_size) / png_size >= SHELL_BACKGROUND_JPG_MIN_IMPROVEMENT_RATIO:
                        new_web_path = str(jpg_abs).replace(str(ROOT_DIR), '').replace('\\', '/')
                        if not new_web_path.startswith('/'):
                            new_web_path = '/' + new_web_path
                        if background_web and background_web != new_web_path:
                            config = replace_string_values(config, background_web, new_web_path)
                            changed_config = True
                            print(f"  ✓ Shell background switched to JPG: {bg_abs.name} -> {jpg_abs.name} ({png_size/1024:.0f}KB -> {jpg_size/1024:.0f}KB)")
            else:
                # Background already JPG; just resize in place if needed.
                if bg_abs.suffix.lower() in ('.jpg', '.jpeg'):
                    # Flatten/convert to RGB before saving JPG.
                    rgb_img = resized_img.convert('RGB')
                    save_jpg_optimized(rgb_img, bg_abs, quality=SHELL_BACKGROUND_JPG_QUALITY)
                    print(f"  ✓ Shell background JPG optimized: {bg_abs.name} (max-height {SHELL_BACKGROUND_MAX_HEIGHT_PX}px, resized={did_resize})")
        except Exception as e:
            print(f"  ⚠️  Shell background optimize failed: {e}")

    if changed_config:
        try:
            web_cfg_path.write_text(json.dumps(config, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
            print("  ✓ Updated web-config.json with optimized shell background path")
        except Exception as e:
            print(f"  ⚠️  Could not persist web-config.json shell background change: {e}")


def load_track_cover_lookup():
    """Return a mapping of audio master filename -> cover filename.

    This replaces legacy play/playlist.json linkage by reading the media library state
    (data/media-library-state.json) which records track-cover assets and their linked audio.
    """
    lookup = {}
    if not MEDIA_LIBRARY_STATE_FILE.exists():
        return lookup
    try:
        with open(str(MEDIA_LIBRARY_STATE_FILE), 'r', encoding='utf-8') as handle:
            state = json.load(handle)
    except Exception as e:
        print(f"⚠️  Could not read media-library-state.json: {e}")
        return lookup
    if not isinstance(state, dict):
        return lookup
    assets = state.get('assets')
    if not isinstance(assets, dict):
        return lookup
    for key, meta in assets.items():
        if not isinstance(meta, dict):
            continue
        # state key looks like "illustrations/<filename>"
        if not isinstance(key, str) or not key.startswith('illustrations/'):
            continue
        if str(meta.get('role') or '').strip() != 'track-cover':
            continue
        linked = str(meta.get('linked_audio') or '').strip()
        if not linked:
            continue
        filename = key.split('/', 1)[1]
        if filename:
            lookup[linked] = filename
    return lookup


def cover_filename_for_audio(master_filename, cover_lookup):
    """Try to resolve a cover file for a given audio master filename."""
    if not master_filename:
        return None
    if master_filename in cover_lookup:
        return cover_lookup[master_filename]
    stem = Path(master_filename).stem
    for ext in ('.jpg', '.jpeg', '.png'):
        candidate = IMG_ORIG_DIR / (stem + ext)
        if candidate.exists():
            return candidate.name
    return None


def optimized_audio_name(source_name):
    """Map any supported source-audio filename to its optimized MP3 filename."""
    return Path(source_name).stem + '.mp3'


def _copy_cover_fallback(source_path, dest_path, reason):
    """Best-effort delivery cover when conversion crashes or fails."""
    try:
        shutil.copy2(source_path, dest_path)
        print("    ⚠️  Cover conversion skipped ({}); copied source instead: {}".format(
            reason, os.path.basename(dest_path)
        ))
        return os.path.basename(dest_path)
    except Exception as copy_error:
        print("    ❌ Cover fallback copy failed: {}".format(copy_error))
        return None


def convert_cover_to_jpeg(source_path, dest_path, quality=75, max_edge=None):
    """
    Convert cover image to JPEG with medium quality and optional max edge resize.

    Runs Pillow in a child process so a segfault on a corrupt/hostile image
    cannot abort the whole deliverables-media stage (seen as exit code -11).
    Does not upscale; only shrinks when either side exceeds max_edge.
    """
    if not os.path.exists(source_path):
        print("    ⚠️  Source cover not found: {}".format(source_path))
        return None

    if max_edge is None:
        max_edge = COVER_OPTIMAL_MAX_EDGE
    max_edge = max(1, int(max_edge))

    worker = (
        "import sys\n"
        "from PIL import Image\n"
        "src, dest, quality, max_edge = sys.argv[1], sys.argv[2], int(sys.argv[3]), int(sys.argv[4])\n"
        "img = Image.open(src)\n"
        "if img.mode in ('RGBA', 'LA', 'P'):\n"
        "    background = Image.new('RGB', img.size, (255, 255, 255))\n"
        "    if img.mode == 'P':\n"
        "        img = img.convert('RGBA')\n"
        "    background.paste(img, mask=img.split()[-1] if img.mode == 'RGBA' else None)\n"
        "    img = background\n"
        "elif img.mode != 'RGB':\n"
        "    img = img.convert('RGB')\n"
        "w, h = img.size\n"
        "longest = max(w, h)\n"
        "if longest > max_edge:\n"
        "    scale = float(max_edge) / float(longest)\n"
        "    new_size = (max(1, int(round(w * scale))), max(1, int(round(h * scale))))\n"
        "    resample = getattr(getattr(Image, 'Resampling', Image), 'LANCZOS', Image.LANCZOS)\n"
        "    img = img.resize(new_size, resample)\n"
        "img.save(dest, 'JPEG', quality=quality, optimize=True)\n"
    )

    try:
        result = subprocess.run(
            [sys.executable, '-c', worker, source_path, dest_path, str(quality), str(max_edge)],
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            universal_newlines=True,
            check=False,
        )
    except Exception as e:
        return _copy_cover_fallback(source_path, dest_path, 'launcher error: {}'.format(e))

    if result.returncode != 0:
        detail = (result.stderr or result.stdout or '').strip()
        if result.returncode < 0:
            reason = 'converter crashed (signal {})'.format(-result.returncode)
        else:
            reason = 'converter exited {}'.format(result.returncode)
        if detail:
            reason = '{}: {}'.format(reason, detail.splitlines()[-1][:200])
        return _copy_cover_fallback(source_path, dest_path, reason)

    if not os.path.exists(dest_path):
        return _copy_cover_fallback(source_path, dest_path, 'converter produced no output')

    try:
        source_size = os.path.getsize(source_path)
        dest_size = os.path.getsize(dest_path)
        ratio = (1 - dest_size / source_size) * 100 if source_size > 0 else 0
        print("    ✓ Converted cover: {} → {} (max {}px, {:.0f}% smaller)".format(
            source_path, os.path.basename(dest_path), max_edge, ratio
        ))
    except Exception:
        print("    ✓ Converted cover: {} → {}".format(
            source_path, os.path.basename(dest_path)
        ))

    return os.path.basename(dest_path)


def delivery_image_is_fresh(source_path, dest_path, max_edge):
    """True when dest exists, is newer than source, and longest edge <= max_edge."""
    try:
        source = Path(source_path)
        dest = Path(dest_path)
        if not source.is_file() or not dest.is_file():
            return False
        if dest.stat().st_mtime < source.stat().st_mtime:
            return False
        with Image.open(str(dest)) as img:
            w, h = img.size
        return max(w, h) <= int(max_edge)
    except Exception:
        return False


def write_cover_delivery_variants(source_path, optimal_path, thumb_path, quality=75):
    """Write optimal (720) and thumb (100) JPEG derivatives for one source image."""
    wrote = []
    if not delivery_image_is_fresh(source_path, optimal_path, COVER_OPTIMAL_MAX_EDGE):
        Path(optimal_path).parent.mkdir(parents=True, exist_ok=True)
        name = convert_cover_to_jpeg(
            source_path,
            optimal_path,
            quality=quality,
            max_edge=COVER_OPTIMAL_MAX_EDGE,
        )
        if name:
            wrote.append('optimal')
    else:
        print("    ✓ Optimal fresh: {}".format(os.path.basename(optimal_path)))

    if not delivery_image_is_fresh(source_path, thumb_path, COVER_THUMB_MAX_EDGE):
        Path(thumb_path).parent.mkdir(parents=True, exist_ok=True)
        name = convert_cover_to_jpeg(
            source_path,
            thumb_path,
            quality=max(60, int(quality) - 10),
            max_edge=COVER_THUMB_MAX_EDGE,
        )
        if name:
            wrote.append('thumb')
    else:
        print("    ✓ Thumb fresh: {}".format(os.path.basename(thumb_path)))

    return wrote


def image_source_has_alpha(source_path):
    try:
        with Image.open(str(source_path)) as img:
            return png_has_visible_transparency(img)
    except Exception:
        return False


def convert_image_delivery_variant(source_path, dest_path, max_edge, quality=75, preserve_alpha=False):
    """
    Write one delivery variant. When preserve_alpha is True and the source has
    transparency, emit PNG; otherwise JPEG (flattening onto white only when needed).
    """
    if not os.path.exists(source_path):
        print("    ⚠️  Source image not found: {}".format(source_path))
        return None

    max_edge = max(1, int(max_edge))
    dest_path = Path(dest_path)
    dest_path.parent.mkdir(parents=True, exist_ok=True)

    use_png = bool(preserve_alpha and image_source_has_alpha(source_path))
    if use_png and dest_path.suffix.lower() not in ('.png',):
        dest_path = dest_path.with_suffix('.png')
    elif not use_png and dest_path.suffix.lower() not in ('.jpg', '.jpeg'):
        dest_path = dest_path.with_suffix('.jpg')

    if use_png:
        worker = (
            "import sys\n"
            "from PIL import Image\n"
            "src, dest, max_edge = sys.argv[1], sys.argv[2], int(sys.argv[3])\n"
            "img = Image.open(src)\n"
            "if img.mode == 'P':\n"
            "    img = img.convert('RGBA')\n"
            "elif img.mode not in ('RGBA', 'LA'):\n"
            "    img = img.convert('RGBA') if 'A' in img.getbands() else img.convert('RGB').convert('RGBA')\n"
            "w, h = img.size\n"
            "longest = max(w, h)\n"
            "if longest > max_edge:\n"
            "    scale = float(max_edge) / float(longest)\n"
            "    new_size = (max(1, int(round(w * scale))), max(1, int(round(h * scale))))\n"
            "    resample = getattr(getattr(Image, 'Resampling', Image), 'LANCZOS', Image.LANCZOS)\n"
            "    img = img.resize(new_size, resample)\n"
            "img.save(dest, 'PNG', optimize=True)\n"
        )
        args = [sys.executable, '-c', worker, source_path, str(dest_path), str(max_edge)]
    else:
        worker = (
            "import sys\n"
            "from PIL import Image\n"
            "src, dest, quality, max_edge = sys.argv[1], sys.argv[2], int(sys.argv[3]), int(sys.argv[4])\n"
            "img = Image.open(src)\n"
            "if img.mode in ('RGBA', 'LA', 'P'):\n"
            "    background = Image.new('RGB', img.size, (255, 255, 255))\n"
            "    if img.mode == 'P':\n"
            "        img = img.convert('RGBA')\n"
            "    background.paste(img, mask=img.split()[-1] if img.mode in ('RGBA', 'LA') else None)\n"
            "    img = background\n"
            "elif img.mode != 'RGB':\n"
            "    img = img.convert('RGB')\n"
            "w, h = img.size\n"
            "longest = max(w, h)\n"
            "if longest > max_edge:\n"
            "    scale = float(max_edge) / float(longest)\n"
            "    new_size = (max(1, int(round(w * scale))), max(1, int(round(h * scale))))\n"
            "    resample = getattr(getattr(Image, 'Resampling', Image), 'LANCZOS', Image.LANCZOS)\n"
            "    img = img.resize(new_size, resample)\n"
            "img.save(dest, 'JPEG', quality=quality, optimize=True)\n"
        )
        args = [sys.executable, '-c', worker, source_path, str(dest_path), str(quality), str(max_edge)]

    try:
        result = subprocess.run(
            args,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            universal_newlines=True,
            check=False,
        )
    except Exception as e:
        return _copy_cover_fallback(source_path, str(dest_path), 'launcher error: {}'.format(e))

    if result.returncode != 0 or not dest_path.exists():
        return _copy_cover_fallback(source_path, str(dest_path), 'converter failed')

    print("    ✓ Variant {}: {} (max {}px, {})".format(
        dest_path.stem, dest_path.name, max_edge, 'PNG alpha' if use_png else 'JPEG'
    ))
    return str(dest_path)


def file_xxh3_hex(path):
    """Return lowercase XXH3-64 hex digest of file bytes, or '' if unavailable."""
    if xxhash is None:
        return ''
    try:
        hasher = xxhash.xxh3_64()
        with open(path, 'rb') as handle:
            while True:
                chunk = handle.read(1024 * 1024)
                if not chunk:
                    break
                hasher.update(chunk)
        return hasher.hexdigest().lower()
    except Exception:
        return ''


def visual_original_path_for_asset(asset):
    """Legacy intake path only (img/photo/special/video). Prefer visual_working_path_for_asset()."""
    bucket = str(asset.get('intake_bucket') or '').strip().lower()
    filename = os.path.basename(str(asset.get('original_filename') or '').strip())
    if not filename:
        return None
    mapping = {
        'img': IMG_ORIG_DIR / filename,
        'photo': PHOTO_ORIG_DIR / filename,
        'special': ROOT_DIR / 'media' / 'special' / filename,
        'video': ROOT_DIR / 'media' / 'video' / 'original' / filename,
    }
    path = mapping.get(bucket)
    if path is None or not path.exists():
        return None
    return path


def visual_working_path_for_asset(asset):
    """
    Resolve visual source bytes for delivery build.
    Order: media/visual/master/ast_* → media/visual/original/ → legacy intake.
    """
    asset_id = str(asset.get('id') or '').strip()
    original = os.path.basename(str(asset.get('original_filename') or '').strip())
    master_name = os.path.basename(str(asset.get('master_filename') or '').strip())
    fmt = str(asset.get('master_format') or '').strip().lower()
    if not fmt and original:
        fmt = Path(original).suffix.lstrip('.').lower()

    candidates = []
    if asset_id.startswith('ast_') and fmt:
        candidates.append(VISUAL_MASTER_DIR / '{}.{}'.format(asset_id, fmt))
    if master_name.startswith('ast_'):
        candidates.append(VISUAL_MASTER_DIR / master_name)
    if original:
        candidates.append(VISUAL_ORIG_DIR / original)
    legacy = visual_original_path_for_asset(asset)
    if legacy is not None:
        candidates.append(legacy)

    for path in candidates:
        if path is not None and path.is_file():
            return path
    return None


def role_image_variants(role):
    role_map = DELIVERY_CONTEXTS.get('role_variants') if isinstance(DELIVERY_CONTEXTS.get('role_variants'), dict) else {}
    role = str(role or 'unassigned').strip().lower() or 'unassigned'
    variants = role_map.get(role) or role_map.get('default_image') or ['thumb', 'card']
    # Normalize legacy names and skip video-only variants for images.
    out = []
    for name in variants:
        name = str(name).strip().lower()
        if name in ('optimal', 'lightbox'):
            name = 'card'
        if name in ('poster', 'standard-stream'):
            continue
        if name and name not in out:
            out.append(name)
    if 'thumb' not in out:
        out.insert(0, 'thumb')
    if 'card' not in out:
        out.append('card')
    return out


def variant_manifest_entry(abs_path):
    path = Path(abs_path)
    rel = str(path).replace(str(ROOT_DIR), '').replace('\\', '/')
    if not rel.startswith('/'):
        rel = '/' + rel
    rel = rel.lstrip('/')
    width = height = 0
    try:
        with Image.open(str(path)) as img:
            width, height = img.size
    except Exception:
        pass
    try:
        size = path.stat().st_size
    except Exception:
        size = 0
    from datetime import datetime, timezone
    return {
        'path': rel,
        'width': int(width),
        'height': int(height),
        'format': path.suffix.lower().lstrip('.'),
        'bytes': int(size),
        'updated_at': datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ'),
    }


def update_visual_asset_delivery(asset_id, variants_map, has_alpha=None, source_xxh3=None):
    """Patch registry.json delivery.variants for one visual asset."""
    if not asset_id or not ASSET_REGISTRY_FILE.exists():
        return False
    try:
        payload = json.loads(ASSET_REGISTRY_FILE.read_text(encoding='utf-8'))
    except Exception:
        return False
    if not isinstance(payload, dict):
        return False
    assets = payload.get('assets') if isinstance(payload.get('assets'), dict) else {}
    asset = assets.get(asset_id)
    if not isinstance(asset, dict):
        return False
    delivery = asset.get('delivery') if isinstance(asset.get('delivery'), dict) else {}
    existing = delivery.get('variants') if isinstance(delivery.get('variants'), dict) else {}
    existing.update(variants_map)
    delivery['variants'] = existing
    delivery['visual_ready'] = True
    delivery['built_at'] = datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ')
    delivery['checked_at'] = delivery['built_at']
    if source_xxh3:
        delivery['source_xxh3'] = str(source_xxh3).lower()
    asset['delivery'] = delivery
    if has_alpha is not None:
        asset['has_alpha'] = bool(has_alpha)
    assets[asset_id] = asset
    payload['assets'] = assets
    try:
        ASSET_REGISTRY_FILE.write_text(
            json.dumps(payload, indent=2, ensure_ascii=False) + '\n',
            encoding='utf-8',
        )
        return True
    except Exception as exc:
        print("    ⚠️  Could not update registry for {}: {}".format(asset_id, exc))
        return False


def visual_image_delivery_is_fresh(asset, source_path, required_variants):
    """True when required delivery variants exist and master XXH3 matches."""
    if xxhash is None:
        return False
    if os.environ.get('BANDPROMO_FORCE_VISUAL_DELIVERY', '').strip() == '1':
        return False
    delivery = asset.get('delivery') if isinstance(asset.get('delivery'), dict) else {}
    recorded = str(delivery.get('source_xxh3') or '').strip().lower()
    if not recorded:
        return False
    current = file_xxh3_hex(source_path)
    if not current or current != recorded:
        return False
    asset_id = str(asset.get('id') or '').strip()
    delivery_dir = VISUAL_DELIVERY_ROOT / asset_id
    if not delivery_dir.is_dir():
        return False
    for variant in required_variants:
        found = False
        for ext in ('.png', '.jpg', '.jpeg', '.webp'):
            if (delivery_dir / '{}{}'.format(variant, ext)).is_file():
                found = True
                break
        if not found:
            return False
    return True


def process_visual_image_asset(asset):
    """Write media/visual/delivery/{id}/{variant} plus legacy dual-read copies."""
    asset_id = str(asset.get('id') or '').strip()
    source = visual_working_path_for_asset(asset)
    if not asset_id or source is None:
        return False

    role = str(asset.get('role') or 'unassigned')
    required = role_image_variants(role)
    if visual_image_delivery_is_fresh(asset, source, required):
        print("    → Delivery: already up to date (master XXH3 match) — skipped")
        return 'skipped'

    preserve_alpha = role in ('brand-logo', 'style-ref') or bool(asset.get('has_alpha'))
    has_alpha = image_source_has_alpha(source)
    if has_alpha:
        preserve_alpha = True

    delivery_dir = VISUAL_DELIVERY_ROOT / asset_id
    delivery_dir.mkdir(parents=True, exist_ok=True)
    variants_written = {}

    for variant in required:
        max_edge = variant_max_edge(variant, COVER_OPTIMAL_MAX_EDGE if variant != 'thumb' else COVER_THUMB_MAX_EDGE)
        if max_edge <= 0:
            max_edge = COVER_OPTIMAL_MAX_EDGE
        dest = delivery_dir / ('{}.png'.format(variant) if preserve_alpha else '{}.jpg'.format(variant))
        quality = 75 if variant != 'thumb' else 65
        written = convert_image_delivery_variant(
            str(source),
            str(dest),
            max_edge=max_edge,
            quality=quality,
            preserve_alpha=preserve_alpha,
        )
        if written:
            variants_written[variant] = variant_manifest_entry(written)
            print("    → Built {}: {}".format(variant, Path(written).name))

    # Dual-read: also refresh legacy optimal/thumb trees from the same source.
    bucket = str(asset.get('intake_bucket') or '').strip().lower()
    stem = Path(str(asset.get('original_filename') or source.name)).stem
    if bucket == 'photo':
        legacy_opt = PHOTO_OPT_DIR / (stem + '.jpg')
        legacy_thumb = PHOTO_THUMB_DIR / (stem + '.jpg')
    else:
        legacy_opt = IMG_OPT_DIR / (stem + '.jpg')
        legacy_thumb = IMG_THUMB_DIR / (stem + '.jpg')
    write_cover_delivery_variants(str(source), str(legacy_opt), str(legacy_thumb), quality=80)

    if variants_written:
        source_digest = file_xxh3_hex(source)
        update_visual_asset_delivery(
            asset_id,
            variants_written,
            has_alpha=has_alpha,
            source_xxh3=source_digest or None,
        )
        if not source_digest and xxhash is None:
            print("    ⚠️  Install Python package `xxhash` for skip-if-fresh (pip install -r scripts/requirements.txt)")
        return True
    return False


def load_registry_visual_image_queue():
    payload = load_asset_registry()
    assets = payload.get('assets') if isinstance(payload.get('assets'), dict) else {}
    queue = []
    for asset_id, asset in assets.items():
        if not isinstance(asset, dict):
            continue
        if str(asset.get('kind') or '').strip().lower() != 'visual':
            continue
        if str(asset.get('media_type') or '').strip().lower() != 'image':
            continue
        item = dict(asset)
        item['id'] = str(asset.get('id') or asset_id)
        queue.append(item)
    queue.sort(key=lambda item: str(item.get('original_filename') or '').lower())
    return queue


def resolve_ffmpeg_path():
    """Resolve a working ffmpeg binary (env, bundled scripts/bin, then PATH)."""
    candidates = []
    env_path = str(os.environ.get('FFMPEG_PATH') or '').strip()
    if env_path:
        candidates.append(env_path)

    bundled_name = 'ffmpeg.exe' if os.name == 'nt' else 'ffmpeg'
    candidates.append(str(SCRIPT_DIR / 'bin' / bundled_name))
    candidates.append('ffmpeg')

    seen = set()
    for candidate in candidates:
        key = candidate.lower()
        if not candidate or key in seen:
            continue
        seen.add(key)
        if candidate not in ('ffmpeg', 'ffmpeg.exe') and not Path(candidate).is_file():
            continue
        try:
            result = subprocess.run(
                [candidate, '-version'],
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
                check=False,
            )
            if result.returncode == 0:
                return candidate
        except FileNotFoundError:
            continue
        except OSError:
            continue
    return ''


def check_ffmpeg():
    """Check if ffmpeg is accessible (env var FFMPEG_PATH takes priority)."""
    return resolve_ffmpeg_path() != ''


def get_ffmpeg_path():
    """Return a working ffmpeg path, or 'ffmpeg' as a last-resort PATH name."""
    return resolve_ffmpeg_path() or 'ffmpeg'


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

        display = asset.get('display') if isinstance(asset.get('display'), dict) else {}
        delivery = asset.get('delivery') if isinstance(asset.get('delivery'), dict) else {}
        recorded_xxh3 = str(delivery.get('source_xxh3') or '').strip().lower() or None
        # Legacy mtime fingerprint (pre-M3) — ignored once XXH3 is present.
        recorded_mtime = delivery.get('source_mtime')
        try:
            recorded_mtime = int(recorded_mtime) if recorded_mtime is not None and str(recorded_mtime).strip() != '' else None
        except (TypeError, ValueError):
            recorded_mtime = None
        queue.append({
            'asset_id': str(asset_id),
            'master_filename': master_filename,
            'original_filename': os.path.basename(str(asset.get('original_filename') or '').strip()),
            'delivery_filename': Path(master_filename).stem + '.mp3',
            'display_title': str(display.get('title') or '').strip(),
            'display_artist': str(display.get('artist') or '').strip(),
            'display': display,
            'recorded_source_xxh3': recorded_xxh3,
            'recorded_source_mtime': recorded_mtime,
        })

    # Operator-facing order: artist → title → stable asset filename (not ULID dump order alone).
    queue.sort(key=lambda item: (
        str(item.get('display_artist') or '').lower(),
        str(item.get('display_title') or '').lower(),
        str(item.get('master_filename') or '').lower(),
    ))
    return queue


def update_audio_asset_delivery(asset_id, source_xxh3, ready=True, source_mtime=None):
    """Patch registry.json delivery flags for one audio asset after optimize."""
    if not asset_id or not ASSET_REGISTRY_FILE.exists():
        return False
    try:
        payload = json.loads(ASSET_REGISTRY_FILE.read_text(encoding='utf-8'))
    except Exception:
        return False
    if not isinstance(payload, dict):
        return False
    assets = payload.get('assets') if isinstance(payload.get('assets'), dict) else {}
    asset = assets.get(asset_id)
    if not isinstance(asset, dict):
        return False
    delivery = asset.get('delivery') if isinstance(asset.get('delivery'), dict) else {}
    delivery['audio_optimal'] = bool(ready)
    if source_xxh3:
        delivery['source_xxh3'] = str(source_xxh3).lower()
    if source_mtime is not None:
        delivery['source_mtime'] = int(source_mtime)
    delivery['built_at'] = datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ')
    delivery['checked_at'] = delivery['built_at']
    asset['delivery'] = delivery
    assets[asset_id] = asset
    payload['assets'] = assets
    try:
        ASSET_REGISTRY_FILE.write_text(
            json.dumps(payload, indent=2, ensure_ascii=False) + '\n',
            encoding='utf-8',
        )
        return True
    except Exception as exc:
        print("    ⚠️  Could not update audio delivery registry for {}: {}".format(asset_id, exc))
        return False


def audio_delivery_is_fresh(source_path, mp3_path, recorded_source_xxh3=None, recorded_source_mtime=None):
    """True when delivery MP3 exists and master XXH3 matches the last successful build.

    Falls back to legacy mtime fingerprint only when XXH3 is unavailable (no xxhash package
    or no recorded digest yet). First builds always rebuild so we learn the fingerprint.
    """
    try:
        source = Path(source_path)
        dest = Path(mp3_path)
        if not source.is_file() or not dest.is_file() or dest.stat().st_size <= 0:
            return False
        if os.environ.get('BANDPROMO_FORCE_AUDIO_DELIVERY', '').strip() == '1':
            return False

        current_xxh3 = file_xxh3_hex(source)
        if recorded_source_xxh3 and current_xxh3:
            return current_xxh3 == str(recorded_source_xxh3).lower()

        # Legacy mtime path (pre-M3 installs) until the next successful build stores XXH3.
        if recorded_source_mtime is None or current_xxh3:
            # Prefer learning XXH3: if we can hash, treat missing recorded xxh3 as stale.
            if current_xxh3 and not recorded_source_xxh3:
                return False
            if recorded_source_mtime is None:
                return False
        current_mtime = int(source.stat().st_mtime)
        if int(recorded_source_mtime) != current_mtime:
            return False
        if int(dest.stat().st_mtime) < current_mtime:
            return False
        return True
    except Exception:
        return False


def format_track_label(artist='', title='', fallback=''):
    """Build an operator-facing track label."""
    artist = str(artist or '').strip()
    title = str(title or '').strip()
    if artist and title:
        return f'{title} by {artist}'
    if title:
        return title
    if artist:
        return artist
    return str(fallback or 'Unknown track').strip() or 'Unknown track'


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


def _id3_text(tags, *keys):
    for key in keys:
        if key not in tags:
            continue
        value = tags[key]
        if isinstance(value, list):
            return str(value[0]).strip()
        text = getattr(value, 'text', None)
        if isinstance(text, list) and text:
            return str(text[0]).strip()
        return str(value).strip()
    return ''


def _id3_comment(tags):
    for key in tags.keys():
        if str(key).startswith('COMM'):
            return str(tags[key]).strip()
    return ''


def _id3_lyrics(tags):
    for key in tags.keys():
        if str(key).startswith('USLT'):
            return str(tags[key]).strip()
    return ''


def _normalize_lyrics_text(lyrics_data):
    if isinstance(lyrics_data, list):
        lyrics_text = '\n'.join(str(line) for line in lyrics_data)
    else:
        lyrics_text = str(lyrics_data)
    lines = lyrics_text.split('\n')
    if lines and '||' in lines[0]:
        lines[0] = lines[0].split('||', 1)[1]
    return '\n'.join(lines)


def get_audio_tags(source_path):
    """Extract tags from supported source audio files for MP3 delivery output.

    FLAC uses Vorbis comments. MP3 must use ID3 frames (TIT2/TPE1/…); EasyID3-style
    keys like audio.get('title') are empty on a raw mutagen.mp3.MP3 object.
    """
    tags = {}
    try:
        source_path = str(source_path)
        source_suffix = Path(source_path).suffix.lower()

        if source_suffix == '.flac':
            audio = FLAC(source_path)
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
            elif audio.get('description'):
                tags['comment'] = audio['description'][0]
            if audio.get('bpm'):
                tags['bpm'] = audio['bpm'][0]
            if audio.get('initialkey'):
                tags['key'] = audio['initialkey'][0]
            if audio.get('mixartist'):
                tags['mixartist'] = audio['mixartist'][0]
            if audio.get('unsyncedlyrics'):
                tags['lyrics'] = _normalize_lyrics_text(audio['unsyncedlyrics'])
            elif audio.get('lyrics'):
                tags['lyrics'] = _normalize_lyrics_text(audio['lyrics'])
            if getattr(audio, 'pictures', None):
                tags['picture'] = audio.pictures[0]
            return tags

        if source_suffix == '.mp3':
            try:
                id3 = ID3(source_path)
            except Exception:
                return tags

            title = _id3_text(id3, 'TIT2')
            artist = _id3_text(id3, 'TPE1')
            album = _id3_text(id3, 'TALB')
            date = _id3_text(id3, 'TDRC')
            tracknumber = _id3_text(id3, 'TRCK')
            genre = _id3_text(id3, 'TCON')
            albumartist = _id3_text(id3, 'TPE2')
            bpm = _id3_text(id3, 'TBPM')
            key = _id3_text(id3, 'TKEY')
            mixartist = _id3_text(id3, 'TPE4')
            comment = _id3_comment(id3)
            lyrics = _id3_lyrics(id3)

            if title:
                tags['title'] = title
            if artist:
                tags['artist'] = artist
            if album:
                tags['album'] = album
            if date:
                tags['date'] = date
            if tracknumber:
                tags['tracknumber'] = tracknumber
            if genre:
                tags['genre'] = genre
            if albumartist:
                tags['albumartist'] = albumartist
            if comment:
                tags['comment'] = comment
            if bpm:
                tags['bpm'] = bpm
            if key:
                tags['key'] = key
            if mixartist:
                tags['mixartist'] = mixartist
            if lyrics:
                tags['lyrics'] = _normalize_lyrics_text(lyrics)

            for key_name in id3.keys():
                if str(key_name).startswith('APIC'):
                    tags['picture'] = id3[key_name]
                    break
            return tags

        # WAV / other: best-effort Easy-style keys when present.
        audio = File(source_path)
        if audio is None:
            return tags
        if audio.get('title'):
            tags['title'] = audio['title'][0]
        if audio.get('artist'):
            tags['artist'] = audio['artist'][0]
        if audio.get('album'):
            tags['album'] = audio['album'][0]
        if getattr(audio, 'pictures', None):
            tags['picture'] = audio.pictures[0]
    except Exception as e:
        print(f"  Warning: Could not read source audio tags: {e}")

    return tags


def merge_catalog_display_into_tags(tags, display):
    """Fill missing delivery-tag fields from registry display (operator catalog cache)."""
    if not isinstance(tags, dict):
        tags = {}
    if not isinstance(display, dict):
        return tags

    mapping = [
        ('title', 'title'),
        ('artist', 'artist'),
        ('album', 'album'),
        ('date', 'date'),
        ('tracknumber', 'tracknumber'),
        ('bpm', 'bpm'),
        ('genre', 'genre'),
        ('comment', 'comment'),
        ('lyrics', 'lyrics'),
    ]
    for tag_key, display_key in mapping:
        current = str(tags.get(tag_key) or '').strip()
        if current:
            continue
        value = display.get(display_key)
        if value is None:
            continue
        text = str(value).strip() if not isinstance(value, list) else '\n'.join(str(v) for v in value).strip()
        if text:
            tags[tag_key] = text

    initialkey = str(display.get('initialkey') or '').strip()
    if initialkey and not str(tags.get('key') or '').strip():
        tags['key'] = initialkey

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
            id3.add(TBPM(encoding=3, text=[tags['bpm']]))
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
            mime = getattr(picture, 'mime', None) or 'image/jpeg'
            data = getattr(picture, 'data', None)
            if data:
                id3.add(APIC(encoding=3, 
                            mime=mime,
                            type=3,  # Cover front
                            desc='',
                            data=data))
        
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
    # Legacy playlist.json-based queue removed; keep function for compatibility.
    return False


def delivery_queue_needs_ffmpeg(queue):
    """Only require ffmpeg when at least one registry asset needs transcoding."""
    for item in queue:
        master_filename = item.get('master_filename')
        if not master_filename:
            continue
        source_path, _source_tier = resolve_audio_working_path(master_filename)
        source = Path(source_path)
        if not source.exists():
            continue
        if audio_delivery_mode(source_path) != 'transcode':
            continue
        mp3_path = AUDIO_OPT_DIR / (Path(master_filename).stem + '.mp3')
        if audio_delivery_is_fresh(
            str(source_path),
            str(mp3_path),
            item.get('recorded_source_xxh3'),
            item.get('recorded_source_mtime'),
        ):
            continue
        return True
    return False


def process_track_cover(cover_filename):
    if not cover_filename:
        return

    orig_cover_path = IMG_ORIG_DIR / cover_filename
    stem = Path(cover_filename).stem
    optimal_path = IMG_OPT_DIR / (stem + '.jpg')
    thumb_path = IMG_THUMB_DIR / (stem + '.jpg')
    print(f"  → Track cover (Files): refreshing player images for {cover_filename}")
    write_cover_delivery_variants(
        str(orig_cover_path),
        str(optimal_path),
        str(thumb_path),
        quality=75,
    )


def process_audio_delivery(
    master_filename,
    cover_filename=None,
    display_title='',
    display_artist='',
    display=None,
    asset_id='',
    recorded_source_mtime=None,
    recorded_source_xxh3=None,
):
    """Convert one registry audio asset to a delivery MP3."""
    source_path, source_tier = resolve_audio_working_path(master_filename)
    source = Path(source_path)
    if not source.exists() or not source.is_file():
        label = format_track_label(display_artist, display_title, master_filename)
        print(f"\n🎵 Processing: {label}")
        print(f"  ❌ Source audio not found for {master_filename}")
        return False

    mp3_filename = Path(master_filename).stem + '.mp3'
    mp3_path = AUDIO_OPT_DIR / mp3_filename
    delivery_mode = audio_delivery_mode(source_path)
    source_mtime = int(source.stat().st_mtime)
    source_digest = file_xxh3_hex(source)
    force_rebuild = os.environ.get('BANDPROMO_FORCE_AUDIO_DELIVERY', '').strip() == '1'
    catalog = display if isinstance(display, dict) else {}
    if not display_title:
        display_title = str(catalog.get('title') or '').strip()
    if not display_artist:
        display_artist = str(catalog.get('artist') or '').strip()

    # Prefer registry display for the headline when tags are not read yet.
    headline = format_track_label(display_artist, display_title, master_filename)
    print(f"\n🎵 Processing: {headline}")
    print(f"  → Source tier: {source_tier}")
    print("  → Reading source audio tags...")
    tags = get_audio_tags(str(source_path))
    tags = merge_catalog_display_into_tags(tags, catalog)
    tag_artist = str(tags.get('artist') or '').strip()
    tag_title = str(tags.get('title') or '').strip()
    if tag_artist or tag_title:
        print(f"  → Tags: {format_track_label(tag_artist, tag_title, master_filename)}")
        headline = format_track_label(
            tag_artist or display_artist,
            tag_title or display_title,
            master_filename,
        )
    else:
        print("  → Tags: no artist/title on the source file")
        if display_artist or display_title:
            print(f"  → Using catalog display: {format_track_label(display_artist, display_title, master_filename)}")

    print(f"  → Asset file: {master_filename}")

    if (
        not force_rebuild
        and audio_delivery_is_fresh(
            str(source_path),
            str(mp3_path),
            recorded_source_xxh3,
            recorded_source_mtime,
        )
    ):
        print("  → Delivery: already up to date (master XXH3 match) — skipped")
        if asset_id:
            update_audio_asset_delivery(
                asset_id,
                source_digest or recorded_source_xxh3,
                ready=True,
                source_mtime=source_mtime,
            )
        return 'skipped'

    print(f"  → Delivery route: {'MP3 copy (source-aware)' if delivery_mode == 'copy' else 'Transcode to MP3 320kbps'}")

    if delivery_mode == 'copy':
        print("  → Copying MP3 source to delivery tier...")
        converted_ok = copy_audio_to_mp3(str(source_path), str(mp3_path))
    else:
        print("  → Converting to MP3 (320kbps)...")
        converted_ok = convert_audio_to_mp3(str(source_path), str(mp3_path))

    if not converted_ok:
        print(f"  ❌ Failed to convert audio ({headline})")
        return False

    print("  → Applying ID3 tags to delivery MP3...")
    set_id3_tags(str(mp3_path), tags)

    if cover_filename:
        process_track_cover(cover_filename)
    elif tags.get('picture') is not None:
        print("  → Track cover: none assigned in Files; embedded artwork was copied into the delivery MP3")
    else:
        print("  → Track cover: none assigned in Files and no embedded artwork on the source")

    if asset_id:
        update_audio_asset_delivery(
            asset_id,
            source_digest,
            ready=True,
            source_mtime=source_mtime,
        )
        if not source_digest and xxhash is None:
            print("  ⚠️  Install Python package `xxhash` for skip-if-fresh (pip install -r scripts/requirements.txt)")

    return True


def main():
    """Main media optimization function."""
    # Verify source directories exist
    include_audio = OPTIMIZE_MODE == 'full'
    special_only = os.environ.get('BANDPROMO_OPTIMIZE_SPECIAL_ASSETS_ONLY', '').strip() == '1'

    if special_only:
        optimize_shell_brand_media_images()
        return 0

    if include_audio and not AUDIO_ORIG_DIR.exists():
        print(f"❌ Error: Audio original directory not found at {AUDIO_ORIG_DIR}")
        sys.exit(1)

    # Create output directories if they don't exist
    AUDIO_OPT_DIR.mkdir(parents=True, exist_ok=True)
    IMG_OPT_DIR.mkdir(parents=True, exist_ok=True)
    IMG_THUMB_DIR.mkdir(parents=True, exist_ok=True)
    PHOTO_OPT_DIR.mkdir(parents=True, exist_ok=True)
    PHOTO_THUMB_DIR.mkdir(parents=True, exist_ok=True)

    print(f"🧭 Optimize mode: {OPTIMIZE_MODE}")
    if include_audio:
        print(f"📁 Audio original: {AUDIO_ORIG_DIR}")
        if AUDIO_MASTER_DIR.exists():
            print(f"📁 Audio master: {AUDIO_MASTER_DIR}")
        print(f"📁 Audio (optimized): {AUDIO_OPT_DIR}")
    print(f"📁 Image original: {IMG_ORIG_DIR}")
    print(f"📁 Image (optimized): {IMG_OPT_DIR} (max {COVER_OPTIMAL_MAX_EDGE}px)")
    print(f"📁 Image (thumb): {IMG_THUMB_DIR} (max {COVER_THUMB_MAX_EDGE}px)")
    print(f"📁 Photo original: {PHOTO_ORIG_DIR}")
    print(f"📁 Photo (optimized): {PHOTO_OPT_DIR} (max {COVER_OPTIMAL_MAX_EDGE}px)")
    print(f"📁 Photo (thumb): {PHOTO_THUMB_DIR} (max {COVER_THUMB_MAX_EDGE}px)")
    if include_audio:
        print("ℹ️  This full optimize pass refreshes audio delivery files plus track covers, photos, and illustrations.")
        print("ℹ️  Theme/share assets in media/special are used directly; social share variants are generated by makeSocial.py.")
    else:
        print("ℹ️  This image-only pass refreshes track covers, photos, and illustrations.")
        print("ℹ️  Theme/share assets in media/special are used directly; social share variants are generated by makeSocial.py.")

    # Reduce first-paint contention from large /media/special shell assets.
    optimize_shell_brand_media_images()

    audio_queue = []
    cover_lookup = load_track_cover_lookup()

    if include_audio:
        print("\n📖 Loading asset registry for audio delivery...")
        audio_queue = load_registry_audio_delivery_queue()
        if not audio_queue:
            print("❌ No registered audio assets found in data/assets/registry.json")
            print("   Run Repair catalog or upload audio via Files first.")
            sys.exit(1)
        print(f"✓ Found {len(audio_queue)} registered audio assets")

        if cover_lookup:
            print(f"✓ Track cover linkage available for {len(cover_lookup)} assets")
        else:
            print("ℹ️  No track cover linkage found (media library state missing or empty)")
    else:
        if cover_lookup:
            print(f"\n📖 Loaded media library state for cover refresh ({len(cover_lookup)} track covers)")
        else:
            print("\n📖 No media library cover linkage found; image-only refresh will skip track-cover-specific work and continue with photos/illustrations.")
    print("=" * 70)

    if include_audio and delivery_queue_needs_ffmpeg(audio_queue):
        if not check_ffmpeg():
            ffmpeg_name = get_ffmpeg_path()
            print(f"\n❌ Error: ffmpeg not found ({ffmpeg_name})")
            print("   Run build.py to auto-install ffmpeg, or install manually.")
            sys.exit(1)

    converted = 0
    skipped = 0
    failed = 0

    if include_audio:
        print("\n🎵 Processing registered audio delivery...")
        if os.environ.get('BANDPROMO_FORCE_AUDIO_DELIVERY', '').strip() == '1':
            print("ℹ️  BANDPROMO_FORCE_AUDIO_DELIVERY=1 — rebuilding every delivery MP3")
        for item in audio_queue:
            master_filename = item.get('master_filename')
            cover_filename = cover_filename_for_audio(master_filename, cover_lookup)
            result = process_audio_delivery(
                master_filename,
                cover_filename,
                display_title=item.get('display_title') or '',
                display_artist=item.get('display_artist') or '',
                display=item.get('display') if isinstance(item.get('display'), dict) else {},
                asset_id=item.get('asset_id') or '',
                recorded_source_mtime=item.get('recorded_source_mtime'),
                recorded_source_xxh3=item.get('recorded_source_xxh3'),
            )
            if result == 'skipped':
                skipped += 1
            elif result:
                converted += 1
            else:
                failed += 1
    elif cover_lookup:
        print("\n🖼️  Processing track cover images...")
        seen = set()
        for cover_filename in sorted(set(cover_lookup.values())):
            if cover_filename and cover_filename not in seen:
                seen.add(cover_filename)
                process_track_cover(cover_filename)
    else:
        print("\n🖼️  Processing track cover images...")
        print("  ✓ No track-cover-specific work queued")

    # ── Visual registry image delivery (asset-id variants) ─────────────────────────
    print("\n🎨 Processing visual registry image delivery...")
    VISUAL_DELIVERY_ROOT.mkdir(parents=True, exist_ok=True)
    visual_queue = load_registry_visual_image_queue()
    # Defense in depth: one delivery pass per on-disk basename even if the
    # registry still holds orphan duplicate ast_ rows for the same file.
    deduped = []
    seen_names = set()
    for asset in visual_queue:
        label = os.path.basename(str(asset.get('original_filename') or asset.get('id') or ''))
        key = label.lower()
        if key in seen_names:
            continue
        seen_names.add(key)
        deduped.append(asset)
    visual_queue = deduped
    visual_count = 0
    visual_skipped = 0
    visual_failed = 0
    if visual_queue:
        for asset in visual_queue:
            label = asset.get('original_filename') or asset.get('id')
            print(f"  Processing visual: {label}")
            result = process_visual_image_asset(asset)
            if result == 'skipped':
                visual_skipped += 1
            elif result:
                visual_count += 1
            else:
                visual_failed += 1
                print(f"    ⚠️  Skipped or failed: {label}")
        if visual_skipped:
            print(f"  ✓ Visual images rebuilt: {visual_count}; already up to date: {visual_skipped}")
    else:
        print("  ✓ No registered visual image assets")

    # ── Photos (legacy dual-read trees; registry pass above is primary) ──────────
    print("\n📷 Processing photos (legacy dual-read)...")
    photo_exts = {'.png', '.jpg', '.jpeg', '.webp'}
    photo_count = 0
    registered_visual_names = {
        os.path.basename(str(a.get('original_filename') or ''))
        for a in visual_queue
    }
    if PHOTO_ORIG_DIR.exists():
        for src in sorted(PHOTO_ORIG_DIR.iterdir()):
            if src.is_file() and src.suffix.lower() in photo_exts:
                if src.name in registered_visual_names:
                    continue
                dest = PHOTO_OPT_DIR / (src.stem + '.jpg')
                thumb = PHOTO_THUMB_DIR / (src.stem + '.jpg')
                print(f"  Processing: {src.name} → {dest.name} + thumb")
                write_cover_delivery_variants(str(src), str(dest), str(thumb), quality=80)
                photo_count += 1
        if photo_count == 0:
            print("  ✓ No unregistered photos found in original/")
    else:
        print(f"  ⚠️  Photo original directory not found: {PHOTO_ORIG_DIR}")

    # ── User illustrations (legacy dual-read for unregistered only) ────────────
    print("\n🖼️  Processing user illustrations (legacy dual-read)...")
    IMG_OPT_DIR.mkdir(parents=True, exist_ok=True)
    IMG_THUMB_DIR.mkdir(parents=True, exist_ok=True)
    img_illus_count = 0
    # Skip cover art filenames that belong to tracks (already handled above)
    track_covers = set(cover_lookup.values()) if cover_lookup else set()
    if IMG_ORIG_DIR.exists():
        for src in sorted(IMG_ORIG_DIR.iterdir()):
            if src.is_file() and src.suffix.lower() in photo_exts and src.name not in track_covers:
                if src.name in registered_visual_names:
                    continue
                dest = IMG_OPT_DIR / (src.stem + '.jpg')
                thumb = IMG_THUMB_DIR / (src.stem + '.jpg')
                print(f"  Processing: {src.name} → {dest.name} + thumb")
                write_cover_delivery_variants(str(src), str(dest), str(thumb), quality=80)
                img_illus_count += 1
        if img_illus_count == 0:
            print("  ✓ No unregistered illustrations found in img/original/")
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

    # Keep all jpg/jpeg in IMG_OPT_DIR / IMG_THUMB_DIR (they're all valid covers)

    # Clean up photos whose originals no longer exist
    if PHOTO_ORIG_DIR.exists():
        allowed_photos = {src.stem + '.jpg'
                          for src in PHOTO_ORIG_DIR.iterdir()
                          if src.is_file() and src.suffix.lower() in photo_exts}
        for folder in (PHOTO_OPT_DIR, PHOTO_THUMB_DIR):
            if not folder.exists():
                continue
            for item in folder.iterdir():
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
        print(f"   Skipped (fresh)  : {skipped}")
        print(f"   Failed           : {failed}")
    else:
        print("   Converted tracks : skipped (image-only mode)")
        print("   Failed           : 0")
    print(f"   Photos optimized       : {photo_count}")
    print(f"   Illustrations optimized: {img_illus_count}")
    print(f"   Visual registry images : {visual_count} (failed {visual_failed})")
    print(f"   Cleaned up files       : {removed}")
    if include_audio:
        print(f"   Audio output     : {AUDIO_OPT_DIR}")
    print(f"   Image output     : {IMG_OPT_DIR} / {IMG_THUMB_DIR}")
    print(f"   Photo output     : {PHOTO_OPT_DIR} / {PHOTO_THUMB_DIR}")
    print(f"   Visual delivery  : {VISUAL_DELIVERY_ROOT}")

    if (MEDIA_DIR / 'share.jpg').exists():
        print(f"\n   ⚠️  Legacy media/share.jpg found — safe to delete (now handled by makeSocial.py)")


if __name__ == '__main__':
    main()
