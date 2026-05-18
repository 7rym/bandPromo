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


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def build_zip(
    output_dir: Path,
    package_url_base: str | None = None,
    manifest_url: str | None = None,
    release_tag: str | None = None,
) -> tuple[Path, dict[str, object]]:
    version_text = read_version()
    files = tracked_files()
    if not files:
        raise RuntimeError("No tracked files found to package.")

    output_dir.mkdir(parents=True, exist_ok=True)
    archive_path = output_dir / package_name(version_text)
    if archive_path.exists():
        archive_path.unlink()

    with ZipFile(archive_path, "w", compression=ZIP_DEFLATED) as archive:
        for relative_path in files:
            source_path = ROOT / relative_path
            if not source_path.is_file():
                continue
            archive.write(source_path, relative_path)

    manifest = {
        "version": version_text,
        "package_file": archive_path.name,
        "sha256": sha256_file(archive_path),
        "git_commit": git("rev-parse", "HEAD"),
        "tracked_file_count": len(files),
        "generated_at_utc": git("show", "-s", "--format=%cI", "HEAD"),
        "notes": [
            "This package is built only on explicit operator/developer action.",
            "Tracked runtime state such as web-config.json, .env, data/, log/, and media/ is excluded from the package surface by repository policy.",
        ],
    }

    if package_url_base:
        manifest["package_url"] = package_url_base.rstrip("/") + "/" + archive_path.name
    if manifest_url:
        manifest["manifest_url"] = manifest_url
    if release_tag:
        manifest["release_tag"] = release_tag

    manifest_path = output_dir / "release-manifest.json"
    manifest_path.write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
    return archive_path, manifest


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
        archive_path, manifest = build_zip(
            output_dir,
            package_url_base=args.package_url_base,
            manifest_url=args.manifest_url,
            release_tag=args.release_tag,
        )
    except Exception as exc:  # pragma: no cover - CLI failure path
        print(f"ERROR: {exc}", file=sys.stderr)
        return 1

    print(f"Built package: {archive_path}")
    print(f"Version: {manifest['version']}")
    print(f"SHA256: {manifest['sha256']}")
    print(f"Tracked files: {manifest['tracked_file_count']}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())