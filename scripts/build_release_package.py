#!/usr/bin/env python3
"""Build a distributable bandPromo package from tracked repository files.

This script is intentionally manual/explicit. It does not run as part of every
build, and it should only be used for builds that qualify as distributable
packages.

App ZIP contents come from tracked repository files. Default-theme and Demo
Release media come from on-disk runtime media/ (never from git). CI seeds that
tree from the previous published default-theme package before running this
script; local developers typically already have media/ from setup.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path
import shutil
import stat
import subprocess
import sys
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
)


def read_version() -> str:
    version_text = (ROOT / "VERSION").read_text(encoding="utf-8").strip()
    if not version_text:
        raise RuntimeError("VERSION is empty.")
    return version_text


def slugify_version(version_text: str) -> str:
    return version_text.lower().replace(" ", "-")


def git(*args: str) -> str:
    result = subprocess.run(
        ["git", *args],
        cwd=ROOT,
        capture_output=True,
        text=True,
        check=True,
    )
    return result.stdout.strip()


def tracked_files() -> list[str]:
    output = git("ls-files")
    files: list[str] = []
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


def package_name(version_text: str) -> str:
    return f"bandpromo-{slugify_version(version_text)}.zip"


def default_theme_package_name(version_text: str) -> str:
    return f"bandpromo-default-theme-{slugify_version(version_text)}.zip"


def demo_release_package_name(version_text: str) -> str:
    return f"bandpromo-demo-release-{slugify_version(version_text)}.zip"


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def ensure_runtime_protection_files() -> list[str]:
    """Materialize Apache/PHP protection stubs from templates if missing."""
    paths: list[str] = []
    for template_name, relative_path in RUNTIME_PROTECTION_MAP:
        template = RUNTIME_TEMPLATES / template_name
        if not template.is_file():
            raise RuntimeError(f"Missing runtime template: {template.relative_to(ROOT).as_posix()}")
        target = ROOT / relative_path
        target.parent.mkdir(parents=True, exist_ok=True)
        if not target.is_file():
            target.write_bytes(template.read_bytes())
        paths.append(relative_path.replace("\\", "/"))
    return paths


def collect_media_files() -> list[str]:
    """Collect packaging media from on-disk runtime media/ (never from git)."""
    media_root = ROOT / "media"
    files: list[str] = []

    if media_root.is_dir():
        for path in media_root.rglob("*"):
            if not path.is_file():
                continue
            if path.name.lower() in MEDIA_JUNK_NAMES:
                continue
            relative = path.relative_to(ROOT).as_posix()
            lowered = f"/{relative.lower()}/"
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


def require_starter_media(media_files: list[str]) -> None:
    present = set(media_files)
    missing: list[str] = []
    for path in REQUIRED_STARTER_MEDIA:
        if path not in present or not (ROOT / path).is_file():
            missing.append(path)

    if missing:
        missing_list = ", ".join(missing)
        raise RuntimeError(
            "Required starter media is missing from on-disk media/. "
            "Seed local media via setup, or download/extract the latest "
            "bandpromo-default-theme-*.zip into the workspace before packaging. "
            f"Missing: {missing_list}"
        )


def write_zip(archive_path: Path, files: list[str]) -> None:
    if archive_path.exists():
        archive_path.unlink()

    with ZipFile(archive_path, "w", compression=ZIP_DEFLATED) as archive:
        for relative_path in files:
            source_path = ROOT / relative_path
            if not source_path.is_file():
                continue
            archive.write(source_path, relative_path)


def write_demo_release_zip(
    archive_path: Path,
    package_template_files: list[str],
    media_files: list[str],
) -> list[str]:
    """Write Demo Release ZIP with install-relative paths for docs + media/."""
    if archive_path.exists():
        archive_path.unlink()

    written: list[str] = []
    with ZipFile(archive_path, "w", compression=ZIP_DEFLATED) as archive:
        for relative_path in package_template_files:
            if not relative_path.startswith(DEMO_RELEASE_TEMPLATE_PREFIX):
                continue
            source_path = ROOT / relative_path
            if not source_path.is_file():
                continue
            arcname = relative_path[len(DEMO_RELEASE_TEMPLATE_PREFIX) :]
            archive.write(source_path, arcname)
            written.append(arcname)
        for relative_path in media_files:
            source_path = ROOT / relative_path
            if not source_path.is_file():
                continue
            archive.write(source_path, relative_path)
            written.append(relative_path)
    return written


def build_zip(
    output_dir: Path,
    package_url_base: str | None = None,
    manifest_url: str | None = None,
    release_tag: str | None = None,
) -> tuple[Path, Path, Path, dict[str, object]]:
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
    )

    manifest: dict[str, object] = {
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
            "The entire /media tree is git-ignored. Default-theme and Demo Release media are packaged from on-disk runtime media/ (seeded by setup locally, or from the previous published default-theme ZIP in CI).",
            "Setup uses the shared Demo Release importer; operator package export follows after Release hub UX stabilizes.",
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
            "sha256": sha256_file(demo_release_archive_path),
            "tracked_file_count": len(demo_paths),
            "paths": demo_paths,
            "role": "demo_release_campaign",
            "release_id": "bandpromo-demo",
            "release_export_version": 1,
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


def parse_args() -> argparse.Namespace:
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


def handle_remove_readonly(func, path, exc_info) -> None:
    exc_value = exc_info[1] if isinstance(exc_info, tuple) else exc_info
    if isinstance(exc_value, PermissionError):
        os.chmod(path, stat.S_IWRITE)
        func(path)
        return
    raise exc_value


def main() -> int:
    args = parse_args()
    output_dir = Path(args.output_dir).resolve()

    if args.clean and output_dir.exists():
        shutil.rmtree(output_dir, onexc=handle_remove_readonly)

    try:
        archive_path, default_theme_archive_path, demo_release_archive_path, manifest = build_zip(
            output_dir,
            package_url_base=args.package_url_base,
            manifest_url=args.manifest_url,
            release_tag=args.release_tag,
        )
    except Exception as exc:  # pragma: no cover - CLI failure path
        print(f"ERROR: {exc}", file=sys.stderr)
        return 1

    print(f"Built package: {archive_path}")
    print(f"Built default theme package: {default_theme_archive_path}")
    print(f"Built Demo Release package: {demo_release_archive_path}")
    print(f"Version: {manifest['version']}")
    print(f"SHA256: {manifest['sha256']}")
    print(f"App package tracked files: {manifest['tracked_file_count']}")
    print(
        "Default theme media files: "
        f"{manifest['default_theme_package']['tracked_file_count']}"
    )
    print(
        "Demo Release package files: "
        f"{manifest['demo_release_package']['tracked_file_count']}"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
