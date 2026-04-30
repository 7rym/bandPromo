import io
import sys

# Force UTF-8 output - compatible with Python 3.6+
if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8', errors='replace', line_buffering=True)
else:
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace', line_buffering=True)

import json
import os
from pathlib import Path

SCRIPT_DIR  = Path(__file__).parent
ROOT_DIR    = SCRIPT_DIR.parent
CONFIG_FILE = ROOT_DIR / 'web-config.json'
SPECIAL_DIR = ROOT_DIR / 'media' / 'special'

# Target dimensions per platform
PLATFORMS = {
    'facebook': (1200, 630),  # Open Graph
    'twitter':  (1200, 630),  # Twitter Card (summary_large_image)
}


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


def resolve_share_image(config):
    """
    Return the local Path of the configured share image.
    social.share_image is a URL-style path like /media/special/bandPromo_share.png.
    """
    path_str = config.get('social', {}).get('share_image', '/media/special/bandPromo_share.png')
    # Strip leading slash and resolve relative to ROOT_DIR
    return ROOT_DIR / path_str.lstrip('/')


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

        if (w, h) == (tw, th) and src_path.suffix.lower() in ('.jpg', '.jpeg'):
            print(f"  ✓ {platform}: already {tw}×{th}, no resize needed")
            return True

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

        dest = src_path.parent / f"{src_path.stem}_{platform}.jpg"
        canvas.save(str(dest), 'JPEG', quality=quality, optimize=True)

        src_size  = src_path.stat().st_size
        dest_size = dest.stat().st_size
        print(f"  ✓ {platform}: {w}×{h} → {tw}×{th}  →  {dest.name}  ({dest_size // 1024} KB)")
        return True

    except Exception as e:
        print(f"  ❌ {platform}: {e}")
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

    print(f"\n── Config validation ──────────────────────────────────────────────────")
    validate_social_config(config)

    print(f"\n── Share image processing ─────────────────────────────────────────────")
    print(f"  Source: {src_image.relative_to(ROOT_DIR)}")

    if not src_image.exists():
        print(f"  ❌ Source image not found — upload it via Admin → Config → Media")
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
        if not resize_for_platform(src_image, platform, target_size):
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

    sys.stdout.flush()
    return all_ok


if __name__ == '__main__':
    sys.exit(0 if main() else 1)
