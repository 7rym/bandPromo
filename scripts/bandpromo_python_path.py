"""Site-local Python path bootstrap for bandPromo build scripts.

Operators never pip-install system packages. Dependencies live under
scripts/vendor/ (writable on the host) with offline wheels in
scripts/vendor-wheels/ as fallback.
"""

from __future__ import annotations

import sys
from pathlib import Path

SCRIPT_DIR = Path(__file__).resolve().parent
VENDOR_DIR = SCRIPT_DIR / "vendor"
VENDOR_WHEELS_DIR = SCRIPT_DIR / "vendor-wheels"


def ensure_vendor_on_sys_path() -> Path:
    """Prepend scripts/vendor to sys.path when present. Returns the vendor path."""
    vendor = VENDOR_DIR
    vendor_str = str(vendor)
    if vendor_str not in sys.path:
        sys.path.insert(0, vendor_str)
    return vendor


def python_tag() -> str:
    """PEP 425 interpreter tag for the running Python (e.g. cp312)."""
    return "cp{0}{1}".format(sys.version_info[0], sys.version_info[1])


def required_import_names():
    return ("PIL", "mutagen", "xxhash")
