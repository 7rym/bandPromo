import io
import sys

# Force UTF-8 output - compatible with Python 3.6+
if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8', errors='replace', line_buffering=True)
else:
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace', line_buffering=True)

import json
from pathlib import Path

SCRIPT_DIR    = Path(__file__).parent
ROOT_DIR      = SCRIPT_DIR.parent
CONFIG_FILE   = ROOT_DIR / 'web-config.json'
MANIFEST_FILE = ROOT_DIR / 'site.webmanifest'


def generate_manifest():
    print("\n📝 Generating site.webmanifest...")
    sys.stdout.flush()

    config = {}
    if CONFIG_FILE.exists():
        try:
            with open(CONFIG_FILE, 'r', encoding='utf-8') as f:
                config = json.load(f)
            print("  ✅ Loaded web-config.json")
            sys.stdout.flush()
        except Exception as e:
            print(f"  ⚠️  Could not read web-config.json: {e}")
            sys.stdout.flush()

    site     = config.get('site', {})
    branding = config.get('branding', {})
    social   = config.get('social', {})

    manifest = {
        "name":             site.get('name', 'My Site'),
        "short_name":       site.get('short_name', 'Site'),
        "description":      site.get('description', 'A web application'),
        "theme_color":      branding.get('theme_color', '#121212'),
        "background_color": branding.get('background_color', '#000000'),
        "display":          "standalone",
        "start_url":        "/",
        "scope":            "/",
        "orientation":      "any",
        "categories":       social.get('categories', ['entertainment']),
        "prefer_related_applications": False,
        "icons": [
            {"src": "/media/icons/favicon-16x16.png",            "sizes": "16x16",   "type": "image/png", "purpose": "any"},
            {"src": "/media/icons/favicon-32x32.png",            "sizes": "32x32",   "type": "image/png", "purpose": "any"},
            {"src": "/media/icons/favicon-96x96.png",            "sizes": "96x96",   "type": "image/png", "purpose": "any"},
            {"src": "/media/icons/web-app-manifest-192x192.png", "sizes": "192x192", "type": "image/png", "purpose": "any"},
            {"src": "/media/icons/web-app-manifest-192x192.png", "sizes": "192x192", "type": "image/png", "purpose": "maskable"},
            {"src": "/media/icons/web-app-manifest-512x512.png", "sizes": "512x512", "type": "image/png", "purpose": "any"},
            {"src": "/media/icons/web-app-manifest-512x512.png", "sizes": "512x512", "type": "image/png", "purpose": "maskable"},
        ]
    }

    try:
        payload = json.dumps(manifest, indent=2, ensure_ascii=False)
        if not payload.endswith('\n'):
            payload = payload + '\n'

        if MANIFEST_FILE.exists():
            try:
                existing = MANIFEST_FILE.read_text(encoding='utf-8')
            except Exception:
                existing = ''
            if existing.replace('\r\n', '\n') == payload.replace('\r\n', '\n'):
                print("  ✅ site.webmanifest already up to date")
                sys.stdout.flush()
                try:
                    from bandpromo_build_stats import emit_build_stats
                    emit_build_stats(handled=1, fresh=1, scope='manifest')
                except Exception:
                    pass
                return True

        with open(MANIFEST_FILE, 'w', encoding='utf-8', newline='\n') as f:
            f.write(payload)
        print("  ✅ site.webmanifest written")
        sys.stdout.flush()
        try:
            from bandpromo_build_stats import emit_build_stats
            emit_build_stats(handled=1, created=1, scope='manifest')
        except Exception:
            pass
        return True
    except Exception as e:
        print(f"  ❌ Could not write manifest: {e}")
        sys.stdout.flush()
        try:
            from bandpromo_build_stats import emit_build_stats
            emit_build_stats(handled=1, failed=1, scope='manifest')
        except Exception:
            pass
        return False


if __name__ == '__main__':
    sys.exit(0 if generate_manifest() else 1)
