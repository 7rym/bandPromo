#!/usr/bin/env python3
"""Build a distributable bandPromo package from tracked repository files.

This script is intentionally manual/explicit. It does not run as part of every
build, and it should only be used for builds that qualify as distributable
packages.
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

# Tracked files are the source of truth for the package surface, but some tracked
# repository paths are still developer/repo infrastructure rather than operator
# install payload.
EXCLUDED_PREFIXES = (
    ".github/",
    ".vscode/",
    "dist/",
    "build/",
    "__pycache__/",
)

EXCLUDED_FILES = {
    ".gitignore",
    "desktop.ini",
}

DEFAULT_THEME_PREFIXES = (
    "media/",
)

DEMO_RELEASE_TEMPLATE_PREFIX = "biblioteca/templates/demo-release-package/"


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


def split_release_files(files: list[str]) -> tuple[list[str], list[str]]:
    app_files: list[str] = []
    default_theme_files: list[str] = []

    for relative_path in files:
        if any(relative_path.startswith(prefix) for prefix in DEFAULT_THEME_PREFIXES):
            default_theme_files.append(relative_path)
        else:
            app_files.append(relative_path)

    return app_files, default_theme_files


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

    app_files, default_theme_files = split_release_files(files)
    if not app_files:
        raise RuntimeError("No tracked application files found to package.")
    if not default_theme_files:
        raise RuntimeError("No tracked default theme files found to package.")

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
            "Tracked runtime state such as web-config.json, .env, data/, and log/ is excluded from the package surface by repository policy.",
            "Tracked media starter assets ship as default_theme_package (dual-read) and inside demo_release_package (campaign docs + media).",
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
        "Default theme tracked files: "
        f"{manifest['default_theme_package']['tracked_file_count']}"
    )
    print(
        "Demo Release tracked files: "
        f"{manifest['demo_release_package']['tracked_file_count']}"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
