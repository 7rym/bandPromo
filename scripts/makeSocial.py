import io
import sys

# Force UTF-8 output - compatible with Python 3.6+
if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8', errors='replace', line_buffering=True)
else:
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace', line_buffering=True)

import json
from pathlib import Path

SCRIPT_DIR  = Path(__file__).parent
ROOT_DIR    = SCRIPT_DIR.parent
CONFIG_FILE = ROOT_DIR / 'web-config.json'
SPECIAL_DIR = ROOT_DIR / 'media' / 'special'
BRANDS_DIR  = ROOT_DIR / 'data' / 'brands'
ASSETS_REGISTRY = ROOT_DIR / 'data' / 'assets' / 'registry.json'
VISUAL_DELIVERY_ROOT = ROOT_DIR / 'media' / 'visual' / 'delivery'

# Target dimensions per platform
PLATFORMS = {
    'facebook': (1200, 630),  # Open Graph
    'twitter':  (1200, 630),  # Twitter Card (summary_large_image)
}

# Config paths that brand poster sync (and legacy cover) can share.
SHELL_IMAGE_CONFIG_KEYS = (
    'social.share_image',
    'release.social.share_image',
    'release.brand.poster',
    'release.theme.cover',
    'media.cover',
)


def load_config():
    if not CONFIG_FILE.exists():
        print(f"  ⚠️  web-config.json not found — using defaults")
        return {}
    try:
        with open(CONFIG_FILE, 'r', encoding='utf-8') as f:
            return json.load(f)
    except Exception as e:
        print(f"  ⚠️  Could not read web-config.json: {e}")
        return {}


def config_get(config, dotted, default=None):
    node = config
    for part in dotted.split('.'):
        if not isinstance(node, dict) or part not in node:
            return default
        node = node[part]
    return node


def normalize_media_path(value):
    text = str(value or '').strip().replace('\\', '/')
    if text.startswith(('http://', 'https://')):
        return text
    return '/' + text.lstrip('/')


def load_asset_registry():
    if not ASSETS_REGISTRY.is_file():
        return {}
    try:
        with open(ASSETS_REGISTRY, 'r', encoding='utf-8') as handle:
            payload = json.load(handle)
        assets = payload.get('assets') if isinstance(payload, dict) else None
        return assets if isinstance(assets, dict) else {}
    except Exception:
        return {}


def resolve_visual_delivery_image(asset_id, preferred_variants=('card', 'share', 'poster')):
    """Return a local Path under media/visual/delivery/{asset_id}/ when present."""
    asset_id = str(asset_id or '').strip()
    if not asset_id.startswith('ast_'):
        return None
    delivery_dir = VISUAL_DELIVERY_ROOT / asset_id
    if not delivery_dir.is_dir():
        return None
    for variant in preferred_variants:
        for ext in ('.png', '.jpg', '.jpeg', '.webp'):
            candidate = delivery_dir / f'{variant}{ext}'
            if candidate.is_file():
                return candidate
    # Any image in the delivery folder as last resort.
    for path in sorted(delivery_dir.iterdir()):
        if path.is_file() and path.suffix.lower() in ('.png', '.jpg', '.jpeg', '.webp'):
            return path
    return None


def resolve_asset_id_to_path(asset_id):
    """Resolve registry asset_id → on-disk image path (master/original first)."""
    asset_id = str(asset_id or '').strip()
    if not asset_id.startswith('ast_'):
        return None

    assets = load_asset_registry()
    asset = assets.get(asset_id)
    if not isinstance(asset, dict):
        # Delivery-only fallback when registry entry is missing.
        return resolve_visual_delivery_image(asset_id)

    filename = str(asset.get('original_filename') or asset.get('master_filename') or '').strip()
    filename = Path(filename).name
    master_name = str(asset.get('master_filename') or '').strip()
    master_name = Path(master_name).name
    fmt = str(asset.get('master_format') or '').strip().lower()
    if not fmt and filename:
        fmt = Path(filename).suffix.lstrip('.').lower()

    candidates = []
    if fmt:
        candidates.append(ROOT_DIR / 'media' / 'visual' / 'master' / f'{asset_id}.{fmt}')
    if master_name.startswith('ast_'):
        candidates.append(ROOT_DIR / 'media' / 'visual' / 'master' / master_name)
    if filename:
        candidates.append(ROOT_DIR / 'media' / 'visual' / 'original' / filename)
    bucket = str(asset.get('intake_bucket') or '').strip().lower()
    if filename:
        if bucket == 'special' or bucket == '':
            candidates.append(SPECIAL_DIR / filename)
        if bucket == 'photo':
            candidates.append(ROOT_DIR / 'media' / 'photo' / 'original' / filename)
        else:
            candidates.append(ROOT_DIR / 'media' / 'img' / 'original' / filename)
            candidates.append(SPECIAL_DIR / filename)
            candidates.append(ROOT_DIR / 'media' / 'photo' / 'original' / filename)
    for candidate in candidates:
        if candidate.is_file():
            return candidate

    # Last resort: delivery card/share (may be smaller than OG targets).
    return resolve_visual_delivery_image(asset_id)


def resolve_share_image(config):
    """
    Return the local Path of the configured share image.
    Prefer base brand asset_ids.poster, then social.share_image path.
    """
    brand_id = active_brand_id(config)
    brand_doc = load_brand_document(brand_id)
    if isinstance(brand_doc, dict):
        asset_ids = brand_doc.get('asset_ids') if isinstance(brand_doc.get('asset_ids'), dict) else {}
        poster_asset_id = str(asset_ids.get('poster') or '').strip()
        if poster_asset_id:
            resolved = resolve_asset_id_to_path(poster_asset_id)
            if resolved is not None:
                return resolved
        assets = brand_doc.get('assets') if isinstance(brand_doc.get('assets'), dict) else {}
        brand_poster = normalize_media_path(assets.get('poster', ''))
        if brand_poster.startswith('/media/'):
            candidate = ROOT_DIR / brand_poster.lstrip('/')
            if candidate.is_file():
                return candidate

    path_str = config_get(config, 'social.share_image', '/media/special/bandPromo_share.png')
    path_str = normalize_media_path(path_str)
    # Bare asset ids occasionally land in config during migration.
    if str(path_str).startswith('ast_') or str(path_str).lstrip('/').startswith('ast_'):
        resolved = resolve_asset_id_to_path(str(path_str).lstrip('/'))
        if resolved is not None:
            return resolved
    return ROOT_DIR / str(path_str).lstrip('/\\')


def active_brand_id(config):
    pointers = config_get(config, 'install.pointers', {}) or {}
    if not isinstance(pointers, dict):
        return ''
    brand_id = str(pointers.get('active_brand_id') or pointers.get('active_theme_id') or '').strip()
    if brand_id == 'setup-default':
        return 'bandpromo-default'
    return brand_id


def load_brand_document(brand_id):
    brand_id = str(brand_id or '').strip()
    if not brand_id:
        return None
    candidates = [brand_id]
    if brand_id == 'bandpromo-default':
        candidates.append('setup-default')
    for candidate in candidates:
        path = BRANDS_DIR / f'{candidate}.json'
        if not path.is_file():
            continue
        try:
            with open(path, 'r', encoding='utf-8') as handle:
                decoded = json.load(handle)
            return decoded if isinstance(decoded, dict) else None
        except Exception:
            return None
    return None


def config_keys_pointing_at(config, path_str):
    target = normalize_media_path(path_str)
    matches = []
    for key in SHELL_IMAGE_CONFIG_KEYS:
        value = config_get(config, key, '')
        if normalize_media_path(value) == target:
            matches.append(f'{key}={normalize_media_path(value)}')
    return matches


def suggest_special_images(limit=8):
    if not SPECIAL_DIR.is_dir():
        return []
    names = []
    for path in sorted(SPECIAL_DIR.iterdir()):
        if not path.is_file():
            continue
        if path.suffix.lower() not in ('.png', '.jpg', '.jpeg', '.webp'):
            continue
        # Skip generated platform crops
        stem = path.stem.lower()
        if stem.endswith('_facebook') or stem.endswith('_twitter'):
            continue
        names.append(path.name)
        if len(names) >= limit:
            break
    return names


def print_missing_share_image_help(config, src_image):
    """Operator + developer diagnostics for a missing social source image."""
    try:
        relative = src_image.relative_to(ROOT_DIR).as_posix()
    except ValueError:
        relative = str(src_image)

    configured = normalize_media_path(
        config_get(config, 'social.share_image', '/media/special/bandPromo_share.png')
    )
    matching_keys = config_keys_pointing_at(config, configured)
    brand_id = active_brand_id(config)
    brand_doc = load_brand_document(brand_id)
    brand_title = ''
    brand_poster = ''
    poster_asset_id = ''
    if isinstance(brand_doc, dict):
        brand_title = str(brand_doc.get('title') or '').strip()
        assets = brand_doc.get('assets') if isinstance(brand_doc.get('assets'), dict) else {}
        brand_poster = normalize_media_path(assets.get('poster', ''))
        asset_ids = brand_doc.get('asset_ids') if isinstance(brand_doc.get('asset_ids'), dict) else {}
        poster_asset_id = str(asset_ids.get('poster') or '').strip()

    print('  ❌ Share / poster source image is missing on disk.')
    print(f'     Missing file: {relative}')
    print(f'     Config value: social.share_image = {configured}')
    if matching_keys:
        print('     Same path also set on:')
        for item in matching_keys:
            if item.startswith('social.share_image='):
                continue
            print(f'       - {item}')
    if brand_id:
        label = f'{brand_title} ({brand_id})' if brand_title else brand_id
        print(f'     Base brand: {label}')
        if poster_asset_id:
            print(f'     Brand Shell media → Poster asset_id: {poster_asset_id}')
        if brand_poster:
            print(f'     Brand Shell media → Poster slot: {brand_poster}')
            if brand_poster == configured:
                print('     (Poster sync writes this path into social.share_image / media.cover.)')
    if Path(relative).name.lower().startswith('bandpromo_'):
        print('     Note: bandPromo_* names are bundled demo/seed filenames, not operator upload names.')
        print('           The default-theme package normally installs them under media/special/.')

    print('')
    print('  Fix (operator):')
    brand_locked = bool(brand_doc.get('locked')) if isinstance(brand_doc, dict) else False
    brand_system = bool(brand_doc.get('system')) if isinstance(brand_doc, dict) else False
    if brand_locked or brand_system or brand_id in ('bandpromo-default', 'setup-default'):
        print('     bandPromo Default is locked — you cannot edit its Poster slot in Branding.')
        print('     Do one of the following:')
        print('     A) Content → Branding → edit YOUR brand (a duplicate) → Shell media → Poster,')
        print('        Save, then Set as base on that brand so Publish uses it.')
        print('     B) Restore the missing starter file (Dashboard → Site update / reinstall starter')
        print('        pack, or for local source trees restore media/special/bandPromo_cover.png).')
        editable = []
        registry_path = BRANDS_DIR / 'registry.json'
        if registry_path.is_file():
            try:
                with open(registry_path, 'r', encoding='utf-8') as handle:
                    registry = json.load(handle)
                for entry in registry.get('brands') or []:
                    if not isinstance(entry, dict):
                        continue
                    if entry.get('locked') or entry.get('system'):
                        continue
                    eid = str(entry.get('id') or '').strip()
                    title = str(entry.get('title') or eid).strip()
                    if eid:
                        editable.append(f'{title} ({eid})')
            except Exception:
                editable = []
        if editable:
            print('     Editable brands on this install:')
            for label in editable[:8]:
                print(f'       - {label}')
    else:
        print('     1. Admin → Content → Branding')
        print('     2. Edit the base brand → Shell media → Poster / share image')
        print('     3. Choose an existing image (or upload one), then Save brand')
        print('        If this brand is Base, saving syncs the path into web-config.json.')
    print('     Optional: Settings → Sharing only describes SEO text; the image itself is the Branding poster slot.')

    suggestions = suggest_special_images()
    if suggestions:
        print('')
        print('  Images already in media/special/ you can point at:')
        for name in suggestions:
            marker = ' ← likely share/poster candidate' if 'share' in name.lower() else ''
            print(f'     - {name}{marker}')
    else:
        print('')
        print('  media/special/ has no usable image files right now — upload one via Files → Brand assets')
        print('  (or Visual) before assigning the Branding poster slot.')

    print('')
    print('  Fix (developer / local install):')
    print(f'     Inspect web-config.json keys above, brand doc data/brands/{brand_id or "<active>"}.json')
    print('     assets.poster, and restore missing seed media or retarget the poster path.')
    sys.stdout.flush()


def resize_for_platform(src_path, platform, target_size, quality=85):
    """
    Resize and optimize a share image for a specific platform.
    Maintains aspect ratio with letterboxing (black bars) if needed.
    Outputs a JPEG next to the source with a platform suffix.
    e.g. bandPromo_share.png → bandPromo_share_facebook.jpg
    """
    try:
        from PIL import Image
    except ImportError:
        print("  ❌ Pillow is required: pip install Pillow")
        return False

    try:
        img = Image.open(src_path)
        w, h = img.size
        tw, th = target_size
        dest = src_path.parent / "{0}_{1}.jpg".format(src_path.stem, platform)

        # Skip rewrite when the platform deliverable is already current.
        if dest.is_file():
            try:
                with Image.open(dest) as existing:
                    dest_ok = existing.size == (tw, th)
                src_mtime = src_path.stat().st_mtime
                dest_mtime = dest.stat().st_mtime
                if dest_ok and dest_mtime >= src_mtime:
                    print("  ✓ {0}: already up to date ({1})".format(platform, dest.name))
                    return 'fresh'
            except Exception:
                pass

        if (w, h) == (tw, th) and src_path.suffix.lower() in ('.jpg', '.jpeg'):
            print("  ✓ {0}: already {1}×{2}, no resize needed".format(platform, tw, th))
            return 'fresh'

        # Convert to RGB
        if img.mode in ('RGBA', 'LA', 'P'):
            bg = Image.new('RGB', img.size, (0, 0, 0))
            if img.mode == 'P':
                img = img.convert('RGBA')
            bg.paste(img, mask=img.split()[-1] if img.mode in ('RGBA', 'LA') else None)
            img = bg
        elif img.mode != 'RGB':
            img = img.convert('RGB')

        # Scale to fit inside target, preserve aspect ratio
        _lanczos = getattr(getattr(Image, 'Resampling', None), 'LANCZOS', None) or Image.LANCZOS
        img.thumbnail(target_size, _lanczos)

        # Letterbox onto black canvas
        canvas = Image.new('RGB', target_size, (0, 0, 0))
        offset = ((tw - img.width) // 2, (th - img.height) // 2)
        canvas.paste(img, offset)

        canvas.save(str(dest), 'JPEG', quality=quality, optimize=True)

        dest_size = dest.stat().st_size
        print("  ✓ {0}: {1}×{2} → {3}×{4}  →  {5}  ({6} KB)".format(
            platform, w, h, tw, th, dest.name, dest_size // 1024
        ))
        return 'created'

    except Exception as e:
        print("  ❌ {0}: {1}".format(platform, e))
        return False


def validate_social_config(config):
    """Report on social config completeness."""
    social = config.get('social', {})
    site   = config.get('site', {})

    fields = {
        'site.name':              site.get('name'),
        'site.description':       site.get('description'),
        'site.url':               site.get('url'),
        'social.share_image':     social.get('share_image'),
        'social.share_image_width':  social.get('share_image_width'),
        'social.share_image_height': social.get('share_image_height'),
        'social.twitter':         social.get('twitter'),
        'social.facebook':        social.get('facebook'),
    }

    missing = [k for k, v in fields.items() if not v]
    if missing:
        for m in missing:
            print(f"  ⚠️  Not configured: {m}")
    else:
        print(f"  ✓ All social fields configured")

    return len(missing) == 0


def main():
    print("\n📣 Generating social media assets...")
    sys.stdout.flush()

    config     = load_config()
    src_image  = resolve_share_image(config)
    all_ok     = True
    created = 0
    fresh = 0
    failed = 0

    print(f"\n── Config validation ──────────────────────────────────────────────────")
    validate_social_config(config)

    print(f"\n── Share image processing ─────────────────────────────────────────────")
    try:
        print(f"  Source: {src_image.relative_to(ROOT_DIR)}")
    except ValueError:
        print(f"  Source: {src_image}")

    if not src_image.exists():
        print_missing_share_image_help(config, src_image)
        return False

    src_w, src_h = 0, 0
    try:
        from PIL import Image
        with Image.open(src_image) as im:
            src_w, src_h = im.size
        print(f"  Size: {src_w}×{src_h}")
    except ImportError:
        print("  ❌ Pillow is required: pip install Pillow")
        return False

    for platform, target_size in PLATFORMS.items():
        result = resize_for_platform(src_image, platform, target_size)
        if result == 'created':
            created += 1
        elif result == 'fresh':
            fresh += 1
        else:
            failed += 1
            all_ok = False

    # Warn if old legacy file still exists
    legacy = ROOT_DIR / 'media' / 'share.jpg'
    if legacy.exists():
        print(f"\n  ⚠️  Legacy media/share.jpg found — safe to delete (replaced by media/special/)")

    print(f"\n── Summary ────────────────────────────────────────────────────────────")
    if all_ok:
        print(f"  ✅ Social assets ready in {SPECIAL_DIR.relative_to(ROOT_DIR)}/")
    else:
        print(f"  ⚠️  Some assets could not be generated — check warnings above")

    try:
        from bandpromo_build_stats import emit_build_stats
        emit_build_stats(
            handled=created + fresh + failed,
            created=created,
            fresh=fresh,
            failed=failed,
            scope='social',
        )
    except Exception:
        pass

    sys.stdout.flush()
    return all_ok


if __name__ == '__main__':
    sys.exit(0 if main() else 1)
