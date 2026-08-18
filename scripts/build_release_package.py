#!/usr/bin/env python3
"""Build a distributable bandPromo application package from tracked repository files.

Operator-facing app releases ship:
  - bandPromo.zip (stable alias; also the package_file in the manifest)
  - release-manifest.json

Demo campaign content ships separately as bandPromo-demo.pcf on the durable
GitHub release tag `demo-content` (see scripts/prepare_demo_content_package.py).
App manifests point at that durable Demo PCF; they do not re-upload it.
Until the next demo-content publish, GitHub may still serve the legacy
bandPromo-demo.prp filename; setup prefers .pcf and falls back to .prp.

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
import urllib.request
from typing import Dict, List, Optional, Tuple
from zipfile import ZIP_DEFLATED, ZipFile


ROOT = Path(__file__).resolve().parent.parent
DEFAULT_OUTPUT_DIR = ROOT / "dist"
RUNTIME_TEMPLATES = ROOT / "biblioteca" / "templates" / "runtime"
TEMPLATE_ICONS_ZIP = ROOT / "biblioteca" / "templates" / "icons" / "bP-icons.zip"

APP_PACKAGE_ALIAS = "bandPromo.zip"
DEMO_CONTENT_TAG = "demo-content"
DEFAULT_DEMO_MANIFEST_URL = (
    "https://github.com/7rym/bandPromo/releases/download/"
    + DEMO_CONTENT_TAG
    + "/demo-manifest.json"
)

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

INSTALL_ICON_PATHS = (
    "media/icons/bP-icons.zip",
    "media/icons/apple-touch-icon.png",
    "media/icons/favicon-16x16.png",
    "media/icons/favicon-32x32.png",
    "media/icons/favicon-96x96.png",
    "media/icons/favicon.ico",
    "media/icons/web-app-manifest-192x192.png",
    "media/icons/web-app-manifest-512x512.png",
)

OPTIONAL_ICON_PATHS = (
    "media/icons/favicon.svg",
)

REQUIRED_ICON_BASENAMES = (
    "apple-touch-icon.png",
    "favicon-16x16.png",
    "favicon-32x32.png",
    "favicon-96x96.png",
    "favicon.ico",
    "web-app-manifest-192x192.png",
    "web-app-manifest-512x512.png",
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


def package_alias_name():
    # type: () -> str
    return APP_PACKAGE_ALIAS


def package_name(version_text):
    # type: (str) -> str
    # Kept for local debugging; operator releases publish the stable alias only.
    return "bandpromo-{0}.zip".format(slugify_version(version_text))


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


def ensure_install_icons_on_disk():
    # type: () -> None
    """Materialize install icons under media/icons/ from the tracked template zip."""
    icons_dir = ROOT / "media" / "icons"
    runtime_zip = icons_dir / "bP-icons.zip"
    template_zip = TEMPLATE_ICONS_ZIP

    icons_dir.mkdir(parents=True, exist_ok=True)
    if template_zip.is_file():
        shutil.copy2(str(template_zip), str(runtime_zip))
    elif not runtime_zip.is_file():
        raise RuntimeError(
            "Missing tracked icon seed at {0}. "
            "Place bP-icons.zip there (not under media/).".format(
                template_zip.relative_to(ROOT).as_posix()
            )
        )

    missing = [
        name
        for name in REQUIRED_ICON_BASENAMES
        if not (icons_dir / name).is_file()
    ]
    if not missing:
        return

    with ZipFile(str(runtime_zip), "r") as archive:
        archive.extractall(str(icons_dir))
    still_missing = [
        name
        for name in REQUIRED_ICON_BASENAMES
        if not (icons_dir / name).is_file()
    ]
    if still_missing:
        raise RuntimeError(
            "bP-icons.zip did not provide required install icons: {0}".format(
                ", ".join(still_missing)
            )
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


def load_demo_manifest(demo_manifest_url):
    # type: (Optional[str]) -> Optional[Dict[str, object]]
    if not demo_manifest_url:
        return None
    try:
        with urllib.request.urlopen(demo_manifest_url, timeout=60) as response:
            raw = response.read()
    except Exception as exc:
        raise RuntimeError(
            "Could not load durable Demo PCF manifest from {0}: {1}".format(
                demo_manifest_url, exc
            )
        )
    if raw.startswith(b"\xef\xbb\xbf"):
        raw = raw[3:]
    try:
        decoded = json.loads(raw.decode("utf-8"))
    except Exception as exc:
        raise RuntimeError(
            "Demo PCF manifest at {0} is not valid JSON: {1}".format(
                demo_manifest_url, exc
            )
        )
    if not isinstance(decoded, dict):
        raise RuntimeError("Demo PCF manifest must be a JSON object.")
    package_url = str(decoded.get("package_url") or "").strip()
    sha256 = str(decoded.get("sha256") or "").strip()
    package_file = str(decoded.get("package_file") or "bandPromo-demo.pcf").strip()
    package_alias = str(decoded.get("package_alias") or package_file).strip()
    role = str(decoded.get("role") or "platform_demo_pcf").strip()
    fmt = str(decoded.get("format") or "pcf").strip()
    if package_url == "" or sha256 == "":
        raise RuntimeError(
            "Demo PCF manifest is missing package_url and/or sha256."
        )
    return {
        "version": str(decoded.get("version") or "").strip(),
        "package_file": package_file,
        "package_alias": package_alias,
        "sha256": sha256,
        "package_url": package_url,
        "role": role,
        "format": fmt,
        "release_id": str(decoded.get("release_id") or "bandpromo-demo"),
        "release_export_version": int(decoded.get("release_export_version") or 1),
        "platform_demo": True,
        "release_tag": str(decoded.get("release_tag") or DEMO_CONTENT_TAG),
        "manifest_url": demo_manifest_url,
    }


def build_zip(
    output_dir,
    package_url_base=None,
    manifest_url=None,
    release_tag=None,
    demo_manifest_url=None,
    require_demo_manifest=False,
):
    # type: (Path, Optional[str], Optional[str], Optional[str], Optional[str], bool) -> Tuple[Path, Dict[str, object]]
    version_text = read_version()
    files = tracked_files()
    if not files:
        raise RuntimeError("No tracked files found to package.")

    app_files = list(files)
    for relative_path in ensure_runtime_protection_files():
        if relative_path not in app_files:
            app_files.append(relative_path)

    # Install icons ship with the application package (Demo PCF is masters-only).
    ensure_install_icons_on_disk()
    for relative_path in INSTALL_ICON_PATHS:
        if (ROOT / relative_path).is_file():
            if relative_path not in app_files:
                app_files.append(relative_path)
        else:
            raise RuntimeError(
                "Required install icon missing after ensure: {0}".format(relative_path)
            )
    for relative_path in OPTIONAL_ICON_PATHS:
        if (ROOT / relative_path).is_file() and relative_path not in app_files:
            app_files.append(relative_path)

    output_dir.mkdir(parents=True, exist_ok=True)
    archive_path = output_dir / package_alias_name()
    write_zip(archive_path, app_files)

    # Optional versioned copy for local inspection only (not uploaded by CI).
    versioned_path = output_dir / package_name(version_text)
    if versioned_path.exists():
        versioned_path.unlink()
    shutil.copy2(str(archive_path), str(versioned_path))

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
            "Application releases ship bandPromo.zip only. Demo campaign media ships as a Portable Campaign File (.pcf) on the durable demo-content GitHub release.",
            "Setup imports the Demo PCF for the locked platform demo campaign.",
        ],
    }

    if package_url_base:
        base = package_url_base.rstrip("/")
        manifest["package_url"] = base + "/" + archive_path.name
    if manifest_url:
        manifest["manifest_url"] = manifest_url
    if release_tag:
        manifest["release_tag"] = release_tag

    demo_url = demo_manifest_url
    if demo_url is None and require_demo_manifest:
        demo_url = DEFAULT_DEMO_MANIFEST_URL
    demo_package = None  # type: Optional[Dict[str, object]]
    if demo_url:
        demo_package = load_demo_manifest(demo_url)
    elif require_demo_manifest:
        raise RuntimeError("require_demo_manifest was set but no demo manifest URL was provided.")

    if demo_package is not None:
        manifest["demo_release_package"] = demo_package
    else:
        # Local builds may omit the durable demo pointer; CI publish requires it.
        manifest["demo_release_package"] = None

    manifest_path = output_dir / "release-manifest.json"
    manifest_path.write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
    return archive_path, manifest


def parse_args():
    # type: () -> argparse.Namespace
    parser = argparse.ArgumentParser(
        description="Build a distributable bandPromo application release package."
    )
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
    parser.add_argument(
        "--demo-manifest-url",
        default=None,
        help=(
            "URL of the durable demo-content demo-manifest.json to embed. "
            "Defaults to the GitHub demo-content release when --require-demo-manifest is set."
        ),
    )
    parser.add_argument(
        "--require-demo-manifest",
        action="store_true",
        help="Fail if the durable Demo PCF manifest cannot be loaded and embedded.",
    )
    parser.add_argument(
        "--skip-demo-manifest",
        action="store_true",
        help="Do not embed demo_release_package (local smoke builds).",
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

    demo_url = None  # type: Optional[str]
    require_demo = bool(args.require_demo_manifest)
    if args.skip_demo_manifest:
        demo_url = None
        require_demo = False
    elif args.demo_manifest_url:
        demo_url = args.demo_manifest_url
        require_demo = True
    elif args.require_demo_manifest:
        demo_url = DEFAULT_DEMO_MANIFEST_URL
        require_demo = True

    try:
        archive_path, manifest = build_zip(
            output_dir,
            package_url_base=args.package_url_base,
            manifest_url=args.manifest_url,
            release_tag=args.release_tag,
            demo_manifest_url=demo_url,
            require_demo_manifest=require_demo,
        )
    except Exception as exc:  # pragma: no cover - CLI failure path
        print("ERROR: {0}".format(exc), file=sys.stderr)
        return 1

    print("Built package: {0}".format(archive_path))
    print("Versioned copy: {0}".format(output_dir / package_name(str(manifest["version"]))))
    print("Version: {0}".format(manifest["version"]))
    print("SHA256: {0}".format(manifest["sha256"]))
    print("App package files: {0}".format(manifest["tracked_file_count"]))
    demo = manifest.get("demo_release_package")
    if isinstance(demo, dict):
        print("Demo PCF: {0}".format(demo.get("package_url")))
        print("Demo PCF SHA256: {0}".format(demo.get("sha256")))
    else:
        print("Demo PCF: (not embedded; use --require-demo-manifest for publish builds)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
