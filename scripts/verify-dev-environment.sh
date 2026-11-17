#!/usr/bin/env bash
# Verify a restored bandPromo developer checkout (Linux/macOS).
set -euo pipefail

CHECKOUT_ROOT="${1:-.}"
CHECKOUT_ROOT="$(cd "$CHECKOUT_ROOT" && pwd)"

required=(
  VERSION
  web-config.json
  data/.setup_complete
  data/terces
  .git
  .cursor/rules/session-handoff.mdc
  docs/SESSION-HANDOFF.md
  scripts/session-start.sh
  scripts/start-dev-server.sh
)

echo "Verifying checkout: $CHECKOUT_ROOT"
fail=0
for rel in "${required[@]}"; do
  if [[ -e "$CHECKOUT_ROOT/$rel" ]]; then
    echo "  OK   $rel"
  else
    echo "  MISS $rel"
    fail=$((fail + 1))
  fi
done

if [[ -d "$CHECKOUT_ROOT/media" ]]; then
  echo "  OK   media/"
else
  echo "  WARN media/ missing"
fi

echo
echo "Tools:"
for cmd in php python3 ffmpeg git gh; do
  if command -v "$cmd" >/dev/null 2>&1; then
    echo "  OK   $cmd = $(command -v "$cmd")"
  else
    # python is acceptable fallback
    if [[ "$cmd" == "python3" ]] && command -v python >/dev/null 2>&1; then
      echo "  OK   python = $(command -v python) (python3 preferred)"
    else
      echo "  MISS $cmd"
      fail=$((fail + 1))
    fi
  fi
done

echo
echo "Linux notes:"
echo "  - Prefer bash scripts under scripts/*.sh — do not require PowerShell."
echo "  - scripts/vendor/ copied from Windows is ABI-wrong; rebuild on this host."
echo "  - Private files live in ~/.bandpromo/ (smoke suite + optional CA bundle)."

if [[ "$fail" -gt 0 ]]; then
  echo "VERIFY FAILED ($fail missing)"
  exit 1
fi
echo "VERIFY OK"
exit 0
