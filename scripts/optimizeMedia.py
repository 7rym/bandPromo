"""
optimizeMedia — Web-Optimized Media Generator

Builds registry-driven delivery variants:
- Audio → tagless MP3 under media/audio/optimal (identity in registry + player payload)
- Visual → role-based variants under media/visual/delivery/{asset_id}/

Scope comes from data/assets/registry.json. convert_cover_to_jpeg remains for
audioSourceDelivery cover helpers.
"""

import os
import json
import subprocess
import sys
import shutil
from datetime import datetime, timezone
from pathlib import Path

SCRIPT_DIR   = Path(__file__).parent
sys.path.insert(0, str(SCRIPT_DIR))
try:
    import bandpromo_python_path
    bandpromo_python_path.ensure_vendor_on_sys_path()
except Exception:
    pass

from mutagen.apev2 import APEv2, APENoHeaderError
from mutagen.id3 import ID3, ID3NoHeaderError

try:
    import xxhash
except ImportError:
    xxhash = None

import stdio_utf8
stdio_utf8.configure()

# Find the root directory (scripts/..)
ROOT_DIR     = SCRIPT_DIR.parent
AUDIO_ORIG_DIR = ROOT_DIR / 'media' / 'audio' / 'original'
AUDIO_MASTER_DIR = ROOT_DIR / 'media' / 'audio' / 'master'
AUDIO_OPT_DIR  = ROOT_DIR / 'media' / 'audio' / 'optimal'
IMG_ORIG_DIR   = ROOT_DIR / 'media' / 'img'   / 'original'
IMG_OPT_DIR    = ROOT_DIR / 'media' / 'img'   / 'optimal'
PHOTO_ORIG_DIR = ROOT_DIR / 'media' / 'photo' / 'original'
ASSET_REGISTRY_FILE = ROOT_DIR / 'data' / 'assets' / 'registry.json'
MEDIA_DIR    = ROOT_DIR / 'media'
OPTIMIZE_MODE = os.environ.get('BANDPROMO_OPTIMIZE_MODE', '').strip().lower() or 'image-only'
_XXHASH_WARNED = False


def warn_xxhash_missing_once():
    global _XXHASH_WARNED
    if xxhash is not None or _XXHASH_WARNED:
        return
    _XXHASH_WARNED = True
    print('  ⚠️  xxhash unavailable — skip-if-fresh disabled until scripts/vendor bootstrap succeeds')

# Player card max CSS is 600px; deliver slightly above for sharpness.
COVER_OPTIMAL_MAX_EDGE = 720
# Playlist rows / cover-flow thumbs (~70–100 CSS px).
COVER_THUMB_MAX_EDGE = 100

DELIVERY_CONTEXTS_FILE = SCRIPT_DIR / 'delivery-contexts.json'
VISUAL_DELIVERY_ROOT = ROOT_DIR / 'media' / 'visual' / 'delivery'
VISUAL_ORIG_DIR = ROOT_DIR / 'media' / 'visual' / 'original'
VISUAL_MASTER_DIR = ROOT_DIR / 'media' / 'visual' / 'master'


def load_delivery_contexts():
    """Load scripts/delivery-contexts.json; fall back to built-in defaults."""
    defaults = {
        'variants': {
            'thumb': {'max_edge': COVER_THUMB_MAX_EDGE},
            'card': {'max_edge': COVER_OPTIMAL_MAX_EDGE},
            'huge': {'max_width': 1920, 'max_height': 1080},
            'logo': {'max_edge': 640},
            'poster': {'max_edge': COVER_OPTIMAL_MAX_EDGE},
        },
        'role_variants': {
            'default_image': ['thumb', 'card', 'huge'],
            'brand-logo': ['logo', 'thumb', 'huge'],
            'unassigned': ['thumb', 'card', 'huge'],
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


def variant_max_box(variant_name, fallback_edge=None):
    """Return a (max_width, max_height) contain box for one image variant."""
    variants = DELIVERY_CONTEXTS.get('variants') if isinstance(DELIVERY_CONTEXTS.get('variants'), dict) else {}
    entry = variants.get(variant_name) if isinstance(variants.get(variant_name), dict) else {}
    edge = variant_max_edge(variant_name, fallback_edge)
    try:
        max_width = int(entry.get('max_width', edge))
    except (TypeError, ValueError):
        max_width = edge
    try:
        max_height = int(entry.get('max_height', edge))
    except (TypeError, ValueError):
        max_height = edge
    return max(1, max_width), max(1, max_height)


# Prefer registry-driven edges when available.
COVER_OPTIMAL_MAX_EDGE = variant_max_edge('card', COVER_OPTIMAL_MAX_EDGE) or COVER_OPTIMAL_MAX_EDGE
COVER_THUMB_MAX_EDGE = variant_max_edge('thumb', COVER_THUMB_MAX_EDGE) or COVER_THUMB_MAX_EDGE

try:
    from PIL import Image
except ImportError:
    print("❌ Error: Pillow (PIL) is required for image conversion")
    print("   Install with: pip install Pillow")
    sys.exit(1)


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


def image_source_has_alpha(source_path):
    try:
        with Image.open(str(source_path)) as img:
            return png_has_visible_transparency(img)
    except Exception:
        return False


def convert_image_delivery_variant(source_path, dest_path, max_width, max_height, quality=75, preserve_alpha=False):
    """
    Write one delivery variant. When preserve_alpha is True and the source has
    transparency, emit PNG; otherwise JPEG (flattening onto white only when needed).
    """
    if not os.path.exists(source_path):
        print("    ⚠️  Source image not found: {}".format(source_path))
        return None

    max_width = max(1, int(max_width))
    max_height = max(1, int(max_height))
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
            "src, dest, max_width, max_height = sys.argv[1], sys.argv[2], int(sys.argv[3]), int(sys.argv[4])\n"
            "img = Image.open(src)\n"
            "if img.mode == 'P':\n"
            "    img = img.convert('RGBA')\n"
            "elif img.mode not in ('RGBA', 'LA'):\n"
            "    img = img.convert('RGBA') if 'A' in img.getbands() else img.convert('RGB').convert('RGBA')\n"
            "w, h = img.size\n"
            "scale = min(1.0, float(max_width) / float(w), float(max_height) / float(h))\n"
            "if scale < 1.0:\n"
            "    new_size = (max(1, int(round(w * scale))), max(1, int(round(h * scale))))\n"
            "    resample = getattr(getattr(Image, 'Resampling', Image), 'LANCZOS', Image.LANCZOS)\n"
            "    img = img.resize(new_size, resample)\n"
            "img.save(dest, 'PNG', optimize=True)\n"
        )
        args = [sys.executable, '-c', worker, source_path, str(dest_path), str(max_width), str(max_height)]
    else:
        worker = (
            "import sys\n"
            "from PIL import Image\n"
            "src, dest, quality, max_width, max_height = sys.argv[1], sys.argv[2], int(sys.argv[3]), int(sys.argv[4]), int(sys.argv[5])\n"
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
            "scale = min(1.0, float(max_width) / float(w), float(max_height) / float(h))\n"
            "if scale < 1.0:\n"
            "    new_size = (max(1, int(round(w * scale))), max(1, int(round(h * scale))))\n"
            "    resample = getattr(getattr(Image, 'Resampling', Image), 'LANCZOS', Image.LANCZOS)\n"
            "    img = img.resize(new_size, resample)\n"
            "img.save(dest, 'JPEG', quality=quality, optimize=True)\n"
        )
        args = [
            sys.executable, '-c', worker, source_path, str(dest_path),
            str(quality), str(max_width), str(max_height),
        ]

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

    print("    ✓ Variant {}: {} (max {}x{}px, {})".format(
        dest_path.stem, dest_path.name, max_width, max_height, 'PNG alpha' if use_png else 'JPEG'
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


def visual_working_path_for_asset(asset):
    """
    Resolve visual source bytes for delivery build.
    Master only — original is not a working copy.
    """
    asset_id = str(asset.get('id') or '').strip()
    master_name = os.path.basename(str(asset.get('master_filename') or '').strip())
    fmt = str(asset.get('master_format') or '').strip().lower()
    if not fmt and master_name:
        fmt = Path(master_name).suffix.lstrip('.').lower()

    candidates = []
    if asset_id.startswith('ast_') and fmt:
        candidates.append(VISUAL_MASTER_DIR / '{}.{}'.format(asset_id, fmt))
    if master_name.startswith('ast_'):
        candidates.append(VISUAL_MASTER_DIR / master_name)

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
    if 'huge' not in out:
        out.append('huge')
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
    return {
        'path': rel,
        'width': int(width),
        'height': int(height),
        'format': path.suffix.lower().lstrip('.'),
        'bytes': int(size),
        'updated_at': datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ'),
    }


def update_visual_asset_delivery(asset_id, variants_map, has_alpha=None, source_xxh3=None, master_width=None, master_height=None):
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
    if master_width and master_height:
        asset['master_width'] = int(master_width)
        asset['master_height'] = int(master_height)
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


def stamp_visual_master_dimensions(asset_id, width, height):
    """Record master pixel size without rewriting delivery variants."""
    width = int(width or 0)
    height = int(height or 0)
    if not asset_id or width <= 0 or height <= 0 or not ASSET_REGISTRY_FILE.exists():
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
    if int(asset.get('master_width') or 0) == width and int(asset.get('master_height') or 0) == height:
        return False
    asset['master_width'] = width
    asset['master_height'] = height
    assets[asset_id] = asset
    payload['assets'] = assets
    try:
        ASSET_REGISTRY_FILE.write_text(
            json.dumps(payload, indent=2, ensure_ascii=False) + '\n',
            encoding='utf-8',
        )
        return True
    except Exception:
        return False


def image_master_pixel_size(source_path):
    try:
        with Image.open(str(source_path)) as img:
            width, height = img.size
        return int(width), int(height)
    except Exception:
        return 0, 0


def visual_image_delivery_is_fresh(asset, source_path, required_variants):
    """True when required variants exist, master XXH3 matches, and sizes match policy.

    XXH3 alone is not enough: changing delivery-contexts.json max_edge (e.g. thumb
    100→150) must rebuild even when the master bytes are unchanged.
    """
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

    try:
        with Image.open(str(source_path)) as source_img:
            source_width, source_height = source_img.size
    except Exception:
        return False

    for variant in required_variants:
        dest_path = None
        for ext in ('.png', '.jpg', '.jpeg', '.webp'):
            candidate = delivery_dir / '{}{}'.format(variant, ext)
            if candidate.is_file():
                dest_path = candidate
                break
        if dest_path is None:
            return False

        max_width, max_height = variant_max_box(
            variant,
            COVER_OPTIMAL_MAX_EDGE if variant != 'thumb' else COVER_THUMB_MAX_EDGE,
        )
        expected_scale = min(
            1.0,
            float(max_width) / float(source_width),
            float(max_height) / float(source_height),
        )
        expected_width = max(1, int(round(source_width * expected_scale)))
        expected_height = max(1, int(round(source_height * expected_scale)))

        try:
            with Image.open(str(dest_path)) as dest_img:
                delivery_width, delivery_height = dest_img.size
        except Exception:
            return False

        # Rebuild after policy changes, including non-square contain boxes.
        if abs(int(delivery_width) - expected_width) > 1:
            return False
        if abs(int(delivery_height) - expected_height) > 1:
            return False
        if delivery_width > int(max_width) + 1 or delivery_height > int(max_height) + 1:
            return False
    return True


def process_visual_image_asset(asset):
    """Write media/visual/delivery/{id}/{variant} from the visual master."""
    asset_id = str(asset.get('id') or '').strip()
    source = visual_working_path_for_asset(asset)
    if not asset_id or source is None:
        return False

    role = str(asset.get('role') or 'unassigned')
    required = role_image_variants(role)
    source_width, source_height = image_master_pixel_size(source)
    if visual_image_delivery_is_fresh(asset, source, required):
        stamp_visual_master_dimensions(asset_id, source_width, source_height)
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
        max_width, max_height = variant_max_box(
            variant,
            COVER_OPTIMAL_MAX_EDGE if variant != 'thumb' else COVER_THUMB_MAX_EDGE,
        )
        dest = delivery_dir / ('{}.png'.format(variant) if preserve_alpha else '{}.jpg'.format(variant))
        quality = 82 if variant == 'huge' else (75 if variant != 'thumb' else 65)
        written = convert_image_delivery_variant(
            str(source),
            str(dest),
            max_width=max_width,
            max_height=max_height,
            quality=quality,
            preserve_alpha=preserve_alpha,
        )
        if written:
            variants_written[variant] = variant_manifest_entry(written)
            print("    → Built {}: {}".format(variant, Path(written).name))

    if variants_written:
        source_digest = file_xxh3_hex(source)
        update_visual_asset_delivery(
            asset_id,
            variants_written,
            has_alpha=has_alpha,
            source_xxh3=source_digest or None,
            master_width=source_width,
            master_height=source_height,
        )
        if not source_digest and xxhash is None:
            warn_xxhash_missing_once()
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


def delivery_mp3_has_tags(mp3_path):
    """True when a delivery MP3 still carries ID3 or APEv2 (must rebuild/strip)."""
    path = Path(mp3_path)
    if not path.is_file():
        return False
    try:
        ID3(str(path))
        return True
    except ID3NoHeaderError:
        pass
    except Exception:
        pass
    try:
        APEv2(str(path))
        return True
    except APENoHeaderError:
        return False
    except Exception:
        return False


def strip_delivery_audio_tags(mp3_path):
    """Remove all ID3 and APEv2 tags from a delivery MP3 (tagless delivery policy)."""
    path = str(mp3_path)
    try:
        try:
            ape = APEv2(path)
            ape.delete()
        except APENoHeaderError:
            pass
        except Exception:
            pass
        try:
            id3 = ID3(path)
            id3.delete()
        except ID3NoHeaderError:
            pass
        except Exception:
            pass
        return True
    except Exception as exc:
        print(f"  Warning: Could not strip delivery tags: {exc}")
        return False


def audio_delivery_is_fresh(source_path, mp3_path, recorded_source_xxh3=None, recorded_source_mtime=None):
    """True when delivery MP3 exists, is tagless, and master XXH3 matches the last build.

    Falls back to legacy mtime fingerprint only when XXH3 is unavailable (no xxhash package
    or no recorded digest yet). First builds always rebuild so we learn the fingerprint.
    Previously tagged delivery files are treated as stale so Publish migrates them.
    """
    try:
        source = Path(source_path)
        dest = Path(mp3_path)
        if not source.is_file() or not dest.is_file() or dest.stat().st_size <= 0:
            return False
        if os.environ.get('BANDPROMO_FORCE_AUDIO_DELIVERY', '').strip() == '1':
            return False
        if delivery_mp3_has_tags(dest):
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
            return path, 'master'

    stem = Path(filename).stem
    source_suffix = Path(filename).suffix.lower()
    preferred_suffixes = ['.flac', '.mp3', '.wav'] if source_suffix == '.wav' else [source_suffix, '.flac', '.mp3', '.wav']
    seen = set()
    for suffix in preferred_suffixes:
        candidate = AUDIO_MASTER_DIR / '{0}{1}'.format(stem, suffix)
        key = str(candidate).lower()
        if key in seen:
            continue
        seen.add(key)
        if candidate.exists() and candidate.is_file():
            return candidate, 'master'

    # Master or fail — never fall back to media/audio/original.
    return AUDIO_MASTER_DIR / os.path.basename(str(filename or '').strip()), 'master'


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


def process_audio_delivery(
    master_filename,
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
    if display_artist or display_title:
        print(f"  → Catalog display: {format_track_label(display_artist, display_title, master_filename)}")
    else:
        print("  → Catalog display: (empty — using filename for logs)")

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
        print("  → Delivery: already up to date (tagless + master XXH3 match) — skipped")
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

    print("  → Stripping tags from delivery MP3 (tagless delivery policy)...")
    strip_delivery_audio_tags(str(mp3_path))
    print("  → Track cover: Visual delivery only (no stem img/optimal|thumb dual-write)")

    if asset_id:
        update_audio_asset_delivery(
            asset_id,
            source_digest,
            ready=True,
            source_mtime=source_mtime,
        )
        if not source_digest and xxhash is None:
            warn_xxhash_missing_once()

    return True


def main():
    """Main media optimization function."""
    # Verify source directories exist
    include_audio = OPTIMIZE_MODE == 'full'

    if include_audio:
        # PRP imports ship masters only; keep original/ as an empty intake folder.
        AUDIO_ORIG_DIR.mkdir(parents=True, exist_ok=True)
        AUDIO_MASTER_DIR.mkdir(parents=True, exist_ok=True)

    # Create output directories if they don't exist
    AUDIO_OPT_DIR.mkdir(parents=True, exist_ok=True)
    VISUAL_DELIVERY_ROOT.mkdir(parents=True, exist_ok=True)

    print(f"🧭 Optimize mode: {OPTIMIZE_MODE}")
    if include_audio:
        print(f"📁 Audio original: {AUDIO_ORIG_DIR}")
        if AUDIO_MASTER_DIR.exists():
            print(f"📁 Audio master: {AUDIO_MASTER_DIR}")
        print(f"📁 Audio (optimized): {AUDIO_OPT_DIR}")
    print(f"📁 Visual delivery: {VISUAL_DELIVERY_ROOT}")
    if include_audio:
        print("ℹ️  This full optimize pass refreshes audio delivery plus Visual registry image delivery.")
    else:
        print("ℹ️  This image-only pass refreshes Visual registry image delivery.")

    audio_queue = []

    if include_audio:
        print("\n📖 Loading asset registry for audio delivery...")
        audio_queue = load_registry_audio_delivery_queue()
        if not audio_queue:
            print("❌ No registered audio assets found in data/assets/registry.json")
            print("   Run Repair catalog or upload audio via Files first.")
            sys.exit(1)
        print(f"✓ Found {len(audio_queue)} registered audio assets")
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
            result = process_audio_delivery(
                master_filename,
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

    # ── Unregistered intake (register-or-fail) ───────────────────────────────────
    print("\n📷 Photos / illustrations intake check (register-or-fail)...")
    photo_exts = {'.png', '.jpg', '.jpeg', '.webp'}
    registered_visual_names = {
        os.path.basename(str(a.get('original_filename') or ''))
        for a in visual_queue
    }
    orphan_count = 0
    for label, orig_dir in (('photo', PHOTO_ORIG_DIR), ('illustration', IMG_ORIG_DIR)):
        if not orig_dir.exists():
            continue
        for src in sorted(orig_dir.iterdir()):
            if not src.is_file() or src.suffix.lower() not in photo_exts:
                continue
            if src.name in registered_visual_names:
                continue
            orphan_count += 1
            print(
                "  ⚠️  Unregistered {} {} — skipped (run Content autofix / register before Publish)"
                .format(label, src.name)
            )
    if orphan_count == 0:
        print("  ✓ No unregistered image originals waiting for registry")
    else:
        print("  ⚠️  {} unregistered image(s) were not converted (register-or-fail)".format(orphan_count))

    # ── Cleanup stale audio delivery files ───────────────────────────────────────────
    print("\n🧹 Cleaning up audio delivery directory...")

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

    if removed == 0:
        print("  ✓ Audio delivery directory is clean")

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
    print(f"   Unregistered images skipped : {orphan_count}")
    print(f"   Visual registry images : rebuilt {visual_count}, fresh {visual_skipped}, failed {visual_failed}")
    print(f"   Cleaned up files       : {removed}")
    if include_audio:
        print(f"   Audio output     : {AUDIO_OPT_DIR}")
    print(f"   Visual delivery  : {VISUAL_DELIVERY_ROOT}")

    if (MEDIA_DIR / 'share.jpg').exists():
        print(f"\n   ⚠️  Legacy media/share.jpg found — safe to delete (now handled by makeSocial.py)")

    audio_handled = (converted + skipped + failed) if include_audio else 0
    visual_handled = visual_count + visual_skipped + visual_failed
    try:
        from bandpromo_build_stats import emit_build_stats
        emit_build_stats(
            handled=audio_handled + visual_handled,
            created=converted + visual_count,
            fresh=skipped + visual_skipped,
            failed=failed + visual_failed,
            scope='media',
        )
    except Exception:
        pass


if __name__ == '__main__':
    main()
