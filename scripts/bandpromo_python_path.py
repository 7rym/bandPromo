"""Site-local Python path bootstrap for bandPromo build scripts.

Operators never pip-install system packages. Dependencies live under
scripts/vendor/ (writable on the host) with offline wheels in
scripts/vendor-wheels/ as fallback.

Must stay importable on CPython 3.6.9+ (hard floor for all scripts/).
"""

from __future__ import print_function

import json
import os
import subprocess
import sys
from pathlib import Path

SCRIPT_DIR = Path(__file__).resolve().parent
VENDOR_DIR = SCRIPT_DIR / "vendor"
VENDOR_WHEELS_DIR = SCRIPT_DIR / "vendor-wheels"
REQUIREMENTS_FILE = SCRIPT_DIR / "requirements.txt"
VENDOR_TAG_FILE = VENDOR_DIR / ".bandpromo-python-tag"


def ensure_vendor_on_sys_path():
    """Prepend scripts/vendor to sys.path when present. Returns the vendor path."""
    vendor = VENDOR_DIR
    vendor_str = str(vendor)
    if vendor_str not in sys.path:
        sys.path.insert(0, vendor_str)
    return vendor


def python_tag():
    """PEP 425 interpreter tag for the running Python (e.g. cp312)."""
    return "cp{0}{1}".format(sys.version_info[0], sys.version_info[1])


def required_import_names():
    return ("PIL", "mutagen", "xxhash")


def read_vendor_python_tag():
    """Return the cpXX tag recorded when vendor was last bootstrapped, or ''."""
    try:
        if not VENDOR_TAG_FILE.is_file():
            return ""
        return str(VENDOR_TAG_FILE.read_text(encoding="utf-8")).strip()
    except Exception:
        return ""


def write_vendor_python_tag(tag=None):
    """Record which interpreter ABI scripts/vendor was built for."""
    tag = str(tag or python_tag()).strip() or python_tag()
    try:
        VENDOR_DIR.mkdir(parents=True, exist_ok=True)
        VENDOR_TAG_FILE.write_text(tag + "\n", encoding="utf-8")
        return tag
    except Exception:
        return ""


def vendor_tag_matches():
    recorded = read_vendor_python_tag()
    if not recorded:
        return False
    return recorded == python_tag()


def verify_required_imports():
    """Return (ok, missing_or_broken) after ensure_vendor_on_sys_path().

    A bare ``import PIL`` / ``import xxhash`` can succeed for incomplete vendor
    trees (pure-Python stubs without native extensions). Probe the bits delivery
    actually needs.
    """
    ensure_vendor_on_sys_path()
    missing = []

    try:
        from PIL import Image  # noqa: F401
    except Exception:
        missing.append("PIL")

    try:
        import mutagen  # noqa: F401
    except Exception:
        missing.append("mutagen")

    try:
        import xxhash
        # Native extension — pure stub packages fail here.
        if not hasattr(xxhash, "xxh3_64") and not hasattr(xxhash, "xxh64"):
            missing.append("xxhash")
        else:
            hasher = getattr(xxhash, "xxh3_64", None) or getattr(xxhash, "xxh64")
            hasher(b"bandpromo").hexdigest()
    except Exception:
        if "xxhash" not in missing:
            missing.append("xxhash")

    return (missing == [], missing)


def vendor_has_package_dirs():
    if not VENDOR_DIR.is_dir():
        return False
    for name in ("mutagen", "PIL", "xxhash"):
        if not (VENDOR_DIR / name).is_dir():
            return False
    return True


def vendor_status():
    """Structured vendor health for Environment / Site health / bootstrap."""
    ensure_vendor_on_sys_path()
    running = python_tag()
    recorded = read_vendor_python_tag()
    imports_ok, missing = verify_required_imports()
    tag_ok = bool(recorded) and recorded == running
    # Imports can pass via global site-packages while vendor is stale for this ABI.
    ok = bool(imports_ok and tag_ok and vendor_has_package_dirs())
    reasons = []
    if not recorded:
        reasons.append("no_vendor_tag")
    elif not tag_ok:
        reasons.append("python_tag_mismatch")
    if not imports_ok:
        reasons.append("imports_broken")
    if not vendor_has_package_dirs():
        reasons.append("vendor_dirs_missing")
    return {
        "ok": ok,
        "running_tag": running,
        "vendor_tag": recorded,
        "tag_ok": tag_ok,
        "imports_ok": imports_ok,
        "missing": list(missing),
        "vendor_dir": str(VENDOR_DIR),
        "python_executable": sys.executable,
        "python_version": sys.version.split()[0],
        "reasons": reasons,
    }


def _pip_install_to_vendor(extra_args):
    VENDOR_DIR.mkdir(parents=True, exist_ok=True)
    if not REQUIREMENTS_FILE.is_file():
        return None
    cmd = [
        sys.executable,
        "-m",
        "pip",
        "install",
        "-r",
        str(REQUIREMENTS_FILE),
        "--target",
        str(VENDOR_DIR),
        "--upgrade",
        "--only-binary=:all:",
    ] + list(extra_args or [])
    return subprocess.run(
        cmd,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        universal_newlines=True,
    )


def _wheel_matches_interpreter(filename, py_tag):
    """True when a .whl name is safe to unpack for this interpreter."""
    name = str(filename or '').lower()
    if not name.endswith('.whl'):
        return False
    # Pure-Python wheels (mutagen, etc.).
    if 'py3-none-any' in name or 'py2.py3-none-any' in name:
        return True
    # Must match this ABI exactly — never unpack an older cpXX over a newer host.
    if py_tag and py_tag.lower() in name:
        return True
    return False


def _extract_offline_wheels(py_tag):
    """Best-effort unpack of matching wheels when pip tags reject the host."""
    import zipfile

    if not VENDOR_WHEELS_DIR.is_dir():
        return []
    extracted = []
    for path in sorted(VENDOR_WHEELS_DIR.glob("*.whl")):
        if not _wheel_matches_interpreter(path.name, py_tag):
            continue
        try:
            with zipfile.ZipFile(str(path), "r") as archive:
                archive.extractall(str(VENDOR_DIR))
            extracted.append(path.name)
        except Exception:
            continue
    return extracted


def repair_vendor_packages(log_fn=None):
    """Reinstall scripts/vendor for the running interpreter. Returns vendor_status()."""

    def _log(message):
        if log_fn is not None:
            try:
                log_fn(message)
                return
            except Exception:
                pass
        try:
            print(message)
            sys.stdout.flush()
        except Exception:
            pass

    ensure_vendor_on_sys_path()
    status = vendor_status()
    if status["ok"]:
        return status

    py_tag = python_tag()
    _log(
        "Repairing scripts/vendor for {0} (was {1}; missing={2})".format(
            py_tag,
            status.get("vendor_tag") or "(none)",
            ",".join(status.get("missing") or []) or "tag/dirs",
        )
    )

    # Prefer network pip --target (correct ABI wheels). Offline extract is last resort.
    try:
        result = _pip_install_to_vendor([])
        if result is not None and result.returncode == 0:
            # Drop cached broken modules so verify sees the new install.
            for mod_name in list(sys.modules.keys()):
                if mod_name == "PIL" or mod_name.startswith("PIL.") or mod_name in (
                    "xxhash",
                    "mutagen",
                ) or mod_name.startswith("mutagen.") or mod_name.startswith("xxhash."):
                    try:
                        del sys.modules[mod_name]
                    except Exception:
                        pass
            imports_ok, _missing = verify_required_imports()
            if imports_ok:
                write_vendor_python_tag(py_tag)
                _log("Vendor repaired via pip for " + py_tag)
                return vendor_status()
    except Exception as exc:
        _log("pip --target failed: {0}".format(exc))

    if VENDOR_WHEELS_DIR.is_dir():
        try:
            result = _pip_install_to_vendor(
                ["--no-index", "--find-links", str(VENDOR_WHEELS_DIR)]
            )
            if result is not None and result.returncode == 0:
                for mod_name in list(sys.modules.keys()):
                    if mod_name == "PIL" or mod_name.startswith("PIL.") or mod_name in (
                        "xxhash",
                        "mutagen",
                    ) or mod_name.startswith("mutagen.") or mod_name.startswith("xxhash."):
                        try:
                            del sys.modules[mod_name]
                        except Exception:
                            pass
                imports_ok, _missing = verify_required_imports()
                if imports_ok:
                    write_vendor_python_tag(py_tag)
                    _log("Vendor repaired from vendor-wheels for " + py_tag)
                    return vendor_status()
        except Exception as exc:
            _log("offline pip failed: {0}".format(exc))

        try:
            extracted = _extract_offline_wheels(py_tag)
            if extracted:
                for mod_name in list(sys.modules.keys()):
                    if mod_name == "PIL" or mod_name.startswith("PIL.") or mod_name in (
                        "xxhash",
                        "mutagen",
                    ) or mod_name.startswith("mutagen.") or mod_name.startswith("xxhash."):
                        try:
                            del sys.modules[mod_name]
                        except Exception:
                            pass
                imports_ok, _missing = verify_required_imports()
                if imports_ok:
                    write_vendor_python_tag(py_tag)
                    _log(
                        "Vendor repaired by extracting {0} wheel(s) for {1}".format(
                            len(extracted), py_tag
                        )
                    )
                    return vendor_status()
                _log(
                    "Extracted {0} wheel(s) but imports still broken — refusing to stamp tag".format(
                        len(extracted)
                    )
                )
        except Exception as exc:
            _log("wheel extract failed: {0}".format(exc))

    imports_ok, missing = verify_required_imports()
    if imports_ok and vendor_has_package_dirs():
        write_vendor_python_tag(py_tag)
    _log(
        "Vendor repair incomplete for {0}: {1}".format(
            py_tag, ",".join(missing) if missing else "unknown"
        )
    )
    return vendor_status()


def ensure_vendor_ready(repair=True, log_fn=None):
    """Ensure vendor matches this Python. Optionally repair. Returns vendor_status()."""
    ensure_vendor_on_sys_path()
    status = vendor_status()
    if status["ok"] or not repair:
        return status
    return repair_vendor_packages(log_fn=log_fn)


def vendor_status_json():
    """Machine-readable status for PHP Environment probes."""
    return json.dumps(vendor_status(), ensure_ascii=False)
