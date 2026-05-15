"""
Build pipeline for bandPromo
Runs the full pipeline: preflight checks → source config → optimize media → manifest

Designed to be called from the admin panel (Admin > Config > Build),
but can also be run directly from the command line.

Output (new structure):
  media/audio/original/   - source FLAC files (uploaded by admin)
  media/audio/optimal/  - converted MP3 files (generated here)
  media/img/original/     - source PNG cover art (uploaded by admin)
  media/img/optimal/   - optimised JPEG covers (generated here)
  play/playlist.json  - single playlist config for the player
  media/special/*_facebook.jpg, *_twitter.jpg – social share images
  media/special/    - platform-specific social share images (generated here)
"""

import subprocess
import sys
import os
import io
import json
import platform
import urllib.request
import stat
from pathlib import Path

import zipfile

# Debug: capture default encoding BEFORE any reconfiguration
_default_encoding = sys.stdout.encoding

# Force UTF-8 output — compatible with Python 3.6+
if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8', errors='replace')
else:
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

print("Python version: " + sys.version)
print("Default stdout encoding: " + str(_default_encoding))

SCRIPT_DIR = Path(__file__).parent
ROOT_DIR   = SCRIPT_DIR.parent

REQUIREMENTS = SCRIPT_DIR / 'requirements.txt'
FFMPEG_BIN   = SCRIPT_DIR / 'bin' / ('ffmpeg.exe' if platform.system() == 'Windows' else 'ffmpeg')
SUPPORTED_AUDIO_EXTENSIONS = ('.flac', '.mp3', '.wav')
KNOWN_AUDIO_EXTENSIONS = SUPPORTED_AUDIO_EXTENSIONS + ('.wav', '.aif', '.aiff', '.m4a', '.aac', '.ogg', '.wma')

RUNTIME_TEMPLATE_MAP = (
    ('biblioteca/templates/web-config.template.json', 'web-config.json', 'json'),
    ('biblioteca/templates/gallery.template.json', 'data/gallery.json', 'json'),
    ('biblioteca/templates/bio.template.html', 'data/bio.html', 'text'),
    ('biblioteca/templates/faq.template.html', 'data/faq.html', 'text'),
)


# ── Preflight helpers ─────────────────────────────────────────────────────────

def ensure_runtime_files_seeded():
    """Seed required runtime files from tracked templates if missing."""
    print('Checking required runtime files...')
    sys.stdout.flush()

    errors = []

    for template_rel, target_rel, kind in RUNTIME_TEMPLATE_MAP:
        template_path = ROOT_DIR / template_rel
        target_path = ROOT_DIR / target_rel

        if not template_path.exists():
            errors.append(f'Missing template file: {template_path}')
            continue

        try:
            template_content = template_path.read_text(encoding='utf-8')
        except Exception as e:
            errors.append(f'Could not read template file: {template_path} ({e})')
            continue

        if kind == 'json':
            try:
                loaded = json.loads(template_content)
                if not isinstance(loaded, (dict, list)):
                    errors.append(f'Invalid JSON template/root type: {template_path}')
                    continue
            except Exception as e:
                errors.append(f'Invalid JSON template: {template_path} ({e})')
                continue

        target_path.parent.mkdir(parents=True, exist_ok=True)

        if not target_path.exists():
            try:
                target_path.write_text(template_content, encoding='utf-8')
                print(f'  Seeded missing runtime file: {target_path}')
            except Exception as e:
                errors.append(f'Could not write runtime file: {target_path} ({e})')
                continue

        if kind == 'json':
            try:
                target_loaded = json.loads(target_path.read_text(encoding='utf-8'))
                if not isinstance(target_loaded, (dict, list)):
                    errors.append(f'Invalid runtime JSON/root type: {target_path}')
            except Exception as e:
                errors.append(f'Invalid runtime JSON file: {target_path} ({e})')

    if errors:
        print('  ❌ Runtime file preflight failed:')
        for err in errors:
            print('    - ' + err)
        sys.stdout.flush()
        return False

    print('  ✅ Required runtime files present')
    sys.stdout.flush()
    return True

def install_pip_dependencies():
    """Attempt pip install; always returns True (sub-scripts handle missing imports)."""
    print('Checking Python dependencies...')
    sys.stdout.flush()
    if not REQUIREMENTS.exists():
        print('  WARNING requirements.txt not found, skipping')
        sys.stdout.flush()
        return True
    try:
        result = subprocess.run(
            [sys.executable, '-m', 'pip', 'install', '-r', str(REQUIREMENTS),
             '--quiet', '--only-binary=:all:'],
            stdout=subprocess.PIPE, stderr=subprocess.PIPE
        )
        if result.returncode == 0:
            print('  OK Dependencies installed')
        else:
            print('  WARNING pip install skipped (will use system packages)')
    except Exception as e:
        print('  WARNING Could not run pip: ' + str(e))
    sys.stdout.flush()
    return True  # Always continue - sub-scripts fail with clear messages if needed



def find_ffmpeg():
    """Return a usable ffmpeg executable path, or None."""
    # 1. Local binary in scripts/bin/
    if FFMPEG_BIN.exists():
        return str(FFMPEG_BIN)
    # 2. System PATH
    try:
        subprocess.run(
            ['ffmpeg', '-version'],
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, check=True
        )
        return 'ffmpeg'
    except (FileNotFoundError, subprocess.CalledProcessError):
        pass
    return None


def download_ffmpeg_static():
    """Download a static ffmpeg binary for Linux x86_64 (typical shared hosting)."""
    if platform.system() != 'Linux':
        return False

    machine = platform.machine().lower()
    arch = 'arm64' if ('aarch64' in machine or 'arm64' in machine) else 'amd64'
    url = f"https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-{arch}-static.tar.xz"

    print(f"  ⬇️  Downloading static ffmpeg binary for Linux {arch}...")
    print(f"      Source: johnvansickle.com/ffmpeg")
    sys.stdout.flush()

    bin_dir = FFMPEG_BIN.parent
    bin_dir.mkdir(parents=True, exist_ok=True)
    archive = bin_dir / 'ffmpeg.tar.xz'

    try:
        urllib.request.urlretrieve(url, str(archive))
    except Exception as e:
        print(f"  ❌ Download failed: {e}")
        print("     Please install ffmpeg manually: https://ffmpeg.org/download.html")
        sys.stdout.flush()
        return False

    try:
        import tarfile
        with tarfile.open(str(archive), 'r:xz') as tar:
            for member in tar.getmembers():
                if member.name.endswith('/ffmpeg') and '/' in member.name:
                    member.name = 'ffmpeg'
                    tar.extract(member, path=str(bin_dir))
                    break
        archive.unlink()
        FFMPEG_BIN.chmod(FFMPEG_BIN.stat().st_mode | stat.S_IXUSR | stat.S_IXGRP | stat.S_IXOTH)
        print(f"  ✅ ffmpeg installed to {FFMPEG_BIN}")
        sys.stdout.flush()
        return True
    except Exception as e:
        print(f"  ❌ Extraction failed: {e}")
        if archive.exists():
            archive.unlink()
        return False


def ensure_ffmpeg():
    """Find or install ffmpeg. Returns the executable path or exits with instructions."""
    print("📦 Checking ffmpeg...")
    sys.stdout.flush()
    path = find_ffmpeg()
    if path:
        print(f"  ✅ ffmpeg found: {path}")
        sys.stdout.flush()
        return path

    print("  ⚠️  ffmpeg not found — attempting automatic install...")
    sys.stdout.flush()
    if download_ffmpeg_static():
        return str(FFMPEG_BIN)

    system = platform.system()
    if system == 'Darwin':
        hint = "  Install with: brew install ffmpeg"
    elif system == 'Windows':
        hint = "  Install with: winget install ffmpeg\n  Or download from: https://ffmpeg.org/download.html"
    else:
        hint = "  Install with: sudo apt-get install -y ffmpeg\n  Or contact your hosting provider."

    print(f"\n❌ Could not obtain ffmpeg automatically.")
    print(hint)
    sys.stdout.flush()
    sys.exit(1)


# ── Icon extraction helper ─────────────────────────────────────────────────────
def ensure_icons():
    """Ensure all default icons exist in media/icons, extracting from bP-icons.zip if needed."""
    icons_dir = ROOT_DIR / 'media' / 'icons'
    zip_path = icons_dir / 'bP-icons.zip'
    # List of required default icons (update as needed)
    required_icons = [
        'apple-touch-icon.png',
        'favicon-16x16.png',
        'favicon-32x32.png',
        'favicon-96x96.png',
        'favicon.ico',
        'web-app-manifest-192x192.png',
        'web-app-manifest-512x512.png',
    ]
    missing = [f for f in required_icons if not (icons_dir / f).exists()]
    if not missing:
        print('  ✅ All required icons present.')
        return
    if not zip_path.exists():
        print(f'  ❌ Missing icons and bP-icons.zip not found at {zip_path}')
        return
    print(f'  ⬇️  Extracting default icons from {zip_path}...')
    try:
        with zipfile.ZipFile(zip_path, 'r') as z:
            z.extractall(str(icons_dir))
        # Verify again
        still_missing = [f for f in required_icons if not (icons_dir / f).exists()]
        if still_missing:
            print(f'  ❌ Still missing icons after extraction: {still_missing}')
        else:
            print('  ✅ Default icons extracted.')
    except Exception as e:
        print(f'  ❌ Failed to extract icons: {e}')


# ── Sub-script runner ─────────────────────────────────────────────────────────

def run_script(script_path, env_extras=None):
    """Run a build sub-script, streaming its output line by line."""
    script_path = Path(script_path)
    env = os.environ.copy()
    env['BUILD_ROOT'] = str(ROOT_DIR)
    env['PYTHONIOENCODING'] = 'utf-8:replace'
    if env_extras:
        env.update(env_extras)

    print('\n' + '=' * 70)
    print('Running: ' + script_path.name)
    print('=' * 70 + '\n')
    sys.stdout.flush()

    try:
        proc = subprocess.Popen(
            [sys.executable, '-u', str(script_path)],
            cwd=str(ROOT_DIR),
            env=env,
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
        )
        for raw_line in iter(proc.stdout.readline, b''):
            line = raw_line.decode('utf-8', errors='replace').rstrip('\n')
            print(line)
            sys.stdout.flush()
        proc.stdout.close()
        proc.wait()
        if proc.returncode != 0:
            print('FAILED Script exited with code ' + str(proc.returncode))
            sys.stdout.flush()
            return False
        return True
    except FileNotFoundError:
        print('FAILED Script not found: ' + str(script_path))
        sys.stdout.flush()
        return False



def main():
    print("\n=== bandPromo Build Pipeline ===")
    print(f"Root: {ROOT_DIR}\n")
    sys.stdout.flush()

    # -- Preflight --------------------------------------------------------
    print("-- Preflight -------------------------------")
    if not ensure_runtime_files_seeded():
        sys.exit(1)

    if not install_pip_dependencies():
        sys.exit(1)

    ffmpeg_path = ensure_ffmpeg()

    # Ensure icons are present
    print("-- Checking icons in media/icons --")
    ensure_icons()

    audio_orig = ROOT_DIR / 'media' / 'audio' / 'original'
    supported_audio = []
    unsupported_audio = []
    if audio_orig.exists():
        for entry in audio_orig.iterdir():
            if not entry.is_file():
                continue
            suffix = entry.suffix.lower()
            if suffix in SUPPORTED_AUDIO_EXTENSIONS:
                supported_audio.append(entry.name)
            elif suffix in KNOWN_AUDIO_EXTENSIONS:
                unsupported_audio.append(entry.name)

    if not supported_audio:
        print(f"\n❌ No supported source audio found in {audio_orig}")
        if unsupported_audio:
            print("   Unsupported audio files present: " + ', '.join(sorted(unsupported_audio)))
            print("   Current supported source formats: FLAC, MP3, and WAV")
        print("   Upload your source files via Admin → Files first.")
        sys.stdout.flush()
        sys.exit(1)

    if unsupported_audio:
        print("⚠️  Unsupported source audio will be skipped: " + ', '.join(sorted(unsupported_audio)))

    print("\n✅ Preflight passed\n")
    sys.stdout.flush()

    # ── Step 1: Generate play/playlist.json ─────────────────────────────────────
    print("── Step 1/4: Generating play/playlist.json ───────────")
    sys.stdout.flush()
    if not run_script(SCRIPT_DIR / 'makePlaylists.py', {'FFMPEG_PATH': ffmpeg_path}):
        print("\n❌ Build failed at step 1")
        sys.stdout.flush()
        sys.exit(1)

    # ── Step 2: Optimize media (MP3 + optimised covers) ─────────────────────────────
    print("\n── Step 2/4: Optimizing media (audio + image + photo optimisation) ──")
    sys.stdout.flush()
    if not run_script(SCRIPT_DIR / 'optimizeMedia.py', {'FFMPEG_PATH': ffmpeg_path}):
        print("\n❌ Build failed at step 2")
        sys.stdout.flush()
        sys.exit(1)

    # ── Step 3: Social media assets ──────────────────────────────────────────────
    print("\n── Step 3/4: Generating social media assets ────────")
    sys.stdout.flush()
    if not run_script(SCRIPT_DIR / 'makeSocial.py'):
        print("\n❌ Build failed at step 3")
        sys.stdout.flush()
        sys.exit(1)

    # ── Step 4: Generate PWA manifest ─────────────────────────────────────────
    print("\n── Step 4/4: Generating PWA manifest ───────────────")
    sys.stdout.flush()
    if not run_script(SCRIPT_DIR / 'makePWA.py'):
        print("\n❌ Build failed at step 4")
        sys.stdout.flush()
        sys.exit(1)

    print("""
╔══════════════════════════════════════════════════╗
║               ✅ Build complete!                ║
╚══════════════════════════════════════════════════╝

Output:
  media/audio/optimal/  — MP3 files
  media/img/original/    — cover PNG files
  media/img/optimal/    — cover JPEG files (optimised)
  play/playlist.json — player playlist
  media/special/*_facebook.jpg, *_twitter.jpg – social share images
  site.webmanifest — PWA manifest
""")
    sys.stdout.flush()
    return 0


if __name__ == '__main__':
    sys.exit(main())

