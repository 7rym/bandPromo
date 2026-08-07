"""
Build pipeline for bandPromo
Runs the full pipeline: preflight checks → source config → optimize media → video delivery → social assets → manifest

Designed to be called from the admin panel (Admin > Config > Build),
but can also be run directly from the command line.

Output (new structure):
    media/audio/original/   - source audio files (uploaded by admin)
    media/audio/optimal/  - publish-ready audio delivery files (generated here)
    media/img/original/     - source cover/artwork files (uploaded by admin)
    media/img/optimal/   - publish-ready cover/artwork delivery files (generated here, max 720px)
    media/img/thumb/     - small list/cover-flow thumbs (generated here, max 100px)
  (removed) play/playlist.json  - legacy player artifact (replaced by playlist documents)
    media/special/*_facebook.jpg, *_twitter.jpg – social share delivery images
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
from datetime import datetime, timezone
from pathlib import Path

import zipfile

SCRIPT_DIR = Path(__file__).parent
ROOT_DIR   = SCRIPT_DIR.parent

# Site-local vendor path before any third-party imports in child stages.
sys.path.insert(0, str(SCRIPT_DIR))
try:
    import bandpromo_python_path
    bandpromo_python_path.ensure_vendor_on_sys_path()
except Exception:
    pass

# Debug: capture default encoding BEFORE any reconfiguration
_default_encoding = sys.stdout.encoding

# Force UTF-8 output — compatible with Python 3.6+
if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8', errors='replace')
else:
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

print("Python version: " + sys.version)
print("Default stdout encoding: " + str(_default_encoding))

REQUIREMENTS = SCRIPT_DIR / 'requirements.txt'
VENDOR_DIR = SCRIPT_DIR / 'vendor'
VENDOR_WHEELS_DIR = SCRIPT_DIR / 'vendor-wheels'
FFMPEG_BIN   = SCRIPT_DIR / 'bin' / ('ffmpeg.exe' if platform.system() == 'Windows' else 'ffmpeg')
SUPPORTED_AUDIO_EXTENSIONS = ('.flac', '.mp3', '.wav')
KNOWN_AUDIO_EXTENSIONS = SUPPORTED_AUDIO_EXTENSIONS + ('.wav', '.aif', '.aiff', '.m4a', '.aac', '.ogg', '.wma')

RUNTIME_TEMPLATE_MAP = (
    ('biblioteca/templates/web-config.template.json', 'web-config.json', 'json'),
    ('biblioteca/templates/runtime/root.htaccess', '.htaccess', 'text'),
    ('biblioteca/templates/runtime/user.ini', '.user.ini', 'text'),
    ('biblioteca/templates/runtime/play.htaccess', 'play/.htaccess', 'text'),
    ('biblioteca/templates/runtime/deny-all.htaccess', 'data/.htaccess', 'text'),
    ('biblioteca/templates/runtime/deny-all.htaccess', 'log/.htaccess', 'text'),
    ('biblioteca/templates/runtime/deny-all.htaccess', 'backups/.htaccess', 'text'),
    ('biblioteca/templates/runtime/media.htaccess', 'media/.htaccess', 'text'),
)

PAGE_TEMPLATE_MAP = (
    ('biblioteca/templates/pages.registry.template.json', 'data/pages/registry.json'),
    ('biblioteca/templates/bio.template.json', 'data/pages/bio.json'),
    ('biblioteca/templates/gallery.template.json', 'data/pages/gallery.json'),
    ('biblioteca/templates/faq.template.json', 'data/pages/faq.json'),
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


def seed_page_runtime_files():
    """Seed JSON page documents from tracked templates."""
    print('Checking required page runtime files...')
    sys.stdout.flush()

    errors = []

    for template_rel, json_rel in PAGE_TEMPLATE_MAP:
        template_path = ROOT_DIR / template_rel
        json_path = ROOT_DIR / json_rel

        if not template_path.exists():
            errors.append(f'Missing page template file: {template_path}')
            continue

        try:
            template_loaded = json.loads(template_path.read_text(encoding='utf-8'))
            if not isinstance(template_loaded, dict):
                errors.append(f'Invalid page template/root type: {template_path}')
                continue
        except Exception as e:
            errors.append(f'Invalid page template JSON: {template_path} ({e})')
            continue

        json_path.parent.mkdir(parents=True, exist_ok=True)

        if not json_path.exists():
            try:
                json_path.write_text(
                    json.dumps(template_loaded, indent=4, ensure_ascii=False) + '\n',
                    encoding='utf-8',
                )
                print(f'  Seeded missing page JSON: {json_path}')
            except Exception as e:
                errors.append(f'Could not write page JSON: {json_path} ({e})')
                continue

        try:
            loaded = json.loads(json_path.read_text(encoding='utf-8'))
            if not isinstance(loaded, dict):
                errors.append(f'Invalid runtime page JSON/root type: {json_path}')
        except Exception as e:
            errors.append(f'Invalid runtime page JSON file: {json_path} ({e})')

    if errors:
        print('  ❌ Page runtime preflight failed:')
        for err in errors:
            print('    - ' + err)
        sys.stdout.flush()
        return False

    print('  ✅ Required page runtime files present')
    sys.stdout.flush()
    return True

def _verify_required_python_imports():
    """Return (ok, missing_names) for PIL/mutagen/xxhash after vendor bootstrap."""
    try:
        import bandpromo_python_path as bpp
        bpp.ensure_vendor_on_sys_path()
        names = bpp.required_import_names()
    except Exception:
        names = ('PIL', 'mutagen', 'xxhash')
        vendor = str(VENDOR_DIR)
        if vendor not in sys.path:
            sys.path.insert(0, vendor)

    missing = []
    for name in names:
        try:
            __import__(name)
        except Exception:
            missing.append(name)
    return (missing == [], missing)


def _pip_install_to_vendor(extra_args):
    """Run pip install --target scripts/vendor for this interpreter."""
    VENDOR_DIR.mkdir(parents=True, exist_ok=True)
    cmd = [
        sys.executable, '-m', 'pip', 'install',
        '-r', str(REQUIREMENTS),
        '--target', str(VENDOR_DIR),
        '--upgrade',
        '--only-binary=:all:',
    ] + list(extra_args)
    return subprocess.run(
        cmd,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        universal_newlines=True,
    )


def _pip_failure_note(result):
    """Return a short one-line note from pip stdout/stderr."""
    err = (result.stderr or result.stdout or '').strip()
    if not err:
        return ''
    line = err.splitlines()[-1].strip()
    if len(line) > 180:
        line = line[:177] + '...'
    return line


def _wheel_matches_interpreter(wheel_name, py_tag):
    """True when a vendor-wheels filename is usable for this interpreter tag."""
    name = wheel_name.lower()
    if not name.endswith('.whl'):
        return False
    if 'py3-none-any' in name or 'py2.py3-none-any' in name:
        return True
    # CPython 3.6 wheels use the historical cp36m ABI tag.
    if py_tag == 'cp36':
        return '-cp36-' in name or '-cp36m-' in name
    token = '-{0}-'.format(py_tag)
    return token in name


def _extract_offline_wheels(py_tag):
    """Unpack matching .whl ZIP contents into scripts/vendor/ (bypasses old pip tags)."""
    if not VENDOR_WHEELS_DIR.is_dir():
        return []

    VENDOR_DIR.mkdir(parents=True, exist_ok=True)
    matched = []
    for path in sorted(VENDOR_WHEELS_DIR.glob('*.whl')):
        if not _wheel_matches_interpreter(path.name, py_tag):
            continue
        matched.append(path)

    extracted = []
    for path in matched:
        with zipfile.ZipFile(str(path), 'r') as archive:
            archive.extractall(str(VENDOR_DIR))
        extracted.append(path.name)
    return extracted


def install_pip_dependencies():
    """Install build deps into scripts/vendor for this host Python (no operator pip)."""
    py_tag = 'cp{0}{1}'.format(sys.version_info[0], sys.version_info[1])
    print('Checking Python dependencies...')
    print('  Interpreter: {0} ({1})'.format(sys.executable, py_tag))
    sys.stdout.flush()

    if not REQUIREMENTS.exists():
        print('  WARNING requirements.txt not found, skipping')
        sys.stdout.flush()
        return True

    try:
        import bandpromo_python_path as bpp
        bpp.ensure_vendor_on_sys_path()
    except Exception:
        vendor = str(VENDOR_DIR)
        if vendor not in sys.path:
            sys.path.insert(0, vendor)

    ok, missing = _verify_required_python_imports()
    if ok:
        print('  OK Dependencies already available for ' + py_tag)
        sys.stdout.flush()
        return True

    # 1) Network install into site-local vendor (writable under the install).
    try:
        result = _pip_install_to_vendor([])
        if result.returncode == 0:
            ok, missing = _verify_required_python_imports()
            if ok:
                print('  OK Dependencies installed into scripts/vendor for ' + py_tag)
                sys.stdout.flush()
                return True
        else:
            print('  NOTE pip --target install did not succeed for ' + py_tag)
            note = _pip_failure_note(result)
            if note:
                print('  NOTE ' + note)
    except Exception as e:
        print('  NOTE Could not run pip --target: ' + str(e))

    # 2) Offline wheels via pip --find-links (needs a pip that accepts the tags).
    if VENDOR_WHEELS_DIR.is_dir():
        try:
            result = _pip_install_to_vendor([
                '--no-index',
                '--find-links', str(VENDOR_WHEELS_DIR),
            ])
            if result.returncode == 0:
                ok, missing = _verify_required_python_imports()
                if ok:
                    print('  OK Dependencies installed from scripts/vendor-wheels for ' + py_tag)
                    sys.stdout.flush()
                    return True
            else:
                print('  NOTE pip offline wheel install did not succeed for ' + py_tag)
                note = _pip_failure_note(result)
                if note:
                    print('  NOTE ' + note)
        except Exception as e:
            print('  NOTE Offline wheel install failed: ' + str(e))

    # 3) Direct wheel extract — works when host pip rejects manylinux2014 tags.
    try:
        extracted = _extract_offline_wheels(py_tag)
        if extracted:
            print('  Extracted {0} offline wheel(s) into scripts/vendor for {1}'.format(
                len(extracted), py_tag
            ))
            ok, missing = _verify_required_python_imports()
            if ok:
                print('  OK Dependencies available from extracted vendor-wheels for ' + py_tag)
                sys.stdout.flush()
                return True
    except Exception as e:
        print('  NOTE Direct wheel extract failed: ' + str(e))

    ok, missing = _verify_required_python_imports()
    if ok:
        print('  OK Dependencies available after bootstrap')
        sys.stdout.flush()
        return True

    print('  WARNING Missing Python packages for {0}: {1}'.format(
        py_tag, ', '.join(missing) if missing else 'unknown'
    ))
    print('  Build continues; stages that need those packages will report clearly.')
    sys.stdout.flush()
    return True



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

def print_stage_banner(script_name, stage_index=None, stage_total=None, stage_label=''):
    """Print a single framed banner for a publish stage / sub-script."""
    width = 70
    rule = '=' * width
    lines = []
    if stage_index is not None and stage_total is not None:
        label = str(stage_label or '').strip() or 'stage'
        lines.append('Stage {}/{} — {}'.format(stage_index, stage_total, label))
    lines.append('Script: {}'.format(script_name))

    print()
    print(rule)
    for line in lines:
        print('  ' + line)
    print(rule)
    print()
    sys.stdout.flush()


def run_script(script_path, env_extras=None, stage_index=None, stage_total=None, stage_label=''):
    """Run a build sub-script, streaming its output line by line."""
    script_path = Path(script_path)
    env = os.environ.copy()
    env['BUILD_ROOT'] = str(ROOT_DIR)
    env['PYTHONIOENCODING'] = 'utf-8:replace'
    vendor_str = str(VENDOR_DIR)
    existing_pythonpath = str(env.get('PYTHONPATH') or '').strip()
    if existing_pythonpath:
        env['PYTHONPATH'] = vendor_str + os.pathsep + existing_pythonpath
    else:
        env['PYTHONPATH'] = vendor_str
    if env_extras:
        env.update(env_extras)

    print_stage_banner(
        script_path.name,
        stage_index=stage_index,
        stage_total=stage_total,
        stage_label=stage_label,
    )

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


def load_build_meta():
    meta_path = ROOT_DIR / 'log' / 'build.meta.json'
    if not meta_path.exists():
        return {}
    try:
        loaded = json.loads(meta_path.read_text(encoding='utf-8'))
        return loaded if isinstance(loaded, dict) else {}
    except Exception as exc:
        print('Warning: Could not read build.meta.json: ' + str(exc))
        return {}


def load_stage_manifest():
    manifest_path = SCRIPT_DIR / 'build-stages.json'
    if not manifest_path.exists():
        print('FAILED Build stage manifest not found: ' + str(manifest_path))
        sys.stdout.flush()
        return None
    try:
        loaded = json.loads(manifest_path.read_text(encoding='utf-8'))
        if not isinstance(loaded, dict):
            raise ValueError('manifest root must be an object')
        return loaded
    except Exception as exc:
        print('FAILED Could not load build stage manifest: ' + str(exc))
        sys.stdout.flush()
        return None


def resolve_stage_ids(manifest, meta):
    requested = meta.get('stages') if isinstance(meta, dict) else None
    if isinstance(requested, list) and requested:
        allowed = {
            str(stage.get('id', '')).strip()
            for stage in manifest.get('stages', [])
            if isinstance(stage, dict) and str(stage.get('id', '')).strip()
        }
        resolved = []
        for stage_id in requested:
            stage_id = str(stage_id).strip()
            if stage_id and stage_id in allowed and stage_id not in resolved:
                resolved.append(stage_id)
        if resolved:
            return resolved

    profile = 'full'
    if isinstance(meta, dict):
        profile = str(meta.get('profile') or 'full').strip() or 'full'
    profiles = manifest.get('profiles', {})
    if not isinstance(profiles, dict):
        profiles = {}
    profile_stages = profiles.get(profile)
    if not isinstance(profile_stages, list) or not profile_stages:
        profile_stages = profiles.get('full', [])
    if not isinstance(profile_stages, list):
        return []

    allowed = {
        str(stage.get('id', '')).strip()
        for stage in manifest.get('stages', [])
        if isinstance(stage, dict) and str(stage.get('id', '')).strip()
    }
    resolved = []
    for stage_id in profile_stages:
        stage_id = str(stage_id).strip()
        if stage_id and stage_id in allowed and stage_id not in resolved:
            resolved.append(stage_id)
    return resolved


def stage_lookup(manifest):
    lookup = {}
    for stage in manifest.get('stages', []):
        if not isinstance(stage, dict):
            continue
        stage_id = str(stage.get('id', '')).strip()
        if stage_id:
            lookup[stage_id] = stage
    return lookup


def log_stage_boundary(stage_id, exit_code=None):
    if exit_code is None:
        print('STAGE_START:' + stage_id)
    else:
        print('STAGE_END:' + stage_id + ':' + str(exit_code))
    sys.stdout.flush()


def run_publish_stage(stage, ffmpeg_path, index, total):
    stage_id = str(stage.get('id', '')).strip()
    label = str(stage.get('label') or stage_id or 'stage').strip()
    script_name = str(stage.get('script', '')).strip()
    if not stage_id or not script_name:
        print('FAILED Invalid stage definition: ' + repr(stage_id))
        sys.stdout.flush()
        log_stage_boundary(stage_id or 'unknown', 1)
        return False

    log_stage_boundary(stage_id)
    group = str(stage.get('group') or '').strip()
    if group:
        print('STAGE_GROUP:' + group)
    sys.stdout.flush()

    env_extras = {}
    stage_env = stage.get('env')
    if isinstance(stage_env, dict):
        env_extras.update({str(k): str(v) for k, v in stage_env.items()})
    if stage.get('requires_ffmpeg'):
        env_extras['FFMPEG_PATH'] = ffmpeg_path

    ok = run_script(
        SCRIPT_DIR / script_name,
        env_extras,
        stage_index=index,
        stage_total=total,
        stage_label=label,
    )
    log_stage_boundary(stage_id, 0 if ok else 1)
    if not ok:
        print('\n❌ Build failed at stage: ' + stage_id)
        sys.stdout.flush()
    return ok


def run_preflight():
    print("-- Preflight -------------------------------")
    if not ensure_runtime_files_seeded():
        return None

    if not seed_page_runtime_files():
        return None

    if not install_pip_dependencies():
        return None

    ffmpeg_path = ensure_ffmpeg()

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
        return None

    if unsupported_audio:
        print("⚠️  Unsupported source audio will be skipped: " + ', '.join(sorted(unsupported_audio)))

    print("\n✅ Preflight passed\n")
    sys.stdout.flush()
    return ffmpeg_path


def main():
    started_at = datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ')
    print('LOG_STARTED:' + started_at)
    print('[' + started_at.replace('T', ' ').replace('Z', ' UTC') + '] Python publish pipeline starting')
    print("\n=== bandPromo Build Pipeline ===")
    print(f"Root: {ROOT_DIR}\n")
    sys.stdout.flush()

    log_stage_boundary('preflight')
    ffmpeg_path = run_preflight()
    if not ffmpeg_path:
        log_stage_boundary('preflight', 1)
        return 1
    log_stage_boundary('preflight', 0)

    manifest = load_stage_manifest()
    if manifest is None:
        return 1

    meta = load_build_meta()
    stage_ids = resolve_stage_ids(manifest, meta)
    if not stage_ids:
        print('FAILED No publish stages resolved for this build run.')
        sys.stdout.flush()
        return 1

    profile = 'full'
    if isinstance(meta, dict):
        profile = str(meta.get('profile') or 'full').strip() or 'full'

    print('PROFILE:' + profile)
    print('STAGES:' + ','.join(stage_ids))
    sys.stdout.flush()

    stages_by_id = stage_lookup(manifest)
    total = len(stage_ids)
    for index, stage_id in enumerate(stage_ids, start=1):
        stage = stages_by_id.get(stage_id)
        if not isinstance(stage, dict):
            print('FAILED Unknown stage id: ' + stage_id)
            sys.stdout.flush()
            log_stage_boundary(stage_id, 1)
            return 1
        if not run_publish_stage(stage, ffmpeg_path, index, total):
            return 1

    print("""
╔══════════════════════════════════════════════════╗
║               ✅ Build complete!                ║
╚══════════════════════════════════════════════════╝

Output:
    media/audio/optimal/  — publish-ready audio delivery files
    media/img/original/    — source cover/artwork files
    media/img/optimal/    — publish-ready cover/artwork delivery files (max 720px)
    media/img/thumb/      — playlist/cover-flow thumbs (max 100px)
    media/video/optimal/  — publish-ready video delivery files
  (removed) play/playlist.json — legacy player playlist artifact
    media/special/*_facebook.jpg, *_twitter.jpg – social share delivery images
  site.webmanifest — PWA manifest

Note: Initial layout seed (playlist order, gallery list, player tabs) runs during
setup via biblioteca/run-layout-seed.php — not during routine publish.
""")
    sys.stdout.flush()
    return 0


if __name__ == '__main__':
    sys.exit(main())

