#!/usr/bin/env python3
"""Prepare the durable demo-content GitHub release assets.

Writes:
  dist/demo-content/bandPromo-demo.prp
  dist/demo-content/demo-manifest.json

Source is an Admin-exported PRP (or a previously published .prp). Demo content
is updated only when campaign media/docs change — not on every app build.

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


ROOT = Path(__file__).resolve().parent.parent
DEFAULT_OUTPUT_DIR = ROOT / "dist" / "demo-content"
DEMO_CONTENT_TAG = "demo-content"
DEFAULT_PACKAGE_URL = (
    "https://github.com/7rym/bandPromo/releases/download/"
    + DEMO_CONTENT_TAG
    + "/bandPromo-demo.prp"
)
ALIAS_NAME = "bandPromo-demo.prp"


def sha256_file(path):
    # type: (Path) -> str
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def read_version():
    # type: () -> str
    return (ROOT / "VERSION").read_text(encoding="utf-8").strip()


def handle_remove_readonly(func, path, exc_info):
    # type: (object, str, object) -> None
    exc_value = exc_info[1] if isinstance(exc_info, tuple) else exc_info
    if isinstance(exc_value, PermissionError):
        os.chmod(path, stat.S_IWRITE)
        func(path)
        return
    raise exc_value


def parse_args():
    # type: () -> argparse.Namespace
    parser = argparse.ArgumentParser(
        description="Prepare durable demo-content release assets from a PRP file."
    )
    parser.add_argument(
        "--prp",
        required=True,
        help="Path to bandPromo-demo.prp (or .zip Admin export).",
    )
    parser.add_argument(
        "--output-dir",
        default=str(DEFAULT_OUTPUT_DIR),
        help="Directory for bandPromo-demo.prp + demo-manifest.json.",
    )
    parser.add_argument(
        "--clean",
        action="store_true",
        help="Remove the output directory before writing.",
    )
    parser.add_argument(
        "--package-url",
        default=DEFAULT_PACKAGE_URL,
        help="Published download URL recorded in demo-manifest.json.",
    )
    parser.add_argument(
        "--version",
        default=None,
        help="Optional content version label (defaults to app VERSION).",
    )
    parser.add_argument(
        "--publish",
        action="store_true",
        help="Create/update the GitHub release tag demo-content with prepared assets.",
    )
    parser.add_argument(
        "--repo",
        default="7rym/bandPromo",
        help="GitHub repository for --publish (owner/name).",
    )
    return parser.parse_args()


def publish_demo_content(output_dir, repo):
    # type: (Path, str) -> None
    prp_path = output_dir / ALIAS_NAME
    manifest_path = output_dir / "demo-manifest.json"
    if not prp_path.is_file() or not manifest_path.is_file():
        raise RuntimeError("Prepared demo-content assets are missing.")

    list_cmd = [
        "gh",
        "release",
        "view",
        DEMO_CONTENT_TAG,
        "--repo",
        repo,
    ]
    exists = subprocess.run(
        list_cmd,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        universal_newlines=True,
    )
    if exists.returncode != 0:
        create_cmd = [
            "gh",
            "release",
            "create",
            DEMO_CONTENT_TAG,
            str(prp_path),
            str(manifest_path),
            "--repo",
            repo,
            "--title",
            "bandPromo demo content",
            "--notes",
            "Durable Demo PRP for setup. Updated only when demo campaign content changes. "
            "Marked prerelease so /releases/latest stays on application builds.",
            "--prerelease",
        ]
        subprocess.run(create_cmd, check=True)
        return

    upload_cmd = [
        "gh",
        "release",
        "upload",
        DEMO_CONTENT_TAG,
        str(prp_path),
        str(manifest_path),
        "--repo",
        repo,
        "--clobber",
    ]
    subprocess.run(upload_cmd, check=True)


def main():
    # type: () -> int
    args = parse_args()
    source = Path(args.prp).expanduser().resolve()
    if not source.is_file():
        print("ERROR: PRP not found: {0}".format(source), file=sys.stderr)
        return 1

    output_dir = Path(args.output_dir).resolve()
    if args.clean and output_dir.exists():
        shutil.rmtree(str(output_dir), onerror=handle_remove_readonly)
    output_dir.mkdir(parents=True, exist_ok=True)

    dest = output_dir / ALIAS_NAME
    if dest.exists():
        dest.unlink()
    shutil.copy2(str(source), str(dest))

    version_text = (args.version or read_version()).strip()
    digest = sha256_file(dest)
    manifest = {
        "version": version_text,
        "package_file": ALIAS_NAME,
        "package_alias": ALIAS_NAME,
        "sha256": digest,
        "package_url": str(args.package_url).strip(),
        "role": "platform_demo_prp",
        "format": "prp",
        "release_id": "bandpromo-demo",
        "release_export_version": 1,
        "platform_demo": True,
        "release_tag": DEMO_CONTENT_TAG,
        "notes": [
            "Durable Demo PRP for setup. Updated only when demo campaign content changes.",
            "Application releases embed this manifest pointer; they do not re-upload the PRP.",
        ],
    }
    manifest_path = output_dir / "demo-manifest.json"
    manifest_path.write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")

    print("Prepared Demo PRP: {0}".format(dest))
    print("Bytes: {0}".format(dest.stat().st_size))
    print("SHA256: {0}".format(digest))
    print("Wrote: {0}".format(manifest_path))

    if args.publish:
        try:
            publish_demo_content(output_dir, args.repo)
        except Exception as exc:
            print("ERROR: publish failed: {0}".format(exc), file=sys.stderr)
            return 1
        print("Published GitHub release tag: {0}".format(DEMO_CONTENT_TAG))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
