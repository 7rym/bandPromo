"""
optimizeVideo — Publish-ready Video Delivery Generator
Builds delivery-safe MP4 files from uploaded source videos.

Input:
- media/video/original/*.{mp4,mov,webm}
- Registered visual video assets in data/assets/registry.json

Output:
- media/video/optimal/*.mp4 (legacy dual-read)
- media/video/poster/*.jpg (legacy dual-read)
- media/visual/delivery/{asset_id}/standard-stream.mp4
- media/visual/delivery/{asset_id}/poster.jpg

Audio policy:
- role=gallery → keep soundtrack in delivery
- all other video roles (living covers, shell backgrounds, unassigned, …) → silent delivery (video track only)
"""

import io
import json
import os
import shutil
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

SCRIPT_DIR = Path(__file__).parent
sys.path.insert(0, str(SCRIPT_DIR))
try:
    import bandpromo_python_path
    bandpromo_python_path.ensure_vendor_on_sys_path()
except Exception:
    pass

if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8', errors='replace', line_buffering=True)
else:
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace', line_buffering=True)

try:
    import xxhash
except ImportError:
    xxhash = None

ROOT_DIR = SCRIPT_DIR.parent
VIDEO_ORIG_DIR = ROOT_DIR / 'media' / 'video' / 'original'
VIDEO_OPT_DIR = ROOT_DIR / 'media' / 'video' / 'optimal'
VIDEO_POSTER_DIR = ROOT_DIR / 'media' / 'video' / 'poster'
VISUAL_DELIVERY_ROOT = ROOT_DIR / 'media' / 'visual' / 'delivery'
VISUAL_ORIG_DIR = ROOT_DIR / 'media' / 'visual' / 'original'
VISUAL_MASTER_DIR = ROOT_DIR / 'media' / 'visual' / 'master'
ASSET_REGISTRY_FILE = ROOT_DIR / 'data' / 'assets' / 'registry.json'
SUPPORTED_VIDEO_EXTENSIONS = ('.mp4', '.mov', '.webm', '.mkv')
TRANSCODE_EXTENSIONS = ('.mov', '.webm')
_XXHASH_WARNED = False


def warn_xxhash_once():
    global _XXHASH_WARNED
    if xxhash is not None or _XXHASH_WARNED:
        return
    _XXHASH_WARNED = True
    print('  ⚠️  xxhash unavailable — skip-if-fresh disabled until scripts/vendor bootstrap succeeds')


def get_ffmpeg_path():
    """Resolve ffmpeg: FFMPEG_PATH env, bundled scripts/bin, then PATH."""
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
    return 'ffmpeg'


def check_ffmpeg():
    ffmpeg = get_ffmpeg_path()
    try:
        subprocess.run([ffmpeg, '-version'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, check=True)
        return True
    except (FileNotFoundError, subprocess.CalledProcessError):
        return False
    except OSError:
        return False


def delivery_path_for(source_path: Path) -> Path:
    return VIDEO_OPT_DIR / (source_path.stem + '.mp4')


def poster_path_for(source_path: Path) -> Path:
    return VIDEO_POSTER_DIR / (source_path.stem + '.jpg')


def remux_mp4_keep_audio(source_path: Path, target_path: Path) -> bool:
    """Stream-copy into MP4 (e.g. MKV master → delivery). Falls back to re-encode."""
    ffmpeg = get_ffmpeg_path()
    target_path.parent.mkdir(parents=True, exist_ok=True)
    command = [
        ffmpeg,
        '-y',
        '-i',
        str(source_path),
        '-map',
        '0:v:0',
        '-map',
        '0:a?',
        '-c',
        'copy',
        '-movflags',
        '+faststart',
        str(target_path),
    ]
    try:
        result = _run_ffmpeg_capture(command)
    except Exception as exc:
        print(f"  ❌ Could not start ffmpeg for MP4 remux: {exc}", file=sys.stderr)
        return False

    if result.returncode != 0:
        print("  ❌ ffmpeg MP4 remux failed — falling back to re-encode", file=sys.stderr)
        return transcode_to_mp4(source_path, target_path, keep_audio=True)

    return True


def delivery_mode_for(source_path: Path, keep_audio: bool = True) -> str:
    """copy = byte-identical MP4 pass-through; remux/transcode otherwise."""
    suffix = source_path.suffix.lower()
    if suffix == '.mp4' and keep_audio:
        return 'copy'
    if suffix == '.mp4' and not keep_audio:
        return 'remux-silent'
    if suffix == '.mkv' and keep_audio:
        return 'remux-mp4'
    if suffix == '.mkv' and not keep_audio:
        return 'remux-silent'
    return 'transcode'


def video_keeps_audio(asset) -> bool:
    """Gallery videos may keep soundtrack; living covers / shell / other roles are silent."""
    if not isinstance(asset, dict):
        return True
    role = str(asset.get('role') or '').strip().lower()
    return role == 'gallery'


def audio_mode_label(keep_audio: bool) -> str:
    return 'keep' if keep_audio else 'silent'


def recorded_audio_mode(asset) -> str:
    if not isinstance(asset, dict):
        return ''
    delivery = asset.get('delivery') if isinstance(asset.get('delivery'), dict) else {}
    return str(delivery.get('audio_mode') or '').strip().lower()


def needs_refresh(source_path: Path, target_path: Path) -> bool:
    if not target_path.exists():
        return True
    return source_path.stat().st_mtime > target_path.stat().st_mtime


def stream_needs_refresh(source_path: Path, target_path: Path, asset, keep_audio: bool) -> bool:
    desired = audio_mode_label(keep_audio)
    if recorded_audio_mode(asset) != desired:
        return True
    return needs_refresh(source_path, target_path)


def ensure_directories():
    VIDEO_OPT_DIR.mkdir(parents=True, exist_ok=True)
    VIDEO_POSTER_DIR.mkdir(parents=True, exist_ok=True)
    VISUAL_DELIVERY_ROOT.mkdir(parents=True, exist_ok=True)


def copy_mp4(source_path: Path, target_path: Path) -> bool:
    try:
        target_path.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(str(source_path), str(target_path))
        return True
    except Exception as exc:
        print(f"  ❌ Could not copy MP4 source: {exc}", file=sys.stderr)
        return False


def _run_ffmpeg_capture(command):
    return subprocess.run(
        command,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        universal_newlines=True,
    )


def remux_mp4_silent(source_path: Path, target_path: Path) -> bool:
    """Fast path: copy video stream, drop all audio (living covers / shell backgrounds)."""
    ffmpeg = get_ffmpeg_path()
    target_path.parent.mkdir(parents=True, exist_ok=True)
    command = [
        ffmpeg,
        '-y',
        '-i',
        str(source_path),
        '-map',
        '0:v:0',
        '-c:v',
        'copy',
        '-an',
        '-movflags',
        '+faststart',
        str(target_path),
    ]
    try:
        result = _run_ffmpeg_capture(command)
    except Exception as exc:
        print(f"  ❌ Could not start ffmpeg for silent remux: {exc}", file=sys.stderr)
        return False

    if result.returncode != 0:
        print("  ❌ ffmpeg silent remux failed — falling back to re-encode", file=sys.stderr)
        return transcode_to_mp4(source_path, target_path, keep_audio=False)

    return True


def transcode_to_mp4(source_path: Path, target_path: Path, keep_audio: bool = True) -> bool:
    ffmpeg = get_ffmpeg_path()
    target_path.parent.mkdir(parents=True, exist_ok=True)
    command = [
        ffmpeg,
        '-y',
        '-i',
        str(source_path),
        '-map',
        '0:v:0',
    ]
    if keep_audio:
        command.extend(['-map', '0:a?'])
    command.extend([
        '-vf',
        'scale=trunc(iw/2)*2:trunc(ih/2)*2',
        '-c:v',
        'libx264',
        '-pix_fmt',
        'yuv420p',
        '-movflags',
        '+faststart',
    ])
    if keep_audio:
        command.extend(['-c:a', 'aac', '-b:a', '192k'])
    else:
        command.append('-an')
    command.append(str(target_path))
    try:
        result = _run_ffmpeg_capture(command)
    except Exception as exc:
        print(f"  ❌ Could not start ffmpeg for video transcode: {exc}", file=sys.stderr)
        return False

    if result.returncode != 0:
        print("  ❌ ffmpeg video transcode failed", file=sys.stderr)
        tail = '\n'.join((result.stdout or '').splitlines()[-12:])
        if tail:
            print(tail, file=sys.stderr)
        if target_path.exists():
            try:
                target_path.unlink()
            except OSError:
                pass
        return False

    return True


def write_delivery_mp4(source_path: Path, target_path: Path, keep_audio: bool) -> bool:
    mode = delivery_mode_for(source_path, keep_audio=keep_audio)
    if mode == 'copy':
        return copy_mp4(source_path, target_path)
    if mode == 'remux-silent':
        return remux_mp4_silent(source_path, target_path)
    if mode == 'remux-mp4':
        return remux_mp4_keep_audio(source_path, target_path)
    return transcode_to_mp4(source_path, target_path, keep_audio=keep_audio)


def ensure_video_poster(source_path: Path, poster_path: Path) -> bool:
    if not needs_refresh(source_path, poster_path):
        return True

    poster_path.parent.mkdir(parents=True, exist_ok=True)
    ffmpeg = get_ffmpeg_path()
    command = [
        ffmpeg,
        '-y',
        '-i',
        str(source_path),
        '-frames:v',
        '1',
        '-q:v',
        '2',
        str(poster_path),
    ]
    try:
        result = _run_ffmpeg_capture(command)
    except Exception as exc:
        print(f"  ⚠️  Could not start ffmpeg for poster extraction: {exc}", file=sys.stderr)
        return False

    if result.returncode != 0:
        print(f"  ⚠️  Could not refresh poster for {source_path.name}", file=sys.stderr)
        tail = '\n'.join((result.stdout or '').splitlines()[-8:])
        if tail:
            print(tail, file=sys.stderr)
        if poster_path.exists():
            try:
                poster_path.unlink()
            except OSError:
                pass
        return False

    return True


def load_asset_registry():
    if not ASSET_REGISTRY_FILE.exists():
        return {}
    try:
        payload = json.loads(ASSET_REGISTRY_FILE.read_text(encoding='utf-8'))
    except Exception:
        return {}
    return payload if isinstance(payload, dict) else {}


def basename_lower(value) -> str:
    name = basename_safe(value)
    return name.lower() if name else ''


def basename_safe(value) -> str:
    text = str(value or '').strip().replace('\\', '/')
    if text == '':
        return ''
    return Path(text).name


def load_registry_visual_video_queue():
    payload = load_asset_registry()
    assets = payload.get('assets') if isinstance(payload.get('assets'), dict) else {}
    queue = []
    for asset_id, asset in assets.items():
        if not isinstance(asset, dict):
            continue
        if str(asset.get('kind') or '').strip().lower() != 'visual':
            continue
        if str(asset.get('media_type') or '').strip().lower() != 'video':
            continue
        item = dict(asset)
        item['id'] = str(asset.get('id') or asset_id)
        queue.append(item)
    queue.sort(key=lambda item: str(item.get('original_filename') or '').lower())
    return queue


def visual_video_source_path(asset):
    """Master-first source path for video delivery (legacy intake fallback)."""
    asset_id = str(asset.get('id') or '').strip()
    filename = os.path.basename(str(asset.get('original_filename') or '').strip())
    master_name = os.path.basename(str(asset.get('master_filename') or '').strip())
    fmt = str(asset.get('master_format') or '').strip().lower()
    if not fmt and filename:
        fmt = Path(filename).suffix.lstrip('.').lower()

    candidates = []
    if asset_id.startswith('ast_') and fmt:
        candidates.append(VISUAL_MASTER_DIR / '{}.{}'.format(asset_id, fmt))
    if master_name.startswith('ast_'):
        candidates.append(VISUAL_MASTER_DIR / master_name)
    if filename:
        candidates.append(VISUAL_ORIG_DIR / filename)

    bucket = str(asset.get('intake_bucket') or '').strip().lower()
    if filename:
        if bucket == 'special':
            candidates.append(ROOT_DIR / 'media' / 'special' / filename)
        candidates.append(VIDEO_ORIG_DIR / filename)

    for path in candidates:
        if path.is_file():
            return path
    return None


def variant_manifest_entry(abs_path: Path, format_hint: str = ''):
    rel = str(abs_path).replace(str(ROOT_DIR), '').replace('\\', '/')
    if not rel.startswith('/'):
        rel = '/' + rel
    rel = rel.lstrip('/')
    try:
        size = abs_path.stat().st_size
    except Exception:
        size = 0
    return {
        'path': rel,
        'width': 0,
        'height': 0,
        'format': format_hint or abs_path.suffix.lower().lstrip('.'),
        'bytes': int(size),
        'updated_at': datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ'),
    }


def file_xxh3_hex(path):
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


def update_visual_asset_delivery(asset_id, variants_map, source_xxh3=None, audio_mode=None):
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
    if audio_mode:
        delivery['audio_mode'] = str(audio_mode).strip().lower()
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
        print(f"  ⚠️  Could not update registry for {asset_id}: {exc}")
        return False


def process_one_video(source_path: Path, asset_id: str = '', asset=None):
    keep_audio = video_keeps_audio(asset)
    mode = delivery_mode_for(source_path, keep_audio=keep_audio)
    audio_label = audio_mode_label(keep_audio)
    legacy_target = delivery_path_for(source_path)
    legacy_poster = poster_path_for(source_path)

    print(f"\n📼 Processing: {source_path.name}")
    if mode == 'copy':
        route = 'MP4 copy (keep audio)'
    elif mode == 'remux-silent':
        route = 'MP4 remux silent (drop audio)'
    elif mode == 'remux-mp4':
        route = 'MP4 remux (keep audio, stream copy)'
    else:
        route = 'Transcode to MP4 ({})'.format('keep audio' if keep_audio else 'silent')
    print(f"  → Delivery route: {route}")
    if isinstance(asset, dict):
        role = str(asset.get('role') or 'unassigned').strip() or 'unassigned'
        print(f"  → Role: {role} → audio={audio_label}")

    built = False
    skipped = False
    failed = False
    # M4: registered assets write only media/visual/delivery/{ast_*}/ (no stem dual-write).
    write_legacy = asset_id == ''

    if write_legacy:
        if needs_refresh(source_path, legacy_target):
            ok = write_delivery_mp4(source_path, legacy_target, keep_audio=True)
            if ok:
                built = True
                print(f"  ✓ Wrote legacy delivery file: {legacy_target.name}")
            else:
                failed = True
                return {'built': False, 'skipped': False, 'failed': True, 'poster': False}
        else:
            skipped = True
            print(f"  ✓ Legacy delivery file is up to date: {legacy_target.name}")

        poster_ok = ensure_video_poster(source_path, legacy_poster)
        if poster_ok and legacy_poster.exists():
            print(f"  ✓ Legacy poster is up to date: {legacy_poster.name}")
    else:
        poster_ok = False
        print("  → Skipping legacy video/optimal dual-write (registered asset)")

    variants = {}
    if asset_id:
        delivery_dir = VISUAL_DELIVERY_ROOT / asset_id
        stream_path = delivery_dir / 'standard-stream.mp4'
        poster_path = delivery_dir / 'poster.jpg'

        if stream_needs_refresh(source_path, stream_path, asset, keep_audio):
            ok = write_delivery_mp4(source_path, stream_path, keep_audio=keep_audio)
            if not ok and write_legacy and legacy_target.exists():
                ok = write_delivery_mp4(legacy_target, stream_path, keep_audio=keep_audio)
            if ok:
                built = True
                print(f"  ✓ Wrote asset stream: {stream_path} ({audio_label})")
            else:
                failed = True
        elif stream_path.exists():
            skipped = True
            print(f"  ✓ Asset stream up to date: {stream_path.name} ({audio_label})")

        if stream_path.exists():
            variants['standard-stream'] = variant_manifest_entry(stream_path, 'mp4')

        if ensure_video_poster(source_path, poster_path) and poster_path.exists():
            variants['poster'] = variant_manifest_entry(poster_path, 'jpg')
            print(f"  ✓ Wrote asset poster: {poster_path.name}")
        elif write_legacy and legacy_poster.exists():
            try:
                poster_path.parent.mkdir(parents=True, exist_ok=True)
                shutil.copy2(str(legacy_poster), str(poster_path))
                variants['poster'] = variant_manifest_entry(poster_path, 'jpg')
            except Exception:
                pass

        if variants:
            update_visual_asset_delivery(
                asset_id,
                variants,
                source_xxh3=file_xxh3_hex(source_path) or None,
                audio_mode=audio_label,
            )

    return {
        'built': built,
        'skipped': skipped,
        'failed': failed,
        'poster': bool(poster_ok and write_legacy and legacy_poster.exists()) or bool(variants.get('poster')),
    }


def main():
    print("\n🎬 Video delivery build")
    print(f"Root: {ROOT_DIR}")
    sys.stdout.flush()

    ensure_directories()

    if not check_ffmpeg():
        ffmpeg_name = get_ffmpeg_path()
        print(f"❌ ffmpeg not found ({ffmpeg_name})")
        print("   Video delivery generation requires ffmpeg during the full build.")
        return 1

    built = 0
    skipped = 0
    failed = 0
    posters_ready = 0
    unregistered_skipped = 0
    processed_names = set()

    if xxhash is None:
        warn_xxhash_once()

    visual_queue = load_registry_visual_video_queue()
    if visual_queue:
        print(f"\n🎨 Processing {len(visual_queue)} registered visual video asset(s)...")
        for asset in visual_queue:
            source = visual_video_source_path(asset)
            if source is None:
                print(f"  ⚠️  Missing source for {asset.get('id')}: {asset.get('original_filename')}")
                failed += 1
                continue
            result = process_one_video(
                source,
                asset_id=str(asset.get('id') or ''),
                asset=asset,
            )
            processed_names.add(source.name.lower())
            original_name = basename_lower(asset.get('original_filename'))
            if original_name:
                processed_names.add(original_name)
            master_name = basename_lower(asset.get('master_filename'))
            if master_name:
                processed_names.add(master_name)
            if result['failed']:
                failed += 1
            elif result['built']:
                built += 1
            else:
                skipped += 1
            if result['poster']:
                posters_ready += 1

    if not VIDEO_ORIG_DIR.exists():
        if not visual_queue:
            print("ℹ️  No source video directory found. Skipping video delivery build.")
        print("\n" + "=" * 70)
        print(f"Built/updated video delivery files: {built}")
        print(f"Already up to date: {skipped}")
        print(f"Poster files ready: {posters_ready}")
        print(f"Failures: {failed}")
        return 1 if failed else 0

    source_files = [
        path for path in sorted(VIDEO_ORIG_DIR.iterdir())
        if path.is_file() and path.suffix.lower() in SUPPORTED_VIDEO_EXTENSIONS
        and path.name.lower() not in processed_names
    ]

    if source_files:
        print(f"\n📁 {len(source_files)} unregistered video source(s) — skipped (register-or-fail)")
        for source_path in source_files:
            print(f"  ⚠️  Unregistered video {source_path.name} — register the visual asset before Publish")
        unregistered_skipped = len(source_files)

    if not visual_queue and not source_files:
        print("ℹ️  No source videos found. Skipping video delivery build.")
        return 0

    print("\n" + "=" * 70)
    print(f"Built/updated video delivery files: {built}")
    print(f"Already up to date: {skipped}")
    if unregistered_skipped:
        print(f"Unregistered intake skipped: {unregistered_skipped}")
    print(f"Poster files ready: {posters_ready}")
    print(f"Failures: {failed}")
    print(f"Visual delivery root: {VISUAL_DELIVERY_ROOT}")

    return 1 if failed else 0


if __name__ == '__main__':
    sys.exit(main())
