# -*- coding: utf-8 -*-
"""PWA manifest stage — thin CLI wrapping site_health.chrome_pwa."""

from __future__ import print_function

import os
import sys

try:
    import stdio_utf8
    stdio_utf8.configure()
except Exception:
    pass

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
SITE_HEALTH_DIR = os.path.join(SCRIPT_DIR, 'site_health')
if SITE_HEALTH_DIR not in sys.path:
    sys.path.insert(0, SITE_HEALTH_DIR)

# When run as a standalone stage, chrome_pwa uses log.info → stdout; open a
# throwaway Activity path only if caller already began a site_health run.
import log as health_log  # noqa: E402
from chrome_pwa import generate_manifest  # noqa: E402


def generate_manifest_cli():
    """CLI entry used by build.py / legacy stage lists."""
    # Without an open site_health run, log.emit still prints to stdout.
    return generate_manifest()


# Back-compat name for imports that expect generate_manifest on this module.
generate_manifest = generate_manifest_cli


if __name__ == '__main__':
    sys.exit(0 if generate_manifest_cli() else 1)
