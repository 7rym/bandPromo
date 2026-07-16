"""
optimizeVideo — Publish-ready Video Delivery Generator
Builds delivery-safe MP4 files from uploaded source videos.

Input:
- media/video/original/*.{mp4,mov,webm}

Output:
- media/video/optimal/*.mp4
- media/video/poster/*.jpg (backfilled if missing)
"""

import io
import os
import shutil
import subprocess
import sys
from pathlib import Path

if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8', errors='replace', line_buffering=True)
else:
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace', line_buffering=True)

SCRIPT_DIR = Path(__file__).parent
ROOT_DIR = SCRIPT_DIR.parent
VIDEO_ORIG_DIR = ROOT_DIR / 'media' / 'video' / 'original'
VIDEO_OPT_DIR = ROOT_DIR / 'media' / 'video' / 'optimal'
VIDEO_POSTER_DIR = ROOT_DIR / 'media' / 'video' / 'poster'
SUPPORTED_VIDEO_EXTENSIONS = ('.mp4', '.mov', '.webm')
TRANSCODE_EXTENSIONS = ('.mov', '.webm')


def get_ffmpeg_path():
    return os.environ.get('FFMPEG_PATH', 'ffmpeg')


def check_ffmpeg():
    ffmpeg = get_ffmpeg_path()
    try:
        subprocess.run([ffmpeg, '-version'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, check=True)
        return True
    except (FileNotFoundError, subprocess.CalledProcessError):
        return False


def delivery_path_for(source_path: Path) -> Path:
    return VIDEO_OPT_DIR / (source_path.stem + '.mp4')


def poster_path_for(source_path: Path) -> Path:
    return VIDEO_POSTER_DIR / (source_path.stem + '.jpg')


def delivery_mode_for(source_path: Path) -> str:
    return 'copy' if source_path.suffix.lower() == '.mp4' else 'transcode'


def needs_refresh(source_path: Path, target_path: Path) -> bool:
    if not target_path.exists():
        return True
    return source_path.stat().st_mtime > target_path.stat().st_mtime


def ensure_directories():
    VIDEO_OPT_DIR.mkdir(parents=True, exist_ok=True)
    VIDEO_POSTER_DIR.mkdir(parents=True, exist_ok=True)


def copy_mp4(source_path: Path, target_path: Path) -> bool:
    try:
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


def transcode_to_mp4(source_path: Path, target_path: Path) -> bool:
    ffmpeg = get_ffmpeg_path()
    command = [
        ffmpeg,
        '-y',
        '-i',
        str(source_path),
        '-map',
        '0:v:0',
        '-map',
        '0:a?',
        '-vf',
        'scale=trunc(iw/2)*2:trunc(ih/2)*2',
        '-c:v',
        'libx264',
        '-pix_fmt',
        'yuv420p',
        '-movflags',
        '+faststart',
        '-c:a',
        'aac',
        '-b:a',
        '192k',
        str(target_path),
    ]
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


def ensure_video_poster(source_path: Path, poster_path: Path) -> bool:
    if not needs_refresh(source_path, poster_path):
        return True

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


def main():
    print("\n🎬 Video delivery build")
    print(f"Root: {ROOT_DIR}")
    sys.stdout.flush()

    ensure_directories()

    if not VIDEO_ORIG_DIR.exists():
        print("ℹ️  No source video directory found. Skipping video delivery build.")
        return 0

    source_files = [
        path for path in sorted(VIDEO_ORIG_DIR.iterdir())
        if path.is_file() and path.suffix.lower() in SUPPORTED_VIDEO_EXTENSIONS
    ]

    if not source_files:
        print("ℹ️  No source videos found. Skipping video delivery build.")
        return 0

    if not check_ffmpeg():
        ffmpeg_name = get_ffmpeg_path()
        print(f"❌ ffmpeg not found ({ffmpeg_name})")
        print("   Video delivery generation requires ffmpeg during the full build.")
        return 1

    built = 0
    skipped = 0
    failed = 0
    posters_ready = 0

    for source_path in source_files:
        mode = delivery_mode_for(source_path)
        target_path = delivery_path_for(source_path)
        poster_path = poster_path_for(source_path)

        print(f"\n📼 Processing: {source_path.name}")
        print(f"  → Delivery route: {'MP4 copy' if mode == 'copy' else 'Transcode to MP4'}")

        if needs_refresh(source_path, target_path):
            if mode == 'copy':
                ok = copy_mp4(source_path, target_path)
            else:
                ok = transcode_to_mp4(source_path, target_path)

            if ok:
                built += 1
                print(f"  ✓ Wrote delivery file: {target_path.name}")
            else:
                failed += 1
                continue
        else:
            skipped += 1
            print(f"  ✓ Delivery file is up to date: {target_path.name}")

        if ensure_video_poster(source_path, poster_path):
            if poster_path.exists():
                posters_ready += 1
                print(f"  ✓ Poster is up to date: {poster_path.name}")
        elif poster_path.exists():
            posters_ready += 1

    print("\n" + "=" * 70)
    print(f"Built/updated video delivery files: {built}")
    print(f"Already up to date: {skipped}")
    print(f"Poster files ready: {posters_ready}")
    print(f"Failures: {failed}")

    return 1 if failed else 0


if __name__ == '__main__':
    sys.exit(main())
