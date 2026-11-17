#!/usr/bin/env bash
# Start PHP built-in server for local bandPromo (Linux/macOS).
set -euo pipefail

PORT="${1:-8000}"
BIND_HOST="${BIND_HOST:-127.0.0.1}"
TIMEZONE="${BANDPROMO_TIMEZONE:-Europe/Oslo}"

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
LOG_DIR="$REPO_ROOT/log"
SERVER_LOG="$LOG_DIR/dev-server.log"
PID_FILE="$LOG_DIR/dev-server.pid"

mkdir -p "$LOG_DIR"

if command -v lsof >/dev/null 2>&1; then
  existing="$(lsof -tiTCP:"$PORT" -sTCP:LISTEN 2>/dev/null || true)"
  if [[ -n "$existing" ]]; then
    # shellcheck disable=SC2086
    kill $existing 2>/dev/null || true
    sleep 0.5
  fi
elif [[ -f "$PID_FILE" ]]; then
  old_pid="$(cat "$PID_FILE" 2>/dev/null || true)"
  if [[ -n "${old_pid:-}" ]] && kill -0 "$old_pid" 2>/dev/null; then
    kill "$old_pid" 2>/dev/null || true
    sleep 0.5
  fi
fi

php_bin="$(command -v php)"
nohup "$php_bin" \
  -d "date.timezone=$TIMEZONE" \
  -d max_execution_time=0 \
  -d max_input_time=0 \
  -d memory_limit=1024M \
  -d upload_max_filesize=512M \
  -d post_max_size=520M \
  -S "${BIND_HOST}:${PORT}" \
  >"$SERVER_LOG" 2>&1 &
echo $! >"$PID_FILE"

sleep 0.75

if command -v lsof >/dev/null 2>&1; then
  if ! lsof -tiTCP:"$PORT" -sTCP:LISTEN >/dev/null 2>&1; then
    echo "Dev server failed to start on port $PORT." >&2
    tail -n 8 "$SERVER_LOG" 2>/dev/null || true
    exit 1
  fi
elif ! kill -0 "$(cat "$PID_FILE")" 2>/dev/null; then
  echo "Dev server failed to start on port $PORT." >&2
  tail -n 8 "$SERVER_LOG" 2>/dev/null || true
  exit 1
fi

echo "bandPromo dev server running at http://${BIND_HOST}:${PORT} (log: log/dev-server.log)"
