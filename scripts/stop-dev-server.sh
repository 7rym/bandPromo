#!/usr/bin/env bash
# Stop the local bandPromo PHP built-in server (Linux/macOS).
set -euo pipefail

PORT="${1:-8000}"
REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PID_FILE="$REPO_ROOT/log/dev-server.pid"
stopped=0

if command -v lsof >/dev/null 2>&1; then
  existing="$(lsof -tiTCP:"$PORT" -sTCP:LISTEN 2>/dev/null || true)"
  if [[ -n "$existing" ]]; then
    # shellcheck disable=SC2086
    kill $existing 2>/dev/null || true
    stopped=1
  fi
fi

if [[ -f "$PID_FILE" ]]; then
  old_pid="$(cat "$PID_FILE" 2>/dev/null || true)"
  if [[ -n "${old_pid:-}" ]] && kill -0 "$old_pid" 2>/dev/null; then
    kill "$old_pid" 2>/dev/null || true
    stopped=1
  fi
  rm -f "$PID_FILE"
fi

if [[ "$stopped" -eq 1 ]]; then
  echo "Stopped bandPromo dev server on port $PORT."
else
  echo "No bandPromo dev server is listening on port $PORT."
fi
