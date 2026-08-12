#!/usr/bin/env python3
"""Build a distributable bandPromo package from tracked repository files.

This script is intentionally manual/explicit. It does not run as part of every
build, and it should only be used for builds that qualify as distributable
packages.

App ZIP contents come from tracked repository files. Default-theme and Demo
Release media come from on-disk runtime media/ (never from git). CI seeds that
tree from the previous published default-theme package before running this
script; local developers typically already have media/ from setup.

Must stay runnable on CPython 3.6.9+ (hard floor for all scripts/).
"""

import argparse
import hashlib
import json
import os
from pathlib import Path
import shutil
import stat
import subprocess
import sys
from typing import Dict, List, Optional, Tuple
from zipfile import ZIP_DEFLATED, ZipFile


ROOT = Path(__file__).resolve().parent.parent
DEFAULT_OUTPUT_DIR = ROOT / "dist"
RUNTIME_TEMPLATES = ROOT / "biblioteca" / "templates" / "runtime"
MEDIA_HTACCESS_TEMPLATE = RUNTIME_TEMPLATES / "media.htaccess"

# Tracked files are the source of truth for the app package surface, but some
# tracked repository paths are still developer/repo infrastructure rather than
# operator install payload.
EXCLUDED_PREFIXES = (
    ".github/",
    ".vscode/",
    "dist/",
    "build/",
    "__pycache__/",
    "media/",
    "data/",
    "log/",
    "backups/",
)

EXCLUDED_FILES = {
    ".gitignore",
    ".editorconfig",
    "desktop.ini",
    ".htaccess",
    ".user.ini",
}

# Install-relative runtime protection files written from tracked templates.
RUNTIME_PROTECTION_MAP = (
    ("root.htaccess", ".htaccess"),
    ("user.ini", ".user.ini"),
    ("play.htaccess", "play/.htaccess"),
    ("deny-all.htaccess", "data/.htaccess"),
    ("deny-all.htaccess", "log/.htaccess"),
    ("deny-all.htaccess", "backups/.htaccess"),
    ("media.htaccess", "media/.htaccess"),
)

DEMO_RELEASE_TEMPLATE_PREFIX = "biblioteca/templates/demo-release-package/"

MEDIA_JUNK_NAMES = {
    "desktop.ini",
    "thumbs.db",
    ".ds_store",
}

MEDIA_SKIP_PATH_PARTS = (
    "/optimal/",
    "/master/",
    "/poster/",
    "/visual/delivery/",
    "/visual/original/",
    "/visual/master/",
)

REQUIRED_STARTER_MEDIA = (
    "media/.htaccess",
    "media/icons/bP-icons.zip",
    "media/special/bandPromo_logo.png",
    "media/special/bandPromo_cover.png",
    "media/audio/original/bandPromo_the_very_first_song.flac",
    "media/audio/original/bandPromo_the_second_song.flac",
    "media/video/original/bandPromo_rollercoaster.mp4",
)


def read_version():
    # type: () -> str
    version_text = (ROOT / "VERSION").read_text(encoding="utf-8").strip()
    if not version_text:
        raise RuntimeError("VERSION is empty.")
    return version_text


def slugify_version(version_text):
    # type: (str) -> str
    return version_text.lower().replace(" ", "-")


def git(*args):
    # type: (*str) -> str
    cmd = ["git"]
    cmd.extend(args)
    result = subprocess.run(
        cmd,
        cwd=str(ROOT),
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        universal_newlines=True,
        check=True,
    )
    return result.stdout.strip()


def tracked_files():
    # type: () -> List[str]
    output = git("ls-files")
    files = []  # type: List[str]
    for relative_path in output.splitlines():
        relative_path = relative_path.strip().replace("\\", "/")
        if not relative_path:
            continue
        if relative_path in EXCLUDED_FILES:
            continue
        if any(relative_path.startswith(prefix) for prefix in EXCLUDED_PREFIXES):
            continue
        files.append(relative_path)
    return files


def package_name(version_text):
    # type: (str) -> str
    return "bandpromo-{0}.zip".format(slugify_version(version_text))


def default_theme_package_name(version_text):
    # type: (str) -> str
    return "bandpromo-default-theme-{0}.zip".format(slugify_version(version_text))


def demo_release_package_name(version_text):
    # type: (str) -> str
    # Portable release package (ZIP bytes, .prp extension).
    return "bandPromo-demo-{0}.prp".format(slugify_version(version_text))


def demo_release_package_alias_name():
    # type: () -> str
    return "bandPromo-demo.prp"

def sha256_file(path):
    # type: (Path) -> str
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def ensure_runtime_protection_files():
    # type: () -> List[str]
    """Materialize Apache/PHP protection stubs from templates if missing."""
    paths = []  # type: List[str]
    for template_name, relative_path in RUNTIME_PROTECTION_MAP:
        template = RUNTIME_TEMPLATES / template_name
        if not template.is_file():
            raise RuntimeError(
                "Missing runtime template: {0}".format(
                    template.relative_to(ROOT).as_posix()
                )
            )
        target = ROOT / relative_path
        target.parent.mkdir(parents=True, exist_ok=True)
        if not target.is_file():
            target.write_bytes(template.read_bytes())
        paths.append(relative_path.replace("\\", "/"))
    return paths


def collect_media_files():
    # type: () -> List[str]
    """Collect packaging media from on-disk runtime media/ (never from git)."""
    media_root = ROOT / "media"
    files = []  # type: List[str]

    if media_root.is_dir():
        for path in media_root.rglob("*"):
            if not path.is_file():
                continue
            if path.name.lower() in MEDIA_JUNK_NAMES:
                continue
            relative = path.relative_to(ROOT).as_posix()
            lowered = "/{0}/".format(relative.lower())
            if any(part in lowered for part in MEDIA_SKIP_PATH_PARTS):
                continue
            files.append(relative)

    # Always emit the security rule from the tracked template.
    if not MEDIA_HTACCESS_TEMPLATE.is_file():
        raise RuntimeError(
            "Missing biblioteca/templates/runtime/media.htaccess — required to package media/.htaccess."
        )
    htaccess_path = ROOT / "media" / ".htaccess"
    htaccess_path.parent.mkdir(parents=True, exist_ok=True)
    if (not htaccess_path.is_file()) or (
        htaccess_path.read_bytes() != MEDIA_HTACCESS_TEMPLATE.read_bytes()
    ):
        htaccess_path.write_bytes(MEDIA_HTACCESS_TEMPLATE.read_bytes())
    if "media/.htaccess" not in files:
        files.append("media/.htaccess")

    return sorted(set(files))


def require_starter_media(media_files):
    # type: (List[str]) -> None
    present = set(media_files)
    missing = []  # type: List[str]
    for path in REQUIRED_STARTER_MEDIA:
        if path not in present or not (ROOT / path).is_file():
            missing.append(path)

    if missing:
        missing_list = ", ".join(missing)
        raise RuntimeError(
            "Required starter media is missing from on-disk media/. "
            "Seed local media via setup, or download/extract the latest "
            "bandpromo-default-theme-*.zip into the workspace before packaging. "
            "Missing: {0}".format(missing_list)
        )


def write_zip(archive_path, files):
    # type: (Path, List[str]) -> None
    if archive_path.exists():
        archive_path.unlink()

    with ZipFile(str(archive_path), "w", compression=ZIP_DEFLATED) as archive:
        for relative_path in files:
            source_path = ROOT / relative_path
            if not source_path.is_file():
                continue
            archive.write(str(source_path), relative_path)


def write_demo_release_zip(archive_path, package_template_files, media_files, version_text):
    # type: (Path, List[str], List[str], str) -> List[str]
    """Write Demo PRP (.prp = ZIP) with install-relative paths for docs + media/."""
    if archive_path.exists():
        archive_path.unlink()

    written = []  # type: List[str]
    with ZipFile(str(archive_path), "w", compression=ZIP_DEFLATED) as archive:
        for relative_path in package_template_files:
            if not relative_path.startswith(DEMO_RELEASE_TEMPLATE_PREFIX):
                continue
            source_path = ROOT / relative_path
            if not source_path.is_file():
                continue
            arcname = relative_path[len(DEMO_RELEASE_TEMPLATE_PREFIX) :]
            if arcname == "release-package-manifest.json":
                # Enrich tracked template with packaging metadata.
                try:
                    manifest = json.loads(source_path.read_text(encoding="utf-8"))
                except Exception:
                    manifest = {}
                if not isinstance(manifest, dict):
                    manifest = {}
                manifest["release_export_version"] = int(
                    manifest.get("release_export_version") or 1
                )
                manifest["format"] = "prp"
                manifest["platform_demo"] = True
                manifest["bandpromo_version"] = version_text
                manifest["exported_at"] = ""
                payload = json.dumps(manifest, indent=2, ensure_ascii=False) + "\n"
                archive.writestr(arcname, payload.encode("utf-8"))
            else:
                archive.write(str(source_path), arcname)
            written.append(arcname)
        for relative_path in media_files:
            source_path = ROOT / relative_path
            if not source_path.is_file():
                continue
            archive.write(str(source_path), relative_path)
            written.append(relative_path)
    return written


def build_zip(output_dir, package_url_base=None, manifest_url=None, release_tag=None):
    # type: (Path, Optional[str], Optional[str], Optional[str]) -> Tuple[Path, Path, Path, Dict[str, object]]
    version_text = read_version()
    files = tracked_files()
    if not files:
        raise RuntimeError("No tracked files found to package.")

    app_files = list(files)
    if not app_files:
        raise RuntimeError("No tracked application files found to package.")

    for relative_path in ensure_runtime_protection_files():
        if relative_path not in app_files:
            app_files.append(relative_path)

    # Install icons ship with the application package (Demo PRP is masters-only).
    for relative_path in (
        "media/icons/bP-icons.zip",
        "media/icons/apple-touch-icon.png",
        "media/icons/favicon-16x16.png",
        "media/icons/favicon-32x32.png",
        "media/icons/favicon-96x96.png",
        "media/icons/favicon.ico",
        "media/icons/favicon.svg",
        "media/icons/web-app-manifest-192x192.png",
        "media/icons/web-app-manifest-512x512.png",
    ):
        if (ROOT / relative_path).is_file() and relative_path not in app_files:
            app_files.append(relative_path)

    default_theme_files = collect_media_files()
    require_starter_media(default_theme_files)

    demo_template_files = [
        path for path in files if path.startswith(DEMO_RELEASE_TEMPLATE_PREFIX)
    ]
    # Also include untracked template files present on disk (fresh package docs).
    template_dir = ROOT / "biblioteca" / "templates" / "demo-release-package"
    if template_dir.is_dir():
        for path in template_dir.rglob("*"):
            if not path.is_file():
                continue
            relative = path.relative_to(ROOT).as_posix()
            if relative not in demo_template_files:
                demo_template_files.append(relative)
    if not demo_template_files:
        raise RuntimeError("No Demo Release package templates found.")

    output_dir.mkdir(parents=True, exist_ok=True)
    archive_path = output_dir / package_name(version_text)
    default_theme_archive_path = output_dir / default_theme_package_name(version_text)
    demo_release_archive_path = output_dir / demo_release_package_name(version_text)

    write_zip(archive_path, app_files)
    write_zip(default_theme_archive_path, default_theme_files)
    demo_paths = write_demo_release_zip(
        demo_release_archive_path,
        demo_template_files,
        default_theme_files,
        version_text,
    )
    demo_alias_path = output_dir / demo_release_package_alias_name()
    if demo_alias_path.exists():
        demo_alias_path.unlink()
    shutil.copy2(str(demo_release_archive_path), str(demo_alias_path))

    manifest = {  # type: Dict[str, object]
        "version": version_text,
        "package_file": archive_path.name,
        "sha256": sha256_file(archive_path),
        "git_commit": git("rev-parse", "HEAD"),
        "tracked_file_count": len(app_files),
        "generated_at_utc": git("show", "-s", "--format=%cI", "HEAD"),
        "requirements": {
            "php_min": "8.0.0",
            "sqlite_min": "3.8.0",
            "php_extensions": ["pdo_sqlite"],
            "php_classes": ["ZipArchive"],
        },
        "notes": [
            "This package is built only on explicit operator/developer action.",
            "Tracked runtime state such as web-config.json, .env, data/, log/, and media/ is excluded from git by repository policy.",
            "Apache/PHP protection stubs (.htaccess, .user.ini) are generated from biblioteca/templates/runtime/ during setup and packaging; they are not tracked at install paths.",
            "The entire /media tree is git-ignored. Default-theme media and the Demo PRP are packaged from on-disk runtime media/ (seeded by setup locally, or from the previous published default-theme ZIP in CI).",
            "Setup imports bandPromo-demo.prp (portable release package) for the locked platform demo campaign.",
        ],
        "default_theme_package": {
            "version": version_text,
            "package_file": default_theme_archive_path.name,
            "sha256": sha256_file(default_theme_archive_path),
            "tracked_file_count": len(default_theme_files),
            "paths": default_theme_files,
            "role": "required_setup_assets",
        },
        "demo_release_package": {
            "version": version_text,
            "package_file": demo_release_archive_path.name,
            "package_alias": demo_alias_path.name,
            "sha256": sha256_file(demo_release_archive_path),
            "tracked_file_count": len(demo_paths),
            "paths": demo_paths,
            "role": "platform_demo_prp",
            "format": "prp",
            "release_id": "bandpromo-demo",
            "release_export_version": 1,
            "platform_demo": True,
        },
    }

    if package_url_base:
        base = package_url_base.rstrip("/")
        manifest["package_url"] = base + "/" + archive_path.name
        default_theme = manifest["default_theme_package"]
        demo_release = manifest["demo_release_package"]
        assert isinstance(default_theme, dict)
        assert isinstance(demo_release, dict)
        default_theme["package_url"] = base + "/" + default_theme_archive_path.name
        demo_release["package_url"] = base + "/" + demo_release_archive_path.name
    if manifest_url:
        manifest["manifest_url"] = manifest_url
    if release_tag:
        manifest["release_tag"] = release_tag
        default_theme = manifest["default_theme_package"]
        demo_release = manifest["demo_release_package"]
        assert isinstance(default_theme, dict)
        assert isinstance(demo_release, dict)
        default_theme["release_tag"] = release_tag
        demo_release["release_tag"] = release_tag

    manifest_path = output_dir / "release-manifest.json"
    manifest_path.write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
    return archive_path, default_theme_archive_path, demo_release_archive_path, manifest


def parse_args():
    # type: () -> argparse.Namespace
    parser = argparse.ArgumentParser(description="Build a distributable bandPromo release package.")
    parser.add_argument(
        "--output-dir",
        default=str(DEFAULT_OUTPUT_DIR),
        help="Directory where the package ZIP and manifest should be written.",
    )
    parser.add_argument(
        "--clean",
        action="store_true",
        help="Remove the output directory before building.",
    )
    parser.add_argument(
        "--package-url-base",
        default=None,
        help="Optional base URL used to publish the package_url field into the manifest.",
    )
    parser.add_argument(
        "--manifest-url",
        default=None,
        help="Optional manifest URL published into the generated manifest.",
    )
    parser.add_argument(
        "--release-tag",
        default=None,
        help="Optional release tag recorded in the generated manifest.",
    )
    return parser.parse_args()


def handle_remove_readonly(func, path, exc_info):
    # type: (object, str, object) -> None
    exc_value = exc_info[1] if isinstance(exc_info, tuple) else exc_info
    if isinstance(exc_value, PermissionError):
        os.chmod(path, stat.S_IWRITE)
        func(path)
        return
    raise exc_value


def main():
    # type: () -> int
    args = parse_args()
    output_dir = Path(args.output_dir).resolve()

    if args.clean and output_dir.exists():
        # onerror is the Python 3.6 API; onexc is 3.12+.
        shutil.rmtree(str(output_dir), onerror=handle_remove_readonly)

    try:
        archive_path, default_theme_archive_path, demo_release_archive_path, manifest = build_zip(
            output_dir,
            package_url_base=args.package_url_base,
            manifest_url=args.manifest_url,
            release_tag=args.release_tag,
        )
    except Exception as exc:  # pragma: no cover - CLI failure path
        print("ERROR: {0}".format(exc), file=sys.stderr)
        return 1

    print("Built package: {0}".format(archive_path))
    print("Built default theme package: {0}".format(default_theme_archive_path))
    print("Built Demo PRP: {0}".format(demo_release_archive_path))
    print("Demo PRP alias: {0}".format(output_dir / demo_release_package_alias_name()))
    print("Version: {0}".format(manifest["version"]))
    print("SHA256: {0}".format(manifest["sha256"]))
    print("App package tracked files: {0}".format(manifest["tracked_file_count"]))
    print(
        "Default theme media files: {0}".format(
            manifest["default_theme_package"]["tracked_file_count"]
        )
    )
    print(
        "Demo PRP files: {0}".format(
            manifest["demo_release_package"]["tracked_file_count"]
        )
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
